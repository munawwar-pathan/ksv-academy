<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
if ($current_user['role'] === 'staff') {
    require_menu_permission('admin_staff_periods');
}
$page_title = 'Staff Periods & Timetable | KSV Academy HRMS';
$page_heading = 'Staff Periods';
$page_subheading = 'Faculty Schedules, Subject Class Distribution, Period Timings & Master Timetable';
$active_menu = 'staff-periods';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];

// ── Handle Actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($current_user['role'], ['super_admin', 'admin'])) {
    $action = $_POST['action'] ?? '';

    // 1. Add / Assign Period
    if ($action === 'add_period') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $class_name = trim($_POST['class_name'] ?? 'Class 10th');
        $day_of_week = trim($_POST['day_of_week'] ?? 'Monday - Friday');
        $period_name = trim($_POST['period_name'] ?? 'Period 1');
        $time_period = trim($_POST['time_period'] ?? '');
        $room_no = trim($_POST['room_no'] ?? 'Room 101');

        $staff_name = '';
        if ($staff_id > 0) {
            $st_stmt = $db->prepare("
                SELECT u.full_name FROM staff_profiles sp 
                JOIN users u ON sp.user_id = u.id 
                WHERE sp.id = ? LIMIT 1
            ");
            $st_stmt->execute([$staff_id]);
            $staff_name = $st_stmt->fetchColumn() ?: 'Faculty Member';
        }

        if ($staff_id > 0 && !empty($subject) && !empty($time_period)) {
            $stmt = $db->prepare("
                INSERT INTO staff_periods (staff_id, staff_name, subject, class_name, day_of_week, period_name, time_period, room_no, session_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$staff_id, $staff_name, $subject, $class_name, $day_of_week, $period_name, $time_period, $room_no, $session_id]);
            set_flash('success', "Period successfully assigned to {$staff_name} for {$subject} ({$time_period})!");
        } else {
            set_flash('error', 'Please select a staff member and provide both subject and time period.');
        }

        $redirect_view = $_POST['redirect_view'] ?? 'table';
        header('Location: ' . ADMIN_URL . '/staff-periods?view=' . urlencode($redirect_view));
        exit;
    }

    // 2. Edit Period
    if ($action === 'edit_period') {
        $id = intval($_POST['id'] ?? 0);
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $class_name = trim($_POST['class_name'] ?? 'Class 10th');
        $day_of_week = trim($_POST['day_of_week'] ?? 'Monday - Friday');
        $period_name = trim($_POST['period_name'] ?? 'Period 1');
        $time_period = trim($_POST['time_period'] ?? '');
        $room_no = trim($_POST['room_no'] ?? 'Room 101');

        $staff_name = '';
        if ($staff_id > 0) {
            $st_stmt = $db->prepare("
                SELECT u.full_name FROM staff_profiles sp 
                JOIN users u ON sp.user_id = u.id 
                WHERE sp.id = ? LIMIT 1
            ");
            $st_stmt->execute([$staff_id]);
            $staff_name = $st_stmt->fetchColumn() ?: 'Faculty Member';
        }

        if ($id > 0 && $staff_id > 0 && !empty($subject) && !empty($time_period)) {
            $stmt = $db->prepare("
                UPDATE staff_periods 
                SET staff_id = ?, staff_name = ?, subject = ?, class_name = ?, day_of_week = ?, period_name = ?, time_period = ?, room_no = ?
                WHERE id = ?
            ");
            $stmt->execute([$staff_id, $staff_name, $subject, $class_name, $day_of_week, $period_name, $time_period, $room_no, $id]);
            set_flash('success', "Period schedule updated successfully!");
        } else {
            set_flash('error', 'Please fill in all required fields.');
        }

        $redirect_view = $_POST['redirect_view'] ?? 'table';
        header('Location: ' . ADMIN_URL . '/staff-periods?view=' . urlencode($redirect_view));
        exit;
    }

    // 3. Delete Period
    if ($action === 'delete_period') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $db->prepare("DELETE FROM staff_periods WHERE id = ?");
            $del->execute([$id]);
            set_flash('success', "Period schedule removed.");
        }
        $redirect_view = $_POST['redirect_view'] ?? 'table';
        header('Location: ' . ADMIN_URL . '/staff-periods?view=' . urlencode($redirect_view));
        exit;
    }
}

// ── Query Staff List ─────────────────────────────────────────────────────────
$staff_members = $db->query("
    SELECT sp.id as staff_id, sp.employee_code, sp.department, sp.designation, u.id as user_id, u.full_name, u.username
    FROM staff_profiles sp
    JOIN users u ON sp.user_id = u.id
    WHERE u.status = 'active'
    ORDER BY u.full_name ASC
")->fetchAll();

// ── Query Periods with Filters ──────────────────────────────────────────────
$filter_staff = intval($_GET['staff_id'] ?? 0);
$filter_day = trim($_GET['day'] ?? '');
$search = trim($_GET['search'] ?? '');
$view_mode = trim($_GET['view'] ?? 'table'); // 'table' or 'schedule'

if (!function_exists('get_staff_photo_url')) {
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
        $name_to_check = !empty($sm['full_name']) ? $sm['full_name'] : (!empty($sm['user_full_name']) ? $sm['user_full_name'] : ($sm['staff_name'] ?? ''));
        $lower = strtolower($name_to_check);
        if (strpos($lower, 'salam') !== false || strpos($lower, 'director') !== false) {
            return '/assets/imgs/faculties/founder-director.png';
        }
        if (strpos($lower, 'mishra') !== false || strpos($lower, 'rameshwar') !== false) {
            return '/assets/imgs/faculties/staff-mishra.png';
        }
        if (strpos($lower, 'priya') !== false || strpos($lower, 'desai') !== false) {
            return '/assets/imgs/faculties/staff-priya.png';
        }
        if (strpos($lower, 'amit') !== false || strpos($lower, 'verma') !== false) {
            return '/assets/imgs/faculties/staff-amit.png';
        }
        if (strpos($lower, 'aman') !== false) {
            return '/assets/imgs/faculties/mr-aman-kumar-soni.png';
        }
        if (strpos($lower, 'lubna') !== false) {
            return '/assets/imgs/faculties/mrs-lubna-khan.png';
        }
        if (strpos($lower, 'hafiza') !== false || strpos($lower, 'palikar') !== false) {
            return '/assets/imgs/faculties/hafiza-palikar.png';
        }
        if (strpos($lower, 'rafeek') !== false) {
            return '/assets/imgs/faculties/rafeek-khan.png';
        }
        if (!empty($sm['photo']) && $sm['photo'] !== '/assets/imgs/ksv-logo.jpg') {
            return $sm['photo'];
        }
        return '';
    }
}

