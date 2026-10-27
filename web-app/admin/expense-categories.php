<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
if ($current_user['role'] === 'staff') {
    require_menu_permission('admin_expense_categories');
}
$page_title = 'Expense Categories & Heads | KSV Academy HRMS';
$page_heading = 'Expense Categories';
$page_subheading = 'Classify School Expenditures, Accounting Heads & Budget Classifications';
$active_menu = 'expense-categories';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add Category
    if ($action === 'add_category') {
        $cat_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!empty($cat_name)) {
            try {
                // Check if already exists
                $check = $db->prepare("SELECT id FROM expense_categories WHERE LOWER(category_name) = LOWER(?)");
                $check->execute([$cat_name]);
                if ($check->fetch()) {
                    set_flash('error', "Category '{$cat_name}' already exists.");
                } else {
                    $stmt = $db->prepare("INSERT INTO expense_categories (category_name, description) VALUES (?, ?)");
                    $stmt->execute([$cat_name, $description]);
                    set_flash('success', "Expense category '{$cat_name}' created successfully!");
                }
            } catch (Exception $e) {
                set_flash('error', 'Error adding category: ' . $e->getMessage());
            }
        } else {
            set_flash('error', 'Please provide a valid category name.');
        }
        header('Location: ' . ADMIN_URL . '/expense-categories');
        exit;
    }

    // Edit Category
    if ($action === 'edit_category') {
        $cat_id = intval($_POST['category_id'] ?? 0);
        $cat_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($cat_id > 0 && !empty($cat_name)) {
            try {
                // Check if name is taken by another category
                $check = $db->prepare("SELECT id FROM expense_categories WHERE LOWER(category_name) = LOWER(?) AND id != ?");
                $check->execute([$cat_name, $cat_id]);
                if ($check->fetch()) {
                    set_flash('error', "Another category with the name '{$cat_name}' already exists.");
                } else {
                    $stmt = $db->prepare("UPDATE expense_categories SET category_name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$cat_name, $description, $cat_id]);
                    set_flash('success', "Category updated successfully!");
                }
            } catch (Exception $e) {
                set_flash('error', 'Error updating category: ' . $e->getMessage());
            }
        }
        header('Location: ' . ADMIN_URL . '/expense-categories');
        exit;
    }

    // Delete Category
    if ($action === 'delete_category') {
        $cat_id = intval($_POST['category_id'] ?? 0);
        if ($cat_id > 0) {
            $stmt_he = $db->prepare("SELECT COUNT(*) FROM expenses WHERE category_id = ?");
            $stmt_he->execute([$cat_id]);
            $has_exp = $stmt_he->fetchColumn();
            if ($has_exp > 0) {
                set_flash('error', "Cannot delete category: {$has_exp} expense entries are attached to it.");
            } else {
                $db->prepare("DELETE FROM expense_categories WHERE id = ?")->execute([$cat_id]);
                set_flash('success', "Expense category removed successfully.");
            }
        }
        header('Location: ' . ADMIN_URL . '/expense-categories');
        exit;
    }
}

// Compute overall stats across all categories for active session (Prepared Statement)
$stmt_all_cat = $db->prepare("
    SELECT c.id, c.category_name,
        (SELECT COUNT(*) FROM expenses WHERE category_id = c.id AND session_id = ?) as expense_count,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category_id = c.id AND session_id = ?) as total_spent
    FROM expense_categories c
    ORDER BY c.category_name ASC
");
$stmt_all_cat->execute([$session_id, $session_id]);
$all_categories = $stmt_all_cat->fetchAll();

$total_categories_count = count($all_categories);
$active_in_use_count = 0;
$session_total_spent = 0;
$top_category_name = '—';
$top_category_amount = 0;

foreach ($all_categories as $c_item) {
    $spent = floatval($c_item['total_spent']);
    $session_total_spent += $spent;
    if ($c_item['expense_count'] > 0) {
        $active_in_use_count++;
    }
    if ($spent > $top_category_amount) {
        $top_category_amount = $spent;
        $top_category_name = $c_item['category_name'];
    }
}

