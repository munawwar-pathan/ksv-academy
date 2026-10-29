<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
$page_title = 'Leave Management | KSV Academy HRMS';
$page_heading = 'Staff Leaves';
$page_subheading = 'Leave Entitlements, Requests & Approvals';
$active_menu = 'leaves';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Filters: search, status, type
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_type = trim($_GET['type'] ?? '');

// Handle Approve / Reject / Revert (Super Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $leave_id = intval($_POST['leave_id'] ?? 0);
        $status = $_POST['status'] ?? 'Pending';
        if ($leave_id > 0 && in_array($status, ['Approved', 'Rejected', 'Pending'])) {
            $stmt = $db->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $leave_id]);
            set_flash('success', "Leave request #{$leave_id} successfully marked as {$status}.");
            header('Location: ' . ADMIN_URL . '/leaves');
            exit;
        }
    }
}

// Handle Apply Leave (Super Admin on behalf of staff OR Staff for themselves)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_leave') {
    $staff_id = 0;
    if ($current_user['role'] === 'super_admin') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
    } else {
        $stmt_stf = $db->prepare("SELECT id FROM staff_profiles WHERE user_id = ? LIMIT 1");
        $stmt_stf->execute([$current_user['id']]);
        $staff = $stmt_stf->fetch();
        if ($staff) {
            $staff_id = $staff['id'];
        }
    }

    if ($staff_id > 0) {
        $ltype = trim($_POST['leave_type'] ?? 'Casual Leave');
        $sdate = trim($_POST['start_date'] ?? date('Y-m-d'));
        $edate = trim($_POST['end_date'] ?? date('Y-m-d'));
        $reason = trim($_POST['reason'] ?? '');
        $initial_status = ($current_user['role'] === 'super_admin' && isset($_POST['pre_approve'])) ? 'Approved' : 'Pending';

        $stmt = $db->prepare("INSERT INTO leave_requests (staff_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$staff_id, $ltype, $sdate, $edate, $reason, $initial_status]);
        set_flash('success', 'Leave application successfully recorded.');
        header('Location: ' . ADMIN_URL . '/leaves');
        exit;
    } else {
        set_flash('error', 'Please select a valid faculty member.');
    }
}

// Handle Delete Leave (Super Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin' && isset($_POST['action']) && $_POST['action'] === 'delete_leave') {
    $leave_id = intval($_POST['leave_id'] ?? 0);
    if ($leave_id > 0) {
        $db->prepare("DELETE FROM leave_requests WHERE id = ?")->execute([$leave_id]);
        set_flash('success', "Leave request record #{$leave_id} deleted.");
        header('Location: ' . ADMIN_URL . '/leaves');
        exit;
    }
}

