<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
require_menu_permission('leaves');

$page_title = 'My Leave Applications | KSV Academy HRMS';
$page_heading = 'Faculty Leave Applications';
$page_subheading = 'Personal Leave Entitlements, Requests Ledger & Approval Status';
$active_menu = 'leaves';

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

// Handle Cancel / Withdraw Leave Request (Self Staff, only if Pending)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_leave') {
    $cancel_id = intval($_POST['leave_id'] ?? 0);
    if ($cancel_id > 0) {
        $stmt = $db->prepare("DELETE FROM leave_requests WHERE id = ? AND staff_id = ? AND status = 'Pending'");
        $stmt->execute([$cancel_id, $staff_id]);
        if ($stmt->rowCount() > 0) {
            set_flash('success', "Pending leave application #{$cancel_id} has been withdrawn.");
        }
        header('Location: ' . STAFF_URL . '/leaves');
        exit;
    }
}

// Handle Approve / Reject (if Super Admin is testing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
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
    $ltype = trim($_POST['leave_type'] ?? 'Casual Leave');
    $sdate = trim($_POST['start_date'] ?? date('Y-m-d'));
    $edate = trim($_POST['end_date'] ?? date('Y-m-d'));
    $reason = trim($_POST['reason'] ?? '');

    if (strtotime($edate) < strtotime($sdate)) {
        set_flash('error', 'End date cannot be earlier than start date.');
    } else {
        $stmt = $db->prepare("INSERT INTO leave_requests (staff_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$staff_id, $ltype, $sdate, $edate, $reason]);
        set_flash('success', 'Leave application successfully submitted for approval.');
    }
    header('Location: ' . STAFF_URL . '/leaves');
    exit;
}

// Calculate KPI Metrics for this Staff
$metric_stmt = $db->prepare("
    SELECT status, COUNT(*) as cnt, 
           COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0) as total_days 
    FROM leave_requests 
    WHERE staff_id = ? 
    GROUP BY status
");
$metric_stmt->execute([$staff_id]);
$raw_metrics = $metric_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_applications = 0;
$total_approved = 0;
$total_approved_days = 0;
$total_pending = 0;
$total_rejected = 0;

foreach ($raw_metrics as $m) {
    $total_applications += intval($m['cnt']);
    if ($m['status'] === 'Approved') {
        $total_approved += intval($m['cnt']);
        $total_approved_days += intval($m['total_days']);
    } elseif ($m['status'] === 'Pending') {
        $total_pending += intval($m['cnt']);
    } elseif ($m['status'] === 'Rejected') {
        $total_rejected += intval($m['cnt']);
    }
}

$annual_entitlement = 15;
$leaves_remaining = max(0, $annual_entitlement - $total_approved_days);

// Filter Parameters
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_type = trim($_GET['type'] ?? '');

