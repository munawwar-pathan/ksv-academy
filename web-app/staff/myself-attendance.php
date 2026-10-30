<?php
require_once __DIR__ . '/../config.php';

// Ensure user is staff or admin
$current_user = require_login(['staff', 'super_admin']);
require_menu_permission('myself-attendance');

$page_title = 'MySelf Attendance Register';
$page_heading = 'My Attendance Register';
$page_subheading = 'Monthly Presence Ledger, Daily Biometric In/Out Timings & Punch History';
$active_menu = 'myself-attendance';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'] ?? 1;

// Resolve Staff Profile Info
$stmt = $db->prepare("SELECT * FROM staff_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$current_user['id']]);
$staff_profile = $stmt->fetch();

if (!$staff_profile) {
    $emp_code = 'KSV-FAC-0' . $current_user['id'];
    $stmt = $db->prepare("INSERT INTO staff_profiles (user_id, employee_code, department, designation, joining_date, monthly_salary, today_status) VALUES (?, ?, 'Academics', 'Faculty Member', CURDATE(), 60000, 'Present')");
    $stmt->execute([$current_user['id'], $emp_code]);
    $staff_profile = $db->query("SELECT * FROM staff_profiles WHERE user_id = {$current_user['id']}")->fetch();
}

$staff_id = $staff_profile['id'];

// Handle Biometric Quick Punch In / Punch Out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'punch_in') {
        $now_time = date('H:i:s');
        $today = date('Y-m-d');
        $chk = $db->prepare("SELECT id FROM attendance_logs WHERE type = 'staff' AND reference_id = ? AND log_date = ?");
        $chk->execute([$staff_id, $today]);
        $existing = $chk->fetch();

        if ($existing) {
            $upd = $db->prepare("UPDATE attendance_logs SET check_in_time = ?, status = 'Present' WHERE id = ?");
            $upd->execute([$now_time, $existing['id']]);
        } else {
            $ins = $db->prepare("INSERT INTO attendance_logs (session_id, type, reference_id, log_date, status, check_in_time, remarks) VALUES (?, 'staff', ?, ?, 'Present', ?, 'Self biometric punch-in')");
            $ins->execute([$session_id, $staff_id, $today, $now_time]);
        }

        $db->prepare("UPDATE staff_profiles SET today_status = 'Present' WHERE id = ?")->execute([$staff_id]);
        set_flash('success', "Biometric Punch In recorded at " . date('h:i A') . "!");
        header('Location: ' . STAFF_URL . '/myself-attendance?month=' . urlencode($_GET['month'] ?? date('Y-m')));
        exit;
    }

    if ($action === 'punch_out') {
        $now_time = date('H:i:s');
        $today = date('Y-m-d');
        $upd = $db->prepare("UPDATE attendance_logs SET check_out_time = ? WHERE type = 'staff' AND reference_id = ? AND log_date = ?");
        $upd->execute([$now_time, $staff_id, $today]);
        set_flash('success', "Biometric Punch Out recorded at " . date('h:i A') . "!");
        header('Location: ' . STAFF_URL . '/myself-attendance?month=' . urlencode($_GET['month'] ?? date('Y-m')));
        exit;
    }
}

// Selected Month setup (defaults to current month YYYY-MM)
$selected_month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = date('Y-m');
}

$status_filter = strtolower(trim($_GET['status'] ?? 'all'));
if (!in_array($status_filter, ['all', 'present', 'absent', 'late', 'leave', 'weekly_off'])) {
    $status_filter = 'all';
}

$year = intval(substr($selected_month, 0, 4));
$month = intval(substr($selected_month, 5, 2));
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$start_date = "{$selected_month}-01";
$end_date = sprintf("%s-%02d", $selected_month, $days_in_month);
$today_str = date('Y-m-d');

// Previous and Next month URLs
$prev_month = date('Y-m', strtotime("{$start_date} -1 month"));
$next_month = date('Y-m', strtotime("{$start_date} +1 month"));

