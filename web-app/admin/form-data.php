<?php
/**
 * KSV Academy HRMS - Form Data & Leads Management
 * Manages submissions from:
 * 1. Book Your 3-Days Free Demo Class (form_type = 'demo_class')
 * 2. Candidate Registration Form (form_type = 'admission')
 * 3. Send an Inquiry (form_type = 'inquiry')
 *
 * Stored and managed in table: form_data
 */

require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'admin']);
$page_title = 'Form Data Management';
$page_heading = 'Form Submissions';
$page_subheading = 'Track and manage Demo Class bookings, Candidate Admissions, and Inquiries from the website';
$active_menu = 'form-data';

$db = get_db_connection();
ensure_form_data_table($db);

// ── Handle Actions (Status Update, Note, Delete) ─────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Status & Notes
    if ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $new_status = trim($_POST['status'] ?? 'unread');
        $notes = trim($_POST['notes'] ?? '');

        $valid_statuses = ['unread', 'contacted', 'enrolled', 'closed'];
        if (!in_array($new_status, $valid_statuses)) {
            $new_status = 'unread';
        }

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE form_data SET status = ?, notes = ? WHERE id = ?");
            $stmt->execute([$new_status, $notes, $id]);
            set_flash('success', "Lead #{$id} status updated to " . ucfirst($new_status) . ".");
        }

        $tab = urlencode($_GET['tab'] ?? 'all');
        $status = urlencode($_GET['status'] ?? 'all');
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 10);
        header("Location: " . ADMIN_URL . "/form-data?tab={$tab}&status={$status}&page={$page}&limit={$limit}");
        exit;
    }

    // 2. Delete Lead
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM form_data WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', "Submission record #{$id} permanently removed.");
        }

        $tab = urlencode($_GET['tab'] ?? 'all');
        $status = urlencode($_GET['status'] ?? 'all');
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 10);
        header("Location: " . ADMIN_URL . "/form-data?tab={$tab}&status={$status}&page={$page}&limit={$limit}");
        exit;
    }
}

// ── Query Parameters: Tabs, Search, Status & Sorting ─────────────────────────
$active_tab = $_GET['tab'] ?? 'all';
if (!in_array($active_tab, ['all', 'demo_class', 'admission', 'inquiry'])) {
    $active_tab = 'all';
}

$filter_status = $_GET['status'] ?? 'all';
$valid_filter_statuses = ['all', 'unread', 'contacted', 'enrolled', 'closed'];
if (!in_array($filter_status, $valid_filter_statuses)) {
    $filter_status = 'all';
}

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

// ── Pagination Settings ──────────────────────────────────────────────────────
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// ── Counts for Tabs & Stats Cards ────────────────────────────────────────────
$counts = [
    'all'        => (int) $db->query("SELECT COUNT(*) FROM form_data")->fetchColumn(),
    'demo_class' => (int) $db->query("SELECT COUNT(*) FROM form_data WHERE form_type = 'demo_class'")->fetchColumn(),
    'admission'  => (int) $db->query("SELECT COUNT(*) FROM form_data WHERE form_type = 'admission'")->fetchColumn(),
    'inquiry'    => (int) $db->query("SELECT COUNT(*) FROM form_data WHERE form_type = 'inquiry'")->fetchColumn(),
    'unread'     => (int) $db->query("SELECT COUNT(*) FROM form_data WHERE status = 'unread'")->fetchColumn(),
    'contacted'  => (int) $db->query("SELECT COUNT(*) FROM form_data WHERE status = 'contacted'")->fetchColumn(),
    'enrolled'   => (int) $db->query("SELECT COUNT(*) FROM form_data WHERE status = 'enrolled'")->fetchColumn(),
    'closed'     => (int) $db->query("SELECT COUNT(*) FROM form_data WHERE status = 'closed'")->fetchColumn(),
];

// Helper to preserve active query parameters in pagination links
function get_form_data_pagination_url($target_page, $tab = 'all', $status = 'all', $search = '', $sort = 'newest', $limit = 10) {
    $params = [];
    if (!empty($tab) && $tab !== 'all') $params['tab'] = $tab;
    if (!empty($status) && $status !== 'all') $params['status'] = $status;
    if (!empty($search)) $params['search'] = $search;
    if (!empty($sort) && $sort !== 'newest') $params['sort'] = $sort;
    if ($limit !== 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    $query_str = !empty($params) ? '?' . http_build_query($params) : '';
    return ADMIN_URL . '/form-data' . $query_str;
}

// ── Build Filtered Query & Pagination ────────────────────────────────────────
$where = ["1=1"];
$params = [];

if ($active_tab !== 'all') {
    $where[] = "form_type = ?";
    $params[] = $active_tab;
}
if ($filter_status !== 'all') {
    $where[] = "status = ?";
    $params[] = $filter_status;
}
if (!empty($search)) {
    $where[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR course LIKE ? OR school_name LIKE ? OR message LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term]);
}

$where_sql = implode(' AND ', $where);
$order_sql = ($sort === 'oldest') ? "ORDER BY id ASC" : "ORDER BY id DESC";

// Count total records matching filters
$count_stmt = $db->prepare("SELECT COUNT(*) FROM form_data WHERE {$where_sql}");
$count_stmt->execute($params);
$total_records = intval($count_stmt->fetchColumn());

