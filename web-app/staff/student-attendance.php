<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
require_menu_permission('student-attendance');
$page_title = 'Student Attendance & QR Scanner';
$page_heading = 'Student Attendance & QR Code Scanner';
$page_subheading = 'Real-time Camera QR Biometric Punch-In, Daily Roll Register & Automated Logging';
$active_menu = 'student-attendance';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];

$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_standard = $_GET['standard'] ?? '';

// Handle QR Scan / Manual Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Mark attendance by Student ID or QR Code String
    if ($action === 'mark_attendance') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $qr_data = trim($_POST['qr_data'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['Present', 'Late', 'Absent', 'Leave']) ? $_POST['status'] : 'Present';
        $log_date = $_POST['log_date'] ?? date('Y-m-d');

        // Check-in timestamp handling
        if ($status === 'Absent') {
            $check_in = null;
            $default_remarks = 'Marked Absent';
        } elseif (!empty($_POST['check_in_time'])) {
            $check_in = date('H:i:s', strtotime($_POST['check_in_time']));
            $default_remarks = $status === 'Late' ? 'Late arrival recorded (' . date('h:i A', strtotime($check_in)) . ')' : 'Marked Present';
        } else {
            $check_in = date('H:i:s');
            $default_remarks = $status === 'Late' ? 'Late arrival recorded (' . date('h:i A') . ')' : 'Marked via Quick Punch';
        }

        $remarks = trim($_POST['remarks'] ?? $default_remarks);
        if (empty($remarks)) {
            $remarks = $default_remarks;
        }

        // If scanned via QR string, resolve student_id
        if ($student_id === 0 && !empty($qr_data)) {
            // Find student by qr_code, roll_no, or admission_no
            $stmt = $db->prepare("SELECT id, student_name, roll_no FROM students WHERE qr_code = ? OR roll_no = ? OR admission_no = ? OR CONCAT('STU-', roll_no) = ? LIMIT 1");
            $stmt->execute([$qr_data, $qr_data, $qr_data, $qr_data]);
            $stu = $stmt->fetch();
            if ($stu) {
                $student_id = $stu['id'];
            }
        }

        if ($student_id > 0) {
            // Check if log already exists for this student on this date
            $chk = $db->prepare("SELECT id FROM attendance_logs WHERE type = 'student' AND reference_id = ? AND log_date = ? LIMIT 1");
            $chk->execute([$student_id, $log_date]);
            $existing_id = $chk->fetchColumn();

            if ($existing_id) {
                // Update status
                $stmt = $db->prepare("UPDATE attendance_logs SET status = ?, check_in_time = ?, remarks = ? WHERE id = ?");
                $stmt->execute([$status, $check_in, $remarks, $existing_id]);
            } else {
                // Insert new log
                $stmt = $db->prepare("INSERT INTO attendance_logs (session_id, type, reference_id, log_date, status, check_in_time, remarks) VALUES (?, 'student', ?, ?, ?, ?, ?)");
                $stmt->execute([$session_id, $student_id, $log_date, $status, $check_in, $remarks]);
            }

            // Recalculate student's cumulative attendance percentage
            $calc_stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as attended_days
                FROM attendance_logs 
                WHERE type = 'student' AND reference_id = ? AND session_id = ?
            ");
            $calc_stmt->execute([$student_id, $session_id]);
            $calc_res = $calc_stmt->fetch(PDO::FETCH_ASSOC);
            if ($calc_res && $calc_res['total_days'] > 0) {
                $new_pct = round(($calc_res['attended_days'] / $calc_res['total_days']) * 100, 1);
                $upd_pct = $db->prepare("UPDATE students SET attendance_percentage = ? WHERE id = ?");
                $upd_pct->execute([$new_pct, $student_id]);
            }

            // Return JSON if AJAX request
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                $stmt_sinfo = $db->prepare("SELECT student_name, roll_no, standard FROM students WHERE id = ?");
                $stmt_sinfo->execute([$student_id]);
                $stu_info = $stmt_sinfo->fetch(PDO::FETCH_ASSOC);

                // Get updated counts for the page date
                $stats_stmt = $db->prepare("
                    SELECT 
                        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                    FROM attendance_logs 
                    WHERE type = 'student' AND session_id = ? AND log_date = ?
                ");
                $stats_stmt->execute([$session_id, $log_date]);
                $day_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

                $formatted_time = !empty($check_in) ? date('h:i A', strtotime($check_in)) : '—';
                $input_time = !empty($check_in) ? date('H:i', strtotime($check_in)) : '';

                echo json_encode([
                    'success' => true,
                    'message' => "Marked {$status} for {$stu_info['student_name']} ({$stu_info['roll_no']})",
                    'student' => $stu_info,
                    'status' => $status,
                    'check_in_time' => $check_in,
                    'formatted_time' => $formatted_time,
                    'input_time' => $input_time,
                    'time' => $formatted_time,
                    'day_stats' => [
                        'present' => intval($day_stats['present_count'] ?? 0),
                        'late' => intval($day_stats['late_count'] ?? 0),
                        'absent' => intval($day_stats['absent_count'] ?? 0)
                    ]
                ]);
                exit;
            }

            set_flash('success', "Attendance marked as {$status} for student #{$student_id}.");
            header("Location: " . STAFF_URL . "/student-attendance?date={$log_date}");
            exit;
        } else {
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "No student found matching QR code '{$qr_data}'."]);
                exit;
            }
            set_flash('error', "No student found matching scanned QR code '{$qr_data}'.");
        }
    }

    // Mark all students present for the date
    if ($action === 'mark_all_present') {
        $log_date = $_POST['log_date'] ?? date('Y-m-d');
        $all_stus = $db->prepare("SELECT id FROM students WHERE session_id = ?");
        $all_stus->execute([$session_id]);
        $stu_ids = $all_stus->fetchAll(PDO::FETCH_COLUMN);

        $now_time = date('H:i:s');
        foreach ($stu_ids as $sid) {
            $chk = $db->prepare("SELECT id FROM attendance_logs WHERE type = 'student' AND reference_id = ? AND log_date = ? LIMIT 1");
            $chk->execute([$sid, $log_date]);
            $existing_id = $chk->fetchColumn();
            if ($existing_id) {
                $upd = $db->prepare("UPDATE attendance_logs SET status = 'Present', check_in_time = COALESCE(check_in_time, ?) WHERE id = ?");
                $upd->execute([$now_time, $existing_id]);
            } else {
                $ins = $db->prepare("INSERT INTO attendance_logs (session_id, type, reference_id, log_date, status, check_in_time, remarks) VALUES (?, 'student', ?, ?, 'Present', ?, 'Marked All Present')");
                $ins->execute([$session_id, $sid, $log_date, $now_time]);
            }
        }
        set_flash('success', "All enrolled scholars marked as Present for " . date('d M Y', strtotime($log_date)) . ".");
        header("Location: " . STAFF_URL . "/student-attendance?date={$log_date}");
        exit;
    }
}

