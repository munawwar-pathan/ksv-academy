<?php
require_once __DIR__ . '/../config.php';

// Ensure user is super_admin
$current_user = require_login(['super_admin']);

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session ? intval($active_session['id']) : 1;

$page_title = 'Super Admin Dashboard';
$page_heading = 'Admin Dashboard';
$page_subheading = 'Campus Overview • Academic Session ' . htmlspecialchars($active_session['session_name'] ?? '2025-2026');
$active_menu = 'dashboard';

// Handle Quick Action Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Approve or Reject Leave
    if ($action === 'update_leave') {
        $leave_id = intval($_POST['leave_id'] ?? 0);
        $status = in_array($_POST['status'], ['Approved', 'Rejected']) ? $_POST['status'] : 'Pending';
        if ($leave_id > 0) {
            $stmt = $db->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $leave_id]);
            set_flash('success', "Leave request #{$leave_id} marked as {$status}.");
            header('Location: ' . ADMIN_URL . '/dashboard');
            exit;
        }
    }

    // 2. Add New Staff Member
    if ($action === 'add_staff') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? 'staff');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $salary = floatval($_POST['salary'] ?? 50000);

        if (!empty($full_name) && !empty($username)) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, role, email, phone, full_name, status) VALUES (?, ?, 'staff', ?, ?, ?, 'active')");
                $stmt->execute([$username, $hash, $email, $phone, $full_name]);
                $new_user_id = $db->lastInsertId();

                $emp_code = 'KSV-FAC-0' . rand(10, 99);
                $stmt2 = $db->prepare("INSERT INTO staff_profiles (user_id, session_id, employee_code, department, designation, joining_date, monthly_salary, today_status) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'Present')");
                $stmt2->execute([$new_user_id, $session_id, $emp_code, $department, $designation, $salary]);

                set_flash('success', "Faculty member {$full_name} ({$emp_code}) added successfully!");
                header('Location: ' . ADMIN_URL . '/dashboard');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Error creating staff: ' . $e->getMessage());
            }
        }
    }

    // 3. Post Announcement
    if ($action === 'post_announcement') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $target = trim($_POST['target_role'] ?? 'all');
        if (!empty($title) && !empty($content)) {
            $stmt = $db->prepare("INSERT INTO announcements (title, content, target_role, posted_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $target, $current_user['full_name']]);
            set_flash('success', 'Announcement published successfully!');
            header('Location: ' . ADMIN_URL . '/dashboard');
            exit;
        }
    }
}

// Fetch Key Dashboard Metrics (Prepared Statements)
$stmt_staff = $db->prepare("SELECT COUNT(*) FROM staff_profiles WHERE session_id = ?");
$stmt_staff->execute([$session_id]);
$total_staff = intval($stmt_staff->fetchColumn());

$stmt_pstaff = $db->prepare("SELECT COUNT(*) FROM staff_profiles WHERE session_id = ? AND today_status = 'Present'");
$stmt_pstaff->execute([$session_id]);
$present_staff = intval($stmt_pstaff->fetchColumn());

$stmt_stu = $db->prepare("SELECT COUNT(*) FROM students WHERE session_id = ?");
$stmt_stu->execute([$session_id]);
$total_students = intval($stmt_stu->fetchColumn());

$stmt_fee = $db->prepare("SELECT SUM(total_fees) as total, SUM(paid_fees) as paid FROM students WHERE session_id = ?");
$stmt_fee->execute([$session_id]);
$fee_stats = $stmt_fee->fetch();
$total_fees = floatval($fee_stats['total'] ?? 0);
$paid_fees = floatval($fee_stats['paid'] ?? 0);
$pending_leaves = intval($db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn());