// Calculate Pagination Bounds
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch Paginated Records
$query = "SELECT * FROM form_data WHERE {$where_sql} {$order_sql} LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);
$param_idx = 1;
foreach ($params as $val) {
    $stmt->bindValue($param_idx++, $val);
}
$stmt->bindValue($param_idx++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($param_idx++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$submissions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* Tabs Navigation Bar */
.fd-tabs-nav {
  display: flex;
  gap: 8px;
  align-items: center;
  background: #f1f5f9;
  padding: 6px;
  border-radius: 12px;
  flex-wrap: wrap;
}
.fd-tab-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 9px;
  font-size: 0.84rem;
  font-weight: 700;
  color: #475569;
  text-decoration: none;
  transition: all 0.2s ease;
}
.fd-tab-pill:hover {
  color: #0284c7;
  background: rgba(255, 255, 255, 0.7);
}
.fd-tab-pill.active {
  background: #0284c7;
  color: #ffffff;
  box-shadow: 0 3px 10px rgba(2, 132, 199, 0.25);
}
.fd-tab-badge {
  font-size: 0.72rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 9999px;
  background: #e2e8f0;
  color: #334155;
  transition: all 0.2s ease;
}
.fd-tab-pill.active .fd-tab-badge {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

/* Source Type Badge */
.badge-form-type {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: capitalize;
  white-space: nowrap;
}
.type-demo {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}
.type-admission {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
}
.type-inquiry {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fde68a;
}

/* Status Badges */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.status-unread {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  animation: pulse-dot 2s infinite ease-in-out;
}
.status-contacted {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
}
.status-enrolled {
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #a7f3d0;
}
.status-closed {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #cbd5e1;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.75; }
}

/* Standard Action Icon Buttons (Matches other HRMS modules) */
.sid-action-btn {
  width: 32px;
  height: 32px;
  border-radius: 7px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 0.78rem;
  padding: 0;
  text-decoration: none;
}
.sid-action-btn:hover {
  transform: translateY(-1px);
}
.sid-action-btn.view {
  color: #0284c7;
  border-color: #bae6fd;
  background: #f0f9ff;
}
.sid-action-btn.view:hover {
  background: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
}
.sid-action-btn.edit {
  color: #d97706;
  border-color: #fde68a;
  background: #fffbeb;
}
.sid-action-btn.edit:hover {
  background: #d97706;
  color: #ffffff;
  border-color: #d97706;
}
.sid-action-btn.delete {
  color: #dc2626;
  border-color: #fecaca;
  background: #fef2f2;
}
.sid-action-btn.delete:hover {
  background: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
}

/* Detail modal styles */
.lead-modal-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 12px;
}
@media (max-width: 640px) {
  .lead-modal-grid {
    grid-template-columns: 1fr;
  }
}
.lead-detail-item {
  background: #f8fafc;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.lead-detail-label {
  font-size: 0.70rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #64748b;
  margin-bottom: 3px;
}
.lead-detail-val {
  font-size: 0.90rem;
  font-weight: 600;
  color: #0f172a;
  word-break: break-word;
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR FORM SUBMISSIONS & LEADS
   ══════════════════════════════════════════════════════════════════════ */

/* 1. Top KPI Stat Metrics Cards */
[data-theme="dark"] .stat-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .stat-card .stat-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-card .stat-number {
  color: #f8fafc !important;
}
[data-theme="dark"] .stat-card-progress {
  background: #334155 !important;
}
[data-theme="dark"] .stat-card.blue .stat-icon-wrapper {
  background: rgba(2, 132, 199, 0.2) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .stat-card.purple .stat-icon-wrapper {
  background: rgba(124, 58, 237, 0.2) !important;
  color: #c084fc !important;
}
[data-theme="dark"] .stat-card.green .stat-icon-wrapper {
  background: rgba(22, 163, 74, 0.2) !important;
  color: #4ade80 !important;
}
[data-theme="dark"] .stat-card.amber .stat-icon-wrapper {
  background: rgba(217, 119, 6, 0.2) !important;
  color: #fbbf24 !important;
}
[data-theme="dark"] .stat-card-footer {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-card-footer span {
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #0284c7"],
[data-theme="dark"] .stat-card-footer span[style*="color:#0284c7"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #7c3aed"],
[data-theme="dark"] .stat-card-footer span[style*="color:#7c3aed"] {
  color: #c084fc !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #16a34a"],
[data-theme="dark"] .stat-card-footer span[style*="color:#16a34a"] {
  color: #4ade80 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #d97706"],
[data-theme="dark"] .stat-card-footer span[style*="color:#d97706"] {
  color: #fbbf24 !important;
}

/* 2. Toolbar & Navigation Tabs */
[data-theme="dark"] .fd-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .fd-tabs-nav {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .fd-tab-pill {
  color: #94a3b8 !important;
}
[data-theme="dark"] .fd-tab-pill:hover {
  background: #1e293b !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .fd-tab-pill.active {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
  color: #ffffff !important;
  box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35) !important;
}
[data-theme="dark"] .fd-tab-badge {
  background: #1e293b !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .fd-tab-pill.active .fd-tab-badge {
  background: rgba(255, 255, 255, 0.25) !important;
  color: #ffffff !important;
}
[data-theme="dark"] .fd-secondary-toolbar {
  border-top-color: #334155 !important;
}
[data-theme="dark"] .fd-status-filter-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .fd-status-filter-btn {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .fd-status-filter-btn:hover {
  background: #1e293b !important;
  color: #f8fafc !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .fd-status-filter-btn.active-all {
  background: rgba(2, 132, 199, 0.2) !important;
  color: #38bdf8 !important;
  border-color: #0284c7 !important;
}
[data-theme="dark"] .fd-status-filter-btn.active-unread {
  background: rgba(239, 68, 68, 0.2) !important;
  color: #f87171 !important;
  border-color: #ef4444 !important;
}
[data-theme="dark"] .fd-status-filter-btn.active-contacted {
  background: rgba(37, 99, 235, 0.2) !important;
  color: #60a5fa !important;
  border-color: #2563eb !important;
}
[data-theme="dark"] .fd-status-filter-btn.active-enrolled {
  background: rgba(16, 185, 129, 0.2) !important;
  color: #34d399 !important;
  border-color: #10b981 !important;
}
[data-theme="dark"] .fd-status-filter-btn.active-closed {
  background: #334155 !important;
  color: #cbd5e1 !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .fd-search-box .fd-search-icon {
  color: #94a3b8 !important;
}
[data-theme="dark"] .fd-search-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .fd-search-input:focus {
  border-color: #0284c7 !important;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.2) !important;
}
[data-theme="dark"] .fd-search-input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .fd-search-clear {
  color: #f87171 !important;
}
[data-theme="dark"] .fd-search-clear:hover {
  color: #fca5a5 !important;
}
[data-theme="dark"] .fd-sort-select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .fd-sort-select option {
  background: #0f172a !important;
  color: #f8fafc !important;
}

/* 3. Submissions Table Card & Components */
[data-theme="dark"] .fd-table-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .fd-table-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .fd-table-card-header h2,
[data-theme="dark"] .fd-table-card-header .card-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .fd-records-badge {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .fd-table thead th {
  background: #0f172a !important;
  border-bottom: 1.5px solid #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .fd-table tbody tr {
  border-bottom: 1px solid #334155 !important;
  background: transparent !important;
}
[data-theme="dark"] .fd-table tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.02) !important;
}
[data-theme="dark"] .fd-table tbody tr.row-unread {
  background: rgba(239, 68, 68, 0.08) !important;
  border-left: 4px solid #ef4444 !important;
}
[data-theme="dark"] .fd-table tbody tr.row-unread:hover {
  background: rgba(239, 68, 68, 0.12) !important;
}
[data-theme="dark"] .fd-table tbody td {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .fd-table tbody td:first-child {
  color: #94a3b8 !important;
}

/* Empty Table State */
[data-theme="dark"] .fd-table tbody td div[style*="background: #f1f5f9"] {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .fd-table tbody td h4 {
  color: #f8fafc !important;
}
[data-theme="dark"] .fd-table tbody td p {
  color: #94a3b8 !important;
}

/* User / Applicant Cell */
[data-theme="dark"] .cell-avatar[style*="#eff6ff"] {
  background: rgba(2, 132, 199, 0.18) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .cell-avatar[style*="#ecfdf5"] {
  background: rgba(16, 185, 129, 0.18) !important;
  color: #34d399 !important;
}
[data-theme="dark"] .cell-avatar[style*="#fffbeb"] {
  background: rgba(245, 158, 11, 0.18) !important;
  color: #fbbf24 !important;
}
[data-theme="dark"] .cell-name {
  color: #f8fafc !important;
}
[data-theme="dark"] .user-cell a[href^="tel"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] .cell-meta span[style*="color: #64748b"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .cell-meta span[style*="color: #475569"] {
  color: #cbd5e1 !important;
}

/* Form Type Badges */
[data-theme="dark"] .badge-form-type.type-demo {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border: 1px solid rgba(2, 132, 199, 0.3) !important;
}
[data-theme="dark"] .badge-form-type.type-admission {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border: 1px solid rgba(16, 185, 129, 0.3) !important;
}
[data-theme="dark"] .badge-form-type.type-inquiry {
  background: rgba(245, 158, 11, 0.15) !important;
  color: #fbbf24 !important;
  border: 1px solid rgba(245, 158, 11, 0.3) !important;
}

/* Course & School Columns */
[data-theme="dark"] .col-course div[style*="color: #0f172a;"] {
  color: #f8fafc !important;
}
[data-theme="dark"] .col-course div[style*="color: #64748b;"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .col-school div[style*="color: #334155;"] {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .col-school div[style*="color: #b45309;"] {
  color: #fbbf24 !important;
}
[data-theme="dark"] .col-school div[style*="color: #64748b;"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .col-school div[style*="background: #f0f9ff;"] {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border: 1px solid rgba(2, 132, 199, 0.3) !important;
}

/* Status Badges */
[data-theme="dark"] .status-pill.status-unread {
  background: rgba(239, 68, 68, 0.15) !important;
  color: #f87171 !important;
  border-color: rgba(239, 68, 68, 0.3) !important;
}
[data-theme="dark"] .status-pill.status-contacted {
  background: rgba(37, 99, 235, 0.15) !important;
  color: #60a5fa !important;
  border-color: rgba(37, 99, 235, 0.3) !important;
}
[data-theme="dark"] .status-pill.status-enrolled {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
}
[data-theme="dark"] .status-pill.status-closed {
  background: rgba(148, 163, 184, 0.15) !important;
  color: #cbd5e1 !important;
  border-color: rgba(148, 163, 184, 0.25) !important;
}

/* Date Column */
[data-theme="dark"] .col-date div[style*="color: #1e293b;"] {
  color: #f8fafc !important;
}
[data-theme="dark"] .col-date div[style*="color: #64748b;"] {
  color: #94a3b8 !important;
}

/* Action Buttons */
[data-theme="dark"] .sid-action-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .sid-action-btn.view {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
}
[data-theme="dark"] .sid-action-btn.view:hover {
  background: #0284c7 !important;
  border-color: #0284c7 !important;
  color: #ffffff !important;
}
[data-theme="dark"] .sid-action-btn.edit {
  background: rgba(245, 158, 11, 0.15) !important;
  color: #fbbf24 !important;
  border-color: rgba(245, 158, 11, 0.3) !important;
}
[data-theme="dark"] .sid-action-btn.edit:hover {
  background: #d97706 !important;
  border-color: #d97706 !important;
  color: #ffffff !important;
}
[data-theme="dark"] .sid-action-btn.delete {
  background: rgba(220, 38, 38, 0.15) !important;
  color: #f87171 !important;
  border-color: rgba(220, 38, 38, 0.3) !important;
}
[data-theme="dark"] .sid-action-btn.delete:hover {
  background: #dc2626 !important;
  border-color: #dc2626 !important;
  color: #ffffff !important;
}

/* 4. Pagination Controls */
[data-theme="dark"] .pagination-controls-wrap {
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
[data-theme="dark"] .pagination-limit-select option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .pagination-btn:hover:not(.disabled) {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-btn.active {
  background: #0092df !important;
  border-color: #0092df !important;
  color: #ffffff !important;
}
[data-theme="dark"] .pagination-btn.disabled {
  opacity: 0.35 !important;
}
[data-theme="dark"] .pagination-ellipsis {
  color: #64748b !important;
}

/* 5. Modals */
[data-theme="dark"] .hrms-modal {
  background: rgba(0, 0, 0, 0.7) !important;
}
[data-theme="dark"] .hrms-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5) !important;
}
[data-theme="dark"] .hrms-modal-header {
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .hrms-modal-header h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] .modal-close-btn {
  color: #94a3b8 !important;
  background: transparent !important;
}
[data-theme="dark"] .modal-close-btn:hover {
  color: #f8fafc !important;
}

/* Detail Modal Grid */
[data-theme="dark"] .lead-detail-item {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .lead-detail-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .lead-detail-val {
  color: #f8fafc !important;
}
[data-theme="dark"] .lead-detail-val[style*="color: #334155"] {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .lead-detail-item[style*="background: #f0f9ff"] {
  background: rgba(2, 132, 199, 0.12) !important;
  border-color: rgba(2, 132, 199, 0.25) !important;
}
[data-theme="dark"] .lead-detail-item[style*="background: #f0f9ff"] .lead-detail-label {
  color: #38bdf8 !important;
}
[data-theme="dark"] #detailNotes {
  color: #e0f2fe !important;
}
[data-theme="dark"] #detailTimestamp,
[data-theme="dark"] #detailSource {
  color: #94a3b8 !important;
}
[data-theme="dark"] #viewDetailModal .hrms-modal-body > div:last-child {
  border-top-color: #334155 !important;
}
[data-theme="dark"] #viewDetailModal button[onclick^="closeModal"] {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] #viewDetailModal button[onclick^="closeModal"]:hover {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #f8fafc !important;
}

/* Status Update Modal */
[data-theme="dark"] #statusModal .hrms-modal-body > div:first-child {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] #statusModalApplicantName {
  color: #f8fafc !important;
}
[data-theme="dark"] #statusModal label.form-label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] #statusModal select.hrms-input,
[data-theme="dark"] #statusModal textarea.hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #statusModal select.hrms-input:focus,
[data-theme="dark"] #statusModal textarea.hrms-input:focus {
  border-color: #0284c7 !important;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.2) !important;
}
[data-theme="dark"] #statusModal select.hrms-input option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #statusModal textarea.hrms-input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] #statusModal .hrms-modal-footer {
  background: #1e293b !important;
  border-top-color: #334155 !important;
}
[data-theme="dark"] #statusModal .hrms-modal-footer button[onclick^="closeModal"] {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] #statusModal .hrms-modal-footer button[onclick^="closeModal"]:hover {
  background: #334155 !important;
  color: #f8fafc !important;
}
</style>

<!-- ══════════════════════════════════════════════════════════
     TOP KPI STAT METRICS GRID (Matches other HRMS pages)
     ══════════════════════════════════════════════════════════ -->
<div class="stat-grid" style="margin-bottom: 22px;">
  
  <!-- 1. Total Submissions -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Submissions</span>
          <span class="stat-number"><?php echo $counts['all']; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-clipboard-list"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-database" style="color: #0284c7; margin-right: 4px;"></i> All Website Forms</span>
      <span style="color: #0284c7; font-weight: 700;"><?php echo $counts['all']; ?> Total</span>
    </div>
  </div>

  <!-- 2. Demo Class Bookings -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Demo Class Bookings</span>
          <span class="stat-number"><?php echo $counts['demo_class']; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-graduation-cap"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #7c3aed;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-calendar-check" style="color: #7c3aed; margin-right: 4px;"></i> 3-Days Free Demo</span>
      <span style="color: #7c3aed; font-weight: 700;"><?php echo $counts['demo_class']; ?> Bookings</span>
    </div>
  </div>

  <!-- 3. Candidate Registration -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Admission Inquiries</span>
          <span class="stat-number"><?php echo $counts['admission']; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-file-signature"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #16a34a;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-user-graduate" style="color: #16a34a; margin-right: 4px;"></i> Session 2026-27</span>
      <span style="color: #16a34a; font-weight: 700;"><?php echo $counts['admission']; ?> Applicants</span>
    </div>
  </div>

  <!-- 4. Contact Messages -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Contact Messages</span>
          <span class="stat-number"><?php echo $counts['inquiry']; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-headset"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #d97706;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-envelope-open-text" style="color: #d97706; margin-right: 4px;"></i> General Queries</span>
      <span style="color: #d97706; font-weight: 700;"><?php echo $counts['inquiry']; ?> Messages</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     FORM DATA TABS & FILTER TOOLBAR
     ══════════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card fd-toolbar-card no-print">
  
  <div class="fd-tabs-wrapper">
    <!-- Tabs for the 3 Forms + All -->
    <div class="fd-tabs-nav">
      
      <!-- Tab 1: All -->
      <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, 'all', $filter_status, $search, $sort, $limit)); ?>" class="fd-tab-pill <?php echo $active_tab === 'all' ? 'active' : ''; ?>">
        <i class="fa-solid fa-layer-group"></i>
        <span>All Forms</span>
        <span class="fd-tab-badge"><?php echo $counts['all']; ?></span>
      </a>

      <!-- Tab 2: Book Your 3-Days Free Demo Class -->
      <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, 'demo_class', $filter_status, $search, $sort, $limit)); ?>" class="fd-tab-pill <?php echo $active_tab === 'demo_class' ? 'active' : ''; ?>">
        <i class="fa-solid fa-graduation-cap"></i>
        <span>Free Demo Class</span>
        <span class="fd-tab-badge"><?php echo $counts['demo_class']; ?></span>
      </a>

      <!-- Tab 3: Candidate Registration Form -->
      <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, 'admission', $filter_status, $search, $sort, $limit)); ?>" class="fd-tab-pill <?php echo $active_tab === 'admission' ? 'active' : ''; ?>">
        <i class="fa-solid fa-file-signature"></i>
        <span>Candidate Registration</span>
        <span class="fd-tab-badge"><?php echo $counts['admission']; ?></span>
      </a>

      <!-- Tab 4: Send an Inquiry -->
      <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, 'inquiry', $filter_status, $search, $sort, $limit)); ?>" class="fd-tab-pill <?php echo $active_tab === 'inquiry' ? 'active' : ''; ?>">
        <i class="fa-solid fa-envelope-open-text"></i>
        <span>Inquiries</span>
        <span class="fd-tab-badge"><?php echo $counts['inquiry']; ?></span>
      </a>

    </div>
  </div>

  <!-- Secondary Filter Bar: Status Filter, Search, Sort -->
  <div class="fd-secondary-toolbar">
    
    <!-- Status Filter Buttons -->
    <div class="fd-status-filter-group">
      <span class="fd-status-filter-label">Status:</span>
      
      <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, $active_tab, 'all', $search, $sort, $limit)); ?>" class="fd-status-filter-btn <?php echo $filter_status === 'all' ? 'active-all' : ''; ?>">
        All (<?php echo $counts['all']; ?>)
      </a>

      <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, $active_tab, 'unread', $search, $sort, $limit)); ?>" class="fd-status-filter-btn <?php echo $filter_status === 'unread' ? 'active-unread' : ''; ?>">
        Unread (<?php echo $counts['unread']; ?>)
      </a>

      <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, $active_tab, 'contacted', $search, $sort, $limit)); ?>" class="fd-status-filter-btn <?php echo $filter_status === 'contacted' ? 'active-contacted' : ''; ?>">
        Contacted (<?php echo $counts['contacted']; ?>)
      </a>

      <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, $active_tab, 'enrolled', $search, $sort, $limit)); ?>" class="fd-status-filter-btn <?php echo $filter_status === 'enrolled' ? 'active-enrolled' : ''; ?>">
        Enrolled (<?php echo $counts['enrolled']; ?>)
      </a>

      <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, $active_tab, 'closed', $search, $sort, $limit)); ?>" class="fd-status-filter-btn <?php echo $filter_status === 'closed' ? 'active-closed' : ''; ?>">
        Closed (<?php echo $counts['closed']; ?>)
      </a>

    </div>

    <!-- Search Input Form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/form-data" class="fd-search-form">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      
      <div class="fd-search-box">
        <i class="fa-solid fa-magnifying-glass fd-search-icon"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, phone, course..." class="fd-search-input">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_form_data_pagination_url(1, $active_tab, $filter_status, '', $sort, $limit)); ?>" class="fd-search-clear" title="Clear Search">
            <i class="fa-solid fa-xmark"></i>
          </a>
        <?php endif; ?>
      </div>

      <select name="sort" onchange="this.form.submit()" class="fd-sort-select">
        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
      </select>
    </form>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     FORM DATA TABLE & PAGINATION
     ══════════════════════════════════════════════════════════ -->