// Pagination Settings (10, 20, 30, 40, 50)
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Build WHERE filter conditions
$where_clauses = ["1=1"];
$where_params = [];

if ($filter_staff > 0) {
    $where_clauses[] = "p.staff_id = ?";
    $where_params[] = $filter_staff;
}

if (!empty($filter_day) && $filter_day !== 'all') {
    $where_clauses[] = "p.day_of_week LIKE ?";
    $where_params[] = "%$filter_day%";
}

if (!empty($search)) {
    $where_clauses[] = "(p.staff_name LIKE ? OR p.subject LIKE ? OR p.class_name LIKE ? OR p.room_no LIKE ?)";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
}

$where_sql = implode(' AND ', $where_clauses);

// Accurate SQL aggregate for total periods, unique faculty, and unique subjects
$metrics_stmt = $db->prepare("
    SELECT COUNT(*) as total_periods,
           COUNT(DISTINCT p.staff_id) as unique_faculty,
           COUNT(DISTINCT p.subject) as unique_subjects
    FROM staff_periods p
    LEFT JOIN staff_profiles sp ON p.staff_id = sp.id
    LEFT JOIN users u ON sp.user_id = u.id
    WHERE {$where_sql}
");
$metrics_stmt->execute($where_params);
$metrics = $metrics_stmt->fetch(PDO::FETCH_ASSOC);
$total_records = intval($metrics['total_periods'] ?? 0);
$total_periods = $total_records;
$unique_faculty_count = intval($metrics['unique_faculty'] ?? 0);
$unique_subjects_count = intval($metrics['unique_subjects'] ?? 0);

// Calculate Pagination Bounds
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Base SELECT clause for periods
$base_select = "
    SELECT p.*, sp.employee_code, sp.department, sp.designation, sp.photo, u.username, u.email as user_email, u.full_name as user_full_name, u.avatar
    FROM staff_periods p
    LEFT JOIN staff_profiles sp ON p.staff_id = sp.id
    LEFT JOIN users u ON sp.user_id = u.id
    WHERE {$where_sql}
    ORDER BY p.period_name ASC, p.id ASC
";

// If in table view, paginate the records; for schedule card view, fetch all matching periods
if ($view_mode === 'schedule') {
    $stmt = $db->prepare($base_select);
    $stmt->execute($where_params);
    $periods = $stmt->fetchAll();
} else {
    $table_sql = $base_select . " LIMIT ? OFFSET ?";
    $stmt = $db->prepare($table_sql);
    $param_idx = 1;
    foreach ($where_params as $val) {
        $stmt->bindValue($param_idx++, $val);
    }
    $stmt->bindValue($param_idx++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($param_idx++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $periods = $stmt->fetchAll();
}

// Helper to preserve active query parameters in pagination links
function get_staff_periods_pagination_url($target_page, $search, $filter_staff, $filter_day, $view_mode, $limit) {
    $params = [];
    if (!empty($view_mode) && $view_mode !== 'table') $params['view'] = $view_mode;
    if ($filter_staff > 0) $params['staff_id'] = $filter_staff;
    if (!empty($filter_day) && $filter_day !== 'all') $params['day'] = $filter_day;
    if (!empty($search)) $params['search'] = $search;
    if ($limit !== 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    $query_str = !empty($params) ? '?' . http_build_query($params) : '';
    return ADMIN_URL . '/staff-periods' . $query_str;
}

// Helper for subject badge color theme (Simple & Sober)
function get_subject_theme($subj) {
    $s = strtolower($subj);
    if (strpos($s, 'phys') !== false) return ['bg' => '#f0f9ff', 'color' => '#0284c7', 'border' => '#bae6fd', 'icon' => 'fa-atom'];
    if (strpos($s, 'chem') !== false) return ['bg' => '#fff7ed', 'color' => '#c2410c', 'border' => '#fed7aa', 'icon' => 'fa-flask-vial'];
    if (strpos($s, 'math') !== false || strpos($s, 'calc') !== false || strpos($s, 'geom') !== false) return ['bg' => '#faf5ff', 'color' => '#7e22ce', 'border' => '#e9d5ff', 'icon' => 'fa-square-root-variable'];
    if (strpos($s, 'bio') !== false || strpos($s, 'anat') !== false) return ['bg' => '#f0fdf4', 'color' => '#166534', 'border' => '#bbf7d0', 'icon' => 'fa-dna'];
    if (strpos($s, 'eng') !== false || strpos($s, 'lit') !== false) return ['bg' => '#fefce8', 'color' => '#854d0e', 'border' => '#fef08a', 'icon' => 'fa-book'];
    if (strpos($s, 'comp') !== false || strpos($s, 'code') !== false) return ['bg' => '#eef2ff', 'color' => '#4338ca', 'border' => '#c7d2fe', 'icon' => 'fa-laptop-code'];
    return ['bg' => '#f8fafc', 'color' => '#334155', 'border' => '#e2e8f0', 'icon' => 'fa-bookmark'];
}

// Group periods by faculty for schedule card view
$periods_by_staff = [];
foreach ($periods as $prd) {
    $sid = $prd['staff_id'];
    if (!isset($periods_by_staff[$sid])) {
        $periods_by_staff[$sid] = [
            'staff_name' => $prd['staff_name'],
            'full_name' => $prd['user_full_name'] ?? $prd['staff_name'],
            'employee_code' => $prd['employee_code'] ?? 'KSV-FAC',
            'department' => $prd['department'] ?? 'Faculty',
            'designation' => $prd['designation'] ?? 'Lecturer',
            'photo' => $prd['photo'] ?? '',
            'avatar' => $prd['avatar'] ?? '',
            'periods' => []
        ];
    }
    $periods_by_staff[$sid]['periods'][] = $prd;
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Toolbar Single-Line Fit ── */
.roaster-toolbar-card .roaster-search-box {
  min-width: 180px;
  max-width: 220px;
}
.roaster-toolbar-card .roaster-select-box select {
  min-width: 135px;
}

/* ── View Switcher Pills ── */
.sp-view-toggle {
  display: inline-flex;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  gap: 3px;
}
.sp-view-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.15s ease;
  color: #64748b;
}
.sp-view-tab:hover {
  color: #0f172a;
}
.sp-view-tab.active {
  background: #ffffff;
  color: #0f172a;
  font-weight: 700;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  border: 1px solid #cbd5e1;
}

/* ── Staff Periods Master Table (Simple & Sober matching staff-id.php) ── */
.staff-periods-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.staff-periods-table thead th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.71rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 10px 10px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.staff-periods-table tbody td {
  padding: 10px 10px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.82rem;
}
.staff-periods-table tbody tr {
  transition: background-color 0.15s ease;
}
.staff-periods-table tbody tr:hover {
  background-color: #f8fafc;
}

/* Badges & Tags */
.sp-code-pill {
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
.sp-subject-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 700;
  font-family: 'Outfit', sans-serif;
  white-space: nowrap;
}
.sp-slot-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #fefce8;
  border: 1px solid #fef08a;
  color: #854d0e;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 5px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  white-space: nowrap;
}
.sp-time-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0369a1;
  font-size: 0.74rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  white-space: nowrap;
}
.sp-day-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 5px;
  white-space: nowrap;
}
.sp-venue-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 5px;
  white-space: nowrap;
}

