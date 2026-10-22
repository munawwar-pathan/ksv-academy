<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
$page_title = 'Attendance Tracker';
$page_heading = 'Attendance Records & Biometric Logs';
$page_subheading = 'Real-Time Punch In/Out, Daily Presence and Duty Logs';
$active_menu = 'attendance';

$db = get_db_connection();

$filter_type = $_GET['type'] ?? ($current_user['role'] === 'parent' || $current_user['role'] === 'student' ? 'student' : 'staff');
$filter_date = $_GET['date'] ?? date('Y-m-d');

// Handle Record Attendance (Super Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $type = $_POST['type'] ?? 'staff';
    $ref_id = intval($_POST['reference_id'] ?? 0);
    $status = $_POST['status'] ?? 'Present';
    $remarks = trim($_POST['remarks'] ?? 'Manual admin entry');
    $log_date = $_POST['log_date'] ?? date('Y-m-d');
    $check_in = !empty($_POST['check_in']) ? $_POST['check_in'] : '07:30:00';

    if ($ref_id > 0) {
        $stmt = $db->prepare("INSERT INTO attendance_logs (type, reference_id, log_date, status, check_in_time, remarks) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$type, $ref_id, $log_date, $status, $check_in, $remarks]);
        set_flash('success', 'Attendance record added successfully!');
        header("Location: " . PARENT_URL . "/attendance?type={$type}&date={$log_date}");
        exit;
    }
}

