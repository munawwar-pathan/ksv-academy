<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'parent', 'student']);
if (in_array($current_user['role'], ['student', 'parent'])) {
    require_menu_permission('fees');
}
$page_title = 'Fee Receipts & Dues';
$page_heading = 'Tuition Fee Receipts & Accounts Ledger';
$page_subheading = 'Payment History, Installment Slips & Outstanding Due Breakdown';
$active_menu = 'fees';

$db = get_db_connection();
$school_settings = get_school_settings();
$active_session = get_active_session();

// ── Handle Collect Fee Payment (Super Admin) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $action = $_POST['action'] ?? '';
    if ($action === 'collect_fee') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $method = trim($_POST['payment_method'] ?? 'Online UPI');
        $remarks = trim($_POST['remarks'] ?? 'Tuition Installment');

        if ($student_id > 0 && $amount > 0) {
            $rec_no = 'REC-2026-0' . rand(200, 999);
            $stmt = $db->prepare("INSERT INTO fee_payments (student_id, receipt_no, amount, payment_method, payment_date, remarks) VALUES (?, ?, ?, ?, CURDATE(), ?)");
            $stmt->execute([$student_id, $rec_no, $amount, $method, $remarks]);

            // Update student paid_fees
            $db->prepare("UPDATE students SET paid_fees = paid_fees + ? WHERE id = ?")->execute([$amount, $student_id]);

            set_flash('success', "Payment of ₹" . number_format($amount, 2) . " recorded! Receipt Generated: {$rec_no}");
            header('Location: ' . PARENT_URL . '/fees' . ($student_id ? '?student_id=' . $student_id : ''));
            exit;
        }
    }
}

// ── Resolve Active Student Record ──
$student = null;

// Check for Impersonation Session
if (!empty($_SESSION['impersonated_student'])) {
    $s_id = intval($_SESSION['impersonated_student']['id']);
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$s_id]);
    $student = $stmt->fetch();
}

// 1. Direct query parameter override (?student_id=X)
$target_sid = intval($_GET['student_id'] ?? 0);
if ($target_sid > 0) {
    if ($current_user['role'] === 'super_admin') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$target_sid]);
        $student = $stmt->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    } elseif ($current_user['role'] === 'parent') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$target_sid, $current_user['id']]);
        $student = $stmt->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    }
}

// 2. Role Specific Resolution
$parent_children = [];
if ($current_user['role'] === 'student') {
    // A student always views their own record
    $stmt = $db->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$current_user['id']]);
    $student = $stmt->fetch();
    if (!$student) {
        $stmt = $db->prepare("SELECT * FROM students WHERE student_name LIKE ? OR roll_no = ? LIMIT 1");
        $stmt->execute(['%' . $current_user['full_name'] . '%', $current_user['username']]);
        $student = $stmt->fetch();
    }
    if ($student) {
        $_SESSION['active_student_id'] = $student['id'];
    }
} elseif ($current_user['role'] === 'parent') {
    // Fetch all children linked to this parent
    $c_stmt = $db->prepare("SELECT id, student_name, roll_no, standard, photo, total_fees, paid_fees FROM students WHERE parent_user_id = ? ORDER BY id ASC");
    $c_stmt->execute([$current_user['id']]);
    $parent_children = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($student) && !empty($_SESSION['active_student_id'])) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['active_student_id'], $current_user['id']]);
        $student = $stmt->fetch();
    }
    if (empty($student) && !empty($parent_children)) {
        $student = $db->query("SELECT * FROM students WHERE id = {$parent_children[0]['id']}")->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    }
} else {
    // Super admin
    if (empty($student) && !empty($_SESSION['active_student_id'])) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['active_student_id']]);
        $student = $stmt->fetch();
    }
}

// Final Fallback if no student resolved
if (!$student) {
    $student = $db->query("SELECT * FROM students ORDER BY id ASC LIMIT 1")->fetch();
    if ($student) $_SESSION['active_student_id'] = $student['id'];
}

$student_id = intval($student['id'] ?? 1);

// ── Financial Metric Calculations for Active Student ──
$course_total_fee = floatval($student['total_fees'] ?? 0);

$sum_stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM fee_payments WHERE student_id = ?");
$sum_stmt->execute([$student_id]);
$total_paid = floatval($sum_stmt->fetchColumn() ?: 0);
$outstanding_due = max(0, $course_total_fee - $total_paid);
$paid_pct = $course_total_fee > 0 ? min(100, round(($total_paid / $course_total_fee) * 100)) : 100;
$due_pct = max(0, 100 - $paid_pct);