// Fetch all attendance logs for this staff in the month
$log_stmt = $db->prepare("
    SELECT * FROM attendance_logs 
    WHERE type = 'staff' AND reference_id = ? AND log_date BETWEEN ? AND ?
    ORDER BY log_date ASC
");
$log_stmt->execute([$staff_id, $start_date, $end_date]);
$month_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

$logs_by_date = [];
foreach ($month_logs as $l) {
    $logs_by_date[$l['log_date']] = $l;
}

// Fetch approved leaves in this month
$leave_stmt = $db->prepare("
    SELECT * FROM leave_requests 
    WHERE staff_id = ? AND status = 'Approved' 
      AND (start_date <= ? AND end_date >= ?)
");
$leave_stmt->execute([$staff_id, $end_date, $start_date]);
$month_leaves = $leave_stmt->fetchAll(PDO::FETCH_ASSOC);

// Map approved leaves by date
$approved_leaves_by_date = [];
foreach ($month_leaves as $lv) {
    $cur = strtotime($lv['start_date']);
    $last = strtotime($lv['end_date']);
    while ($cur <= $last) {
        $d_str = date('Y-m-d', $cur);
        if ($d_str >= $start_date && $d_str <= $end_date) {
            $approved_leaves_by_date[$d_str] = $lv;
        }
        $cur = strtotime('+1 day', $cur);
    }
}

// Build monthly ledger array
$attendance_calendar = [];
$total_present = 0;
$total_absent = 0;
$total_late = 0;
$total_leave = 0;
$total_weekly_off = 0;
$total_working_days_passed = 0;

for ($d = 1; $d <= $days_in_month; $d++) {
    $cur_date = sprintf("%s-%02d", $selected_month, $d);
    $cur_time = strtotime($cur_date);
    $day_name = date('l', $cur_time);
    $is_sunday = (date('w', $cur_time) == 0);
    $is_past = ($cur_date < $today_str);
    $is_today = ($cur_date === $today_str);
    $is_future = ($cur_date > $today_str);

    $log = $logs_by_date[$cur_date] ?? null;
    $leave = $approved_leaves_by_date[$cur_date] ?? null;

    $status = 'Upcoming';
    $badge_class = 'sid-status-upcoming';
    $check_in = '—';
    $check_out = '—';
    $duration = '—';
    $remarks = '';

    if ($log) {
        $status = $log['status'];
        $check_in = !empty($log['check_in_time']) ? date('h:i A', strtotime($log['check_in_time'])) : '—';
        $check_out = !empty($log['check_out_time']) ? date('h:i A', strtotime($log['check_out_time'])) : '—';
        $remarks = $log['remarks'] ?? '';

        if (!empty($log['check_in_time']) && !empty($log['check_out_time'])) {
            $diff = strtotime($log['check_out_time']) - strtotime($log['check_in_time']);
            if ($diff > 0) {
                $hours = floor($diff / 3600);
                $mins = floor(($diff % 3600) / 60);
                $duration = "{$hours}h {$mins}m";
            }
        } elseif (!empty($log['check_in_time']) && $is_today) {
            $duration = 'On Duty (Active)';
        }

        if ($status === 'Present') {
            $total_present++;
        } elseif ($status === 'Late') {
            $total_late++;
        } elseif ($status === 'Absent') {
            $total_absent++;
        } elseif ($status === 'Leave') {
            $total_leave++;
        }
        if (!$is_sunday && ($is_past || $is_today)) {
            $total_working_days_passed++;
        }
    } elseif ($leave) {
        $status = 'Leave';
        $remarks = "Approved " . htmlspecialchars($leave['leave_type']);
        $total_leave++;
        if (!$is_sunday && ($is_past || $is_today)) {
            $total_working_days_passed++;
        }
    } elseif ($is_sunday) {
        $status = 'Weekly Off';
        $remarks = 'Sunday Holiday';
        $total_weekly_off++;
    } elseif ($is_past) {
        $status = 'Absent';
        $remarks = 'Auto Absent (Unpunched)';
        $total_absent++;
        $total_working_days_passed++;
    } elseif ($is_today) {
        $status = 'Not Checked In';
        $remarks = 'Pending punch for today';
        $total_working_days_passed++;
    } else {
        $status = 'Upcoming';
        $remarks = 'Scheduled academic working day';
    }

    $attendance_calendar[] = [
        'day' => $d,
        'date' => $cur_date,
        'day_name' => $day_name,
        'is_sunday' => $is_sunday,
        'is_today' => $is_today,
        'is_past' => $is_past,
        'is_future' => $is_future,
        'status' => $status,
        'check_in' => $check_in,
        'check_out' => $check_out,
        'duration' => $duration,
        'remarks' => $remarks
    ];
}

// Attendance Rate calculation
$attended_days = $total_present + $total_late;
$attendance_rate = $total_working_days_passed > 0 ? round(($attended_days / $total_working_days_passed) * 100, 1) : 0;

// Filter list if status filter is applied
$filtered_calendar = array_filter($attendance_calendar, function($row) use ($status_filter) {
    if ($status_filter === 'all') return true;
    if ($status_filter === 'present') return in_array($row['status'], ['Present', 'Late']);
    if ($status_filter === 'absent') return $row['status'] === 'Absent';
    if ($status_filter === 'late') return $row['status'] === 'Late';
    if ($status_filter === 'leave') return $row['status'] === 'Leave';
    if ($status_filter === 'weekly_off') return $row['status'] === 'Weekly Off';
    return true;
});

// Today's log for quick punch terminal
$today_log = $db->prepare("SELECT * FROM attendance_logs WHERE type = 'staff' AND reference_id = ? AND log_date = CURDATE() LIMIT 1");
$today_log->execute([$staff_id]);
$current_punch = $today_log->fetch();

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Myself Attendance Register Styles ── */
.attendance-register-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #ffffff;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
  margin-bottom: 30px;
}
.attendance-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  background: #ffffff;
}
.attendance-header-info {
  flex: 1;
  min-width: 240px;
}
.attendance-header-title {
  margin: 0;
  font-size: 0.98rem;
  font-weight: 700;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.attendance-header-title i {
  color: #0284c7;
}
.month-records-badge {
  font-size: 0.74rem;
  font-weight: 600;
  background: #f8fafc;
  color: #475569;
  border: 1px solid #e2e8f0;
  padding: 2px 8px;
  border-radius: 6px;
}
.attendance-header-subtitle {
  margin: 3px 0 0;
  font-size: 0.77rem;
  color: #64748b;
  line-height: 1.35;
}
.attendance-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.month-nav-group {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.month-picker-control {
  display: inline-flex;
  align-items: center;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 3px;
}
.month-picker-control a {
  background: transparent;
  color: #475569;
  border: none;
  padding: 5px 10px;
  font-size: 0.78rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  border-radius: 4px;
  transition: all 0.15s ease;
}
.month-picker-control a:hover {
  background: #e2e8f0;
  color: #0f172a;
}
.month-picker-control input[type="month"] {
  border: none;
  background: transparent;
  font-weight: 700;
  font-size: 0.85rem;
  color: #0f172a;
  padding: 4px 8px;
  cursor: pointer;
  outline: none;
  font-family: inherit;
}
.today-punch-form {
  margin: 0;
  display: inline-flex;
}
.today-punch-form button {
  white-space: nowrap;
}

/* Table Responsive Details */
.attendance-table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  width: 100%;
}
.attendance-table {
  width: 100%;
  border-collapse: collapse;
}
.attendance-table th {
  padding: 10px 12px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.attendance-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.attendance-table .col-num {
  width: 48px;
  text-align: center;
  font-weight: 700;
  color: #475569;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.attendance-table .col-date {
  min-width: 120px;
}
.attendance-table .col-checkin,
.attendance-table .col-checkout {
  min-width: 110px;
}
.attendance-table .col-duration {
  min-width: 100px;
}
.attendance-table .col-status {
  min-width: 130px;
}
.attendance-table .col-remarks {
  min-width: 150px;
  max-width: 240px;
  word-break: break-word;
}
.attendance-table .today-row {
  background: #f0f9ff;
}

/* Table Footer Summary */
.attendance-footer-summary {
  padding: 12px 20px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 0.78rem;
  color: #64748b;
}
.footer-summary-stats {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  .attendance-stat-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
    margin-bottom: 16px !important;
  }
  .attendance-card-header {
    padding: 14px 16px;
    gap: 10px;
  }
  .attendance-header-actions {
    width: 100%;
    justify-content: space-between;
  }
}

/* Mobile Phones (<= 576px) */
@media (max-width: 576px) {
  .attendance-stat-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 8px !important;
    margin-bottom: 14px !important;
  }
  .attendance-stat-grid .stat-card {
    padding: 10px 12px !important;
    border-radius: 10px !important;
  }
  .attendance-stat-grid .stat-label {
    font-size: 0.64rem !important;
  }
  .attendance-stat-grid .stat-number {
    font-size: 1.20rem !important;
  }
  .attendance-stat-grid .stat-icon-wrapper {
    width: 28px !important;
    height: 28px !important;
    min-width: 28px !important;
    font-size: 0.76rem !important;
  }
  .attendance-stat-grid .stat-card-footer {
    font-size: 0.65rem !important;
    margin-top: 6px !important;
    padding-top: 5px !important;
    flex-wrap: wrap !important;
    gap: 2px !important;
  }

  .attendance-register-card {
    border-radius: 8px;
    margin-bottom: 20px;
  }
  .attendance-card-header {
    padding: 12px 14px;
    gap: 10px;
  }
  .attendance-header-info {
    min-width: 100%;
  }
  .attendance-header-title {
    font-size: 0.88rem;
    gap: 6px;
  }
  .month-records-badge {
    font-size: 0.68rem;
    padding: 1px 6px;
  }
  .attendance-header-subtitle {
    font-size: 0.72rem;
  }
  .attendance-header-actions {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
  }
  .month-nav-group {
    width: 100%;
    display: flex;
    justify-content: space-between;
  }
  .month-picker-control {
    flex: 1;
    justify-content: space-between;
  }
  .month-picker-control input[type="month"] {
    font-size: 0.78rem;
    padding: 3px 4px;
    text-align: center;
  }
  .current-month-btn {
    white-space: nowrap;
    padding: 5px 8px !important;
    font-size: 0.72rem !important;
  }
  .today-punch-form {
    width: 100%;
  }
  .today-punch-form button {
    width: 100%;
    justify-content: center;
    padding: 8px 12px !important;
    font-size: 0.80rem !important;
  }
  .today-punch-form span {
    width: 100%;
    justify-content: center;
    text-align: center;
  }

  /* Table Cell Compact Sizes on Mobile */
  .attendance-table th {
    padding: 7px 8px;
    font-size: 0.66rem;
  }
  .attendance-table td {
    padding: 7px 8px;
    font-size: 0.72rem;
  }
  .attendance-table .col-num {
    width: 32px;
    font-size: 0.70rem;
    padding: 7px 4px;
  }
  .attendance-table .col-date {
    min-width: 90px;
  }
  .attendance-table .col-checkin,
  .attendance-table .col-checkout {
    min-width: 82px;
  }
  .attendance-table .col-duration {
    min-width: 75px;
  }
  .attendance-table .col-status {
    min-width: 96px;
  }
  .attendance-table .col-remarks {
    min-width: 110px;
    max-width: 150px;
    font-size: 0.70rem;
    line-height: 1.3;
  }
  .attendance-table .sid-dept-pill,
  .attendance-table .sid-code-pill,
  .attendance-table .sid-status-active {
    font-size: 0.66rem !important;
    padding: 2px 6px !important;
    gap: 3px !important;
  }

  .attendance-footer-summary {
    padding: 10px 12px;
    font-size: 0.70rem;
    gap: 8px;
  }
  .footer-summary-stats {
    width: 100%;
    justify-content: space-between;
    gap: 6px 8px;
  }
}

