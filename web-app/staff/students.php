<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
require_menu_permission('students');
$page_title = 'Students Roster & Batches';
$page_heading = 'Students Roster & Batches';
$page_subheading = 'Active Enrolled Students, Fee Records & Attendance Ratios';
$active_menu = 'students';

$db = get_db_connection();

// Handle Add Student (Super Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $student_name = trim($_POST['student_name'] ?? '');
    $standard = trim($_POST['standard'] ?? '');
    $batch_timing = trim($_POST['batch_timing'] ?? '');
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $total_fees = floatval($_POST['total_fees'] ?? 50000);
    $paid_fees = floatval($_POST['paid_fees'] ?? 0);

    if (!empty($student_name)) {
        $roll_no = 'KSV-2026-' . rand(110, 999);
        $stmt = $db->prepare("INSERT INTO students (student_name, roll_no, standard, batch_timing, parent_name, parent_phone, total_fees, paid_fees, attendance_percentage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 100.00)");
        $stmt->execute([$student_name, $roll_no, $standard, $batch_timing, $parent_name, $parent_phone, $total_fees, $paid_fees]);

        // If initial fee was paid, record in fee_payments
        if ($paid_fees > 0) {
            $student_id = $db->lastInsertId();
            $rec_no = 'REC-2026-0' . rand(100, 999);
            $db->prepare("INSERT INTO fee_payments (student_id, receipt_no, amount, payment_method, payment_date, remarks) VALUES (?, ?, ?, 'UPI / Cash', CURDATE(), 'Admission Registration Advance')")->execute([$student_id, $rec_no, $paid_fees]);
        }

        set_flash('success', "Student {$student_name} ({$roll_no}) enrolled successfully!");
        header('Location: ' . STAFF_URL . '/students');
        exit;
    }
}

// Fetch Students Roster
$students = $db->query("SELECT * FROM students ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/../includes/layout-header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
  <div>
    <span style="font-size: 0.9rem; color: var(--hrms-text-muted); font-weight: 600;">
      Enrolled Students: <strong><?php echo count($students); ?></strong>
    </span>
  </div>
  <?php if ($current_user['role'] === 'super_admin'): ?>
    <button type="button" class="btn-hrms btn-hrms-primary" onclick="openModal('addStudentModal')">
      <i class="fa-solid fa-user-plus"></i> Enroll New Student
    </button>
  <?php endif; ?>
</div>

<div class="hrms-card">
  <div class="hrms-card-header">
    <h2 class="card-title"><i class="fa-solid fa-user-graduate"></i> Enrolled Students List</h2>
  </div>
  <div class="table-responsive">
    <table class="hrms-table">
      <thead>
        <tr>
          <th>Roll No</th>
          <th>Student Name</th>
          <th>Class &amp; Batch</th>
          <th>Parent / Guardian</th>
          <th>Attendance</th>
          <th>Fees Paid / Total</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $st): ?>
          <?php 
            $bal = floatval($st['total_fees']) - floatval($st['paid_fees']); 
          ?>
          <tr>
            <td><code><?php echo htmlspecialchars($st['roll_no']); ?></code></td>
            <td>
              <div class="user-cell">
                <div class="cell-avatar"><?php echo strtoupper(substr($st['student_name'], 0, 1)); ?></div>
                <div class="cell-meta">
                  <span class="cell-name"><?php echo htmlspecialchars($st['student_name']); ?></span>
                  <span class="cell-sub">Enrolled <?php echo format_date($st['created_at']); ?></span>
                </div>
              </div>
            </td>
            <td>
              <strong><?php echo htmlspecialchars($st['standard']); ?></strong><br>
              <small style="color: var(--hrms-text-muted);"><?php echo htmlspecialchars($st['batch_timing']); ?></small>
            </td>
            <td>
              <small><?php echo htmlspecialchars($st['parent_name'] ?? '—'); ?><br><?php echo htmlspecialchars($st['parent_phone'] ?? '—'); ?></small>
            </td>
            <td>
              <div style="display: flex; align-items: center; gap: 8px;">
                <div style="flex: 1; height: 6px; background: #e2e8f0; border-radius: 4px; overflow: hidden; width: 60px;">
                  <div style="width: <?php echo $st['attendance_percentage']; ?>%; height: 100%; background: var(--hrms-success);"></div>
                </div>
                <small style="font-weight: 700;"><?php echo $st['attendance_percentage']; ?>%</small>
              </div>
            </td>
            <td>
              <strong style="color: var(--hrms-success);"><?php echo format_inr($st['paid_fees']); ?></strong> / <?php echo format_inr($st['total_fees']); ?>
              <?php if ($bal > 0): ?>
                <br><small style="color: var(--hrms-danger); font-weight: 600;">Due: <?php echo format_inr($bal); ?></small>
              <?php else: ?>
                <br><small style="color: var(--hrms-success); font-weight: 600;">Fully Paid</small>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge-status <?php echo ($bal <= 0) ? 'badge-paid' : 'badge-pending'; ?>">
                <?php echo ($bal <= 0) ? 'Cleared' : 'Pending'; ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Enroll Student -->
<div class="hrms-modal" id="addStudentModal">
  <div class="hrms-modal-card">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--hrms-navy);"><i class="fa-solid fa-user-plus" style="color: var(--hrms-primary);"></i> Enroll New Student</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('addStudentModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo STAFF_URL; ?>/students">
      <div class="hrms-modal-body">
        <div class="form-group">
          <label class="form-label">Student Full Name</label>
          <input type="text" name="student_name" class="hrms-input" placeholder="e.g. Atharva Joshi" required>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Course / Standard</label>
            <input type="text" name="standard" class="hrms-input" placeholder="e.g. 11th Science IIT-JEE" required>
          </div>
          <div class="form-group">
            <label class="form-label">Batch Timing</label>
            <input type="text" name="batch_timing" class="hrms-input" placeholder="e.g. Morning 7:30 AM" required>
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Parent / Guardian Name</label>
            <input type="text" name="parent_name" class="hrms-input" placeholder="Parent name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Parent Mobile</label>
            <input type="text" name="parent_phone" class="hrms-input" placeholder="+91 98200 00000" required>
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Total Agreed Fees (₹)</label>
            <input type="number" name="total_fees" class="hrms-input" value="60000" required>
          </div>
          <div class="form-group">
            <label class="form-label">Initial Paid Amount (₹)</label>
            <input type="number" name="paid_fees" class="hrms-input" value="20000">
          </div>
        </div>
        <button type="submit" class="login-submit-btn">
          <i class="fa-solid fa-check"></i> Complete Enrollment
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
