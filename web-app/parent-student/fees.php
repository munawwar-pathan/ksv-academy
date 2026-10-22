<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'parent', 'student']);
$page_title = 'Fee Management & Receipts';
$page_heading = 'Tuition Fee Collection & Invoicing';
$page_subheading = 'Payment History, Outstanding Dues & Official Receipts';
$active_menu = 'fees';

$db = get_db_connection();

// Handle Collect Fee Payment (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
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
        header('Location: ' . PARENT_URL . '/fees');
        exit;
    }
}

// Fetch totals
$fee_stats = $db->query("SELECT SUM(total_fees) as total, SUM(paid_fees) as paid FROM students")->fetch();
$total_fees = floatval($fee_stats['total'] ?? 0);
$paid_fees = floatval($fee_stats['paid'] ?? 0);
$pending_fees = max(0, $total_fees - $paid_fees);

// Fetch fee payments list
if ($current_user['role'] === 'super_admin') {
    $payments = $db->query("
        SELECT fp.*, s.student_name, s.roll_no, s.standard, s.parent_name, s.parent_phone
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        ORDER BY fp.id DESC
    ")->fetchAll();
    $student_options = $db->query("SELECT id, student_name, roll_no, standard, (total_fees - paid_fees) as due FROM students WHERE (total_fees - paid_fees) > 0")->fetchAll();
} else {
    // For parent or student
    $active_sid = intval($_SESSION['active_student_id'] ?? 0);
    $stmt = $db->prepare("
        SELECT fp.*, s.student_name, s.roll_no, s.standard, s.parent_name, s.parent_phone
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        WHERE s.id = ? OR s.user_id = ? OR s.parent_user_id = ? OR s.student_name LIKE ?
        ORDER BY fp.id DESC
    ");
    $stmt->execute([$active_sid, $current_user['id'], $current_user['id'], '%' . $current_user['full_name'] . '%']);
    $payments = $stmt->fetchAll();
    if (empty($payments)) {
        // Fallback to active student or first student
        $fallback_id = $active_sid > 0 ? $active_sid : 1;
        $payments = $db->query("SELECT fp.*, s.student_name, s.roll_no, s.standard, s.parent_name, s.parent_phone FROM fee_payments fp JOIN students s ON fp.student_id = s.id WHERE s.id = {$fallback_id} ORDER BY fp.id DESC")->fetchAll();
    }
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<!-- KPI Cards -->
<div class="stat-grid">
  
  <div class="stat-card blue">
    <div class="stat-info">
      <span class="stat-label">Total Fee Value</span>
      <span class="stat-number"><?php echo format_inr($total_fees); ?></span>
      <span class="stat-subtext">Session 2026-27</span>
    </div>
    <div class="stat-icon-wrapper">
      <i class="fa-solid fa-graduation-cap"></i>
    </div>
  </div>

  <div class="stat-card green">
    <div class="stat-info">
      <span class="stat-label">Total Collected</span>
      <span class="stat-number"><?php echo format_inr($paid_fees); ?></span>
      <span class="stat-subtext"><?php echo round(($paid_fees / ($total_fees ?: 1)) * 100, 1); ?>% Realized</span>
    </div>
    <div class="stat-icon-wrapper">
      <i class="fa-solid fa-circle-check"></i>
    </div>
  </div>

  <div class="stat-card amber">
    <div class="stat-info">
      <span class="stat-label">Outstanding Dues</span>
      <span class="stat-number"><?php echo format_inr($pending_fees); ?></span>
      <span class="stat-subtext">Due in Installments</span>
    </div>
    <div class="stat-icon-wrapper">
      <i class="fa-solid fa-hourglass-half"></i>
    </div>
  </div>

</div>

<!-- Collect Fee Button for Super Admin -->
<?php if ($current_user['role'] === 'super_admin'): ?>
  <div style="display: flex; justify-content: flex-end; margin-bottom: 24px;">
    <button type="button" class="btn-hrms btn-hrms-primary" onclick="openModal('collectFeeModal')">
      <i class="fa-solid fa-receipt"></i> Record Fee Payment
    </button>
  </div>
<?php endif; ?>

<div class="hrms-card">
  <div class="hrms-card-header">
    <h2 class="card-title"><i class="fa-solid fa-file-invoice"></i> Fee Payment Receipts</h2>
  </div>
  <div class="table-responsive">
    <table class="hrms-table">
      <thead>
        <tr>
          <th>Receipt No</th>
          <th>Student</th>
          <th>Class &amp; Batch</th>
          <th>Amount Paid</th>
          <th>Mode of Payment</th>
          <th>Date</th>
          <th>Remarks</th>
          <th>Download</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><code><?php echo htmlspecialchars($p['receipt_no']); ?></code></td>
            <td>
              <strong><?php echo htmlspecialchars($p['student_name']); ?></strong><br>
              <small style="color: var(--hrms-text-muted);">Roll: <?php echo htmlspecialchars($p['roll_no']); ?></small>
            </td>
            <td><?php echo htmlspecialchars($p['standard']); ?></td>
            <td><strong style="color: var(--hrms-success);"><?php echo format_inr($p['amount']); ?></strong></td>
            <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
            <td><?php echo format_date($p['payment_date'], 'd M Y'); ?></td>
            <td><small><?php echo htmlspecialchars($p['remarks']); ?></small></td>
            <td>
              <button type="button" class="btn-hrms btn-hrms-outline btn-hrms-sm" onclick="alert('KSV Academy Official Receipt\nReceipt: <?php echo $p['receipt_no']; ?>\nStudent: <?php echo $p['student_name']; ?>\nAmount: ₹<?php echo $p['amount']; ?>\nMethod: <?php echo $p['payment_method']; ?>\nDate: <?php echo $p['payment_date']; ?>\nVerified: KSV Accounts Office')">
                <i class="fa-solid fa-download"></i> Slip
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Collect Fee Payment -->
<?php if ($current_user['role'] === 'super_admin'): ?>
<div class="hrms-modal" id="collectFeeModal">
  <div class="hrms-modal-card">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--hrms-navy);"><i class="fa-solid fa-receipt" style="color: var(--hrms-primary);"></i> Record Fee Payment</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('collectFeeModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo PARENT_URL; ?>/fees">
      <div class="hrms-modal-body">
        <div class="form-group">
          <label class="form-label">Select Student</label>
          <select name="student_id" class="hrms-input" required>
            <?php foreach ($student_options as $so): ?>
              <option value="<?php echo $so['id']; ?>"><?php echo htmlspecialchars($so['student_name']); ?> (<?php echo $so['roll_no']; ?>) — Due: ₹<?php echo number_format($so['due'], 0); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Payment Amount (₹)</label>
            <input type="number" name="amount" class="hrms-input" placeholder="Amount" value="15000" required>
          </div>
          <div class="form-group">
            <label class="form-label">Payment Method</label>
            <select name="payment_method" class="hrms-input">
              <option value="Online UPI (GPay/PhonePe)">Online UPI (GPay/PhonePe)</option>
              <option value="Net Banking / NEFT">Net Banking / NEFT</option>
              <option value="Credit / Debit Card">Credit / Debit Card</option>
              <option value="Cheque">Bank Cheque</option>
              <option value="Cash Deposit">Cash Deposit at Desk</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Remarks / Description</label>
          <input type="text" name="remarks" class="hrms-input" value="Term Installment Fee">
        </div>
        <button type="submit" class="login-submit-btn">
          <i class="fa-solid fa-print"></i> Record &amp; Issue Receipt
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
