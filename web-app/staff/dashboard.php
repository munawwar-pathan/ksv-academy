<?php
require_once __DIR__ . '/../config.php';

// Ensure user is staff or admin
$current_user = require_login(['staff', 'super_admin']);
require_menu_permission('dashboard');

$page_title = 'Faculty & Staff Portal';
$page_heading = 'Faculty Self-Service Portal';
$page_subheading = 'Attendance Tracking, Leave Requests & Batch Management';
$active_menu = 'dashboard';

$db = get_db_connection();

// Get Staff Profile Info
$stmt = $db->prepare("SELECT * FROM staff_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$current_user['id']]);
$staff_profile = $stmt->fetch();

// If user is staff without profile, create default fallback
if (!$staff_profile) {
    $emp_code = 'KSV-FAC-0' . $current_user['id'];
    $stmt = $db->prepare("INSERT INTO staff_profiles (user_id, employee_code, department, designation, joining_date, monthly_salary, today_status) VALUES (?, ?, 'Academics', 'Faculty Member', CURDATE(), 60000, 'Present')");
    $stmt->execute([$current_user['id'], $emp_code]);
    $staff_profile = $db->query("SELECT * FROM staff_profiles WHERE user_id = {$current_user['id']}")->fetch();
}

$staff_id = $staff_profile['id'];

// Handle Attendance Punch In / Punch Out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'punch_in') {
        $now_time = date('H:i:s');
        $today = date('Y-m-d');
        // Check if log exists
        $chk = $db->prepare("SELECT id FROM attendance_logs WHERE type = 'staff' AND reference_id = ? AND log_date = ?");
        $chk->execute([$staff_id, $today]);
        $existing = $chk->fetch();

        if ($existing) {
            $upd = $db->prepare("UPDATE attendance_logs SET check_in_time = ?, status = 'Present' WHERE id = ?");
            $upd->execute([$now_time, $existing['id']]);
        } else {
            $ins = $db->prepare("INSERT INTO attendance_logs (type, reference_id, log_date, status, check_in_time, remarks) VALUES ('staff', ?, ?, 'Present', ?, 'Self biometric punch-in')");
            $ins->execute([$staff_id, $today, $now_time]);
        }

        // Update today_status
        $db->prepare("UPDATE staff_profiles SET today_status = 'Present' WHERE id = ?")->execute([$staff_id]);
        set_flash('success', "Punch In recorded successfully at " . date('h:i A') . "!");
        header('Location: ' . STAFF_URL . '/dashboard');
        exit;
    }

    if ($action === 'punch_out') {
        $now_time = date('H:i:s');
        $today = date('Y-m-d');
        $upd = $db->prepare("UPDATE attendance_logs SET check_out_time = ? WHERE type = 'staff' AND reference_id = ? AND log_date = ?");
        $upd->execute([$now_time, $staff_id, $today]);
        set_flash('success', "Punch Out recorded successfully at " . date('h:i A') . "!");
        header('Location: ' . STAFF_URL . '/dashboard');
        exit;
    }

    if ($action === 'apply_leave') {
        $ltype = trim($_POST['leave_type'] ?? 'Casual Leave');
        $sdate = trim($_POST['start_date'] ?? date('Y-m-d'));
        $edate = trim($_POST['end_date'] ?? date('Y-m-d'));
        $reason = trim($_POST['reason'] ?? '');

        if (!empty($sdate) && !empty($edate) && !empty($reason)) {
            $ins = $db->prepare("INSERT INTO leave_requests (staff_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $ins->execute([$staff_id, $ltype, $sdate, $edate, $reason]);
            set_flash('success', 'Leave application submitted for Director approval!');
            header('Location: ' . STAFF_URL . '/dashboard');
            exit;
        }
    }
}

// Fetch Today's Attendance Log for this staff
$today_log = $db->prepare("SELECT * FROM attendance_logs WHERE type = 'staff' AND reference_id = ? AND log_date = CURDATE() LIMIT 1");
$today_log->execute([$staff_id]);
$current_punch = $today_log->fetch();

// Fetch My Leave Requests
$my_leaves = $db->prepare("SELECT * FROM leave_requests WHERE staff_id = ? ORDER BY id DESC LIMIT 5");
$my_leaves->execute([$staff_id]);
$leave_records = $my_leaves->fetchAll();

// Fetch My Payslips
$my_payroll = $db->prepare("SELECT * FROM payroll WHERE staff_id = ? ORDER BY year DESC, id DESC LIMIT 4");
$my_payroll->execute([$staff_id]);
$payroll_slips = $my_payroll->fetchAll();

// Fetch Announcements for Staff
$notices = $db->query("SELECT * FROM announcements WHERE target_role IN ('all', 'staff') ORDER BY id DESC LIMIT 4")->fetchAll();

require_once __DIR__ . '/../includes/layout-header.php';
?>