// Fetch Today's Staff Attendance Roster
$staff_roster = $db->query("
    SELECT sp.*, u.full_name, u.phone, u.email, al.check_in_time, al.check_out_time, al.status as punch_status
    FROM staff_profiles sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN attendance_logs al ON al.reference_id = sp.id AND al.type = 'staff' AND al.log_date = CURDATE()
    ORDER BY sp.id ASC
")->fetchAll();

// Fetch Pending Leaves (requiring action)
$pending_leave_list = $db->query("
    SELECT lr.*, u.full_name, sp.department, sp.designation
    FROM leave_requests lr
    JOIN staff_profiles sp ON lr.staff_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE lr.status = 'Pending'
    ORDER BY lr.id DESC
    LIMIT 5
")->fetchAll();

// Fetch Recent Processed Leaves (history/audit trail)
$recent_leave_history = $db->query("
    SELECT lr.*, u.full_name, sp.department, sp.designation
    FROM leave_requests lr
    JOIN staff_profiles sp ON lr.staff_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE lr.status != 'Pending'
    ORDER BY lr.id DESC
    LIMIT 4
")->fetchAll();

// Fetch Recent Fee Collections
$recent_payments = $db->query("
    SELECT fp.*, s.student_name, s.standard, s.roll_no
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    ORDER BY fp.id DESC
    LIMIT 6
")->fetchAll();

// Fetch Announcements
$announcements = $db->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 4")->fetchAll();

// Computed Metrics for Visual Depth & Readability
$staff_attendance_pct = $total_staff > 0 ? round(($present_staff / $total_staff) * 100) : 0;
$fee_pct = $total_fees > 0 ? round(($paid_fees / $total_fees) * 100) : 0;
$pending_fees = max(0, $total_fees - $paid_fees);

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Dashboard Tables (Simple & Sober like Staff ID) ── */
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
  padding: 10px 12px;
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

/* Dashboard Card Containers */
.dashboard-grid-2 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}
.hrms-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
  background: #ffffff;
  overflow: hidden;
}
.hrms-card-header {
  border-bottom: 1px solid #e2e8f0;
  padding: 13px 18px;
  background: #ffffff;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.card-title {
  margin: 0;
  font-size: 0.94rem;
  font-weight: 700;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
  display: flex;
  align-items: center;
  gap: 8px;
}
.card-title i {
  color: #0284c7;
  font-size: 0.88rem;
}

/* Chips and Badges */
.receipt-tag {
  font-family: ui-monospace, monospace;
  font-size: 0.75rem;
  font-weight: 600;
  background: #f8fafc;
  color: #475569;
  border: 1px solid #e2e8f0;
  padding: 2px 7px;
  border-radius: 4px;
  display: inline-block;
}
.badge-dept {
  display: inline-flex;
  align-items: center;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 5px;
  background: #f8fafc;
  color: #475569;
  border: 1px solid #e2e8f0;
}
.badge-dept.science, .badge-dept.math {
  background: #f0f9ff;
  color: #0284c7;
  border-color: #bae6fd;
}
.badge-dept.admin {
  background: #faf5ff;
  color: #7c3aed;
  border-color: #e9d5ff;
}
.badge-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 5px;
}
.badge-status.badge-present {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}
.badge-status.badge-absent {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecdd3;
}
.badge-status.badge-leave {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fde68a;
}
.badge-status.badge-approved {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}
.badge-status.badge-rejected {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecdd3;
}
.badge-payment {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.73rem;
  font-weight: 600;
  color: #0284c7;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  padding: 2px 8px;
  border-radius: 5px;
}
.pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
}
.badge-live-pulse {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.72rem;
  font-weight: 600;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  border-radius: 5px;
  padding: 2px 7px;
}
.user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}
.cell-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #f0f9ff;
  color: #0284c7;
  border: 1px solid #bae6fd;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.8rem;
  flex-shrink: 0;
}
.cell-name {
  font-weight: 700;
  color: #0f172a;
  font-size: 0.86rem;
  font-family: 'Outfit', sans-serif;
  line-height: 1.25;
}
.cell-sub {
  font-size: 0.72rem;
  color: #64748b;
  margin-top: 2px;
}

/* ══════════════════════════════════════════════════════
   DARK MODE ADAPTATIONS FOR ADMIN DASHBOARD
   ══════════════════════════════════════════════════════ */
[data-theme="dark"] .stat-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .stat-label {
  color: #94a3b8 !important;
}

[data-theme="dark"] .stat-number {
  color: #f8fafc !important;
}

[data-theme="dark"] .stat-card-footer {
  color: #94a3b8 !important;
  border-top-color: #334155 !important;
}

[data-theme="dark"] .stat-card-footer strong {
  color: #f8fafc !important;
}

[data-theme="dark"] .stat-card-link {
  color: #38bdf8 !important;
}

[data-theme="dark"] .hrms-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .hrms-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .card-title {
  color: #f8fafc !important;
}

[data-theme="dark"] .card-title i {
  color: #38bdf8 !important;
}

/* Header Action Outline Buttons */
[data-theme="dark"] .btn-hrms-outline {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f1f5f9 !important;
}

[data-theme="dark"] .btn-hrms-outline:hover {
  background: #24344d !important;
  border-color: #0284c7 !important;
  color: #ffffff !important;
}