// Query all staff profiles for dropdown
$staff_dropdown = $db->query("
    SELECT sp.id, sp.employee_code, sp.department, sp.designation, u.full_name 
    FROM staff_profiles sp 
    JOIN users u ON sp.user_id = u.id 
    ORDER BY u.full_name ASC
")->fetchAll();

// Pagination Settings (10, 20, 30, 40, 50)
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Build WHERE filter conditions
$where_clauses = ["1=1"];
$where_params = [];

// If staff role, restrict strictly to their own requests
if ($current_user['role'] === 'staff') {
    $where_clauses[] = "u.id = ?";
    $where_params[] = $current_user['id'];
}

if (!empty($search)) {
    $where_clauses[] = "(u.full_name LIKE ? OR sp.employee_code LIKE ? OR sp.department LIKE ? OR lr.reason LIKE ? OR lr.leave_type LIKE ?)";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
}

if (!empty($filter_status)) {
    $where_clauses[] = "lr.status = ?";
    $where_params[] = $filter_status;
}

if (!empty($filter_type)) {
    $where_clauses[] = "lr.leave_type = ?";
    $where_params[] = $filter_type;
}

$where_sql = implode(' AND ', $where_clauses);

// Count total matching leave requests
$count_stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM leave_requests lr
    JOIN staff_profiles sp ON lr.staff_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE {$where_sql}
");
$count_stmt->execute($where_params);
$total_records = intval($count_stmt->fetchColumn());
$total_count = $total_records;

// Calculate Pagination Bounds
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch Paginated Records
$query = "
    SELECT lr.*, u.full_name, u.username, u.email as user_email, u.phone as user_phone, u.avatar,
           sp.employee_code, sp.department, sp.designation, sp.photo
    FROM leave_requests lr
    JOIN staff_profiles sp ON lr.staff_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE {$where_sql}
    ORDER BY lr.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($query);
$param_idx = 1;
foreach ($where_params as $val) {
    $stmt->bindValue($param_idx++, $val);
}
$stmt->bindValue($param_idx++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($param_idx++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$leaves = $stmt->fetchAll();

// Helper to preserve active query parameters in pagination links
function get_leaves_pagination_url($target_page, $search, $filter_status, $filter_type, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_status)) $params['status'] = $filter_status;
    if (!empty($filter_type)) $params['type'] = $filter_type;
    if ($limit !== 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    $query_str = !empty($params) ? '?' . http_build_query($params) : '';
    return ADMIN_URL . '/leaves' . $query_str;
}

// Also compute overall global metrics for the KPI grid (Prepared Statement)
$stat_query = "SELECT status, COUNT(*) as cnt FROM leave_requests";
$stat_params = [];
if ($current_user['role'] === 'staff') {
    $stat_query .= " WHERE staff_id IN (SELECT id FROM staff_profiles WHERE user_id = ?)";
    $stat_params[] = $current_user['id'];
}
$stat_query .= " GROUP BY status";
$stmt_stat = $db->prepare($stat_query);
$stmt_stat->execute($stat_params);
$stats_raw = $stmt_stat->fetchAll(PDO::FETCH_KEY_PAIR);

$metric_total = array_sum($stats_raw);
$metric_pending = $stats_raw['Pending'] ?? 0;
$metric_approved = $stats_raw['Approved'] ?? 0;
$metric_rejected = $stats_raw['Rejected'] ?? 0;

// Helper for staff avatar detection
function get_staff_photo_url($sm) {
    if (!empty($sm['photo']) && $sm['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $sm['photo'])) {
        return $sm['photo'];
    }
    if (!empty($sm['avatar'])) {
        if (file_exists(__DIR__ . '/../../assets/imgs/faculties/' . $sm['avatar'])) {
            return '/assets/imgs/faculties/' . $sm['avatar'];
        }
        if (file_exists(__DIR__ . '/../../assets/imgs/' . $sm['avatar'])) {
            return '/assets/imgs/' . $sm['avatar'];
        }
    }
    $lower = strtolower($sm['full_name'] ?? '');
    if (strpos($lower, 'salam') !== false || strpos($lower, 'director') !== false) {
        return '/assets/imgs/faculties/founder-director.png';
    }
    if (strpos($lower, 'mishra') !== false || strpos($lower, 'rameshwar') !== false) {
        return '/assets/imgs/faculties/staff-mishra.png';
    }
    if (strpos($lower, 'priya') !== false || strpos($lower, 'desai') !== false) {
        return '/assets/imgs/faculties/staff-priya.png';
    }
    if (strpos($lower, 'amit') !== false || strpos($lower, 'verma') !== false) {
        return '/assets/imgs/faculties/staff-amit.png';
    }
    if (strpos($lower, 'aman') !== false) {
        return '/assets/imgs/faculties/mr-aman-kumar-soni.png';
    }
    if (strpos($lower, 'lubna') !== false) {
        return '/assets/imgs/faculties/mrs-lubna-khan.png';
    }
    if (strpos($lower, 'hafiza') !== false || strpos($lower, 'palikar') !== false) {
        return '/assets/imgs/faculties/hafiza-palikar.png';
    }
    if (strpos($lower, 'rafeek') !== false) {
        return '/assets/imgs/faculties/rafeek-khan.png';
    }
    if (!empty($sm['photo']) && $sm['photo'] !== '/assets/imgs/ksv-logo.jpg') {
        return $sm['photo'];
    }
    return '';
}

// Color palettes for avatars
$grad_palette = [
    'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)',
    'linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)',
    'linear-gradient(135deg, #059669 0%, #047857 100%)',
    'linear-gradient(135deg, #d97706 0%, #b45309 100%)',
    'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)',
];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Toolbar Single-Line Desktop Alignment & Spacing ── */
.roaster-toolbar-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.roaster-filter-form {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  flex: 1;
}

.roaster-toolbar-card .roaster-search-box {
  min-width: 220px;
  max-width: 260px;
  flex: 1 1 220px;
}

.leaves-filter-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: nowrap;
}

.leaves-filter-row .roaster-select-box {
  display: flex;
  align-items: center;
  position: relative;
}

.leaves-filter-row .roaster-select-box select {
  min-width: 150px;
  max-width: 185px;
}

.leaves-filter-btn-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.leaves-toolbar-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-left: auto;
}

/* Master Leaves Table (Matching staff-id and staff-periods) */
.leaves-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.leaves-table thead th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.70rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 10px 12px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.leaves-table th:first-child,
.leaves-table td:first-child {
  width: 25%;
  min-width: 240px;
}
.leaves-table .leaves-staff-name {
  white-space: nowrap;
}
.leaves-table .leaves-staff-dept {
  white-space: nowrap;
}
.leaves-table thead th:first-child,
.leaves-table tbody td:first-child {
  padding-left: 18px;
}
.leaves-table thead th:last-child,
.leaves-table tbody td:last-child {
  padding-right: 18px;
}
.leaves-table tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.80rem;
}
.leaves-table tbody tr {
  transition: background-color 0.15s ease;
}
.leaves-table tbody tr:hover {
  background-color: #f8fafc;
}

.emp-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0284c7;
  padding: 2px 7px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
}

.leave-type-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.76rem;
  font-weight: 700;
  font-family: 'Outfit', sans-serif;
  white-space: nowrap;
}
.leave-type-chip.casual {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1d4ed8;
}
.leave-type-chip.medical {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}
.leave-type-chip.academic {
  background: #f5f3ff;
  border: 1px solid #ddd6fe;
  color: #6d28d9;
}
.leave-type-chip.emergency {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #b45309;
}
.leave-type-chip.other {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #475569;
}

.duration-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  color: #0369a1;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  padding: 2px 7px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  white-space: nowrap;
}

