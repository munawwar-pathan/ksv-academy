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
$active_session = get_active_session();
$session_id = $active_session['id'] ?? 1;
$school_settings = get_school_settings();

// Get Staff Profile Info
$stmt = $db->prepare("SELECT * FROM staff_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$current_user['id']]);
$staff_profile = $stmt->fetch();

// If user is staff without profile, create default fallback
if (!$staff_profile) {
    $emp_code = 'KSV-FAC-0' . $current_user['id'];
    $stmt = $db->prepare("INSERT INTO staff_profiles (user_id, employee_code, department, designation, joining_date, monthly_salary, today_status) VALUES (?, ?, 'Academics', 'Faculty Member', CURDATE(), 60000, 'Present')");
    $stmt->execute([$current_user['id'], $emp_code]);
    $stmt_sp = $db->prepare("SELECT * FROM staff_profiles WHERE user_id = ?");
    $stmt_sp->execute([$current_user['id']]);
    $staff_profile = $stmt_sp->fetch();
}

$staff_id = $staff_profile['id'];

// Helper for faculty photo detection
if (!function_exists('get_staff_photo_url')) {
    function get_staff_photo_url($sm) {
        if (!empty($sm['photo']) && $sm['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../../' . ltrim($sm['photo'], '/'))) {
            return $sm['photo'];
        }
        if (!empty($sm['avatar'])) {
            if (file_exists(__DIR__ . '/../../assets/imgs/faculties/' . $sm['avatar'])) {
                return '/assets/imgs/faculties/' . $sm['avatar'];
            }
            if (file_exists(__DIR__ . '/../../assets/imgs/' . $sm['avatar'])) {
                return '/assets/imgs/' . $sm['avatar'];
            }
        }
        $lower = strtolower($sm['full_name'] ?? '');
        if (strpos($lower, 'salam') !== false || strpos($lower, 'director') !== false) {
            return '/assets/imgs/faculties/founder-director.png';
        }
        if (strpos($lower, 'mishra') !== false || strpos($lower, 'rameshwar') !== false) {
            return '/assets/imgs/faculties/mr-rameshwar-mishra.png';
        }
        if (strpos($lower, 'aman') !== false) {
            return '/assets/imgs/faculties/mr-aman-kumar-soni.png';
        }
        if (strpos($lower, 'lubna') !== false || strpos($lower, 'priya') !== false) {
            return '/assets/imgs/faculties/lubna-surve.png';
        }
        return '';
    }
}

$faculty_photo = get_staff_photo_url(array_merge($staff_profile, [
    'full_name' => $current_user['full_name'],
    'avatar' => $current_user['avatar'] ?? ''
]));

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

// Monthly Attendance Metrics
$cur_year_month = date('Y-m');
$stmt_month_att = $db->prepare("SELECT COUNT(*) FROM attendance_logs WHERE type = 'staff' AND reference_id = ? AND log_date LIKE ? AND status = 'Present'");
$stmt_month_att->execute([$staff_id, $cur_year_month . '%']);
$month_present_days = intval($stmt_month_att->fetchColumn());

// Leave Metrics
$stmt_l_appr = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE staff_id = ? AND status = 'Approved'");
$stmt_l_appr->execute([$staff_id]);
$approved_leaves_count = intval($stmt_l_appr->fetchColumn());

$stmt_l_pend = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE staff_id = ? AND status = 'Pending'");
$stmt_l_pend->execute([$staff_id]);
$pending_leaves_count = intval($stmt_l_pend->fetchColumn());

$total_allowed_leaves = 12; // Standard annual allocation
$available_leaves = max(0, $total_allowed_leaves - $approved_leaves_count);

// Fetch My Leave Requests
$my_leaves = $db->prepare("SELECT * FROM leave_requests WHERE staff_id = ? ORDER BY id DESC LIMIT 5");
$my_leaves->execute([$staff_id]);
$leave_records = $my_leaves->fetchAll();

// Fetch My Payslips
$my_payroll = $db->prepare("
    SELECT p.*, sp.employee_code, sp.department, sp.designation, sp.photo as staff_photo, u.full_name, u.username, u.avatar, u.phone, u.email
    FROM payroll p
    JOIN staff_profiles sp ON p.staff_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE p.staff_id = ?
    ORDER BY p.year DESC, p.id DESC
    LIMIT 4
");
$my_payroll->execute([$staff_id]);
$payroll_slips = $my_payroll->fetchAll(PDO::FETCH_ASSOC);

// Fetch Announcements for Staff
$notices = $db->query("SELECT * FROM announcements WHERE target_role IN ('all', 'staff') ORDER BY id DESC LIMIT 4")->fetchAll();

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Simple & Sober Dashboard Styles ── */
.sober-hero-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}
.sober-profile-wrap {
  display: flex;
  align-items: center;
  gap: 16px;
}
.sober-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  color: #334155;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  font-weight: 700;
  font-family: 'Outfit', sans-serif;
  overflow: hidden;
  flex-shrink: 0;
}
.sober-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.sober-name {
  font-family: 'Outfit', sans-serif;
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  line-height: 1.3;
}
.sober-badges-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 6px;
}
.sober-hero-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

