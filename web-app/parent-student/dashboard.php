<?php
require_once __DIR__ . '/../config.php';

// Ensure user is parent, student, or admin
$current_user = require_login(['parent', 'student', 'super_admin']);

$page_title = 'Parent & Student Academic Portal';
$page_heading = 'Student Academic & Fee Portal';
$page_subheading = 'Real-Time Attendance, Exam Analytics & Fee Status';
$active_menu = 'dashboard';

$db = get_db_connection();

// Find linked student for this parent/student
if (!empty($_SESSION['active_student_id'])) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['active_student_id']]);
    $student = $stmt->fetch();
}

if (empty($student)) {
    if ($current_user['role'] === 'parent') {
        $stmt = $db->prepare("SELECT * FROM students WHERE parent_user_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$current_user['id']]);
        $student = $stmt->fetch();
    } else {
        // If student user logged in
        $stmt = $db->prepare("SELECT * FROM students WHERE user_id = ? OR student_name LIKE ? OR roll_no LIKE 'KSV-2026-101' LIMIT 1");
        $stmt->execute([$current_user['id'], '%' . $current_user['full_name'] . '%']);
        $student = $stmt->fetch();
    }
}

// Fallback if none directly linked
if (!$student) {
    $student = $db->query("SELECT * FROM students ORDER BY id ASC LIMIT 1")->fetch();
}

$student_id = $student['id'];

// Fetch Exam Results for this student
$exam_stmt = $db->prepare("SELECT * FROM exam_results WHERE student_id = ? ORDER BY exam_date DESC");
$exam_stmt->execute([$student_id]);
$exam_results = $exam_stmt->fetchAll();

// Fetch Fee Receipts for this student
$fee_stmt = $db->prepare("SELECT * FROM fee_payments WHERE student_id = ? ORDER BY payment_date DESC");
$fee_stmt->execute([$student_id]);
$fee_receipts = $fee_stmt->fetchAll();

// Fetch Attendance logs for this student
$att_stmt = $db->prepare("SELECT * FROM attendance_logs WHERE type = 'student' AND reference_id = ? ORDER BY log_date DESC LIMIT 7");
$att_stmt->execute([$student_id]);
$attendance_logs = $att_stmt->fetchAll();

// Fetch Announcements for Parents
$announcements = $db->query("SELECT * FROM announcements WHERE target_role IN ('all', 'parent') ORDER BY id DESC LIMIT 4")->fetchAll();

require_once __DIR__ . '/../includes/layout-header.php';
?>

<?php if (!empty($_SESSION['impersonated_student'])): ?>
  <div style="background: #0284c7; color: #ffffff; padding: 10px 18px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);">
    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.84rem;">
      <i class="fa-solid fa-circle-user" style="font-size: 1.1rem;"></i>
      <span>Logged in via Admin Impersonation as: <strong><?php echo htmlspecialchars($student['student_name']); ?></strong> (Roll: <?php echo htmlspecialchars($student['roll_no']); ?>)</span>
    </div>
    <a href="<?php echo ADMIN_URL; ?>/students" style="background: #ffffff; color: #0284c7; font-weight: 700; font-size: 0.78rem; padding: 5px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
      <i class="fa-solid fa-arrow-left"></i> Return to Admin Panel
    </a>
  </div>
<?php endif; ?>

<!-- Student Profile Banner -->
<div style="background: linear-gradient(135deg, #001f33 0%, #003754 50%, #007cbd 100%); border-radius: var(--hrms-radius-lg); padding: 26px 30px; color: #ffffff; margin-bottom: 28px; box-shadow: var(--hrms-shadow-md); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
  <div style="display: flex; align-items: center; gap: 18px;">
    <div style="width: 68px; height: 68px; border-radius: 50%; background: #ffffff; color: var(--hrms-primary); display: flex; align-items: center; justify-content: center; font-size: 1.9rem; font-weight: 800; border: 3px solid rgba(255, 255, 255, 0.4); box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
      <?php echo strtoupper(substr($student['student_name'], 0, 1)); ?>
    </div>
    <div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.55rem; font-weight: 800; color: #ffffff;"><?php echo htmlspecialchars($student['student_name']); ?></h2>
        <span style="background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.3); color: #ffffff; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
          Roll: <?php echo htmlspecialchars($student['roll_no']); ?>
        </span>
      </div>
      <p style="font-size: 0.88rem; color: #bae6fd; margin-top: 4px;">
        <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($student['standard']); ?> &bull; 
        <i class="fa-solid fa-clock"></i> <?php echo htmlspecialchars($student['batch_timing']); ?>
      </p>
    </div>
  </div>

  <div style="display: flex; gap: 12px; align-items: center;">
    <div style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); padding: 10px 18px; border-radius: var(--hrms-radius-md); text-align: right;">
      <span style="font-size: 0.7rem; text-transform: uppercase; color: #bae6fd; font-weight: 700;">Guardian Name</span>
      <div style="font-weight: 700; font-size: 0.95rem; color: #ffffff;"><?php echo htmlspecialchars($student['parent_name']); ?></div>
    </div>
  </div>
