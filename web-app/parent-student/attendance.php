<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
$page_title = 'Student Attendance Register';
$page_heading = 'Student Attendance Register';
$page_subheading = 'Monthly Presence Ledger, Daily Biometric In/Out Timings & Punch History';
$active_menu = 'attendance';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'] ?? 1;

// ── Student / Scholar Resolution ──
$student = null;
$parent_children = [];
$selected_student_id = intval($_GET['student_id'] ?? 0);

if ($current_user['role'] === 'student') {
    $stmt = $db->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$current_user['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        $stmt = $db->prepare("SELECT * FROM students WHERE student_name LIKE ? OR roll_no = ? LIMIT 1");
        $stmt->execute(['%' . $current_user['full_name'] . '%', $current_user['username']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($student) {
        $selected_student_id = $student['id'];
        $_SESSION['active_student_id'] = $student['id'];
    }
} elseif ($current_user['role'] === 'parent') {
    $c_stmt = $db->prepare("SELECT id, student_name, roll_no, standard, section, photo, attendance_percentage FROM students WHERE parent_user_id = ? ORDER BY id ASC");
    $c_stmt->execute([$current_user['id']]);
    $parent_children = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($selected_student_id > 0) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$selected_student_id, $current_user['id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) {
            $_SESSION['active_student_id'] = $student['id'];
        }
    }

    if (!$student && !empty($_SESSION['active_student_id'])) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['active_student_id'], $current_user['id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$student && !empty($parent_children)) {
        $student = $parent_children[0];
        $selected_student_id = $student['id'];
        $_SESSION['active_student_id'] = $student['id'];
    }
} else {
    // Super Admin or Staff
    if ($selected_student_id > 0) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$selected_student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$student && !empty($_SESSION['active_student_id'])) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['active_student_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$student) {
        $student = $db->query("SELECT * FROM students WHERE session_id = {$session_id} ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }
}

$student_id = $student['id'] ?? 0;

// Selected Month setup (defaults to current month YYYY-MM)
$selected_month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = date('Y-m');
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

// Helper for month URLs with student_id preserved
function get_student_month_url($m, $sid) {
    $params = ['month' => $m];
    if ($sid > 0) {
        $params['student_id'] = $sid;
    }
    return PARENT_URL . '/attendance?' . http_build_query($params);
}

// Fetch all attendance logs for this student in the month
$log_stmt = $db->prepare("
    SELECT * FROM attendance_logs 
    WHERE type = 'student' AND reference_id = ? AND log_date BETWEEN ? AND ?
    ORDER BY log_date ASC
");
$log_stmt->execute([$student_id, $start_date, $end_date]);
$month_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

$logs_by_date = [];
foreach ($month_logs as $l) {
    $logs_by_date[$l['log_date']] = $l;
}

// Build monthly ledger array (Day 1 to days_in_month)
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

    $status = 'Upcoming';
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
            $duration = 'In Academy';
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

// Today's log for duty / presence indicator
$today_log = $db->prepare("SELECT * FROM attendance_logs WHERE type = 'student' AND reference_id = ? AND log_date = CURDATE() LIMIT 1");
$today_log->execute([$student_id]);
$current_punch = $today_log->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── 4-Column Stat Grid ── */
.stat-grid-4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}
@media (max-width: 1100px) {
  .stat-grid-4 {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 576px) {
  .stat-grid-4 {
    grid-template-columns: 1fr;
  }
}

/* ── Staff & Student Table Styling (Exact replica of myself-attendance.php) ── */
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
  padding: 11px 12px;
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

/* Pills & Badges */
.sid-dept-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #0f172a;
  padding: 3px 8px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}
.sid-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 2px 7px;
  border-radius: 5px;
  font-size: 0.74rem;
  font-weight: 600;
  white-space: nowrap;
}
.sid-status-active {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 2px 9px;
  border-radius: 20px;
  font-size: 0.74rem;
  font-weight: 600;
  border: 1px solid transparent;
  white-space: nowrap;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS FOR STUDENT ATTENDANCE REGISTER
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  /* 4 Monthly KPI Stat Cards */
  .stat-grid-4 {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
    margin-bottom: 16px !important;
  }

  .stat-card {
    padding: 12px 14px !important;
    border-radius: 10px !important;
  }

  .stat-label {
    font-size: 0.68rem !important;
  }

  .stat-number {
    font-size: 1.15rem !important;
    margin-top: 2px !important;
  }

  .stat-icon-wrapper {
    width: 32px !important;
    height: 32px !important;
    font-size: 0.88rem !important;
  }

  .stat-card-progress {
    margin: 8px 0 6px !important;
    height: 4px !important;
  }

  .stat-card-footer {
    font-size: 0.68rem !important;
    margin-top: 6px !important;
    padding-top: 6px !important;
    flex-wrap: wrap !important;
    gap: 4px !important;
  }

  /* Attendance Register Card */
  .attendance-register-card,
  .hrms-card {
    border-radius: 10px !important;
    margin-bottom: 20px !important;
  }

  .attendance-card-header {
    padding: 14px 14px !important;
    gap: 12px !important;
  }

  .attendance-header-info {
    width: 100% !important;
  }

  .attendance-header-title {
    font-size: 0.90rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }

  .attendance-header-subtitle {
    font-size: 0.72rem !important;
  }

  .attendance-header-actions {
    width: 100% !important;
    justify-content: space-between !important;
    gap: 8px !important;
  }

  .child-select-form {
    width: 100% !important;
  }

  .child-select-dropdown {
    width: 100% !important;
    font-size: 0.78rem !important;
    height: 36px !important;
  }

  .month-nav-box {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 2px !important;
  }

  .month-picker-input {
    font-size: 0.78rem !important;
    padding: 3px 6px !important;
  }

  .current-month-btn {
    font-size: 0.74rem !important;
    padding: 5px 10px !important;
    white-space: nowrap !important;
  }

  .today-status-badge {
    font-size: 0.72rem !important;
    padding: 4px 8px !important;
  }

  /* Table Cell Compact Sizes on Mobile */
  .table-responsive {
    width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    margin: 0 !important;
  }

  .staff-id-table {
    min-width: 600px !important;
  }

  .staff-id-table thead th {
    padding: 8px 8px !important;
    font-size: 0.68rem !important;
  }

  .staff-id-table tbody td {
    padding: 8px 8px !important;
    font-size: 0.76rem !important;
  }

  .sid-dept-pill,
  .sid-code-pill {
    font-size: 0.70rem !important;
    padding: 2px 6px !important;
  }

  .sid-status-active {
    font-size: 0.70rem !important;
    padding: 2px 7px !important;
  }

  /* Table Footer Summary */
  .attendance-card-footer {
    padding: 10px 14px !important;
    font-size: 0.72rem !important;
    gap: 8px !important;
    flex-direction: column !important;
    align-items: flex-start !important;
  }

  .footer-stats-pills {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 8px !important;
    font-size: 0.72rem !important;
  }
}

/* Micro Devices (<= 480px) */
@media (max-width: 480px) {
  .attendance-header-actions {
    flex-direction: column !important;
    align-items: stretch !important;
  }

  .month-nav-box {
    width: 100% !important;
  }

  .month-input-form {
    flex: 1 !important;
    justify-content: center !important;
  }

  .month-picker-input {
    width: 100% !important;
    text-align: center !important;
  }

  .current-month-btn {
    width: 100% !important;
    text-align: center !important;
    justify-content: center !important;
  }

  .today-status-badge {
    width: 100% !important;
    text-align: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
  }
}

/* Micro Devices (<= 380px) */
@media (max-width: 380px) {
  .stat-grid-4 {
    grid-template-columns: 1fr !important;
    gap: 10px !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR STUDENT ATTENDANCE REGISTER
   ══════════════════════════════════════════════════════════════════════ */
[data-theme="dark"] .attendance-register-card,
[data-theme="dark"] .hrms-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .attendance-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .attendance-header-title {
  color: #f8fafc !important;
}

[data-theme="dark"] .attendance-header-subtitle {
  color: #94a3b8 !important;
}

[data-theme="dark"] .month-records-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .student-name-badge {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .child-select-dropdown {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}

[data-theme="dark"] .month-nav-box {
  background: #0f172a !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .month-nav-prev,
[data-theme="dark"] .month-nav-next {
  color: #94a3b8 !important;
}

[data-theme="dark"] .month-picker-input {
  color: #f8fafc !important;
}

[data-theme="dark"] .current-month-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .today-status-badge.active-academic {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .today-status-badge.duty-completed {
  background: rgba(22, 101, 52, 0.25) !important;
  border-color: rgba(74, 222, 128, 0.3) !important;
  color: #4ade80 !important;
}

[data-theme="dark"] .staff-id-table thead th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .staff-id-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .staff-id-table tbody tr:hover td {
  background: #192339 !important;
}

[data-theme="dark"] .staff-id-table tr[style*="background: #f0f9ff"] td {
  background: rgba(2, 132, 199, 0.12) !important;
}

[data-theme="dark"] .staff-id-table tbody td div[style*="color: #0f172a"] {
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table tbody td div[style*="color: #64748b"] {
  color: #94a3b8 !important;
}

[data-theme="dark"] .staff-id-table tbody td span[style*="color: #475569"] {
  color: #94a3b8 !important;
}

[data-theme="dark"] .sid-dept-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .sid-code-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .sid-status-active[style*="color: #166534"] {
  background: rgba(22, 101, 52, 0.25) !important;
  border-color: rgba(74, 222, 128, 0.3) !important;
  color: #4ade80 !important;
}

[data-theme="dark"] .sid-status-active[style*="color: #b45309"] {
  background: rgba(217, 119, 6, 0.2) !important;
  border-color: rgba(251, 191, 36, 0.3) !important;
  color: #fbbf24 !important;
}

[data-theme="dark"] .sid-status-active[style*="color: #b91c1c"] {
  background: rgba(220, 38, 38, 0.2) !important;
  border-color: rgba(248, 113, 113, 0.3) !important;
  color: #f87171 !important;
}

[data-theme="dark"] .sid-status-active[style*="color: #1d4ed8"] {
  background: rgba(37, 99, 235, 0.2) !important;
  border-color: rgba(96, 165, 250, 0.3) !important;
  color: #60a5fa !important;
}

[data-theme="dark"] .sid-status-active[style*="color: #64748b"] {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .sid-status-active[style*="color: #c2410c"] {
  background: rgba(234, 88, 12, 0.2) !important;
  border-color: rgba(251, 146, 60, 0.3) !important;
  color: #fb923c !important;
}

[data-theme="dark"] .attendance-card-footer {
  background: #0f172a !important;
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .attendance-card-footer strong {
  color: #f8fafc !important;
}
</style>

<!-- 4 Sober Monthly KPI Summary Cards (Exact replica of myself-attendance.php) -->
<div class="stat-grid-4">

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

<!-- Tabular Form Register Card (Exact replica of myself-attendance.php) -->
<div class="hrms-card attendance-register-card" style="border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 30px;">
  
  <!-- Table Header Bar & Month Controls -->
  <div class="attendance-card-header" style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; background: #ffffff;">
    <div class="attendance-header-info">
      <h3 class="attendance-header-title" style="margin: 0; font-size: 0.98rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <i class="fa-solid fa-table-list" style="color: #0284c7;"></i>
        Attendance Register — <?php echo date('F Y', strtotime($start_date)); ?>
        <span class="month-records-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          <?php echo count($attendance_calendar); ?> Records
        </span>
        <?php if (!empty($student['student_name'])): ?>
          <span class="student-name-badge" style="font-size: 0.74rem; font-weight: 700; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 6px;">
            <?php echo htmlspecialchars($student['student_name']); ?> (<?php echo htmlspecialchars($student['roll_no'] ?? '—'); ?>)
          </span>
        <?php endif; ?>
      </h3>
      <p class="attendance-header-subtitle" style="margin: 3px 0 0; font-size: 0.77rem; color: #64748b;">
        Detailed daily timeline indicating official check-in, check-out, duration and biometric status.
      </p>
    </div>

    <!-- Month Navigation, Child Switcher and Status Actions -->
    <div class="attendance-header-actions" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      
      <!-- Parent Children Switcher (if multiple children exist) -->
      <?php if (!empty($parent_children) && count($parent_children) > 1): ?>
        <form method="GET" action="<?php echo PARENT_URL; ?>/attendance" class="child-select-form" style="margin: 0; display: inline-flex;">
          <input type="hidden" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
          <select name="student_id" onchange="this.form.submit()" class="hrms-input child-select-dropdown" style="width: auto; padding: 5px 10px; font-size: 0.8rem; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600;">
            <?php foreach ($parent_children as $pc): ?>
              <option value="<?php echo $pc['id']; ?>" <?php echo $selected_student_id === intval($pc['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($pc['student_name']); ?> (<?php echo htmlspecialchars($pc['roll_no']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>

      <!-- Month Navigation Controls -->
      <div class="month-nav-box" style="display: inline-flex; align-items: center; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 3px;">
        <a href="<?php echo htmlspecialchars(get_student_month_url($prev_month, $selected_student_id)); ?>" 
           class="btn-hrms btn-hrms-sm month-nav-prev" 
           style="background: transparent; color: #475569; border: none; padding: 5px 10px; font-size: 0.78rem;" 
           title="Previous Month">
          <i class="fa-solid fa-chevron-left"></i>
        </a>
        
        <form method="GET" action="<?php echo PARENT_URL; ?>/attendance" class="month-input-form" style="margin: 0; display: inline-flex; align-items: center;">
          <?php if ($selected_student_id > 0): ?>
            <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
          <?php endif; ?>
          <input type="month" 
                 name="month" 
                 value="<?php echo htmlspecialchars($selected_month); ?>" 
                 onchange="this.form.submit()" 
                 class="month-picker-input"
                 style="border: none; background: transparent; font-weight: 700; font-size: 0.85rem; color: #0f172a; padding: 4px 8px; cursor: pointer; outline: none; font-family: inherit;">
        </form>

        <a href="<?php echo htmlspecialchars(get_student_month_url($next_month, $selected_student_id)); ?>" 
           class="btn-hrms btn-hrms-sm month-nav-next" 
           style="background: transparent; color: #475569; border: none; padding: 5px 10px; font-size: 0.78rem;" 
           title="Next Month">
          <i class="fa-solid fa-chevron-right"></i>
        </a>
      </div>

      <!-- Reset to Current Month Button -->
      <?php if ($selected_month !== date('Y-m')): ?>
        <a href="<?php echo PARENT_URL; ?>/attendance<?php echo $selected_student_id ? '?student_id=' . $selected_student_id : ''; ?>" class="btn-hrms btn-hrms-outline btn-hrms-sm current-month-btn" style="font-size: 0.78rem; padding: 6px 12px;">
          Current Month
        </a>
      <?php endif; ?>

      <!-- Today's Status Badge -->
      <?php if ($current_punch && !empty($current_punch['check_in_time'])): ?>
        <span class="today-status-badge duty-completed" style="font-size: 0.76rem; font-weight: 700; color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 5px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
          <i class="fa-solid fa-circle-check"></i> Duty Completed
        </span>
      <?php else: ?>
        <span class="today-status-badge active-academic" style="font-size: 0.76rem; font-weight: 600; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
          <i class="fa-regular fa-calendar-check" style="color: #0284c7;"></i> Academic Active
        </span>
      <?php endif; ?>

    </div>
  </div>

  <!-- Sober Tabular Form (Exact replica of myself-attendance.php) -->
  <div class="table-responsive">
    <table class="hrms-table staff-id-table">
      <thead>
        <tr>
          <th style="width: 55px; text-align: center;">#</th>
          <th style="min-width: 130px;">Date &amp; Day</th>
          <th style="min-width: 120px;">Check-In</th>
          <th style="min-width: 120px;">Check-Out</th>
          <th style="min-width: 110px;">Duration</th>
          <th style="min-width: 140px;">Status</th>
          <th style="min-width: 180px;">Remarks / Source</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($attendance_calendar as $row): ?>
          <?php 
            $is_active_today = $row['is_today'];
            $row_bg = $is_active_today ? 'background: #f0f9ff;' : '';
          ?>
          <tr style="<?php echo $row_bg; ?>">
            
            <!-- Day Number -->
            <td style="text-align: center; font-weight: 700; color: #475569; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
              <?php echo sprintf('%02d', $row['day']); ?>
            </td>

            <!-- Date & Day Name -->
            <td>
              <div style="font-weight: 700; color: #0f172a; font-size: 0.84rem;">
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
            <td>
              <?php if ($row['check_in'] !== '—'): ?>
                <span class="sid-dept-pill" style="color: #0f172a; gap: 5px;">
                  <i class="fa-solid fa-arrow-right-to-bracket" style="color: #059669; font-size: 0.7rem;"></i>
                  <?php echo $row['check_in']; ?>
                </span>
              <?php else: ?>
                <span style="color: #94a3b8; font-size: 0.82rem;">—</span>
              <?php endif; ?>
            </td>

            <!-- Check-Out Time -->
            <td>
              <?php if ($row['check_out'] !== '—'): ?>
                <span class="sid-dept-pill" style="color: #0f172a; gap: 5px;">
                  <i class="fa-solid fa-arrow-right-from-bracket" style="color: #d97706; font-size: 0.7rem;"></i>
                  <?php echo $row['check_out']; ?>
                </span>
              <?php else: ?>
                <span style="color: #94a3b8; font-size: 0.82rem;">—</span>
              <?php endif; ?>
            </td>

            <!-- Duration -->
            <td>
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
            <td>
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
                  <span style="width: 6px; height: 6px; border-radius: 50%; background: #ea580c; display: inline-block;"></span>
                  Not Checked In
                </span>
              <?php else: ?>
                <span style="color: #94a3b8; font-size: 0.76rem; font-style: italic;">
                  Upcoming
                </span>
              <?php endif; ?>
            </td>

            <!-- Remarks -->
            <td>
              <span style="font-size: 0.78rem; color: #475569;">
                <?php echo htmlspecialchars($row['remarks'] ?? '—'); ?>
              </span>
            </td>

          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Table Footer Summary -->
  <div class="attendance-card-footer" style="padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; font-size: 0.78rem; color: #64748b;">
    <div class="footer-month-text">
      Showing month of <strong><?php echo date('F Y', strtotime($start_date)); ?></strong> (<?php echo $days_in_month; ?> calendar days).
    </div>
    <div class="footer-stats-pills" style="display: flex; align-items: center; gap: 14px;">
      <span><strong style="color: #166534;"><?php echo $total_present + $total_late; ?></strong> Present</span>
      <span>•</span>
      <span><strong style="color: #b91c1c;"><?php echo $total_absent; ?></strong> Absent</span>
      <span>•</span>
      <span><strong style="color: #d97706;"><?php echo $total_leave; ?></strong> Approved Leaves</span>
      <span>•</span>
      <span><strong style="color: #0284c7;"><?php echo $attendance_rate; ?>%</strong> Net Rate</span>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