/* 4 Sober Stat Cards */
.sober-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}
.sober-stat-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 16px 18px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.sober-stat-title {
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.sober-stat-title i {
  color: #0284c7;
  font-size: 0.85rem;
}
.sober-stat-value {
  font-family: 'Outfit', sans-serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: #0f172a;
  margin-top: 6px;
  line-height: 1.2;
}
.sober-stat-footer {
  font-size: 0.76rem;
  color: #64748b;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* Card Containers & Grids */
.dashboard-grid-2 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 420px), 1fr));
  gap: 20px;
  margin-bottom: 20px;
}
.dashboard-grid-2 > * {
  min-width: 0;
  max-width: 100%;
}
.hrms-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
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
  gap: 10px;
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

/* Digital Punch Area (Simple & Sober) */
.punch-card-body {
  padding: 20px;
  text-align: center;
}
.sober-clock-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
  text-align: center;
}
.sober-clock-digits {
  font-family: 'Outfit', sans-serif;
  font-size: 2.2rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1;
}
.sober-punch-status-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 18px;
}
.sober-punch-cell {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 14px;
  text-align: left;
}
.sober-punch-cell.check-in {
  border-left: 3px solid #10b981;
}
.sober-punch-cell.check-out {
  border-left: 3px solid #0284c7;
}

/* Admin Punch Notice */
.sober-admin-notice {
  margin-top: 14px;
  font-size: 0.78rem;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  padding: 10px 16px;
  border-radius: 8px;
}

/* Impersonation Banner */
.impersonation-notice-bar {
  background: #0284c7;
  color: #ffffff;
  padding: 10px 18px;
  border-radius: 8px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* Notice & Modal Grids */
.notice-card-body {
  padding: 16px 20px;
}
.modal-date-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 14px;
}

/* Table Responsive Details */
.table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  width: 100%;
}
.table-reason-cell {
  max-width: 160px;
  white-space: normal;
  word-break: break-word;
  line-height: 1.35;
}

/* Sober Buttons */
.btn-sober-primary {
  background: #0284c7;
  color: #ffffff;
  border: 1px solid #0284c7;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  transition: all 0.15s ease;
}
.btn-sober-primary:hover {
  background: #0369a1;
  border-color: #0369a1;
  color: #ffffff;
}
.btn-sober-secondary {
  background: #ffffff;
  color: #334155;
  border: 1px solid #cbd5e1;
  padding: 8px 14px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  transition: all 0.15s ease;
}
.btn-sober-secondary:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Smaller Laptops (<= 860px) */
@media (max-width: 860px) {
  .dashboard-grid-2 {
    grid-template-columns: 1fr;
    gap: 16px;
    margin-bottom: 16px;
  }
}

/* Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  .sober-hero-card {
    padding: 16px 18px;
    margin-bottom: 16px;
    flex-direction: column;
    align-items: stretch;
    gap: 14px;
  }

  .sober-hero-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
  }

  .sober-hero-actions .btn-sober-primary,
  .sober-hero-actions .btn-sober-secondary {
    flex: 1;
    justify-content: center;
    text-align: center;
    padding: 9px 12px;
    font-size: 0.80rem;
    white-space: nowrap;
  }

  .sober-stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 16px;
  }

  .sober-stat-card {
    padding: 14px 14px;
    border-radius: 8px;
  }

  .sober-stat-title {
    font-size: 0.70rem;
  }

  .sober-stat-value {
    font-size: 1.22rem;
    margin-top: 4px;
  }

  .sober-stat-footer {
    font-size: 0.70rem;
    margin-top: 6px;
    padding-top: 6px;
  }
}

/* Mobile Phones (<= 576px) */
@media (max-width: 576px) {
  .sober-hero-card {
    padding: 14px 14px;
    border-radius: 10px;
    gap: 12px;
  }

  .sober-profile-wrap {
    gap: 12px;
    align-items: center;
  }

  .sober-avatar {
    width: 48px;
    height: 48px;
    font-size: 1.2rem;
  }

  .sober-name {
    font-size: 1.10rem;
  }

  .sober-badges-row {
    gap: 5px;
    margin-top: 5px;
  }

  .sober-badges-row .sid-code-pill,
  .sober-badges-row .sid-dept-pill,
  .sober-badges-row .sid-status-verified {
    font-size: 0.68rem !important;
    padding: 2px 7px !important;
    line-height: 1.3 !important;
  }

  .sober-hero-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    width: 100%;
  }

  .sober-hero-actions .btn-sober-primary,
  .sober-hero-actions .btn-sober-secondary {
    padding: 8px 6px;
    font-size: 0.76rem;
    gap: 4px;
    width: 100%;
  }

  .sober-stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 14px;
  }

  .sober-stat-card {
    padding: 12px 12px;
  }

  .sober-stat-title {
    font-size: 0.67rem;
    letter-spacing: 0.02em;
  }

  .sober-stat-title i {
    font-size: 0.78rem;
  }

  .sober-stat-value {
    font-size: 1.15rem;
  }

  .sober-stat-footer {
    font-size: 0.68rem;
    flex-wrap: wrap;
    gap: 3px;
    margin-top: 6px;
    padding-top: 6px;
  }

  .hrms-card-header {
    padding: 11px 14px;
    gap: 8px;
  }

  .card-title {
    font-size: 0.88rem;
    gap: 6px;
  }

  .punch-card-body {
    padding: 14px 12px;
  }

  .sober-clock-box {
    padding: 12px 8px;
    margin-bottom: 12px;
    border-radius: 6px;
  }

  .sober-clock-digits {
    font-size: 1.65rem;
    letter-spacing: 0.5px;
  }

  .sober-clock-shift {
    font-size: 0.72rem !important;
  }

  .sober-punch-status-grid {
    gap: 8px;
    margin-bottom: 12px;
  }

  .sober-punch-cell {
    padding: 8px 10px;
    border-radius: 6px;
  }

  .sober-punch-label {
    font-size: 0.66rem !important;
  }

  .sober-punch-val {
    font-size: 0.86rem !important;
    word-break: break-word;
  }

  .sober-admin-notice {
    padding: 8px 12px;
    font-size: 0.72rem;
    text-align: left;
    align-items: flex-start;
    gap: 6px;
    line-height: 1.35;
  }

  .sober-admin-notice i {
    margin-top: 2px;
    flex-shrink: 0;
  }

  .hrms-table th {
    padding: 8px 8px;
    font-size: 0.68rem;
    white-space: nowrap;
  }

  .hrms-table td {
    padding: 8px 8px;
    font-size: 0.74rem;
  }

  .table-reason-cell {
    max-width: 130px;
    min-width: 80px;
    white-space: normal;
    word-break: break-word;
    line-height: 1.3;
  }

  .notice-card-body {
    padding: 12px 14px;
  }

  .notice-item {
    margin-bottom: 12px;
    padding-left: 10px;
  }

  .notice-title {
    font-size: 0.82rem !important;
  }

  .notice-time {
    font-size: 0.68rem !important;
  }

  .notice-desc {
    font-size: 0.76rem !important;
  }

  .impersonation-notice-bar {
    padding: 10px 12px;
    margin-bottom: 14px;
    font-size: 0.76rem;
    gap: 8px;
  }

  .hrms-modal-card {
    width: calc(100% - 20px) !important;
    margin: 12px auto !important;
  }

  .hrms-modal-header {
    padding: 12px 16px;
  }

  .hrms-modal-body {
    padding: 14px 14px;
  }
}

/* Extra Small Phones (<= 380px) */
@media (max-width: 380px) {
  .sober-hero-actions {
    grid-template-columns: 1fr;
  }

  .sober-hero-actions .btn-sober-primary,
  .sober-hero-actions .btn-sober-secondary {
    width: 100%;
    padding: 8px 10px;
    font-size: 0.78rem;
  }

  .sober-clock-digits {
    font-size: 1.45rem;
  }

  .sober-name {
    font-size: 1.02rem;
  }

  .modal-date-grid {
    grid-template-columns: 1fr;
    gap: 10px;
  }
}