/* Table Search Box */
[data-theme="dark"] .table-search-box {
  background: #0f172a !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .table-search-box input {
  color: #f8fafc !important;
}

[data-theme="dark"] .table-search-box:focus-within {
  background: #162032 !important;
  border-color: #0284c7 !important;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.2) !important;
}

/* Table Headings & Cells */
[data-theme="dark"] .staff-id-table thead th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .staff-id-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .staff-id-table tbody tr:hover {
  background-color: #24344d !important;
}

[data-theme="dark"] td[colspan] {
  color: #94a3b8 !important;
}

/* Faculty Cell & Avatars */
[data-theme="dark"] .cell-avatar {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .cell-name {
  color: #f8fafc !important;
}

[data-theme="dark"] .cell-sub {
  color: #94a3b8 !important;
}

[data-theme="dark"] .cell-sub span[style*="font-family"] {
  color: #94a3b8 !important;
}

/* Department & Category Badges */
[data-theme="dark"] .badge-dept {
  background: #0f172a !important;
  color: #cbd5e1 !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .badge-dept.science,
[data-theme="dark"] .badge-dept.math {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
}

[data-theme="dark"] .badge-dept.admin {
  background: rgba(124, 58, 237, 0.15) !important;
  color: #c084fc !important;
  border-color: rgba(124, 58, 237, 0.35) !important;
}

/* Status Badges */
[data-theme="dark"] .badge-status.badge-present,
[data-theme="dark"] .badge-status.badge-approved {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.35) !important;
}

[data-theme="dark"] .badge-status.badge-absent,
[data-theme="dark"] .badge-status.badge-rejected {
  background: rgba(239, 68, 68, 0.15) !important;
  color: #f87171 !important;
  border-color: rgba(239, 68, 68, 0.35) !important;
}

[data-theme="dark"] .badge-status.badge-leave {
  background: rgba(245, 158, 11, 0.15) !important;
  color: #fbbf24 !important;
  border-color: rgba(245, 158, 11, 0.35) !important;
}

[data-theme="dark"] .badge-live-pulse {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.35) !important;
}

/* Table Text Overrides */
[data-theme="dark"] td span[style*="color:#0f172a"],
[data-theme="dark"] td span[style*="color: #0f172a"],
[data-theme="dark"] td div[style*="color: #0f172a"],
[data-theme="dark"] td div[style*="color:#0f172a"] {
  color: #f8fafc !important;
}

[data-theme="dark"] td div[style*="color: #475569"],
[data-theme="dark"] td div[style*="color:#475569"],
[data-theme="dark"] td div[style*="color: #64748b"],
[data-theme="dark"] td div[style*="color:#64748b"],
[data-theme="dark"] td span[style*="color: #475569"],
[data-theme="dark"] td span[style*="color:#475569"] {
  color: #94a3b8 !important;
}

/* Leave Requests Section */
[data-theme="dark"] .hrms-card-header span[style*="background:#fef3c7"],
[data-theme="dark"] .hrms-card-header span[style*="background: #fef3c7"] {
  background: rgba(245, 158, 11, 0.15) !important;
  color: #fbbf24 !important;
  border-color: rgba(245, 158, 11, 0.35) !important;
}

[data-theme="dark"] .hrms-card-header span[style*="background:#ecfdf5"],
[data-theme="dark"] .hrms-card-header span[style*="background: #ecfdf5"] {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.35) !important;
}

[data-theme="dark"] .hrms-card-body > div[style*="background: #fffbeb"],
[data-theme="dark"] .hrms-card-body > div[style*="background:#fffbeb"] {
  background: rgba(245, 158, 11, 0.12) !important;
  border-bottom-color: rgba(245, 158, 11, 0.25) !important;
  color: #fbbf24 !important;
}

[data-theme="dark"] .hrms-card-body > div[style*="background: #f8fafc"],
[data-theme="dark"] .hrms-card-body > div[style*="background:#f8fafc"] {
  background: #0f172a !important;
  border-top-color: #334155 !important;
  border-bottom-color: #334155 !important;
  color: #94a3b8 !important;
}

/* Recent Fee Collections Section */
[data-theme="dark"] .receipt-tag {
  background: #0f172a !important;
  color: #38bdf8 !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .badge-payment {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
}

[data-theme="dark"] td strong[style*="color: #059669"],
[data-theme="dark"] td strong[style*="color:#059669"] {
  color: #34d399 !important;
}