// Search & Pagination Setup (10, 20, 30, 40, 50)
$search = trim($_GET['search'] ?? '');
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

$where_clauses = [];
$where_params = [];
if (!empty($search)) {
    $where_clauses[] = "(LOWER(c.category_name) LIKE ? OR LOWER(c.description) LIKE ?)";
    $where_params[] = '%' . strtolower($search) . '%';
    $where_params[] = '%' . strtolower($search) . '%';
}
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Count matching categories
$count_stmt = $db->prepare("SELECT COUNT(*) FROM expense_categories c {$where_sql}");
$count_stmt->execute($where_params);
$total_matching_categories = (int)$count_stmt->fetchColumn();

$total_pages = max(1, ceil($total_matching_categories / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_matching_categories > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_matching_categories);

// Fetch paginated categories with session totals
$cat_query = "
    SELECT c.*,
        (SELECT COUNT(*) FROM expenses WHERE category_id = c.id AND session_id = {$session_id}) as expense_count,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category_id = c.id AND session_id = {$session_id}) as total_spent,
        (SELECT COUNT(*) FROM expenses WHERE category_id = c.id) as lifetime_expense_count
    FROM expense_categories c
    {$where_sql}
    ORDER BY c.category_name ASC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($cat_query);
$param_idx = 1;
foreach ($where_params as $val) {
    $stmt->bindValue($param_idx++, $val);
}
$stmt->bindValue($param_idx++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($param_idx++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$categories = $stmt->fetchAll();

// Helper to preserve active query parameters in pagination links
function get_categories_pagination_url($target_page, $search, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if ($limit !== 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    $query_str = !empty($params) ? '?' . http_build_query($params) : '';
    return ADMIN_URL . '/expense-categories' . $query_str;
}

// Helper to pick category icon and color
function get_cat_icon_info($name) {
    $n = strtolower($name);
    if (strpos($n, 'elect') !== false || strpos($n, 'power') !== false || strpos($n, 'util') !== false) {
        return ['icon' => 'fa-bolt', 'bg' => '#fef3c7', 'color' => '#b45309', 'border' => '#fde68a'];
    }
    if (strpos($n, 'station') !== false || strpos($n, 'print') !== false || strpos($n, 'paper') !== false) {
        return ['icon' => 'fa-book-open', 'bg' => '#e0f2fe', 'color' => '#0369a1', 'border' => '#bae6fd'];
    }
    if (strpos($n, 'lab') !== false || strpos($n, 'scien') !== false || strpos($n, 'chem') !== false) {
        return ['icon' => 'fa-flask-vial', 'bg' => '#f3e8ff', 'color' => '#7e22ce', 'border' => '#e9d5ff'];
    }
    if (strpos($n, 'it') !== false || strpos($n, 'soft') !== false || strpos($n, 'comput') !== false || strpos($n, 'tech') !== false) {
        return ['icon' => 'fa-laptop-code', 'bg' => '#ecfdf5', 'color' => '#047857', 'border' => '#a7f3d0'];
    }
    if (strpos($n, 'maint') !== false || strpos($n, 'build') !== false || strpos($n, 'repair') !== false) {
        return ['icon' => 'fa-screwdriver-wrench', 'bg' => '#fff7ed', 'color' => '#c2410c', 'border' => '#fed7aa'];
    }
    if (strpos($n, 'lib') !== false || strpos($n, 'book') !== false) {
        return ['icon' => 'fa-book-bookmark', 'bg' => '#fef2f2', 'color' => '#b91c1c', 'border' => '#fecaca'];
    }
    if (strpos($n, 'sport') !== false || strpos($n, 'game') !== false) {
        return ['icon' => 'fa-volleyball', 'bg' => '#ecfeff', 'color' => '#0e7490', 'border' => '#a5f3fc'];
    }
    if (strpos($n, 'welfare') !== false || strpos($n, 'staff') !== false || strpos($n, 'event') !== false) {
        return ['icon' => 'fa-hands-holding-child', 'bg' => '#fdf2f8', 'color' => '#be185d', 'border' => '#fbcfe8'];
    }
    return ['icon' => 'fa-tags', 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0'];
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
.cat-icon-badge {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.cat-code-pill {
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

.cat-vouchers-pill {
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

.cat-progress-track {
  width: 100%;
  height: 5px;
  background: #f1f5f9;
  border-radius: 999px;
  overflow: hidden;
  margin-top: 5px;
}
.cat-progress-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #0092df, #0284c7);
  transition: width 0.4s ease;
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
.sid-action-btn.edit {
  color: #4f46e5;
  border-color: #c7d2fe;
  background: #eef2ff;
}
.sid-action-btn.edit:hover {
  background: #4f46e5;
  color: #ffffff;
  border-color: #4f46e5;
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
.sid-action-btn.locked {
  color: #94a3b8;
  border-color: #e2e8f0;
  background: #f8fafc;
  cursor: not-allowed;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS FOR EXPENSE CATEGORIES
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  /* Top Stat KPI Grid */
  .stat-grid.exp-cat-stat-grid,
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

  /* Toolbar & Search Card */
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
  .exp-cat-filter-form {
    flex-direction: column !important;
    align-items: stretch !important;
    width: 100% !important;
    gap: 8px !important;
  }
  .exp-cat-search-box {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    height: 40px !important;
    padding: 8px 12px !important;
    border-radius: 9px !important;
  }
  .exp-cat-toolbar-actions {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8px !important;
    width: 100% !important;
    margin: 2px 0 0 0 !important;
  }
  .exp-cat-records-badge {
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
    font-size: 0.78rem !important;
    padding: 0 10px !important;
    box-sizing: border-box !important;
    border-radius: 9px !important;
  }
  .exp-cat-add-btn {
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

  /* Directory Card & Header */
  .exp-cat-directory-card {
    border-radius: 12px !important;
    margin-bottom: 20px !important;
  }
  .exp-cat-card-header {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 10px !important;
  }
  .exp-cat-title-block {
    width: 100% !important;
  }
  .exp-cat-card-title {
    font-size: 0.95rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
  .exp-cat-subtitle {
    font-size: 0.74rem !important;
    line-height: 1.4 !important;
  }
  .exp-cat-header-meta {
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
  .exp-cat-table {
    min-width: 780px !important;
  }
  .exp-cat-table thead th {
    padding: 9px 8px !important;
    font-size: 0.68rem !important;
  }
  .exp-cat-table tbody td {
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

  /* Modals Responsiveness */
  #addCategoryModal,
  #editCategoryModal {
    padding: 12px !important;
  }
  .exp-cat-modal-box {
    padding: 16px 14px !important;
    border-radius: 12px !important;
    max-height: 94vh !important;
    width: 100% !important;
  }
  .exp-cat-modal-header h3 {
    font-size: 1.02rem !important;
  }
  .exp-cat-modal-footer {
    flex-direction: column-reverse !important;
    gap: 8px !important;
    width: 100% !important;
  }
  .exp-cat-modal-footer button {
    width: 100% !important;
    height: 40px !important;
    justify-content: center !important;
  }
}

/* Micro Devices (<= 360px) */
@media (max-width: 360px) {
  .stat-grid.exp-cat-stat-grid,
  .stat-grid {
    grid-template-columns: 1fr !important;
  }
  .exp-cat-toolbar-actions {
    grid-template-columns: 1fr !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR EXPENSE CATEGORIES
   ══════════════════════════════════════════════════════════════════════ */
[data-theme="dark"] .stat-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .stat-card-footer {
  border-top-color: #334155 !important;
}

[data-theme="dark"] .roaster-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .exp-cat-search-box {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .exp-cat-search-box input {
  color: #f8fafc !important;
}
[data-theme="dark"] .exp-cat-records-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .exp-cat-directory-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .exp-cat-card-header {
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .exp-cat-card-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .exp-cat-session-badge {
  background: #0f172a !important;
  color: #cbd5e1 !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .exp-cat-subtitle {
  color: #94a3b8 !important;
}
[data-theme="dark"] .exp-cat-header-meta {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .exp-cat-spent-total {
  color: #f87171 !important;
}
[data-theme="dark"] .exp-cat-heads-total {
  color: #f8fafc !important;
}
[data-theme="dark"] .exp-cat-meta-divider {
  color: #334155 !important;
}

[data-theme="dark"] .exp-cat-table thead th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .exp-cat-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .exp-cat-table tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.02) !important;
}
[data-theme="dark"] .cat-head-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .cat-lifetime-count {
  color: #94a3b8 !important;
}
[data-theme="dark"] .cat-scope-desc {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .cat-code-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .cat-vouchers-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .cat-vouchers-zero {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .cat-progress-track {
  background: #0f172a !important;
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
[data-theme="dark"] .sid-action-btn.edit {
  background: rgba(79, 70, 229, 0.15) !important;
  border-color: rgba(79, 70, 229, 0.3) !important;
  color: #a5b4fc !important;
}
[data-theme="dark"] .sid-action-btn.edit:hover {
  background: #4f46e5 !important;
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
[data-theme="dark"] .sid-action-btn.locked {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #64748b !important;
}

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
}
[data-theme="dark"] .pagination-btn.active {
  background: var(--hrms-primary, #0092df) !important;
  border-color: var(--hrms-primary, #0092df) !important;
  color: #ffffff !important;
}

[data-theme="dark"] .exp-cat-modal-box {
  background: #1e293b !important;
  color: #f8fafc !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .exp-cat-modal-header {
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .exp-cat-modal-header h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] .exp-cat-modal-sub {
  color: #94a3b8 !important;
}
[data-theme="dark"] .exp-cat-modal-close {
  color: #94a3b8 !important;
}
[data-theme="dark"] .exp-cat-field-label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .exp-cat-modal-box .hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .exp-cat-modal-footer {
  border-top-color: #334155 !important;
}
</style>

<!-- ══════════════════════════════════════════════════════
     TOP KPI STAT METRICS GRID
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid exp-cat-stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
  
  <!-- Total Categories -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Expense Head Master</span>
          <span class="stat-number"><?php echo $total_categories_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-layer-group"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-tags" style="color: #0284c7; margin-right: 4px;"></i> Registered Heads</span>
      <span style="color: #0284c7; font-weight: 700;">Campus Accounting</span>
    </div>
  </div>

  <!-- Active In Use -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Active Heads in Use</span>
          <span class="stat-number"><?php echo $active_in_use_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-check-double"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $total_categories_count > 0 ? round(($active_in_use_count / $total_categories_count) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-receipt" style="color: #059669; margin-right: 4px;"></i> Active Vouchers</span>
      <span style="color: #059669; font-weight: 700;"><?php echo $total_categories_count - $active_in_use_count; ?> unused heads</span>
    </div>
  </div>

  <!-- Total Expenditure -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Session Expenditure</span>
          <span class="stat-number" style="color: #dc2626;"><?php echo format_inr($session_total_spent); ?></span>
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
      <span><i class="fa-solid fa-arrow-trend-down" style="color: #dc2626; margin-right: 4px;"></i> Head Disbursements</span>
      <span style="color: #dc2626; font-weight: 700;">Top: <?php echo htmlspecialchars($top_category_name); ?></span>
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
      <span style="color: #d97706; font-weight: 700;">Financial Fiscal Year</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     FILTER & SEARCH ROASTER TOOLBAR
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Search Box -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/expense-categories" class="roaster-filter-form exp-cat-filter-form" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      <div class="roaster-search-box exp-cat-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" id="catSearchInput" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search category name, scope, keywords..." autocomplete="off" oninput="filterCategories()">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_categories_pagination_url(1, '', $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Right: Count Badge & Add Category Button -->
    <div class="roaster-toolbar-actions exp-cat-toolbar-actions">
      <div class="scholars-count-badge exp-cat-records-badge">
        <span class="count-dot" style="background: #0092df;"></span>
        <span><strong id="visibleCatCount"><?php echo $total_matching_categories; ?></strong> <?php echo $total_matching_categories === 1 ? 'Category' : 'Categories'; ?></span>
      </div>

      <button type="button" onclick="openAddCatModal();" class="roaster-add-btn exp-cat-add-btn" style="padding: 7px 16px; font-size: 0.82rem;" title="Add Expense Category Head">
        <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-plus"></i></div>
        <span>Add Category</span>
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     CATEGORIES LEDGER TABLE
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card exp-cat-directory-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
  <div class="hrms-card-header exp-cat-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div class="exp-cat-title-block">
      <h3 class="card-title exp-cat-card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-sitemap" style="color: #0284c7;"></i>
        <span>Expense Heads &amp; Accounting Classifications</span>
        <span class="exp-cat-session-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p class="exp-cat-subtitle" style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Official cost-center chart of accounts for auditing institutional procurement, utilities, maintenance, and student welfare.
      </p>
    </div>

    <div class="exp-cat-header-meta" style="font-size: 0.78rem; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 12px;">
      <span>Total Spent: <strong class="exp-cat-spent-total" style="color: #dc2626; font-size: 0.95rem; font-family: 'Outfit', sans-serif;"><?php echo format_inr($session_total_spent); ?></strong></span>
      <span class="exp-cat-meta-divider" style="color: #cbd5e1;">|</span>
      <span>Total Heads: <strong class="exp-cat-heads-total" style="color: #0f172a;"><?php echo $total_categories_count; ?></strong></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="hrms-table staff-id-table exp-cat-table" id="categoriesTable">
      <thead>
        <tr>
          <th style="min-width: 240px;">Category Head</th>
          <th style="min-width: 220px;">Budget Scope &amp; Purpose</th>
          <th style="min-width: 130px; text-align: center;">Vouchers (Session)</th>
          <th style="min-width: 170px;">Expenditure (₹)</th>
          <th style="min-width: 110px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($categories)): ?>
          <tr>
            <td colspan="5" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-folder-open"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Expense Categories Defined</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">Create your first expense head to begin tracking departmental expenditures.</p>
              <button type="button" onclick="openAddCatModal();" class="roaster-add-btn" style="display: inline-flex;">
                <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
                <span>Add First Category</span>
              </button>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($categories as $cat): ?>
            <?php 
              $style_info = get_cat_icon_info($cat['category_name']);
              $spent_amt = floatval($cat['total_spent']);
              $pct_spent = $session_total_spent > 0 ? round(($spent_amt / $session_total_spent) * 100, 1) : 0;
            ?>
            <tr class="category-row" data-name="<?php echo strtolower(htmlspecialchars($cat['category_name'])); ?>" data-desc="<?php echo strtolower(htmlspecialchars($cat['description'] ?? '')); ?>">
              
              <!-- Category Head with Icon -->
              <td>
                <div style="display: flex; align-items: center; gap: 12px;">
                  <div class="cat-icon-badge" style="background: <?php echo $style_info['bg']; ?>; color: <?php echo $style_info['color']; ?>; border: 1px solid <?php echo $style_info['border']; ?>;">
                    <i class="fa-solid <?php echo $style_info['icon']; ?>"></i>
                  </div>
                  <div>
                    <div class="cell-name cat-head-title" style="font-weight: 700; font-size: 0.90rem; color: #0f172a; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                      <?php echo htmlspecialchars($cat['category_name']); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                      <span class="cat-code-pill">HEAD-<?php echo str_pad($cat['id'], 2, '0', STR_PAD_LEFT); ?></span>
                      <?php if ($cat['lifetime_expense_count'] > 0): ?>
                        <span class="cat-lifetime-count" style="font-size: 0.72rem; color: #64748b;">(<?php echo $cat['lifetime_expense_count']; ?> all-time)</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Scope & Purpose -->
              <td>
                <?php if (!empty($cat['description'])): ?>
                  <div class="cell-sub cat-scope-desc" style="font-size: 0.82rem; color: #475569; line-height: 1.4;">
                    <?php echo htmlspecialchars($cat['description']); ?>
                  </div>
                <?php else: ?>
                  <span class="cat-scope-empty" style="font-size: 0.78rem; color: #94a3b8; font-style: italic;">No specific scope instructions registered</span>
                <?php endif; ?>
              </td>

              <!-- Vouchers in current session -->
              <td style="text-align: center;">
                <?php if ($cat['expense_count'] > 0): ?>
                  <span class="cat-vouchers-pill">
                    <i class="fa-solid fa-file-invoice" style="color: #0284c7; font-size: 0.68rem;"></i>
                    <span><?php echo $cat['expense_count']; ?> vouchers</span>
                  </span>
                <?php else: ?>
                  <span class="cat-vouchers-zero" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.74rem; color: #94a3b8; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 7px; border-radius: 4px;">
                    0 vouchers
                  </span>
                <?php endif; ?>
              </td>

              <!-- Expenditure & Percentage Bar -->
              <td>
                <div style="display: flex; justify-content: space-between; align-items: baseline;">
                  <strong class="cat-spent-text" style="color: <?php echo $spent_amt > 0 ? '#dc2626' : '#94a3b8'; ?>; font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                    <?php echo format_inr($spent_amt); ?>
                  </strong>
                  <?php if ($spent_amt > 0): ?>
                    <span class="cat-percent-badge" style="font-size: 0.72rem; font-weight: 700; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 1px 6px; border-radius: 4px;">
                      <?php echo $pct_spent; ?>%
                    </span>
                  <?php endif; ?>
                </div>
                <div class="cat-progress-track">
                  <div class="cat-progress-fill" style="width: <?php echo min(100, max($pct_spent > 0 ? 3 : 0, $pct_spent)); ?>%;"></div>
                </div>
              </td>

              <!-- Actions -->
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 5px; justify-content: flex-end; align-items: center;">
                  
                  <!-- View Ledger Link -->
                  <a href="<?php echo ADMIN_URL; ?>/expenses?category_id=<?php echo $cat['id']; ?>" class="sid-action-btn view" title="View all expenses under this head">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </a>

                  <!-- Edit Category Button -->
                  <button type="button" 
                          onclick='openEditCatModal(<?php echo json_encode($cat); ?>)' 
                          class="sid-action-btn edit" 
                          title="Edit Category Details">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>

                  <!-- Delete Category Button -->
                  <?php if ($cat['expense_count'] == 0 && $cat['lifetime_expense_count'] == 0): ?>
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/expense-categories" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to permanently delete \'<?php echo addslashes($cat['category_name']); ?>\'?');">
                      <input type="hidden" name="action" value="delete_category">
                      <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                      <button type="submit" class="sid-action-btn delete" title="Delete category head">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="sid-action-btn locked" title="Cannot delete: Active vouchers are bound to this head">
                      <i class="fa-solid fa-lock"></i>
                    </span>
                  <?php endif; ?>

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
        <?php if ($total_matching_categories > 0): ?>
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_matching_categories; ?></strong> <?php echo $total_matching_categories === 1 ? 'category' : 'categories'; ?>
        <?php else: ?>
          Showing <strong>0</strong> categories
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_categories_pagination_url(1, $search, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Categories Table Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_categories_pagination_url($page - 1, $search, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_categories_pagination_url($p, $search, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_categories_pagination_url($page + 1, $search, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: ADD EXPENSE CATEGORY
     ══════════════════════════════════════════════════════ -->
<div id="addCategoryModal" class="hrms-modal-overlay" style="display: none;">
  <div class="hrms-modal-content exp-cat-modal-box" style="max-width: 500px; padding: 26px;">
    
    <div class="exp-cat-modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 9px; background: rgba(0, 146, 223, 0.12); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-folder-plus"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Create Expense Category
          </h3>
          <span class="exp-cat-modal-sub" style="font-size: 0.76rem; color: #64748b;">Add a new institutional expenditure head</span>
        </div>
      </div>
      <button type="button" onclick="closeAddCatModal();" class="exp-cat-modal-close" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #94a3b8;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/expense-categories">
      <input type="hidden" name="action" value="add_category">

      <div class="form-group" style="margin-bottom: 16px;">
        <label class="exp-cat-field-label" style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: #1e293b;">
          Category Head Title <span style="color: #ef4444;">*</span>
        </label>
        <input type="text" name="category_name" class="hrms-input" required placeholder="e.g. Science Laboratory Consumables" style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.85rem;">
      </div>

      <div class="form-group" style="margin-bottom: 22px;">
        <label class="exp-cat-field-label" style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: #1e293b;">
          Budget Scope &amp; Accounting Notes
        </label>
        <textarea name="description" rows="3" class="hrms-input" placeholder="Specify permissible expenditures, vendor categories, or departmental allocation rules..." style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.85rem; font-family: inherit; resize: vertical;"></textarea>
      </div>

      <div class="exp-cat-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
        <button type="button" onclick="closeAddCatModal();" class="roaster-btn secondary" style="padding: 9px 18px;">
          Cancel
        </button>
        <button type="submit" class="roaster-add-btn" style="padding: 9px 20px;">
          <div class="btn-icon-circle"><i class="fa-solid fa-check"></i></div>
          <span>Save Category</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: EDIT EXPENSE CATEGORY
     ══════════════════════════════════════════════════════ -->
<div id="editCategoryModal" class="hrms-modal-overlay" style="display: none;">
  <div class="hrms-modal-content exp-cat-modal-box" style="max-width: 500px; padding: 26px;">
    
    <div class="exp-cat-modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 9px; background: rgba(124, 58, 237, 0.12); color: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-pen-to-square"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Edit Category Head
          </h3>
          <span class="exp-cat-modal-sub" style="font-size: 0.76rem; color: #64748b;">Modify accounting head details and descriptions</span>
        </div>
      </div>
      <button type="button" onclick="closeEditCatModal();" class="exp-cat-modal-close" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #94a3b8;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/expense-categories">
      <input type="hidden" name="action" value="edit_category">
      <input type="hidden" name="category_id" id="edit_category_id" value="">

      <div class="form-group" style="margin-bottom: 16px;">
        <label class="exp-cat-field-label" style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: #1e293b;">
          Category Head Title <span style="color: #ef4444;">*</span>
        </label>
        <input type="text" name="category_name" id="edit_category_name" class="hrms-input" required style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.85rem;">
      </div>

      <div class="form-group" style="margin-bottom: 22px;">
        <label class="exp-cat-field-label" style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: #1e293b;">
          Budget Scope &amp; Accounting Notes
        </label>
        <textarea name="description" id="edit_category_desc" rows="3" class="hrms-input" style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.85rem; font-family: inherit; resize: vertical;"></textarea>
      </div>

      <div class="exp-cat-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
        <button type="button" onclick="closeEditCatModal();" class="roaster-btn secondary" style="padding: 9px 18px;">
          Cancel
        </button>
        <button type="submit" class="roaster-add-btn" style="padding: 9px 20px;">
          <div class="btn-icon-circle"><i class="fa-solid fa-check"></i></div>
          <span>Update Head</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddCatModal() {
  document.getElementById('addCategoryModal').style.display = 'flex';
}
function closeAddCatModal() {
  document.getElementById('addCategoryModal').style.display = 'none';
}

function openEditCatModal(cat) {
  document.getElementById('edit_category_id').value = cat.id;
  document.getElementById('edit_category_name').value = cat.category_name;
  document.getElementById('edit_category_desc').value = cat.description || '';
  document.getElementById('editCategoryModal').style.display = 'flex';
}
function closeEditCatModal() {
  document.getElementById('editCategoryModal').style.display = 'none';
}

// Client-side real-time filter for currently visible rows
function filterCategories() {
  const query = document.getElementById('catSearchInput').value.toLowerCase().trim();
  const rows = document.querySelectorAll('.category-row');
  let visibleCount = 0;

  rows.forEach(row => {
    const name = row.getAttribute('data-name') || '';
    const desc = row.getAttribute('data-desc') || '';
    if (name.includes(query) || desc.includes(query)) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const countBadge = document.getElementById('visibleCatCount');
  if (countBadge) {
    countBadge.innerText = visibleCount;
  }
}

// Modal backdrop click close
window.addEventListener('click', function(e) {
  const addModal = document.getElementById('addCategoryModal');
  const editModal = document.getElementById('editCategoryModal');
  if (e.target === addModal) addModal.style.display = 'none';
  if (e.target === editModal) editModal.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