.reason-quote-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-left: 3px solid #0284c7;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  color: #334155;
  line-height: 1.35;
  max-width: 175px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.status-badge-leave {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 0.74rem;
  font-weight: 700;
  white-space: nowrap;
}
.status-badge-leave.pending {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fde68a;
}
.status-badge-leave.approved {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}
.status-badge-leave.rejected {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.pulse-dot-amber {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #f59e0b;
  display: inline-block;
}

/* Decision Action Buttons */
.leave-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  font-size: 0.72rem;
  font-weight: 600;
  border-radius: 6px;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
}
.leave-action-btn:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}
.leave-action-btn.btn-approve {
  color: #059669;
  background: #ecfdf5;
  border-color: #a7f3d0;
}
.leave-action-btn.btn-approve:hover {
  background: #d1fae5;
  border-color: #6ee7b7;
  color: #047857;
}
.leave-action-btn.btn-reject {
  color: #dc2626;
  background: #fef2f2;
  border-color: #fecaca;
}
.leave-action-btn.btn-reject:hover {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #b91c1c;
}
.leave-action-btn.btn-delete:hover {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #dc2626;
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR LEAVE MANAGEMENT
   ══════════════════════════════════════════════════════════════════════ */

/* 1. Top KPI Stat Metrics Strip */
[data-theme="dark"] .stat-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .stat-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-number {
  color: #f8fafc !important;
}
[data-theme="dark"] .stat-card-progress {
  background: #334155 !important;
}
[data-theme="dark"] .stat-card.blue .stat-icon-wrapper {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
}
[data-theme="dark"] .stat-card.amber .stat-icon-wrapper {
  background: rgba(245, 158, 11, 0.15) !important;
  color: #fbbf24 !important;
  border-color: rgba(245, 158, 11, 0.3) !important;
}
[data-theme="dark"] .stat-card.green .stat-icon-wrapper {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
}
[data-theme="dark"] .stat-card.purple .stat-icon-wrapper {
  background: rgba(139, 92, 246, 0.15) !important;
  color: #a78bfa !important;
  border-color: rgba(139, 92, 246, 0.3) !important;
}
[data-theme="dark"] .stat-card-footer {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-card-footer span strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color:#0284c7"],
[data-theme="dark"] .stat-card-footer span[style*="color: #0284c7"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color:#d97706"],
[data-theme="dark"] .stat-card-footer span[style*="color: #d97706"] {
  color: #fbbf24 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color:#059669"],
[data-theme="dark"] .stat-card-footer span[style*="color: #059669"] {
  color: #34d399 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color:#7c3aed"],
[data-theme="dark"] .stat-card-footer span[style*="color: #7c3aed"] {
  color: #a78bfa !important;
}

/* 2. Filter & Search Toolbar */
[data-theme="dark"] .roaster-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .roaster-search-box {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .roaster-search-box:focus-within {
  border-color: #0092df !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .roaster-search-box i {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-search-box input {
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-search-box input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .roaster-search-box .search-clear-btn {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-search-box .search-clear-btn:hover {
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-select-box select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-select-box select:focus {
  background: #0f172a !important;
  border-color: #0092df !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .roaster-select-box select option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-select-box i {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-btn.secondary {
  background: #334155 !important;
  color: #f1f5f9 !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .roaster-btn.secondary:hover {
  background: #475569 !important;
  color: #ffffff !important;
}
[data-theme="dark"] .roaster-btn.ghost {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-btn.ghost:hover {
  color: #f8fafc !important;
  background: rgba(255, 255, 255, 0.05) !important;
}
[data-theme="dark"] .scholars-count-badge.leaves-records-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .scholars-count-badge.leaves-records-badge strong {
  color: #f8fafc !important;
}

/* 3. Leave Applications Directory Card & Header */
[data-theme="dark"] .leaves-directory-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .leaves-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .leaves-card-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .leaves-session-badge {
  background: #0f172a !important;
  color: #cbd5e1 !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .leaves-subtitle {
  color: #94a3b8 !important;
}

/* 4. Leaves Master Table */
[data-theme="dark"] .leaves-table thead th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .leaves-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .leaves-table tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.02) !important;
}
[data-theme="dark"] .staff-avatar-img {
  border-color: #334155 !important;
}
[data-theme="dark"] .leaves-staff-name {
  color: #f8fafc !important;
}
[data-theme="dark"] .leaves-staff-dept,
[data-theme="dark"] .leaves-designation {
  color: #94a3b8 !important;
}
[data-theme="dark"] .leaves-period-dates {
  color: #f8fafc !important;
}
[data-theme="dark"] .leaves-applied-date {
  color: #94a3b8 !important;
}

/* Badges, Chips & Reason Quotes */
[data-theme="dark"] .emp-code-pill.leaves-emp-code {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .duration-badge {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .reason-quote-box {
  background: #0f172a !important;
  border-color: #334155 !important;
  border-left-color: #0284c7 !important;
  color: #cbd5e1 !important;
}

/* Leave Category Chips */
[data-theme="dark"] .leave-type-chip.casual {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .leave-type-chip.medical {
  background: rgba(220, 38, 38, 0.15) !important;
  border-color: rgba(220, 38, 38, 0.35) !important;
  color: #f87171 !important;
}
[data-theme="dark"] .leave-type-chip.academic {
  background: rgba(124, 58, 237, 0.15) !important;
  border-color: rgba(124, 58, 237, 0.35) !important;
  color: #c084fc !important;
}
[data-theme="dark"] .leave-type-chip.emergency {
  background: rgba(245, 158, 11, 0.15) !important;
  border-color: rgba(245, 158, 11, 0.35) !important;
  color: #fbbf24 !important;
}
[data-theme="dark"] .leave-type-chip.other {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

/* Status Badges */
[data-theme="dark"] .status-badge-leave.approved {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
  color: #34d399 !important;
}
[data-theme="dark"] .status-badge-leave.pending {
  background: rgba(245, 158, 11, 0.15) !important;
  border-color: rgba(245, 158, 11, 0.3) !important;
  color: #fbbf24 !important;
}
[data-theme="dark"] .status-badge-leave.rejected {
  background: rgba(239, 68, 68, 0.15) !important;
  border-color: rgba(239, 68, 68, 0.3) !important;
  color: #f87171 !important;
}

/* Decision Action Buttons */
[data-theme="dark"] .leave-action-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .leave-action-btn:hover {
  background: #334155 !important;
  border-color: #475569 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .leave-action-btn.btn-approve {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
  color: #34d399 !important;
}
[data-theme="dark"] .leave-action-btn.btn-approve:hover {
  background: rgba(16, 185, 129, 0.25) !important;
  color: #6ee7b7 !important;
}
[data-theme="dark"] .leave-action-btn.btn-reject {
  background: rgba(239, 68, 68, 0.15) !important;
  border-color: rgba(239, 68, 68, 0.3) !important;
  color: #f87171 !important;
}
[data-theme="dark"] .leave-action-btn.btn-reject:hover {
  background: rgba(239, 68, 68, 0.25) !important;
  color: #fca5a5 !important;
}
[data-theme="dark"] .leave-action-btn.btn-delete:hover {
  background: rgba(239, 68, 68, 0.2) !important;
  border-color: #ef4444 !important;
  color: #f87171 !important;
}

/* Empty State */
[data-theme="dark"] .leaves-table tbody td div[style*="background: #f1f5f9"],
[data-theme="dark"] .leaves-table tbody td div[style*="background:#f1f5f9"] {
  background: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .leaves-table tbody td h4 {
  color: #f8fafc !important;
}
[data-theme="dark"] .leaves-table tbody td p {
  color: #94a3b8 !important;
}

/* 5. Pagination Controls */
[data-theme="dark"] .pagination-controls-wrap {
  background: #1e293b !important;
  border-top-color: #334155 !important;
}
[data-theme="dark"] .pagination-meta-group span {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pagination-meta-group span strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-limit-box label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pagination-limit-select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .pagination-btn:hover:not(.disabled) {
  background: #334155 !important;
  color: #f8fafc !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .pagination-btn.active {
  background: #0092df !important;
  border-color: #0092df !important;
  color: #ffffff !important;
}
[data-theme="dark"] .pagination-btn.disabled {
  background: #0f172a !important;
  border-color: #1e293b !important;
  color: #475569 !important;
}
[data-theme="dark"] .pagination-ellipsis {
  color: #64748b !important;
}
[data-theme="dark"] .pagination-legend-group {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pagination-legend-text {
  color: #94a3b8 !important;
}

/* 6. Modal: Apply for Faculty Leave */
[data-theme="dark"] .hrms-modal-overlay {
  background: rgba(0, 0, 0, 0.75) !important;
}
[data-theme="dark"] .leaves-modal-box {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7) !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .leaves-modal-header {
  border-bottom: 1px solid #334155 !important;
}
[data-theme="dark"] .leaves-modal-header h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] .leaves-modal-close {
  background: #334155 !important;
  color: #cbd5e1 !important;
  border: 1px solid #475569 !important;
}
[data-theme="dark"] .leaves-modal-close:hover {
  background: #475569 !important;
  color: #ffffff !important;
}
[data-theme="dark"] .leaves-field-label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .leaves-modal-box .hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .leaves-modal-box .hrms-input:focus {
  border-color: #0092df !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.25) !important;
}
[data-theme="dark"] .leaves-modal-box .hrms-input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .leaves-modal-box .hrms-input option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .leaves-modal-box input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(1);
}
[data-theme="dark"] .leaves-duration-banner {
  background: #0f172a !important;
  border-color: #0092df !important;
}
[data-theme="dark"] .leaves-duration-label {
  color: #38bdf8 !important;
}
[data-theme="dark"] #leaveDaysDisplay {
  color: #38bdf8 !important;
}
[data-theme="dark"] .leaves-modal-footer {
  border-top: 1px solid #334155 !important;
  padding-top: 16px !important;
}

/* ── Mobile Responsive Rules (Staff Leaves) ── */
@media (max-width: 768px) {
  .roaster-filter-form {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
    width: 100% !important;
  }

  .roaster-toolbar-card .roaster-search-box {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    flex: none !important;
  }

  .leaves-filter-row {
    grid-column: 1 / -1 !important;
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8px !important;
    width: 100% !important;
  }

  .leaves-filter-row .roaster-select-box {
    width: 100% !important;
    min-width: 0 !important;
  }

  .leaves-filter-row .roaster-select-box select {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    height: 40px !important;
    font-size: 0.78rem !important;
    padding: 8px 24px 8px 28px !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
  }

  .leaves-filter-btn-group {
    grid-column: 1 / -1 !important;
    display: flex !important;
    gap: 8px !important;
    width: 100% !important;
  }

  .leaves-filter-btn-group .roaster-btn {
    flex: 1 !important;
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
    font-size: 0.80rem !important;
    margin: 0 !important;
  }

  .leaves-toolbar-actions {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8px !important;
    width: 100% !important;
    margin-left: 0 !important;
    margin-top: 2px !important;
  }

  .leaves-records-badge {
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
    margin: 0 !important;
  }

  .leaves-apply-btn {
    width: 100% !important;
    height: 38px !important;
    max-width: 100% !important;
    justify-content: center !important;
    margin: 0 !important;
  }

  .leaves-table th:first-child,
  .leaves-table td:first-child {
    width: auto !important;
    min-width: 160px !important;
  }

  .leaves-table .leaves-staff-name {
    white-space: normal !important;
  }

  .leaves-table .leaves-staff-dept {
    white-space: normal !important;
  }
}
</style>

<!-- ══════════════════════════════════════════════════════
     TOP KPI METRIC CARDS (Standard Stat Grid)
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid no-print" style="margin-bottom: 22px;">
  
  <!-- Total Requests -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Applications</span>
          <span class="stat-number"><?php echo number_format($metric_total); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-plane-departure"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Institutional Records</span>
      <span style="color:#0284c7; font-weight:700;">Overall Log</span>
    </div>
  </div>

  <!-- Pending Approvals -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Pending Approvals</span>
          <span class="stat-number"><?php echo number_format($metric_pending); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-hourglass-half"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $metric_total > 0 ? round(($metric_pending / $metric_total) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Action Required</span>
      <span style="color:#d97706; font-weight:700;">Awaiting Review</span>
    </div>
  </div>

  <!-- Approved Leaves -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Sanctioned Leaves</span>
          <span class="stat-number"><?php echo number_format($metric_approved); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-circle-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $metric_total > 0 ? round(($metric_approved / $metric_total) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Granted Requests</span>
      <span style="color:#059669; font-weight:700;">Approved</span>
    </div>
  </div>

  <!-- Rejected / Declined -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Declined Requests</span>
          <span class="stat-number"><?php echo number_format($metric_rejected); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-circle-xmark"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $metric_total > 0 ? round(($metric_rejected / $metric_total) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Disallowed Leaves</span>
      <span style="color:#7c3aed; font-weight:700;">Archived</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     FILTER & SEARCH ROASTER TOOLBAR
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner">
    
    <!-- Left: Filter Form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/leaves" class="roaster-filter-form leaves-filter-form">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      
      <!-- Search Input -->
      <div class="roaster-search-box leaves-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search faculty name, code, reason..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_leaves_pagination_url(1, '', $filter_status, $filter_type, $limit)); ?>" class="search-clear-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <!-- Filter Dropdowns Row -->
      <div class="leaves-filter-row">
        <!-- Status Filter Dropdown -->
        <div class="roaster-select-box">
          <i class="fa-solid fa-filter"></i>
          <select name="status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending Approval</option>
            <option value="Approved" <?php echo $filter_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="Rejected" <?php echo $filter_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
          </select>
          <i class="fa-solid fa-chevron-down select-chevron"></i>
        </div>

        <!-- Leave Type Filter Dropdown -->
        <div class="roaster-select-box">
          <i class="fa-solid fa-tag"></i>
          <select name="type" onchange="this.form.submit()">
            <option value="">All Leave Types</option>
            <option value="Casual Leave" <?php echo $filter_type === 'Casual Leave' ? 'selected' : ''; ?>>Casual Leave (CL)</option>
            <option value="Medical Leave" <?php echo $filter_type === 'Medical Leave' ? 'selected' : ''; ?>>Medical Leave (ML)</option>
            <option value="Academic Duty" <?php echo $filter_type === 'Academic Duty' ? 'selected' : ''; ?>>Academic Duty</option>
            <option value="Emergency Leave" <?php echo $filter_type === 'Emergency Leave' ? 'selected' : ''; ?>>Emergency Leave</option>
          </select>
          <i class="fa-solid fa-chevron-down select-chevron"></i>
        </div>
      </div>

      <!-- Filter Action Buttons -->
      <div class="leaves-filter-btn-group">
        <button type="submit" class="roaster-btn secondary">
          <i class="fa-solid fa-sliders"></i> Filter
        </button>

        <?php if (!empty($search) || !empty($filter_status) || !empty($filter_type)): ?>
          <a href="<?php echo ADMIN_URL; ?>/leaves<?php echo $limit !== 10 ? '?limit=' . $limit : ''; ?>" class="roaster-btn ghost" title="Reset all filters">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Right: Applications Live Count & Apply Leave Button -->
    <div class="roaster-toolbar-actions leaves-toolbar-actions">
      <div class="scholars-count-badge leaves-records-badge">
        <span class="count-dot" style="background: #0284c7;"></span>
        <span><strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'Record' : 'Records'; ?></span>
      </div>

      <button type="button" onclick="openApplyLeaveModal();" class="roaster-add-btn leaves-apply-btn" title="Submit New Leave Application">
        <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
        <span>Apply Leave</span>
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     LEAVE APPLICATIONS HISTORY TABLE
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card leaves-directory-card" style="margin-bottom: 30px;">
  <div class="hrms-card-header leaves-card-header" style="border-bottom: 1px solid var(--hrms-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 22px;">
    <div class="leaves-title-block">
      <h3 class="card-title leaves-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.02rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-plane-departure" style="color: var(--hrms-primary);"></i>
        <span>Faculty Leave Applications &amp; Approvals Register</span>
        <span class="leaves-session-badge" style="font-size: 0.74rem; font-weight: 700; background: #f1f5f9; color: #475569; padding: 2px 10px; border-radius: 20px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p class="leaves-subtitle" style="margin: 4px 0 0; font-size: 0.8rem; color: #64748b;">
        Review institutional time-off requests, medical permissions, and academic duty exemptions.
      </p>
    </div>
  </div>

  <div class="table-responsive">
    <table class="leaves-table">
      <thead>
        <tr>
          <th>Applicant Faculty</th>
          <th>Leave Category</th>
          <th>Period &amp; Duration</th>
          <th>Reason &amp; Justification</th>
          <th>Applied Date</th>
          <th>Status</th>
          <?php if ($current_user['role'] === 'super_admin'): ?>
            <th style="text-align: right;">Decision Action</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($leaves)): ?>
          <tr>
            <td colspan="<?php echo $current_user['role'] === 'super_admin' ? '7' : '6'; ?>" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-calendar-xmark"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Leave Applications Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                No leave requests match the current filters.
              </p>
              <a href="<?php echo ADMIN_URL; ?>/leaves" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                <i class="fa-solid fa-rotate-left"></i> Reset All Filters
              </a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($leaves as $lr): ?>
            <?php 
              $st_photo = get_staff_photo_url($lr);
              $has_st_photo = !empty($st_photo);
              $f_letter = strtoupper(substr($lr['full_name'], 0, 1));
              $bg_color = $grad_palette[ord($f_letter) % count($grad_palette)];

              // Calculate days duration
              $d1 = new DateTime($lr['start_date']);
              $d2 = new DateTime($lr['end_date']);
              $diff = $d1->diff($d2)->days + 1;

              // Type class
              $ltype_lower = strtolower($lr['leave_type']);
              $chip_class = 'other';
              $type_icon = 'fa-tag';
              if (strpos($ltype_lower, 'casual') !== false) {
                $chip_class = 'casual';
                $type_icon = 'fa-umbrella-beach';
              } elseif (strpos($ltype_lower, 'medical') !== false || strpos($ltype_lower, 'sick') !== false) {
                $chip_class = 'medical';
                $type_icon = 'fa-notes-medical';
              } elseif (strpos($ltype_lower, 'academic') !== false || strpos($ltype_lower, 'duty') !== false) {
                $chip_class = 'academic';
                $type_icon = 'fa-graduation-cap';
              } elseif (strpos($ltype_lower, 'emergency') !== false) {
                $chip_class = 'emergency';
                $type_icon = 'fa-triangle-exclamation';
              }
            ?>
            <tr>
              
              <!-- 1. Applicant Profile -->
              <td>
                <div class="user-cell" style="display: flex; align-items: center; gap: 10px;">
                  <?php if ($has_st_photo): ?>
                    <img src="<?php echo htmlspecialchars($st_photo); ?>" alt="<?php echo htmlspecialchars($lr['full_name']); ?>" class="staff-avatar-img" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="staff-avatar-init" style="width: 38px; height: 38px; border-radius: 50%; background: <?php echo $bg_color; ?>; color: #fff; font-weight: 800; font-size: 14px; display: none; align-items: center; justify-content: center;">
                      <?php echo $f_letter; ?>
                    </div>
                  <?php else: ?>
                    <div class="staff-avatar-init" style="width: 38px; height: 38px; border-radius: 50%; background: <?php echo $bg_color; ?>; color: #fff; font-weight: 800; font-size: 14px; display: flex; align-items: center; justify-content: center;">
                      <?php echo $f_letter; ?>
                    </div>
                  <?php endif; ?>

                  <div>
                    <div class="cell-name leaves-staff-name" style="font-weight: 700; color: #0f172a; font-size: 0.83rem; line-height: 1.25;">
                      <?php echo htmlspecialchars($lr['full_name']); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px; margin-top: 2px;">
                      <span class="emp-code-pill leaves-emp-code"><?php echo htmlspecialchars($lr['employee_code']); ?></span>
                      <span class="cell-sub leaves-staff-dept" style="font-size: 0.70rem; color: #64748b;"><?php echo htmlspecialchars($lr['department']); ?></span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 2. Leave Category -->
              <td>
                <div>
                  <span class="leave-type-chip <?php echo $chip_class; ?>">
                    <i class="fa-solid <?php echo $type_icon; ?>"></i>
                    <span><?php echo htmlspecialchars($lr['leave_type']); ?></span>
                  </span>
                  <div class="cell-sub leaves-designation" style="font-size: 0.70rem; color: #64748b; margin-top: 2px;">
                    <?php echo htmlspecialchars($lr['designation']); ?>
                  </div>
                </div>
              </td>

              <!-- 3. Period & Duration -->
              <td>
                <div>
                  <div class="cell-name leaves-period-dates" style="font-size: 0.78rem; font-weight: 700; color: #0f172a; white-space: nowrap;">
                    <?php 
                      $s_ts = strtotime($lr['start_date']);
                      $e_ts = strtotime($lr['end_date']);
                      if (date('Y', $s_ts) === date('Y', $e_ts)) {
                        echo date('d M', $s_ts) . ' &ndash; ' . date('d M Y', $e_ts);
                      } else {
                        echo date('d M Y', $s_ts) . ' &ndash; ' . date('d M Y', $e_ts);
                      }
                    ?>
                  </div>
                  <div style="margin-top: 3px;">
                    <span class="duration-badge">
                      <i class="fa-solid fa-clock"></i>
                      <span><?php echo $diff; ?> <?php echo $diff === 1 ? 'Day' : 'Days'; ?></span>
                    </span>
                  </div>
                </div>
              </td>

              <!-- 4. Reason & Justification -->
              <td>
                <div class="reason-quote-box" title="<?php echo htmlspecialchars($lr['reason']); ?>">
                  <?php echo htmlspecialchars($lr['reason']); ?>
                </div>
              </td>

              <!-- 5. Applied Date -->
              <td>
                <div class="cell-sub leaves-applied-date" style="font-size: 0.76rem; font-weight: 600; color: #64748b; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
                  <i class="fa-regular fa-calendar" style="color: #94a3b8; font-size: 0.72rem;"></i>
                  <span><?php echo format_date($lr['created_at']); ?></span>
                </div>
              </td>

              <!-- 6. Status -->
              <td>
                <?php if ($lr['status'] === 'Approved'): ?>
                  <span class="status-badge-leave approved">
                    <i class="fa-solid fa-circle-check"></i> Approved
                  </span>
                <?php elseif ($lr['status'] === 'Rejected'): ?>
                  <span class="status-badge-leave rejected">
                    <i class="fa-solid fa-circle-xmark"></i> Rejected
                  </span>
                <?php else: ?>
                  <span class="status-badge-leave pending">
                    <span class="pulse-dot-amber"></span> Pending
                  </span>
                <?php endif; ?>
              </td>

              <!-- 7. Decision Action (Admin Only) -->
              <?php if ($current_user['role'] === 'super_admin'): ?>
                <td style="text-align: right; white-space: nowrap;">
                  <div style="display: inline-flex; gap: 5px; justify-content: flex-end; align-items: center;">
                    
                    <?php if ($lr['status'] === 'Pending'): ?>
                      <!-- Approve Form -->
                      <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                        <input type="hidden" name="status" value="Approved">
                        <button type="submit" class="leave-action-btn btn-approve" title="Approve Leave Request">
                          <i class="fa-solid fa-check"></i>
                          <span>Approve</span>
                        </button>
                      </form>

                      <!-- Reject Form -->
                      <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                        <input type="hidden" name="status" value="Rejected">
                        <button type="submit" class="leave-action-btn btn-reject" title="Reject Leave Request">
                          <i class="fa-solid fa-xmark"></i>
                          <span>Reject</span>
                        </button>
                      </form>
                    <?php else: ?>
                      <!-- Status Pill & Reset Option -->
                      <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                        <input type="hidden" name="status" value="Pending">
                        <button type="submit" class="leave-action-btn" title="Reopen / Reset to Pending Status">
                          <i class="fa-solid fa-rotate-left"></i>
                          <span>Reset</span>
                        </button>
                      </form>
                    <?php endif; ?>

                    <!-- Delete Leave Request Form -->
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" style="display: inline; margin: 0;" onsubmit="return confirm('Permanently remove this leave record?');">
                      <input type="hidden" name="action" value="delete_leave">
                      <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                      <button type="submit" class="leave-action-btn btn-delete" style="padding: 5px 8px;" title="Delete Record">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>

                  </div>
                </td>
              <?php endif; ?>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Table Footer Pagination & Per-Page Controls (Matches HRMS standard) -->
  <div class="pagination-controls-wrap">
    
    <!-- Left: Showing summary & Limit Selector -->
    <div class="pagination-meta-group">
      <span style="font-size: 0.82rem; color: #475569;">
        <?php if ($total_records > 0): ?>
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> leave applications
        <?php else: ?>
          Showing <strong>0</strong> leave applications
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_leaves_pagination_url(1, $search, $filter_status, $filter_type, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Leave Applications Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_leaves_pagination_url($page - 1, $search, $filter_status, $filter_type, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
           aria-label="Previous Page"
           title="Previous Page">
          <i class="fa-solid fa-chevron-left" style="font-size: 0.72rem;"></i>
        </a>

        <!-- Page Numbers -->
        <?php
          $range = 2;
          for ($p = 1; $p <= $total_pages; $p++):
            if ($p == 1 || $p == $total_pages || ($p >= $page - $range && $p <= $page + $range)):
        ?>
          <a href="<?php echo htmlspecialchars(get_leaves_pagination_url($p, $search, $filter_status, $filter_type, $limit)); ?>" 
             class="pagination-btn <?php echo $p === $page ? 'active' : ''; ?>">
            <?php echo $p; ?>
          </a>
        <?php 
            elseif (($p == 2 && $page - $range > 2) || ($p == $total_pages - 1 && $page + $range < $total_pages - 1)):
        ?>
          <span class="pagination-ellipsis">&hellip;</span>
        <?php 
            endif;
          endfor;
        ?>

        <!-- Next Page -->
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_leaves_pagination_url($page + 1, $search, $filter_status, $filter_type, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Legend / Session Status -->
    <div class="pagination-legend-group" style="font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 12px; margin-left: auto;">
      <span class="pagination-legend-text" style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
        Sanctioned
      </span>
      <span class="pagination-legend-text" style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
        Pending
      </span>
      <span class="pagination-legend-text" style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: #ef4444;"></span>
        Declined
      </span>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: Apply for Faculty Leave
     ══════════════════════════════════════════════════════ -->
<div id="applyLeaveModal" class="hrms-modal-overlay" onclick="if (event.target === this) closeApplyLeaveModal();" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px); overflow-y: auto; padding: 20px;">
  <div class="hrms-modal-content leaves-modal-box" style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 580px; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); max-height: 92vh; overflow-y: auto;">
    
    <!-- Modal Header -->
    <div class="leaves-modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 14px;">
      <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-calendar-plus" style="color: #0092df;"></i>
        <span>Faculty Leave Application</span>
      </h3>
      <button type="button" onclick="closeApplyLeaveModal();" class="leaves-modal-close" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">&times;</button>
    </div>

    <!-- Modal Form -->
    <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" id="applyLeaveForm">
      <input type="hidden" name="action" value="apply_leave">

      <!-- Faculty Member Selection (If Super Admin) -->
      <?php if ($current_user['role'] === 'super_admin'): ?>
        <div style="margin-bottom: 16px;">
          <label class="leaves-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Select Faculty Member *</label>
          <select name="staff_id" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600;">
            <option value="">-- Choose Faculty Member --</option>
            <?php foreach ($staff_dropdown as $st_opt): ?>
              <option value="<?php echo $st_opt['id']; ?>">
                <?php echo htmlspecialchars($st_opt['full_name']); ?> (<?php echo htmlspecialchars($st_opt['employee_code']); ?> &bull; <?php echo htmlspecialchars($st_opt['department']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <!-- Leave Category -->
      <div style="margin-bottom: 16px;">
        <label class="leaves-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Leave Category / Type *</label>
        <select name="leave_type" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600;">
          <option value="Casual Leave">Casual Leave (CL)</option>
          <option value="Medical Leave">Medical Leave (ML / Sick Leave)</option>
          <option value="Academic Duty">Academic / Examination Duty</option>
          <option value="Emergency Leave">Emergency / Urgent Leave</option>
          <option value="Maternity / Paternity Leave">Maternity / Paternity Leave</option>
          <option value="Unpaid Leave">Leave Without Pay (LWP)</option>
        </select>
      </div>

      <!-- Date Range (Start & End) -->
      <div class="leaves-modal-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
        <div>
          <label class="leaves-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Start Date *</label>
          <input type="date" id="leaveStartDate" name="start_date" value="<?php echo date('Y-m-d'); ?>" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;" onchange="recalculateLeaveDays();">
        </div>
        <div>
          <label class="leaves-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">End Date *</label>
          <input type="date" id="leaveEndDate" name="end_date" value="<?php echo date('Y-m-d'); ?>" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;" onchange="recalculateLeaveDays();">
        </div>
      </div>

      <!-- Live Duration Display Banner -->
      <div style="margin-bottom: 16px;">
        <div class="leaves-duration-banner" style="background: #f0f9ff; border: 1.5px dashed #0092df; border-radius: 10px; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center;">
          <span class="leaves-duration-label" style="font-weight: 700; font-size: 0.82rem; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">Computed Leave Duration:</span>
          <span id="leaveDaysDisplay" style="font-size: 1.15rem; font-weight: 800; color: #0092df; font-family: 'Outfit', sans-serif;">1 Day</span>
        </div>
      </div>

      <!-- Reason Textarea -->
      <div style="margin-bottom: 20px;">
        <label class="leaves-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Reason / Academic Contingency *</label>
        <textarea name="reason" rows="3" required placeholder="Describe the reason for leave and hand-over arrangements if any..." class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
      </div>

      <?php if ($current_user['role'] === 'super_admin'): ?>
        <!-- Pre-Approve Checkbox (Admin Only) -->
        <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
          <input type="checkbox" name="pre_approve" id="preApproveCheck" value="1" style="width: 16px; height: 16px; accent-color: #0092df;">
          <label for="preApproveCheck" class="leaves-field-label" style="font-size: 13px; font-weight: 600; color: #334155; cursor: pointer;">
            Directly mark this leave application as <strong>Approved</strong>
          </label>
        </div>
      <?php endif; ?>

      <!-- Footer Buttons -->
      <div class="leaves-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
        <button type="button" onclick="closeApplyLeaveModal();" class="roaster-btn secondary" style="padding: 8px 18px; font-size: 0.85rem; font-weight: 600;">Cancel</button>
        <button type="submit" class="roaster-add-btn" style="padding: 8px 20px; font-size: 0.85rem; font-weight: 700; border-radius: 8px;">
          <i class="fa-solid fa-paper-plane"></i>
          <span>Submit Application</span>
        </button>
      </div>

    </form>
  </div>
</div>

<script>
function openApplyLeaveModal() {
  const modal = document.getElementById('applyLeaveModal');
  if (modal) {
    modal.style.display = 'flex';
    recalculateLeaveDays();
  }
}

function closeApplyLeaveModal() {
  const modal = document.getElementById('applyLeaveModal');
  if (modal) {
    modal.style.display = 'none';
  }
}

function recalculateLeaveDays() {
  const sInput = document.getElementById('leaveStartDate');
  const eInput = document.getElementById('leaveEndDate');
  const display = document.getElementById('leaveDaysDisplay');
  if (!sInput || !eInput || !display) return;

  const s = new Date(sInput.value);
  const e = new Date(eInput.value);
  if (isNaN(s) || isNaN(e)) return;

  if (e < s) {
    display.textContent = 'Invalid (End before Start)';
    display.style.color = '#dc2626';
    return;
  }

  const diffTime = Math.abs(e - s);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
  display.textContent = diffDays + (diffDays === 1 ? ' Day' : ' Days');
  display.style.color = '#0092df';
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeApplyLeaveModal();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