$count_vouchers_stmt = $db->prepare("SELECT COUNT(*) FROM fee_payments WHERE student_id = ?");
$count_vouchers_stmt->execute([$student_id]);
$total_vouchers_all = intval($count_vouchers_stmt->fetchColumn() ?: 0);

// Super Admin options for recording payment
$student_options = [];
if ($current_user['role'] === 'super_admin') {
    $student_options = $db->query("SELECT id, student_name, roll_no, standard, (total_fees - paid_fees) as due FROM students WHERE (total_fees - paid_fees) > 0 ORDER BY student_name ASC")->fetchAll();
}

// ── Search & Filter Parameters ──
$search = trim($_GET['search'] ?? '');
$filter_method = trim($_GET['method'] ?? '');
$filter_year = trim($_GET['year'] ?? '');

$allowed_limits = [5, 10, 20, 30, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Build Filter SQL
$where = ["fp.student_id = ?"];
$params = [$student_id];

if (!empty($search)) {
    $where[] = "(fp.receipt_no LIKE ? OR fp.payment_method LIKE ? OR fp.remarks LIKE ? OR fp.payment_date LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (!empty($filter_method)) {
    $where[] = "fp.payment_method LIKE ?";
    $params[] = "%{$filter_method}%";
}
if (!empty($filter_year)) {
    $where[] = "YEAR(fp.payment_date) = ?";
    $params[] = $filter_year;
}

$where_sql = implode(' AND ', $where);

// Count Total Matching Records
$count_query = "
    SELECT COUNT(*) 
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    WHERE {$where_sql}
";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = intval($count_stmt->fetchColumn() ?: 0);

// Calculate Pagination Bounds
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch Paginated Payments
$query = "
    SELECT fp.*, s.student_name, s.roll_no, s.standard, s.parent_name, s.parent_phone, s.total_fees
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    WHERE {$where_sql}
    ORDER BY fp.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($query);
$b_idx = 1;
foreach ($params as $p) {
    $stmt->bindValue($b_idx++, $p);
}
$stmt->bindValue($b_idx++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($b_idx++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate sum of filtered records
$filtered_sum = 0;
foreach ($payments as $p_item) {
    $filtered_sum += floatval($p_item['amount']);
}

// Helper for Pagination URL Generation
function get_parent_fees_pagination_url($target_page, $search, $filter_method, $filter_year, $limit, $student_id = 0) {
    $params = [];
    if (!empty($student_id)) $params['student_id'] = $student_id;
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_method)) $params['method'] = $filter_method;
    if (!empty($filter_year)) $params['year'] = $filter_year;
    if ($limit != 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return PARENT_URL . '/fees' . (!empty($params) ? '?' . http_build_query($params) : '');
}

$years_list = [2027, 2026, 2025, 2024];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Parent / Student Fee Ledger Custom Styles (Simple, Sober & Premium) ── */
.periods-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}
@media (max-width: 1024px) {
  .periods-stat-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 600px) {
  .periods-stat-grid {
    grid-template-columns: 1fr;
  }
}

.periods-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
  overflow: hidden;
  margin-bottom: 30px;
}

.periods-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  background: #ffffff;
}

.periods-card-footer {
  padding: 14px 20px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 14px;
}

.roaster-toolbar-card .roaster-search-box {
  min-width: 180px;
  max-width: 240px;
}
.roaster-toolbar-card .roaster-select-box select {
  min-width: 135px;
}

.staff-id-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: auto;
}
.staff-id-table thead th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.70rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 11px 12px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.staff-id-table tbody td {
  padding: 11px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.82rem;
  color: #1e293b;
}
.staff-id-table tbody tr {
  transition: background-color 0.15s ease;
}
.staff-id-table tbody tr:hover {
  background-color: #f8fafc;
}
.staff-id-table tbody tr:last-child td {
  border-bottom: none;
}

/* Badge & Tag Elements */
.sid-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0284c7;
  padding: 2px 7px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.74rem;
  font-weight: 700;
  white-space: nowrap;
}

.sid-status-verified {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  font-size: 0.70rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 14px;
  white-space: nowrap;
}

.sid-mode-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.74rem;
  color: #475569;
  font-weight: 600;
  background: #f1f5f9;
  padding: 2px 7px;
  border-radius: 5px;
  border: 1px solid #e2e8f0;
  white-space: nowrap;
}

/* Multi-child Selector Pill Banner */
.child-selector-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  padding: 10px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-bottom: 20px;
}

