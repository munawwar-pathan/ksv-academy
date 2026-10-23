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

// Pagination Settings (10, 20, 30, 40, 50 - plus 5 for convenience)
$allowed_limits = [5, 10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Count total matching periods for this staff
$count_query = "
    SELECT COUNT(*) FROM staff_periods 
    WHERE staff_id = ? AND session_id = ?
";
$count_params = [$staff_id, $session_id];

if (!empty($search)) {
    $count_query .= " AND (subject LIKE ? OR class_name LIKE ? OR room_no LIKE ? OR period_name LIKE ?)";
    $like = "%{$search}%";
    $count_params[] = $like;
    $count_params[] = $like;
    $count_params[] = $like;
    $count_params[] = $like;
}

if (!empty($filter_day)) {
    $count_query .= " AND day_of_week LIKE ?";
    $count_params[] = "%{$filter_day}%";
}

$count_stmt = $db->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = intval($count_stmt->fetchColumn() ?: 0);

// Calculate Pagination Bounds
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch only this staff's assigned periods with pagination
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
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($query);
$b_idx = 1;
foreach ($params as $p) {
    $stmt->bindValue($b_idx++, $p);
}
$stmt->bindValue($b_idx++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($b_idx++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for generating staff-periods pagination links
function get_staff_periods_pagination_url($target_page, $search, $filter_day, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_day)) $params['day'] = $filter_day;
    if ($limit != 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return STAFF_URL . '/staff-periods' . (!empty($params) ? '?' . http_build_query($params) : '');
}

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

<style>
/* ── Staff Periods Directory Table (Simple, Sober & Premium) ── */
.periods-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}
@media (max-width: 1024px) {
  .periods-stat-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 600px) {
  .periods-stat-grid {
    grid-template-columns: 1fr;
  }
}

.periods-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
  overflow: hidden;
  margin-bottom: 30px;
}

.periods-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  background: #ffffff;
}

.staff-id-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: auto;
}
.staff-id-table thead th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.70rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 11px 10px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.staff-id-table tbody td {
  padding: 11px 10px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.82rem;
  color: #1e293b;
}
.staff-id-table tbody tr {
  transition: background-color 0.15s ease;
}
.staff-id-table tbody tr:hover {
  background-color: #f8fafc;
}
.staff-id-table tbody tr:last-child td {
  border-bottom: none;
}

/* Sober Badges & Tags */
.sid-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
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
.sid-status-verified {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  font-size: 0.70rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 14px;
  white-space: nowrap;
}
</style>

<!-- 4 Sober Monthly KPI Summary Cards (4-Column Balanced Grid) -->
<div class="periods-stat-grid">

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
      <span>Personal Allocated Slots</span>
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
      <span>Campus Classrooms &amp; Labs</span>
      <span style="color: #7c3aed; font-weight: 700;">Designated</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     FILTER & SEARCH TOOLBAR (Simple & Sober)
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-top: 6px; margin-bottom: 20px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Search & Day Filter Form -->
    <form method="GET" action="<?php echo STAFF_URL; ?>/staff-periods" class="roaster-filter-form">
      <?php if ($limit !== 10): ?>
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      <?php endif; ?>

      <!-- Search Input -->
      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search subject, batch, room..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_staff_periods_pagination_url(1, '', $filter_day, $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <!-- Day Filter Dropdown -->
      <div class="roaster-select-box">
        <select name="day" onchange="this.form.submit()">
          <option value="">All Days of Week</option>
          <?php foreach ($days_list as $d): ?>
            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $filter_day === $d ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($d); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!empty($search) || !empty($filter_day)): ?>
        <a href="<?php echo STAFF_URL; ?>/staff-periods<?php echo $limit !== 10 ? '?limit=' . $limit : ''; ?>" class="roaster-btn secondary" style="gap: 5px; font-size: 0.80rem; text-decoration: none; padding: 7px 12px; border-radius: 8px;">
          <i class="fa-solid fa-rotate-left"></i> Reset Filters
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Count Badge -->
    <div class="roaster-toolbar-actions">
      <div class="scholars-count-badge">
        <span class="count-dot" style="background: #0284c7;"></span>
        <span><strong><?php echo $total_records; ?></strong> Assigned Slots</span>
      </div>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     FACULTY PERIODS DIRECTORY TABLE (Simple & Sober)
     ══════════════════════════════════════════════════════ -->