<?php if (!empty($_SESSION['impersonated_staff'])): ?>
  <div style="background: #0284c7; color: #ffffff; padding: 10px 18px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);">
    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.84rem;">
      <i class="fa-solid fa-circle-user" style="font-size: 1.1rem;"></i>
      <span>Logged in via Admin Impersonation as Faculty: <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong> (<?php echo htmlspecialchars($staff_profile['employee_code']); ?>)</span>
    </div>
    <a href="<?php echo ADMIN_URL; ?>/staff" style="background: #ffffff; color: #0284c7; font-weight: 700; font-size: 0.78rem; padding: 5px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
      <i class="fa-solid fa-arrow-left"></i> Return to Admin Panel
    </a>
  </div>
<?php endif; ?>

<!-- Staff Top Bio Card -->
<div style="background: linear-gradient(135deg, #002f47 0%, #00466b 50%, #007cbd 100%); border-radius: var(--hrms-radius-lg); padding: 26px 30px; color: #ffffff; margin-bottom: 28px; box-shadow: var(--hrms-shadow-md); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
  <div style="display: flex; align-items: center; gap: 18px;">
    <div style="width: 64px; height: 64px; border-radius: 50%; background: #ffffff; color: var(--hrms-primary); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 800; border: 3px solid rgba(255, 255, 255, 0.4); box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
      <?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?>
    </div>
    <div>
      <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.45rem; font-weight: 800; color: #ffffff;"><?php echo htmlspecialchars($current_user['full_name']); ?></h2>
      <p style="font-size: 0.88rem; color: #93c5fd; margin-top: 2px;">
        <i class="fa-solid fa-id-badge"></i> <?php echo htmlspecialchars($staff_profile['employee_code']); ?> &bull; 
        <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($staff_profile['designation']); ?> (<?php echo htmlspecialchars($staff_profile['department']); ?>)
      </p>
    </div>
  </div>
  <div>
    <button type="button" class="btn-hrms btn-hrms-primary" onclick="openModal('applyLeaveModal')" style="background: #ffffff; color: var(--hrms-primary); border-color: #ffffff; font-weight: 700;">
      <i class="fa-solid fa-calendar-plus"></i> Apply For Leave
    </button>
  </div>
</div>