/* Impersonation Banner */
.impersonation-notice-bar {
  background: #fefce8;
  border: 1px solid #fef08a;
  border-radius: 10px;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  font-size: 0.82rem;
  color: #854d0e;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS FOR STUDENT & PARENT FEE LEDGER
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  /* Impersonation & Child Selector Banners */
  .impersonation-notice-bar {
    padding: 10px 14px !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 8px !important;
    margin-bottom: 16px !important;
  }
  .impersonation-notice-bar .roaster-btn {
    width: 100% !important;
    text-align: center !important;
    justify-content: center !important;
    padding: 6px 12px !important;
    box-sizing: border-box !important;
  }

  .child-selector-banner {
    padding: 10px 14px !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 8px !important;
    margin-bottom: 16px !important;
  }
  .child-selector-banner > div {
    width: 100% !important;
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
  .child-selector-banner .roaster-btn {
    flex: 1 1 auto !important;
    text-align: center !important;
    justify-content: center !important;
    padding: 6px 10px !important;
    font-size: 0.72rem !important;
  }

  /* 4 Financial KPI Stat Cards */
  .periods-stat-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
    margin-bottom: 16px !important;
  }

  .stat-card {
    padding: 12px 14px !important;
    border-radius: 10px !important;
  }

  .stat-label {
    font-size: 0.68rem !important;
  }

  .stat-number {
    font-size: 1.15rem !important;
    margin-top: 2px !important;
  }

  .stat-icon-wrapper {
    width: 32px !important;
    height: 32px !important;
    font-size: 0.88rem !important;
  }

  .stat-card-progress {
    margin: 8px 0 6px !important;
    height: 4px !important;
  }

  .stat-card-footer {
    font-size: 0.68rem !important;
    margin-top: 6px !important;
    padding-top: 6px !important;
    flex-wrap: wrap !important;
    gap: 4px !important;
  }

  /* Filter & Search Toolbar */
  .roaster-toolbar-card {
    padding: 12px 14px !important;
    margin-bottom: 16px !important;
    border-radius: 10px !important;
  }

  .roaster-toolbar-inner {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 12px !important;
  }

  .roaster-filter-form {
    width: 100% !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
  }

  .roaster-toolbar-card .roaster-search-box {
    min-width: 100% !important;
    max-width: 100% !important;
    width: 100% !important;
    box-sizing: border-box !important;
  }

  .roaster-toolbar-card .roaster-select-box {
    width: 100% !important;
  }

  .roaster-toolbar-card .roaster-select-box select {
    width: 100% !important;
    min-width: 100% !important;
    box-sizing: border-box !important;
  }

  .roaster-toolbar-actions {
    width: 100% !important;
    justify-content: space-between !important;
  }

  .roaster-add-btn {
    flex: 1 !important;
    justify-content: center !important;
    text-align: center !important;
  }

  /* Periods Card */
  .periods-card {
    border-radius: 12px !important;
    margin-bottom: 20px !important;
  }

  .periods-card-header {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 10px !important;
  }

  .periods-card-header > div:last-child {
    width: 100% !important;
    box-sizing: border-box !important;
    display: flex !important;
    justify-content: space-between !important;
  }

  /* Table & Scrolling */
  .table-responsive {
    width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    margin: 0 !important;
  }

  .staff-id-table {
    min-width: 720px !important;
  }

  .staff-id-table thead th {
    padding: 8px 8px !important;
    font-size: 0.68rem !important;
  }

  .staff-id-table tbody td {
    padding: 8px 8px !important;
    font-size: 0.76rem !important;
  }

  .sid-code-pill,
  .sid-mode-badge,
  .sid-status-verified {
    font-size: 0.70rem !important;
    padding: 2px 6px !important;
  }

  /* Card Footer: Pagination & Stats */
  .periods-card-footer {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 12px !important;
  }

  .periods-card-footer > div:first-child {
    width: 100% !important;
    justify-content: space-between !important;
    font-size: 0.76rem !important;
    gap: 8px !important;
  }

  .pagination-nav {
    width: 100% !important;
    justify-content: center !important;
    margin: 4px 0 !important;
  }

  .periods-card-footer > div:last-child {
    width: 100% !important;
    margin-left: 0 !important;
    justify-content: space-between !important;
    font-size: 0.72rem !important;
    border-top: 1px solid #e2e8f0;
    padding-top: 8px !important;
    flex-wrap: wrap !important;
  }
}

