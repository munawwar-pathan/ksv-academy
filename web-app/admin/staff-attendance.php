<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
if ($current_user['role'] === 'staff') {
    require_menu_permission('admin_staff_attendance');
}
$page_title = 'Faculty Attendance & QR Biometric Scanner | KSV Academy HRMS';
$page_heading = 'Staff Attendance';
$page_subheading = 'Real-time Camera QR Biometric Punch-In, Daily Roll Register & Automated Logging';
$active_menu = 'staff-attendance';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];

$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_dept = $_GET['department'] ?? '';

// Handle Punch In / Mark Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Mark single staff attendance (via manual button or QR scan)
    if ($action === 'mark_staff_attendance') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
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

        // If scanned via QR string, resolve staff_id
        if ($staff_id === 0 && !empty($qr_data)) {
            $stmt = $db->prepare("SELECT id, employee_code FROM staff_profiles WHERE qr_code = ? OR employee_code = ? OR CONCAT('STAFF-', employee_code) = ? LIMIT 1");
            $stmt->execute([$qr_data, $qr_data, $qr_data]);
            $st = $stmt->fetch();
            if ($st) {
                $staff_id = $st['id'];
            }
        }

        if ($staff_id > 0) {
            $chk = $db->prepare("SELECT id FROM attendance_logs WHERE type = 'staff' AND reference_id = ? AND log_date = ? LIMIT 1");
            $chk->execute([$staff_id, $log_date]);
            $existing_id = $chk->fetchColumn();

            if ($existing_id) {
                $stmt = $db->prepare("UPDATE attendance_logs SET status = ?, check_in_time = ?, remarks = ? WHERE id = ?");
                $stmt->execute([$status, $check_in, $remarks, $existing_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO attendance_logs (session_id, type, reference_id, log_date, status, check_in_time, remarks) VALUES (?, 'staff', ?, ?, ?, ?, ?)");
                $stmt->execute([$session_id, $staff_id, $log_date, $status, $check_in, $remarks]);
            }

            // Update today_status in staff_profiles if modifying current date
            if ($log_date === date('Y-m-d')) {
                $db->prepare("UPDATE staff_profiles SET today_status = ? WHERE id = ?")->execute([$status, $staff_id]);
            }

            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                $stmt_sinfo = $db->prepare("SELECT u.full_name, sp.employee_code, sp.department FROM staff_profiles sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ?");
                $stmt_sinfo->execute([$staff_id]);
                $staff_info = $stmt_sinfo->fetch(PDO::FETCH_ASSOC);

                // Get updated counts for the page date
                $stats_stmt = $db->prepare("
                    SELECT 
                        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                    FROM attendance_logs 
                    WHERE type = 'staff' AND session_id = ? AND log_date = ?
                ");
                $stats_stmt->execute([$session_id, $log_date]);
                $day_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

                $formatted_time = !empty($check_in) ? date('h:i A', strtotime($check_in)) : '—';
                $input_time = !empty($check_in) ? date('H:i', strtotime($check_in)) : '';

                echo json_encode([
                    'success' => true,
                    'message' => "Marked {$status} for {$staff_info['full_name']} ({$staff_info['employee_code']})",
                    'faculty' => $staff_info,
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

            set_flash('success', "Faculty attendance marked as {$status}.");
            header("Location: " . ADMIN_URL . "/staff-attendance?date={$log_date}" . (!empty($filter_dept) ? '&department=' . urlencode($filter_dept) : ''));
            exit;
        } else {
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "No staff profile found matching QR code '{$qr_data}'."]);
                exit;
            }
            set_flash('error', "No staff profile found matching scanned QR code '{$qr_data}'.");
        }
    }
}

