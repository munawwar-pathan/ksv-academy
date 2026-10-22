<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
require_menu_permission('payroll');
$page_title = 'Payroll & Salary Management';
$page_heading = 'Faculty Payroll & Compensation';
$page_subheading = 'Monthly Salary Slips, Allowances & Direct Credits';
$active_menu = 'payroll';

$db = get_db_connection();

// Handle Generate Payroll Entry (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $staff_id = intval($_POST['staff_id'] ?? 0);
    $month = trim($_POST['month'] ?? date('F'));
    $year = intval($_POST['year'] ?? date('Y'));
    $basic = floatval($_POST['basic_salary'] ?? 55000);
    $allowances = floatval($_POST['allowances'] ?? 3000);
    $deductions = floatval($_POST['deductions'] ?? 200);
    $net = $basic + $allowances - $deductions;

    if ($staff_id > 0) {
        $stmt = $db->prepare("INSERT INTO payroll (staff_id, month, year, basic_salary, allowances, deductions, net_salary, payment_status, payment_date, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, 'Paid', CURDATE(), 'Bank Transfer (NEFT)')");
        $stmt->execute([$staff_id, $month, $year, $basic, $allowances, $deductions, $net]);
        set_flash('success', "Payroll generated for {$month} {$year}! Net Disbursed: ₹" . number_format($net, 2));
        header('Location: ' . STAFF_URL . '/payroll');
        exit;
    }
}

// Fetch Payroll Data
if ($current_user['role'] === 'super_admin') {
    $payroll_list = $db->query("
        SELECT p.*, u.full_name, sp.employee_code, sp.department, sp.designation
        FROM payroll p
        JOIN staff_profiles sp ON p.staff_id = sp.id
        JOIN users u ON sp.user_id = u.id
        ORDER BY p.id DESC
    ")->fetchAll();
    $staff_options = $db->query("SELECT sp.id, u.full_name, sp.employee_code, sp.monthly_salary FROM staff_profiles sp JOIN users u ON sp.user_id = u.id")->fetchAll();
} else {
    // Staff view their own
    $stmt = $db->prepare("
        SELECT p.*, u.full_name, sp.employee_code, sp.department, sp.designation
        FROM payroll p
        JOIN staff_profiles sp ON p.staff_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE u.id = ?
        ORDER BY p.id DESC
    ");
    $stmt->execute([$current_user['id']]);
    $payroll_list = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
  <div>
    <span style="font-size: 0.9rem; color: var(--hrms-text-muted); font-weight: 600;">
      Salary Records Count: <strong><?php echo count($payroll_list); ?></strong>
    </span>
  </div>
  <?php if ($current_user['role'] === 'super_admin'): ?>
    <button type="button" class="btn-hrms btn-hrms-primary" onclick="openModal('generatePayrollModal')">
      <i class="fa-solid fa-calculator"></i> Disburse Monthly Salary
    </button>
  <?php endif; ?>
</div>

<div class="hrms-card">
  <div class="hrms-card-header">
    <h2 class="card-title"><i class="fa-solid fa-file-invoice-dollar"></i> Faculty Salary Disbursements</h2>
  </div>
  <div class="table-responsive">
    <table class="hrms-table">
      <thead>
        <tr>
          <th>Period</th>
          <th>Faculty Member</th>
          <th>Basic Pay</th>
          <th>Allowances</th>
          <th>Deductions</th>
          <th>Net Credited</th>
          <th>Disbursed Date</th>
          <th>Status</th>
          <th>Payslip</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payroll_list as $pl): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($pl['month']); ?> <?php echo $pl['year']; ?></strong></td>
            <td>
              <strong><?php echo htmlspecialchars($pl['full_name']); ?></strong><br>
              <small style="color: var(--hrms-text-muted);"><?php echo htmlspecialchars($pl['employee_code']); ?> &bull; <?php echo htmlspecialchars($pl['department']); ?></small>
            </td>
            <td><?php echo format_inr($pl['basic_salary']); ?></td>
            <td><small style="color: var(--hrms-success); font-weight: 600;">+<?php echo format_inr($pl['allowances']); ?></small></td>
            <td><small style="color: var(--hrms-danger); font-weight: 600;">-<?php echo format_inr($pl['deductions']); ?></small></td>
            <td><strong style="color: var(--hrms-navy); font-size: 0.95rem;"><?php echo format_inr($pl['net_salary']); ?></strong></td>
            <td><small><?php echo format_date($pl['payment_date']); ?></small></td>
            <td><span class="badge-status badge-paid"><i class="fa-solid fa-check"></i> <?php echo $pl['payment_status']; ?></span></td>
            <td>
              <button type="button" class="btn-hrms btn-hrms-outline btn-hrms-sm" onclick="alert('KSV ACADEMY OFFICIAL PAYSLIP\n\nEmployee: <?php echo $pl['full_name']; ?> (<?php echo $pl['employee_code']; ?>)\nPeriod: <?php echo $pl['month']; ?> <?php echo $pl['year']; ?>\nBasic Salary: ₹<?php echo number_format($pl['basic_salary'], 2); ?>\nPerformance Allowances: ₹<?php echo number_format($pl['allowances'], 2); ?>\nProfessional Tax / Deductions: ₹<?php echo number_format($pl['deductions'], 2); ?>\n--------------------------\nNET SALARY: ₹<?php echo number_format($pl['net_salary'], 2); ?>\nPayment Mode: Direct Bank Credit')">
                <i class="fa-solid fa-download"></i> Payslip
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Disburse Monthly Salary -->
<?php if ($current_user['role'] === 'super_admin'): ?>
<div class="hrms-modal" id="generatePayrollModal">
  <div class="hrms-modal-card">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--hrms-navy);"><i class="fa-solid fa-money-check-dollar" style="color: var(--hrms-primary);"></i> Disburse Monthly Salary</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('generatePayrollModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo STAFF_URL; ?>/payroll">
      <div class="hrms-modal-body">
        <div class="form-group">
          <label class="form-label">Select Staff Member</label>
          <select name="staff_id" class="hrms-input" required>
            <?php foreach ($staff_options as $so): ?>
              <option value="<?php echo $so['id']; ?>"><?php echo htmlspecialchars($so['full_name']); ?> (<?php echo $so['employee_code']; ?>) — Base: ₹<?php echo number_format($so['monthly_salary'], 0); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Month</label>
            <select name="month" class="hrms-input">
              <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
                <option value="<?php echo $m; ?>" <?php echo $m === date('F') ? 'selected' : ''; ?>><?php echo $m; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Year</label>
            <input type="number" name="year" class="hrms-input" value="<?php echo date('Y'); ?>">
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Basic Salary (₹)</label>
            <input type="number" name="basic_salary" class="hrms-input" value="65000" required>
          </div>
          <div class="form-group">
            <label class="form-label">Bonus / Allowances (₹)</label>
            <input type="number" name="allowances" class="hrms-input" value="3000">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Deductions / PF / Tax (₹)</label>
          <input type="number" name="deductions" class="hrms-input" value="200">
        </div>
        <button type="submit" class="login-submit-btn">
          <i class="fa-solid fa-check"></i> Process &amp; Generate Payslip
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