// Fetch logs according to role
if ($current_user['role'] === 'parent' || $current_user['role'] === 'student') {
    // Show student logs only
    $active_sid = intval($_SESSION['active_student_id'] ?? 0);
    if ($active_sid > 0) {
        $stmt = $db->prepare("
            SELECT al.*, s.student_name as name, s.roll_no as code, s.standard as department
            FROM attendance_logs al
            JOIN students s ON al.reference_id = s.id
            WHERE al.type = 'student' AND s.id = ?
            ORDER BY al.log_date DESC, al.id DESC
            LIMIT 50
        ");
        $stmt->execute([$active_sid]);
        $logs = $stmt->fetchAll();
    } else {
        $logs = $db->query("
            SELECT al.*, s.student_name as name, s.roll_no as code, s.standard as department
            FROM attendance_logs al
            JOIN students s ON al.reference_id = s.id
            WHERE al.type = 'student'
            ORDER BY al.log_date DESC, al.id DESC
            LIMIT 50
        ")->fetchAll();
    }
} else {
    // Show filtered logs
    if ($filter_type === 'staff') {
        $stmt = $db->prepare("
            SELECT al.*, u.full_name as name, sp.employee_code as code, sp.department
            FROM attendance_logs al
            JOIN staff_profiles sp ON al.reference_id = sp.id
            JOIN users u ON sp.user_id = u.id
            WHERE al.type = 'staff' AND (al.log_date = ? OR ? = '')
            ORDER BY al.id DESC
        ");
        $stmt->execute([$filter_date, $filter_date]);
        $logs = $stmt->fetchAll();
    } else {
        $stmt = $db->prepare("
            SELECT al.*, s.student_name as name, s.roll_no as code, s.standard as department
            FROM attendance_logs al
            JOIN students s ON al.reference_id = s.id
            WHERE al.type = 'student' AND (al.log_date = ? OR ? = '')
            ORDER BY al.id DESC
        ");
        $stmt->execute([$filter_date, $filter_date]);
        $logs = $stmt->fetchAll();
    }
}

// Staff list for modal dropdown
$staff_options = $db->query("SELECT sp.id, u.full_name, sp.employee_code FROM staff_profiles sp JOIN users u ON sp.user_id = u.id")->fetchAll();
$student_options = $db->query("SELECT id, student_name, roll_no FROM students")->fetchAll();

require_once __DIR__ . '/../includes/layout-header.php';
?>

<!-- Filter Bar -->
<div style="background: #ffffff; border: 1px solid var(--hrms-border); border-radius: var(--hrms-radius-lg); padding: 16px 22px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; box-shadow: var(--hrms-shadow-sm);">
  <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
    <div>
      <select name="type" class="hrms-input" onchange="this.form.submit()" style="padding: 7px 12px; font-weight: 600;">
        <option value="staff" <?php echo $filter_type === 'staff' ? 'selected' : ''; ?>>Faculty &amp; Staff Logs</option>
        <option value="student" <?php echo $filter_type === 'student' ? 'selected' : ''; ?>>Students Attendance</option>
      </select>
    </div>
    <div>
      <input type="date" name="date" class="hrms-input" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="this.form.submit()" style="padding: 7px 12px;">
    </div>
    <a href="<?php echo PARENT_URL; ?>/attendance" class="btn-hrms btn-hrms-outline btn-hrms-sm">Clear Filter</a>
  </form>

  <?php if ($current_user['role'] === 'super_admin'): ?>
    <button type="button" class="btn-hrms btn-hrms-primary" onclick="openModal('markAttendanceModal')">
      <i class="fa-solid fa-plus"></i> Manual Attendance Entry
    </button>
  <?php endif; ?>
</div>

<div class="hrms-card">
  <div class="hrms-card-header">
    <h2 class="card-title">
      <i class="fa-solid fa-clipboard-user"></i> 
      <?php echo ucfirst($filter_type); ?> Attendance Logs (<?php echo format_date($filter_date); ?>)
    </h2>
  </div>
  <div class="table-responsive">
    <table class="hrms-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>ID / Roll</th>
          <th>Name</th>
          <th>Department / Class</th>
          <th>Check-In</th>
          <th>Check-Out</th>
          <th>Status</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="8" style="text-align: center; padding: 30px; color: var(--hrms-text-muted);">No attendance records found for this filter.</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $lg): ?>
            <tr>
              <td><strong><?php echo format_date($lg['log_date'], 'd M Y'); ?></strong></td>
              <td><code><?php echo htmlspecialchars($lg['code']); ?></code></td>
              <td><strong><?php echo htmlspecialchars($lg['name']); ?></strong></td>
              <td><?php echo htmlspecialchars($lg['department']); ?></td>
              <td><?php echo $lg['check_in_time'] ? format_time($lg['check_in_time']) : '—'; ?></td>
              <td><?php echo $lg['check_out_time'] ? format_time($lg['check_out_time']) : '—'; ?></td>
              <td>
                <span class="badge-status <?php echo ($lg['status'] === 'Present') ? 'badge-present' : (($lg['status'] === 'Absent') ? 'badge-absent' : 'badge-leave'); ?>">
                  <?php echo $lg['status']; ?>
                </span>
              </td>
              <td><small style="color: var(--hrms-text-muted);"><?php echo htmlspecialchars($lg['remarks']); ?></small></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Manual Attendance Entry -->
<div class="hrms-modal" id="markAttendanceModal">
  <div class="hrms-modal-card">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--hrms-navy);"><i class="fa-solid fa-clock"></i> Record Attendance Log</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('markAttendanceModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo PARENT_URL; ?>/attendance">
      <div class="hrms-modal-body">
        <div class="form-group">
          <label class="form-label">Type</label>
          <select name="type" class="hrms-input" id="attTypeSelect">
            <option value="staff">Staff / Faculty</option>
            <option value="student">Student</option>
          </select>
        </div>
        <div class="form-group" id="staffRefBox">
          <label class="form-label">Select Staff Member</label>
          <select name="reference_id" class="hrms-input">
            <?php foreach ($staff_options as $so): ?>
              <option value="<?php echo $so['id']; ?>"><?php echo htmlspecialchars($so['full_name']); ?> (<?php echo $so['employee_code']; ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="log_date" class="hrms-input" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="hrms-input">
              <option value="Present">Present</option>
              <option value="Absent">Absent</option>
              <option value="Late">Late</option>
              <option value="Leave">On Leave</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Check-In Time</label>
          <input type="time" name="check_in" class="hrms-input" value="07:30">
        </div>
        <div class="form-group">
          <label class="form-label">Remarks</label>
          <input type="text" name="remarks" class="hrms-input" value="Manual admin punch">
        </div>
        <button type="submit" class="login-submit-btn">
          <i class="fa-solid fa-check"></i> Save Attendance
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