<div class="periods-card no-print" id="staffPeriodsDirectoryCard">
  <div class="periods-card-header">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 0.96rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-calendar-week" style="color: #0284c7;"></i>
        My Assigned Periods Timetable
        <span style="font-size: 0.72rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Allocated lectures, batch schedules and classroom venues for <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong> (<?php echo htmlspecialchars($staff_profile['employee_code']); ?>).
      </p>
    </div>
    <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
      <span>Total Records: <strong style="color: #0f172a;"><?php echo $total_records; ?></strong></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="hrms-table staff-id-table">
      <thead>
        <tr>
          <th style="width: 40px; text-align: center;">#</th>
          <th style="width: 95px;">Period</th>
          <th style="width: 145px;">Timing</th>
          <th style="min-width: 160px;">Subject Taught</th>
          <th style="width: 140px;">Class &amp; Batch</th>
          <th style="width: 130px;">Day</th>
          <th style="width: 130px;">Venue</th>
          <th style="width: 80px; text-align: center;">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($periods)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-calendar-xmark"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Assigned Periods Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                <?php echo (!empty($search) || !empty($filter_day)) ? 'No lecture periods match your search filters.' : 'No lecture periods have been allocated to your profile for this session yet.'; ?>
              </p>
              <?php if (!empty($search) || !empty($filter_day)): ?>
                <a href="<?php echo STAFF_URL; ?>/staff-periods" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none; padding: 7px 14px; border-radius: 8px;">
                  <i class="fa-solid fa-rotate-left"></i> Reset All Filters
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($periods as $idx => $p): ?>
            <tr>
              
              <!-- 1. Index -->
              <td style="text-align: center; font-weight: 600; color: #94a3b8; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.78rem;">
                <?php echo sprintf('%02d', $start_record + $idx); ?>
              </td>

              <!-- 2. Slot / Period Name -->
              <td>
                <span class="sid-code-pill">
                  <i class="fa-solid fa-bell" style="font-size: 0.58rem;"></i>
                  <span><?php echo htmlspecialchars($p['period_name']); ?></span>
                </span>
              </td>

              <!-- 3. Timing Schedule -->
              <td>
                <div style="font-weight: 700; color: #0f172a; font-size: 0.82rem; display: flex; align-items: center; gap: 5px; line-height: 1.35;">
                  <i class="fa-regular fa-clock" style="color: #0284c7; font-size: 0.70rem;"></i>
                  <span><?php echo htmlspecialchars($p['time_period']); ?></span>
                </div>
                <div style="font-size: 0.71rem; color: #64748b; margin-top: 2px;">Daily Academic Slot</div>
              </td>

              <!-- 4. Subject Taught -->
              <td>
                <div style="font-weight: 700; color: #0f172a; font-size: 0.86rem; font-family: 'Outfit', sans-serif; line-height: 1.35;">
                  <?php echo htmlspecialchars($p['subject']); ?>
                </div>
                <div style="font-size: 0.71rem; color: #64748b; margin-top: 2px;">
                  Regular Curriculum Module
                </div>
              </td>

              <!-- 5. Class & Batch -->
              <td>
                <div style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #1e293b; font-size: 0.81rem;">
                  <i class="fa-solid fa-graduation-cap" style="font-size: 0.68rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($p['class_name']); ?></span>
                </div>
              </td>

              <!-- 6. Days of Week -->
              <td>
                <div style="display: flex; align-items: center; gap: 6px; color: #334155; font-size: 0.79rem; font-weight: 500;">
                  <i class="fa-regular fa-calendar" style="color: #0284c7; font-size: 0.70rem;"></i>
                  <span><?php echo htmlspecialchars($p['day_of_week']); ?></span>
                </div>
              </td>

              <!-- 7. Classroom / Lab -->
              <td>
                <div style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #1e293b; font-size: 0.81rem;">
                  <i class="fa-solid fa-location-dot" style="color: #d97706; font-size: 0.68rem;"></i>
                  <span><?php echo htmlspecialchars($p['room_no']); ?></span>
                </div>
              </td>

              <!-- 8. Status -->
              <td style="text-align: center;">
                <span class="sid-status-verified">
                  <i class="fa-solid fa-check" style="font-size: 0.58rem;"></i> Active
                </span>
              </td>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Table Footer Pagination & Per-Page Controls (Standard Portal Pagination) -->
  <div class="pagination-controls-wrap">
    
    <!-- Left: Showing summary & Limit Selector -->
    <div class="pagination-meta-group">
      <span style="font-size: 0.82rem; color: #475569;">
        <?php if ($total_records > 0): ?>
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> allocated periods
        <?php else: ?>
          Showing <strong>0</strong> allocated periods
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_staff_periods_pagination_url(1, $search, $filter_day, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
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
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_staff_periods_pagination_url($page - 1, $search, $filter_day, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_staff_periods_pagination_url($p, $search, $filter_day, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_staff_periods_pagination_url($page + 1, $search, $filter_day, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Legend / Stats Info -->
    <div style="font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 14px; margin-left: auto;">
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
        Active Timetable
      </span>
      <span style="color: #cbd5e1;">|</span>
      <span><strong><?php echo $distinct_classes; ?></strong> Batches</span>
      <span style="color: #cbd5e1;">•</span>
      <span><strong><?php echo $distinct_subjects; ?></strong> Subjects</span>
      <span style="color: #cbd5e1;">•</span>
      <span><strong><?php echo $distinct_rooms; ?></strong> Venues</span>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
