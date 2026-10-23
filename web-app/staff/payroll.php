<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
require_menu_permission('payroll');

$page_title = 'My Salary Slips & Payroll | KSV Academy HRMS';
$page_heading = 'Faculty Payroll & Compensation';
$page_subheading = 'Monthly Salary Slips, Allowances, Deductions & Official Printable Receipts';
$active_menu = 'payroll';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'] ?? 1;

// Resolve Logged-in Staff Profile Info
$stmt = $db->prepare("SELECT * FROM staff_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$current_user['id']]);
$staff_profile = $stmt->fetch();

if (!$staff_profile) {
    $emp_code = 'KSV-FAC-0' . $current_user['id'];
    $stmt = $db->prepare("INSERT INTO staff_profiles (user_id, employee_code, department, designation, joining_date, monthly_salary, today_status) VALUES (?, ?, 'Academics', 'Faculty Member', CURDATE(), 60000, 'Present')");
    $stmt->execute([$current_user['id'], $emp_code]);
    $staff_profile = $db->query("SELECT * FROM staff_profiles WHERE user_id = {$current_user['id']}")->fetch();
}
$staff_id = $staff_profile['id'];

// Handle Generate Payroll Entry (Admin Testing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin' && isset($_POST['action']) && $_POST['action'] === 'generate_payroll') {
    $target_staff_id = intval($_POST['staff_id'] ?? $staff_id);
    $month = trim($_POST['month'] ?? date('F'));
    $year = intval($_POST['year'] ?? date('Y'));
    $basic = floatval($_POST['basic_salary'] ?? 65000);
    $allowances = floatval($_POST['allowances'] ?? 3000);
    $deductions = floatval($_POST['deductions'] ?? 200);
    $net = $basic + $allowances - $deductions;

    if ($target_staff_id > 0) {
        $stmt = $db->prepare("INSERT INTO payroll (session_id, staff_id, month, year, basic_salary, allowances, deductions, net_salary, payment_status, payment_date, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Paid', CURDATE(), 'Bank Transfer (NEFT)')");
        $stmt->execute([$session_id, $target_staff_id, $month, $year, $basic, $allowances, $deductions, $net]);
        set_flash('success', "Salary record generated for {$month} {$year}! Net Disbursed: ₹" . number_format($net, 2));
        header('Location: ' . STAFF_URL . '/payroll');
        exit;
    }
}

// KPI Metrics for this Staff Member
$metric_stmt = $db->prepare("
    SELECT COUNT(*) as total_slips,
           COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN net_salary ELSE 0 END), 0) as total_disbursed,
           COALESCE(AVG(net_salary), 0) as avg_payout
    FROM payroll p
    JOIN staff_profiles sp ON p.staff_id = sp.id
    WHERE sp.user_id = ?
");
$metric_stmt->execute([$current_user['id']]);
$metric_data = $metric_stmt->fetch(PDO::FETCH_ASSOC);

$total_slips = intval($metric_data['total_slips'] ?? 0);
$total_disbursed = floatval($metric_data['total_disbursed'] ?? 0);
$avg_payout = floatval($metric_data['avg_payout'] ?? 0);
$monthly_base = floatval($staff_profile['monthly_salary'] ?? 60000);

// Filter Parameters
$search = trim($_GET['search'] ?? '');
$filter_month = trim($_GET['month'] ?? '');
$filter_year = trim($_GET['year'] ?? '');