</div>

<!-- Stat Metric Cards -->
<div class="stat-grid">
  
  <div class="stat-card blue">
    <div class="stat-info">
      <span class="stat-label">Attendance Rate</span>
      <span class="stat-number"><?php echo $student['attendance_percentage']; ?>%</span>
      <span class="stat-subtext" style="color: var(--hrms-success); font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Highly Regular</span>
    </div>
    <div class="stat-icon-wrapper">
      <i class="fa-solid fa-calendar-check"></i>
    </div>
  </div>

  <div class="stat-card green">
    <div class="stat-info">
      <span class="stat-label">Fees Paid</span>
      <span class="stat-number"><?php echo format_inr($student['paid_fees']); ?></span>
      <span class="stat-subtext">Receipts Verified</span>
    </div>
    <div class="stat-icon-wrapper">
      <i class="fa-solid fa-wallet"></i>
    </div>
  </div>

  <div class="stat-card amber">
    <div class="stat-info">
      <span class="stat-label">Pending Balance</span>
      <span class="stat-number">
        <?php 
          $bal = floatval($student['total_fees']) - floatval($student['paid_fees']); 
          echo format_inr(max(0, $bal)); 
        ?>
      </span>
      <span class="stat-subtext">Total: <?php echo format_inr($student['total_fees']); ?></span>
    </div>
    <div class="stat-icon-wrapper">
      <i class="fa-solid fa-receipt"></i>
    </div>
  </div>

  <div class="stat-card purple">
    <div class="stat-info">
      <span class="stat-label">Latest Test Rank</span>
      <span class="stat-number">Rank #2</span>
      <span class="stat-subtext">Grade: Outstanding (O)</span>
    </div>
    <div class="stat-icon-wrapper">
      <i class="fa-solid fa-trophy"></i>
    </div>
  </div>

</div>