/* ── Unified Action Buttons (Matching staff-id.php) ── */
.sp-btn-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px;
  font-size: 0.76rem;
  font-weight: 600;
  border-radius: 6px;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
}
.sp-btn-action:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}
.sp-btn-action.sp-btn-primary {
  background: #0f172a;
  border-color: #0f172a;
  color: #ffffff;
}
.sp-btn-action.sp-btn-primary:hover {
  background: #1e293b;
  border-color: #1e293b;
}
.sp-btn-action.btn-danger:hover {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #dc2626;
}

/* ── Faculty Schedule Cards (View 2) ── */
.faculty-schedule-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
  margin-bottom: 22px;
}
.faculty-schedule-header {
  padding: 14px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.period-timeline-strip {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 14px;
  padding: 18px 20px;
}
.period-timeline-item {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px;
  position: relative;
  transition: all 0.2s ease;
  border-left: 3px solid #0284c7;
}
.period-timeline-item:hover {
  box-shadow: 0 4px 14px rgba(0,0,0,0.04);
  border-color: #cbd5e1;
}

/* ── Modal & Form Inputs ── */
.input-field-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 6px;
}
.input-field-label .req {
  color: #ef4444;
}
.settings-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.85rem;
  font-family: inherit;
  color: #0f172a;
  background: #ffffff;
  transition: all 0.2s ease;
  outline: none;
  box-sizing: border-box;
}
.settings-input:focus {
  border-color: #0284c7;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
}
.preset-slot-btn {
  padding: 4px 9px;
  font-size: 0.72rem;
  font-weight: 600;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 5px;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
}
.preset-slot-btn:hover {
  background: #f0f9ff;
  color: #0284c7;
  border-color: #bae6fd;
}
</style>