// Auto-mark unpunched students as Absent for previous dates:
// 1. Any past date where attendance was already logged for some students in this session
// 2. The currently viewed $filter_date if it is in the past (< today)
$past_dates = $db->query("
    SELECT DISTINCT log_date 
    FROM attendance_logs 
    WHERE session_id = {$session_id} AND type = 'student' AND log_date < CURDATE()
")->fetchAll(PDO::FETCH_COLUMN);

if ($filter_date < date('Y-m-d') && !in_array($filter_date, $past_dates)) {
    $past_dates[] = $filter_date;
}

if (!empty($past_dates)) {
    $auto_absent_sql = "
        INSERT INTO attendance_logs (session_id, type, reference_id, log_date, status, check_in_time, remarks)
        SELECT s.session_id, 'student', s.id, ?, 'Absent', NULL, 'Auto Absent (Unpunched)'
        FROM students s
        WHERE s.session_id = ?
          AND NOT EXISTS (
              SELECT 1 FROM attendance_logs al 
              WHERE al.type = 'student' 
                AND al.reference_id = s.id 
                AND al.log_date = ?
          )
    ";
    $auto_stmt = $db->prepare($auto_absent_sql);
    foreach ($past_dates as $pd) {
        $auto_stmt->execute([$pd, $session_id, $pd]);
    }
}

// Helper for attendance pagination links preserving date, standard and limit
function get_attendance_pagination_url($target_page, $date, $standard, $limit) {
    $params = ['date' => $date];
    if (!empty($standard)) {
        $params['standard'] = $standard;
    }
    if ($limit !== 10) {
        $params['limit'] = $limit;
    }
    if ($target_page > 1) {
        $params['page'] = $target_page;
    }
    return STAFF_URL . '/student-attendance?' . http_build_query($params);
}

// Overall Attendance Statistics Query (unaffected by pagination limits)
$stats_sql = "
    SELECT 
        COUNT(s.id) as total_scholars,
        SUM(CASE WHEN al.status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN al.status = 'Late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN al.status = 'Absent' THEN 1 ELSE 0 END) as absent_count
    FROM students s
    LEFT JOIN attendance_logs al ON al.reference_id = s.id AND al.type = 'student' AND al.log_date = ?
    WHERE s.session_id = ?
";
$stats_params = [$filter_date, $session_id];
if (!empty($filter_standard)) {
    $stats_sql .= " AND s.standard = ?";
    $stats_params[] = $filter_standard;
}
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute($stats_params);
$day_stats_res = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$total_count = intval($day_stats_res['total_scholars'] ?? 0);
$present_count = intval($day_stats_res['present_count'] ?? 0);
$late_count = intval($day_stats_res['late_count'] ?? 0);
$absent_count = intval($day_stats_res['absent_count'] ?? 0);
$unmarked_count = max(0, $total_count - ($present_count + $late_count + $absent_count));

$present_rate = $total_count > 0 ? round(($present_count / $total_count) * 100, 1) : 0;
$late_rate = $total_count > 0 ? round(($late_count / $total_count) * 100, 1) : 0;
$absent_rate = $total_count > 0 ? round((($absent_count + $unmarked_count) / $total_count) * 100, 1) : 0;

// Pagination & Limits Setup
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

$total_records = $total_count;
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? ($offset + 1) : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch students for current page in this session
$std_query = "SELECT s.*, 
    (SELECT status FROM attendance_logs WHERE type = 'student' AND reference_id = s.id AND log_date = ?) as today_status,
    (SELECT check_in_time FROM attendance_logs WHERE type = 'student' AND reference_id = s.id AND log_date = ?) as check_in_time
    FROM students s
    WHERE s.session_id = ?";
$std_params = [$filter_date, $filter_date, $session_id];

if (!empty($filter_standard)) {
    $std_query .= " AND s.standard = ?";
    $std_params[] = $filter_standard;
}
$std_query .= " ORDER BY s.standard ASC, s.roll_no ASC LIMIT ? OFFSET ?";

$stmt = $db->prepare($std_query);
$b_idx = 1;
foreach ($std_params as $p) {
    $stmt->bindValue($b_idx++, $p);
}
$stmt->bindValue($b_idx++, $limit, PDO::PARAM_INT);
$stmt->bindValue($b_idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$students_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

$standards = ['8th Standard', '9th Standard', '10th Standard', '11th Science', '11th Commerce', '12th Science', '12th Commerce'];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Student Attendance Table Styles (Simple & Sober - Matching student-id) ── */
.student-id-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.student-id-table thead th {
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
.student-id-table tbody td {
  padding: 11px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.84rem;
}
.student-id-table tbody tr {
  transition: background-color 0.15s ease;
}
.student-id-table tbody tr:hover {
  background-color: #f8fafc;
}

/* Badge & Tag Elements */
.sid-roll-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0284c7;
  padding: 2px 7px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.74rem;
  font-weight: 700;
  white-space: nowrap;
}
.sid-std-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}
.sid-blood-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  background: #fff1f2;
  border: 1px solid #fecdd3;
  color: #be123c;
  font-size: 0.70rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
  white-space: nowrap;
}

/* Attendance Status Pills */
.att-pill-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 0.74rem;
  font-weight: 600;
  white-space: nowrap;
}
.att-pill-chip.high {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}
.att-pill-chip.medium {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #b45309;
}
.att-pill-chip.low {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}
.legend-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
}
.legend-dot.green { background: #16a34a; }
.legend-dot.amber { background: #d97706; }
.legend-dot.red { background: #dc2626; }

.att-time-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 2px 8px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.74rem;
  font-weight: 600;
  white-space: nowrap;
}

.unmarked-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #64748b;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 12px;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS FOR STUDENT ATTENDANCE
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  /* Stat Metric Cards - Clean 2x2 Grid */
  .stat-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
    margin-bottom: 14px !important;
  }
  .stat-card {
    padding: 10px 12px !important;
    border-radius: 10px !important;
  }
  .stat-label {
    font-size: 0.65rem !important;
    line-height: 1.2 !important;
  }
  .stat-number {
    font-size: 1.25rem !important;
    line-height: 1.2 !important;
  }
  .stat-icon-wrapper {
    width: 30px !important;
    height: 30px !important;
    min-width: 30px !important;
    font-size: 0.8rem !important;
  }
  .stat-card-progress {
    height: 3px !important;
    margin-top: 5px !important;
  }
  .stat-card-footer {
    margin-top: 6px !important;
    padding-top: 5px !important;
    font-size: 0.64rem !important;
    flex-wrap: wrap !important;
    gap: 3px !important;
  }

  /* Attendance Register Workstation Header */
  .hrms-card {
    border-radius: 12px !important;
    margin-bottom: 20px !important;
  }
  .hrms-card-header {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 12px !important;
  }
  .hrms-card-header > div:first-child {
    width: 100% !important;
  }
  .hrms-card-header h3.card-title {
    font-size: 0.95rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
  .hrms-card-header p {
    font-size: 0.74rem !important;
    line-height: 1.4 !important;
  }
  .hrms-card-header > div:last-child {
    width: 100% !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
    margin-top: 2px !important;
  }
  .hrms-card-header form {
    width: 100% !important;
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8px !important;
    margin: 0 !important;
  }
  .hrms-card-header form > div:first-child {
    width: 100% !important;
  }
  .hrms-card-header form input[type="date"] {
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    height: 38px !important;
    font-size: 0.80rem !important;
    border-radius: 8px !important;
    padding: 6px 10px !important;
  }
  .hrms-card-header form select[name="standard"] {
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    height: 38px !important;
    font-size: 0.80rem !important;
    border-radius: 8px !important;
    padding: 6px 10px !important;
  }
  .hrms-card-header .roaster-add-btn {
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
    font-size: 0.82rem !important;
    box-sizing: border-box !important;
    border-radius: 8px !important;
    margin: 0 !important;
  }
  .hrms-card-header .roaster-add-btn .btn-icon-circle {
    width: 22px !important;
    height: 22px !important;
    font-size: 0.72rem !important;
  }

  /* Table & Scrolling */
  .table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    width: 100% !important;
    margin: 0 !important;
  }
  .student-id-table {
    min-width: 720px !important;
  }
  .student-id-table thead th {
    padding: 9px 8px !important;
    font-size: 0.68rem !important;
  }
  .student-id-table tbody td {
    padding: 9px 8px !important;
    font-size: 0.78rem !important;
  }
  .student-id-table tbody td .user-cell {
    gap: 8px !important;
  }
  .student-id-table tbody td .user-cell img {
    width: 34px !important;
    height: 34px !important;
  }
  .student-id-table tbody td .user-cell > div:first-child {
    width: 34px !important;
    height: 34px !important;
    font-size: 13px !important;
  }
  .att-pill-chip {
    padding: 2px 7px !important;
    font-size: 0.72rem !important;
  }
  .att-time-badge {
    padding: 2px 6px !important;
    font-size: 0.72rem !important;
  }
  .unmarked-pill {
    padding: 2px 6px !important;
    font-size: 0.70rem !important;
  }
  .punch-pill-group {
    padding: 2px !important;
    gap: 3px !important;
  }
  .punch-action-btn {
    width: 28px !important;
    height: 28px !important;
    font-size: 0.74rem !important;
  }

  /* Pagination Controls */
  .pagination-controls-wrap {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 12px !important;
    padding: 12px 14px !important;
  }
  .pagination-meta-group {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 8px !important;
    width: 100% !important;
  }
  .pagination-limit-box {
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
  }
  .pagination-limit-select {
    padding: 4px 8px !important;
    font-size: 0.75rem !important;
    height: 30px !important;
  }
  .pagination-nav {
    display: flex !important;
    justify-content: center !important;
    width: 100% !important;
    margin: 2px 0 !important;
  }
  .pagination-btn {
    min-width: 32px !important;
    height: 32px !important;
    font-size: 0.74rem !important;
    padding: 0 4px !important;
  }
  #roasterFooterSummary {
    text-align: center !important;
    width: 100% !important;
    font-size: 0.74rem !important;
  }

  /* Camera Scanner Modal Responsive */
  #scannerModal {
    padding: 10px !important;
  }
  #scannerModal .hrms-modal-card {
    width: 100% !important;
    max-width: min(520px, 96vw) !important;
    margin: auto !important;
    max-height: calc(100vh - 20px) !important;
    max-height: calc(100dvh - 20px) !important;
    border-radius: 16px !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
  }
  #scannerModal .hrms-modal-header {
    padding: 12px 16px !important;
    flex-shrink: 0 !important;
  }
  #scannerModal .hrms-modal-header h3 {
    font-size: 1.02rem !important;
  }
  #scannerModal .hrms-modal-body {
    padding: 14px 16px !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch !important;
    flex: 1 !important;
    max-height: calc(100dvh - 100px) !important;
  }
  .scanner-control-btn {
    width: 100% !important;
    height: 40px !important;
    font-size: 0.82rem !important;
    justify-content: center !important;
  }
  #manualQrInput {
    height: 38px !important;
    font-size: 0.82rem !important;
  }
  #scannerModal .hrms-modal-body button.roaster-add-btn {
    height: 38px !important;
    padding: 0 16px !important;
    font-size: 0.82rem !important;
  }

  /* Late Punch Tooltip Mobile */
  .late-punch-tooltip {
    width: min(270px, calc(100vw - 20px)) !important;
    padding: 10px 12px !important;
  }
  .late-preset-btn {
    min-height: 28px !important;
    padding: 4px 8px !important;
    font-size: 0.72rem !important;
  }
}