/* Circulars & Notices Section */
[data-theme="dark"] .notice-item {
  background: #0f172a !important;
  border-color: #334155 !important;
  border-left-color: #0284c7 !important;
}

[data-theme="dark"] .notice-item:hover {
  background: #162032 !important;
  border-color: #475569 !important;
  border-left-color: #38bdf8 !important;
}

[data-theme="dark"] .notice-title {
  color: #f8fafc !important;
}

[data-theme="dark"] .notice-time {
  color: #94a3b8 !important;
}

[data-theme="dark"] .notice-desc {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .notice-item > div[style*="border-top"] {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .notice-item > div[style*="border-top"] i.fa-user {
  color: #38bdf8 !important;
}

/* Modals */
[data-theme="dark"] .hrms-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
}

[data-theme="dark"] .hrms-modal-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .hrms-modal-header h3 {
  color: #f8fafc !important;
}

[data-theme="dark"] .hrms-modal-header .modal-close-btn {
  color: #94a3b8 !important;
}

[data-theme="dark"] .hrms-modal-header .modal-close-btn:hover {
  color: #f8fafc !important;
}

[data-theme="dark"] .form-label {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}

[data-theme="dark"] .hrms-input:focus {
  border-color: #0284c7 !important;
}
</style>

<!-- Executive Overview Bar (Simple & Sober) -->
<div class="admin-welcome-ribbon">
  <div class="welcome-ribbon-content">
    <div class="welcome-meta-badge">
      <i class="fa-solid fa-graduation-cap"></i>
      <span>Session <?php echo htmlspecialchars($active_session['session_name']); ?> &bull; Active Campus</span>
    </div>
    <h2 class="welcome-title"><?php echo htmlspecialchars($current_user['full_name']); ?> &mdash; Executive Workspace</h2>
    <p class="welcome-subtitle">
      Real-time campus administration: daily biometric attendance, admissions, student fees, and institutional circulars.
    </p>
  </div>
  <div class="welcome-ribbon-actions">
    <button type="button" class="ribbon-btn primary" onclick="openModal('addStaffModal')">
      <i class="fa-solid fa-user-plus"></i> Add Faculty
    </button>
    <a href="<?php echo ADMIN_URL; ?>/fees" class="ribbon-btn outline">
      <i class="fa-solid fa-receipt"></i> Collect Fee
    </a>
    <button type="button" class="ribbon-btn outline" onclick="openModal('announcementModal')">
      <i class="fa-solid fa-bullhorn"></i> New Circular
    </button>
    <a href="<?php echo ADMIN_URL; ?>/expense-report" class="ribbon-btn outline">
      <i class="fa-solid fa-chart-pie"></i> Reports
    </a>
  </div>
</div>

<!-- KPI Stat Cards - Simple, Sober & Proper Readable -->
<div class="stat-grid">
  
  <!-- Faculty & Staff -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Faculty &amp; Staff</span>
          <span class="stat-number"><?php echo $total_staff; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-chalkboard-user"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $staff_attendance_pct; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $present_staff; ?></strong> Present Today (<?php echo $staff_attendance_pct; ?>%)</span>
      <a href="<?php echo ADMIN_URL; ?>/staff" class="stat-card-link">View All <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>

  <!-- Enrolled Students -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Enrolled Students</span>
          <span class="stat-number"><?php echo $total_students; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-user-graduate"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>5 Academic Standards (8th &ndash; 12th)</span>
      <a href="<?php echo ADMIN_URL; ?>/students" class="stat-card-link">Directory <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>

  <!-- Fee Collection -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Fee Collection</span>
          <span class="stat-number"><?php echo format_inr($paid_fees); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $fee_pct; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $fee_pct; ?>%</strong> of <?php echo format_inr($total_fees); ?></span>
      <a href="<?php echo ADMIN_URL; ?>/fees" class="stat-card-link">Ledger <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>

  <!-- Leave Approvals -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Leave Approvals</span>
          <span class="stat-number"><?php echo $pending_leaves; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-calendar-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $pending_leaves > 0 ? '80%' : '0%'; ?>;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><?php echo $pending_leaves > 0 ? '<strong style="color:#d97706;">Action Required (' . $pending_leaves . ')</strong>' : '<span style="color:#059669;"><i class="fa-solid fa-check"></i> All Cleared</span>'; ?></span>
      <a href="<?php echo ADMIN_URL; ?>/leaves" class="stat-card-link">Review <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>

</div>

<!-- Main Split Section 1: Staff Attendance & Leave Requests -->
<div class="dashboard-grid-2">
  
  <!-- Left Column: Today's Staff Attendance & Biometrics -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <div style="display:flex; align-items:center; gap:10px;">
        <h2 class="card-title"><i class="fa-solid fa-user-clock"></i> Faculty &amp; Staff Attendance</h2>
        <span class="badge-live-pulse" style="font-size:0.72rem; padding:2px 8px;"><span class="pulse-dot"></span> Live Log</span>
      </div>
      <div style="display:flex; align-items:center; gap:8px;">
        <div class="table-search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="staffFilterInput" placeholder="Filter faculty..." onkeyup="filterStaffTable()">
        </div>
        <a href="<?php echo ADMIN_URL; ?>/staff-attendance" class="btn-hrms btn-hrms-outline btn-hrms-sm">Full Roster</a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="hrms-table staff-id-table" id="staffAttendanceTable">
        <thead>
          <tr>
            <th style="min-width: 200px;">Faculty Member</th>
            <th style="min-width: 120px;">Department</th>
            <th style="min-width: 110px;">Check-In</th>
            <th style="min-width: 110px; text-align: center;">Today's Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($staff_roster)): ?>
            <tr>
              <td colspan="4" style="text-align:center; padding:28px; color:#64748b;">No staff profiles registered in this session.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($staff_roster as $st): ?>
              <tr>
                <td>
                  <div class="user-cell">
                    <div class="cell-avatar"><?php echo strtoupper(substr($st['full_name'], 0, 1)); ?></div>
                    <div class="cell-meta">
                      <span class="cell-name"><?php echo htmlspecialchars($st['full_name']); ?></span>
                      <span class="cell-sub">
                        <span style="font-family:ui-monospace,monospace; font-weight:600; color:#475569;"><?php echo htmlspecialchars($st['employee_code'] ?? 'KSV-FAC'); ?></span> &bull; 
                        <?php echo htmlspecialchars($st['designation'] ?? 'Lecturer'); ?>
                      </span>
                    </div>
                  </div>
                </td>
                <td>
                  <?php 
                    $dept = htmlspecialchars($st['department'] ?? 'Academics');
                    $dept_cls = 'science';
                    if (stripos($dept, 'Math') !== false) $dept_cls = 'math';
                    elseif (stripos($dept, 'Admin') !== false || stripos($dept, 'Office') !== false) $dept_cls = 'admin';
                  ?>
                  <span class="badge-dept <?php echo $dept_cls; ?>">
                    <?php echo $dept; ?>
                  </span>
                </td>
                <td>
                  <?php if ($st['check_in_time']): ?>
                    <span style="font-weight:600; color:#0f172a; display:inline-flex; align-items:center; gap:5px; font-variant-numeric: tabular-nums;">
                      <i class="fa-regular fa-clock" style="color:#059669; font-size:0.8rem;"></i> <?php echo format_time($st['check_in_time']); ?>
                    </span>
                  <?php else: ?>
                    <span style="color:#94a3b8; font-weight:500; font-size:0.82rem; display:inline-flex; align-items:center; gap:5px;">
                      <i class="fa-regular fa-clock" style="font-size:0.8rem;"></i> &mdash;
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align: center;">
                  <?php 
                    $stat = $st['today_status'] ?? 'Present';
                    $badge_class = 'badge-present';
                    if ($stat === 'Absent') $badge_class = 'badge-absent';
                    elseif ($stat === 'On Leave' || $stat === 'Leave') $badge_class = 'badge-leave';
                  ?>
                  <span class="badge-status <?php echo $badge_class; ?>">
                    <span class="pulse-dot"></span> <?php echo htmlspecialchars($stat); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Column: Faculty Leave Requests & Audit -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <div style="display:flex; align-items:center; gap:8px;">
        <h2 class="card-title"><i class="fa-solid fa-calendar-check"></i> Leave Requests</h2>
        <?php if ($pending_leaves > 0): ?>
          <span style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-size:0.72rem; font-weight:600; padding:2px 8px; border-radius:12px;">
            <?php echo $pending_leaves; ?> Pending Action
          </span>
        <?php else: ?>
          <span style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-size:0.72rem; font-weight:600; padding:2px 8px; border-radius:12px;">
            <i class="fa-solid fa-check"></i> Queue Clear
          </span>
        <?php endif; ?>
      </div>
      <a href="<?php echo ADMIN_URL; ?>/leaves" class="btn-hrms btn-hrms-outline btn-hrms-sm">View All Leaves</a>
    </div>
    <div class="hrms-card-body" style="padding: 0;">
      
      <!-- Pending Leaves Requiring Action -->
      <?php if (!empty($pending_leave_list)): ?>
        <div style="padding: 10px 16px; background: #fffbeb; border-bottom: 1px solid #fef3c7; font-size: 0.76rem; font-weight: 600; color: #92400e; display: flex; align-items: center; gap: 6px;">
          <i class="fa-solid fa-circle-exclamation"></i> Action Required on Pending Requests
        </div>
        <div class="table-responsive">
          <table class="hrms-table staff-id-table">
            <thead>
              <tr>
                <th style="min-width: 170px;">Applicant</th>
                <th style="min-width: 110px;">Window</th>
                <th style="text-align: right; min-width: 130px;">Decision</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending_leave_list as $lv): ?>
                <tr>
                  <td>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.86rem; font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars($lv['full_name']); ?></div>
                    <div style="font-size: 0.72rem; color: #64748b; margin-top: 1px;">
                      <?php echo htmlspecialchars($lv['leave_type']); ?> &bull; <?php echo htmlspecialchars($lv['department'] ?? 'Faculty'); ?>
                    </div>
                    <?php if (!empty($lv['reason'])): ?>
                      <div style="font-size: 0.72rem; color: #475569; margin-top: 2px; font-style: italic;">
                        &ldquo;<?php echo htmlspecialchars(mb_strimwidth($lv['reason'], 0, 50, '...')); ?>&rdquo;
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="font-weight: 600; color: #0f172a; font-size: 0.8rem; display: flex; align-items: center; gap: 5px; font-variant-numeric: tabular-nums; white-space: nowrap;">
                      <i class="fa-regular fa-calendar-days" style="color: #0284c7; font-size: 0.76rem;"></i>
                      <?php echo format_date($lv['start_date'], 'd M'); ?> &ndash; <?php echo format_date($lv['end_date'], 'd M'); ?>
                    </div>
                  </td>
                  <td style="text-align: right; white-space: nowrap;">
                    <div style="display: flex; gap: 5px; justify-content: flex-end; align-items: center;">
                      <form method="POST" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="update_leave">
                        <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                        <input type="hidden" name="status" value="Approved">
                        <button type="submit" class="btn-hrms btn-hrms-success btn-hrms-sm" title="Approve Request" style="padding: 4px 9px; font-size: 0.74rem;">
                          <i class="fa-solid fa-check"></i> Approve
                        </button>
                      </form>
                      <form method="POST" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="update_leave">
                        <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                        <input type="hidden" name="status" value="Rejected">
                        <button type="submit" class="btn-hrms btn-hrms-danger btn-hrms-sm" title="Reject Request" style="padding: 4px 8px; font-size: 0.74rem;">
                          <i class="fa-solid fa-xmark"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- Recent Reviewed Leaves (History/Audit) -->
      <?php if (!empty($recent_leave_history)): ?>
        <?php if (!empty($pending_leave_list)): ?>
          <div style="padding: 8px 16px; background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;">
            Recent Reviewed Leaves
          </div>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="hrms-table staff-id-table">
            <thead>
              <tr>
                <th style="min-width: 170px;">Applicant</th>
                <th style="min-width: 110px;">Window</th>
                <th style="text-align: right; min-width: 90px;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_leave_history as $lv): ?>
                <tr>
                  <td>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.86rem; font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars($lv['full_name']); ?></div>
                    <div style="font-size: 0.72rem; color: #64748b; margin-top: 1px;">
                      <?php echo htmlspecialchars($lv['leave_type']); ?> &bull; <?php echo htmlspecialchars($lv['department'] ?? 'Faculty'); ?>
                    </div>
                    <?php if (!empty($lv['reason'])): ?>
                      <div style="font-size: 0.72rem; color: #64748b; margin-top: 2px;">
                        <?php echo htmlspecialchars(mb_strimwidth($lv['reason'], 0, 45, '...')); ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="font-weight: 600; color: #0f172a; font-size: 0.8rem; display: flex; align-items: center; gap: 5px; font-variant-numeric: tabular-nums; white-space: nowrap;">
                      <i class="fa-regular fa-calendar-days" style="color: #0284c7; font-size: 0.76rem;"></i>
                      <?php echo format_date($lv['start_date'], 'd M'); ?> &ndash; <?php echo format_date($lv['end_date'], 'd M'); ?>
                    </div>
                  </td>
                  <td style="text-align: right;">
                    <span class="badge-status <?php echo $lv['status'] === 'Approved' ? 'badge-approved' : 'badge-rejected'; ?>" style="font-size: 0.72rem; padding: 2px 8px;">
                      <?php echo $lv['status']; ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php elseif (empty($pending_leave_list)): ?>
        <div style="padding: 36px 20px; text-align: center;">
          <div style="width: 40px; height: 40px; border-radius: 50%; background: #f0fdf4; color: #10b981; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.1rem; border: 1px solid #bbf7d0;">
            <i class="fa-solid fa-check"></i>
          </div>
          <h4 style="font-size: 0.9rem; font-weight: 700; color: #0f172a; margin-bottom: 2px; font-family: 'Outfit', sans-serif;">No Active Leave Applications</h4>
          <p style="font-size: 0.78rem; color: #64748b; margin: 0;">All staff leave requests are up to date.</p>
        </div>
      <?php endif; ?>

    </div>
  </div>