/* Micro Phones (<= 330px) */
@media (max-width: 330px) {
  .sober-stats-grid {
    grid-template-columns: 1fr;
  }
}

/* ── Dark Mode Adaptations ── */
[data-theme="dark"] .sober-hero-card,
[data-theme="dark"] .sober-stat-card,
[data-theme="dark"] .hrms-card,
[data-theme="dark"] .hrms-card-header {
  background: #1e293b !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .sober-name,
[data-theme="dark"] .sober-stat-value,
[data-theme="dark"] .card-title,
[data-theme="dark"] .sober-clock-digits {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .sober-stat-title {
  color: #94a3b8 !important;
}

[data-theme="dark"] .sober-stat-footer {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .sober-stat-footer a {
  color: #38bdf8 !important;
}

[data-theme="dark"] .sober-avatar {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

/* Badges & Status Pills in Dark Mode */
[data-theme="dark"] .sid-code-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .sid-dept-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .sid-dept-pill i {
  color: #94a3b8 !important;
}

[data-theme="dark"] .sid-status-verified {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.35) !important;
  color: #34d399 !important;
}

[data-theme="dark"] .sid-status-active {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .sid-blood-badge {
  background: rgba(239, 68, 68, 0.15) !important;
  border-color: rgba(239, 68, 68, 0.35) !important;
  color: #f87171 !important;
}

[data-theme="dark"] .sober-stat-card .sid-code-pill,
[data-theme="dark"] .staff-id-table .sid-code-pill {
  background: rgba(5, 150, 105, 0.15) !important;
  border-color: rgba(5, 150, 105, 0.35) !important;
  color: #34d399 !important;
}

/* Digital Punch Area */
[data-theme="dark"] .sober-clock-box,
[data-theme="dark"] .sober-punch-cell {
  background: #0f172a !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .sober-clock-shift {
  color: #94a3b8 !important;
}

[data-theme="dark"] .sober-punch-label {
  color: #94a3b8 !important;
}

[data-theme="dark"] .sober-punch-val {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .sober-punch-cell.check-in .sober-punch-val {
  color: #34d399 !important;
}

[data-theme="dark"] .sober-punch-cell.check-out .sober-punch-val {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .sober-admin-notice {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

/* Buttons */
[data-theme="dark"] .btn-sober-secondary {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #e2e8f0 !important;
}

[data-theme="dark"] .btn-sober-secondary:hover {
  background: #334155 !important;
  border-color: #64748b !important;
  color: #ffffff !important;
}

/* Tables (Payroll & Leaves) */
[data-theme="dark"] .hrms-card-header span[style*="color: #64748b"],
[data-theme="dark"] .hrms-card-header span[style*="color:#64748b"] {
  color: #94a3b8 !important;
}

[data-theme="dark"] .hrms-card-header strong {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .hrms-table th,
[data-theme="dark"] .staff-id-table th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom: 1px solid #334155 !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .hrms-table td,
[data-theme="dark"] .staff-id-table td {
  border-bottom: 1px solid #334155 !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .hrms-table tbody tr:hover,
[data-theme="dark"] .staff-id-table tbody tr:hover {
  background-color: rgba(51, 65, 85, 0.3) !important;
}

[data-theme="dark"] .hrms-table td strong {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .table-reason-cell small {
  color: #94a3b8 !important;
}

/* Academy Circulars / Notices */
[data-theme="dark"] .notice-card-body {
  background: #1e293b !important;
}

[data-theme="dark"] .notice-item {
  background: #0f172a !important;
  border-color: #334155 !important;
  border-left: 3px solid #0284c7 !important;
}

[data-theme="dark"] .notice-item:hover {
  background: #162032 !important;
  border-color: #475569 !important;
  border-left-color: #38bdf8 !important;
}

[data-theme="dark"] .notice-title {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .notice-time {
  color: #94a3b8 !important;
}

[data-theme="dark"] .notice-desc {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .notice-list > p {
  color: #94a3b8 !important;
}

/* Apply Leave Modal */
[data-theme="dark"] #applyLeaveModal .hrms-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6) !important;
}

[data-theme="dark"] #applyLeaveModal .hrms-modal-header {
  border-bottom: 1px solid #334155 !important;
  background: #1e293b !important;
}

[data-theme="dark"] #applyLeaveModal .hrms-modal-header h3 {
  color: #f1f5f9 !important;
}

[data-theme="dark"] #applyLeaveModal .modal-close-btn {
  color: #94a3b8 !important;
  background: #0f172a !important;
  border: 1px solid #334155 !important;
}

[data-theme="dark"] #applyLeaveModal .modal-close-btn:hover {
  color: #f1f5f9 !important;
  background: #334155 !important;
}

[data-theme="dark"] #applyLeaveModal .hrms-modal-body {
  background: #1e293b !important;
}

[data-theme="dark"] #applyLeaveModal .form-label {
  color: #cbd5e1 !important;
}

[data-theme="dark"] #applyLeaveModal select.hrms-input,
[data-theme="dark"] #applyLeaveModal input.hrms-input,
[data-theme="dark"] #applyLeaveModal textarea.hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f1f5f9 !important;
}

[data-theme="dark"] #applyLeaveModal select.hrms-input option {
  background: #0f172a !important;
  color: #f1f5f9 !important;
}

[data-theme="dark"] #applyLeaveModal .hrms-input:focus {
  border-color: #0284c7 !important;
  outline: none;
}

/* Payslip View Modal Shell */
[data-theme="dark"] #payslipViewModal .receipt-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6) !important;
}

[data-theme="dark"] #payslipViewModal .hrms-modal-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] #payslipViewModal .hrms-modal-header h4 {
  color: #f1f5f9 !important;
}