/* Mobile Phones (<= 480px) */
@media (max-width: 480px) {
  .stat-grid {
    gap: 8px !important;
  }
  .stat-card {
    padding: 8px 10px !important;
  }
  .stat-number {
    font-size: 1.15rem !important;
  }
  .stat-icon-wrapper {
    width: 26px !important;
    height: 26px !important;
    min-width: 26px !important;
    font-size: 0.72rem !important;
  }
  .hrms-card-header .card-title {
    font-size: 0.90rem !important;
  }
}

/* Micro Devices (<= 360px) */
@media (max-width: 360px) {
  .hrms-card-header form {
    grid-template-columns: 1fr !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR STUDENT ATTENDANCE
   ══════════════════════════════════════════════════════════════════════ */
/* 1. Stat Summary Cards */
[data-theme="dark"] .stat-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .stat-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-number {
  color: #f8fafc !important;
}
[data-theme="dark"] .stat-card-footer {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-card-footer span strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .stat-card-progress {
  background: #334155 !important;
}
[data-theme="dark"] .stat-card.blue .stat-icon-wrapper {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
}
[data-theme="dark"] .stat-card.green .stat-icon-wrapper {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
}
[data-theme="dark"] .stat-card.amber .stat-icon-wrapper {
  background: rgba(245, 158, 11, 0.15) !important;
  color: #fbbf24 !important;
  border-color: rgba(245, 158, 11, 0.3) !important;
}
[data-theme="dark"] .stat-card.red .stat-icon-wrapper {
  background: rgba(239, 68, 68, 0.15) !important;
  color: #f87171 !important;
  border-color: rgba(239, 68, 68, 0.3) !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #0284c7"],
[data-theme="dark"] .stat-card-footer span[style*="color:#0284c7"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #059669"],
[data-theme="dark"] .stat-card-footer span[style*="color:#059669"] {
  color: #34d399 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #d97706"],
[data-theme="dark"] .stat-card-footer span[style*="color:#d97706"] {
  color: #fbbf24 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #dc2626"],
[data-theme="dark"] .stat-card-footer span[style*="color:#dc2626"] {
  color: #f87171 !important;
}

/* 2. Main Attendance Register Card & Header */
[data-theme="dark"] .hrms-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .hrms-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .hrms-card-header h3.card-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-card-header h3.card-title span,
[data-theme="dark"] .attendance-date-badge {
  background: #0f172a !important;
  color: #cbd5e1 !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .hrms-card-header p {
  color: #94a3b8 !important;
}
[data-theme="dark"] .hrms-card-header p strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .att-filter-date,
[data-theme="dark"] .att-filter-select,
[data-theme="dark"] .hrms-card-header form input[type="date"],
[data-theme="dark"] .hrms-card-header form select[name="standard"] {
  background: #0f172a !important;
  color: #f8fafc !important;
  border: 1px solid #334155 !important;
  border-radius: 6px !important;
  color-scheme: dark !important;
}
[data-theme="dark"] .att-filter-date:focus,
[data-theme="dark"] .att-filter-select:focus,
[data-theme="dark"] .hrms-card-header form input[type="date"]:focus,
[data-theme="dark"] .hrms-card-header form select[name="standard"]:focus {
  background: #18263e !important;
  border-color: var(--hrms-primary, #0092df) !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .att-filter-date::-webkit-calendar-picker-indicator,
[data-theme="dark"] .hrms-card-header form input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(1) brightness(0.9);
}
[data-theme="dark"] .att-filter-select option,
[data-theme="dark"] .hrms-card-header form select[name="standard"] option {
  background: #0f172a !important;
  color: #f8fafc !important;
}

/* 3. Table Structure & Roster Cells */
[data-theme="dark"] .student-id-table thead th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .student-id-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .student-id-table tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.02) !important;
}

/* Table Empty State */
[data-theme="dark"] .student-id-table tbody td div[style*="background: #f1f5f9"],
[data-theme="dark"] .student-id-table tbody td div[style*="background:#f1f5f9"] {
  background: #334155 !important;
  color: #94a3b8 !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .student-id-table tbody td h4 {
  color: #f8fafc !important;
}
[data-theme="dark"] .student-id-table tbody td p strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .student-id-table tbody td .roaster-btn.secondary {
  background: #334155 !important;
  color: #f1f5f9 !important;
  border-color: #475569 !important;
}

/* Student Profile Cell */
[data-theme="dark"] .student-id-table tbody td .user-cell img {
  border-color: #334155 !important;
}
[data-theme="dark"] .student-id-table tbody td .user-cell > div > div:first-child {
  color: #f8fafc !important;
}
[data-theme="dark"] .student-id-table tbody td .user-cell > div > div:last-child {
  color: #94a3b8 !important;
}
[data-theme="dark"] .student-id-table tbody td .user-cell > div[style*="background: #f1f5f9"],
[data-theme="dark"] .student-id-table tbody td .user-cell > div[style*="background:#f1f5f9"] {
  background: #334155 !important;
  color: #f8fafc !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .sid-blood-badge {
  background: rgba(225, 29, 72, 0.15) !important;
  border-color: rgba(225, 29, 72, 0.3) !important;
  color: #fb7185 !important;
}

/* Standard & Roll Pills */
[data-theme="dark"] .sid-std-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .sid-std-pill i {
  color: #94a3b8 !important;
}
[data-theme="dark"] .sid-roll-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}

/* Attendance Status Chips */
[data-theme="dark"] .att-pill-chip.high {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
  color: #34d399 !important;
}
[data-theme="dark"] .att-pill-chip.medium {
  background: rgba(245, 158, 11, 0.15) !important;
  border-color: rgba(245, 158, 11, 0.3) !important;
  color: #fbbf24 !important;
}
[data-theme="dark"] .att-pill-chip.low {
  background: rgba(239, 68, 68, 0.15) !important;
  border-color: rgba(239, 68, 68, 0.3) !important;
  color: #f87171 !important;
}
[data-theme="dark"] .unmarked-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

/* Check-In Timestamp */
[data-theme="dark"] .att-time-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .att-time-badge i {
  color: #38bdf8 !important;
}

/* Quick Punch Segmented Control */
[data-theme="dark"] .punch-pill-group {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .punch-action-btn.present:not(.active) {
  color: #34d399 !important;
}
[data-theme="dark"] .punch-action-btn.late:not(.active) {
  color: #fbbf24 !important;
}
[data-theme="dark"] .punch-action-btn.absent:not(.active) {
  color: #f87171 !important;
}
[data-theme="dark"] .punch-action-btn:hover:not(.active) {
  background: #1e293b !important;
}

/* 4. Pagination Controls & Footer Summary */
[data-theme="dark"] .pagination-controls-wrap {
  background: #1e293b !important;
  border-top-color: #334155 !important;
}
[data-theme="dark"] .pagination-meta-group span {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pagination-meta-group span strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-limit-box label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pagination-limit-select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
  border-radius: 6px !important;
}
[data-theme="dark"] .pagination-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .pagination-btn:hover:not(.disabled) {
  background: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-btn.active {
  background: var(--hrms-primary, #0092df) !important;
  border-color: var(--hrms-primary, #0092df) !important;
  color: #ffffff !important;
}
[data-theme="dark"] .pagination-btn.disabled {
  background: #0f172a !important;
  border-color: #1e293b !important;
  color: #475569 !important;
  opacity: 0.5 !important;
}
[data-theme="dark"] .pagination-ellipsis {
  color: #64748b !important;
}
[data-theme="dark"] #roasterFooterSummary {
  color: #94a3b8 !important;
}
[data-theme="dark"] #roasterFooterSummary span[style*="color: #059669"],
[data-theme="dark"] #roasterFooterSummary span[style*="color:#059669"] {
  color: #34d399 !important;
}
[data-theme="dark"] #roasterFooterSummary span[style*="color: #d97706"],
[data-theme="dark"] #roasterFooterSummary span[style*="color:#d97706"] {
  color: #fbbf24 !important;
}
[data-theme="dark"] #roasterFooterSummary span[style*="color: #dc2626"],
[data-theme="dark"] #roasterFooterSummary span[style*="color:#dc2626"] {
  color: #f87171 !important;
}
[data-theme="dark"] #roasterFooterSummary span[style*="color: #64748b"],
[data-theme="dark"] #roasterFooterSummary span[style*="color:#64748b"] {
  color: #94a3b8 !important;
}

/* 5. Live Camera QR Scanner Modal */
[data-theme="dark"] #scannerModal .hrms-modal-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
}
[data-theme="dark"] #scannerModal .hrms-modal-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] #scannerModal .hrms-modal-header > div:first-child > div:first-child {
  background: rgba(2, 132, 199, 0.2) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] #scannerModal .hrms-modal-header h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] #scannerModal .hrms-modal-header p {
  color: #94a3b8 !important;
}
[data-theme="dark"] #scannerModal .scholars-count-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] #scannerModal .scholars-count-badge span[style*="color: #0284c7"],
[data-theme="dark"] #scannerModal .scholars-count-badge span[style*="color:#0284c7"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] #scannerModal .modal-close-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] #scannerModal .modal-close-btn:hover {
  background: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #scannerModal .hrms-modal-body p {
  color: #94a3b8 !important;
}
[data-theme="dark"] #scannerModal .hrms-modal-body p strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .scanner-control-btn.stop {
  background: rgba(239, 68, 68, 0.15) !important;
  color: #f87171 !important;
  border: 1.5px solid rgba(239, 68, 68, 0.35) !important;
}
[data-theme="dark"] .scanner-control-btn.stop:hover {
  background: rgba(239, 68, 68, 0.25) !important;
  color: #fca5a5 !important;
  border-color: rgba(239, 68, 68, 0.5) !important;
}
[data-theme="dark"] #scanResultBox[style*="background: #dcfce7"],
[data-theme="dark"] #scanResultBox[style*="background:#dcfce7"] {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.35) !important;
  color: #34d399 !important;
}
[data-theme="dark"] #scanResultBox[style*="background: #fee2e2"],
[data-theme="dark"] #scanResultBox[style*="background:#fee2e2"] {
  background: rgba(239, 68, 68, 0.15) !important;
  border-color: rgba(239, 68, 68, 0.35) !important;
  color: #f87171 !important;
}
[data-theme="dark"] #scannerModal .hrms-modal-body div[style*="border-top: 1px dashed"] {
  border-top-color: #334155 !important;
}
[data-theme="dark"] #scannerModal .hrms-modal-body label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] #scannerModal .hrms-modal-body label i {
  color: #38bdf8 !important;
}
[data-theme="dark"] #manualQrInput {
  background: #0f172a !important;
  color: #f8fafc !important;
  border: 1px solid #334155 !important;
  border-radius: 6px !important;
}
[data-theme="dark"] #manualQrInput::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] #manualQrInput:focus {
  background: #18263e !important;
  border-color: var(--hrms-primary, #0092df) !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}

/* 6. Late Punch Tooltip Popup */
[data-theme="dark"] .late-punch-tooltip {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.5), 0 0 0 1px #334155 !important;
}
[data-theme="dark"] .late-tooltip-arrow {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .late-tooltip-header {
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .late-tooltip-header i.fa-clock {
  color: #fbbf24 !important;
}
[data-theme="dark"] .late-tooltip-header span {
  color: #f8fafc !important;
}
[data-theme="dark"] .late-tooltip-close {
  color: #94a3b8 !important;
}
[data-theme="dark"] .late-tooltip-close:hover {
  background: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .late-tooltip-body {
  color: #cbd5e1 !important;
}
[data-theme="dark"] #lateStudentName {
  color: #f8fafc !important;
}
[data-theme="dark"] #lateStudentRoll {
  color: #38bdf8 !important;
}
[data-theme="dark"] .late-tooltip-body label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] #lateTimeInput {
  background: #0f172a !important;
  color: #f8fafc !important;
  border: 1px solid #334155 !important;
  border-radius: 6px !important;
  color-scheme: dark !important;
}
[data-theme="dark"] #lateTimeInput:focus {
  background: #18263e !important;
  border-color: #f59e0b !important;
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2) !important;
}
[data-theme="dark"] #lateTimeInput::-webkit-calendar-picker-indicator {
  filter: invert(1) brightness(0.9);
}
[data-theme="dark"] .late-preset-btn {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  border-radius: 6px !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .late-preset-btn:hover {
  background: #334155 !important;
  color: #f8fafc !important;
  border-color: #475569 !important;
}

/* 7. Toast Notification */
[data-theme="dark"] .punch-toast {
  background: #1e293b !important;
  color: #f8fafc !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5) !important;
}

</style>

<!-- KPI Stat Summary Cards -->
<div class="stat-grid" style="margin-bottom: 24px;">
  
  <!-- Total Registered -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Enrolled</span>
          <span class="stat-number"><?php echo $total_count; ?></span>
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
      <span>Academic Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
      <span style="color: #0284c7; font-weight: 700;"><i class="fa-solid fa-check"></i> Active</span>
    </div>
  </div>

  <!-- Present Today -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Present Today</span>
          <span class="stat-number" id="kpiPresentCount"><?php echo $present_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-circle-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" id="kpiPresentBar" style="width: <?php echo $present_rate; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span id="kpiPresentRate"><strong><?php echo $present_rate; ?>%</strong> Attendance Rate</span>
      <span style="color: #059669; font-weight: 700;">On-time</span>
    </div>
  </div>

  <!-- Late Arrival -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Late Arrival</span>
          <span class="stat-number" id="kpiLateCount"><?php echo $late_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-clock"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" id="kpiLateBar" style="width: <?php echo $late_rate; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span id="kpiLateRate"><strong><?php echo $late_rate; ?>%</strong> Late Rate</span>
      <span style="color: #d97706; font-weight: 700;">Timestamped</span>
    </div>
  </div>

  <!-- Unmarked / Absent -->
  <div class="stat-card red">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Pending / Absent</span>
          <span class="stat-number" id="kpiAbsentCount"><?php echo $absent_count + $unmarked_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-circle-xmark"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" id="kpiAbsentBar" style="width: <?php echo $absent_rate; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span id="kpiAbsentRate"><strong><?php echo $absent_rate; ?>%</strong> Absent Rate</span>
      <span style="color: #dc2626; font-weight: 700;"><?php echo $unmarked_count > 0 ? "{$unmarked_count} pending" : "All logged"; ?></span>
    </div>
  </div>

</div>

<!-- Main Attendance Register Workstation (Simple & Sober - Like student-id) -->
<div class="hrms-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
  <div class="hrms-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-clipboard-user" style="color: #0284c7;"></i>
        Attendance Register
        <span style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          <?php echo date('d M Y', strtotime($filter_date)); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Daily attendance roster &amp; biometric punch logs for <strong><?php echo date('l, d F Y', strtotime($filter_date)); ?></strong>
      </p>
    </div>

    <!-- Date, Class Filter & Quick Actions -->
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <form method="GET" action="<?php echo STAFF_URL; ?>/student-attendance" style="display: flex; gap: 8px; align-items: center; margin: 0;">
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
        <div style="position: relative;">
          <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="this.form.submit()" class="hrms-input" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; width: 140px; border: 1px solid #cbd5e1;">
        </div>
        
        <select name="standard" onchange="this.form.submit()" class="hrms-input" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; min-width: 140px; border: 1px solid #cbd5e1;">
          <option value="">All Academic Classes</option>
          <?php foreach ($standards as $std): ?>
            <option value="<?php echo $std; ?>" <?php echo $filter_standard === $std ? 'selected' : ''; ?>><?php echo $std; ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <!-- Start Camera Scanner Trigger Button -->
      <button type="button" onclick="openScannerModal();" class="roaster-add-btn" style="padding: 6px 14px; font-size: 0.8rem; gap: 7px;" title="Open Camera Scanner Popup">
        <div class="btn-icon-circle"><i class="fa-solid fa-camera"></i></div>
        <span>Camera Scanner</span>
      </button>
    </div>
  </div>

  <!-- Attendance Roster Table -->
  <div class="table-responsive">
    <table class="hrms-table student-id-table">
      <thead>
        <tr>
          <th style="min-width: 170px;">Student Profile</th>
          <th style="min-width: 110px;">Roll &amp; Admission</th>
          <th style="min-width: 130px;">Standard &amp; Section</th>
          <th style="min-width: 110px;">Status</th>
          <th style="min-width: 110px;">Check-In Time</th>
          <th style="min-width: 120px; text-align: right;">Quick Punch</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students_attendance)): ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-user-slash"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Scholars Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                No students in Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong> matched the selected criteria.
              </p>
              <a href="<?php echo STAFF_URL; ?>/student-attendance" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                <i class="fa-solid fa-rotate-left"></i> Reset Filter
              </a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($students_attendance as $row): ?>
            <?php 
              $has_photo = !empty($row['photo']) && $row['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $row['photo']);
              $first_letter = strtoupper(substr($row['student_name'], 0, 1));
              $cur_status = $row['today_status'] ?? '';
            ?>
            <tr>
              <!-- 1. Student Profile Cell -->
              <td>
                <div class="user-cell" style="display: flex; align-items: center; gap: 12px;">
                  <?php if ($has_photo): ?>
                    <img src="<?php echo htmlspecialchars($row['photo']); ?>" alt="<?php echo htmlspecialchars($row['student_name']); ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 14px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php else: ?>
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 14px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                      <?php echo htmlspecialchars($row['student_name']); ?>
                    </div>
                    <div style="font-size: 0.73rem; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 6px;">
                      <span><?php echo htmlspecialchars($row['gender'] ?? 'Scholar'); ?></span>
                      <?php if (!empty($row['blood_group'])): ?>
                        <span>&bull;</span>
                        <span class="sid-blood-badge">Blood: <?php echo htmlspecialchars($row['blood_group']); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 2. Roll & Admission -->
              <td>
                <span class="sid-roll-pill">
                  <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
                  <span><?php echo htmlspecialchars($row['roll_no']); ?></span>
                </span>
                <div style="font-size: 0.74rem; color: #64748b; margin-top: 4px;">
                  Adm: <?php echo htmlspecialchars($row['admission_no'] ?? '—'); ?>
                </div>
              </td>

              <!-- 3. Standard & Section -->
              <td>
                <span class="sid-std-pill">
                  <i class="fa-solid fa-graduation-cap" style="font-size: 0.65rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($row['standard']); ?></span>
                </span>
                <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;">
                  Section <?php echo htmlspecialchars($row['section'] ?? 'A'); ?>
                </div>
              </td>

              <!-- 4. Status Badge Cell -->
              <td id="statusCell-<?php echo $row['id']; ?>">
                <?php if ($cur_status === 'Present'): ?>
                  <span class="att-pill-chip high">
                    <span class="legend-dot green"></span> Present
                  </span>
                <?php elseif ($cur_status === 'Late'): ?>
                  <span class="att-pill-chip medium">
                    <span class="legend-dot amber"></span> Late
                  </span>
                <?php elseif ($cur_status === 'Absent'): ?>
                  <span class="att-pill-chip low">
                    <span class="legend-dot red"></span> Absent
                  </span>
                <?php else: ?>
                  <span class="unmarked-pill">
                    <i class="fa-solid fa-minus" style="font-size: 0.6rem;"></i> Unmarked
                  </span>
                <?php endif; ?>
              </td>

              <!-- 5. Check-In Timestamp -->
              <td id="checkInCell-<?php echo $row['id']; ?>">
                <?php if (!empty($row['check_in_time']) && $cur_status !== 'Absent'): ?>
                  <span class="att-time-badge">
                    <i class="fa-regular fa-clock" style="color: #0284c7; font-size: 0.68rem;"></i>
                    <?php echo date('h:i A', strtotime($row['check_in_time'])); ?>
                  </span>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.75rem; font-weight: 600;">—</span>
                <?php endif; ?>
              </td>

              <!-- 6. Quick Punch Segmented Group -->
              <td style="text-align: right;">
                <div class="punch-pill-group" id="punchGroup-<?php echo $row['id']; ?>">
                  
                  <!-- P: Present -->
                  <button type="button" 
                          onclick="punchStudent(<?php echo $row['id']; ?>, 'Present', this)" 
                          class="punch-action-btn present <?php echo $cur_status === 'Present' ? 'active' : ''; ?>" 
                          title="Mark Present">
                    P
                  </button>

                  <!-- L: Late (Opens small tooltip popup for entering late time) -->
                  <button type="button" 
                          onclick="openLateTooltip(this, <?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['student_name'])); ?>', '<?php echo addslashes(htmlspecialchars($row['roll_no'])); ?>', '<?php echo (!empty($row['check_in_time']) && $cur_status === 'Late') ? date('H:i', strtotime($row['check_in_time'])) : ''; ?>')" 
                          class="punch-action-btn late <?php echo $cur_status === 'Late' ? 'active' : ''; ?>" 
                          title="Mark Late &amp; Enter Time">
                    L
                  </button>

                  <!-- A: Absent -->
                  <button type="button" 
                          onclick="punchStudent(<?php echo $row['id']; ?>, 'Absent', this)" 
                          class="punch-action-btn absent <?php echo $cur_status === 'Absent' ? 'active' : ''; ?>" 
                          title="Mark Absent">
                    A
                  </button>

                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

    <!-- Table Pagination & Per-Page Controls -->
    <div class="pagination-controls-wrap">
      
      <!-- Left: Showing summary & Limit Selector -->
      <div class="pagination-meta-group">
        <span style="font-size: 0.82rem; color: #475569;">
          <?php if ($total_records > 0): ?>
            Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> scholars
          <?php else: ?>
            Showing <strong>0</strong> scholars
          <?php endif; ?>
        </span>

        <div class="pagination-limit-box">
          <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
          <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
            <?php foreach ($allowed_limits as $l): ?>
              <option value="<?php echo htmlspecialchars(get_attendance_pagination_url(1, $filter_date, $filter_standard, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
                <?php echo $l; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Center: Navigation page buttons -->
      <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav" aria-label="Student Attendance Pagination">
          <!-- Prev Page -->
          <a href="<?php echo $page > 1 ? htmlspecialchars(get_attendance_pagination_url($page - 1, $filter_date, $filter_standard, $limit)) : '#'; ?>" 
             class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
             aria-label="Previous Page"
             title="Previous Page">
            <i class="fa-solid fa-chevron-left" style="font-size: 0.72rem;"></i>
          </a>

          <!-- Page Numbers -->
          <?php
            $range = 2;
            for ($p = 1; $p <= $total_pages; $p++):
              if ($p == 1 || $p == $total_pages || ($p >= $page - $range && $p <= $page + $range)):
          ?>
            <a href="<?php echo htmlspecialchars(get_attendance_pagination_url($p, $filter_date, $filter_standard, $limit)); ?>" 
               class="pagination-btn <?php echo $p === $page ? 'active' : ''; ?>">
              <?php echo $p; ?>
            </a>
          <?php 
              elseif (($p == 2 && $page - $range > 2) || ($p == $total_pages - 1 && $page + $range < $total_pages - 1)):
          ?>
            <span class="pagination-ellipsis">&hellip;</span>
          <?php 
              endif;
            endfor;
          ?>

          <!-- Next Page -->
          <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_attendance_pagination_url($page + 1, $filter_date, $filter_standard, $limit)) : '#'; ?>" 
             class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
             aria-label="Next Page"
             title="Next Page">
            <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
          </a>
        </nav>
      <?php endif; ?>

      <!-- Right: Summary status counts -->
      <div id="roasterFooterSummary" style="font-size: 0.76rem; color: #475569;">
        <span style="color: #059669; font-weight: 700;"><?php echo $present_count; ?> Present</span> &bull; 
        <span style="color: #d97706; font-weight: 700;"><?php echo $late_count; ?> Late</span> &bull; 
        <span style="color: #dc2626; font-weight: 700;"><?php echo $absent_count; ?> Absent</span>
        <?php if ($unmarked_count > 0): ?>
          &bull; <span style="color: #64748b; font-weight: 600;"><?php echo $unmarked_count; ?> Unmarked</span>
        <?php endif; ?>
      </div>

    </div>

  </div>

