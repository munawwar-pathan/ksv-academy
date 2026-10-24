<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
if ($current_user['role'] === 'staff') {
    require_menu_permission('admin_expense_report');
}
$page_title = 'Financial & Expense Audit Report | KSV Academy HRMS';
$page_heading = 'Expense Report';
$page_subheading = 'Category Breakdown, Revenue vs Expenditure Balance, and Monthly Audit Summary';
$active_menu = 'expense-report';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Summary calculations for the active session
$total_fees = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM fee_payments WHERE session_id = {$session_id}")->fetchColumn());
$total_expenses = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE session_id = {$session_id}")->fetchColumn());
$total_payroll = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM payroll WHERE session_id = {$session_id} AND payment_status = 'Paid'")->fetchColumn());

$net_operating_balance = $total_fees - ($total_expenses + $total_payroll);
$total_disbursed = $total_expenses + $total_payroll;

// Category-wise Breakdown
$cat_breakdown = $db->query("
    SELECT c.id, c.category_name, COALESCE(SUM(e.amount), 0) as total_amount, COUNT(e.id) as vouchers_count
    FROM expense_categories c
    LEFT JOIN expenses e ON c.id = e.category_id AND e.session_id = {$session_id}
    GROUP BY c.id, c.category_name
    HAVING total_amount > 0
    ORDER BY total_amount DESC
")->fetchAll();

// Monthly Breakdown
$monthly_expenses = $db->query("
    SELECT DATE_FORMAT(expense_date, '%M %Y') as month_label,
           DATE_FORMAT(expense_date, '%Y-%m') as month_key,
           COUNT(id) as voucher_count,
           SUM(amount) as monthly_total
    FROM expenses
    WHERE session_id = {$session_id}
    GROUP BY month_key, month_label
    ORDER BY month_key DESC
")->fetchAll();

// Payment Method Distribution
$method_breakdown = $db->query("
    SELECT payment_method,
           COUNT(id) as voucher_count,
           COALESCE(SUM(amount), 0) as total_amount
    FROM expenses
    WHERE session_id = {$session_id}
    GROUP BY payment_method
    ORDER BY total_amount DESC
")->fetchAll();

// Helper to pick category icon and color
function get_report_cat_icon($name) {
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
    if (strpos($n, 'it') !== false || strpos($n, 'soft') !== false || strpos($n, 'comput') !== false) {
        return ['icon' => 'fa-laptop-code', 'bg' => '#ecfdf5', 'color' => '#047857', 'border' => '#a7f3d0'];
    }
    if (strpos($n, 'maint') !== false || strpos($n, 'build') !== false || strpos($n, 'repair') !== false) {
        return ['icon' => 'fa-screwdriver-wrench', 'bg' => '#fff7ed', 'color' => '#c2410c', 'border' => '#fed7aa'];
    }
    return ['icon' => 'fa-tags', 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0'];
}

function get_method_icon($method) {
    switch (strtolower(trim($method))) {
        case 'cash':
            return ['icon' => 'fa-wallet', 'bg' => '#ecfdf5', 'color' => '#059669', 'border' => '#a7f3d0'];
        case 'upi':
            return ['icon' => 'fa-qrcode', 'bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe'];
        case 'bank transfer':
            return ['icon' => 'fa-building-columns', 'bg' => '#f5f3ff', 'color' => '#7c3aed', 'border' => '#ddd6fe'];
        case 'cheque':
            return ['icon' => 'fa-money-check', 'bg' => '#fffbeb', 'color' => '#d97706', 'border' => '#fde68a'];
        default:
            return ['icon' => 'fa-credit-card', 'bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#e2e8f0'];
    }
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Staff ID Style Table (Simple & Sober - Matching staff-id) ── */
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

/* ── Report Module Specific Styles ── */
.report-progress-bar {
  width: 100%;
  height: 6px;
  background: #f1f5f9;
  border-radius: 999px;
  overflow: hidden;
  margin-top: 6px;
}
.report-progress-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #0092df, #0284c7);
  transition: width 0.4s ease;
}
.report-cat-item {
  padding: 11px 13px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  transition: all 0.2s ease;
}
.report-cat-item:hover {
  background: #ffffff;
  border-color: #cbd5e1;
  box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}
.method-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.method-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 3px 8px rgba(0,0,0,0.04);
}
.method-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}

/* ── Print Styles ── */
@media print {
  @page {
    size: A4 portrait;
    margin: 15mm;
  }
  body {
    background: #ffffff !important;
    color: #000000 !important;
  }
  .no-print, header, aside, .sidebar, .hrms-sidebar, .hrms-header, .stat-grid, .roaster-toolbar-card {
    display: none !important;
  }
  .report-printable-area {
    display: block !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  .print-header-banner {
    display: block !important;
    margin-bottom: 24px;
    border-bottom: 2px solid #0f172a;
    padding-bottom: 16px;
  }
  .print-signatures {
    display: flex !important;
    justify-content: space-between;
    margin-top: 50px;
    padding-top: 20px;
  }
  .hrms-card {
    box-shadow: none !important;
    border: 1px solid #cbd5e1 !important;
    margin-bottom: 20px !important;
    break-inside: avoid;
  }
  .hrms-table th {
    background: #f1f5f9 !important;
    color: #0f172a !important;
  }
}
.print-header-banner, .print-signatures {
  display: none;
}
</style>

<!-- ══════════════════════════════════════════════════════
     TOP KPI STAT METRICS GRID
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid no-print" style="margin-bottom: 24px;">
  
  <!-- Total Revenue (Fee Collections) -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Fee Revenue (Collections)</span>
          <span class="stat-number" style="color: #059669;"><?php echo format_inr($total_fees); ?></span>
        </div>
        <div class="stat-icon-wrapper" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
          <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #10b981;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span style="color: #059669; font-weight: 700;"><i class="fa-solid fa-arrow-trend-up"></i> Inflow Verified</span>
      <span class="text-muted">Session <?php echo htmlspecialchars($active_session['session_name']); ?></span>
    </div>
  </div>

  <!-- Operational Expenses -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Operational Expenses</span>
          <span class="stat-number" style="color: #dc2626;"><?php echo format_inr($total_expenses); ?></span>
        </div>
        <div class="stat-icon-wrapper" style="background: rgba(239, 68, 68, 0.12); color: #dc2626;">
          <i class="fa-solid fa-receipt"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #dc2626;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span style="color: #dc2626; font-weight: 700;"><i class="fa-solid fa-file-invoice-dollar"></i> Disbursed Vouchers</span>
      <span class="text-muted">Bills, Labs &amp; Upkeep</span>
    </div>
  </div>

  <!-- Faculty & Staff Payroll -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Faculty &amp; Staff Payroll</span>
          <span class="stat-number" style="color: #7c3aed;"><?php echo format_inr($total_payroll); ?></span>
        </div>
        <div class="stat-icon-wrapper" style="background: rgba(124, 58, 237, 0.12); color: #7c3aed;">
          <i class="fa-solid fa-users-line"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #7c3aed;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span style="color: #7c3aed; font-weight: 700;"><i class="fa-solid fa-money-bill-wave"></i> Paid Staff Salaries</span>
      <span class="text-muted">Academic &amp; Admin</span>
    </div>
  </div>

  <!-- Net Operating Balance -->
  <div class="stat-card <?php echo $net_operating_balance >= 0 ? 'amber' : 'purple'; ?>">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Net Operating Balance</span>
          <span class="stat-number" style="color: <?php echo $net_operating_balance >= 0 ? '#007cbd' : '#dc2626'; ?>;">
            <?php echo format_inr($net_operating_balance); ?>
          </span>
        </div>
        <div class="stat-icon-wrapper" style="background: <?php echo $net_operating_balance >= 0 ? 'rgba(0, 146, 223, 0.12)' : 'rgba(239, 68, 68, 0.12)'; ?>; color: <?php echo $net_operating_balance >= 0 ? '#0092df' : '#dc2626'; ?>;">
          <i class="fa-solid fa-scale-balanced"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: <?php echo $net_operating_balance >= 0 ? '#0092df' : '#dc2626'; ?>;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span style="color: <?php echo $net_operating_balance >= 0 ? '#007cbd' : '#dc2626'; ?>; font-weight: 700;">
        <i class="fa-solid <?php echo $net_operating_balance >= 0 ? 'fa-shield-halved' : 'fa-circle-exclamation'; ?>"></i> 
        <?php echo $net_operating_balance >= 0 ? 'Surplus Reserve' : 'Deficit Alert'; ?>
      </span>
      <span class="text-muted">Revenue &minus; Outflow</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     ROASTER TOOLBAR CARD & NAVIGATION
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card exp-rep-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner exp-rep-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Subnavigation Links -->
    <div class="exp-rep-nav-links" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <a href="<?php echo ADMIN_URL; ?>/expenses" class="roaster-btn secondary exp-rep-nav-btn" style="text-decoration: none; font-size: 0.82rem; gap: 6px;">
        <i class="fa-solid fa-file-invoice-dollar" style="color: #0092df;"></i>
        <span>Expenses &amp; Vouchers</span>
      </a>

      <a href="<?php echo ADMIN_URL; ?>/expense-categories" class="roaster-btn secondary exp-rep-nav-btn" style="text-decoration: none; font-size: 0.82rem; gap: 6px;">
        <i class="fa-solid fa-sitemap" style="color: #059669;"></i>
        <span>Expense Heads</span>
      </a>

      <a href="<?php echo ADMIN_URL; ?>/fee-collection" class="roaster-btn secondary exp-rep-nav-btn" style="text-decoration: none; font-size: 0.82rem; gap: 6px;">
        <i class="fa-solid fa-hand-holding-dollar" style="color: #7c3aed;"></i>
        <span>Fee Collections</span>
      </a>
    </div>

    <!-- Right: Session Badge & Print Button -->
    <div class="roaster-toolbar-actions exp-rep-toolbar-actions">
      <div class="scholars-count-badge exp-rep-session-badge">
        <span class="count-dot" style="background: #10b981;"></span>
        <span>Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
      </div>

      <button type="button" onclick="window.print();" class="roaster-add-btn exp-rep-print-btn" style="padding: 7px 18px; font-size: 0.82rem;" title="Print Official Financial Audit Report">
        <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-print"></i></div>
        <span>Print Audit Report</span>
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     REPORT PRINTABLE / AUDIT CONTAINER
     ══════════════════════════════════════════════════════ -->
<div class="report-printable-area">
  
  <!-- Printable Official Header Banner -->
  <div class="print-header-banner">
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <?php echo htmlspecialchars($school_settings['school_name'] ?? 'KSV Academy'); ?>
        </h1>
        <p style="margin: 3px 0 0; font-size: 13px; color: #475569;">
          <?php echo htmlspecialchars($school_settings['address'] ?? 'Official Financial Audit & Expenditure Ledger Report'); ?>
        </p>
      </div>
      <div style="text-align: right;">
        <div style="font-size: 12px; font-weight: 700; color: #0284c7;">ACADEMIC AUDIT SESSION</div>
        <div style="font-size: 18px; font-weight: 800; color: #0f172a;"><?php echo htmlspecialchars($active_session['session_name']); ?></div>
        <div style="font-size: 11px; color: #64748b;">Generated: <?php echo date('d M Y, h:i A'); ?></div>
      </div>
    </div>
  </div>

  <!-- Primary 2-Column Analytics Section -->
  <div class="exp-rep-main-grid" style="display: grid; grid-template-columns: 1.25fr 1.75fr; gap: 24px; margin-bottom: 24px;">
    
    <!-- Left Column: Category-Wise Allocation -->
    <div class="hrms-card exp-rep-card" style="border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
      <div class="hrms-card-header exp-rep-card-header" style="border-bottom: 1px solid #e2e8f0; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
        <h4 class="exp-rep-card-title" style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px;">
          <i class="fa-solid fa-chart-pie" style="color: #0284c7;"></i>
          <span>Category-Wise Allocation</span>
        </h4>
        <span class="exp-rep-header-badge" style="font-size: 0.74rem; font-weight: 600; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          <?php echo count($cat_breakdown); ?> Heads Active
        </span>
      </div>

      <div class="exp-rep-card-body" style="padding: 18px;">
        <?php if (empty($cat_breakdown)): ?>
          <div style="text-align: center; padding: 36px 12px; color: #94a3b8;">
            <i class="fa-solid fa-folder-open" style="font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 8px;"></i>
            <span style="font-size: 0.85rem;">No categorized expenses recorded in this session.</span>
          </div>
        <?php else: ?>
          <div class="exp-rep-cat-list" style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($cat_breakdown as $cb): ?>
              <?php 
                $pct = $total_expenses > 0 ? round(($cb['total_amount'] / $total_expenses) * 100, 1) : 0;
                $cat_style = get_report_cat_icon($cb['category_name']);
              ?>
              <div class="report-cat-item exp-rep-cat-item">
                <div class="exp-rep-cat-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                  <div class="exp-rep-cat-left" style="display: flex; align-items: center; gap: 9px; min-width: 0;">
                    <div class="exp-rep-cat-icon" style="width: 28px; height: 28px; border-radius: 7px; background: <?php echo $cat_style['bg']; ?>; color: <?php echo $cat_style['color']; ?>; border: 1px solid <?php echo $cat_style['border']; ?>; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0;">
                      <i class="fa-solid <?php echo $cat_style['icon']; ?>"></i>
                    </div>
                    <div style="min-width: 0;">
                      <span class="exp-rep-cat-name" style="font-weight: 700; font-size: 0.88rem; color: #0f172a; font-family: 'Outfit', sans-serif;">
                        <?php echo htmlspecialchars($cb['category_name']); ?>
                      </span>
                      <span class="exp-rep-cat-sub" style="font-size: 0.72rem; color: #64748b; margin-left: 6px;">
                        &bull; <?php echo $cb['vouchers_count']; ?> vouchers
                      </span>
                    </div>
                  </div>
                  <div class="exp-rep-cat-right" style="text-align: right; flex-shrink: 0;">
                    <strong class="exp-rep-cat-amount" style="color: #dc2626; font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                      <?php echo format_inr($cb['total_amount']); ?>
                    </strong>
                    <span class="exp-rep-cat-pct" style="display: inline-block; font-size: 0.72rem; font-weight: 700; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 1px 6px; border-radius: 4px; margin-left: 4px;">
                      <?php echo $pct; ?>%
                    </span>
                  </div>
                </div>

                <!-- Progress Bar -->
                <div class="report-progress-bar exp-rep-progress-track">
                  <div class="report-progress-fill" style="width: <?php echo min(100, max(2, $pct)); ?>%;"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Column: Monthly Expenditure Trend -->
    <div class="hrms-card exp-rep-card" style="border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
      <div class="hrms-card-header exp-rep-card-header" style="border-bottom: 1px solid #e2e8f0; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
        <h4 class="exp-rep-card-title" style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px;">
          <i class="fa-solid fa-calendar-days" style="color: #0284c7;"></i>
          <span>Monthly Expenditure Trend</span>
        </h4>
        <span class="exp-rep-header-badge" style="font-size: 0.74rem; font-weight: 600; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          <?php echo count($monthly_expenses); ?> Months Recorded
        </span>
      </div>

      <div class="table-responsive exp-rep-table-wrap">
        <table class="hrms-table staff-id-table exp-rep-table">
          <thead>
            <tr>
              <th style="min-width: 130px;">Month &amp; Year</th>
              <th style="min-width: 90px; text-align: center;">Vouchers</th>
              <th style="min-width: 120px;">Total Disbursed (₹)</th>
              <th style="min-width: 100px;">Avg / Voucher</th>
              <th style="min-width: 70px; text-align: right;">Share</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($monthly_expenses)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 40px 20px;">
                  <i class="fa-regular fa-calendar-xmark" style="font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 8px;"></i>
                  <span>No monthly expenditure vouchers recorded yet.</span>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($monthly_expenses as $me): ?>
                <?php 
                  $m_pct = $total_expenses > 0 ? round(($me['monthly_total'] / $total_expenses) * 100, 1) : 0;
                ?>
                <tr>
                  <td>
                    <div class="exp-rep-month-cell" style="font-weight: 700; font-size: 0.86rem; color: #0f172a; display: flex; align-items: center; gap: 7px;">
                      <i class="fa-regular fa-calendar" style="color: #0284c7; font-size: 0.72rem;"></i>
                      <span class="exp-rep-month-label"><?php echo htmlspecialchars($me['month_label']); ?></span>
                    </div>
                  </td>
                  <td style="text-align: center;">
                    <span class="exp-rep-vouchers-pill" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 600; font-size: 0.74rem; background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 5px;">
                      <?php echo $me['voucher_count']; ?> vouchers
                    </span>
                  </td>
                  <td>
                    <strong class="exp-rep-month-amount" style="color: #dc2626; font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                      <?php echo format_inr($me['monthly_total']); ?>
                    </strong>
                  </td>
                  <td class="exp-rep-avg-amount" style="font-size: 0.82rem; color: #475569; font-weight: 500;">
                    <?php echo format_inr($me['monthly_total'] / max(1, $me['voucher_count'])); ?>
                  </td>
                  <td style="text-align: right;">
                    <span class="exp-rep-share-badge" style="font-size: 0.74rem; font-weight: 700; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2px 7px; border-radius: 4px;">
                      <?php echo $m_pct; ?>%
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Bottom Analytics Row: Payment Channel Distribution & Executive Audit Matrix -->
  <div class="exp-rep-bottom-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-bottom: 30px;">
    
    <!-- Payment Methods Breakdown -->
    <div class="hrms-card exp-rep-card" style="border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
      <div class="hrms-card-header exp-rep-card-header" style="border-bottom: 1px solid #e2e8f0; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
        <h4 class="exp-rep-card-title" style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px;">
          <i class="fa-solid fa-credit-card" style="color: #0284c7;"></i>
          <span>Disbursement Channels &amp; Modes</span>
        </h4>
        <span class="exp-rep-header-badge" style="font-size: 0.74rem; font-weight: 600; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Voucher Settlement
        </span>
      </div>

      <div class="exp-rep-methods-grid" style="padding: 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <?php if (empty($method_breakdown)): ?>
          <p style="color: #94a3b8; font-size: 0.82rem; grid-column: 1 / -1; text-align: center; padding: 20px 0;">No disbursement mode data.</p>
        <?php else: ?>
          <?php foreach ($method_breakdown as $mb): ?>
            <?php 
              $m_info = get_method_icon($mb['payment_method']);
              $mb_pct = $total_expenses > 0 ? round(($mb['total_amount'] / $total_expenses) * 100, 1) : 0;
            ?>
            <div class="method-card exp-rep-method-card">
              <div class="method-icon-box exp-rep-method-icon" style="background: <?php echo $m_info['bg']; ?>; color: <?php echo $m_info['color']; ?>; border: 1px solid <?php echo $m_info['border']; ?>;">
                <i class="fa-solid <?php echo $m_info['icon']; ?>"></i>
              </div>
              <div style="flex: 1; min-width: 0;">
                <div style="display: flex; justify-content: space-between; align-items: baseline;">
                  <span class="exp-rep-method-name" style="font-size: 0.84rem; font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($mb['payment_method']); ?></span>
                  <span class="exp-rep-method-pct" style="font-size: 0.72rem; font-weight: 700; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 1px 5px; border-radius: 4px;"><?php echo $mb_pct; ?>%</span>
                </div>
                <div class="exp-rep-method-amount" style="font-size: 0.90rem; font-weight: 700; color: #dc2626; font-family: 'Outfit', sans-serif; margin-top: 2px;">
                  <?php echo format_inr($mb['total_amount']); ?>
                </div>
                <div class="exp-rep-method-sub" style="font-size: 0.72rem; color: #64748b; margin-top: 2px;">
                  <?php echo $mb['voucher_count']; ?> vouchers settled
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Executive Audit Summary Matrix -->
    <div class="hrms-card exp-rep-card" style="border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
      <div class="hrms-card-header exp-rep-card-header" style="border-bottom: 1px solid #e2e8f0; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
        <h4 class="exp-rep-card-title" style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px;">
          <i class="fa-solid fa-file-contract" style="color: #0284c7;"></i>
          <span>Institutional Ledger Reconcilement</span>
        </h4>
        <span class="exp-rep-header-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </div>

      <div class="exp-rep-ledger-wrap" style="padding: 16px;">
        <table class="hrms-table staff-id-table exp-rep-ledger-table" style="width: 100%;">
          <tbody>
            <tr class="exp-rep-row-inflow">
              <td class="exp-rep-ledger-cell-title" style="color: #475569; font-size: 0.84rem;">
                <span style="display: inline-flex; align-items: center; gap: 8px;">
                  <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 0.75rem;"></i>
                  <span>Total Fee Realization (Inflow)</span>
                </span>
              </td>
              <td class="exp-rep-ledger-cell-val" style="text-align: right; font-weight: 700; color: #059669; font-family: 'Outfit', sans-serif; font-size: 0.88rem;">
                + <?php echo format_inr($total_fees); ?>
              </td>
            </tr>

            <tr class="exp-rep-row-expense">
              <td class="exp-rep-ledger-cell-title" style="color: #475569; font-size: 0.84rem;">
                <span style="display: inline-flex; align-items: center; gap: 8px;">
                  <i class="fa-solid fa-circle-minus" style="color: #ef4444; font-size: 0.75rem;"></i>
                  <span>Operational &amp; Maintenance Expenses</span>
                </span>
              </td>
              <td class="exp-rep-ledger-cell-val" style="text-align: right; font-weight: 700; color: #dc2626; font-family: 'Outfit', sans-serif; font-size: 0.88rem;">
                &minus; <?php echo format_inr($total_expenses); ?>
              </td>
            </tr>

            <tr class="exp-rep-row-payroll">
              <td class="exp-rep-ledger-cell-title" style="color: #475569; font-size: 0.84rem;">
                <span style="display: inline-flex; align-items: center; gap: 8px;">
                  <i class="fa-solid fa-circle-minus" style="color: #7c3aed; font-size: 0.75rem;"></i>
                  <span>Staff &amp; Faculty Net Salaries Disbursed</span>
                </span>
              </td>
              <td class="exp-rep-ledger-cell-val" style="text-align: right; font-weight: 700; color: #7c3aed; font-family: 'Outfit', sans-serif; font-size: 0.88rem;">
                &minus; <?php echo format_inr($total_payroll); ?>
              </td>
            </tr>

            <tr class="exp-rep-row-outflow" style="background: #f8fafc;">
              <td class="exp-rep-ledger-cell-title" style="font-weight: 600; color: #1e293b; font-size: 0.84rem;">
                Total Institutional Outflow
              </td>
              <td class="exp-rep-ledger-cell-val" style="text-align: right; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif; font-size: 0.88rem;">
                <?php echo format_inr($total_disbursed); ?>
              </td>
            </tr>

            <tr class="exp-rep-row-balance <?php echo $net_operating_balance >= 0 ? 'is-surplus' : 'is-deficit'; ?>" style="background: <?php echo $net_operating_balance >= 0 ? '#f0fdf4' : '#fef2f2'; ?>;">
              <td class="exp-rep-ledger-cell-title" style="font-weight: 700; font-size: 0.88rem; color: #0f172a; font-family: 'Outfit', sans-serif;">
                Net Operating Balance (Reserves)
              </td>
              <td class="exp-rep-ledger-cell-val" style="text-align: right; font-weight: 800; font-size: 0.98rem; color: <?php echo $net_operating_balance >= 0 ? '#166534' : '#dc2626'; ?>; font-family: 'Outfit', sans-serif;">
                <?php echo format_inr($net_operating_balance); ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Printable Signatures / Audit Certification Section -->
  <div class="print-signatures">
    <div style="text-align: center; width: 180px;">
      <div style="border-bottom: 1px dashed #000000; height: 40px; margin-bottom: 8px;"></div>
      <div style="font-weight: 700; font-size: 12px;">Prepared By</div>
      <div style="font-size: 11px; color: #475569;">Senior Accountant</div>
    </div>

    <div style="text-align: center; width: 180px;">
      <div style="border-bottom: 1px dashed #000000; height: 40px; margin-bottom: 8px;"></div>
      <div style="font-weight: 700; font-size: 12px;">Verified &amp; Audited</div>
      <div style="font-size: 11px; color: #475569;">Bursar / Accounts Officer</div>
    </div>

    <div style="text-align: center; width: 180px;">
      <div style="border-bottom: 1px dashed #000000; height: 40px; margin-bottom: 8px;"></div>
      <div style="font-weight: 700; font-size: 12px;">Authorized Approval</div>
      <div style="font-size: 11px; color: #475569;">Director / Principal</div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