[data-theme="dark"] #payslipViewModal #modalPayslipSub {
  color: #94a3b8 !important;
}

[data-theme="dark"] #payslipViewModal .modal-close-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] #payslipViewModal .modal-close-btn:hover {
  background: #334155 !important;
  color: #f1f5f9 !important;
}

[data-theme="dark"] #payslipModalBody {
  background: #0f172a !important;
}

[data-theme="dark"] #payslipViewModal .no-print[style*="border-top"],
[data-theme="dark"] #payslipViewModal div.no-print:last-child {
  background: #1e293b !important;
  border-top-color: #334155 !important;
}

[data-theme="dark"] #payslipViewModal button[onclick="printPayslip();"] {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #e2e8f0 !important;
}

[data-theme="dark"] #payslipViewModal button[onclick="printPayslip();"]:hover {
  background: #334155 !important;
  color: #ffffff !important;
}
</style>

<?php if (!empty($_SESSION['impersonated_staff'])): ?>
  <div class="impersonation-notice-bar">
    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem;">
      <i class="fa-solid fa-circle-user"></i>
      <span>Logged in via Admin Impersonation as Faculty: <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong> (<?php echo htmlspecialchars($staff_profile['employee_code']); ?>)</span>
    </div>
    <a href="<?php echo ADMIN_URL; ?>/staff" style="background: #ffffff; color: #0284c7; font-weight: 700; font-size: 0.76rem; padding: 4px 10px; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0;">
      <i class="fa-solid fa-arrow-left"></i> Return to Admin
    </a>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     1. SIMPLE & SOBER FACULTY BIO CARD
     ══════════════════════════════════════════════════════ -->
<div class="sober-hero-card">
  <div class="sober-profile-wrap">
    <div class="sober-avatar">
      <?php if (!empty($faculty_photo)): ?>
        <img src="<?php echo htmlspecialchars($faculty_photo); ?>" alt="<?php echo htmlspecialchars($current_user['full_name']); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
        <span style="display: none;"><?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?></span>
      <?php else: ?>
        <span><?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?></span>
      <?php endif; ?>
    </div>

    <div>
      <h2 class="sober-name"><?php echo htmlspecialchars($current_user['full_name']); ?></h2>
      <div class="sober-badges-row">
        <span class="sid-code-pill">
          <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
          <span><?php echo htmlspecialchars($staff_profile['employee_code']); ?></span>
        </span>
        <span class="sid-dept-pill">
          <i class="fa-solid fa-graduation-cap" style="font-size: 0.65rem; color: #64748b;"></i>
          <span><?php echo htmlspecialchars($staff_profile['designation']); ?> &bull; Dept of <?php echo htmlspecialchars($staff_profile['department']); ?></span>
        </span>
        <span class="sid-dept-pill">
          <i class="fa-regular fa-calendar" style="font-size: 0.65rem; color: #64748b;"></i>
          <span>Session <?php echo htmlspecialchars($active_session['session_name'] ?? CURRENT_SESSION); ?></span>
        </span>
        <span class="sid-status-verified">
          <i class="fa-solid fa-check" style="font-size: 0.68rem;"></i> Today: Present
        </span>
      </div>
    </div>
  </div>

  <div class="sober-hero-actions">
    <button type="button" class="btn-sober-primary" onclick="openModal('applyLeaveModal')">
      <i class="fa-solid fa-calendar-plus"></i> Apply for Leave
    </button>
    <a href="<?php echo STAFF_URL; ?>/staff-id" class="btn-sober-secondary" title="View official faculty identity card">
      <i class="fa-solid fa-id-card" style="color: #0284c7;"></i> Staff ID Card
    </a>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     2. FOUR SIMPLE & SOBER METRIC CARDS
     ══════════════════════════════════════════════════════ -->