</div>

<!-- Live Camera QR Scanner Modal Popup -->
<div class="hrms-modal" id="scannerModal">
  <div class="hrms-modal-card" style="max-width: 520px; border-radius: 18px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);">
    
    <!-- Modal Header -->
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px; background: #ffffff; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(0, 146, 223, 0.1); color: var(--hrms-primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
          <i class="fa-solid fa-qrcode"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
            Live QR Code Scanner
          </h3>
          <p style="margin: 2px 0 0; font-size: 0.76rem; color: #64748b;">Instant Scholar Punch-In</p>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 10px;">
        <div class="scholars-count-badge" style="padding: 4px 10px; font-size: 0.72rem;">
          <span class="count-dot"></span>
          <span style="font-weight: 800; color: #0284c7;">AUTO-PUNCH</span>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeScannerModal();" aria-label="Close scanner modal" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>

    <!-- Modal Body -->
    <div class="hrms-modal-body" style="padding: 22px;">
      <p style="margin: 0 0 14px; font-size: 0.82rem; color: #64748b; line-height: 1.45;">
        Aim device camera at scholar's <strong>Student ID Card QR Code</strong> or barcode for automatic attendance logging.
      </p>

      <!-- Camera Viewport Box with Reticle Guides & Laser Sweep -->
      <div class="scanner-viewport-box" id="qrScannerContainer" style="margin-bottom: 14px;">
        <div id="qrReader" style="width: 100%;"></div>

        <!-- Idle Overlay (shown before camera begins) -->
        <div class="scanner-idle-placeholder" id="scannerIdleMsg" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; max-width: 320px; text-align: center; margin: 0; padding: 20px; z-index: 2;">
          <div class="scanner-radar-pulse">
            <i class="fa-solid fa-camera"></i>
          </div>
          <h4 style="margin: 0 0 4px; font-size: 0.95rem; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;">
            Starting Camera...
          </h4>
          <p style="margin: 0; font-size: 0.75rem; color: #94a3b8;">
            Please ensure camera permissions are allowed
          </p>
        </div>

        <!-- Corner Target Guides -->
        <div class="scanner-reticle" id="scannerReticle" style="display: none;">
          <div class="scanner-reticle-corner top-left"></div>
          <div class="scanner-reticle-corner top-right"></div>
          <div class="scanner-reticle-corner bottom-left"></div>
          <div class="scanner-reticle-corner bottom-right"></div>
          <div class="scanner-laser-line"></div>
        </div>
      </div>

      <!-- Scanner Control Buttons -->
      <div style="display: flex; gap: 10px; margin-bottom: 14px;">
        <button type="button" id="startScannerBtn" onclick="startScanner();" class="scanner-control-btn start" style="display: none;">
          <i class="fa-solid fa-video"></i> Turn On Camera
        </button>
        <button type="button" id="stopScannerBtn" onclick="stopScanner();" class="scanner-control-btn stop">
          <i class="fa-solid fa-power-off"></i> Turn Off Camera
        </button>
      </div>

      <!-- Live Scan Result Feedback Box -->
      <div id="scanResultBox" style="display: none; margin-bottom: 14px; padding: 12px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 700; box-shadow: 0 2px 8px rgba(0,0,0,0.04);"></div>

      <!-- Manual Code Punch Bar -->
      <div style="padding-top: 14px; border-top: 1px dashed #e2e8f0;">
        <label style="display: flex; align-items: center; gap: 6px; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 8px;">
          <i class="fa-solid fa-keyboard" style="color: var(--hrms-primary);"></i>
          Or Type Roll Number / QR Hash Manually:
        </label>
        <div style="display: flex; gap: 8px;">
          <input type="text" id="manualQrInput" placeholder="e.g. KSV-2026-926 or ADM-101" class="hrms-input" style="flex: 1; padding: 8px 12px; font-size: 0.85rem;" onkeypress="if(event.key==='Enter') submitManualQr();">
          <button type="button" onclick="submitManualQr();" class="roaster-add-btn" style="padding: 8px 16px; font-size: 0.82rem;">
            <span>Punch</span>
          </button>
        </div>
      </div>

    </div>

  </div>