<div class="hrms-card fd-table-card">
  <div class="hrms-card-header fd-table-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <h2 class="card-title" style="margin: 0;">
        <i class="fa-solid fa-table-list" style="color: #0284c7;"></i>
        <?php 
          echo match($active_tab) {
            'demo_class' => '3-Days Free Demo Class Bookings',
            'admission'  => 'Candidate Registration Applications',
            'inquiry'    => 'Website Inquiry Messages',
            default      => 'All Form Leads & Inquiries'
          };
        ?>
      </h2>
      <span class="fd-records-badge" style="font-size: 0.8rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 8px;">
        Total <?php echo $total_records; ?> record<?php echo $total_records !== 1 ? 's' : ''; ?>
      </span>
    </div>
  </div>

  <div class="hrms-card-body" style="padding: 0;">
    <div class="table-responsive fd-table-responsive">
      <table class="hrms-table staff-id-table fd-table">
        <thead>
          <tr>
            <th style="width: 50px;">#ID</th>
            <th>Applicant / Student</th>
            <th>Form Type</th>
            <th class="col-course">Course &amp; Preferred Shift</th>
            <th class="col-school">School / Inquiry Details</th>
            <th>Status</th>
            <th class="col-date">Date &amp; Time</th>
            <th style="text-align: right; min-width: 100px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($submissions)): ?>
            <tr>
              <td colspan="8" style="text-align: center; padding: 48px 20px; color: #64748b;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 14px;">
                  <i class="fa-solid fa-inbox"></i>
                </div>
                <h4 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-bottom: 6px;">No submissions found</h4>
                <p style="font-size: 0.85rem; margin: 0; color: #64748b;">
                  <?php if (!empty($search) || $filter_status !== 'all'): ?>
                    No entries match your search or status filter. Try clearing filters.
                  <?php else: ?>
                    Submissions from the Free Demo Class modal, Admission form, and Contact page will appear here.
                  <?php endif; ?>
                </p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($submissions as $sub): ?>
              <?php
                $type_class = match($sub['form_type']) {
                  'demo_class' => 'type-demo',
                  'admission'  => 'type-admission',
                  'inquiry'    => 'type-inquiry',
                  default      => 'type-demo'
                };

                $type_icon = match($sub['form_type']) {
                  'demo_class' => 'fa-graduation-cap',
                  'admission'  => 'fa-file-signature',
                  'inquiry'    => 'fa-headset',
                  default      => 'fa-clipboard'
                };

                $type_title = match($sub['form_type']) {
                  'demo_class' => 'Demo Class',
                  'admission'  => 'Admission',
                  'inquiry'    => 'Inquiry',
                  default      => ucfirst($sub['form_type'])
                };

                $status_class = match($sub['status']) {
                  'unread'    => 'status-unread',
                  'contacted' => 'status-contacted',
                  'enrolled'  => 'status-enrolled',
                  'closed'    => 'status-closed',
                  default     => 'status-unread'
                };
              ?>
              <tr class="<?php echo $sub['status'] === 'unread' ? 'row-unread' : ''; ?>">
                <!-- ID -->
                <td style="font-weight: 700; color: #64748b; font-size: 0.82rem;">
                  #<?php echo $sub['id']; ?>
                </td>

                <!-- Applicant / Student Details -->
                <td>
                  <div class="user-cell">
                    <div class="cell-avatar" style="background: <?php echo $sub['form_type'] === 'demo_class' ? '#eff6ff' : ($sub['form_type'] === 'admission' ? '#ecfdf5' : '#fffbeb'); ?>; color: <?php echo $sub['form_type'] === 'demo_class' ? '#1d4ed8' : ($sub['form_type'] === 'admission' ? '#047857' : '#b45309'); ?>;">
                      <?php 
                        $parts = explode(' ', trim($sub['full_name']));
                        echo strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                      ?>
                    </div>
                    <div class="cell-meta">
                      <span class="cell-name"><?php echo htmlspecialchars($sub['full_name']); ?></span>
                      <div style="font-size: 0.78rem; margin-top: 2px;">
                        <a href="tel:<?php echo htmlspecialchars($sub['phone']); ?>" style="color: #0284c7; text-decoration: none; font-weight: 600;">
                          <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($sub['phone']); ?>
                        </a>
                      </div>
                      <?php if (!empty($sub['email'])): ?>
                        <span style="font-size: 0.75rem; color: #64748b;"><i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($sub['email']); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($sub['parent_name'])): ?>
                        <span style="font-size: 0.72rem; color: #475569;"><i class="fa-solid fa-user-shield"></i> Parent: <?php echo htmlspecialchars($sub['parent_name']); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>

                <!-- Form Type -->
                <td>
                  <span class="badge-form-type <?php echo $type_class; ?>">
                    <i class="fa-solid <?php echo $type_icon; ?>"></i> <?php echo $type_title; ?>
                  </span>
                </td>

                <!-- Course & Preferred Shift -->
                <td class="col-course">
                  <?php if (!empty($sub['course'])): ?>
                    <div style="font-weight: 700; font-size: 0.85rem; color: #0f172a;">
                      <?php echo htmlspecialchars($sub['course']); ?>
                    </div>
                  <?php else: ?>
                    <span style="color: #94a3b8; font-size: 0.82rem;">—</span>
                  <?php endif; ?>

                  <?php if (!empty($sub['batch_timing'])): ?>
                    <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
                      <i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($sub['batch_timing']); ?>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- School / Inquiry Details -->
                <td class="col-school">
                  <?php if (!empty($sub['school_name'])): ?>
                    <div style="font-size: 0.82rem; color: #334155; font-weight: 600;">
                      <i class="fa-solid fa-school"></i> <?php echo htmlspecialchars($sub['school_name']); ?>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($sub['query_type'])): ?>
                    <div style="font-size: 0.78rem; font-weight: 700; color: #b45309;">
                      <i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($sub['query_type']); ?>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($sub['message'])): ?>
                    <div style="font-size: 0.78rem; color: #64748b; font-style: italic; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;" title="<?php echo htmlspecialchars($sub['message']); ?>">
                      "<?php echo htmlspecialchars($sub['message']); ?>"
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($sub['notes'])): ?>
                    <div style="font-size: 0.72rem; color: #0284c7; background: #f0f9ff; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px;">
                      <i class="fa-solid fa-comment-dots"></i> <?php echo htmlspecialchars(substr($sub['notes'], 0, 35)) . (strlen($sub['notes']) > 35 ? '...' : ''); ?>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- Status -->
                <td>
                  <span class="status-pill <?php echo $status_class; ?>">
                    <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i>
                    <?php echo htmlspecialchars(ucfirst($sub['status'])); ?>
                  </span>
                </td>

                <!-- Submitted Date -->
                <td class="col-date">
                  <div style="font-size: 0.82rem; font-weight: 600; color: #1e293b;">
                    <?php echo format_date($sub['created_at'], 'd M Y'); ?>
                  </div>
                  <div style="font-size: 0.74rem; color: #64748b;">
                    <?php echo format_time($sub['created_at']); ?>
                  </div>
                </td>

                <!-- Actions (Standard HRMS style buttons) -->
                <td style="text-align: right;">
                  <div style="display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end;">
                    
                    <!-- View Detail Button -->
                    <button type="button" class="sid-action-btn view" title="View Full Details" onclick='showLeadDetails(<?php echo htmlspecialchars(json_encode($sub), ENT_QUOTES, "UTF-8"); ?>)'>
                      <i class="fa-solid fa-eye"></i>
                    </button>

                    <!-- Update Status / Notes Button -->
                    <button type="button" class="sid-action-btn edit" title="Update Status & Notes" onclick='openStatusModal(<?php echo htmlspecialchars(json_encode($sub), ENT_QUOTES, "UTF-8"); ?>)'>
                      <i class="fa-solid fa-pen-to-square"></i>
                    </button>

                    <!-- Delete Button Form -->
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/form-data" style="margin: 0; display: inline;" onsubmit="return confirm('Permanently remove this submission record for <?php echo htmlspecialchars(addslashes($sub['full_name'])); ?>?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                      <button type="submit" class="sid-action-btn delete" title="Delete record">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>

                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Table Footer Pagination & Per-Page Controls (Standard HRMS Component) -->
    <div class="pagination-controls-wrap">
      
      <!-- Left: Showing summary & Limit Selector -->
      <div class="pagination-meta-group">
        <span style="font-size: 0.82rem; color: #475569;">
          <?php if ($total_records > 0): ?>
            Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'submission' : 'submissions'; ?>
          <?php else: ?>
            Showing <strong>0</strong> submissions
          <?php endif; ?>
        </span>

        <div class="pagination-limit-box">
          <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
          <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
            <?php foreach ($allowed_limits as $l): ?>
              <option value="<?php echo htmlspecialchars(get_form_data_pagination_url(1, $active_tab, $filter_status, $search, $sort, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
                <?php echo $l; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Right: Navigation page buttons -->
      <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav" aria-label="Form Submissions Pagination">
          <!-- Prev Page -->
          <a href="<?php echo $page > 1 ? htmlspecialchars(get_form_data_pagination_url($page - 1, $active_tab, $filter_status, $search, $sort, $limit)) : '#'; ?>" 
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
            <a href="<?php echo htmlspecialchars(get_form_data_pagination_url($p, $active_tab, $filter_status, $search, $sort, $limit)); ?>" 
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
          <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_form_data_pagination_url($page + 1, $active_tab, $filter_status, $search, $sort, $limit)) : '#'; ?>" 
             class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
             aria-label="Next Page"
             title="Next Page">
            <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
          </a>
        </nav>
      <?php endif; ?>

    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL 1: VIEW FULL DETAILS MODAL
     ══════════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="viewDetailModal">
  <div class="hrms-modal-card fd-modal-card" style="max-width: 620px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 14px;">
      <div>
        <span class="badge-form-type" id="detailTypeBadge" style="margin-bottom: 6px;">Form Type</span>
        <h3 id="detailApplicantName" style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;">Applicant Name</h3>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('viewDetailModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="hrms-modal-body" style="padding: 20px;">
      
      <div class="lead-modal-grid">
        <div class="lead-detail-item">
          <div class="lead-detail-label">Mobile / WhatsApp Number</div>
          <div class="lead-detail-val" id="detailPhone">—</div>
        </div>

        <div class="lead-detail-item">
          <div class="lead-detail-label">Email Address</div>
          <div class="lead-detail-val" id="detailEmail">—</div>
        </div>

        <div class="lead-detail-item">
          <div class="lead-detail-label">Parent / Guardian Name</div>
          <div class="lead-detail-val" id="detailParent">—</div>
        </div>

        <div class="lead-detail-item">
          <div class="lead-detail-label">Current Status</div>
          <div class="lead-detail-val" id="detailStatus">—</div>
        </div>

        <div class="lead-detail-item">
          <div class="lead-detail-label">Course / Standard</div>
          <div class="lead-detail-val" id="detailCourse">—</div>
        </div>

        <div class="lead-detail-item">
          <div class="lead-detail-label">Preferred Timing / Shift</div>
          <div class="lead-detail-val" id="detailBatch">—</div>
        </div>

        <div class="lead-detail-item" style="grid-column: span 2;">
          <div class="lead-detail-label">Current School / Junior College</div>
          <div class="lead-detail-val" id="detailSchool">—</div>
        </div>

        <div class="lead-detail-item" style="grid-column: span 2;">
          <div class="lead-detail-label">Query Subject / Inquiry Category</div>
          <div class="lead-detail-val" id="detailQueryType">—</div>
        </div>

        <div class="lead-detail-item" style="grid-column: span 2;">
          <div class="lead-detail-label">Message / Student Remarks</div>
          <div class="lead-detail-val" id="detailMessage" style="font-style: italic; color: #334155; line-height: 1.5;">—</div>
        </div>

        <div class="lead-detail-item" style="grid-column: span 2; background: #f0f9ff; border-color: #bae6fd;">
          <div class="lead-detail-label" style="color: #0369a1;">Counselor / Follow-up Notes</div>
          <div class="lead-detail-val" id="detailNotes" style="color: #0c4a6e;">No follow-up notes recorded yet.</div>
        </div>

        <div class="lead-detail-item">
          <div class="lead-detail-label">Submission Timestamp</div>
          <div class="lead-detail-val" id="detailTimestamp" style="font-size: 0.8rem; color: #64748b;">—</div>
        </div>

        <div class="lead-detail-item">
          <div class="lead-detail-label">Source URL / Page</div>
          <div class="lead-detail-val" id="detailSource" style="font-size: 0.8rem; color: #64748b;">—</div>
        </div>
      </div>

      <div style="display: flex; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
        <a href="#" id="detailCallBtn" class="btn-hrms" style="flex: 1; background: #0284c7; color: #fff;">
          <i class="fa-solid fa-phone"></i> Call Phone
        </a>
        <button type="button" class="btn-hrms" style="background: #e2e8f0; color: #334155;" onclick="closeModal('viewDetailModal')">
          Close
        </button>
      </div>

    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL 2: UPDATE STATUS & COUNSELOR NOTES MODAL
     ══════════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="statusModal">
  <div class="hrms-modal-card fd-modal-card" style="max-width: 480px;">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0;">
        <i class="fa-solid fa-pen-to-square" style="color: #0284c7;"></i> Update Lead Status
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('statusModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/form-data">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="id" id="statusModalLeadId" value="">
      
      <div class="hrms-modal-body" style="padding: 20px;">
        <div style="margin-bottom: 14px; background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
          <span style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Applicant</span>
          <div id="statusModalApplicantName" style="font-size: 0.96rem; font-weight: 800; color: #0f172a;">—</div>
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-weight: 700; font-size: 0.82rem; margin-bottom: 6px; display: block;">Follow-up Status</label>
          <select name="status" id="statusModalSelect" class="hrms-input" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.88rem;" required>
            <option value="unread">Unread / Pending</option>
            <option value="contacted">Contacted / In Progress</option>
            <option value="enrolled">Enrolled / Confirmed</option>
            <option value="closed">Closed / Not Interested</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-weight: 700; font-size: 0.82rem; margin-bottom: 6px; display: block;">Counselor Notes / Calling Remarks</label>
          <textarea name="notes" id="statusModalNotes" class="hrms-input" rows="4" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.85rem;" placeholder="e.g. Spoke to student's father. Scheduled demo for 10am batch on Monday..."></textarea>
        </div>
      </div>

      <div class="hrms-modal-footer" style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn-hrms" style="background: #e2e8f0; color: #475569;" onclick="closeModal('statusModal')">Cancel</button>
        <button type="submit" class="btn-hrms btn-hrms-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
// Modal control helpers
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add('show');
    modal.style.display = 'flex';
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('show');
    modal.style.display = 'none';
  }
}