<!-- ══════════════════════════════════════════════════════
     TOP METRICS CARDS (Exact match to other pages like staff.php / students.php)
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid no-print" style="margin-bottom: 22px;">
  
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Assigned Periods</span>
          <span class="stat-number"><?php echo $total_periods; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-clock"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
      <span style="color:#059669; font-weight:700;"><i class="fa-solid fa-check"></i> Active</span>
    </div>
  </div>

  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Faculty on Timetable</span>
          <span class="stat-number"><?php echo $unique_faculty_count; ?></span>
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
      <span>Allocated Instructors</span>
      <span style="color:#7c3aed; font-weight:700;">Scheduled</span>
    </div>
  </div>

  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Allocated Subjects</span>
          <span class="stat-number"><?php echo $unique_subjects_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-book-open"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Course Disciplines</span>
      <span style="color:#059669; font-weight:700;">Covered</span>
    </div>
  </div>

  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Registered Staff</span>
          <span class="stat-number"><?php echo count($staff_members); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-id-badge"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Total Faculty Pool</span>
      <span style="color:#d97706; font-weight:700;">Faculty Pool</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     TOOLBAR & FILTERS (Exact match to other pages like staff.php / students.php)
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner">
    
    <!-- Left: Filters & Live Search Form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/staff-periods" class="roaster-filter-form">
      <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      
      <!-- Styled Search Input -->
      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search faculty, subject, room..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_staff_periods_pagination_url(1, '', $filter_staff, $filter_day, $view_mode, $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <div class="periods-filter-row">
        <!-- Staff Filter -->
        <div class="roaster-select-box">
          <i class="fa-solid fa-chalkboard-user"></i>
          <select name="staff_id" onchange="this.form.submit();">
            <option value="0">All Faculty Members</option>
            <?php foreach ($staff_members as $sm): ?>
              <option value="<?php echo $sm['staff_id']; ?>" <?php echo $filter_staff == $sm['staff_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($sm['full_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <i class="fa-solid fa-chevron-down select-chevron"></i>
        </div>

        <!-- Day Filter -->
        <div class="roaster-select-box">
          <i class="fa-solid fa-calendar-day"></i>
          <select name="day" onchange="this.form.submit();">
            <option value="all">All Days</option>
            <option value="Monday" <?php echo $filter_day === 'Monday' ? 'selected' : ''; ?>>Monday</option>
            <option value="Tuesday" <?php echo $filter_day === 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
            <option value="Wednesday" <?php echo $filter_day === 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
            <option value="Thursday" <?php echo $filter_day === 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
            <option value="Friday" <?php echo $filter_day === 'Friday' ? 'selected' : ''; ?>>Friday</option>
            <option value="Saturday" <?php echo $filter_day === 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
          </select>
          <i class="fa-solid fa-chevron-down select-chevron"></i>
        </div>
      </div>

      <div class="periods-filter-btn-group">
        <button type="submit" class="roaster-btn secondary">
          <i class="fa-solid fa-sliders"></i> Filter
        </button>

        <?php if (!empty($search) || $filter_staff > 0 || (!empty($filter_day) && $filter_day !== 'all')): ?>
          <a href="<?php echo ADMIN_URL; ?>/staff-periods?view=<?php echo urlencode($view_mode); ?><?php echo $limit !== 10 ? '&limit=' . $limit : ''; ?>" class="roaster-btn ghost" title="Reset all filters">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Right: Count Badge & Add Button -->
    <div class="roaster-toolbar-actions periods-toolbar-actions">
      <div class="scholars-count-badge">
        <span class="count-dot" style="background: #0092df;"></span>
        <span><strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'Period' : 'Periods'; ?></span>
      </div>

      <?php if (in_array($current_user['role'], ['super_admin', 'admin'])): ?>
        <button type="button" class="roaster-add-btn" onclick="openAssignModal();">
          <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
          <span>Assign Period</span>
        </button>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     VIEW 1: PERIOD ALLOCATION MASTER TABLE (Simple & Sober)
     ══════════════════════════════════════════════════════ -->
<?php if ($view_mode !== 'schedule'): ?>
  <div class="hrms-card no-print periods-directory-card" style="margin-bottom: 30px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
    
    <div class="hrms-card-header periods-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px; background: #ffffff;">
      <div class="periods-title-block">
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-calendar-check" style="color: #0284c7;"></i>
          Staff Period Master Schedule
          <span class="periods-session-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
            Session <?php echo htmlspecialchars($active_session['session_name']); ?>
          </span>
        </h3>
        <p class="periods-subtitle" style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
          Comprehensive timetable of assigned teaching periods, subject allocations, class sections, and venues.
        </p>
      </div>

      <div class="periods-header-controls" style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
        <!-- View Switcher -->
        <div class="sp-view-toggle periods-view-toggle">
          <a href="<?php echo ADMIN_URL; ?>/staff-periods?view=table<?php echo $filter_staff > 0 ? '&staff_id='.$filter_staff : ''; ?><?php echo !empty($filter_day) ? '&day='.urlencode($filter_day) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo $limit !== 10 ? '&limit='.$limit : ''; ?>" 
             class="sp-view-tab <?php echo $view_mode !== 'schedule' ? 'active' : ''; ?>">
            <i class="fa-solid fa-table-list"></i>
            <span>Table View</span>
          </a>
          <a href="<?php echo ADMIN_URL; ?>/staff-periods?view=schedule<?php echo $filter_staff > 0 ? '&staff_id='.$filter_staff : ''; ?><?php echo !empty($filter_day) ? '&day='.urlencode($filter_day) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo $limit !== 10 ? '&limit='.$limit : ''; ?>" 
             class="sp-view-tab <?php echo $view_mode === 'schedule' ? 'active' : ''; ?>">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Faculty Timetables</span>
          </a>
        </div>

        <div class="periods-records-count" style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
          <span>Total Records: <strong style="color: #0f172a;"><?php echo $total_records; ?></strong></span>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="hrms-table staff-periods-table">
        <thead>
          <tr>
            <th>Faculty Member</th>
            <th>Subject &amp; Class</th>
            <th>Period Slot &amp; Timing</th>
            <th>Schedule Day</th>
            <th>Classroom / Venue</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($periods)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 50px 20px; color: #64748b;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                  <i class="fa-regular fa-calendar-xmark"></i>
                </div>
                <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Staff Periods Found</h4>
                <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                  No period schedules match your current filters for Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
                </p>
                <a href="<?php echo ADMIN_URL; ?>/staff-periods?view=<?php echo urlencode($view_mode); ?>" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                  <i class="fa-solid fa-rotate-left"></i> Reset All Filters
                </a>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($periods as $p): ?>
              <?php 
                $th = get_subject_theme($p['subject']);
                $st_photo = get_staff_photo_url($p);
                $has_st_photo = !empty($st_photo);
                $f_letter = strtoupper(substr($p['staff_name'], 0, 1));
              ?>
              <tr>
                
                <!-- 1. Faculty Member -->
                <td>
                  <div class="user-cell" style="display: flex; align-items: center; gap: 10px;">
                    <?php if ($has_st_photo): ?>
                      <img src="<?php echo htmlspecialchars($st_photo); ?>" alt="<?php echo htmlspecialchars($p['staff_name']); ?>" class="staff-avatar-img" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                      <div class="staff-avatar-init" style="width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                        <?php echo $f_letter; ?>
                      </div>
                    <?php else: ?>
                      <div class="staff-avatar-init" style="width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <?php echo $f_letter; ?>
                      </div>
                    <?php endif; ?>

                    <div>
                      <div class="cell-name" style="font-weight: 700; color: #0f172a; font-size: 0.88rem; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                        <?php echo htmlspecialchars($p['staff_name']); ?>
                      </div>
                      <div class="cell-sub" style="font-size: 0.73rem; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                        <span class="sp-code-pill">
                          <i class="fa-solid fa-hashtag" style="font-size: 0.60rem;"></i>
                          <span><?php echo htmlspecialchars($p['employee_code'] ?? 'KSV-FAC'); ?></span>
                        </span>
                        <?php if (!empty($p['department'])): ?>
                          <span style="color: #94a3b8;">&bull;</span>
                          <span class="sp-dept-name" style="color: #64748b;"><?php echo htmlspecialchars($p['department']); ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- 2. Subject & Class -->
                <td>
                  <span class="sp-subject-pill" style="background: <?php echo $th['bg']; ?>; color: <?php echo $th['color']; ?>; border: 1px solid <?php echo $th['border']; ?>;">
                    <i class="fa-solid <?php echo $th['icon']; ?>" style="font-size: 0.72rem;"></i>
                    <span><?php echo htmlspecialchars($p['subject']); ?></span>
                  </span>
                  <div style="font-size: 0.74rem; color: #64748b; margin-top: 4px; display: flex; align-items: center; gap: 5px;">
                    <i class="fa-solid fa-graduation-cap" style="font-size: 0.68rem; color: #94a3b8;"></i>
                    <span class="sp-class-name" style="font-weight: 600; color: #475569;"><?php echo htmlspecialchars($p['class_name']); ?></span>
                  </div>
                </td>

                <!-- 3. Period Slot & Timing -->
                <td>
                  <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                    <span class="sp-slot-pill">
                      <i class="fa-solid fa-hourglass-half" style="font-size: 0.62rem;"></i>
                      <span><?php echo htmlspecialchars($p['period_name']); ?></span>
                    </span>
                    <span class="sp-time-chip">
                      <i class="fa-regular fa-clock" style="font-size: 0.68rem;"></i>
                      <span><?php echo htmlspecialchars($p['time_period']); ?></span>
                    </span>
                  </div>
                </td>

                <!-- 4. Schedule Day -->
                <td>
                  <span class="sp-day-pill">
                    <i class="fa-regular fa-calendar-days" style="color: #0284c7; font-size: 0.72rem;"></i>
                    <span><?php echo htmlspecialchars($p['day_of_week']); ?></span>
                  </span>
                </td>

                <!-- 5. Classroom / Venue -->
                <td>
                  <span class="sp-venue-pill">
                    <i class="fa-solid fa-door-open" style="color: #64748b; font-size: 0.72rem;"></i>
                    <span><?php echo htmlspecialchars($p['room_no']); ?></span>
                  </span>
                </td>

                <!-- 6. Actions -->
                <td style="text-align: right;">
                  <div style="display: inline-flex; align-items: center; gap: 5px; justify-content: flex-end;">
                    <?php if (in_array($current_user['role'], ['super_admin', 'admin'])): ?>
                      <button type="button" 
                              class="sp-btn-action" 
                              onclick='openEditModal(<?php echo json_encode($p); ?>)' 
                              title="Edit Period">
                        <i class="fa-solid fa-pen-to-square" style="color: #0284c7; font-size: 0.72rem;"></i>
                        <span>Edit</span>
                      </button>

                      <form method="POST" action="<?php echo ADMIN_URL; ?>/staff-periods" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to remove this period allocation?');">
                        <input type="hidden" name="action" value="delete_period">
                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                        <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_mode); ?>">
                        <button type="submit" class="sp-btn-action btn-danger" title="Delete Period">
                          <i class="fa-solid fa-trash-can" style="color: #ef4444; font-size: 0.72rem;"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <span style="font-size: 0.74rem; color: #94a3b8; font-weight: 600;">Read Only</span>
                    <?php endif; ?>
                  </div>
                </td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Table Footer Pagination & Per-Page Controls (Matches HRMS standard) -->
    <div class="pagination-controls-wrap">
      
      <!-- Left: Showing summary & Limit Selector -->
      <div class="pagination-meta-group">
        <span style="font-size: 0.82rem; color: #475569;">
          <?php if ($total_records > 0): ?>
            Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> periods
          <?php else: ?>
            Showing <strong>0</strong> periods
          <?php endif; ?>
        </span>

        <div class="pagination-limit-box">
          <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
          <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
            <?php foreach ($allowed_limits as $l): ?>
              <option value="<?php echo htmlspecialchars(get_staff_periods_pagination_url(1, $search, $filter_staff, $filter_day, $view_mode, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
                <?php echo $l; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Center/Right: Navigation page buttons -->
      <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav" aria-label="Staff Periods Master Schedule Pagination">
          <!-- Prev Page -->
          <a href="<?php echo $page > 1 ? htmlspecialchars(get_staff_periods_pagination_url($page - 1, $search, $filter_staff, $filter_day, $view_mode, $limit)) : '#'; ?>" 
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
            <a href="<?php echo htmlspecialchars(get_staff_periods_pagination_url($p, $search, $filter_staff, $filter_day, $view_mode, $limit)); ?>" 
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
          <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_staff_periods_pagination_url($page + 1, $search, $filter_staff, $filter_day, $view_mode, $limit)) : '#'; ?>" 
             class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
             aria-label="Next Page"
             title="Next Page">
            <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
          </a>
        </nav>
      <?php endif; ?>

      <!-- Legend / Session Status -->
      <div class="pagination-legend-text" style="font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 12px; margin-left: auto;">
        <span style="display: inline-flex; align-items: center; gap: 6px;">
          <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
          Active Schedule
        </span>
        <span style="color: #cbd5e1;">|</span>
        <span>Session: <strong style="color: #334155;"><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
      </div>

    </div>
  </div>