</div>

<!-- Audio element for QR beep feedback -->
<audio id="scanBeepSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>

<script>
let html5QrCode = null;

function killAllCameraTracks() {
  // Explicitly terminate all video track streams from DOM video elements to guarantee hardware camera indicator shuts off
  const videoElements = document.querySelectorAll('#qrReader video, #qrScannerContainer video');
  videoElements.forEach(video => {
    if (video.srcObject) {
      const stream = video.srcObject;
      if (stream && stream.getTracks) {
        stream.getTracks().forEach(track => {
          try {
            track.stop();
          } catch (e) {}
        });
      }
      video.srcObject = null;
    }
  });
}

function openScannerModal() {
  const modal = document.getElementById('scannerModal');
  if (modal) {
    modal.classList.add('show');
    // Automatically turn on scanner camera when popup opens
    setTimeout(() => {
      startScanner();
    }, 200);
  }
}

function closeScannerModal() {
  const modal = document.getElementById('scannerModal');
  if (modal) {
    modal.classList.remove('show');
  }
  // Turn off scanner and hardware camera immediately
  stopScanner();
  killAllCameraTracks();
}

// Close on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeScannerModal();
});

// Close on clicking backdrop outside card
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('scannerModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) closeScannerModal();
    });
  }
});

function startScanner() {
  const idleMsg = document.getElementById('scannerIdleMsg');
  const reticle = document.getElementById('scannerReticle');
  if (idleMsg) {
    idleMsg.style.display = 'none';
    const h4 = idleMsg.querySelector('h4');
    const p = idleMsg.querySelector('p');
    if (h4) h4.textContent = 'Camera Initializing...';
    if (p) p.textContent = 'Please ensure camera permissions are allowed';
  }
  if (reticle) reticle.style.display = 'block';

  document.getElementById('startScannerBtn').style.display = 'none';
  document.getElementById('stopScannerBtn').style.display = 'inline-flex';

  if (!html5QrCode) {
    html5QrCode = new Html5Qrcode("qrReader");
  }

  const config = { fps: 10, qrbox: { width: 220, height: 220 } };

  html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
    .catch(err => {
      console.warn("Unable to start camera scanner, falling back to manual mode:", err);
      if (idleMsg) {
        idleMsg.style.display = 'block';
        const h4 = idleMsg.querySelector('h4');
        const p = idleMsg.querySelector('p');
        if (h4) h4.textContent = 'Camera Unavailable';
        if (p) p.textContent = 'Camera blocked or not found. Enter Roll Number manually below.';
      }
      if (reticle) reticle.style.display = 'none';
      document.getElementById('startScannerBtn').style.display = 'inline-flex';
      document.getElementById('stopScannerBtn').style.display = 'none';
      showScanFeedback(false, "Camera access unavailable or blocked. You can enter the Roll Number manually below.");
    });
}