/* Micro Phones (<= 340px) */
@media (max-width: 340px) {
  .attendance-stat-grid {
    grid-template-columns: 1fr !important;
  }
  .month-picker-control input[type="month"] {
    font-size: 0.72rem;
    max-width: 110px;
  }
}

/* ── Dark Mode Adaptations ── */
[data-theme="dark"] .stat-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .stat-number {
  color: #f8fafc !important;
}

[data-theme="dark"] .stat-card-footer {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .stat-card-footer strong {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .stat-card .stat-label {
  color: #94a3b8 !important;
}

[data-theme="dark"] .attendance-register-card,
[data-theme="dark"] .attendance-card-header {
  background: #1e293b !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .attendance-header-title {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .attendance-header-subtitle {
  color: #94a3b8 !important;
}

[data-theme="dark"] .month-records-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .month-picker-control {
  background: #0f172a !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .month-picker-control a {
  color: #94a3b8 !important;
}

[data-theme="dark"] .month-picker-control a:hover {
  background: #334155 !important;
  color: #f1f5f9 !important;
}

[data-theme="dark"] .month-picker-control input[type="month"] {
  background: transparent !important;
  color: #f1f5f9 !important;
  color-scheme: dark !important;
}

[data-theme="dark"] .current-month-btn {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #e2e8f0 !important;
}

[data-theme="dark"] .current-month-btn:hover {
  background: #334155 !important;
  color: #ffffff !important;
}

[data-theme="dark"] .today-punch-form span[style*="background: #f0fdf4"],
[data-theme="dark"] .today-punch-form span[style*="background:#f0fdf4"] {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.35) !important;
  color: #34d399 !important;
}

[data-theme="dark"] .attendance-table th {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .attendance-table td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .attendance-table tbody tr:hover {
  background: rgba(51, 65, 85, 0.3) !important;
}

[data-theme="dark"] .attendance-table .col-num {
  color: #94a3b8 !important;
}

[data-theme="dark"] .attendance-date-title {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .attendance-table .col-date > div:last-child:not([style*="#dc2626"]) {
  color: #94a3b8 !important;
}

[data-theme="dark"] .attendance-table .today-row,
[data-theme="dark"] .attendance-table tr[style*="background: #f0f9ff"],
[data-theme="dark"] .attendance-table tr[style*="background:#f0f9ff"] {
  background: rgba(2, 132, 199, 0.15) !important;
}

[data-theme="dark"] .attendance-table .today-row td {
  background: transparent !important;
}

/* Check-In & Check-Out Pills */
[data-theme="dark"] .attendance-table .sid-dept-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f1f5f9 !important;
}

/* Duration Pill */
[data-theme="dark"] .attendance-table .sid-code-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .attendance-table .sid-code-pill i {
  color: #94a3b8 !important;
}

/* Status Pill Badges in Dark Mode */
[data-theme="dark"] .attendance-table .sid-status-active[style*="background: #f0fdf4"],
[data-theme="dark"] .attendance-table .sid-status-active[style*="background:#f0fdf4"] {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.35) !important;
  color: #34d399 !important;
}

[data-theme="dark"] .attendance-table .sid-status-active[style*="background: #fffbeb"],
[data-theme="dark"] .attendance-table .sid-status-active[style*="background:#fffbeb"] {
  background: rgba(217, 119, 6, 0.15) !important;
  border-color: rgba(217, 119, 6, 0.35) !important;
  color: #fbbf24 !important;
}

[data-theme="dark"] .attendance-table .sid-status-active[style*="background: #fef2f2"],
[data-theme="dark"] .attendance-table .sid-status-active[style*="background:#fef2f2"] {
  background: rgba(239, 68, 68, 0.15) !important;
  border-color: rgba(239, 68, 68, 0.35) !important;
  color: #f87171 !important;
}

[data-theme="dark"] .attendance-table .sid-status-active[style*="background: #eff6ff"],
[data-theme="dark"] .attendance-table .sid-status-active[style*="background:#eff6ff"] {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .attendance-table .sid-status-active[style*="background: #f8fafc"],
[data-theme="dark"] .attendance-table .sid-status-active[style*="background:#f8fafc"] {
  background: rgba(148, 163, 184, 0.12) !important;
  border-color: rgba(148, 163, 184, 0.25) !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .attendance-table .sid-status-active[style*="background: #fff7ed"],
[data-theme="dark"] .attendance-table .sid-status-active[style*="background:#fff7ed"] {
  background: rgba(249, 115, 22, 0.15) !important;
  border-color: rgba(249, 115, 22, 0.35) !important;
  color: #fb923c !important;
}

/* Remarks */
[data-theme="dark"] .attendance-table .col-remarks span {
  color: #cbd5e1 !important;
}

/* Footer Summary */
[data-theme="dark"] .attendance-footer-summary {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .footer-summary-left strong {
  color: #f1f5f9 !important;
}

[data-theme="dark"] .attendance-footer-summary strong[style*="color: #166534"],
[data-theme="dark"] .attendance-footer-summary strong[style*="color:#166534"] {
  color: #34d399 !important;
}

[data-theme="dark"] .attendance-footer-summary strong[style*="color: #b91c1c"],
[data-theme="dark"] .attendance-footer-summary strong[style*="color:#b91c1c"] {
  color: #f87171 !important;
}

[data-theme="dark"] .attendance-footer-summary strong[style*="color: #d97706"],
[data-theme="dark"] .attendance-footer-summary strong[style*="color:#d97706"] {
  color: #fbbf24 !important;
}

[data-theme="dark"] .attendance-footer-summary strong[style*="color: #0284c7"],
[data-theme="dark"] .attendance-footer-summary strong[style*="color:#0284c7"] {
  color: #38bdf8 !important;
}

[data-theme="dark"] .summary-dot {
  color: #475569 !important;
}
</style>

<!-- 4 Sober Monthly KPI Summary Cards -->
<div class="stat-grid attendance-stat-grid" style="margin-bottom: 22px;">

  <!-- Present Days -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Present Days</span>
          <span class="stat-number"><?php echo $total_present + $total_late; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-user-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $attendance_rate; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $attendance_rate; ?>%</strong> Monthly Presence Rate</span>
      <span style="color: #059669; font-weight: 700;">On-Duty</span>
    </div>
  </div>

  <!-- Absent Days -->
  <div class="stat-card red">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Absent Days</span>
          <span class="stat-number"><?php echo $total_absent; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-user-xmark"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $total_working_days_passed > 0 ? round(($total_absent / $total_working_days_passed) * 100, 1) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $total_absent; ?></strong> Days Unpunched</span>
      <span style="color: #dc2626; font-weight: 700;">Absent</span>
    </div>
  </div>

  <!-- Approved Leaves -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Approved Leaves</span>
          <span class="stat-number"><?php echo $total_leave; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-calendar-minus"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo min(100, $total_leave * 10); ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Authorized Institutional Leaves</span>
      <span style="color: #d97706; font-weight: 700;">Sanctioned</span>
    </div>
  </div>

  <!-- Month Working Calendar Overview -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Month Total Days</span>
          <span class="stat-number"><?php echo $days_in_month; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-calendar-days"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $total_weekly_off; ?></strong> Sundays • <strong><?php echo $days_in_month - $total_weekly_off; ?></strong> Working</span>
      <span style="color: #0284c7; font-weight: 700;"><?php echo date('F Y', strtotime($start_date)); ?></span>
    </div>
  </div>

</div>

<!-- Tabular Form Register Card -->
<div class="hrms-card attendance-register-card">
  
  <!-- Table Header Bar & Month Controls (Shifted here, replacing filter pills) -->
  <div class="attendance-card-header">
    <div class="attendance-header-info">
      <h3 class="attendance-header-title">
        <i class="fa-solid fa-table-list"></i>
        Attendance Register — <?php echo date('F Y', strtotime($start_date)); ?>
        <span class="month-records-badge">
          <?php echo count($attendance_calendar); ?> Records
        </span>
      </h3>
      <p class="attendance-header-subtitle">
        Detailed daily timeline indicating official check-in, check-out, duration and biometric status.
      </p>
    </div>

    <!-- Month Navigation and Quick Actions (Shifted here) -->
    <div class="attendance-header-actions">
      <div class="month-nav-group">
        <div class="month-picker-control">
          <a href="<?php echo STAFF_URL; ?>/myself-attendance?month=<?php echo $prev_month; ?>" 
             title="Previous Month">
            <i class="fa-solid fa-chevron-left"></i>
          </a>
          
          <form method="GET" action="<?php echo STAFF_URL; ?>/myself-attendance" style="margin: 0; display: inline-flex; align-items: center;">
            <input type="month" 
                   name="month" 
                   value="<?php echo htmlspecialchars($selected_month); ?>" 
                   onchange="this.form.submit()">
          </form>

          <a href="<?php echo STAFF_URL; ?>/myself-attendance?month=<?php echo $next_month; ?>" 
             title="Next Month">
            <i class="fa-solid fa-chevron-right"></i>
          </a>
        </div>

        <!-- Reset to Current Month Button -->
        <?php if ($selected_month !== date('Y-m')): ?>
          <a href="<?php echo STAFF_URL; ?>/myself-attendance" class="btn-hrms btn-hrms-outline btn-hrms-sm current-month-btn">
            Current Month
          </a>
        <?php endif; ?>
      </div>

      <!-- Today's Punch In/Out button (Shifted here) -->
      <form method="POST" class="today-punch-form">
        <?php if (!$current_punch || empty($current_punch['check_in_time'])): ?>
          <input type="hidden" name="action" value="punch_in">
          <button type="submit" class="btn-hrms btn-hrms-primary btn-hrms-sm" style="padding: 6px 14px; font-size: 0.8rem; background: #0284c7; border-color: #0284c7; gap: 6px;">
            <i class="fa-solid fa-fingerprint"></i> Biometric Punch In
          </button>
        <?php elseif (empty($current_punch['check_out_time'])): ?>
          <input type="hidden" name="action" value="punch_out">
          <button type="submit" class="btn-hrms btn-hrms-sm" style="padding: 6px 14px; font-size: 0.8rem; background: #d97706; color: #ffffff; border: 1px solid #b45309; gap: 6px;">
            <i class="fa-solid fa-right-from-bracket"></i> Punch Out (<?php echo date('h:i A', strtotime($current_punch['check_in_time'])); ?>)
          </button>
        <?php else: ?>
          <span style="font-size: 0.76rem; font-weight: 700; color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 5px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
            <i class="fa-solid fa-circle-check"></i> Duty Completed
          </span>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Sober Tabular Form -->
  <div class="table-responsive attendance-table-responsive">
    <table class="hrms-table staff-id-table attendance-table">
      <thead>
        <tr>
          <th class="col-num">#</th>
          <th class="col-date">Date &amp; Day</th>
          <th class="col-checkin">Check-In</th>
          <th class="col-checkout">Check-Out</th>
          <th class="col-duration">Duration</th>
          <th class="col-status">Status</th>
          <th class="col-remarks">Remarks / Source</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($filtered_calendar)): ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 40px 20px; color: #64748b;">
              <i class="fa-solid fa-calendar-xmark" style="font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 8px;"></i>
              No records found for the selected filter.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($filtered_calendar as $row): ?>
            <?php 
              $is_active_today = $row['is_today'];
              $row_bg = $is_active_today ? 'background: #f0f9ff;' : '';
            ?>
            <tr class="<?php echo $is_active_today ? 'today-row' : ''; ?>" style="<?php echo $row_bg; ?>">
              
              <!-- Day Number -->
              <td class="col-num" style="text-align: center; font-weight: 700; color: #475569; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
                <?php echo sprintf('%02d', $row['day']); ?>
              </td>

              <!-- Date & Day Name -->
              <td class="col-date">
                <div class="attendance-date-title" style="font-weight: 700; color: #0f172a; font-size: 0.84rem;">
                  <?php echo date('d M Y', strtotime($row['date'])); ?>
                  <?php if ($is_active_today): ?>
                    <span style="background: #0284c7; color: #ffffff; font-size: 0.65rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; text-transform: uppercase; margin-left: 5px;">Today</span>
                  <?php endif; ?>
                </div>
                <div style="font-size: 0.74rem; color: <?php echo $row['is_sunday'] ? '#dc2626; font-weight: 600;' : '#64748b;'; ?>">
                  <?php echo $row['day_name']; ?>
                </div>
              </td>

              <!-- Check-In Time -->
              <td class="col-checkin">
                <?php if ($row['check_in'] !== '—'): ?>
                  <span class="sid-dept-pill" style="color: #0f172a; gap: 5px;">
                    <i class="fa-solid fa-arrow-right-to-bracket" style="color: #059669; font-size: 0.7rem;"></i>
                    <?php echo $row['check_in']; ?>
                  </span>
                <?php elseif ($is_active_today): ?>
                  <form method="POST" style="margin: 0; display: inline-block;">
                    <input type="hidden" name="action" value="punch_in">
                    <button type="submit" class="btn-hrms btn-hrms-primary btn-hrms-sm" style="padding: 3px 9px; font-size: 0.72rem; background: #0284c7; border-color: #0284c7; gap: 4px;">
                      <i class="fa-solid fa-fingerprint"></i> Punch In
                    </button>
                  </form>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.82rem;">—</span>
                <?php endif; ?>
              </td>

              <!-- Check-Out Time -->
              <td class="col-checkout">
                <?php if ($row['check_out'] !== '—'): ?>
                  <span class="sid-dept-pill" style="color: #0f172a; gap: 5px;">
                    <i class="fa-solid fa-arrow-right-from-bracket" style="color: #d97706; font-size: 0.7rem;"></i>
                    <?php echo $row['check_out']; ?>
                  </span>
                <?php elseif ($is_active_today && $row['check_in'] !== '—'): ?>
                  <form method="POST" style="margin: 0; display: inline-block;">
                    <input type="hidden" name="action" value="punch_out">
                    <button type="submit" class="btn-hrms btn-hrms-sm" style="padding: 3px 9px; font-size: 0.72rem; background: #d97706; color: #ffffff; border: 1px solid #b45309; gap: 4px;">
                      <i class="fa-solid fa-right-from-bracket"></i> Punch Out
                    </button>
                  </form>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.82rem;">—</span>
                <?php endif; ?>
              </td>

              <!-- Duration -->
              <td class="col-duration">
                <?php if ($row['duration'] !== '—'): ?>
                  <span class="sid-code-pill" style="background: #f8fafc; border-color: #e2e8f0; color: #334155;">
                    <i class="fa-regular fa-clock" style="font-size: 0.7rem; color: #64748b;"></i>
                    <?php echo $row['duration']; ?>
                  </span>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.82rem;">—</span>
                <?php endif; ?>
              </td>

              <!-- Status Pill Badge -->
              <td class="col-status">
                <?php if ($row['status'] === 'Present'): ?>
                  <span class="sid-status-active" style="background: #f0fdf4; border-color: #bbf7d0; color: #166534;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a; display: inline-block;"></span>
                    Present
                  </span>
                <?php elseif ($row['status'] === 'Late'): ?>
                  <span class="sid-status-active" style="background: #fffbeb; border-color: #fde68a; color: #b45309;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #d97706; display: inline-block;"></span>
                    Late Arrival
                  </span>
                <?php elseif ($row['status'] === 'Absent'): ?>
                  <span class="sid-status-active" style="background: #fef2f2; border-color: #fecaca; color: #b91c1c;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #dc2626; display: inline-block;"></span>
                    Absent
                  </span>
                <?php elseif ($row['status'] === 'Leave'): ?>
                  <span class="sid-status-active" style="background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #2563eb; display: inline-block;"></span>
                    Approved Leave
                  </span>
                <?php elseif ($row['status'] === 'Weekly Off'): ?>
                  <span class="sid-status-active" style="background: #f8fafc; border-color: #e2e8f0; color: #64748b;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #94a3b8; display: inline-block;"></span>
                    Weekly Off
                  </span>
                <?php elseif ($row['status'] === 'Not Checked In'): ?>
                  <span class="sid-status-active" style="background: #fff7ed; border-color: #ffedd5; color: #c2410c;">
                    <span class="pulse-dot" style="background: #ea580c;"></span>
                    Not Checked In
                  </span>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.76rem; font-style: italic;">
                    Upcoming
                  </span>
                <?php endif; ?>
              </td>

              <!-- Remarks -->
              <td class="col-remarks">
                <span style="font-size: 0.78rem; color: #475569;">
                  <?php echo htmlspecialchars($row['remarks'] ?? '—'); ?>
                </span>
              </td>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Table Footer Summary -->
  <div class="attendance-footer-summary">
    <div class="footer-summary-left">
      Showing month of <strong><?php echo date('F Y', strtotime($start_date)); ?></strong> (<?php echo $days_in_month; ?> calendar days).
    </div>
    <div class="footer-summary-stats">
      <span><strong style="color: #166534;"><?php echo $total_present + $total_late; ?></strong> Present</span>
      <span class="summary-dot">•</span>
      <span><strong style="color: #b91c1c;"><?php echo $total_absent; ?></strong> Absent</span>
      <span class="summary-dot">•</span>
      <span><strong style="color: #d97706;"><?php echo $total_leave; ?></strong> Approved Leaves</span>
      <span class="summary-dot">•</span>
      <span><strong style="color: #0284c7;"><?php echo $attendance_rate; ?>%</strong> Net Rate</span>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
