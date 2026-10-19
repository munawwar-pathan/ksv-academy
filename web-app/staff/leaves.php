<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
$page_title = 'Leave Management';
$page_heading = 'Faculty Leave Applications';
$page_subheading = 'Leave Entitlements, Requests & Approvals';
$active_menu = 'leaves';

$db = get_db_connection();

// Handle Approve / Reject (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $leave_id = intval($_POST['leave_id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    if ($leave_id > 0 && in_array($status, ['Approved', 'Rejected', 'Pending'])) {
        $db->prepare("UPDATE leave_requests SET status = ? WHERE id = ?")->execute([$status, $leave_id]);
        set_flash('success', "Leave request #{$leave_id} set to {$status}.");
        header('Location: ' . STAFF_URL . '/leaves');
        exit;
    }
}

// Handle Apply Leave (Staff)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_leave') {
    $staff = $db->query("SELECT id FROM staff_profiles WHERE user_id = {$current_user['id']}")->fetch();
    if ($staff) {
        $ltype = trim($_POST['leave_type'] ?? 'Casual Leave');
        $sdate = trim($_POST['start_date'] ?? date('Y-m-d'));
        $edate = trim($_POST['end_date'] ?? date('Y-m-d'));
        $reason = trim($_POST['reason'] ?? '');
        $stmt = $db->prepare("INSERT INTO leave_requests (staff_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$staff['id'], $ltype, $sdate, $edate, $reason]);
        set_flash('success', 'Leave application submitted for approval.');
        header('Location: ' . STAFF_URL . '/leaves');
        exit;
    }
}

// Query leaves
if ($current_user['role'] === 'super_admin') {
    $leaves = $db->query("
        SELECT lr.*, u.full_name, sp.employee_code, sp.department, sp.designation
        FROM leave_requests lr
        JOIN staff_profiles sp ON lr.staff_id = sp.id
        JOIN users u ON sp.user_id = u.id
        ORDER BY lr.id DESC
    ")->fetchAll();
} else {
    $stmt = $db->prepare("
        SELECT lr.*, u.full_name, sp.employee_code, sp.department, sp.designation
        FROM leave_requests lr
        JOIN staff_profiles sp ON lr.staff_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE u.id = ?
        ORDER BY lr.id DESC
    ");
    $stmt->execute([$current_user['id']]);
    $leaves = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
  <div>
    <span style="font-size: 0.9rem; color: var(--hrms-text-muted); font-weight: 600;">
      Total Leave Applications: <strong><?php echo count($leaves); ?></strong>
    </span>
  </div>
  <?php if ($current_user['role'] === 'staff'): ?>
    <button type="button" class="btn-hrms btn-hrms-primary" onclick="openModal('applyLeaveModal')">
      <i class="fa-solid fa-calendar-plus"></i> Apply for Leave
    </button>
  <?php endif; ?>
</div>

<div class="hrms-card">
  <div class="hrms-card-header">
    <h2 class="card-title"><i class="fa-solid fa-plane-departure"></i> Leave Applications History</h2>
  </div>
  <div class="table-responsive">
    <table class="hrms-table">
      <thead>
        <tr>
          <th>Applicant</th>
          <th>Leave Type</th>
          <th>Period</th>
          <th>Reason</th>
          <th>Application Date</th>
          <th>Status</th>
          <?php if ($current_user['role'] === 'super_admin'): ?>
            <th>Decision Action</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($leaves)): ?>
          <tr><td colspan="7" style="text-align: center; padding: 30px; color: var(--hrms-text-muted);">No leave requests found.</td></tr>
        <?php else: ?>
          <?php foreach ($leaves as $lr): ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($lr['full_name']); ?></strong><br>
                <small style="color: var(--hrms-text-muted);"><?php echo htmlspecialchars($lr['employee_code']); ?> &bull; <?php echo htmlspecialchars($lr['department']); ?></small>
              </td>
              <td><strong><?php echo htmlspecialchars($lr['leave_type']); ?></strong></td>
              <td><?php echo format_date($lr['start_date']); ?> to <?php echo format_date($lr['end_date']); ?></td>
              <td><small><?php echo htmlspecialchars($lr['reason']); ?></small></td>
              <td><?php echo format_date($lr['created_at']); ?></td>
              <td>
                <span class="badge-status <?php echo ($lr['status'] === 'Approved') ? 'badge-approved' : (($lr['status'] === 'Rejected') ? 'badge-rejected' : 'badge-pending'); ?>">
                  <?php echo $lr['status']; ?>
                </span>
              </td>
              <?php if ($current_user['role'] === 'super_admin'): ?>
                <td>
                  <?php if ($lr['status'] === 'Pending'): ?>
                    <div style="display: flex; gap: 6px;">
                      <form method="POST">
                        <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                        <input type="hidden" name="status" value="Approved">
                        <button type="submit" class="btn-hrms btn-hrms-success btn-hrms-sm"><i class="fa-solid fa-check"></i> Approve</button>
                      </form>
                      <form method="POST">
                        <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                        <input type="hidden" name="status" value="Rejected">
                        <button type="submit" class="btn-hrms btn-hrms-danger btn-hrms-sm"><i class="fa-solid fa-xmark"></i> Reject</button>
                      </form>
                    </div>
                  <?php else: ?>
                    <small style="color: var(--hrms-text-muted);">Decided</small>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Apply Leave for Staff -->
<div class="hrms-modal" id="applyLeaveModal">
  <div class="hrms-modal-card">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--hrms-navy);"><i class="fa-solid fa-calendar-plus" style="color: var(--hrms-primary);"></i> Apply for Leave</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('applyLeaveModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo STAFF_URL; ?>/leaves">
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
          <i class="fa-solid fa-paper-plane"></i> Submit Application
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