</div>

<!-- Main Split Section 2: Recent Collections & Announcements -->
<div class="dashboard-grid-2">
  
  <!-- Recent Fee Collections -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-money-bill-wave"></i> Recent Fee Collections</h2>
      <a href="<?php echo ADMIN_URL; ?>/fees" class="btn-hrms btn-hrms-outline btn-hrms-sm">Fee Management</a>
    </div>
    <div class="table-responsive">
      <table class="hrms-table staff-id-table">
        <thead>
          <tr>
            <th style="width: 105px;">Receipt #</th>
            <th style="min-width: 180px;">Student Name</th>
            <th style="min-width: 110px;">Amount Paid</th>
            <th style="min-width: 120px;">Payment Mode</th>
            <th style="min-width: 110px; text-align: right;">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent_payments)): ?>
            <tr>
              <td colspan="5" style="text-align: center; padding: 28px; color: #64748b;">No recent fee payments logged.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($recent_payments as $fp): ?>
              <tr>
                <td>
                  <span class="receipt-tag">#<?php echo htmlspecialchars($fp['receipt_no']); ?></span>
                </td>
                <td>
                  <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem; font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars($fp['student_name']); ?></div>
                  <div style="font-size: 0.72rem; color: #64748b; margin-top: 1px;">
                    <?php echo htmlspecialchars($fp['standard']); ?> &bull; Roll: #<?php echo htmlspecialchars($fp['roll_no'] ?? '—'); ?>
                  </div>
                </td>
                <td>
                  <strong style="color: #059669; font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                    <?php echo format_inr($fp['amount']); ?>
                  </strong>
                </td>
                <td style="white-space: nowrap;">
                  <?php 
                    $mode = strtolower($fp['payment_method'] ?? 'cash');
                    $m_icon = 'fa-money-bill-1';
                    if (strpos($mode, 'upi') !== false || strpos($mode, 'online') !== false) $m_icon = 'fa-mobile-screen-button';
                    elseif (strpos($mode, 'bank') !== false || strpos($mode, 'net') !== false) $m_icon = 'fa-building-columns';
                    elseif (strpos($mode, 'cheque') !== false) $m_icon = 'fa-money-check';
                    elseif (strpos($mode, 'card') !== false) $m_icon = 'fa-credit-card';
                  ?>
                  <span class="badge-payment">
                    <i class="fa-solid <?php echo $m_icon; ?>"></i>
                    <?php echo htmlspecialchars($fp['payment_method']); ?>
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <span style="font-size: 0.8rem; font-weight: 500; color: #475569; display: inline-flex; align-items: center; gap: 5px; justify-content: flex-end; font-variant-numeric: tabular-nums;">
                    <i class="fa-regular fa-calendar" style="color: #94a3b8; font-size: 0.76rem;"></i>
                    <?php echo format_date($fp['payment_date'], 'd M Y'); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Academy Announcements -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title"><i class="fa-solid fa-bullhorn"></i> Circulars &amp; Notices</h2>
      <button type="button" class="btn-hrms btn-hrms-outline btn-hrms-sm" onclick="openModal('announcementModal')">+ Publish Notice</button>
    </div>
    <div class="hrms-card-body">
      <div class="notice-list">
        <?php if (empty($announcements)): ?>
          <p style="text-align:center; color:#64748b; padding:20px;">No notices published yet.</p>
        <?php else: ?>
          <?php foreach ($announcements as $ann): ?>
            <div class="notice-item">
              <div class="notice-header">
                <span class="notice-title"><?php echo htmlspecialchars($ann['title']); ?></span>
                <span class="notice-time">
                  <i class="fa-regular fa-calendar" style="color:#94a3b8; margin-right:3px;"></i>
                  <?php echo format_date($ann['created_at'], 'd M Y'); ?>
                </span>
              </div>
              <p class="notice-desc">
                <?php echo htmlspecialchars($ann['content']); ?>
              </p>
              <div style="display:flex; align-items:center; justify-content:space-between; font-size:0.74rem; color:#64748b; padding-top:8px; border-top:1px solid #f1f5f9;">
                <span><i class="fa-regular fa-user" style="color:#0284c7; margin-right:4px;"></i> <?php echo htmlspecialchars($ann['posted_by']); ?></span>
                <span class="badge-dept" style="text-transform:capitalize; font-size:0.7rem;">
                  Audience: <?php echo htmlspecialchars($ann['target_role']); ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- Interactive Client Search Script -->
