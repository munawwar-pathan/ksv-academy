<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin']);
$page_title = 'Expense Categories & Heads | KSV Academy HRMS';
$page_heading = 'Expense Categories & Heads';
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
            $has_exp = $db->query("SELECT COUNT(*) FROM expenses WHERE category_id = {$cat_id}")->fetchColumn();
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

// Compute overall stats across all categories for active session
$all_categories = $db->query("
    SELECT c.id, c.category_name,
        (SELECT COUNT(*) FROM expenses WHERE category_id = c.id AND session_id = {$session_id}) as expense_count,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category_id = c.id AND session_id = {$session_id}) as total_spent
    FROM expense_categories c
    ORDER BY c.category_name ASC
")->fetchAll();

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
/* ── Category Module Specific Styling ── */
.cat-icon-badge {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
  transition: transform 0.2s ease;
}
.hrms-table tbody tr:hover .cat-icon-badge {
  transform: scale(1.08);
}
.cat-tag-pill {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.72rem;
  font-weight: 700;
  color: #64748b;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  padding: 1px 6px;
  border-radius: 4px;
}
.cat-progress-track {
  width: 100%;
  height: 6px;
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
.action-btn-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 6px 11px;
  border-radius: 7px;
  font-size: 0.78rem;
  font-weight: 700;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
}
.action-btn-sm.view {
  background: rgba(0, 146, 223, 0.09);
  color: #007cbd;
  border: 1px solid rgba(0, 146, 223, 0.2);
}
.action-btn-sm.view:hover {
  background: #0092df;
  color: #ffffff;
}
.action-btn-sm.edit {
  background: #f8fafc;
  color: #475569;
  border: 1px solid #cbd5e1;
}
.action-btn-sm.edit:hover {
  background: #0f172a;
  color: #ffffff;
  border-color: #0f172a;
}
.action-btn-sm.delete {
  background: rgba(239, 68, 68, 0.08);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.2);
}
.action-btn-sm.delete:hover {
  background: #dc2626;
  color: #ffffff;
}
.action-btn-sm.locked {
  background: #f8fafc;
  color: #94a3b8;
  border: 1px solid #e2e8f0;
  cursor: not-allowed;
}
</style>