<!-- ══════════════════════════════════════════════════════
     VIEW 2: FACULTY TIMETABLE CARDS
     ══════════════════════════════════════════════════════ -->
<?php else: ?>
  <div class="hrms-card no-print periods-directory-card" style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
    <div class="hrms-card-header periods-card-header" style="border-bottom: none; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 20px; background: #ffffff;">
      <div class="periods-title-block">
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-calendar-days" style="color: #0284c7;"></i>
          Faculty Timetable Registers
          <span class="periods-session-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
            Session <?php echo htmlspecialchars($active_session['session_name']); ?>
          </span>
        </h3>
        <p class="periods-subtitle" style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
          Weekly lecture schedules organized per individual faculty member.
        </p>
      </div>

      <div class="periods-header-controls" style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
        <!-- View Switcher -->
        <div class="sp-view-toggle periods-view-toggle">
          <a href="<?php echo ADMIN_URL; ?>/staff-periods?view=table<?php echo $filter_staff > 0 ? '&staff_id='.$filter_staff : ''; ?><?php echo !empty($filter_day) ? '&day='.urlencode($filter_day) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo $limit !== 10 ? '&limit='.$limit : ''; ?>" 
             class="sp-view-tab <?php echo $view_mode !== 'schedule' ? 'active' : ''; ?>">
            <i class="fa-solid fa-table-list"></i>
            <span>Table View</span>
          </a>
          <a href="<?php echo ADMIN_URL; ?>/staff-periods?view=schedule<?php echo $filter_staff > 0 ? '&staff_id='.$filter_staff : ''; ?><?php echo !empty($filter_day) ? '&day='.urlencode($filter_day) : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo $limit !== 10 ? '&limit='.$limit : ''; ?>" 
             class="sp-view-tab <?php echo $view_mode === 'schedule' ? 'active' : ''; ?>">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Faculty Timetables</span>
          </a>
        </div>

        <div class="periods-records-count" style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
          <span>Faculty on Timetable: <strong style="color: #0f172a;"><?php echo count($periods_by_staff); ?></strong></span>
        </div>
      </div>
    </div>
  </div>
  
  <?php if (empty($periods_by_staff)): ?>
    <div class="hrms-card no-print periods-directory-card" style="padding: 50px 20px; text-align: center; border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff;">
      <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
        <i class="fa-regular fa-calendar-xmark"></i>
      </div>
      <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Faculty Timetables Found</h4>
      <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">No periods match your active search or day filters for Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.</p>
      <a href="<?php echo ADMIN_URL; ?>/staff-periods?view=<?php echo urlencode($view_mode); ?>" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
        <i class="fa-solid fa-rotate-left"></i> Reset All Filters
      </a>
    </div>
  <?php else: ?>
    <?php foreach ($periods_by_staff as $st_id => $st_data): ?>
      <?php 
        $st_card_photo = get_staff_photo_url($st_data);
        $has_card_photo = !empty($st_card_photo);
        $f_card_letter = strtoupper(substr($st_data['staff_name'], 0, 1));
      ?>
      <div class="faculty-schedule-card no-print">
        
        <div class="faculty-schedule-header">
          <div style="display: flex; align-items: center; gap: 12px;">
            <?php if ($has_card_photo): ?>
              <img src="<?php echo htmlspecialchars($st_card_photo); ?>" alt="<?php echo htmlspecialchars($st_data['staff_name']); ?>" class="staff-avatar-img" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div class="staff-avatar-init" style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                <?php echo $f_card_letter; ?>
              </div>
            <?php else: ?>
              <div class="staff-avatar-init" style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <?php echo $f_card_letter; ?>
              </div>
            <?php endif; ?>

            <div>
              <h3 class="faculty-card-name" style="margin: 0; font-size: 0.98rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
                <?php echo htmlspecialchars($st_data['staff_name']); ?>
              </h3>
              <div class="faculty-card-sub" style="font-size: 0.74rem; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <span class="sp-code-pill">
                  <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
                  <span><?php echo htmlspecialchars($st_data['employee_code']); ?></span>
                </span>
                <span>&bull;</span>
                <span><?php echo htmlspecialchars($st_data['designation']); ?> (<?php echo htmlspecialchars($st_data['department']); ?>)</span>
              </div>
            </div>
          </div>

          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="faculty-card-badge" style="font-size: 0.76rem; font-weight: 600; color: #0284c7; background: #f0f9ff; padding: 3px 10px; border-radius: 20px; border: 1px solid #bae6fd;">
              <i class="fa-solid fa-clock" style="font-size: 0.70rem;"></i> <?php echo count($st_data['periods']); ?> Periods Assigned
            </span>
          </div>
        </div>

        <div class="period-timeline-strip">
          <?php foreach ($st_data['periods'] as $p_item): ?>
            <?php $th_card = get_subject_theme($p_item['subject']); ?>
            <div class="period-timeline-item" style="border-left-color: <?php echo $th_card['color']; ?>;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span class="sp-slot-pill">
                  <i class="fa-solid fa-hourglass-half" style="font-size: 0.60rem;"></i>
                  <span><?php echo htmlspecialchars($p_item['period_name']); ?></span>
                </span>
                <span class="sp-venue-pill" style="font-size: 0.72rem; padding: 2px 7px;">
                  <i class="fa-solid fa-door-open" style="color: #64748b; font-size: 0.68rem;"></i>
                  <span><?php echo htmlspecialchars($p_item['room_no']); ?></span>
                </span>
              </div>

              <div style="font-weight: 700; font-size: 0.90rem; color: #0f172a; font-family: 'Outfit', sans-serif; margin-bottom: 3px;">
                <span class="sp-subject-pill" style="background: <?php echo $th_card['bg']; ?>; color: <?php echo $th_card['color']; ?>; border: 1px solid <?php echo $th_card['border']; ?>; padding: 2px 7px; font-size: 0.74rem;">
                  <i class="fa-solid <?php echo $th_card['icon']; ?>"></i>
                  <span><?php echo htmlspecialchars($p_item['subject']); ?></span>
                </span>
              </div>

              <div class="period-item-class" style="font-size: 0.74rem; color: #64748b; margin-bottom: 10px; display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-graduation-cap" style="color: #94a3b8; font-size: 0.70rem;"></i>
                <span><?php echo htmlspecialchars($p_item['class_name']); ?></span>
                <span>&bull;</span>
                <span><?php echo htmlspecialchars($p_item['day_of_week']); ?></span>
              </div>

              <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 8px;">
                <span class="sp-time-chip" style="font-size: 0.72rem; padding: 2px 7px;">
                  <i class="fa-regular fa-clock" style="font-size: 0.65rem;"></i>
                  <span><?php echo htmlspecialchars($p_item['time_period']); ?></span>
                </span>

                <?php if (in_array($current_user['role'], ['super_admin', 'admin'])): ?>
                  <div style="display: flex; gap: 4px;">
                    <button type="button" class="sp-btn-action" style="padding: 3px 8px; font-size: 0.70rem;" onclick='openEditModal(<?php echo json_encode($p_item); ?>)' title="Edit">
                      <i class="fa-solid fa-pen-to-square" style="color: #0284c7;"></i>
                    </button>
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/staff-periods" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this period?');">
                      <input type="hidden" name="action" value="delete_period">
                      <input type="hidden" name="id" value="<?php echo $p_item['id']; ?>">
                      <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_mode); ?>">
                      <button type="submit" class="sp-btn-action btn-danger" style="padding: 3px 8px; font-size: 0.70rem;" title="Delete">
                        <i class="fa-solid fa-trash-can" style="color: #ef4444;"></i>
                      </button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>

            </div>
          <?php endforeach; ?>
        </div>

      </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     MODAL: ASSIGN NEW STAFF PERIOD
     ══════════════════════════════════════════════════════ -->