<!-- Two Columns: Real-Time Attendance Punch & Statistics -->
<div class="dashboard-grid-2">
  
  <!-- Real-Time Biometric Punch Box -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Digital Attendance Punch</h2>
      <span class="badge-status badge-active"><i class="fa-solid fa-signal"></i> Biometric Online</span>
    </div>
    <div class="hrms-card-body" style="text-align: center; padding: 32px 24px;">
      
      <!-- Live Clock Display -->
      <div style="margin-bottom: 20px;">
        <div id="staffLiveClock" style="font-family: 'Outfit', sans-serif; font-size: 2.6rem; font-weight: 800; color: var(--hrms-navy); line-height: 1;">
          <?php echo date('h:i:s A'); ?>
        </div>
        <p style="font-size: 0.82rem; color: var(--hrms-text-muted); margin-top: 6px; font-weight: 600;">
          <?php echo date('l, d F Y'); ?> (Shift: 7:30 AM - 3:30 PM)
        </p>
      </div>

      <!-- Current Status -->
      <div style="background: var(--hrms-bg); border-radius: var(--hrms-radius-md); padding: 16px; margin-bottom: 24px; display: flex; justify-content: space-around; border: 1px solid var(--hrms-border);">
        <div>
          <span style="font-size: 0.74rem; color: var(--hrms-text-muted); font-weight: 700; text-transform: uppercase;">Check-In</span>
          <div style="font-weight: 800; font-size: 1.1rem; color: var(--hrms-success); margin-top: 2px;">
            <?php echo (!empty($current_punch['check_in_time'])) ? format_time($current_punch['check_in_time']) : '— Not Yet —'; ?>
          </div>
        </div>
        <div style="width: 1px; background: var(--hrms-border);"></div>
        <div>
          <span style="font-size: 0.74rem; color: var(--hrms-text-muted); font-weight: 700; text-transform: uppercase;">Check-Out</span>
          <div style="font-weight: 800; font-size: 1.1rem; color: var(--hrms-navy); margin-top: 2px;">
            <?php echo (!empty($current_punch['check_out_time'])) ? format_time($current_punch['check_out_time']) : '— In Progress —'; ?>
          </div>
        </div>
      </div>

      <!-- Punch Action Buttons -->
      <div style="display: flex; gap: 14px; justify-content: center;">
        <form method="POST">
          <input type="hidden" name="action" value="punch_in">
          <button type="submit" class="btn-hrms btn-hrms-primary" style="padding: 12px 24px; font-size: 0.95rem;">
            <i class="fa-solid fa-fingerprint"></i> Punch In
          </button>
        </form>

        <form method="POST">
          <input type="hidden" name="action" value="punch_out">
          <button type="submit" class="btn-hrms btn-hrms-outline" style="padding: 12px 24px; font-size: 0.95rem;">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Punch Out
          </button>
        </form>
      </div>

    </div>
  </div>

  <!-- Faculty Monthly Performance & Salary Overview -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-file-invoice-dollar"></i> Salary Slips &amp; Payroll</h2>
      <span style="font-size: 0.8rem; font-weight: 700; color: var(--hrms-primary);">Fixed Monthly: <?php echo format_inr($staff_profile['monthly_salary'] ?? 60000); ?></span>
    </div>
    <div class="hrms-card-body" style="padding: 0;">
      <table class="hrms-table">
        <thead>
          <tr>
            <th>Month / Year</th>
            <th>Net Amount</th>
            <th>Status</th>
            <th>Slip</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payroll_slips as $ps): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($ps['month']); ?> <?php echo $ps['year']; ?></strong></td>
              <td><strong style="color: var(--hrms-navy);"><?php echo format_inr($ps['net_salary']); ?></strong></td>
              <td>
                <span class="badge-status badge-paid">
                  <i class="fa-solid fa-check"></i> <?php echo $ps['payment_status']; ?>
                </span>
              </td>
              <td>
                <button type="button" class="btn-hrms btn-hrms-outline btn-hrms-sm" onclick="alert('Viewing Payslip: <?php echo $ps['month']; ?> <?php echo $ps['year']; ?>\nBasic: ₹<?php echo $ps['basic_salary']; ?>\nNet Credited: ₹<?php echo $ps['net_salary']; ?>')">
                  <i class="fa-solid fa-download"></i> View
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Second Grid: Leave Application Status & Notices -->
<div class="dashboard-grid-2">
  
  <!-- My Leave Applications -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-calendar-check"></i> My Leave Applications</h2>
      <button type="button" class="btn-hrms btn-hrms-primary btn-hrms-sm" onclick="openModal('applyLeaveModal')">+ Apply Leave</button>
    </div>
    <div class="table-responsive">
      <table class="hrms-table">
        <thead>
          <tr>
            <th>Leave Type</th>
            <th>Dates</th>
            <th>Reason</th>
            <th>Director Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($leave_records)): ?>
            <tr><td colspan="4" style="text-align: center; color: var(--hrms-text-muted);">No leave records found.</td></tr>
          <?php else: ?>
            <?php foreach ($leave_records as $lr): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($lr['leave_type']); ?></strong></td>
                <td><?php echo format_date($lr['start_date'], 'd M'); ?> – <?php echo format_date($lr['end_date'], 'd M Y'); ?></td>
                <td><small style="color: var(--hrms-text-muted);"><?php echo htmlspecialchars($lr['reason']); ?></small></td>
                <td>
                  <?php 
                    $stat = $lr['status'];
                    $bclass = 'badge-pending';
                    if ($stat === 'Approved') $bclass = 'badge-approved';
                    elseif ($stat === 'Rejected') $bclass = 'badge-rejected';
                  ?>
                  <span class="badge-status <?php echo $bclass; ?>"><?php echo $stat; ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Academy Notices for Staff -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-bullhorn"></i> Academy Circulars</h2>
    </div>
    <div class="hrms-card-body">
      <div class="notice-list">
        <?php foreach ($notices as $not): ?>
          <div class="notice-item">
            <div class="notice-header">
              <span class="notice-title"><?php echo htmlspecialchars($not['title']); ?></span>
              <span class="notice-time"><?php echo format_date($not['created_at'], 'd M'); ?></span>
            </div>
            <p class="notice-desc"><?php echo htmlspecialchars($not['content']); ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- Modal: Apply Leave -->
<div class="hrms-modal" id="applyLeaveModal">
  <div class="hrms-modal-card">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--hrms-navy);"><i class="fa-solid fa-calendar-plus" style="color: var(--hrms-primary);"></i> Apply for Leave</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('applyLeaveModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo STAFF_URL; ?>/dashboard">
      <input type="hidden" name="action" value="apply_leave">
      <div class="hrms-modal-body">
        <div class="form-group">
          <label class="form-label">Leave Type</label>
          <select name="leave_type" class="hrms-input">
            <option value="Casual Leave">Casual Leave (CL)</option>
            <option value="Medical Leave">Medical Leave (ML)</option>
            <option value="Academic Duty">Academic / Examination Duty</option>
            <option value="Emergency Leave">Emergency Leave</option>
          </select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="hrms-input" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="hrms-input" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Reason / Justification</label>
          <textarea name="reason" class="hrms-input" rows="3" placeholder="Provide reason for leave..." required></textarea>
        </div>
        <button type="submit" class="login-submit-btn">
          <i class="fa-solid fa-paper-plane"></i> Submit Application to Director
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