<div class="sober-stats-grid">
  
  <!-- Stat 1: Monthly Attendance -->
  <div class="sober-stat-card">
    <div class="sober-stat-title">
      <span>Duty Attendance</span>
      <i class="fa-solid fa-calendar-check"></i>
    </div>
    <div class="sober-stat-value"><?php echo $month_present_days; ?> Days</div>
    <div class="sober-stat-footer">
      <span>Present in <?php echo date('F'); ?></span>
      <span class="sid-status-verified" style="font-size: 0.70rem; padding: 1px 7px;"><i class="fa-solid fa-check"></i> Regular</span>
    </div>
  </div>

  <!-- Stat 2: Leave Balance -->
  <div class="sober-stat-card">
    <div class="sober-stat-title">
      <span>Leave Balance</span>
      <i class="fa-solid fa-umbrella-beach" style="color: #d97706;"></i>
    </div>
    <div class="sober-stat-value"><?php echo $available_leaves; ?> Days</div>
    <div class="sober-stat-footer">
      <span><?php echo $approved_leaves_count; ?> Used &bull; <?php echo $pending_leaves_count; ?> Pending</span>
      <a href="javascript:void(0)" onclick="openModal('applyLeaveModal')" style="color: #0284c7; font-weight: 600; text-decoration: none;">+ Apply</a>
    </div>
  </div>

  <!-- Stat 3: Compensation -->
  <div class="sober-stat-card">
    <div class="sober-stat-title">
      <span>Monthly Salary</span>
      <i class="fa-solid fa-file-invoice-dollar" style="color: #059669;"></i>
    </div>
    <div class="sober-stat-value"><?php echo format_inr($staff_profile['monthly_salary'] ?? 60000); ?></div>
    <div class="sober-stat-footer">
      <span>Fixed Compensation</span>
      <span class="sid-code-pill" style="color: #059669; background: #ecfdf5; border-color: #a7f3d0; font-size: 0.72rem;">Bank NEFT</span>
    </div>
  </div>

  <!-- Stat 4: Biometric Pass -->
  <div class="sober-stat-card">
    <div class="sober-stat-title">
      <span>Biometric QR ID</span>
      <i class="fa-solid fa-qrcode" style="color: #7c3aed;"></i>
    </div>
    <div class="sober-stat-value" style="font-family: ui-monospace, monospace; font-size: 1.2rem; color: #0284c7;">
      <?php echo htmlspecialchars($staff_profile['employee_code']); ?>
    </div>
    <div class="sober-stat-footer">
      <span>Duty QR Pass</span>
      <a href="<?php echo STAFF_URL; ?>/staff-id" style="color: #0284c7; font-weight: 600; text-decoration: none;">View ID Card &rarr;</a>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     3. TWO COLUMNS: REAL-TIME BIOMETRIC PUNCH & PAYROLL
     ══════════════════════════════════════════════════════ -->
