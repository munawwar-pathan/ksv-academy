<?php
require_once __DIR__ . '/../config.php';

// Ensure user is staff or admin
$current_user = require_login(['staff', 'super_admin']);
require_menu_permission('staff-periods');

$page_title = 'My Staff Periods | KSV Academy HRMS';
$page_heading = 'My Staff Periods & Class Schedule';
$page_subheading = 'Personal Academic Timetable, Allocated Lectures & Classroom Distribution';
$active_menu = 'staff-periods';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'] ?? 1;

// Resolve Logged-in Staff Profile Info
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

// Filter parameters
$search = trim($_GET['search'] ?? '');
$filter_day = trim($_GET['day'] ?? '');

// Fetch only this staff's assigned periods
$query = "
    SELECT * FROM staff_periods 
    WHERE staff_id = ? AND session_id = ?
";
$params = [$staff_id, $session_id];

if (!empty($search)) {
    $query .= " AND (subject LIKE ? OR class_name LIKE ? OR room_no LIKE ? OR period_name LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($filter_day)) {
    $query .= " AND day_of_week LIKE ?";
    $params[] = "%{$filter_day}%";
}

$query .= " 
    ORDER BY 
        CASE 
            WHEN period_name LIKE '%Period 1%' THEN 1
            WHEN period_name LIKE '%Period 2%' THEN 2
            WHEN period_name LIKE '%Period 3%' THEN 3
            WHEN period_name LIKE '%Period 4%' THEN 4
            WHEN period_name LIKE '%Period 5%' THEN 5
            WHEN period_name LIKE '%Period 6%' THEN 6
            WHEN period_name LIKE '%Period 7%' THEN 7
            WHEN period_name LIKE '%Period 8%' THEN 8
            ELSE 9
        END ASC,
        time_period ASC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics calculation for this staff
$all_stmt = $db->prepare("SELECT * FROM staff_periods WHERE staff_id = ? AND session_id = ?");
$all_stmt->execute([$staff_id, $session_id]);
$all_periods = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_assigned = count($all_periods);
$distinct_classes = count(array_unique(array_filter(array_column($all_periods, 'class_name'))));
$distinct_subjects = count(array_unique(array_filter(array_column($all_periods, 'subject'))));
$distinct_rooms = count(array_unique(array_filter(array_column($all_periods, 'room_no'))));

// Available days for filter dropdown
$days_list = [
    'Monday - Friday',
    'Monday - Saturday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday'
];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<!-- 4 Sober Monthly KPI Summary Cards -->
<div class="stat-grid" style="margin-bottom: 22px;">

  <!-- Total Allocated Periods -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Assigned Periods</span>
          <span class="stat-number"><?php echo $total_assigned; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Personal Allocated Daily Slots</span>
      <span style="color: #0284c7; font-weight: 700;">Academic Master</span>
    </div>
  </div>

  <!-- Allocated Batches -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Classes &amp; Batches</span>
          <span class="stat-number"><?php echo $distinct_classes; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-graduation-cap"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Active Sections Assigned</span>
      <span style="color: #059669; font-weight: 700;">Enrolled</span>
    </div>
  </div>

  <!-- Distinct Subjects -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Curriculum Subjects</span>
          <span class="stat-number"><?php echo $distinct_subjects; ?></span>
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
      <span>Syllabus Modules Taught</span>
      <span style="color: #d97706; font-weight: 700;">Disciplines</span>
    </div>
  </div>

  <!-- Assigned Rooms / Lecture Halls -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Lecture Halls &amp; Labs</span>
          <span class="stat-number"><?php echo $distinct_rooms; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-chalkboard"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Campus Classrooms &amp; Laboratories</span>
      <span style="color: #7c3aed; font-weight: 700;">Designated</span>
    </div>
  </div>

</div>