// Populate and show Lead Detail Modal
function showLeadDetails(sub) {
  if (!sub) return;

  const typeLabels = {
    'demo_class': 'Free Demo Class Booking',
    'admission': 'Candidate Registration Application',
    'inquiry': 'Website Inquiry Message'
  };

  const typeClasses = {
    'demo_class': 'type-demo',
    'admission': 'type-admission',
    'inquiry': 'type-inquiry'
  };

  const badge = document.getElementById('detailTypeBadge');
  badge.textContent = typeLabels[sub.form_type] || sub.form_type;
  badge.className = 'badge-form-type ' + (typeClasses[sub.form_type] || 'type-demo');

  document.getElementById('detailApplicantName').textContent = sub.full_name || '—';
  document.getElementById('detailPhone').textContent = sub.phone || '—';
  document.getElementById('detailEmail').textContent = sub.email || '—';
  document.getElementById('detailParent').textContent = sub.parent_name || '—';
  document.getElementById('detailStatus').textContent = (sub.status || 'unread').toUpperCase();
  document.getElementById('detailCourse').textContent = sub.course || '—';
  document.getElementById('detailBatch').textContent = sub.batch_timing || '—';
  document.getElementById('detailSchool').textContent = sub.school_name || '—';
  document.getElementById('detailQueryType').textContent = sub.query_type || '—';
  document.getElementById('detailMessage').textContent = sub.message ? ('"' + sub.message + '"') : 'No additional remarks provided.';
  document.getElementById('detailNotes').textContent = sub.notes || 'No counselor notes recorded yet.';
  document.getElementById('detailTimestamp').textContent = sub.created_at || '—';
  document.getElementById('detailSource').textContent = sub.source_page || 'Website Direct';

  // Phone call action link
  document.getElementById('detailCallBtn').href = 'tel:' + (sub.phone || '');

  openModal('viewDetailModal');
}

// Populate and show Status / Notes update modal
function openStatusModal(sub) {
  if (!sub) return;
  document.getElementById('statusModalLeadId').value = sub.id;
  document.getElementById('statusModalApplicantName').textContent = sub.full_name + ' (#' + sub.id + ')';
  document.getElementById('statusModalSelect').value = sub.status || 'unread';
  document.getElementById('statusModalNotes').value = sub.notes || '';
  openModal('statusModal');
}

// Close modals when clicking outside card
window.addEventListener('click', function(e) {
  if (e.target.classList.contains('hrms-modal')) {
    e.target.classList.remove('show');
    e.target.style.display = 'none';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