<div class="dashboard-grid-2">
  
  <!-- Digital Attendance Punch Box (Simple & Sober) -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title">
        <i class="fa-solid fa-clock-rotate-left"></i> Digital Attendance Punch
      </h2>
      <span class="sid-status-active" style="font-size: 0.72rem; padding: 2px 8px;">
        <span class="pulse-dot"></span> Biometric Online
      </span>
    </div>

    <div class="punch-card-body">
      
      <!-- Live Clock Display -->
      <div class="sober-clock-box">
        <div id="staffLiveClock" class="sober-clock-digits">
          <?php echo date('h:i:s A'); ?>
        </div>
        <div class="sober-clock-shift" style="font-size: 0.80rem; color: #64748b; margin-top: 4px; font-weight: 600;">
          <?php echo date('l, d F Y'); ?> (Shift: 7:30 AM – 3:30 PM)
        </div>
      </div>

      <!-- Current Status -->
      <div class="sober-punch-status-grid">
        <div class="sober-punch-cell check-in">
          <div class="sober-punch-label" style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Check-In</div>
          <div class="sober-punch-val" style="font-weight: 700; font-size: 1.05rem; color: <?php echo !empty($current_punch['check_in_time']) ? '#166534' : '#64748b'; ?>; margin-top: 2px;">
            <?php echo (!empty($current_punch['check_in_time'])) ? format_time($current_punch['check_in_time']) : '— Not Yet —'; ?>
          </div>
        </div>

        <div class="sober-punch-cell check-out">
          <div class="sober-punch-label" style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Check-Out</div>
          <div class="sober-punch-val" style="font-weight: 700; font-size: 1.05rem; color: #0f172a; margin-top: 2px;">
            <?php echo (!empty($current_punch['check_out_time'])) ? format_time($current_punch['check_out_time']) : '— In Progress —'; ?>
          </div>
        </div>
      </div>

      <!-- Managed From Admin Side Notice -->
      <div class="sober-admin-notice">
        <i class="fa-solid fa-shield-halved" style="color: #0284c7; font-size: 0.85rem;"></i>
        <span>Attendance punches are recorded &amp; managed directly by the Administrator</span>
      </div>

    </div>
  </div>

  <!-- Faculty Monthly Performance & Salary Overview -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <div>
        <h2 class="card-title">
          <i class="fa-solid fa-file-invoice-dollar"></i> Salary Slips &amp; Payroll
        </h2>
        <span style="font-size: 0.78rem; color: #64748b;">Fixed Monthly: <strong><?php echo format_inr($staff_profile['monthly_salary'] ?? 60000); ?></strong></span>
      </div>
      <a href="<?php echo STAFF_URL; ?>/payroll" class="btn-sober-secondary" style="padding: 5px 10px; font-size: 0.76rem;">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> All Slips
      </a>
    </div>

    <div class="table-responsive">
      <table class="hrms-table staff-id-table">
        <thead>
          <tr>
            <th>Month / Year</th>
            <th>Net Amount</th>
            <th>Status</th>
            <th style="text-align: right;">Slip</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payroll_slips)): ?>
            <tr>
              <td colspan="4" style="text-align: center; color: #64748b; padding: 24px;">No payroll records found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($payroll_slips as $ps): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($ps['month']); ?> <?php echo $ps['year']; ?></strong></td>
                <td>
                  <span class="sid-code-pill" style="color: #047857; background: #ecfdf5; border-color: #a7f3d0; font-size: 0.78rem;">
                    <?php echo format_inr($ps['net_salary']); ?>
                  </span>
                </td>
                <td>
                  <span class="sid-status-verified">
                    <i class="fa-solid fa-check" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($ps['payment_status']); ?>
                  </span>
                </td>
                <td style="text-align: right;">
                  <button type="button" class="btn-sober-secondary" style="padding: 4px 10px; font-size: 0.76rem;" onclick="previewPayslip(<?php echo htmlspecialchars(json_encode($ps)); ?>);" title="View, Print & Download Salary Receipt">
                    <i class="fa-solid fa-receipt"></i> View Receipt
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     4. SECOND GRID: LEAVE APPLICATION STATUS & NOTICES
     ══════════════════════════════════════════════════════ -->