function stopScanner() {
  const idleMsg = document.getElementById('scannerIdleMsg');
  const reticle = document.getElementById('scannerReticle');
  const startBtn = document.getElementById('startScannerBtn');
  const stopBtn = document.getElementById('stopScannerBtn');

  if (startBtn) startBtn.style.display = 'inline-flex';
  if (stopBtn) stopBtn.style.display = 'none';

  if (idleMsg) {
    idleMsg.style.display = 'block';
    const h4 = idleMsg.querySelector('h4');
    const p = idleMsg.querySelector('p');
    if (h4) h4.textContent = 'Camera Turned Off';
    if (p) p.textContent = "Click 'Turn On Camera' below to reactivate";
  }
  if (reticle) reticle.style.display = 'none';

  if (html5QrCode) {
    try {
      if (html5QrCode.isScanning) {
        html5QrCode.stop().then(() => {
          killAllCameraTracks();
        }).catch(err => {
          console.warn("Stop scanner error:", err);
          killAllCameraTracks();
        });
      } else {
        killAllCameraTracks();
      }
    } catch (e) {
      killAllCameraTracks();
    }
  } else {
    killAllCameraTracks();
  }
}

function onScanSuccess(decodedText, decodedResult) {
  // Beep sound
  try {
    const beep = document.getElementById('scanBeepSound');
    if (beep) beep.play();
  } catch (e) {}

  postAttendancePunch(decodedText);
}

