<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
if ($current_user['role'] === 'staff') {
    require_menu_permission('admin_expenses');
}
$page_title = 'Expenses & Vouchers | KSV Academy HRMS';
$page_heading = 'Expenses';
$page_subheading = 'Campus Expenditure Vouchers, Vendor Payments & Ledger Tracking';
$active_menu = 'expenses';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Filter inputs
$filter_cat_id = intval($_GET['category_id'] ?? 0);
$filter_method = trim($_GET['method'] ?? '');
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');
$search = trim($_GET['search'] ?? '');

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_expense') {
        $category_id = intval($_POST['category_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
        $payment_method = trim($_POST['payment_method'] ?? 'Cash');
        $invoice_ref = trim($_POST['invoice_ref'] ?? '');
        $vendor_name = trim($_POST['vendor_name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($title) && $amount > 0 && $category_id > 0) {
            $stmt = $db->prepare("
                INSERT INTO expenses (session_id, category_id, title, amount, expense_date, payment_method, invoice_ref, vendor_name, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$session_id, $category_id, $title, $amount, $expense_date, $payment_method, $invoice_ref, $vendor_name, $notes, $current_user['id']]);

            set_flash('success', "Expense voucher '{$title}' of " . format_inr($amount) . " recorded successfully!");
            header('Location: ' . ADMIN_URL . '/expenses');
            exit;
        } else {
            set_flash('error', 'Please fill all mandatory fields (Title, Category, Amount).');
        }
    }

    if ($action === 'delete_expense') {
        $del_id = intval($_POST['expense_id'] ?? 0);
        if ($del_id > 0) {
            $db->prepare("DELETE FROM expenses WHERE id = ?")->execute([$del_id]);
            set_flash('success', 'Expense voucher successfully deleted.');
            header('Location: ' . ADMIN_URL . '/expenses');
            exit;
        }
    }
}

// Fetch all categories for filter and form
$categories = $db->query("SELECT id, category_name FROM expense_categories ORDER BY category_name ASC")->fetchAll();

// Pagination Settings (10, 20, 30, 40, 50)
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Build WHERE filter conditions
$where_clauses = ["e.session_id = ?"];
$where_params = [$session_id];

if ($filter_cat_id > 0) {
    $where_clauses[] = "e.category_id = ?";
    $where_params[] = $filter_cat_id;
}
if (!empty($filter_method)) {
    $where_clauses[] = "e.payment_method = ?";
    $where_params[] = $filter_method;
}
if (!empty($filter_start_date)) {
    $where_clauses[] = "e.expense_date >= ?";
    $where_params[] = $filter_start_date;
}
if (!empty($filter_end_date)) {
    $where_clauses[] = "e.expense_date <= ?";
    $where_params[] = $filter_end_date;
}
if (!empty($search)) {
    $where_clauses[] = "(e.title LIKE ? OR e.invoice_ref LIKE ? OR e.vendor_name LIKE ? OR e.notes LIKE ?)";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
}

$where_sql = implode(' AND ', $where_clauses);

// Accurate SQL aggregate for total vouchers and disbursed sum under current filters
$agg_stmt = $db->prepare("
    SELECT COUNT(*) as total_vouchers,
           COALESCE(SUM(e.amount), 0) as total_amount
    FROM expenses e
    JOIN expense_categories c ON e.category_id = c.id
    WHERE {$where_sql}
");
$agg_stmt->execute($where_params);
$agg = $agg_stmt->fetch(PDO::FETCH_ASSOC);
$total_records = intval($agg['total_vouchers'] ?? 0);
$total_expense_sum = floatval($agg['total_amount'] ?? 0);

// Calculate Pagination Bounds
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch Paginated Expense Records
$query = "
    SELECT e.*, c.category_name
    FROM expenses e
    JOIN expense_categories c ON e.category_id = c.id
    WHERE {$where_sql}
    ORDER BY e.expense_date DESC, e.id DESC
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
$expenses = $stmt->fetchAll();

// Global session total for top stat card
$global_sum_stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_spent, COUNT(*) as total_vouchers FROM expenses WHERE session_id = ?");
$global_sum_stmt->execute([$session_id]);
$global_metrics = $global_sum_stmt->fetch();
$all_time_spent = floatval($global_metrics['total_spent'] ?? 0);
$all_time_vouchers = intval($global_metrics['total_vouchers'] ?? 0);

// Helper to preserve active query parameters in pagination links
function get_expenses_pagination_url($target_page, $search, $filter_cat_id, $filter_method, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if ($filter_cat_id > 0) $params['category_id'] = $filter_cat_id;
    if (!empty($filter_method)) $params['method'] = $filter_method;
    if ($limit !== 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    $query_str = !empty($params) ? '?' . http_build_query($params) : '';
    return ADMIN_URL . '/expenses' . $query_str;
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Staff ID Style Table & Badge Styles (Simple & Sober - Matching staff-id) ── */
.staff-id-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.staff-id-table thead th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.73rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 11px 12px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.staff-id-table tbody td {
  padding: 11px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.84rem;
}
.staff-id-table tbody tr {
  transition: background-color 0.15s ease;
}
.staff-id-table tbody tr:hover {
  background-color: #f8fafc;
}

/* Badge & Tag Elements */
.exp-cat-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
}

.mode-pill-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.ref-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
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

.voucher-id-pill {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #64748b;
  padding: 1px 6px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.70rem;
  font-weight: 600;
}

/* Action Icon Buttons */
.sid-action-btn {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 0.76rem;
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

/* ── Toolbar Single-Line Desktop Alignment & Spacing ── */
.roaster-toolbar-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.expenses-filter-form {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  flex: 1;
}

.roaster-toolbar-card .expenses-search-box {
  min-width: 200px;
  max-width: 240px;
  flex: 1 1 200px;
}

.expenses-filter-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
}

.expenses-filter-row .roaster-select-box {
  display: flex;
  align-items: center;
  position: relative;
}

.expenses-filter-row .roaster-select-box select {
  min-width: 145px;
  max-width: 175px;
}

.expenses-filter-btn-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.expenses-toolbar-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
  flex-shrink: 0;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS FOR EXPENSES & VOUCHERS
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  /* Top Stat KPI Grid */
  .stat-grid.expenses-stat-grid,
  .stat-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
    margin-bottom: 16px !important;
  }
  .stat-card {
    padding: 12px 14px !important;
    border-radius: 12px !important;
  }
  .stat-card-top {
    gap: 8px !important;
  }
  .stat-label {
    font-size: 0.70rem !important;
  }
  .stat-number {
    font-size: 1.25rem !important;
  }
  .stat-icon-wrapper {
    width: 38px !important;
    height: 38px !important;
    font-size: 1.05rem !important;
    border-radius: 10px !important;
  }
  .stat-card-footer {
    font-size: 0.68rem !important;
    flex-wrap: wrap !important;
    gap: 4px !important;
    margin-top: 10px !important;
    padding-top: 8px !important;
  }

  /* Toolbar & Filter Card */
  .roaster-toolbar-card {
    padding: 12px 14px !important;
    margin-bottom: 14px !important;
    border-radius: 12px !important;
  }
  .roaster-toolbar-inner {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 10px !important;
  }
  .expenses-filter-form {
    flex-direction: column !important;
    align-items: stretch !important;
    width: 100% !important;
    gap: 8px !important;
  }
  .expenses-search-box {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    height: 40px !important;
    padding: 8px 12px !important;
    border-radius: 9px !important;
  }
  .expenses-filter-row {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8px !important;
    width: 100% !important;
  }
  .expenses-filter-row .roaster-select-box {
    width: 100% !important;
    min-width: 0 !important;
  }
  .expenses-filter-row .roaster-select-box select {
    width: 100% !important;
    min-width: 0 !important;
    height: 40px !important;
    font-size: 0.80rem !important;
    border-radius: 9px !important;
    padding: 8px 24px 8px 28px !important;
  }
  .expenses-filter-btn-group {
    display: flex !important;
    gap: 8px !important;
    width: 100% !important;
  }
  .expenses-filter-btn-group .roaster-btn {
    flex: 1 !important;
    height: 40px !important;
    font-size: 0.80rem !important;
    padding: 0 16px !important;
    justify-content: center !important;
    border-radius: 9px !important;
    white-space: nowrap !important;
  }
  .expenses-toolbar-actions {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8px !important;
    width: 100% !important;
    margin: 2px 0 0 0 !important;
  }
  .expenses-records-badge {
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
    font-size: 0.78rem !important;
    padding: 0 10px !important;
    box-sizing: border-box !important;
    border-radius: 9px !important;
  }
  .expenses-add-btn {
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
    font-size: 0.80rem !important;
    padding: 0 12px !important;
    box-sizing: border-box !important;
    border-radius: 9px !important;
    white-space: nowrap !important;
    margin: 0 !important;
  }

  /* Expenses Directory Card & Header */
  .expenses-directory-card {
    border-radius: 12px !important;
    margin-bottom: 20px !important;
  }
  .expenses-card-header {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 10px !important;
  }
  .expenses-title-block {
    width: 100% !important;
  }
  .expenses-card-title {
    font-size: 0.95rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
  .expenses-subtitle {
    font-size: 0.74rem !important;
    line-height: 1.4 !important;
  }
  .expenses-header-meta {
    width: 100% !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding-top: 8px !important;
    border-top: 1px dashed #e2e8f0 !important;
    font-size: 0.75rem !important;
  }

  /* Table & Scrolling */
  .table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    width: 100% !important;
    margin: 0 !important;
  }
  .expenses-table {
    min-width: 860px !important;
  }
  .expenses-table thead th {
    padding: 9px 8px !important;
    font-size: 0.68rem !important;
  }
  .expenses-table tbody td {
    padding: 9px 8px !important;
    font-size: 0.78rem !important;
  }
  .sid-action-btn {
    width: 28px !important;
    height: 28px !important;
    font-size: 0.72rem !important;
  }

  /* Pagination Controls */
  .pagination-controls-wrap {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 12px !important;
    padding: 12px 14px !important;
  }
  .pagination-meta-group {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 8px !important;
    width: 100% !important;
  }
  .pagination-limit-box {
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
  }
  .pagination-limit-select {
    padding: 4px 8px !important;
    font-size: 0.75rem !important;
    height: 30px !important;
  }
  .pagination-nav {
    display: flex !important;
    justify-content: center !important;
    width: 100% !important;
    flex-wrap: wrap !important;
    gap: 4px !important;
  }
  .pagination-btn {
    width: 30px !important;
    height: 30px !important;
    font-size: 0.75rem !important;
    padding: 0 !important;
  }
  .pagination-legend-group {
    margin-left: 0 !important;
    width: 100% !important;
    justify-content: space-between !important;
    font-size: 0.74rem !important;
    border-top: 1px dashed #e2e8f0 !important;
    padding-top: 8px !important;
  }

  /* Modals Responsiveness */
  #addExpenseModal,
  #voucherModal {
    padding: 12px !important;
  }
  .expenses-modal-box,
  .voucher-modal-box {
    padding: 16px 14px !important;
    border-radius: 12px !important;
    max-height: 94vh !important;
  }
  .expenses-modal-header h3,
  .voucher-modal-header h3 {
    font-size: 1.05rem !important;
  }
  .expenses-modal-grid-2 {
    grid-template-columns: 1fr !important;
    gap: 12px !important;
    margin-bottom: 12px !important;
  }
  .expenses-modal-footer {
    flex-direction: column-reverse !important;
    gap: 8px !important;
    width: 100% !important;
  }
  .expenses-cancel-btn,
  .expenses-submit-btn {
    width: 100% !important;
    height: 40px !important;
    justify-content: center !important;
  }
  .voucher-grid-2 {
    grid-template-columns: 1fr !important;
    gap: 8px !important;
  }
  .voucher-modal-footer {
    flex-direction: column-reverse !important;
    gap: 8px !important;
    width: 100% !important;
  }
  .voucher-print-btn,
  .voucher-close-btn {
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
  }
}