<div class="dashboard-grid-2">
  
  <!-- My Leave Applications -->
  <div class="hrms-card">
    <div class="hrms-card-header">
      <h2 class="card-title">
        <i class="fa-solid fa-calendar-check"></i> My Leave Applications
      </h2>
      <button type="button" class="btn-sober-primary" onclick="openModal('applyLeaveModal')" style="padding: 5px 12px; font-size: 0.78rem;">
        + Apply Leave
      </button>
    </div>

    <div class="table-responsive">
      <table class="hrms-table staff-id-table">
        <thead>
          <tr>
            <th>Leave Type</th>
            <th>Dates</th>
            <th>Reason</th>
            <th style="text-align: right;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($leave_records)): ?>
            <tr>
              <td colspan="4" style="text-align: center; color: #64748b; padding: 24px;">No leave applications found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($leave_records as $lr): ?>
              <tr>
                <td>
                  <span class="sid-dept-pill">
                    <?php echo htmlspecialchars($lr['leave_type']); ?>
                  </span>
                </td>
                <td><?php echo format_date($lr['start_date'], 'd M'); ?> – <?php echo format_date($lr['end_date'], 'd M Y'); ?></td>
                <td class="table-reason-cell"><small style="color: #64748b;"><?php echo htmlspecialchars($lr['reason']); ?></small></td>
                <td style="text-align: right;">
                  <?php 
                    $stat = $lr['status'];
                    if ($stat === 'Approved'): ?>
                      <span class="sid-status-verified"><i class="fa-solid fa-check"></i> Approved</span>
                    <?php elseif ($stat === 'Rejected'): ?>
                      <span class="sid-blood-badge"><i class="fa-solid fa-xmark"></i> Rejected</span>
                    <?php else: ?>
                      <span class="sid-status-active"><span class="pulse-dot"></span> Pending</span>
                    <?php endif; ?>
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
      <h2 class="card-title">
        <i class="fa-solid fa-bullhorn"></i> Academy Circulars
      </h2>
    </div>
    <div class="hrms-card-body notice-card-body">
      <div class="notice-list">
        <?php foreach ($notices as $not): ?>
          <div class="notice-item" style="border-left: 3px solid #0284c7; padding-left: 12px; margin-bottom: 14px;">
            <div class="notice-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
              <span class="notice-title" style="font-weight: 700; color: #0f172a; font-size: 0.86rem;"><?php echo htmlspecialchars($not['title']); ?></span>
              <span class="notice-time" style="font-size: 0.72rem; color: #64748b;"><?php echo format_date($not['created_at'], 'd M'); ?></span>
            </div>
            <p class="notice-desc" style="font-size: 0.80rem; color: #475569; margin: 0; line-height: 1.4;"><?php echo htmlspecialchars($not['content']); ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- Modal: Apply Leave -->
<div class="hrms-modal" id="applyLeaveModal">
  <div class="hrms-modal-card" style="border-radius: 10px; max-width: 460px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 16px 20px;">
      <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-calendar-plus" style="color: #0284c7;"></i> Apply for Leave
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('applyLeaveModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo STAFF_URL; ?>/dashboard">
      <input type="hidden" name="action" value="apply_leave">
      <div class="hrms-modal-body" style="padding: 20px;">
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-weight: 600; font-size: 0.80rem; color: #334155; margin-bottom: 4px; display: block;">Leave Type</label>
          <select name="leave_type" class="hrms-input" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
            <option value="Casual Leave">Casual Leave (CL)</option>
            <option value="Medical Leave">Medical Leave (ML)</option>
            <option value="Academic Duty">Academic / Examination Duty</option>
            <option value="Emergency Leave">Emergency Leave</option>
          </select>
        </div>
        <div class="modal-date-grid">
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; font-size: 0.80rem; color: #334155; margin-bottom: 4px; display: block;">Start Date</label>
            <input type="date" name="start_date" class="hrms-input" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1;" required>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; font-size: 0.80rem; color: #334155; margin-bottom: 4px; display: block;">End Date</label>
            <input type="date" name="end_date" class="hrms-input" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1;" required>
          </div>
        </div>
        <div class="form-group" style="margin-bottom: 18px;">
          <label class="form-label" style="font-weight: 600; font-size: 0.80rem; color: #334155; margin-bottom: 4px; display: block;">Reason</label>
          <textarea name="reason" class="hrms-input" rows="3" placeholder="Provide reason for leave..." style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1;" required></textarea>
        </div>
        <button type="submit" class="login-submit-btn" style="background: #0284c7; color: #ffffff; border: none; padding: 10px; border-radius: 6px; font-weight: 700; font-size: 0.88rem; width: 100%; cursor: pointer;">
          <i class="fa-solid fa-paper-plane"></i> Submit Application to Director
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Simple & Sober Live Clock
function updateStaffLiveClock() {
  const clockEl = document.getElementById('staffLiveClock');
  if (!clockEl) return;
  const now = new Date();
  let hours = now.getHours();
  const minutes = String(now.getMinutes()).padStart(2, '0');
  const seconds = String(now.getSeconds()).padStart(2, '0');
  const ampm = hours >= 12 ? 'PM' : 'AM';
  hours = hours % 12;
  hours = hours ? String(hours).padStart(2, '0') : '12';
  clockEl.textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
}
setInterval(updateStaffLiveClock, 1000);
</script>

<?php require_once __DIR__ . '/../includes/payslip-modal.php'; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