function onScanFailure(error) {
  // ignore continuous frame failures
}

function submitManualQr() {
  const val = document.getElementById('manualQrInput').value.trim();
  if (!val) {
    alert('Please enter a roll number or QR code string');
    return;
  }
  postAttendancePunch(val);
}

function postAttendancePunch(qrCodeValue) {
  const formData = new FormData();
  formData.append('action', 'mark_attendance');
  formData.append('qr_data', qrCodeValue);
  formData.append('status', 'Present');
  formData.append('log_date', '<?php echo $filter_date; ?>');
  formData.append('ajax', '1');

  fetch('<?php echo STAFF_URL; ?>/student-attendance', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showScanFeedback(true, '🎉 ' + data.message + ' at ' + data.time);
      setTimeout(() => {
        window.location.reload();
      }, 1200);
    } else {
      showScanFeedback(false, '⚠️ ' + data.message);
    }
  })
  .catch(err => {
    showScanFeedback(false, 'Network error while recording punch.');
  });
}

function showScanFeedback(success, text) {
  const box = document.getElementById('scanResultBox');
  box.style.display = 'block';
  box.style.background = success ? '#dcfce7' : '#fee2e2';
  box.style.color = success ? '#15803d' : '#b91c1c';
  box.style.border = '1px solid ' + (success ? '#86efac' : '#fca5a5');
  box.innerText = text;
}

// ==========================================
// Quick Punch & Late Tooltip Functions
// ==========================================
let currentLateBtn = null;

function getCurrentTimeFormatted() {
  const d = new Date();
  const h = String(d.getHours()).padStart(2, '0');
  const m = String(d.getMinutes()).padStart(2, '0');
  return `${h}:${m}`;
}

function openLateTooltip(btn, studentId, studentName, rollNo, curTime) {
  currentLateBtn = btn;
  const tooltip = document.getElementById('latePunchTooltip');
  if (!tooltip) return;

  document.getElementById('lateStudentId').value = studentId;
  document.getElementById('lateStudentName').textContent = studentName;
  document.getElementById('lateStudentRoll').textContent = rollNo;
  document.getElementById('lateTimeInput').value = curTime ? curTime : getCurrentTimeFormatted();

  tooltip.style.display = 'block';

  // Calculate coordinates relative to viewport
  const rect = btn.getBoundingClientRect();
  const tipWidth = 250;
  const tipHeight = 175;

  let left = rect.left + (rect.width / 2) - (tipWidth / 2);
  if (left < 10) left = 10;
  if (left + tipWidth > window.innerWidth - 10) {
    left = window.innerWidth - tipWidth - 10;
  }

  let top = rect.top - tipHeight - 8;
  let isAbove = true;
  if (top < 10) {
    top = rect.bottom + 8;
    isAbove = false;
  }

  tooltip.style.left = `${left}px`;
  tooltip.style.top = `${top}px`;

  const arrow = tooltip.querySelector('.late-tooltip-arrow');
  if (arrow) {
    const arrowLeft = (rect.left + rect.width / 2) - left;
    arrow.style.left = `${Math.max(12, Math.min(tipWidth - 16, arrowLeft))}px`;
    arrow.className = isAbove ? 'late-tooltip-arrow arrow-bottom' : 'late-tooltip-arrow arrow-top';
  }

  setTimeout(() => {
    const inp = document.getElementById('lateTimeInput');
    if (inp) {
      inp.focus();
      inp.select();
    }
  }, 60);
}

function closeLateTooltip() {
  const tooltip = document.getElementById('latePunchTooltip');
  if (tooltip) {
    tooltip.style.display = 'none';
  }
  currentLateBtn = null;
}

function setLatePreset(preset) {
  const inp = document.getElementById('lateTimeInput');
  if (!inp) return;
  if (preset === 'now') {
    inp.value = getCurrentTimeFormatted();
  } else {
    inp.value = preset;
  }
}

function submitLatePunch() {
  const studentId = document.getElementById('lateStudentId').value;
  const timeVal = document.getElementById('lateTimeInput').value || getCurrentTimeFormatted();
  if (!studentId) return;

  punchStudent(studentId, 'Late', currentLateBtn, timeVal);
  closeLateTooltip();
}