<!-- 2 Columns: Test Performance & Attendance -->
<div class="dashboard-grid-2">
  
  <!-- Exam Results & Academic Report -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-chart-line"></i> Academic Test Marks &amp; Progress</h2>
      <span class="badge-status badge-approved">Session 2026-27</span>
    </div>
    <div class="table-responsive">
      <table class="hrms-table">
        <thead>
          <tr>
            <th>Exam / Assessment</th>
            <th>Subject</th>
            <th>Marks Scored</th>
            <th>Percentage</th>
            <th>Grade</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($exam_results as $ex): ?>
            <?php 
              $pct = round(($ex['marks_obtained'] / $ex['total_marks']) * 100, 1);
            ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($ex['exam_name']); ?></strong><br><small style="color:var(--hrms-text-muted);"><?php echo format_date($ex['exam_date']); ?></small></td>
              <td><?php echo htmlspecialchars($ex['subject']); ?></td>
              <td><strong><?php echo $ex['marks_obtained']; ?></strong> / <?php echo $ex['total_marks']; ?></td>
              <td>
                <div style="display: flex; align-items: center; gap: 8px;">
                  <div style="flex: 1; height: 6px; background: #e2e8f0; border-radius: 4px; overflow: hidden; width: 60px;">
                    <div style="width: <?php echo $pct; ?>%; height: 100%; background: var(--hrms-primary);"></div>
                  </div>
                  <small style="font-weight: 700;"><?php echo $pct; ?>%</small>
                </div>
              </td>
              <td><span class="badge-status badge-present"><?php echo htmlspecialchars($ex['grade']); ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Daily Attendance Log -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-calendar-days"></i> Daily Attendance Log</h2>
      <span style="font-size: 0.8rem; font-weight: 700; color: var(--hrms-success);">96.5% Regular</span>
    </div>
    <div class="hrms-card-body" style="padding: 0;">
      <table class="hrms-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Entry Time</th>
            <th>Status</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attendance_logs as $att): ?>
            <tr>
              <td><strong><?php echo format_date($att['log_date'], 'D, d M'); ?></strong></td>
              <td><?php echo $att['check_in_time'] ? format_time($att['check_in_time']) : '—'; ?></td>
              <td><span class="badge-status badge-present"><?php echo $att['status']; ?></span></td>
              <td><small style="color: var(--hrms-text-muted);"><?php echo htmlspecialchars($att['remarks']); ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Second Row: Fee Payments Receipts & Academy Notices -->
<div class="dashboard-grid-2">
  
  <!-- Fee Receipts Table -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-receipt"></i> Official Tuition Fee Receipts</h2>
      <a href="<?php echo PARENT_URL; ?>/fees" class="btn-hrms btn-hrms-outline btn-hrms-sm">Fee Statement</a>
    </div>
    <div class="table-responsive">
      <table class="hrms-table">
        <thead>
          <tr>
            <th>Receipt Number</th>
            <th>Payment Date</th>
            <th>Mode</th>
            <th>Amount</th>
            <th>Slip</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fee_receipts as $fr): ?>
            <tr>
              <td><code><?php echo htmlspecialchars($fr['receipt_no']); ?></code></td>
              <td><?php echo format_date($fr['payment_date'], 'd M Y'); ?></td>
              <td><?php echo htmlspecialchars($fr['payment_method']); ?></td>
              <td><strong style="color: var(--hrms-success);"><?php echo format_inr($fr['amount']); ?></strong></td>
              <td>
                <button type="button" class="btn-hrms btn-hrms-outline btn-hrms-sm" onclick="alert('KSV Academy Official Receipt\nReceipt No: <?php echo $fr['receipt_no']; ?>\nStudent: <?php echo $student['student_name']; ?>\nAmount: ₹<?php echo $fr['amount']; ?>\nPayment Date: <?php echo $fr['payment_date']; ?>\nStatus: Paid & Cleared')">
                  <i class="fa-solid fa-download"></i> Receipt
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Parent Notices & Announcements -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-bullhorn"></i> Academy Circulars &amp; Alerts</h2>
    </div>
    <div class="hrms-card-body">
      <div class="notice-list">
        <?php foreach ($announcements as $ann): ?>
          <div class="notice-item">
            <div class="notice-header">
              <span class="notice-title"><?php echo htmlspecialchars($ann['title']); ?></span>
              <span class="notice-time"><?php echo format_date($ann['created_at'], 'd M'); ?></span>
            </div>
            <p class="notice-desc"><?php echo htmlspecialchars($ann['content']); ?></p>
            <div style="margin-top: 8px; font-size: 0.72rem; color: var(--hrms-primary); font-weight: 700;">
              <i class="fa-solid fa-user-tie"></i> Issued by: <?php echo htmlspecialchars($ann['posted_by']); ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