/* Micro Devices (<= 480px) */
@media (max-width: 480px) {
  .hrms-modal-card {
    width: 94% !important;
    max-width: 94% !important;
    margin: 16px auto !important;
  }

  .hrms-modal-body > div[style*="grid-template-columns"] {
    grid-template-columns: 1fr !important;
    gap: 10px !important;
  }
}

/* Micro Devices (<= 380px) */
@media (max-width: 380px) {
  .periods-stat-grid {
    grid-template-columns: 1fr !important;
    gap: 10px !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR STUDENT & PARENT FEE LEDGER
   ══════════════════════════════════════════════════════════════════════ */
[data-theme="dark"] .impersonation-notice-bar {
  background: rgba(202, 138, 4, 0.15) !important;
  border-color: rgba(202, 138, 4, 0.35) !important;
  color: #fde047 !important;
}

[data-theme="dark"] .child-selector-banner {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .child-selector-banner > span {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .roaster-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .roaster-search-box {
  background: #0f172a !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .roaster-search-box input {
  color: #f8fafc !important;
}

[data-theme="dark"] .roaster-select-box select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}

[data-theme="dark"] .scholars-count-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .periods-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .periods-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .periods-card-header h3 {
  color: #f8fafc !important;
}

[data-theme="dark"] .periods-card-header p {
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-header > div:last-child {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-header > div:last-child strong {
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table thead th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .staff-id-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .staff-id-table tbody tr:hover td {
  background: #192339 !important;
}

[data-theme="dark"] .staff-id-table tbody td div[style*="color: #0f172a"] {
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table tbody td span[style*="color: #0f172a"] {
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table tbody td span[style*="color: #334155"] {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .staff-id-table tbody td div[style*="color: #1e293b"] {
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table tbody td span[style*="color: #64748b"] {
  color: #94a3b8 !important;
}

[data-theme="dark"] .sid-code-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .sid-mode-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .sid-status-verified {
  background: rgba(22, 101, 52, 0.25) !important;
  color: #4ade80 !important;
  border-color: rgba(74, 222, 128, 0.3) !important;
}

[data-theme="dark"] .periods-card-footer {
  background: #0f172a !important;
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-footer span {
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-footer strong {
  color: #f8fafc !important;
}

[data-theme="dark"] .periods-card-footer > div:last-child {
  border-top-color: #334155 !important;
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

[data-theme="dark"] .pagination-btn.active {
  background: #0284c7 !important;
  border-color: #0284c7 !important;
  color: #ffffff !important;
}

[data-theme="dark"] .pagination-btn:hover:not(.disabled):not(.active) {
  background: #1e293b !important;
  color: #f8fafc !important;
}

[data-theme="dark"] .pagination-btn.disabled {
  opacity: 0.4 !important;
}

[data-theme="dark"] .hrms-modal-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .hrms-modal-header {
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .hrms-modal-header h3 {
  color: #f8fafc !important;
}

[data-theme="dark"] .modal-close-btn {
  color: #94a3b8 !important;
}

[data-theme="dark"] .hrms-modal-body .form-label {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .hrms-modal-body .hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table td[colspan] div[style*="background: #f1f5f9"] {
  background: #0f172a !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .staff-id-table td[colspan] h4 {
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table td[colspan] p {
  color: #94a3b8 !important;
}

[data-theme="dark"] .roaster-btn.secondary {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .roaster-btn.secondary:hover {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #ffffff !important;
}
</style>

<!-- ══════════════════════════════════════════════════════
     ADMIN IMPERSONATION OR MULTI-CHILD SELECTOR
     ══════════════════════════════════════════════════════ -->
<?php if (!empty($_SESSION['impersonated_student'])): ?>
  <div class="impersonation-notice-bar no-print">
    <div style="display: flex; align-items: center; gap: 8px;">
      <i class="fa-solid fa-user-secret" style="color: #ca8a04;"></i>
      <span>Viewing Fee Ledger via Admin Impersonation: <strong><?php echo htmlspecialchars($student['student_name']); ?></strong> (Roll: <?php echo htmlspecialchars($student['roll_no']); ?>)</span>
    </div>
    <a href="<?php echo ADMIN_URL; ?>/fees" class="roaster-btn secondary" style="font-size: 0.76rem; padding: 4px 10px; text-decoration: none; border-radius: 6px;">
      Return to Admin Fees
    </a>
  </div>
<?php endif; ?>

<?php if ($current_user['role'] === 'parent' && count($parent_children) > 1): ?>
  <div class="child-selector-banner no-print">
    <span style="font-size: 0.80rem; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 6px;">
      <i class="fa-solid fa-children" style="color: #0284c7;"></i> Select Student:
    </span>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <?php foreach ($parent_children as $pc): ?>
        <a href="<?php echo PARENT_URL; ?>/fees?student_id=<?php echo $pc['id']; ?>" 
           class="roaster-btn <?php echo $pc['id'] == $student_id ? 'primary' : 'secondary'; ?>" 
           style="font-size: 0.76rem; padding: 5px 12px; border-radius: 6px; text-decoration: none;">
          <?php echo htmlspecialchars($pc['student_name']); ?> (<?php echo htmlspecialchars($pc['standard']); ?>)
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     4 SOBER ACADEMIC KPI METRIC CARDS (Exact replica of staff/payroll.php)
     ══════════════════════════════════════════════════════ -->
<div class="periods-stat-grid">

  <!-- 1. Total Course Fee Value -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Course Fee Scale</span>
          <span class="stat-number"><?php echo format_inr($course_total_fee); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-graduation-cap"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><?php echo htmlspecialchars($student['student_name']); ?> &bull; <?php echo htmlspecialchars($student['standard']); ?></span>
      <span style="color: #0284c7; font-weight: 700;">Scale</span>
    </div>
  </div>

  <!-- 2. Tuition Fee Cleared -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Tuition Fees Cleared</span>
          <span class="stat-number"><?php echo format_inr($total_paid); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-circle-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $paid_pct; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $paid_pct; ?>%</strong> Realized Payment</span>
      <span style="color: #059669; font-weight: 700;">Cleared</span>
    </div>
  </div>

  <!-- 3. Outstanding Balance Due -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Outstanding Balance Due</span>
          <span class="stat-number" style="color: <?php echo $outstanding_due > 0 ? '#dc2626' : '#059669'; ?>;">
            <?php echo format_inr($outstanding_due); ?>
          </span>
        </div>
        <div class="stat-icon-wrapper" style="<?php echo $outstanding_due > 0 ? 'background: rgba(220, 38, 38, 0.1); color: #dc2626;' : 'background: rgba(16, 185, 129, 0.1); color: #059669;'; ?>">
          <i class="fa-solid <?php echo $outstanding_due > 0 ? 'fa-hourglass-half' : 'fa-check-double'; ?>"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $due_pct; ?>%; <?php echo $outstanding_due > 0 ? 'background: #dc2626;' : 'background: #059669;'; ?>"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><?php echo $outstanding_due > 0 ? "<strong>{$due_pct}%</strong> Pending Clearance" : "All Fees Cleared"; ?></span>
      <span style="color: <?php echo $outstanding_due > 0 ? '#dc2626' : '#059669'; ?>; font-weight: 700;">
        <?php echo $outstanding_due > 0 ? 'Installments Due' : 'Settled ✓'; ?>
      </span>
    </div>
  </div>

  <!-- 4. Verified Fee Vouchers -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Verified Fee Receipts</span>
          <span class="stat-number"><?php echo $total_vouchers_all; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-receipt"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Accounts Department Signed</span>
      <span style="color: #7c3aed; font-weight: 700;">Official Slips</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     FILTER & SEARCH TOOLBAR (Simple, Sober & Functional)
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-top: 6px; margin-bottom: 20px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Search & Filter Form -->
    <form method="GET" action="<?php echo PARENT_URL; ?>/fees" class="roaster-filter-form">
      <?php if ($student_id): ?>
        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
      <?php endif; ?>
      <?php if ($limit !== 10): ?>
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      <?php endif; ?>

      <!-- Search Input -->
      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search receipt #, remarks..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_parent_fees_pagination_url(1, '', $filter_method, $filter_year, $limit, $student_id)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <!-- Payment Mode Filter -->
      <div class="roaster-select-box">
        <select name="method" onchange="this.form.submit()">
          <option value="">All Payment Modes</option>
          <option value="Cash" <?php echo $filter_method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
          <option value="UPI" <?php echo $filter_method === 'UPI' ? 'selected' : ''; ?>>Online UPI</option>
          <option value="Card" <?php echo $filter_method === 'Card' ? 'selected' : ''; ?>>Credit / Debit Card</option>
          <option value="Net Banking" <?php echo $filter_method === 'Net Banking' ? 'selected' : ''; ?>>Net Banking</option>
          <option value="Cheque" <?php echo $filter_method === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
        </select>
      </div>

      <!-- Year Filter -->
      <div class="roaster-select-box">
        <select name="year" onchange="this.form.submit()">
          <option value="">All Years</option>
          <?php foreach ($years_list as $y): ?>
            <option value="<?php echo $y; ?>" <?php echo strval($filter_year) === strval($y) ? 'selected' : ''; ?>>
              <?php echo $y; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!empty($search) || !empty($filter_method) || !empty($filter_year)): ?>
        <a href="<?php echo PARENT_URL; ?>/fees?student_id=<?php echo $student_id; ?><?php echo $limit !== 10 ? '&limit=' . $limit : ''; ?>" class="roaster-btn secondary" style="gap: 5px; font-size: 0.80rem; text-decoration: none; padding: 7px 12px; border-radius: 8px;">
          <i class="fa-solid fa-rotate-left"></i> Reset Filters
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Count Badge & Admin Action -->
    <div class="roaster-toolbar-actions" style="display: flex; align-items: center; gap: 10px;">
      <div class="scholars-count-badge">
        <span class="count-dot" style="background: #0284c7;"></span>
        <span><strong><?php echo $total_records; ?></strong> Payment Slips</span>
      </div>

      <?php if ($current_user['role'] === 'super_admin'): ?>
        <button type="button" class="roaster-add-btn" onclick="openModal('collectFeeModal')" style="padding: 7px 14px; font-size: 0.80rem; border-radius: 8px;">
          <i class="fa-solid fa-receipt"></i> Record Fee Payment
        </button>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     FEE PAYMENT RECEIPTS LEDGER TABLE CARD
     ══════════════════════════════════════════════════════ -->
<div class="periods-card">
  
  <!-- Card Header -->
  <div class="periods-card-header">
    <div style="display: flex; align-items: center; gap: 10px;">
      <div style="width: 32px; height: 32px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
        <i class="fa-solid fa-receipt"></i>
      </div>
      <div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <h3 style="font-size: 0.96rem; font-weight: 700; color: #0f172a; margin: 0; font-family: 'Outfit', sans-serif;">
            Official Tuition Fee Receipts Ledger
          </h3>
          <span style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-size: 0.65rem; font-weight: 700; padding: 1px 7px; border-radius: 10px; text-transform: uppercase;">
            Verified Slips
          </span>
        </div>
        <p style="font-size: 0.74rem; color: #64748b; margin: 2px 0 0;">
          Payment vouchers, installment receipts &amp; cleared dues for <?php echo htmlspecialchars($student['student_name']); ?>.
        </p>
      </div>
    </div>

    <div style="font-size: 0.76rem; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 6px;">
      <span>Roll: <strong style="color: #0f172a;"><?php echo htmlspecialchars($student['roll_no']); ?></strong></span>
      <span style="color: #cbd5e1; margin: 0 6px;">•</span>
      <span>Class: <strong style="color: #0f172a;"><?php echo htmlspecialchars($student['standard']); ?></strong></span>
    </div>
  </div>

  <!-- Table Body -->
  <div class="table-responsive" style="overflow-x: auto;">
    <table class="staff-id-table">
      <thead>
        <tr>
          <th style="width: 44px; text-align: center;">#</th>
          <th>Receipt No</th>
          <th>Student Details</th>
          <th>Class / Course</th>
          <th>Payment Date</th>
          <th>Payment Mode</th>
          <th style="text-align: right;">Amount (₹)</th>
          <th>Particulars / Remarks</th>
          <th style="text-align: center;">Status</th>
          <th style="text-align: center; width: 85px;">Slip</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
          <tr>
            <td colspan="10" style="text-align: center; padding: 45px 20px;">
              <div style="max-width: 320px; margin: 0 auto;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 12px;">
                  <i class="fa-solid fa-receipt"></i>
                </div>
                <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0 0 4px; font-family: 'Outfit', sans-serif;">
                  No Fee Receipts Found
                </h4>
                <p style="font-size: 0.78rem; color: #64748b; margin: 0;">
                  <?php if (!empty($search) || !empty($filter_method) || !empty($filter_year)): ?>
                    No fee payments match the selected search or filter criteria.
                  <?php else: ?>
                    Tuition fee payments recorded by the academy will appear here.
                  <?php endif; ?>
                </p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($payments as $idx => $p): ?>
            <?php 
              $row_num = $start_record + $idx;
              $p_payload = array_merge($p, [
                'student_name' => $student['student_name'] ?? $p['student_name'] ?? '',
                'roll_no' => $student['roll_no'] ?? $p['roll_no'] ?? '',
                'standard' => $student['standard'] ?? $p['standard'] ?? '',
                'total_fees' => $course_total_fee
              ]);

              // Payment mode icon helper
              $method_str = $p['payment_method'] ?: 'Online UPI';
              $method_icon = 'fa-solid fa-wallet';
              if (stripos($method_str, 'cash') !== false) {
                  $method_icon = 'fa-solid fa-money-bill-wave';
              } elseif (stripos($method_str, 'upi') !== false || stripos($method_str, 'gpay') !== false) {
                  $method_icon = 'fa-solid fa-mobile-screen';
              } elseif (stripos($method_str, 'card') !== false) {
                  $method_icon = 'fa-solid fa-credit-card';
              } elseif (stripos($method_str, 'bank') !== false || stripos($method_str, 'neft') !== false) {
                  $method_icon = 'fa-solid fa-building-columns';
              } elseif (stripos($method_str, 'cheque') !== false) {
                  $method_icon = 'fa-solid fa-money-check';
              }
            ?>
            <tr>
              <!-- # -->
              <td style="text-align: center; color: #64748b; font-weight: 600; font-size: 0.76rem;">
                <?php echo sprintf('%02d', $row_num); ?>
              </td>

              <!-- Receipt No -->
              <td>
                <span class="sid-code-pill">
                  <i class="fa-solid fa-receipt" style="font-size: 0.62rem;"></i>
                  <?php echo htmlspecialchars($p['receipt_no']); ?>
                </span>
              </td>

              <!-- Student Details -->
              <td>
                <div style="font-weight: 700; color: #0f172a; font-size: 0.82rem;">
                  <?php echo htmlspecialchars($p['student_name']); ?>
                </div>
                <div style="font-size: 0.72rem; color: #64748b; margin-top: 1px;">
                  Roll: <span style="font-family: monospace; font-weight: 600; color: #334155;"><?php echo htmlspecialchars($p['roll_no']); ?></span>
                </div>
              </td>

              <!-- Class -->
              <td>
                <span style="font-size: 0.78rem; font-weight: 600; color: #334155;">
                  <?php echo htmlspecialchars($p['standard']); ?>
                </span>
              </td>

              <!-- Payment Date -->
              <td>
                <div style="font-size: 0.80rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 5px;">
                  <i class="fa-regular fa-calendar" style="color: #64748b; font-size: 0.70rem;"></i>
                  <?php echo format_date($p['payment_date'], 'd M Y'); ?>
                </div>
              </td>

              <!-- Payment Mode -->
              <td>
                <span class="sid-mode-badge">
                  <i class="<?php echo $method_icon; ?>" style="color: #0284c7; font-size: 0.66rem;"></i>
                  <?php echo htmlspecialchars(str_replace([' (GPay/PhonePe)', ' / Cash', ' at Desk'], '', $p['payment_method'] ?: 'Online UPI')); ?>
                </span>
              </td>

              <!-- Amount Paid -->
              <td style="text-align: right;">
                <span style="font-weight: 800; color: #0f172a; font-size: 0.86rem; font-family: 'Outfit', sans-serif; white-space: nowrap;">
                  <?php echo format_inr($p['amount']); ?>
                </span>
              </td>

              <!-- Particulars / Remarks -->
              <td>
                <span style="font-size: 0.76rem; color: #64748b;">
                  <?php echo htmlspecialchars($p['remarks'] ?: 'Term Installment Fee'); ?>
                </span>
              </td>

              <!-- Status -->
              <td style="text-align: center;">
                <span class="sid-status-verified">
                  <i class="fa-solid fa-circle-check"></i> Cleared
                </span>
              </td>

              <!-- Slip Button -->
              <td style="text-align: center;">
                <button type="button" class="roaster-btn secondary" onclick="openFeeReceiptModal(<?php echo htmlspecialchars(json_encode($p_payload), ENT_QUOTES, 'UTF-8'); ?>);" style="padding: 3px 8px; font-size: 0.74rem; border-radius: 6px; gap: 4px; display: inline-flex; align-items: center; cursor: pointer;" title="View Official Fee Voucher">
                  <i class="fa-solid fa-receipt" style="color: #0284c7; font-size: 0.68rem;"></i> Slip
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Card Footer: Counts & Pagination Navigation -->
  <div class="periods-card-footer">
    
    <!-- Left: Entries Counter & Limit Selector -->
    <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
      <span style="font-size: 0.82rem; color: #475569;">
        <?php if ($total_records > 0): ?>
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> payment vouchers
        <?php else: ?>
          Showing <strong>0</strong> payment vouchers
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_parent_fees_pagination_url(1, $search, $filter_method, $filter_year, $l, $student_id)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Student Fees Ledger Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_parent_fees_pagination_url($page - 1, $search, $filter_method, $filter_year, $limit, $student_id)) : '#'; ?>" 
           class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
           aria-label="Previous Page"
           title="Previous Page">
          <i class="fa-solid fa-chevron-left" style="font-size: 0.72rem;"></i>
        </a>

        <!-- Page Numbers -->
        <?php
          $range = 2;
          for ($p_idx = 1; $p_idx <= $total_pages; $p_idx++):
            if ($p_idx == 1 || $p_idx == $total_pages || ($p_idx >= $page - $range && $p_idx <= $page + $range)):
        ?>
          <a href="<?php echo htmlspecialchars(get_parent_fees_pagination_url($p_idx, $search, $filter_method, $filter_year, $limit, $student_id)); ?>" 
             class="pagination-btn <?php echo $p_idx === $page ? 'active' : ''; ?>">
            <?php echo $p_idx; ?>
          </a>
        <?php 
            elseif (($p_idx == 2 && $page - $range > 2) || ($p_idx == $total_pages - 1 && $page + $range < $total_pages - 1)):
        ?>
          <span class="pagination-ellipsis">&hellip;</span>
        <?php 
            endif;
          endfor;
        ?>

        <!-- Next Page -->
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_parent_fees_pagination_url($page + 1, $search, $filter_method, $filter_year, $limit, $student_id)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Summary Stats in Footer -->
    <div style="font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 12px; margin-left: auto;">
      <span>Course Fee: <strong><?php echo format_inr($course_total_fee); ?></strong></span>
      <span style="color: #cbd5e1;">•</span>
      <span>Total Cleared: <strong style="color: #059669;"><?php echo format_inr($total_paid); ?></strong></span>
      <?php if ($outstanding_due > 0): ?>
        <span style="color: #cbd5e1;">•</span>
        <span>Due: <strong style="color: #dc2626;"><?php echo format_inr($outstanding_due); ?></strong></span>
      <?php endif; ?>
    </div>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: COLLECT FEE PAYMENT (FOR SUPER ADMIN USE)
     ══════════════════════════════════════════════════════ -->
<?php if ($current_user['role'] === 'super_admin'): ?>
<div class="hrms-modal" id="collectFeeModal">
  <div class="hrms-modal-card" style="max-width: 500px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 16px 20px;">
      <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-receipt" style="color: #0284c7;"></i>
        Record Fee Payment
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('collectFeeModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo PARENT_URL; ?>/fees">
      <input type="hidden" name="action" value="collect_fee">
      <div class="hrms-modal-body" style="padding: 20px;">
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Select Student</label>
          <select name="student_id" class="hrms-input" required style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem; background: #fff;">
            <?php foreach ($student_options as $so): ?>
              <option value="<?php echo $so['id']; ?>" <?php echo $so['id'] == $student_id ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($so['student_name']); ?> (<?php echo $so['roll_no']; ?>) &bull; Due: ₹<?php echo number_format($so['due'], 0); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Payment Amount (₹)</label>
            <input type="number" name="amount" class="hrms-input" placeholder="Amount" value="15000" required style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem;">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Payment Method</label>
            <select name="payment_method" class="hrms-input" style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem; background: #fff;">
              <option value="Online UPI (GPay/PhonePe)">Online UPI (GPay/PhonePe)</option>
              <option value="Net Banking / NEFT">Net Banking / NEFT</option>
              <option value="Credit / Debit Card">Credit / Debit Card</option>
              <option value="Cheque">Bank Cheque</option>
              <option value="Cash Deposit">Cash Deposit at Desk</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-bottom: 20px;">
          <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Payment Remarks / Particulars</label>
          <input type="text" name="remarks" class="hrms-input" value="Term Installment Fee" style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem;">
        </div>
        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
          <button type="button" class="roaster-btn secondary" onclick="closeModal('collectFeeModal')" style="padding: 9px 16px; border-radius: 8px; font-size: 0.84rem; cursor: pointer;">Cancel</button>
          <button type="submit" class="btn-hrms btn-hrms-primary" style="padding: 9px 20px; border-radius: 8px; font-size: 0.84rem; font-weight: 700; cursor: pointer;">
            <i class="fa-solid fa-receipt"></i> Record &amp; Issue Receipt
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Include Shared Official Receipt Voucher Modal -->
<?php require_once __DIR__ . '/../includes/receipt-modal.php'; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