<!-- Tabular Form Register Card -->
<div class="hrms-card" style="border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 30px;">
  
  <!-- Table Header Bar & Filter Controls -->
  <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; background: #ffffff;">
    <div>
      <h3 style="margin: 0; font-size: 0.98rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-calendar-week" style="color: #0284c7;"></i>
        My Assigned Periods Timetable
        <span style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          <?php echo count($periods); ?> Assigned Slots
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.77rem; color: #64748b;">
        Allocated lectures, batch schedules and classroom venues for <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong> (<?php echo htmlspecialchars($staff_profile['employee_code']); ?>).
      </p>
    </div>

    <!-- Filter Form & Actions -->
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <form method="GET" action="<?php echo STAFF_URL; ?>/staff-periods" style="margin: 0; display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        
        <!-- Search Input -->
        <div style="position: relative;">
          <input type="text" 
                 name="search" 
                 value="<?php echo htmlspecialchars($search); ?>" 
                 placeholder="Search subject, room, class..." 
                 class="hrms-input" 
                 style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; width: 190px; border: 1px solid #cbd5e1;">
        </div>

        <!-- Day Filter Dropdown -->
        <select name="day" onchange="this.form.submit()" class="hrms-input" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; min-width: 140px; border: 1px solid #cbd5e1;">
          <option value="">All Days</option>
          <?php foreach ($days_list as $d): ?>
            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $filter_day === $d ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($d); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <?php if (!empty($search) || !empty($filter_day)): ?>
          <a href="<?php echo STAFF_URL; ?>/staff-periods" class="btn-hrms btn-hrms-outline btn-hrms-sm" style="font-size: 0.78rem; padding: 6px 10px;" title="Reset Filters">
            <i class="fa-solid fa-rotate-left"></i> Clear
          </a>
        <?php endif; ?>
      </form>

      <!-- Print Button -->
      <button type="button" onclick="window.print();" class="btn-hrms btn-hrms-outline btn-hrms-sm" style="font-size: 0.78rem; padding: 6px 12px; gap: 6px;">
        <i class="fa-solid fa-print"></i> Print Schedule
      </button>
    </div>
  </div>

  <!-- Sober Tabular Form -->
  <div class="table-responsive">
    <table class="hrms-table staff-id-table">
      <thead>
        <tr>
          <th style="width: 50px; text-align: center;">#</th>
          <th style="min-width: 110px;">Slot / Period</th>
          <th style="min-width: 140px;">Timing Schedule</th>
          <th style="min-width: 180px;">Subject Taught</th>
          <th style="min-width: 140px;">Class &amp; Batch</th>
          <th style="min-width: 140px;">Days of Week</th>
          <th style="min-width: 140px;">Classroom / Lab</th>
          <th style="min-width: 100px; text-align: center;">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($periods)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 48px; height: 48px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.3rem; color: #94a3b8;">
                <i class="fa-solid fa-calendar-xmark"></i>
              </div>
              <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-bottom: 4px;">No Assigned Periods Found</h4>
              <p style="font-size: 0.8rem; color: #64748b; margin: 0 0 10px;">
                <?php echo (!empty($search) || !empty($filter_day)) ? 'No periods match your filter criteria.' : 'No lecture periods have been allocated to your profile for this session yet.'; ?>
              </p>
              <?php if (!empty($search) || !empty($filter_day)): ?>
                <a href="<?php echo STAFF_URL; ?>/staff-periods" class="btn-hrms btn-hrms-outline btn-hrms-sm">
                  <i class="fa-solid fa-rotate-left"></i> Reset Filter
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($periods as $idx => $p): ?>
            <tr>
              
              <!-- Index Number -->
              <td style="text-align: center; font-weight: 700; color: #475569; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
                <?php echo sprintf('%02d', $idx + 1); ?>
              </td>

              <!-- Slot / Period Name -->
              <td>
                <span class="sid-code-pill" style="font-weight: 700; background: #f0f9ff; border-color: #bae6fd; color: #0284c7;">
                  <i class="fa-solid fa-bell" style="font-size: 0.65rem;"></i>
                  <span><?php echo htmlspecialchars($p['period_name']); ?></span>
                </span>
              </td>

              <!-- Timing Schedule -->
              <td>
                <span class="sid-dept-pill" style="color: #0f172a; gap: 6px; font-weight: 700;">
                  <i class="fa-regular fa-clock" style="color: #0284c7; font-size: 0.72rem;"></i>
                  <span><?php echo htmlspecialchars($p['time_period']); ?></span>
                </span>
              </td>

              <!-- Subject Taught -->
              <td>
                <div style="font-weight: 700; color: #0f172a; font-size: 0.86rem; font-family: 'Outfit', sans-serif;">
                  <?php echo htmlspecialchars($p['subject']); ?>
                </div>
                <div style="font-size: 0.73rem; color: #64748b; margin-top: 2px;">
                  Academic Session: <?php echo htmlspecialchars($active_session['session_name']); ?>
                </div>
              </td>

              <!-- Class & Batch -->
              <td>
                <span class="sid-std-pill" style="gap: 5px;">
                  <i class="fa-solid fa-graduation-cap" style="font-size: 0.68rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($p['class_name']); ?></span>
                </span>
              </td>

              <!-- Days of Week -->
              <td>
                <span style="font-size: 0.76rem; font-weight: 600; color: #334155; display: inline-flex; align-items: center; gap: 5px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 5px;">
                  <i class="fa-regular fa-calendar" style="color: #64748b; font-size: 0.7rem;"></i>
                  <span><?php echo htmlspecialchars($p['day_of_week']); ?></span>
                </span>
              </td>

              <!-- Classroom / Lab -->
              <td>
                <span class="sid-dept-pill" style="color: #334155; gap: 5px;">
                  <i class="fa-solid fa-location-dot" style="color: #d97706; font-size: 0.7rem;"></i>
                  <span><?php echo htmlspecialchars($p['room_no']); ?></span>
                </span>
              </td>

              <!-- Status -->
              <td style="text-align: center;">
                <span class="sid-status-active" style="background: #f0fdf4; border-color: #bbf7d0; color: #166534; font-size: 0.72rem; padding: 2px 8px;">
                  <span style="width: 5px; height: 5px; border-radius: 50%; background: #16a34a; display: inline-block;"></span>
                  Active Slot
                </span>
              </td>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Table Footer Summary -->
  <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; font-size: 0.78rem; color: #64748b;">
    <div>
      Showing <strong><?php echo count($periods); ?></strong> periods allocated to <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong>.
    </div>
    <div style="display: flex; align-items: center; gap: 14px;">
      <span><strong style="color: #0284c7;"><?php echo $distinct_classes; ?></strong> Batches</span>
      <span>•</span>
      <span><strong style="color: #059669;"><?php echo $distinct_subjects; ?></strong> Subjects</span>
      <span>•</span>
      <span><strong style="color: #7c3aed;"><?php echo $distinct_rooms; ?></strong> Venues</span>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