// Pagination Settings (10, 20, 30, 40, 50 - plus 5 for convenience)
$allowed_limits = [5, 10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Fetch Filtered Payroll Records for this Staff
$where = ["sp.user_id = ?"];
$params = [$current_user['id']];

if (!empty($search)) {
    $where[] = "(p.month LIKE ? OR p.year LIKE ? OR p.payment_method LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (!empty($filter_month)) {
    $where[] = "p.month = ?";
    $params[] = $filter_month;
}
if (!empty($filter_year)) {
    $where[] = "p.year = ?";
    $params[] = $filter_year;
}

$where_sql = implode(' AND ', $where);

// Count total matching payroll records
$count_query = "
    SELECT COUNT(*) 
    FROM payroll p
    JOIN staff_profiles sp ON p.staff_id = sp.id
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

// Fetch paginated payroll records
$query = "
    SELECT p.*, u.full_name, sp.employee_code, sp.department, sp.designation
    FROM payroll p
    JOIN staff_profiles sp ON p.staff_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE {$where_sql}
    ORDER BY p.id DESC
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
$payroll_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for generating staff-payroll pagination links
function get_staff_payroll_pagination_url($target_page, $search, $filter_month, $filter_year, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_month)) $params['month'] = $filter_month;
    if (!empty($filter_year)) $params['year'] = $filter_year;
    if ($limit != 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return STAFF_URL . '/payroll' . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Available filter months and years
$months_list = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$years_list = [2027, 2026, 2025, 2024];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Staff Payroll Custom Styles (Simple, Sober & Premium) ── */
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

.roaster-toolbar-card .roaster-search-box {
  min-width: 170px;
  max-width: 210px;
}
.roaster-toolbar-card .roaster-select-box select {
  min-width: 130px;
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
  padding: 11px 9px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.staff-id-table tbody td {
  padding: 11px 9px;
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
</style>

<!-- 4 Sober Monthly KPI Summary Cards (4-Column Balanced Grid) -->
<div class="periods-stat-grid">

  <!-- Monthly Base Pay -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Monthly Base Salary</span>
          <span class="stat-number"><?php echo format_inr($monthly_base); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-wallet"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Approved Academic Scale</span>
      <span style="color: #0284c7; font-weight: 700;">Base Pay</span>
    </div>
  </div>

  <!-- Total Net Disbursed -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Net Disbursed</span>
          <span class="stat-number"><?php echo format_inr($total_disbursed); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-money-bill-transfer"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Direct Bank Credits</span>
      <span style="color: #059669; font-weight: 700;">Disbursed</span>
    </div>
  </div>

  <!-- Verified Salary Slips -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Verified Salary Slips</span>
          <span class="stat-number"><?php echo $total_slips; ?></span>
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
      <span>Digital Vouchers &amp; Slips</span>
      <span style="color: #d97706; font-weight: 700;">Archived</span>
    </div>
  </div>

  <!-- Average Compensation -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Average Net Payout</span>
          <span class="stat-number"><?php echo format_inr($avg_payout); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-chart-line"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Monthly Net Average</span>
      <span style="color: #7c3aed; font-weight: 700;">Payout</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     FILTER & SEARCH TOOLBAR (Simple & Sober)
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-top: 6px; margin-bottom: 20px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Search & Month/Year Filter Form -->
    <form method="GET" action="<?php echo STAFF_URL; ?>/payroll" class="roaster-filter-form">
      <?php if ($limit !== 10): ?>
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      <?php endif; ?>

      <!-- Search Input -->
      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search month, year..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_staff_payroll_pagination_url(1, '', $filter_month, $filter_year, $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <!-- Month Filter -->
      <div class="roaster-select-box">
        <select name="month" onchange="this.form.submit()">
          <option value="">All Months</option>
          <?php foreach ($months_list as $m): ?>
            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $filter_month === $m ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($m); ?>
            </option>
          <?php endforeach; ?>
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

      <?php if (!empty($search) || !empty($filter_month) || !empty($filter_year)): ?>
        <a href="<?php echo STAFF_URL; ?>/payroll<?php echo $limit !== 10 ? '?limit=' . $limit : ''; ?>" class="roaster-btn secondary" style="gap: 5px; font-size: 0.80rem; text-decoration: none; padding: 7px 12px; border-radius: 8px;">
          <i class="fa-solid fa-rotate-left"></i> Reset Filters
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Count Badge -->
    <div class="roaster-toolbar-actions" style="display: flex; align-items: center; gap: 10px;">
      <div class="scholars-count-badge">
        <span class="count-dot" style="background: #0284c7;"></span>
        <span><strong><?php echo $total_records; ?></strong> Salary Slips</span>
      </div>

      <?php if ($current_user['role'] === 'super_admin'): ?>
        <button type="button" class="btn-hrms btn-hrms-primary" onclick="openModal('generatePayrollModal')" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer;">
          <i class="fa-solid fa-calculator"></i> Disburse Salary
        </button>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     SALARY SLIPS DIRECTORY TABLE (Simple & Sober)
     ══════════════════════════════════════════════════════ -->
<div class="periods-card no-print" id="staffPayrollDirectoryCard">
  <div class="periods-card-header">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 0.96rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-file-invoice-dollar" style="color: #0284c7;"></i>
        My Salary Slips &amp; Payroll Ledger
        <span style="font-size: 0.72rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Monthly compensation statements, allowances, statutory deductions and verified credit receipts for <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong> (<?php echo htmlspecialchars($staff_profile['employee_code']); ?>).
      </p>
    </div>
    <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
      <span>Total Records: <strong style="color: #0f172a;"><?php echo $total_records; ?></strong></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="hrms-table staff-id-table">
      <thead>
        <tr>
          <th style="width: 36px; text-align: center;">#</th>
          <th style="width: 135px;">Pay Period</th>
          <th style="width: 95px;">Basic Pay</th>
          <th style="width: 85px;">Allowances</th>
          <th style="width: 85px;">Deductions</th>
          <th style="width: 105px;">Net Credited</th>
          <th style="min-width: 135px;">Payment Details</th>
          <th style="width: 75px; text-align: center;">Status</th>
          <th style="width: 105px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payroll_list)): ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-receipt"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Salary Slips Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                <?php echo (!empty($search) || !empty($filter_month) || !empty($filter_year)) ? 'No payroll records match your search filters.' : 'No salary slips have been disbursed for your account yet.'; ?>
              </p>
              <?php if (!empty($search) || !empty($filter_month) || !empty($filter_year)): ?>
                <a href="<?php echo STAFF_URL; ?>/payroll" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none; padding: 7px 14px; border-radius: 8px;">
                  <i class="fa-solid fa-rotate-left"></i> Reset All Filters
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($payroll_list as $idx => $pl): ?>
            <tr>
              
              <!-- 1. Index -->
              <td style="text-align: center; font-weight: 600; color: #94a3b8; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.78rem;">
                <?php echo sprintf('%02d', $start_record + $idx); ?>
              </td>

              <!-- 2. Pay Period -->
              <td>
                <div style="font-weight: 700; color: #0f172a; font-size: 0.86rem; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 6px;">
                  <i class="fa-regular fa-calendar-check" style="color: #0284c7; font-size: 0.72rem;"></i>
                  <span><?php echo htmlspecialchars($pl['month']); ?> <?php echo $pl['year']; ?></span>
                </div>
                <div style="font-size: 0.71rem; color: #64748b; margin-top: 2px;">Monthly Pay Cycle</div>
              </td>

              <!-- 3. Basic Pay -->
              <td>
                <span style="font-weight: 600; color: #334155; font-size: 0.82rem;">
                  <?php echo format_inr($pl['basic_salary']); ?>
                </span>
              </td>

              <!-- 4. Allowances -->
              <td>
                <span style="font-weight: 600; color: #059669; font-size: 0.80rem;">
                  +<?php echo format_inr($pl['allowances']); ?>
                </span>
              </td>

              <!-- 5. Deductions -->
              <td>
                <span style="font-weight: 600; color: #dc2626; font-size: 0.80rem;">
                  -<?php echo format_inr($pl['deductions']); ?>
                </span>
              </td>

              <!-- 6. Net Credited -->
              <td>
                <span style="font-weight: 800; color: #0284c7; font-size: 0.90rem; font-family: 'Outfit', sans-serif;">
                  <?php echo format_inr($pl['net_salary']); ?>
                </span>
              </td>

              <!-- 7. Payment Details -->
              <td>
                <div style="font-weight: 600; color: #0f172a; font-size: 0.80rem; display: flex; align-items: center; gap: 5px;">
                  <i class="fa-regular fa-clock" style="color: #64748b; font-size: 0.68rem;"></i>
                  <span><?php echo format_date($pl['payment_date']); ?></span>
                </div>
                <div style="font-size: 0.70rem; color: #64748b; margin-top: 1px;">
                  <?php echo htmlspecialchars($pl['payment_method'] ?: 'Bank Transfer (NEFT)'); ?>
                </div>
              </td>

              <!-- 8. Status -->
              <td style="text-align: center;">
                <span class="sid-status-verified">
                  <i class="fa-solid fa-check" style="font-size: 0.58rem;"></i> <?php echo htmlspecialchars($pl['payment_status']); ?>
                </span>
              </td>

              <!-- 9. Payslip View Receipt Action -->
              <td style="text-align: right;">
                <button type="button" class="roaster-btn secondary" onclick="previewPayslip(<?php echo htmlspecialchars(json_encode($pl), ENT_QUOTES, 'UTF-8'); ?>);" style="padding: 4px 10px; font-size: 0.74rem; border-radius: 6px; gap: 5px; cursor: pointer; display: inline-flex; align-items: center;" title="View Official Salary Receipt">
                  <i class="fa-solid fa-receipt" style="color: #0284c7;"></i> View Receipt
                </button>
              </td>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Table Footer Pagination & Per-Page Controls (Standard Portal Pagination) -->
  <div class="pagination-controls-wrap">
    
    <!-- Left: Showing summary & Limit Selector -->
    <div class="pagination-meta-group">
      <span style="font-size: 0.82rem; color: #475569;">
        <?php if ($total_records > 0): ?>
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> salary records
        <?php else: ?>
          Showing <strong>0</strong> salary records
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_staff_payroll_pagination_url(1, $search, $filter_month, $filter_year, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Staff Payroll Ledger Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_staff_payroll_pagination_url($page - 1, $search, $filter_month, $filter_year, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_staff_payroll_pagination_url($p, $search, $filter_month, $filter_year, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_staff_payroll_pagination_url($page + 1, $search, $filter_month, $filter_year, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Legend / Financial Stats -->
    <div style="font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 14px; margin-left: auto;">
      <span>Total Disbursed: <strong><?php echo format_inr($total_disbursed); ?></strong></span>
      <span style="color: #cbd5e1;">•</span>
      <span>Base Pay: <strong><?php echo format_inr($monthly_base); ?></strong></span>
      <span style="color: #cbd5e1;">•</span>
      <span>Avg Payout: <strong><?php echo format_inr($avg_payout); ?></strong></span>
    </div>
  </div>

</div>

<!-- Modal: Disburse Monthly Salary (For Super Admin Testing) -->
<?php if ($current_user['role'] === 'super_admin'): ?>
<?php 
  $staff_options = $db->query("SELECT sp.id, u.full_name, sp.employee_code, sp.monthly_salary FROM staff_profiles sp JOIN users u ON sp.user_id = u.id ORDER BY u.full_name ASC")->fetchAll();
?>
<div class="hrms-modal" id="generatePayrollModal">
  <div class="hrms-modal-card" style="max-width: 500px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 16px 20px;">
      <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-money-check-dollar" style="color: #0284c7;"></i>
        Disburse Faculty Salary
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('generatePayrollModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo STAFF_URL; ?>/payroll">
      <input type="hidden" name="action" value="generate_payroll">
      <div class="hrms-modal-body" style="padding: 20px;">
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Select Staff Member</label>
          <select name="staff_id" class="hrms-input" required style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem; background: #fff;">
            <?php foreach ($staff_options as $so): ?>
              <option value="<?php echo $so['id']; ?>" <?php echo $so['id'] == $staff_id ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($so['full_name']); ?> (<?php echo $so['employee_code']; ?>) &bull; Base: ₹<?php echo number_format($so['monthly_salary'], 0); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Month</label>
            <select name="month" class="hrms-input" style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem; background: #fff;">
              <?php foreach ($months_list as $m): ?>
                <option value="<?php echo $m; ?>" <?php echo $m === date('F') ? 'selected' : ''; ?>><?php echo $m; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Year</label>
            <input type="number" name="year" class="hrms-input" value="<?php echo date('Y'); ?>" style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem;">
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Basic Salary (₹)</label>
            <input type="number" name="basic_salary" class="hrms-input" value="65000" required style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem;">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Allowances (₹)</label>
            <input type="number" name="allowances" class="hrms-input" value="3000" style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem;">
          </div>
        </div>
        <div class="form-group" style="margin-bottom: 20px;">
          <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Deductions / PF / Tax (₹)</label>
          <input type="number" name="deductions" class="hrms-input" value="200" style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem;">
        </div>
        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
          <button type="button" class="roaster-btn secondary" onclick="closeModal('generatePayrollModal')" style="padding: 9px 16px; border-radius: 8px; font-size: 0.84rem; cursor: pointer;">Cancel</button>
          <button type="submit" class="btn-hrms btn-hrms-primary" style="padding: 9px 20px; border-radius: 8px; font-size: 0.84rem; font-weight: 700; cursor: pointer;">
            <i class="fa-solid fa-check"></i> Disburse Salary
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/payslip-modal.php'; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>