<div id="assignPeriodModal" class="hrms-modal" onclick="if (event.target === this) closeAssignModal();">
  <div class="hrms-modal-card" style="max-width: 580px;">
    
    <div class="hrms-modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 9px; background: rgba(0, 146, 223, 0.12); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-calendar-plus"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Assign Faculty Period
          </h3>
          <span style="font-size: 0.74rem; color: #64748b;">Schedule staff period with subject, class and timing</span>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAssignModal();" style="border: none; cursor: pointer; padding: 4px 8px; font-size: 1.2rem; background: transparent; color: #94a3b8;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="hrms-modal-body">
      <form method="POST" action="<?php echo ADMIN_URL; ?>/staff-periods">
        <input type="hidden" name="action" value="add_period">
        <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_mode); ?>">

        <!-- 1. Staff Name -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="input-field-label">
            Staff Member / Faculty <span class="req">*</span>
          </label>
          <select name="staff_id" class="settings-input" required>
            <option value="">-- Choose Faculty Member --</option>
            <?php foreach ($staff_members as $sm): ?>
              <option value="<?php echo $sm['staff_id']; ?>">
                <?php echo htmlspecialchars($sm['full_name']); ?> &ndash; <?php echo htmlspecialchars($sm['designation']); ?> (<?php echo htmlspecialchars($sm['department']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- 2. Subject & Class -->
        <div class="periods-modal-grid-2" style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 14px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="input-field-label">
              Subject Name <span class="req">*</span>
            </label>
            <input type="text" name="subject" class="settings-input" required placeholder="e.g. Mathematics, Physics, Organic Chem" list="subjectPresets">
            <datalist id="subjectPresets">
              <option value="Physics">
              <option value="Advanced Physics">
              <option value="Chemistry">
              <option value="Organic Chemistry">
              <option value="Mathematics & CET">
              <option value="Calculus & Vector Algebra">
              <option value="Biology & NEET">
              <option value="English Literature">
              <option value="Computer Science & Coding">
            </datalist>
          </div>

          <div class="form-group">
            <label class="input-field-label">
              Class / Batch / Grade <span class="req">*</span>
            </label>
            <input type="text" name="class_name" class="settings-input" required placeholder="e.g. Class 12th PCM" list="classPresets">
            <datalist id="classPresets">
              <option value="Class 10th (Foundation)">
              <option value="Class 11th (PCM)">
              <option value="Class 11th (PCB / NEET)">
              <option value="Class 12th (IIT-JEE)">
              <option value="Class 12th (NEET-UG)">
              <option value="Class 12th (Commerce)">
            </datalist>
          </div>
        </div>

        <!-- 3. Period Slot & Time Period -->
        <div class="periods-modal-grid-2" style="display: grid; grid-template-columns: 1fr 1.4fr; gap: 14px; margin-bottom: 10px;">
          <div class="form-group">
            <label class="input-field-label">
              Period Slot <span class="req">*</span>
            </label>
            <select name="period_name" id="addPeriodName" class="settings-input" required>
              <option value="Period 1">Period 1</option>
              <option value="Period 2">Period 2</option>
              <option value="Period 3">Period 3</option>
              <option value="Period 4">Period 4</option>
              <option value="Period 5">Period 5</option>
              <option value="Period 6">Period 6</option>
              <option value="Period 7">Period 7</option>
              <option value="Period 8">Period 8</option>
              <option value="Zero Period">Zero Period</option>
              <option value="Remedial / Doubt Session">Remedial / Doubt Session</option>
            </select>
          </div>

          <div class="form-group">
            <label class="input-field-label">
              Time Period <span class="req">*</span>
            </label>
            <input type="text" name="time_period" id="addTimePeriod" class="settings-input" required placeholder="e.g. 08:30 AM - 09:15 AM">
          </div>
        </div>

        <!-- Quick Time Presets -->
        <div class="periods-presets-box" style="margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px;">
          <span style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; display: block; margin-bottom: 8px;">
            <i class="fa-solid fa-wand-magic-sparkles" style="color: #0092df;"></i> Quick Timing Slot Presets:
          </span>
          <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('addPeriodName', 'addTimePeriod', 'Period 1', '08:30 AM - 09:15 AM')">08:30-09:15 AM (P1)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('addPeriodName', 'addTimePeriod', 'Period 2', '09:15 AM - 10:00 AM')">09:15-10:00 AM (P2)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('addPeriodName', 'addTimePeriod', 'Period 3', '10:15 AM - 11:00 AM')">10:15-11:00 AM (P3)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('addPeriodName', 'addTimePeriod', 'Period 4', '11:00 AM - 11:45 AM')">11:00-11:45 AM (P4)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('addPeriodName', 'addTimePeriod', 'Period 5', '12:30 PM - 01:15 PM')">12:30-01:15 PM (P5)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('addPeriodName', 'addTimePeriod', 'Period 6', '01:15 PM - 02:00 PM')">01:15-02:00 PM (P6)</button>
          </div>
        </div>

        <!-- 4. Schedule Day & Room No -->
        <div class="periods-modal-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 22px;">
          <div class="form-group">
            <label class="input-field-label">
              Day of Week <span class="req">*</span>
            </label>
            <select name="day_of_week" class="settings-input" required>
              <option value="Monday - Friday">Monday - Friday</option>
              <option value="Monday - Saturday">Monday - Saturday</option>
              <option value="Monday">Monday</option>
              <option value="Tuesday">Tuesday</option>
              <option value="Wednesday">Wednesday</option>
              <option value="Thursday">Thursday</option>
              <option value="Friday">Friday</option>
              <option value="Saturday">Saturday</option>
            </select>
          </div>

          <div class="form-group">
            <label class="input-field-label">
              Classroom / Room No.
            </label>
            <input type="text" name="room_no" class="settings-input" placeholder="e.g. Hall 1, Lab B, Room 102" value="Lecture Hall 1">
          </div>
        </div>

        <!-- Actions -->
        <div class="periods-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
          <button type="button" onclick="closeAssignModal();" class="roaster-btn secondary" style="padding: 9px 18px;">
            Cancel
          </button>
          <button type="submit" class="roaster-add-btn" style="padding: 9px 22px;">
            <div class="btn-icon-circle"><i class="fa-solid fa-check"></i></div>
            <span>Assign Period</span>
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: EDIT STAFF PERIOD
     ══════════════════════════════════════════════════════ -->
<div id="editPeriodModal" class="hrms-modal" onclick="if (event.target === this) closeEditModal();">
  <div class="hrms-modal-card" style="max-width: 580px;">
    
    <div class="hrms-modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 9px; background: rgba(0, 146, 223, 0.12); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-pen-to-square"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Edit Period Assignment
          </h3>
          <span style="font-size: 0.74rem; color: #64748b;">Modify timing, subject or classroom allocation</span>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeEditModal();" style="border: none; cursor: pointer; padding: 4px 8px; font-size: 1.2rem; background: transparent; color: #94a3b8;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="hrms-modal-body">
      <form method="POST" action="<?php echo ADMIN_URL; ?>/staff-periods">
        <input type="hidden" name="action" value="edit_period">
        <input type="hidden" name="id" id="edit_id" value="">
        <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_mode); ?>">

        <!-- Staff Member -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="input-field-label">
            Staff Member / Faculty <span class="req">*</span>
          </label>
          <select name="staff_id" id="edit_staff_id" class="settings-input" required>
            <?php foreach ($staff_members as $sm): ?>
              <option value="<?php echo $sm['staff_id']; ?>">
                <?php echo htmlspecialchars($sm['full_name']); ?> &ndash; <?php echo htmlspecialchars($sm['designation']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Subject & Class -->
        <div class="periods-modal-grid-2" style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 14px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="input-field-label">
              Subject Name <span class="req">*</span>
            </label>
            <input type="text" name="subject" id="edit_subject" class="settings-input" required>
          </div>

          <div class="form-group">
            <label class="input-field-label">
              Class / Batch / Grade <span class="req">*</span>
            </label>
            <input type="text" name="class_name" id="edit_class_name" class="settings-input" required>
          </div>
        </div>

        <!-- Period Slot & Time Period -->
        <div class="periods-modal-grid-2" style="display: grid; grid-template-columns: 1fr 1.4fr; gap: 14px; margin-bottom: 10px;">
          <div class="form-group">
            <label class="input-field-label">
              Period Slot <span class="req">*</span>
            </label>
            <select name="period_name" id="editPeriodName" class="settings-input" required>
              <option value="Period 1">Period 1</option>
              <option value="Period 2">Period 2</option>
              <option value="Period 3">Period 3</option>
              <option value="Period 4">Period 4</option>
              <option value="Period 5">Period 5</option>
              <option value="Period 6">Period 6</option>
              <option value="Period 7">Period 7</option>
              <option value="Period 8">Period 8</option>
              <option value="Zero Period">Zero Period</option>
              <option value="Remedial / Doubt Session">Remedial / Doubt Session</option>
            </select>
          </div>

          <div class="form-group">
            <label class="input-field-label">
              Time Period <span class="req">*</span>
            </label>
            <input type="text" name="time_period" id="editTimePeriod" class="settings-input" required>
          </div>
        </div>

        <!-- Quick Presets -->
        <div class="periods-presets-box" style="margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px;">
          <span style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; display: block; margin-bottom: 8px;">
            <i class="fa-solid fa-wand-magic-sparkles" style="color: #0092df;"></i> Quick Timing Slot Presets:
          </span>
          <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('editPeriodName', 'editTimePeriod', 'Period 1', '08:30 AM - 09:15 AM')">08:30-09:15 AM (P1)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('editPeriodName', 'editTimePeriod', 'Period 2', '09:15 AM - 10:00 AM')">09:15-10:00 AM (P2)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('editPeriodName', 'editTimePeriod', 'Period 3', '10:15 AM - 11:00 AM')">10:15-11:00 AM (P3)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('editPeriodName', 'editTimePeriod', 'Period 4', '11:00 AM - 11:45 AM')">11:00-11:45 AM (P4)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('editPeriodName', 'editTimePeriod', 'Period 5', '12:30 PM - 01:15 PM')">12:30-01:15 PM (P5)</button>
            <button type="button" class="preset-slot-btn" onclick="applyPresetTiming('editPeriodName', 'editTimePeriod', 'Period 6', '01:15 PM - 02:00 PM')">01:15-02:00 PM (P6)</button>
          </div>
        </div>

        <!-- Day of Week & Room No -->
        <div class="periods-modal-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 22px;">
          <div class="form-group">
            <label class="input-field-label">
              Day of Week <span class="req">*</span>
            </label>
            <select name="day_of_week" id="edit_day_of_week" class="settings-input" required>
              <option value="Monday - Friday">Monday - Friday</option>
              <option value="Monday - Saturday">Monday - Saturday</option>
              <option value="Monday">Monday</option>
              <option value="Tuesday">Tuesday</option>
              <option value="Wednesday">Wednesday</option>
              <option value="Thursday">Thursday</option>
              <option value="Friday">Friday</option>
              <option value="Saturday">Saturday</option>
            </select>
          </div>

          <div class="form-group">
            <label class="input-field-label">
              Classroom / Room No.
            </label>
            <input type="text" name="room_no" id="edit_room_no" class="settings-input">
          </div>
        </div>

        <!-- Actions -->
        <div class="periods-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
          <button type="button" onclick="closeEditModal();" class="roaster-btn secondary" style="padding: 9px 18px;">
            Cancel
          </button>
          <button type="submit" class="roaster-add-btn" style="padding: 9px 22px;">
            <div class="btn-icon-circle"><i class="fa-solid fa-floppy-disk"></i></div>
            <span>Update Period</span>
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<script>
function openAssignModal() {
  const modal = document.getElementById('assignPeriodModal');
  if (modal) modal.classList.add('show');
}
function closeAssignModal() {
  const modal = document.getElementById('assignPeriodModal');
  if (modal) modal.classList.remove('show');
}

function openEditModal(period) {
  document.getElementById('edit_id').value = period.id;
  document.getElementById('edit_staff_id').value = period.staff_id;
  document.getElementById('edit_subject').value = period.subject;
  document.getElementById('edit_class_name').value = period.class_name;
  document.getElementById('editPeriodName').value = period.period_name;
  document.getElementById('editTimePeriod').value = period.time_period;
  document.getElementById('edit_day_of_week').value = period.day_of_week;
  document.getElementById('edit_room_no').value = period.room_no;

  const modal = document.getElementById('editPeriodModal');
  if (modal) modal.classList.add('show');
}
function closeEditModal() {
  const modal = document.getElementById('editPeriodModal');
  if (modal) modal.classList.remove('show');
}

function applyPresetTiming(slotSelectId, timeInputId, slotName, timeStr) {
  const slotSel = document.getElementById(slotSelectId);
  const timeInp = document.getElementById(timeInputId);
  if (slotSel) slotSel.value = slotName;
  if (timeInp) timeInp.value = timeStr;
}

window.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeAssignModal();
    closeEditModal();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