function punchStudent(studentId, status, btnElement, checkInTime) {
  const group = document.getElementById(`punchGroup-${studentId}`);
  if (group) group.style.opacity = '0.6';

  const formData = new FormData();
  formData.append('action', 'mark_attendance');
  formData.append('student_id', studentId);
  formData.append('status', status);
  formData.append('log_date', '<?php echo $filter_date; ?>');
  if (checkInTime) {
    formData.append('check_in_time', checkInTime);
  }
  formData.append('ajax', '1');

  fetch('<?php echo STAFF_URL; ?>/student-attendance', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (group) group.style.opacity = '1';

    if (data.success) {
      // 1. Update Status Cell
      const statusCell = document.getElementById(`statusCell-${studentId}`);
      if (statusCell) {
        if (status === 'Present') {
          statusCell.innerHTML = `<span class="att-pill-chip high" style="font-size: 0.72rem; padding: 2px 7px;"><span class="legend-dot green"></span> Present</span>`;
        } else if (status === 'Late') {
          statusCell.innerHTML = `<span class="att-pill-chip medium" style="font-size: 0.72rem; padding: 2px 7px;"><span class="legend-dot amber"></span> Late</span>`;
        } else if (status === 'Absent') {
          statusCell.innerHTML = `<span class="att-pill-chip low" style="font-size: 0.72rem; padding: 2px 7px;"><span class="legend-dot red"></span> Absent</span>`;
        }
      }

      // 2. Update Check-In Cell
      const checkInCell = document.getElementById(`checkInCell-${studentId}`);
      if (checkInCell) {
        if (status === 'Absent' || !data.formatted_time || data.formatted_time === '—') {
          checkInCell.innerHTML = `<span style="color: #94a3b8; font-size: 0.75rem; font-weight: 600;">—</span>`;
        } else {
          checkInCell.innerHTML = `<span class="att-time-badge"><i class="fa-regular fa-clock" style="color: var(--hrms-primary); font-size: 0.68rem;"></i> ${data.formatted_time}</span>`;
        }
      }

      // 3. Update Punch Buttons active state
      if (group) {
        group.querySelectorAll('.punch-action-btn').forEach(b => b.classList.remove('active'));
        const activeBtn = group.querySelector(`.punch-action-btn.${status.toLowerCase()}`);
        if (activeBtn) activeBtn.classList.add('active');

        // Update the L button's onclick to remember the new late time
        const lateBtn = group.querySelector('.punch-action-btn.late');
        if (lateBtn && status === 'Late' && data.input_time) {
          const stuName = data.student.student_name.replace(/'/g, "\\'");
          const stuRoll = data.student.roll_no.replace(/'/g, "\\'");
          lateBtn.setAttribute('onclick', `openLateTooltip(this, ${studentId}, '${stuName}', '${stuRoll}', '${data.input_time}')`);
        }
      }

      // 4. Update KPI stat summary
      if (data.day_stats) {
        const total = <?php echo $total_count; ?>;
        const pCount = data.day_stats.present;
        const lCount = data.day_stats.late;
        const aCount = data.day_stats.absent;
        const pRate = total > 0 ? ((pCount / total) * 100).toFixed(1) : '0';
        const lRate = total > 0 ? ((lCount / total) * 100).toFixed(1) : '0';
        const aRate = total > 0 ? (((total - pCount - lCount) / total) * 100).toFixed(1) : '0';

        const kpiP = document.getElementById('kpiPresentCount');
        const kpiL = document.getElementById('kpiLateCount');
        const kpiA = document.getElementById('kpiAbsentCount');
        const kpiPRate = document.getElementById('kpiPresentRate');
        const kpiLRate = document.getElementById('kpiLateRate');
        const kpiARate = document.getElementById('kpiAbsentRate');
        const kpiPBar = document.getElementById('kpiPresentBar');
        const kpiLBar = document.getElementById('kpiLateBar');
        const kpiABar = document.getElementById('kpiAbsentBar');

        if (kpiP) kpiP.textContent = pCount;
        if (kpiL) kpiL.textContent = lCount;
        if (kpiA) kpiA.textContent = aCount;
        if (kpiPRate) kpiPRate.innerHTML = `<strong>${pRate}%</strong> Attendance Rate`;
        if (kpiLRate) kpiLRate.innerHTML = `<strong>${lRate}%</strong> Late Rate`;
        if (kpiARate) kpiARate.innerHTML = `<strong>${aRate}%</strong> Absent Rate`;
        if (kpiPBar) kpiPBar.style.width = `${pRate}%`;
        if (kpiLBar) kpiLBar.style.width = `${lRate}%`;
        if (kpiABar) kpiABar.style.width = `${aRate}%`;

        const foot = document.getElementById('roasterFooterSummary');
        if (foot) {
          foot.innerHTML = `<span style="color: #059669; font-weight: 700;">${pCount} Present</span> &bull; <span style="color: #d97706; font-weight: 700;">${lCount} Late</span> &bull; <span style="color: #dc2626; font-weight: 700;">${aCount} Absent</span>`;
        }
      }

      showPunchToast(data.message);
    } else {
      alert(data.message || 'Error marking attendance');
    }
  })
  .catch(err => {
    if (group) group.style.opacity = '1';
    console.error('Punch error:', err);
    alert('Network error while marking punch.');
  });
}

function showPunchToast(message) {
  let toast = document.getElementById('punchToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'punchToast';
    toast.className = 'punch-toast';
    document.body.appendChild(toast);
  }
  toast.innerHTML = `<i class="fa-solid fa-circle-check" style="color: #10b981;"></i> <span>${message}</span>`;
  toast.style.display = 'flex';
  clearTimeout(window.punchToastTimer);
  window.punchToastTimer = setTimeout(() => {
    toast.style.display = 'none';
  }, 2400);
}

// Click outside or press Escape to close late tooltip
document.addEventListener('click', function(e) {
  const tooltip = document.getElementById('latePunchTooltip');
  if (tooltip && tooltip.style.display === 'block') {
    if (!tooltip.contains(e.target) && !e.target.closest('.punch-action-btn.late')) {
      closeLateTooltip();
    }
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeLateTooltip();
  }
});
</script>

<!-- Floating Tooltip Popup for Entering Late Time -->
<div id="latePunchTooltip" class="late-punch-tooltip" style="display: none;">
  <div class="late-tooltip-arrow arrow-bottom"></div>
  <div class="late-tooltip-header">
    <div style="display: flex; align-items: center; gap: 6px;">
      <i class="fa-solid fa-clock" style="color: #d97706; font-size: 0.85rem;"></i>
      <span style="font-weight: 800; font-size: 0.82rem; color: #0f172a;">Late Check-In</span>
    </div>
    <button type="button" class="late-tooltip-close" onclick="closeLateTooltip();" aria-label="Close">&times;</button>
  </div>
  
  <div class="late-tooltip-body">
    <div style="font-size: 0.74rem; color: #64748b; margin-bottom: 8px;">
      <strong id="lateStudentName" style="color: #0f172a;"></strong> 
      (<span id="lateStudentRoll"></span>)
    </div>

    <input type="hidden" id="lateStudentId" value="">

    <div style="margin-bottom: 8px;">
      <label for="lateTimeInput" style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; margin-bottom: 4px;">
        Arrival / Punch Time:
      </label>
      <input type="time" id="lateTimeInput" class="hrms-input" style="padding: 6px 10px; font-size: 0.85rem; width: 100%; border-radius: 6px; font-weight: 700; color: #0f172a; text-align: center;" onkeypress="if(event.key==='Enter') submitLatePunch();">
    </div>

    <!-- Quick Presets -->
    <div style="display: flex; gap: 4px; margin-bottom: 10px; justify-content: center;">
      <button type="button" class="late-preset-btn" onclick="setLatePreset('now')">Now</button>
      <button type="button" class="late-preset-btn" onclick="setLatePreset('09:15')">9:15</button>
      <button type="button" class="late-preset-btn" onclick="setLatePreset('09:30')">9:30</button>
      <button type="button" class="late-preset-btn" onclick="setLatePreset('10:00')">10:00</button>
    </div>

    <button type="button" id="confirmLatePunchBtn" onclick="submitLatePunch();" class="late-punch-submit-btn">
      <i class="fa-solid fa-check"></i> Punch Late
    </button>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