<!-- ══════════════════════════════════════════════════════
     TOP KPI STAT METRICS GRID
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
  
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
    <form method="GET" action="<?php echo ADMIN_URL; ?>/expense-categories" class="roaster-filter-form" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" id="catSearchInput" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search category name, scope, keywords..." autocomplete="off" oninput="filterCategories()">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_categories_pagination_url(1, '', $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Right: Count Badge & Add Category Button -->
    <div class="roaster-toolbar-actions">
      <div class="scholars-count-badge">
        <span class="count-dot" style="background: #0092df;"></span>
        <span><strong id="visibleCatCount"><?php echo $total_matching_categories; ?></strong> <?php echo $total_matching_categories === 1 ? 'Category' : 'Categories'; ?></span>
      </div>

      <button type="button" onclick="openAddCatModal();" class="roaster-add-btn" style="padding: 7px 16px; font-size: 0.82rem;" title="Add Expense Category Head">
        <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-plus"></i></div>
        <span>Add Category</span>
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     CATEGORIES LEDGER TABLE
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card" style="margin-bottom: 30px;">
  <div class="hrms-card-header" style="border-bottom: 1px solid var(--hrms-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 22px;">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-sitemap" style="color: var(--hrms-primary);"></i>
        Expense Heads &amp; Accounting Classifications
        <span style="font-size: 0.76rem; font-weight: 700; background: #f1f5f9; color: #475569; padding: 2px 10px; border-radius: 20px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 4px 0 0; font-size: 0.8rem; color: #64748b;">
        Official cost-center chart of accounts for auditing institutional procurement, utilities, maintenance, and student welfare.
      </p>
    </div>

    <div style="font-size: 0.78rem; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 12px;">
      <span>Total Spent: <strong style="color: #dc2626; font-size: 0.95rem; font-family: 'Outfit', sans-serif;"><?php echo format_inr($session_total_spent); ?></strong></span>
      <span style="color: #cbd5e1;">|</span>
      <span>Total Heads: <strong style="color: #0f172a;"><?php echo $total_categories_count; ?></strong></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="hrms-table" id="categoriesTable">
      <thead>
        <tr>
          <th style="width: 300px;">Category Head</th>
          <th>Budget Scope &amp; Purpose</th>
          <th style="width: 140px; text-align: center;">Vouchers (Session)</th>
          <th style="width: 200px;">Expenditure (₹)</th>
          <th style="width: 140px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($categories)): ?>
          <tr>
            <td colspan="5" style="text-align: center; padding: 48px 20px; color: #94a3b8;">
              <i class="fa-solid fa-folder-open" style="font-size: 2.4rem; color: #cbd5e1; display: block; margin-bottom: 12px;"></i>
              <strong style="color: #475569; font-size: 0.95rem;">No Expense Categories Defined</strong>
              <p style="margin: 4px 0 16px; font-size: 0.82rem;">Create your first expense head to begin tracking departmental expenditures.</p>
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
                    <div style="font-weight: 800; font-size: 0.88rem; color: #0f172a; font-family: 'Outfit', sans-serif;">
                      <?php echo htmlspecialchars($cat['category_name']); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                      <span class="cat-tag-pill">EXP-HEAD-<?php echo str_pad($cat['id'], 2, '0', STR_PAD_LEFT); ?></span>
                      <?php if ($cat['lifetime_expense_count'] > 0): ?>
                        <span style="font-size: 0.72rem; color: #64748b;">(<?php echo $cat['lifetime_expense_count']; ?> all-time)</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Scope & Purpose -->
              <td>
                <?php if (!empty($cat['description'])): ?>
                  <span style="font-size: 0.82rem; color: #475569; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                    <?php echo htmlspecialchars($cat['description']); ?>
                  </span>
                <?php else: ?>
                  <span style="font-size: 0.78rem; color: #94a3b8; font-style: italic;">No specific scope instructions registered</span>
                <?php endif; ?>
              </td>

              <!-- Vouchers in current session -->
              <td style="text-align: center;">
                <?php if ($cat['expense_count'] > 0): ?>
                  <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 700; font-size: 0.76rem; background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 20px; border: 1px solid #bae6fd;">
                    <i class="fa-solid fa-file-invoice"></i> <?php echo $cat['expense_count']; ?> vouchers
                  </span>
                <?php else: ?>
                  <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.74rem; color: #94a3b8; background: #f8fafc; padding: 3px 8px; border-radius: 6px;">
                    0 vouchers
                  </span>
                <?php endif; ?>
              </td>

              <!-- Expenditure & Percentage Bar -->
              <td>
                <div style="display: flex; justify-content: space-between; align-items: baseline;">
                  <strong style="color: <?php echo $spent_amt > 0 ? '#dc2626' : '#94a3b8'; ?>; font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                    <?php echo format_inr($spent_amt); ?>
                  </strong>
                  <?php if ($spent_amt > 0): ?>
                    <span style="font-size: 0.72rem; font-weight: 800; color: #0284c7; background: #f0f9ff; padding: 1px 6px; border-radius: 4px;">
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
                <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                  
                  <!-- View Ledger Link -->
                  <a href="<?php echo ADMIN_URL; ?>/expenses?category_id=<?php echo $cat['id']; ?>" class="action-btn-sm view" title="View all expenses under this head">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span>Ledger</span>
                  </a>

                  <!-- Edit Category Button -->
                  <button type="button" 
                          onclick='openEditCatModal(<?php echo json_encode($cat); ?>)' 
                          class="action-btn-sm edit" 
                          title="Edit Category Details">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>

                  <!-- Delete Category Button -->
                  <?php if ($cat['expense_count'] == 0 && $cat['lifetime_expense_count'] == 0): ?>
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/expense-categories" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete \'<?php echo addslashes($cat['category_name']); ?>\'?');">
                      <input type="hidden" name="action" value="delete_category">
                      <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                      <button type="submit" class="action-btn-sm delete" title="Delete category head">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="action-btn-sm locked" title="Cannot delete: Active vouchers are bound to this head">
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
  <div class="hrms-modal-content" style="max-width: 500px; padding: 26px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 9px; background: rgba(0, 146, 223, 0.12); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-folder-plus"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Create Expense Category
          </h3>
          <span style="font-size: 0.76rem; color: #64748b;">Add a new institutional expenditure head</span>
        </div>
      </div>
      <button type="button" onclick="closeAddCatModal();" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #94a3b8;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/expense-categories">
      <input type="hidden" name="action" value="add_category">

      <div class="form-group" style="margin-bottom: 16px;">
        <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: #1e293b;">
          Category Head Title <span style="color: #ef4444;">*</span>
        </label>
        <input type="text" name="category_name" class="hrms-input" required placeholder="e.g. Science Laboratory Consumables" style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.85rem;">
      </div>

      <div class="form-group" style="margin-bottom: 22px;">
        <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: #1e293b;">
          Budget Scope &amp; Accounting Notes
        </label>
        <textarea name="description" rows="3" class="hrms-input" placeholder="Specify permissible expenditures, vendor categories, or departmental allocation rules..." style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.85rem; font-family: inherit; resize: vertical;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
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
  <div class="hrms-modal-content" style="max-width: 500px; padding: 26px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 9px; background: rgba(124, 58, 237, 0.12); color: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-pen-to-square"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Edit Category Head
          </h3>
          <span style="font-size: 0.76rem; color: #64748b;">Modify accounting head details and descriptions</span>
        </div>
      </div>
      <button type="button" onclick="closeEditCatModal();" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #94a3b8;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/expense-categories">
      <input type="hidden" name="action" value="edit_category">
      <input type="hidden" name="category_id" id="edit_category_id" value="">

      <div class="form-group" style="margin-bottom: 16px;">
        <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: #1e293b;">
          Category Head Title <span style="color: #ef4444;">*</span>
        </label>
        <input type="text" name="category_name" id="edit_category_name" class="hrms-input" required style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.85rem;">
      </div>

      <div class="form-group" style="margin-bottom: 22px;">
        <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: #1e293b;">
          Budget Scope &amp; Accounting Notes
        </label>
        <textarea name="description" id="edit_category_desc" rows="3" class="hrms-input" style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.85rem; font-family: inherit; resize: vertical;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
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