/* Mobile Screens (<= 480px) */
@media (max-width: 480px) {
  .expenses-filter-row {
    grid-template-columns: 1fr !important;
  }
}

/* Micro Devices (<= 360px) */
@media (max-width: 360px) {
  .stat-grid.expenses-stat-grid,
  .stat-grid {
    grid-template-columns: 1fr !important;
  }
  .expenses-toolbar-actions {
    grid-template-columns: 1fr !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR EXPENSES & VOUCHERS
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
[data-theme="dark"] .stat-card.purple .stat-number {
  color: #f87171 !important;
}
[data-theme="dark"] .stat-card-progress {
  background: #334155 !important;
}
[data-theme="dark"] .stat-card.purple .stat-icon-wrapper {
  background: rgba(239, 68, 68, 0.15) !important;
  color: #f87171 !important;
  border-color: rgba(239, 68, 68, 0.3) !important;
}
[data-theme="dark"] .stat-card.blue .stat-icon-wrapper {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
}
[data-theme="dark"] .stat-card.green .stat-icon-wrapper {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
}
[data-theme="dark"] .stat-card.amber .stat-icon-wrapper {
  background: rgba(245, 158, 11, 0.15) !important;
  color: #fbbf24 !important;
  border-color: rgba(245, 158, 11, 0.3) !important;
}
[data-theme="dark"] .stat-card-footer {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-card-footer span strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #dc2626"],
[data-theme="dark"] .stat-card-footer span[style*="color:#dc2626"] {
  color: #f87171 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #0284c7"],
[data-theme="dark"] .stat-card-footer span[style*="color:#0284c7"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #059669"],
[data-theme="dark"] .stat-card-footer span[style*="color:#059669"] {
  color: #34d399 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #d97706"],
[data-theme="dark"] .stat-card-footer span[style*="color:#d97706"] {
  color: #fbbf24 !important;
}

/* 2. Filter & Search Toolbar */
[data-theme="dark"] .roaster-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .expenses-search-box {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .expenses-search-box:focus-within {
  border-color: #0092df !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .expenses-search-box i {
  color: #94a3b8 !important;
}
[data-theme="dark"] .expenses-search-box input {
  color: #f8fafc !important;
}
[data-theme="dark"] .expenses-search-box input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .expenses-search-box .clear-search-btn {
  color: #94a3b8 !important;
}
[data-theme="dark"] .expenses-search-box .clear-search-btn:hover {
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
[data-theme="dark"] .expenses-records-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .expenses-records-badge strong {
  color: #f8fafc !important;
}

/* 3. Expenses Directory Card & Header */
[data-theme="dark"] .expenses-directory-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .expenses-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .expenses-card-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .expenses-session-badge {
  background: #0f172a !important;
  color: #cbd5e1 !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .expenses-subtitle {
  color: #94a3b8 !important;
}
[data-theme="dark"] .expenses-header-meta {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .expenses-disbursed-total {
  color: #f87171 !important;
}
[data-theme="dark"] .expenses-records-total {
  color: #f8fafc !important;
}
[data-theme="dark"] .expenses-meta-divider {
  color: #475569 !important;
}

/* 4. Expenses Master Table */
[data-theme="dark"] .expenses-table thead th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .expenses-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .expenses-table tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.02) !important;
}
[data-theme="dark"] .exp-date-text,
[data-theme="dark"] .exp-title-text {
  color: #f8fafc !important;
}
[data-theme="dark"] .exp-notes-text {
  color: #94a3b8 !important;
}
[data-theme="dark"] .exp-vendor-text {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .exp-amount-text {
  color: #f87171 !important;
}

/* Badges & Action Buttons */
[data-theme="dark"] .exp-cat-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .exp-cat-badge i {
  color: #94a3b8 !important;
}
[data-theme="dark"] .mode-pill-chip {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .mode-pill-chip i {
  color: #38bdf8 !important;
}
[data-theme="dark"] .ref-code-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .voucher-id-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .sid-action-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .sid-action-btn.view {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .sid-action-btn.view:hover {
  background: #0284c7 !important;
  color: #ffffff !important;
}
[data-theme="dark"] .sid-action-btn.delete {
  background: rgba(220, 38, 38, 0.15) !important;
  border-color: rgba(220, 38, 38, 0.3) !important;
  color: #f87171 !important;
}
[data-theme="dark"] .sid-action-btn.delete:hover {
  background: #dc2626 !important;
  color: #ffffff !important;
}

/* Empty State */
[data-theme="dark"] .expenses-table tbody td div[style*="background: #f1f5f9"],
[data-theme="dark"] .expenses-table tbody td div[style*="background:#f1f5f9"] {
  background: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .expenses-table tbody td h4 {
  color: #f8fafc !important;
}
[data-theme="dark"] .expenses-table tbody td p {
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
  background: var(--hrms-primary, #0092df) !important;
  border-color: var(--hrms-primary, #0092df) !important;
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
[data-theme="dark"] .pagination-legend-group strong,
[data-theme="dark"] .pagination-legend-text strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-legend-group span[style*="color: #cbd5e1"],
[data-theme="dark"] .pagination-legend-group span[style*="color:#cbd5e1"] {
  color: #475569 !important;
}

/* 6. Modals (Add Expense Voucher & View Voucher Details) */
[data-theme="dark"] .hrms-modal-overlay {
  background: rgba(0, 0, 0, 0.75) !important;
}
[data-theme="dark"] .expenses-modal-box,
[data-theme="dark"] .voucher-modal-box {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7) !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .expenses-modal-header,
[data-theme="dark"] .voucher-modal-header {
  border-bottom: 1px solid #334155 !important;
}
[data-theme="dark"] .expenses-modal-header h3,
[data-theme="dark"] .voucher-modal-header h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] .expenses-modal-close,
[data-theme="dark"] .voucher-modal-close {
  background: #334155 !important;
  color: #cbd5e1 !important;
  border: 1px solid #475569 !important;
}
[data-theme="dark"] .expenses-modal-close:hover,
[data-theme="dark"] .voucher-modal-close:hover {
  background: #475569 !important;
  color: #ffffff !important;
}
[data-theme="dark"] .expenses-field-label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .expenses-modal-box .hrms-input,
[data-theme="dark"] .voucher-modal-box .hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .expenses-modal-box .hrms-input:focus,
[data-theme="dark"] .voucher-modal-box .hrms-input:focus {
  border-color: #0092df !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.25) !important;
}
[data-theme="dark"] .expenses-modal-box .hrms-input::placeholder,
[data-theme="dark"] .voucher-modal-box .hrms-input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .expenses-modal-box .hrms-input option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .expenses-modal-box input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(1);
}
[data-theme="dark"] .expenses-modal-box input[name="amount"] {
  color: #f87171 !important;
}
[data-theme="dark"] .expenses-modal-footer {
  border-top: 1px solid #334155 !important;
  padding-top: 16px !important;
}

/* 7. Voucher Details Card */
[data-theme="dark"] .voucher-receipt-card {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .voucher-meta-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .voucher-ref-code {
  color: #38bdf8 !important;
}
[data-theme="dark"] .voucher-date-text {
  color: #f8fafc !important;
}
[data-theme="dark"] .voucher-title-text {
  color: #f8fafc !important;
}
[data-theme="dark"] .voucher-cat-text {
  color: #94a3b8 !important;
}
[data-theme="dark"] .voucher-cat-text strong {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .voucher-amount-row {
  border-top-color: #334155 !important;
}
[data-theme="dark"] .voucher-amount-label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .voucher-amount-val {
  color: #f87171 !important;
}
[data-theme="dark"] .voucher-subcard {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .voucher-subcard-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .voucher-subcard-val {
  color: #f8fafc !important;
}
[data-theme="dark"] .voucher-subcard-mode {
  color: #38bdf8 !important;
}
[data-theme="dark"] .voucher-invoice-box {
  background: rgba(2, 132, 199, 0.12) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
}
[data-theme="dark"] .voucher-invoice-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .voucher-invoice-ref {
  color: #38bdf8 !important;
}
[data-theme="dark"] .voucher-notes-box {
  background: #0f172a !important;
  border-left-color: #0092df !important;
}
[data-theme="dark"] .voucher-notes-label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .voucher-notes-text {
  color: #94a3b8 !important;
}
[data-theme="dark"] .voucher-modal-footer {
  border-top-color: #334155 !important;
}
</style>

<!-- ══════════════════════════════════════════════════════
     TOP KPI STAT METRICS GRID
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid expenses-stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
  
  <!-- Total Expenditure -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Expenditure</span>
          <span class="stat-number" style="color: #dc2626;"><?php echo format_inr($total_expense_sum); ?></span>
        </div>
        <div class="stat-icon-wrapper" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; border-color: #fee2e2;">
          <i class="fa-solid fa-wallet"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #dc2626;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-arrow-trend-down" style="color: #dc2626; margin-right: 4px;"></i> Filtered Disbursements</span>
      <span style="color: #dc2626; font-weight: 700;">Total: <?php echo format_inr($all_time_spent); ?></span>
    </div>
  </div>

  <!-- Total Vouchers -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Vouchers Issued</span>
          <span class="stat-number"><?php echo number_format($total_records); ?></span>
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
      <span><i class="fa-solid fa-file-invoice" style="color: #0284c7; margin-right: 4px;"></i> Registered Bills</span>
      <span style="color: #0284c7; font-weight: 700;">Overall: <?php echo $all_time_vouchers; ?> records</span>
    </div>
  </div>

  <!-- Active Categories -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Budget Categories</span>
          <span class="stat-number"><?php echo count($categories); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-tags"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-layer-group" style="color: #059669; margin-right: 4px;"></i> Active Cost Centers</span>
      <span style="color: #059669; font-weight: 700;">Campus Operations</span>
    </div>
  </div>

  <!-- Working Session -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Active Academic Session</span>
          <span class="stat-number" style="font-size: 1.35rem;"><?php echo htmlspecialchars($active_session['session_name']); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-calendar-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-shield-halved" style="color: #d97706; margin-right: 4px;"></i> Verified Ledger</span>
      <span style="color: #d97706; font-weight: 700;">Active Financial Year</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     FILTER & SEARCH ROASTER TOOLBAR
     ══════════════════════════════════════════════════════ -->
<!-- ══════════════════════════════════════════════════════
     FILTER & SEARCH ROASTER TOOLBAR
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner">
    
    <!-- Left: Filter Form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/expenses" class="roaster-filter-form expenses-filter-form">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      
      <!-- Search Input -->
      <div class="roaster-search-box expenses-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search title, invoice, vendor..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_expenses_pagination_url(1, '', $filter_cat_id, $filter_method, $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <!-- Filter Dropdowns Row -->
      <div class="expenses-filter-row">
        <!-- Category Filter Dropdown -->
        <div class="roaster-select-box">
          <i class="fa-solid fa-folder-tree"></i>
          <select name="category_id" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?php echo $c['id']; ?>" <?php echo $filter_cat_id == $c['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['category_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <i class="fa-solid fa-chevron-down select-chevron"></i>
        </div>

        <!-- Payment Mode Filter Dropdown -->
        <div class="roaster-select-box">
          <i class="fa-solid fa-credit-card"></i>
          <select name="method" onchange="this.form.submit()">
            <option value="">All Payment Modes</option>
            <option value="Cash" <?php echo $filter_method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
            <option value="UPI" <?php echo $filter_method === 'UPI' ? 'selected' : ''; ?>>UPI / QR</option>
            <option value="Bank Transfer" <?php echo $filter_method === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
            <option value="Cheque" <?php echo $filter_method === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
          </select>
          <i class="fa-solid fa-chevron-down select-chevron"></i>
        </div>
      </div>

      <!-- Filter Action Buttons -->
      <div class="expenses-filter-btn-group">
        <button type="submit" class="roaster-btn secondary">
          <i class="fa-solid fa-sliders"></i> Filter
        </button>

        <?php if (!empty($search) || $filter_cat_id > 0 || !empty($filter_method) || !empty($filter_start_date) || !empty($filter_end_date)): ?>
          <a href="<?php echo ADMIN_URL; ?>/expenses<?php echo $limit !== 10 ? '?limit=' . $limit : ''; ?>" class="roaster-btn ghost" title="Reset all filters">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Right: Vouchers Live Count & Add Button -->
    <div class="roaster-toolbar-actions expenses-toolbar-actions">
      <div class="scholars-count-badge expenses-records-badge">
        <span class="count-dot" style="background: #ef4444;"></span>
        <span><strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'Voucher' : 'Vouchers'; ?></span>
      </div>

      <button type="button" onclick="openAddExpenseModal();" class="roaster-add-btn expenses-add-btn" style="padding: 7px 16px; font-size: 0.82rem;" title="Record New Expense Voucher">
        <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-plus"></i></div>
        <span>Add Expense</span>
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     CAMPUS OPERATIONAL EXPENSES TABLE
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card expenses-directory-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
  <div class="hrms-card-header expenses-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div class="expenses-title-block">
      <h3 class="card-title expenses-card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-file-invoice-dollar" style="color: #0284c7;"></i>
        <span>Campus Operational Expenses &amp; Voucher Ledger</span>
        <span class="expenses-session-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p class="expenses-subtitle" style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Comprehensive breakdown of utility bills, laboratory consignments, vendor payouts, and institutional purchases.
      </p>
    </div>

    <div class="expenses-header-meta" style="font-size: 0.78rem; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 12px;">
      <span>Disbursed: <strong class="expenses-disbursed-total" style="color: #dc2626; font-size: 0.95rem; font-family: 'Outfit', sans-serif;"><?php echo format_inr($total_expense_sum); ?></strong></span>
      <span class="expenses-meta-divider" style="color: #cbd5e1;">|</span>
      <span>Total Records: <strong class="expenses-records-total" style="color: #0f172a;"><?php echo $total_records; ?></strong></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="hrms-table staff-id-table expenses-table">
      <thead>
        <tr>
          <th style="min-width: 125px;">Date &amp; Voucher</th>
          <th style="min-width: 220px;">Expense Title &amp; Details</th>
          <th style="min-width: 140px;">Category Head</th>
          <th style="min-width: 160px;">Vendor / Payee</th>
          <th style="min-width: 130px;">Invoice / Ref</th>
          <th style="min-width: 110px;">Amount (₹)</th>
          <th style="min-width: 120px;">Payment Mode</th>
          <th style="min-width: 80px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($expenses)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-receipt"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Expense Records Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                No vouchers match your current filters in Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
              </p>
              <a href="<?php echo ADMIN_URL; ?>/expenses" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                <i class="fa-solid fa-rotate-left"></i> Reset All Filters
              </a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($expenses as $exp): ?>
            <?php 
              // Category icon
              $cat_name = strtolower($exp['category_name']);
              $cat_icon = 'fa-tag';
              if (strpos($cat_name, 'elect') !== false || strpos($cat_name, 'util') !== false) {
                $cat_icon = 'fa-bolt';
              } elseif (strpos($cat_name, 'stat') !== false || strpos($cat_name, 'print') !== false) {
                $cat_icon = 'fa-pen-nib';
              } elseif (strpos($cat_name, 'lab') !== false || strpos($cat_name, 'sci') !== false) {
                $cat_icon = 'fa-flask-vial';
              } elseif (strpos($cat_name, 'it') !== false || strpos($cat_name, 'soft') !== false || strpos($cat_name, 'net') !== false) {
                $cat_icon = 'fa-server';
              } elseif (strpos($cat_name, 'maint') !== false || strpos($cat_name, 'build') !== false) {
                $cat_icon = 'fa-wrench';
              }

              // Payment mode icon
              $mode_lower = strtolower($exp['payment_method']);
              $mode_icon = 'fa-money-bill-wave';
              if (strpos($mode_lower, 'upi') !== false || strpos($mode_lower, 'qr') !== false) {
                $mode_icon = 'fa-qrcode';
              } elseif (strpos($mode_lower, 'bank') !== false || strpos($mode_lower, 'neft') !== false || strpos($mode_lower, 'net') !== false) {
                $mode_icon = 'fa-building-columns';
              } elseif (strpos($mode_lower, 'cheque') !== false) {
                $mode_icon = 'fa-money-check';
              }
            ?>
            <tr>
              
              <!-- 1. Date -->
              <td>
                <div class="cell-name exp-date-text" style="font-size: 0.84rem; font-weight: 700; color: #0f172a;">
                  <?php echo format_date($exp['expense_date']); ?>
                </div>
                <div style="margin-top: 2px;">
                  <span class="voucher-id-pill">#<?php echo $exp['id']; ?></span>
                </div>
              </td>

              <!-- 2. Expense Title & Details -->
              <td>
                <div class="cell-name exp-title-text" style="font-weight: 700; font-size: 0.90rem; color: #0f172a; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                  <?php echo htmlspecialchars($exp['title']); ?>
                </div>
                <?php if (!empty($exp['notes'])): ?>
                  <div class="cell-sub exp-notes-text" style="font-size: 0.73rem; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-circle-info" style="color: #94a3b8; font-size: 0.65rem;"></i>
                    <span><?php echo htmlspecialchars($exp['notes']); ?></span>
                  </div>
                <?php endif; ?>
              </td>

              <!-- 3. Category Head -->
              <td>
                <span class="exp-cat-badge">
                  <i class="fa-solid <?php echo $cat_icon; ?>" style="color: #64748b; font-size: 0.68rem;"></i>
                  <span><?php echo htmlspecialchars($exp['category_name']); ?></span>
                </span>
              </td>

              <!-- 4. Vendor / Payee -->
              <td>
                <div class="cell-sub exp-vendor-text" style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #334155; font-size: 0.82rem;">
                  <i class="fa-solid fa-store" style="color: #94a3b8; font-size: 0.70rem;"></i>
                  <span><?php echo htmlspecialchars($exp['vendor_name'] ?: 'Official Vendor'); ?></span>
                </div>
              </td>

              <!-- 5. Invoice / Ref -->
              <td>
                <?php if (!empty($exp['invoice_ref']) && $exp['invoice_ref'] !== '—'): ?>
                  <span class="ref-code-pill">
                    <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
                    <span><?php echo htmlspecialchars($exp['invoice_ref']); ?></span>
                  </span>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.75rem; font-weight: 600;">—</span>
                <?php endif; ?>
              </td>

              <!-- 6. Amount -->
              <td>
                <strong class="exp-amount-text" style="color: #dc2626; font-size: 0.92rem; font-family: 'Outfit', sans-serif;">
                  <?php echo format_inr($exp['amount']); ?>
                </strong>
              </td>

              <!-- 7. Mode -->
              <td>
                <span class="mode-pill-chip">
                  <i class="fa-solid <?php echo $mode_icon; ?>" style="color: #0284c7; font-size: 0.68rem;"></i>
                  <span><?php echo htmlspecialchars($exp['payment_method']); ?></span>
                </span>
              </td>

              <!-- 8. Actions -->
              <td style="text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; gap: 5px; justify-content: flex-end; align-items: center;">
                  
                  <!-- View Details Button -->
                  <button type="button" onclick="openVoucherModal(<?php echo htmlspecialchars(json_encode($exp)); ?>);" class="sid-action-btn view" title="View Voucher Details">
                    <i class="fa-solid fa-eye"></i>
                  </button>

                  <!-- Delete Voucher -->
                  <form method="POST" action="<?php echo ADMIN_URL; ?>/expenses" style="display: inline; margin: 0;" onsubmit="return confirm('Permanently remove this expense voucher record?');">
                    <input type="hidden" name="action" value="delete_expense">
                    <input type="hidden" name="expense_id" value="<?php echo $exp['id']; ?>">
                    <button type="submit" class="sid-action-btn delete" title="Delete Voucher">
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

  <!-- Table Footer Pagination & Per-Page Controls (Matches HRMS standard) -->
  <div class="pagination-controls-wrap">
    
    <!-- Left: Showing summary & Limit Selector -->
    <div class="pagination-meta-group">
      <span style="font-size: 0.82rem; color: #475569;">
        <?php if ($total_records > 0): ?>
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> expense <?php echo $total_records === 1 ? 'voucher' : 'vouchers'; ?>
        <?php else: ?>
          Showing <strong>0</strong> expense vouchers
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_expenses_pagination_url(1, $search, $filter_cat_id, $filter_method, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Expenses Ledger Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_expenses_pagination_url($page - 1, $search, $filter_cat_id, $filter_method, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_expenses_pagination_url($p, $search, $filter_cat_id, $filter_method, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_expenses_pagination_url($page + 1, $search, $filter_cat_id, $filter_method, $limit)) : '#'; ?>" 
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
        Verified Ledger
      </span>
      <span class="expenses-meta-divider" style="color: #cbd5e1;">|</span>
      <span class="pagination-legend-text">Session: <strong style="color: #334155;"><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: Add Expense Voucher Form
     ══════════════════════════════════════════════════════ -->
<div id="addExpenseModal" class="hrms-modal-overlay" onclick="if (event.target === this) closeAddExpenseModal();" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px); overflow-y: auto; padding: 20px;">
  <div class="hrms-modal-content expenses-modal-box" style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 600px; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); max-height: 92vh; overflow-y: auto;">
    
    <div class="expenses-modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 14px;">
      <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-file-circle-plus" style="color: #0092df;"></i>
        <span>Record New Expense Voucher</span>
      </h3>
      <button type="button" onclick="closeAddExpenseModal();" class="expenses-modal-close" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/expenses" id="addExpenseForm">
      <input type="hidden" name="action" value="add_expense">

      <!-- Expense Title -->
      <div style="margin-bottom: 16px;">
        <label class="expenses-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Expense Title / Purpose *</label>
        <input type="text" name="title" required placeholder="e.g. Science Lab Reagents, Electricity Bill, Generator Fuel" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
      </div>

      <!-- Category & Amount -->
      <div class="expenses-modal-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
        <div>
          <label class="expenses-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Expense Category *</label>
          <select name="category_id" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600;">
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="expenses-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Amount (₹) *</label>
          <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 800; color: #dc2626;">
        </div>
      </div>

      <!-- Date & Payment Mode -->
      <div class="expenses-modal-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
        <div>
          <label class="expenses-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Expense Date *</label>
          <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
        </div>
        <div>
          <label class="expenses-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Payment Mode *</label>
          <select name="payment_method" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600;">
            <option value="Cash">Cash Payment</option>
            <option value="UPI">UPI / Digital QR</option>
            <option value="Bank Transfer">Bank Transfer (NEFT / RTGS)</option>
            <option value="Cheque">Official Cheque</option>
          </select>
        </div>
      </div>

      <!-- Vendor & Invoice Ref -->
      <div class="expenses-modal-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
        <div>
          <label class="expenses-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Vendor / Payee Name</label>
          <input type="text" name="vendor_name" placeholder="e.g. Apex Scientific Supplies" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
        </div>
        <div>
          <label class="expenses-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Invoice / Bill Number</label>
          <input type="text" name="invoice_ref" placeholder="e.g. INV-2025-9901" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
        </div>
      </div>

      <!-- Itemization / Notes -->
      <div style="margin-bottom: 22px;">
        <label class="expenses-field-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Itemization &amp; Approval Notes</label>
        <textarea name="notes" rows="2" placeholder="Itemization, purpose, or departmental remarks..." class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
      </div>

      <!-- Footer Buttons -->
      <div class="expenses-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" onclick="closeAddExpenseModal();" class="roaster-btn secondary expenses-cancel-btn" style="padding: 9px 18px; border-radius: 8px; font-weight: 600;">Cancel</button>
        <button type="submit" class="roaster-add-btn expenses-submit-btn" style="padding: 9px 24px; border-radius: 8px; font-weight: 700;">
          <i class="fa-solid fa-check"></i> <span>Record Voucher</span>
        </button>
      </div>

    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: View Voucher Details
     ══════════════════════════════════════════════════════ -->
<div id="voucherModal" class="hrms-modal-overlay" onclick="if (event.target === this) closeVoucherModal();" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px); overflow-y: auto; padding: 20px;">
  <div class="hrms-modal-content voucher-modal-box" style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 500px; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); max-height: 92vh; overflow-y: auto;">
    
    <div class="voucher-modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 14px;">
      <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-receipt" style="color: #0092df;"></i>
        <span>Official Expense Voucher</span>
      </h3>
      <button type="button" onclick="closeVoucherModal();" class="voucher-modal-close" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">&times;</button>
    </div>

    <!-- Voucher Details Body -->
    <div id="voucherModalBody" style="font-size: 13px;">
      <!-- Populated via Javascript -->
    </div>

    <div class="voucher-modal-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 14px; border-top: 1px solid #f1f5f9;">
      <button type="button" onclick="window.print();" class="roaster-btn secondary voucher-print-btn" style="gap: 6px;">
        <i class="fa-solid fa-print"></i> <span>Print Voucher</span>
      </button>
      <button type="button" onclick="closeVoucherModal();" class="roaster-btn secondary voucher-close-btn" style="padding: 8px 18px; border-radius: 8px; font-weight: 600;">Close</button>
    </div>

  </div>
</div>

<script>
function openAddExpenseModal() {
  const modal = document.getElementById('addExpenseModal');
  if (modal) modal.style.display = 'flex';
}

function closeAddExpenseModal() {
  const modal = document.getElementById('addExpenseModal');
  if (modal) modal.style.display = 'none';
}

function openVoucherModal(exp) {
  const modal = document.getElementById('voucherModal');
  const body = document.getElementById('voucherModalBody');
  if (!modal || !body) return;

  const amtFormatted = '₹' + parseFloat(exp.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

  body.innerHTML = `
    <div class="voucher-receipt-card" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 16px;">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
        <div>
          <span class="voucher-meta-label" style="font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">VOUCHER REF</span>
          <div class="voucher-ref-code" style="font-family: monospace; font-size: 14px; font-weight: 800; color: #0284c7;">#EXP-${String(exp.id).padStart(4, '0')}</div>
        </div>
        <div style="text-align: right;">
          <span class="voucher-meta-label" style="font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">DATE</span>
          <div class="voucher-date-text" style="font-weight: 700; color: #0f172a;">${exp.expense_date}</div>
        </div>
      </div>

      <div style="margin-bottom: 12px;">
        <div class="voucher-title-text" style="font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">${exp.title}</div>
        <div class="voucher-cat-text" style="color: #64748b; font-size: 12px; margin-top: 3px;">Category: <strong>${exp.category_name}</strong></div>
      </div>

      <div class="voucher-amount-row" style="border-top: 1px dashed #cbd5e1; padding-top: 12px; display: flex; justify-content: space-between; align-items: center;">
        <span class="voucher-amount-label" style="font-weight: 800; color: #334155; font-size: 13px;">AMOUNT PAID:</span>
        <span class="voucher-amount-val" style="font-size: 1.35rem; font-weight: 900; color: #dc2626; font-family: 'Outfit', sans-serif;">${amtFormatted}</span>
      </div>
    </div>

    <div class="voucher-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
      <div class="voucher-subcard" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px;">
        <span class="voucher-subcard-label" style="font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">Vendor / Payee</span>
        <div class="voucher-subcard-val" style="font-weight: 700; color: #0f172a; margin-top: 2px;">${exp.vendor_name || 'Official Institution'}</div>
      </div>
      <div class="voucher-subcard" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px;">
        <span class="voucher-subcard-label" style="font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase;">Payment Mode</span>
        <div class="voucher-subcard-mode" style="font-weight: 700; color: #0284c7; margin-top: 2px;">${exp.payment_method}</div>
      </div>
    </div>

    ${exp.invoice_ref ? `
      <div class="voucher-invoice-box" style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 9px 12px; margin-bottom: 12px;">
        <span class="voucher-invoice-label" style="font-size: 10px; font-weight: 700; color: #0369a1; text-transform: uppercase;">Invoice / Bill Number:</span>
        <span class="voucher-invoice-ref" style="font-family: monospace; font-weight: 800; color: #0284c7; margin-left: 6px;">${exp.invoice_ref}</span>
      </div>
    ` : ''}

    ${exp.notes ? `
      <div class="voucher-notes-box" style="background: #f8fafc; border-left: 3px solid #0092df; border-radius: 0 6px 6px 0; padding: 8px 12px;">
        <span class="voucher-notes-label" style="font-size: 11px; font-weight: 700; color: #475569;">Remarks / Purpose:</span>
        <p class="voucher-notes-text" style="margin: 3px 0 0; color: #1e293b; font-size: 12px;">${exp.notes}</p>
      </div>
    ` : ''}
  `;

  modal.style.display = 'flex';
}

function closeVoucherModal() {
  const modal = document.getElementById('voucherModal');
  if (modal) modal.style.display = 'none';
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeAddExpenseModal();
    closeVoucherModal();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