<script>
function filterStaffTable() {
  const input = document.getElementById('staffFilterInput');
  const filter = input.value.toLowerCase();
  const table = document.getElementById('staffAttendanceTable');
  const tr = table.getElementsByTagName('tr');

  for (let i = 1; i < tr.length; i++) {
    const tdName = tr[i].getElementsByTagName('td')[0];
    const tdDept = tr[i].getElementsByTagName('td')[1];
    if (tdName || tdDept) {
      const nameText = tdName ? tdName.textContent || tdName.innerText : '';
      const deptText = tdDept ? tdDept.textContent || tdDept.innerText : '';
      if (nameText.toLowerCase().indexOf(filter) > -1 || deptText.toLowerCase().indexOf(filter) > -1) {
        tr[i].style.display = '';
      } else {
        tr[i].style.display = 'none';
      }
    }
  }
}
</script>

<!-- Modal 1: Add Faculty Member -->
<div class="hrms-modal" id="addStaffModal">
  <div class="hrms-modal-card">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; font-family:'Plus Jakarta Sans',sans-serif;"><i class="fa-solid fa-user-plus" style="color: #0284c7; margin-right:6px;"></i> Register Faculty &amp; Staff</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('addStaffModal')" aria-label="Close modal"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo ADMIN_URL; ?>/dashboard">
      <input type="hidden" name="action" value="add_staff">
      <div class="hrms-modal-body">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="hrms-input" placeholder="e.g. Dr. Rajesh Kulkarni" required>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="hrms-input" placeholder="e.g. rajesh.k" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="hrms-input" value="staff" required>
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Department</label>
            <input type="text" name="department" class="hrms-input" placeholder="e.g. Physics / Mathematics" required>
          </div>
          <div class="form-group">
            <label class="form-label">Designation</label>
            <input type="text" name="designation" class="hrms-input" placeholder="e.g. Senior Faculty" required>
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="hrms-input" placeholder="+91 98200 00000">
          </div>
          <div class="form-group">
            <label class="form-label">Monthly Salary (₹)</label>
            <input type="number" name="salary" class="hrms-input" value="55000">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="hrms-input" placeholder="faculty@ksvacademy.net">
        </div>
        <button type="submit" class="login-submit-btn" style="margin-top: 14px; background:#0284c7;">
          <i class="fa-solid fa-check"></i> Register Faculty Member
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 2: Post Announcement -->
<div class="hrms-modal" id="announcementModal">
  <div class="hrms-modal-card">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; font-family:'Plus Jakarta Sans',sans-serif;"><i class="fa-solid fa-bullhorn" style="color: #0284c7; margin-right:6px;"></i> Broadcast Circular</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('announcementModal')" aria-label="Close modal"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo ADMIN_URL; ?>/dashboard">
      <input type="hidden" name="action" value="post_announcement">
      <div class="hrms-modal-body">
        <div class="form-group">
          <label class="form-label">Notice Headline</label>
          <input type="text" name="title" class="hrms-input" placeholder="e.g. Sunday Mock Test Timing" required>
        </div>
        <div class="form-group">
          <label class="form-label">Target Audience</label>
          <select name="target_role" class="hrms-input">
            <option value="all">Everyone (All Faculty &amp; Parents)</option>
            <option value="staff">Faculty &amp; Staff Only</option>
            <option value="parent">Parents &amp; Students Only</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Notice Message Details</label>
          <textarea name="content" class="hrms-input" rows="4" placeholder="Enter notice content here..." required></textarea>
        </div>
        <button type="submit" class="login-submit-btn" style="background:#0284c7;">
          <i class="fa-solid fa-paper-plane"></i> Publish Circular
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