// Auto-mark unpunched staff as Absent for previous dates:
// 1. Any past date where attendance was already logged for some staff in this session
// 2. The currently viewed $filter_date if it is in the past (< today)
$past_dates = $db->query("
    SELECT DISTINCT log_date 
    FROM attendance_logs 
    WHERE session_id = {$session_id} AND type = 'staff' AND log_date < CURDATE()
")->fetchAll(PDO::FETCH_COLUMN);

if ($filter_date < date('Y-m-d') && !in_array($filter_date, $past_dates)) {
    $past_dates[] = $filter_date;
}

if (!empty($past_dates)) {
    $auto_absent_sql = "
        INSERT INTO attendance_logs (session_id, type, reference_id, log_date, status, check_in_time, remarks)
        SELECT sp.session_id, 'staff', sp.id, ?, 'Absent', NULL, 'Auto Absent (Unpunched)'
        FROM staff_profiles sp
        WHERE sp.session_id = ?
          AND NOT EXISTS (
              SELECT 1 FROM attendance_logs al 
              WHERE al.type = 'staff' 
                AND al.reference_id = sp.id 
                AND al.log_date = ?
          )
    ";
    $auto_stmt = $db->prepare($auto_absent_sql);
    foreach ($past_dates as $pd) {
        $auto_stmt->execute([$pd, $session_id, $pd]);
    }
}

// Overall Attendance Statistics Query (unaffected by pagination limits)
$stats_sql = "
    SELECT 
        COUNT(sp.id) as total_faculty,
        SUM(CASE WHEN al.status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN al.status = 'Late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN al.status = 'Absent' THEN 1 ELSE 0 END) as absent_count
    FROM staff_profiles sp
    LEFT JOIN attendance_logs al ON al.reference_id = sp.id AND al.type = 'staff' AND al.log_date = ?
    WHERE sp.session_id = ?
";
$stats_params = [$filter_date, $session_id];
if (!empty($filter_dept)) {
    $stats_sql .= " AND sp.department = ?";
    $stats_params[] = $filter_dept;
}
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute($stats_params);
$day_stats_res = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$total_count = intval($day_stats_res['total_faculty'] ?? 0);
$present_count = intval($day_stats_res['present_count'] ?? 0);
$late_count = intval($day_stats_res['late_count'] ?? 0);
$absent_count = intval($day_stats_res['absent_count'] ?? 0);
$unmarked_count = max(0, $total_count - ($present_count + $late_count + $absent_count));

$present_rate = $total_count > 0 ? round(($present_count / $total_count) * 100, 1) : 0;
$late_rate = $total_count > 0 ? round(($late_count / $total_count) * 100, 1) : 0;
$absent_rate = $total_count > 0 ? round((($absent_count + $unmarked_count) / $total_count) * 100, 1) : 0;

// Helper for attendance pagination links preserving date, department and limit
function get_staff_attendance_pagination_url($target_page, $date, $department, $limit) {
    $params = ['date' => $date];
    if (!empty($department)) {
        $params['department'] = $department;
    }
    if ($limit !== 10) {
        $params['limit'] = $limit;
    }
    if ($target_page > 1) {
        $params['page'] = $target_page;
    }
    return ADMIN_URL . '/staff-attendance?' . http_build_query($params);
}

// Pagination & Limits Setup (10, 20, 30, 40, 50)
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

// Fetch staff attendance list for current page
$query = "
    SELECT sp.*, u.full_name, u.username, u.avatar,
    (SELECT status FROM attendance_logs WHERE type = 'staff' AND reference_id = sp.id AND log_date = ?) as today_status,
    (SELECT check_in_time FROM attendance_logs WHERE type = 'staff' AND reference_id = sp.id AND log_date = ?) as check_in_time
    FROM staff_profiles sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.session_id = ?
";
$params = [$filter_date, $filter_date, $session_id];

if (!empty($filter_dept)) {
    $query .= " AND sp.department = ?";
    $params[] = $filter_dept;
}
$query .= " ORDER BY sp.id ASC LIMIT ? OFFSET ?";

$stmt = $db->prepare($query);
$b_idx = 1;
foreach ($params as $p) {
    $stmt->bindValue($b_idx++, $p);
}
$stmt->bindValue($b_idx++, $limit, PDO::PARAM_INT);
$stmt->bindValue($b_idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$staff_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dynamic Departments list
$db_depts = $db->query("SELECT DISTINCT department FROM staff_profiles WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
$default_depts = ['Science', 'Chemistry & IIT-JEE', 'Physics', 'Mathematics & CET', 'Biology & NEET-UG', 'Commerce', 'Humanities', 'Administration', 'Computer Science & IT'];
$departments = array_values(array_unique(array_merge($db_depts, $default_depts)));
sort($departments);

// Helper for staff avatar detection
function get_staff_photo_url($sm) {
    if (!empty($sm['photo']) && $sm['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $sm['photo'])) {
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
    $lower = strtolower($sm['full_name']);
    if (strpos($lower, 'salam') !== false || strpos($lower, 'director') !== false) {
        return '/assets/imgs/faculties/founder-director.png';
    }
    if (strpos($lower, 'mishra') !== false || strpos($lower, 'rameshwar') !== false) {
        return '/assets/imgs/faculties/mr-rameshwar-mishra.png';
    }
    if (strpos($lower, 'aman') !== false) {
        return '/assets/imgs/faculties/mr-aman-kumar-soni.png';
    }
    if (strpos($lower, 'lubna') !== false) {
        return '/assets/imgs/faculties/mrs-lubna-khan.png';
    }
    return '';
}

// Avatar gradient fallback
$grad_palette = [
    'linear-gradient(135deg, #7c3aed, #6d28d9)',
    'linear-gradient(135deg, #0284c7, #0369a1)',
    'linear-gradient(135deg, #059669, #047857)',
    'linear-gradient(135deg, #d97706, #b45309)',
    'linear-gradient(135deg, #2563eb, #1d4ed8)',
];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Faculty Attendance Table & Badge Styles (Simple & Sober - Matching staff-id) ── */
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

/* Badge & Tag Elements */
.sid-code-pill {
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
.sid-dept-pill {
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
</style>

<!-- ══════════════════════════════════════════════════════
     KPI STAT SUMMARY CARDS (Exact match to student-attendance.php)
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid" style="margin-bottom: 24px;">
  
  <!-- Total Faculty -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Faculty &amp; Staff</span>
          <span class="stat-number"><?php echo $total_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-chalkboard-user"></i>
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
      <span style="color: #059669; font-weight: 700;">On-Duty</span>
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

<!-- ══════════════════════════════════════════════════════
     MAIN ATTENDANCE REGISTER WORKSTATION (Full Width)
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card attendance-register-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
  <div class="hrms-card-header attendance-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div class="attendance-title-block">
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-clipboard-user" style="color: #0284c7;"></i>
        Faculty &amp; Staff Attendance Register
        <span class="attendance-date-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Daily attendance and biometric punch roll for <strong><?php echo date('l, d M Y', strtotime($filter_date)); ?></strong>
      </p>
    </div>

    <!-- Date, Department Filter & Quick Actions -->
    <div class="attendance-header-actions" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <form method="GET" action="<?php echo ADMIN_URL; ?>/staff-attendance" class="attendance-filter-form" style="display: flex; gap: 8px; align-items: center; margin: 0;">
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
        <div class="att-filter-date-wrap" style="position: relative;">
          <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="this.form.submit()" class="hrms-input att-filter-date" style="padding: 6px 10px; font-size: 0.80rem; border: 1px solid #e2e8f0; border-radius: 6px; width: 140px; background: #ffffff;">
        </div>
        
        <select name="department" onchange="this.form.submit()" class="hrms-input att-filter-select" style="padding: 6px 12px; font-size: 0.80rem; border: 1px solid #e2e8f0; border-radius: 6px; min-width: 155px; background: #ffffff;">
          <option value="">All Departments</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?php echo $dept; ?>" <?php echo $filter_dept === $dept ? 'selected' : ''; ?>>
              <?php echo $dept; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <!-- Start Camera Scanner Button (Opens Floating Popup Modal) -->
      <button type="button" onclick="openScannerModal();" class="roaster-add-btn att-scanner-btn" style="padding: 6px 14px; font-size: 0.80rem; gap: 6px;" title="Open Camera Scanner Popup">
        <div class="btn-icon-circle"><i class="fa-solid fa-camera"></i></div>
        <span>Camera Scanner</span>
      </button>
    </div>
  </div>

  <!-- Attendance Roster Table -->
  <div class="table-responsive">
    <table class="hrms-table staff-id-table">
      <thead>
        <tr>
          <th style="min-width: 200px;">Faculty Member</th>
          <th style="min-width: 120px;">Employee ID</th>
          <th style="min-width: 160px;">Department &amp; Post</th>
          <th style="min-width: 110px;">Status</th>
          <th style="min-width: 115px;">Punch-In Time</th>
          <th style="min-width: 130px; text-align: right;">Quick Punch</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($staff_attendance)): ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-user-slash"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Faculty Records Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0;">
                No faculty members found matching selected criteria.
              </p>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($staff_attendance as $row): ?>
            <?php 
              $photo_url = get_staff_photo_url($row);
              $first_letter = strtoupper(substr($row['full_name'], 0, 1));
              $cur_status = $row['today_status'] ?? '';
            ?>
            <tr>
              
              <!-- Faculty Profile Cell -->
              <td>
                <div class="user-cell" style="display: flex; align-items: center; gap: 12px;">
                  <?php if (!empty($photo_url)): ?>
                    <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo htmlspecialchars($row['full_name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.90rem; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                      <?php echo htmlspecialchars($row['full_name']); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.73rem; color: #64748b; margin-top: 2px;">
                      <span style="font-family: monospace; color: #0284c7; font-weight: 600;">@<?php echo htmlspecialchars($row['username']); ?></span>
                      <?php if (!empty($row['blood_group'])): ?>
                        <span>&bull;</span>
                        <span class="sid-blood-badge">Blood: <?php echo htmlspecialchars($row['blood_group']); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Employee ID Cell -->
              <td>
                <span class="sid-code-pill">
                  <i class="fa-solid fa-id-badge" style="font-size: 0.65rem;"></i>
                  <span><?php echo htmlspecialchars($row['employee_code']); ?></span>
                </span>
              </td>

              <!-- Department & Designation Cell -->
              <td>
                <span class="sid-dept-pill">
                  <i class="fa-solid fa-layer-group" style="font-size: 0.65rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($row['department']); ?></span>
                </span>
                <div style="font-size: 0.73rem; color: #64748b; margin-top: 3px;">
                  <?php echo htmlspecialchars($row['designation']); ?>
                  <?php if (!empty($row['qualification'])): ?>
                    &bull; <?php echo htmlspecialchars($row['qualification']); ?>
                  <?php endif; ?>
                </div>
              </td>

              <!-- Status Badge Cell -->
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

              <!-- Check-In Timestamp -->
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

              <!-- Quick Punch Segmented Group -->
              <td style="text-align: right;">
                <div class="punch-pill-group" id="punchGroup-<?php echo $row['id']; ?>">
                  
                  <!-- P: Present -->
                  <button type="button" 
                          onclick="punchStaff(<?php echo $row['id']; ?>, 'Present', this)" 
                          class="punch-action-btn present <?php echo $cur_status === 'Present' ? 'active' : ''; ?>" 
                          title="Mark Present">
                    P
                  </button>

                  <!-- L: Late (Opens late punch tooltip popup for custom arrival time) -->
                  <button type="button" 
                          onclick="openLateTooltip(this, <?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['full_name'])); ?>', '<?php echo addslashes(htmlspecialchars($row['employee_code'])); ?>', '<?php echo (!empty($row['check_in_time']) && $cur_status === 'Late') ? date('H:i', strtotime($row['check_in_time'])) : ''; ?>')" 
                          class="punch-action-btn late <?php echo $cur_status === 'Late' ? 'active' : ''; ?>" 
                          title="Mark Late &amp; Enter Time">
                    L
                  </button>

                  <!-- A: Absent -->
                  <button type="button" 
                          onclick="punchStaff(<?php echo $row['id']; ?>, 'Absent', this)" 
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
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> faculty &amp; staff
        <?php else: ?>
          Showing <strong>0</strong> faculty &amp; staff
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_staff_attendance_pagination_url(1, $filter_date, $filter_dept, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Faculty Attendance Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_staff_attendance_pagination_url($page - 1, $filter_date, $filter_dept, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_staff_attendance_pagination_url($p, $filter_date, $filter_dept, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_staff_attendance_pagination_url($page + 1, $filter_date, $filter_dept, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Right: Summary status counts -->
    <div id="roasterFooterSummary" style="font-size: 0.76rem; color: #475569; display: flex; align-items: center; gap: 8px; margin-left: auto;">
      <span id="footerPresentCount" style="color: #059669; font-weight: 700;"><?php echo $present_count; ?> Present</span> &bull; 
      <span id="footerLateCount" style="color: #d97706; font-weight: 700;"><?php echo $late_count; ?> Late</span> &bull; 
      <span id="footerAbsentCount" style="color: #dc2626; font-weight: 700;"><?php echo $absent_count; ?> Absent</span>
      <?php if ($unmarked_count > 0): ?>
        &bull; <span id="footerUnmarkedCount" style="color: #64748b; font-weight: 600;"><?php echo $unmarked_count; ?> Unmarked</span>
      <?php endif; ?>
    </div>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     LIVE CAMERA QR SCANNER MODAL POPUP (Exact match to student attendance)
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="scannerModal">
  <div class="hrms-modal-card" style="max-width: 520px; border-radius: 18px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);">
    
    <!-- Modal Header -->
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px; background: #ffffff; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(124, 58, 237, 0.1); color: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
          <i class="fa-solid fa-qrcode"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
            Faculty Biometric QR Scanner
          </h3>
          <p style="margin: 2px 0 0; font-size: 0.76rem; color: #64748b;">Instant Faculty Punch-In Terminal</p>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 10px;">
        <div class="scholars-count-badge" style="padding: 4px 10px; font-size: 0.72rem;">
          <span class="count-dot" style="background: #7c3aed;"></span>
          <span style="font-weight: 800; color: #7c3aed;">AUTO-PUNCH</span>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeScannerModal();" aria-label="Close scanner modal" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>

    <!-- Modal Body -->
    <div class="hrms-modal-body" style="padding: 22px;">
      <p style="margin: 0 0 14px; font-size: 0.82rem; color: #64748b; line-height: 1.45;">
        Aim device camera at faculty member's <strong>Staff ID Card QR Code</strong> for instant attendance logging.
      </p>

      <!-- Camera Viewport Box with Reticle Guides & Laser Sweep -->
      <div class="scanner-viewport-box" id="qrScannerContainer" style="margin-bottom: 14px;">
        <div id="qrReader" style="width: 100%;"></div>

        <!-- Idle Overlay (shown before camera begins) -->
        <div class="scanner-idle-placeholder" id="scannerIdleMsg" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; max-width: 320px; text-align: center; margin: 0; padding: 20px; z-index: 2;">
          <div class="scanner-radar-pulse" style="border-color: rgba(124, 58, 237, 0.4); color: #7c3aed;">
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
        <button type="button" id="startScannerBtn" onclick="startScanner();" class="scanner-control-btn start" style="display: none; background: #7c3aed; color: #ffffff;">
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
          <i class="fa-solid fa-keyboard" style="color: #7c3aed;"></i>
          Or Type Employee Code / QR Hash Manually:
        </label>
        <div class="scanner-manual-wrap" style="display: flex; gap: 8px;">
          <input type="text" id="manualQrInput" placeholder="e.g. KSV-FAC-001 or STAFF-KSV-FAC-001" class="hrms-input manual-punch-input" style="flex: 1; min-width: 0; padding: 8px 12px; font-size: 0.85rem;" onkeypress="if(event.key==='Enter') submitManualQr();">
          <button type="button" onclick="submitManualQr();" class="roaster-add-btn manual-punch-btn" style="padding: 8px 16px; font-size: 0.82rem; background: #7c3aed; white-space: nowrap;">
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
        if (p) p.textContent = 'Camera blocked or not found. Enter Employee Code manually below.';
      }
      if (reticle) reticle.style.display = 'none';
      document.getElementById('startScannerBtn').style.display = 'inline-flex';
      document.getElementById('stopScannerBtn').style.display = 'none';
      showScanFeedback(false, "Camera access unavailable or blocked. You can enter the Employee Code manually below.");
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
    alert('Please enter an employee code or QR code string');
    return;
  }
  postAttendancePunch(val);
}

function postAttendancePunch(qrCodeValue) {
  const formData = new FormData();
  formData.append('action', 'mark_staff_attendance');
  formData.append('qr_data', qrCodeValue);
  formData.append('status', 'Present');
  formData.append('log_date', '<?php echo $filter_date; ?>');
  formData.append('ajax', '1');

  fetch('<?php echo ADMIN_URL; ?>/staff-attendance', {
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
// Quick Punch & Late Tooltip Functions (Matches student-attendance.php)
// ==========================================
let currentLateBtn = null;

function getCurrentTimeFormatted() {
  const d = new Date();
  const h = String(d.getHours()).padStart(2, '0');
  const m = String(d.getMinutes()).padStart(2, '0');
  return `${h}:${m}`;
}

function openLateTooltip(btn, staffId, staffName, empCode, curTime) {
  currentLateBtn = btn;
  const tooltip = document.getElementById('latePunchTooltip');
  if (!tooltip) return;

  document.getElementById('lateStaffId').value = staffId;
  document.getElementById('lateStaffName').textContent = staffName;
  document.getElementById('lateStaffCode').textContent = empCode;
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
  const staffId = document.getElementById('lateStaffId').value;
  const timeVal = document.getElementById('lateTimeInput').value || getCurrentTimeFormatted();
  if (!staffId) return;

  punchStaff(staffId, 'Late', currentLateBtn, timeVal);
  closeLateTooltip();
}

function punchStaff(staffId, status, btnElement, checkInTime) {
  const group = document.getElementById(`punchGroup-${staffId}`);
  if (group) group.style.opacity = '0.6';

  const formData = new FormData();
  formData.append('action', 'mark_staff_attendance');
  formData.append('staff_id', staffId);
  formData.append('status', status);
  formData.append('log_date', '<?php echo $filter_date; ?>');
  if (checkInTime) {
    formData.append('check_in_time', checkInTime);
  }
  formData.append('ajax', '1');

  fetch('<?php echo ADMIN_URL; ?>/staff-attendance', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (group) group.style.opacity = '1';

    if (data.success) {
      // 1. Update Status Cell
      const statusCell = document.getElementById(`statusCell-${staffId}`);
      if (statusCell) {
        if (status === 'Present') {
          statusCell.innerHTML = `<span class="att-pill-chip high"><span class="legend-dot green"></span> Present</span>`;
        } else if (status === 'Late') {
          statusCell.innerHTML = `<span class="att-pill-chip medium"><span class="legend-dot amber"></span> Late</span>`;
        } else if (status === 'Absent') {
          statusCell.innerHTML = `<span class="att-pill-chip low"><span class="legend-dot red"></span> Absent</span>`;
        }
      }

      // 2. Update Check-In Cell
      const checkInCell = document.getElementById(`checkInCell-${staffId}`);
      if (checkInCell) {
        if (status === 'Absent' || !data.formatted_time || data.formatted_time === '—') {
          checkInCell.innerHTML = `<span style="color: #94a3b8; font-size: 0.75rem; font-weight: 600;">—</span>`;
        } else {
          checkInCell.innerHTML = `<span class="att-time-badge"><i class="fa-regular fa-clock" style="color: #0284c7; font-size: 0.68rem;"></i> ${data.formatted_time}</span>`;
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
          const sName = data.faculty.full_name.replace(/'/g, "\\'");
          const sCode = data.faculty.employee_code.replace(/'/g, "\\'");
          lateBtn.setAttribute('onclick', `openLateTooltip(this, ${staffId}, '${sName}', '${sCode}', '${data.input_time}')`);
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
        if (kpiP) kpiP.textContent = pCount;
        if (kpiL) kpiL.textContent = lCount;
        if (kpiA) kpiA.textContent = aCount;

        const barP = document.getElementById('kpiPresentBar');
        const barL = document.getElementById('kpiLateBar');
        const barA = document.getElementById('kpiAbsentBar');
        if (barP) barP.style.width = `${pRate}%`;
        if (barL) barL.style.width = `${lRate}%`;
        if (barA) barA.style.width = `${aRate}%`;

        const rateP = document.getElementById('kpiPresentRate');
        const rateL = document.getElementById('kpiLateRate');
        const rateA = document.getElementById('kpiAbsentRate');
        if (rateP) rateP.innerHTML = `<strong>${pRate}%</strong> Attendance Rate`;
        if (rateL) rateL.innerHTML = `<strong>${lRate}%</strong> Late Rate`;
        if (rateA) rateA.innerHTML = `<strong>${aRate}%</strong> Absent Rate`;

        // Update footer counts
        const fp = document.getElementById('footerPresentCount');
        const fl = document.getElementById('footerLateCount');
        const fa = document.getElementById('footerAbsentCount');
        const fu = document.getElementById('footerUnmarkedCount');
        if (fp) fp.textContent = `${pCount} Present`;
        if (fl) fl.textContent = `${lCount} Late`;
        if (fa) fa.textContent = `${aCount} Absent`;
        if (fu) {
          const unmk = Math.max(0, total - (pCount + lCount + aCount));
          fu.textContent = `${unmk} Unmarked`;
        }
      }
    } else {
      alert(data.message || 'Error recording attendance');
    }
  })
  .catch(err => {
    if (group) group.style.opacity = '1';
    console.error(err);
  });
}

// Close late tooltip when clicking outside
document.addEventListener('click', function(e) {
  const tooltip = document.getElementById('latePunchTooltip');
  if (tooltip && tooltip.style.display === 'block') {
    if (!tooltip.contains(e.target) && (!currentLateBtn || !currentLateBtn.contains(e.target))) {
      closeLateTooltip();
    }
  }
});

// Close late tooltip on Escape
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
      <strong id="lateStaffName" style="color: #0f172a;"></strong> 
      (<span id="lateStaffCode"></span>)
    </div>

    <input type="hidden" id="lateStaffId" value="">

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