// Pagination Settings (10, 20, 30, 40, 50 - plus 5 for convenience)
$allowed_limits = [5, 10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Build Filter Conditions
$where = ["staff_id = ?"];
$params = [$staff_id];

if (!empty($search)) {
    $where[] = "(leave_type LIKE ? OR reason LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
}
if (!empty($filter_status)) {
    $where[] = "status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_type)) {
    $where[] = "leave_type = ?";
    $params[] = $filter_type;
}

$where_sql = implode(' AND ', $where);

// Count total matching leave records
$count_query = "SELECT COUNT(*) FROM leave_requests WHERE {$where_sql}";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = intval($count_stmt->fetchColumn() ?: 0);

// Calculate Pagination Bounds
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch paginated leave records
$query = "SELECT * FROM leave_requests WHERE {$where_sql} ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);
$b_idx = 1;
foreach ($params as $p) {
    $stmt->bindValue($b_idx++, $p);
}
$stmt->bindValue($b_idx++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($b_idx++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for generating staff-leaves pagination links
function get_staff_leaves_pagination_url($target_page, $search, $filter_status, $filter_type, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_status)) $params['status'] = $filter_status;
    if (!empty($filter_type)) $params['type'] = $filter_type;
    if ($limit != 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return STAFF_URL . '/leaves' . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Available leave types
$leave_types_list = [
    'Casual Leave',
    'Medical Leave',
    'Academic Duty',
    'Emergency Leave'
];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Staff Leaves Custom Styles (Simple, Sober & Premium) ── */
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

.roaster-toolbar-card .roaster-search-box {
  min-width: 170px;
  max-width: 210px;
}
.roaster-toolbar-card .roaster-select-box select {
  min-width: 130px;
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
  padding: 11px 12px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.staff-id-table tbody td {
  padding: 12px 12px;
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

/* Badge & Tag Elements */
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

.duration-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  color: #0369a1;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  padding: 2px 7px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  white-space: nowrap;
}

.reason-quote-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-left: 3px solid #0284c7;
  padding: 5px 9px;
  border-radius: 6px;
  font-size: 0.75rem;
  color: #334155;
  line-height: 1.35;
  max-width: 260px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.status-badge-leave {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 14px;
  font-size: 0.70rem;
  font-weight: 600;
  white-space: nowrap;
}
.status-badge-leave.pending {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fde68a;
}
.status-badge-leave.approved {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}
.status-badge-leave.rejected {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.pulse-dot-amber {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #f59e0b;
  display: inline-block;
}

.leave-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  font-size: 0.72rem;
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
.leave-action-btn.btn-reject {
  color: #dc2626;
  background: #fef2f2;
  border-color: #fecaca;
}
.leave-action-btn.btn-reject:hover {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #b91c1c;
}
</style>

<!-- 4 Sober Monthly KPI Summary Cards (4-Column Balanced Grid) -->
<div class="periods-stat-grid">

  <!-- Total Leave Applications -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Applications</span>
          <span class="stat-number"><?php echo $total_applications; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-file-signature"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Leave Requests Submitted</span>
      <span style="color: #0284c7; font-weight: 700;">All-Time</span>
    </div>
  </div>

  <!-- Approved Leaves -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Approved Leaves</span>
          <span class="stat-number"><?php echo $total_approved; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-calendar-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $total_applications > 0 ? round(($total_approved / $total_applications) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $total_approved_days; ?></strong> Approved Days Taken</span>
      <span style="color: #059669; font-weight: 700;">Granted</span>
    </div>
  </div>

  <!-- Pending Approvals -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Pending Approval</span>
          <span class="stat-number"><?php echo $total_pending; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-hourglass-half"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $total_applications > 0 ? round(($total_pending / $total_applications) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Awaiting Principal &amp; HR Review</span>
      <span style="color: #d97706; font-weight: 700;">In Review</span>
    </div>
  </div>

  <!-- Leave Entitlement & Balance -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Available Balance</span>
          <span class="stat-number"><?php echo $leaves_remaining; ?> <span style="font-size: 0.85rem; font-weight: 500; color: #64748b;">/ <?php echo $annual_entitlement; ?></span></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-umbrella-beach"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo round(($leaves_remaining / $annual_entitlement) * 100); ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Annual Academic Allowance</span>
      <span style="color: #7c3aed; font-weight: 700;">Remaining</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     FILTER & SEARCH TOOLBAR (Simple & Sober)
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-top: 6px; margin-bottom: 20px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Search & Filter Form -->
    <form method="GET" action="<?php echo STAFF_URL; ?>/leaves" class="roaster-filter-form">
      <?php if ($limit !== 10): ?>
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      <?php endif; ?>

      <!-- Search Input -->
      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search leave type, reason..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_staff_leaves_pagination_url(1, '', $filter_status, $filter_type, $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <!-- Status Filter -->
      <div class="roaster-select-box">
        <select name="status" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="Approved" <?php echo $filter_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
          <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="Rejected" <?php echo $filter_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
      </div>

      <!-- Leave Type Filter -->
      <div class="roaster-select-box">
        <select name="type" onchange="this.form.submit()">
          <option value="">All Leave Types</option>
          <?php foreach ($leave_types_list as $lt): ?>
            <option value="<?php echo htmlspecialchars($lt); ?>" <?php echo $filter_type === $lt ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($lt); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!empty($search) || !empty($filter_status) || !empty($filter_type)): ?>
        <a href="<?php echo STAFF_URL; ?>/leaves<?php echo $limit !== 10 ? '?limit=' . $limit : ''; ?>" class="roaster-btn secondary" style="gap: 5px; font-size: 0.80rem; text-decoration: none; padding: 7px 12px; border-radius: 8px;">
          <i class="fa-solid fa-rotate-left"></i> Reset Filters
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Count Badge & Apply Button -->
    <div class="roaster-toolbar-actions" style="display: flex; align-items: center; gap: 10px;">
      <div class="scholars-count-badge">
        <span class="count-dot" style="background: #0284c7;"></span>
        <span><strong><?php echo $total_records; ?></strong> Requests</span>
      </div>

      <button type="button" class="btn-hrms btn-hrms-primary" onclick="openModal('applyLeaveModal')" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; text-decoration: none;">
        <i class="fa-solid fa-calendar-plus"></i> Apply for Leave
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     LEAVE APPLICATIONS DIRECTORY TABLE (Simple & Sober)
     ══════════════════════════════════════════════════════ -->
<div class="periods-card no-print" id="staffLeavesDirectoryCard">
  <div class="periods-card-header">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 0.96rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-plane-departure" style="color: #0284c7;"></i>
        My Leave Applications &amp; History
        <span style="font-size: 0.72rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Leave requests, medical duty notes and authorization ledger for <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong> (<?php echo htmlspecialchars($staff_profile['employee_code']); ?>).
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
          <th style="width: 140px;">Leave Type</th>
          <th style="width: 175px;">Leave Duration</th>
          <th style="width: 85px;">Days</th>
          <th style="min-width: 190px;">Reason / Justification</th>
          <th style="width: 115px;">Applied Date</th>
          <th style="width: 95px; text-align: center;">Status</th>
          <th style="width: 90px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($leaves)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-umbrella-beach"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Leave Applications Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                <?php echo (!empty($search) || !empty($filter_status) || !empty($filter_type)) ? 'No leave records match your search filters.' : 'You have not submitted any leave applications for this academic session yet.'; ?>
              </p>
              <?php if (!empty($search) || !empty($filter_status) || !empty($filter_type)): ?>
                <a href="<?php echo STAFF_URL; ?>/leaves" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none; padding: 7px 14px; border-radius: 8px;">
                  <i class="fa-solid fa-rotate-left"></i> Reset All Filters
                </a>
              <?php else: ?>
                <button type="button" class="btn-hrms btn-hrms-primary btn-hrms-sm" onclick="openModal('applyLeaveModal')" style="cursor: pointer;">
                  <i class="fa-solid fa-calendar-plus"></i> Submit Leave Request
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($leaves as $idx => $lr): ?>
            <?php
              $start_ts = strtotime($lr['start_date']);
              $end_ts = strtotime($lr['end_date']);
              $num_days = max(1, round(($end_ts - $start_ts) / 86400) + 1);
            ?>
            <tr>
              
              <!-- 1. Index -->
              <td style="text-align: center; font-weight: 600; color: #94a3b8; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.78rem;">
                <?php echo sprintf('%02d', $start_record + $idx); ?>
              </td>

              <!-- 2. Leave Type -->
              <td>
                <div style="font-weight: 700; color: #0f172a; font-size: 0.84rem; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 6px;">
                  <i class="fa-solid fa-tag" style="color: #0284c7; font-size: 0.70rem;"></i>
                  <span><?php echo htmlspecialchars($lr['leave_type']); ?></span>
                </div>
                <div style="font-size: 0.71rem; color: #64748b; margin-top: 2px;">Faculty Entitlement</div>
              </td>

              <!-- 3. Leave Duration & Dates -->
              <td>
                <div style="font-weight: 700; color: #0f172a; font-size: 0.82rem; display: flex; align-items: center; gap: 5px; line-height: 1.35;">
                  <i class="fa-regular fa-calendar-days" style="color: #0284c7; font-size: 0.70rem;"></i>
                  <span><?php echo date('d M Y', $start_ts); ?> &ndash; <?php echo date('d M Y', $end_ts); ?></span>
                </div>
                <div style="font-size: 0.71rem; color: #64748b; margin-top: 2px;">
                  <?php echo date('l', $start_ts); ?> to <?php echo date('l', $end_ts); ?>
                </div>
              </td>

              <!-- 4. Number of Days -->
              <td>
                <span class="duration-pill">
                  <i class="fa-regular fa-clock" style="font-size: 0.62rem;"></i>
                  <span><?php echo $num_days; ?> <?php echo $num_days === 1 ? 'Day' : 'Days'; ?></span>
                </span>
              </td>

              <!-- 5. Reason / Justification -->
              <td>
                <div class="reason-quote-box" title="<?php echo htmlspecialchars($lr['reason']); ?>">
                  <?php echo htmlspecialchars($lr['reason']); ?>
                </div>
              </td>

              <!-- 6. Applied Date -->
              <td>
                <div style="color: #475569; font-size: 0.80rem; font-weight: 500;">
                  <?php echo !empty($lr['created_at']) ? date('d M Y', strtotime($lr['created_at'])) : '—'; ?>
                </div>
                <div style="font-size: 0.70rem; color: #94a3b8; margin-top: 1px;">
                  <?php echo !empty($lr['created_at']) ? date('h:i A', strtotime($lr['created_at'])) : ''; ?>
                </div>
              </td>

              <!-- 7. Status -->
              <td style="text-align: center;">
                <?php if ($lr['status'] === 'Approved'): ?>
                  <span class="status-badge-leave approved">
                    <i class="fa-solid fa-check" style="font-size: 0.58rem;"></i> Approved
                  </span>
                <?php elseif ($lr['status'] === 'Rejected'): ?>
                  <span class="status-badge-leave rejected">
                    <i class="fa-solid fa-xmark" style="font-size: 0.58rem;"></i> Rejected
                  </span>
                <?php else: ?>
                  <span class="status-badge-leave pending">
                    <span class="pulse-dot-amber"></span> In Review
                  </span>
                <?php endif; ?>
              </td>

              <!-- 8. Action (Withdraw if pending, or decisions if admin) -->
              <td style="text-align: right;">
                <?php if ($lr['status'] === 'Pending'): ?>
                  <form method="POST" action="<?php echo STAFF_URL; ?>/leaves" onsubmit="return confirm('Withdraw this pending leave application?');" style="display: inline;">
                    <input type="hidden" name="action" value="cancel_leave">
                    <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                    <button type="submit" class="leave-action-btn btn-reject" title="Withdraw Application">
                      <i class="fa-solid fa-rotate-left"></i> Withdraw
                    </button>
                  </form>
                <?php else: ?>
                  <span style="font-size: 0.72rem; color: #94a3b8; font-weight: 500;">Recorded</span>
                <?php endif; ?>
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
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> leave applications
        <?php else: ?>
          Showing <strong>0</strong> leave applications
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_staff_leaves_pagination_url(1, $search, $filter_status, $filter_type, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Staff Leaves Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_staff_leaves_pagination_url($page - 1, $search, $filter_status, $filter_type, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_staff_leaves_pagination_url($p, $search, $filter_status, $filter_type, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_staff_leaves_pagination_url($page + 1, $search, $filter_status, $filter_type, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Legend / Leave Balances -->
    <div style="font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 14px; margin-left: auto;">
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
        Active Ledger
      </span>
      <span style="color: #cbd5e1;">|</span>
      <span><strong><?php echo $total_approved_days; ?></strong> Days Availed</span>
      <span style="color: #cbd5e1;">•</span>
      <span><strong><?php echo $total_pending; ?></strong> In Review</span>
      <span style="color: #cbd5e1;">•</span>
      <span><strong><?php echo $leaves_remaining; ?></strong> Days Available</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: APPLY FOR LEAVE (Simple & Sober)
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="applyLeaveModal">
  <div class="hrms-modal-card" style="max-width: 500px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 16px 20px;">
      <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-calendar-plus" style="color: #0284c7;"></i>
        Apply for Faculty Leave
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('applyLeaveModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <form method="POST" action="<?php echo STAFF_URL; ?>/leaves">
      <input type="hidden" name="action" value="apply_leave">
      <div class="hrms-modal-body" style="padding: 20px;">
        
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Leave Category &bull; Entitlement</label>
          <select name="leave_type" id="modalLeaveType" class="hrms-input" style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem; background: #fff;" required>
            <option value="Casual Leave">Casual Leave (CL) &bull; Routine Absence</option>
            <option value="Medical Leave">Medical Leave (ML) &bull; Health / Sick Leave</option>
            <option value="Academic Duty">Academic Duty &bull; Examination / University</option>
            <option value="Emergency Leave">Emergency Leave &bull; Urgent Family Matters</option>
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Start Date</label>
            <input type="date" name="start_date" id="modalStartDate" class="hrms-input" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem;" onchange="recalculateLeaveDays()">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">End Date</label>
            <input type="date" name="end_date" id="modalEndDate" class="hrms-input" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem;" onchange="recalculateLeaveDays()">
          </div>
        </div>

        <!-- Calculated Duration Helper -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
          <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Computed Absence Duration:</span>
          <span id="modalDurationBadge" class="duration-pill" style="font-size: 0.76rem;">1 Day Requested</span>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
          <label class="form-label" style="font-size: 0.80rem; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Reason / Official Justification</label>
          <textarea name="reason" class="hrms-input" rows="3" placeholder="State reason for absence, emergency details or academic duty..." required style="width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; font-size: 0.85rem; font-family: inherit; line-height: 1.4;"></textarea>
        </div>

        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
          <button type="button" class="roaster-btn secondary" onclick="closeModal('applyLeaveModal')" style="padding: 9px 16px; border-radius: 8px; font-size: 0.84rem; cursor: pointer;">Cancel</button>
          <button type="submit" class="btn-hrms btn-hrms-primary" style="padding: 9px 20px; border-radius: 8px; font-size: 0.84rem; font-weight: 700; cursor: pointer;">
            <i class="fa-solid fa-paper-plane"></i> Submit Application
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

<script>
function recalculateLeaveDays() {
  const sInput = document.getElementById('modalStartDate');
  const eInput = document.getElementById('modalEndDate');
  const badge = document.getElementById('modalDurationBadge');
  
  if (!sInput || !eInput || !badge) return;
  
  const sVal = sInput.value;
  const eVal = eInput.value;
  
  if (!sVal || !eVal) return;
  
  const s = new Date(sVal);
  const e = new Date(eVal);
  
  if (e < s) {
    badge.textContent = 'Invalid Date Range';
    badge.style.color = '#dc2626';
    badge.style.borderColor = '#fca5a5';
    badge.style.background = '#fef2f2';
    return;
  }
  
  const diffTime = Math.abs(e - s);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
  
  badge.textContent = diffDays + (diffDays === 1 ? ' Day Requested' : ' Days Requested');
  badge.style.color = '#0369a1';
  badge.style.borderColor = '#bae6fd';
  badge.style.background = '#f0f9ff';
}
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>

