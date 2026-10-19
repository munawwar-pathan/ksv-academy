<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
$page_title = 'Leave Management | KSV Academy HRMS';
$page_heading = 'Faculty Leave Applications';
$page_subheading = 'Leave Entitlements, Requests & Approvals';
$active_menu = 'leaves';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Filters: search, status, type
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_type = trim($_GET['type'] ?? '');

// Handle Approve / Reject / Revert (Super Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $leave_id = intval($_POST['leave_id'] ?? 0);
        $status = $_POST['status'] ?? 'Pending';
        if ($leave_id > 0 && in_array($status, ['Approved', 'Rejected', 'Pending'])) {
            $stmt = $db->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $leave_id]);
            set_flash('success', "Leave request #{$leave_id} successfully marked as {$status}.");
            header('Location: ' . ADMIN_URL . '/leaves');
            exit;
        }
    }
}

// Handle Apply Leave (Super Admin on behalf of staff OR Staff for themselves)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_leave') {
    $staff_id = 0;
    if ($current_user['role'] === 'super_admin') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
    } else {
        $staff = $db->query("SELECT id FROM staff_profiles WHERE user_id = {$current_user['id']}")->fetch();
        if ($staff) {
            $staff_id = $staff['id'];
        }
    }

    if ($staff_id > 0) {
        $ltype = trim($_POST['leave_type'] ?? 'Casual Leave');
        $sdate = trim($_POST['start_date'] ?? date('Y-m-d'));
        $edate = trim($_POST['end_date'] ?? date('Y-m-d'));
        $reason = trim($_POST['reason'] ?? '');
        $initial_status = ($current_user['role'] === 'super_admin' && isset($_POST['pre_approve'])) ? 'Approved' : 'Pending';

        $stmt = $db->prepare("INSERT INTO leave_requests (staff_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$staff_id, $ltype, $sdate, $edate, $reason, $initial_status]);
        set_flash('success', 'Leave application successfully recorded.');
        header('Location: ' . ADMIN_URL . '/leaves');
        exit;
    } else {
        set_flash('error', 'Please select a valid faculty member.');
    }
}

// Handle Delete Leave (Super Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin' && isset($_POST['action']) && $_POST['action'] === 'delete_leave') {
    $leave_id = intval($_POST['leave_id'] ?? 0);
    if ($leave_id > 0) {
        $db->prepare("DELETE FROM leave_requests WHERE id = ?")->execute([$leave_id]);
        set_flash('success', "Leave request record #{$leave_id} deleted.");
        header('Location: ' . ADMIN_URL . '/leaves');
        exit;
    }
}

// Query all staff profiles for dropdown
$staff_dropdown = $db->query("
    SELECT sp.id, sp.employee_code, sp.department, sp.designation, u.full_name 
    FROM staff_profiles sp 
    JOIN users u ON sp.user_id = u.id 
    ORDER BY u.full_name ASC
")->fetchAll();

// Build Query with filters
$query = "
    SELECT lr.*, u.full_name, u.username, u.email as user_email, u.phone as user_phone, u.avatar,
           sp.employee_code, sp.department, sp.designation, sp.photo
    FROM leave_requests lr
    JOIN staff_profiles sp ON lr.staff_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE 1=1
";
$params = [];

// If staff role, restrict strictly to their own requests
if ($current_user['role'] === 'staff') {
    $query .= " AND u.id = ?";
    $params[] = $current_user['id'];
}

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR sp.employee_code LIKE ? OR sp.department LIKE ? OR lr.reason LIKE ? OR lr.leave_type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_status)) {
    $query .= " AND lr.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_type)) {
    $query .= " AND lr.leave_type = ?";
    $params[] = $filter_type;
}

$query .= " ORDER BY lr.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$leaves = $stmt->fetchAll();

// Calculate Summary Statistics
$total_count = count($leaves);
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

// Also compute overall global metrics for the KPI grid
$stat_query = "SELECT status, COUNT(*) as cnt FROM leave_requests";
if ($current_user['role'] === 'staff') {
    $stat_query .= " WHERE staff_id IN (SELECT id FROM staff_profiles WHERE user_id = {$current_user['id']})";
}
$stat_query .= " GROUP BY status";
$stats_raw = $db->query($stat_query)->fetchAll(PDO::FETCH_KEY_PAIR);

$metric_total = array_sum($stats_raw);
$metric_pending = $stats_raw['Pending'] ?? 0;
$metric_approved = $stats_raw['Approved'] ?? 0;
$metric_rejected = $stats_raw['Rejected'] ?? 0;

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
    $lower = strtolower($sm['full_name'] ?? '');
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

// Color palettes for avatars
$grad_palette = [
    'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)',
    'linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)',
    'linear-gradient(135deg, #059669 0%, #047857 100%)',
    'linear-gradient(135deg, #d97706 0%, #b45309 100%)',
    'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)',
];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Leave Management Custom Enhancements (Simple & Sober) ── */
.roaster-toolbar-card .roaster-search-box {
  min-width: 180px;
  max-width: 230px;
}
.roaster-toolbar-card .roaster-select-box select {
  min-width: 140px;
}

/* Master Leaves Table (Matching staff-id and staff-periods) */
.leaves-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.leaves-table thead th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.70rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 10px 12px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.leaves-table thead th:first-child,
.leaves-table tbody td:first-child {
  padding-left: 18px;
}
.leaves-table thead th:last-child,
.leaves-table tbody td:last-child {
  padding-right: 18px;
}
.leaves-table tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.80rem;
}
.leaves-table tbody tr {
  transition: background-color 0.15s ease;
}
.leaves-table tbody tr:hover {
  background-color: #f8fafc;
}

.emp-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0284c7;
  padding: 2px 7px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
}

.leave-type-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.76rem;
  font-weight: 700;
  font-family: 'Outfit', sans-serif;
  white-space: nowrap;
}

.duration-badge {
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
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  color: #334155;
  line-height: 1.35;
  max-width: 175px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.status-badge-leave {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 0.74rem;
  font-weight: 700;
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

/* Decision Action Buttons */
.leave-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
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
.leave-action-btn:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}
.leave-action-btn.btn-approve {
  color: #059669;
  background: #ecfdf5;
  border-color: #a7f3d0;
}
.leave-action-btn.btn-approve:hover {
  background: #d1fae5;
  border-color: #6ee7b7;
  color: #047857;
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
.leave-action-btn.btn-delete:hover {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #dc2626;
}
</style>

<!-- ══════════════════════════════════════════════════════
     TOP KPI METRIC CARDS (Standard Stat Grid)
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid no-print" style="margin-bottom: 22px;">
  
  <!-- Total Requests -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Applications</span>
          <span class="stat-number"><?php echo number_format($metric_total); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-plane-departure"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Institutional Records</span>
      <span style="color:#0284c7; font-weight:700;">Overall Log</span>
    </div>
  </div>

  <!-- Pending Approvals -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Pending Approvals</span>
          <span class="stat-number"><?php echo number_format($metric_pending); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-hourglass-half"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $metric_total > 0 ? round(($metric_pending / $metric_total) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Action Required</span>
      <span style="color:#d97706; font-weight:700;">Awaiting Review</span>
    </div>
  </div>

  <!-- Approved Leaves -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Sanctioned Leaves</span>
          <span class="stat-number"><?php echo number_format($metric_approved); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-circle-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $metric_total > 0 ? round(($metric_approved / $metric_total) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Granted Requests</span>
      <span style="color:#059669; font-weight:700;">Approved</span>
    </div>
  </div>

  <!-- Rejected / Declined -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Declined Requests</span>
          <span class="stat-number"><?php echo number_format($metric_rejected); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-circle-xmark"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $metric_total > 0 ? round(($metric_rejected / $metric_total) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Disallowed Leaves</span>
      <span style="color:#7c3aed; font-weight:700;">Archived</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     FILTER & SEARCH ROASTER TOOLBAR
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner">
    
    <!-- Left: Filter Form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/leaves" class="roaster-filter-form">
      
      <!-- Search Input -->
      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search faculty name, code, reason...">
        <?php if (!empty($search)): ?>
          <a href="<?php echo ADMIN_URL; ?>/leaves<?php echo !empty($filter_status) ? '?status=' . urlencode($filter_status) : ''; ?>" class="search-clear-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <!-- Status Filter Dropdown -->
      <div class="roaster-select-box">
        <i class="fa-solid fa-filter"></i>
        <select name="status" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending Approval</option>
          <option value="Approved" <?php echo $filter_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
          <option value="Rejected" <?php echo $filter_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <!-- Leave Type Filter Dropdown -->
      <div class="roaster-select-box">
        <i class="fa-solid fa-tag"></i>
        <select name="type" onchange="this.form.submit()">
          <option value="">All Leave Types</option>
          <option value="Casual Leave" <?php echo $filter_type === 'Casual Leave' ? 'selected' : ''; ?>>Casual Leave (CL)</option>
          <option value="Medical Leave" <?php echo $filter_type === 'Medical Leave' ? 'selected' : ''; ?>>Medical Leave (ML)</option>
          <option value="Academic Duty" <?php echo $filter_type === 'Academic Duty' ? 'selected' : ''; ?>>Academic Duty</option>
          <option value="Emergency Leave" <?php echo $filter_type === 'Emergency Leave' ? 'selected' : ''; ?>>Emergency Leave</option>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <button type="submit" class="roaster-btn secondary">
        <i class="fa-solid fa-sliders"></i> Filter
      </button>

      <?php if (!empty($search) || !empty($filter_status) || !empty($filter_type)): ?>
        <a href="<?php echo ADMIN_URL; ?>/leaves" class="roaster-btn ghost" title="Reset all filters">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Applications Live Count & Apply Leave Button -->
    <div class="roaster-toolbar-actions">
      <div class="scholars-count-badge">
        <span class="count-dot" style="background: #0284c7;"></span>
        <span><strong><?php echo count($leaves); ?></strong> Records</span>
      </div>

      <button type="button" onclick="openApplyLeaveModal();" class="roaster-add-btn" title="Submit New Leave Application">
        <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
        <span>Apply Leave</span>
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     LEAVE APPLICATIONS HISTORY TABLE
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card" style="margin-bottom: 30px;">
  <div class="hrms-card-header" style="border-bottom: 1px solid var(--hrms-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 22px;">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.02rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-plane-departure" style="color: var(--hrms-primary);"></i>
        Faculty Leave Applications &amp; Approvals Register
        <span style="font-size: 0.74rem; font-weight: 700; background: #f1f5f9; color: #475569; padding: 2px 10px; border-radius: 20px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 4px 0 0; font-size: 0.8rem; color: #64748b;">
        Review institutional time-off requests, medical permissions, and academic duty exemptions.
      </p>
    </div>
  </div>

  <div class="table-responsive">
    <table class="leaves-table">
      <thead>
        <tr>
          <th>Applicant Faculty</th>
          <th>Leave Category</th>
          <th>Period &amp; Duration</th>
          <th>Reason &amp; Justification</th>
          <th>Applied Date</th>
          <th>Status</th>
          <?php if ($current_user['role'] === 'super_admin'): ?>
            <th style="text-align: right;">Decision Action</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($leaves)): ?>
          <tr>
            <td colspan="<?php echo $current_user['role'] === 'super_admin' ? '7' : '6'; ?>" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-calendar-xmark"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Leave Applications Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                No leave requests match the current filters.
              </p>
              <a href="<?php echo ADMIN_URL; ?>/leaves" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                <i class="fa-solid fa-rotate-left"></i> Reset All Filters
              </a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($leaves as $lr): ?>
            <?php 
              $st_photo = get_staff_photo_url($lr);
              $has_st_photo = !empty($st_photo);
              $f_letter = strtoupper(substr($lr['full_name'], 0, 1));
              $bg_color = $grad_palette[ord($f_letter) % count($grad_palette)];

              // Calculate days duration
              $d1 = new DateTime($lr['start_date']);
              $d2 = new DateTime($lr['end_date']);
              $diff = $d1->diff($d2)->days + 1;

              // Type class
              $ltype_lower = strtolower($lr['leave_type']);
              $chip_class = 'other';
              $type_icon = 'fa-tag';
              if (strpos($ltype_lower, 'casual') !== false) {
                $chip_class = 'casual';
                $type_icon = 'fa-umbrella-beach';
              } elseif (strpos($ltype_lower, 'medical') !== false || strpos($ltype_lower, 'sick') !== false) {
                $chip_class = 'medical';
                $type_icon = 'fa-notes-medical';
              } elseif (strpos($ltype_lower, 'academic') !== false || strpos($ltype_lower, 'duty') !== false) {
                $chip_class = 'academic';
                $type_icon = 'fa-graduation-cap';
              } elseif (strpos($ltype_lower, 'emergency') !== false) {
                $chip_class = 'emergency';
                $type_icon = 'fa-triangle-exclamation';
              }
            ?>
            <tr>
              
              <!-- 1. Applicant Profile -->
              <td>
                <div class="user-cell" style="display: flex; align-items: center; gap: 10px;">
                  <?php if ($has_st_photo): ?>
                    <img src="<?php echo htmlspecialchars($st_photo); ?>" alt="<?php echo htmlspecialchars($lr['full_name']); ?>" class="staff-avatar-img" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="staff-avatar-init" style="width: 38px; height: 38px; border-radius: 50%; background: <?php echo $bg_color; ?>; color: #fff; font-weight: 800; font-size: 14px; display: none; align-items: center; justify-content: center;">
                      <?php echo $f_letter; ?>
                    </div>
                  <?php else: ?>
                    <div class="staff-avatar-init" style="width: 38px; height: 38px; border-radius: 50%; background: <?php echo $bg_color; ?>; color: #fff; font-weight: 800; font-size: 14px; display: flex; align-items: center; justify-content: center;">
                      <?php echo $f_letter; ?>
                    </div>
                  <?php endif; ?>

                  <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.83rem; line-height: 1.25;">
                      <?php echo htmlspecialchars($lr['full_name']); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px; margin-top: 2px;">
                      <span class="emp-code-pill"><?php echo htmlspecialchars($lr['employee_code']); ?></span>
                      <span style="font-size: 0.70rem; color: #64748b;"><?php echo htmlspecialchars($lr['department']); ?></span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 2. Leave Category -->
              <td>
                <div>
                  <span class="leave-type-chip <?php echo $chip_class; ?>">
                    <i class="fa-solid <?php echo $type_icon; ?>"></i>
                    <span><?php echo htmlspecialchars($lr['leave_type']); ?></span>
                  </span>
                  <div style="font-size: 0.70rem; color: #64748b; margin-top: 2px;">
                    <?php echo htmlspecialchars($lr['designation']); ?>
                  </div>
                </div>
              </td>

              <!-- 3. Period & Duration -->
              <td>
                <div>
                  <div style="font-size: 0.78rem; font-weight: 700; color: #0f172a; white-space: nowrap;">
                    <?php 
                      $s_ts = strtotime($lr['start_date']);
                      $e_ts = strtotime($lr['end_date']);
                      if (date('Y', $s_ts) === date('Y', $e_ts)) {
                        echo date('d M', $s_ts) . ' &ndash; ' . date('d M Y', $e_ts);
                      } else {
                        echo date('d M Y', $s_ts) . ' &ndash; ' . date('d M Y', $e_ts);
                      }
                    ?>
                  </div>
                  <div style="margin-top: 3px;">
                    <span class="duration-badge">
                      <i class="fa-solid fa-clock"></i>
                      <span><?php echo $diff; ?> <?php echo $diff === 1 ? 'Day' : 'Days'; ?></span>
                    </span>
                  </div>
                </div>
              </td>

              <!-- 4. Reason & Justification -->
              <td>
                <div class="reason-quote-box" title="<?php echo htmlspecialchars($lr['reason']); ?>">
                  <?php echo htmlspecialchars($lr['reason']); ?>
                </div>
              </td>

              <!-- 5. Applied Date -->
              <td>
                <div style="font-size: 0.76rem; font-weight: 600; color: #64748b; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
                  <i class="fa-regular fa-calendar" style="color: #94a3b8; font-size: 0.72rem;"></i>
                  <span><?php echo format_date($lr['created_at']); ?></span>
                </div>
              </td>

              <!-- 6. Status -->
              <td>
                <?php if ($lr['status'] === 'Approved'): ?>
                  <span class="status-badge-leave approved">
                    <i class="fa-solid fa-circle-check"></i> Approved
                  </span>
                <?php elseif ($lr['status'] === 'Rejected'): ?>
                  <span class="status-badge-leave rejected">
                    <i class="fa-solid fa-circle-xmark"></i> Rejected
                  </span>
                <?php else: ?>
                  <span class="status-badge-leave pending">
                    <span class="pulse-dot-amber"></span> Pending
                  </span>
                <?php endif; ?>
              </td>

              <!-- 7. Decision Action (Admin Only) -->
              <?php if ($current_user['role'] === 'super_admin'): ?>
                <td style="text-align: right; white-space: nowrap;">
                  <div style="display: inline-flex; gap: 5px; justify-content: flex-end; align-items: center;">
                    
                    <?php if ($lr['status'] === 'Pending'): ?>
                      <!-- Approve Form -->
                      <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                        <input type="hidden" name="status" value="Approved">
                        <button type="submit" class="leave-action-btn btn-approve" title="Approve Leave Request">
                          <i class="fa-solid fa-check"></i>
                          <span>Approve</span>
                        </button>
                      </form>

                      <!-- Reject Form -->
                      <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                        <input type="hidden" name="status" value="Rejected">
                        <button type="submit" class="leave-action-btn btn-reject" title="Reject Leave Request">
                          <i class="fa-solid fa-xmark"></i>
                          <span>Reject</span>
                        </button>
                      </form>
                    <?php else: ?>
                      <!-- Status Pill & Reset Option -->
                      <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" style="display: inline; margin: 0;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                        <input type="hidden" name="status" value="Pending">
                        <button type="submit" class="leave-action-btn" title="Reopen / Reset to Pending Status">
                          <i class="fa-solid fa-rotate-left"></i>
                          <span>Reset</span>
                        </button>
                      </form>
                    <?php endif; ?>

                    <!-- Delete Leave Request Form -->
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" style="display: inline; margin: 0;" onsubmit="return confirm('Permanently remove this leave record?');">
                      <input type="hidden" name="action" value="delete_leave">
                      <input type="hidden" name="leave_id" value="<?php echo $lr['id']; ?>">
                      <button type="submit" class="leave-action-btn btn-delete" style="padding: 5px 8px;" title="Delete Record">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>

                  </div>
                </td>
              <?php endif; ?>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: Apply for Faculty Leave
     ══════════════════════════════════════════════════════ -->
<div id="applyLeaveModal" class="hrms-modal-overlay" onclick="if (event.target === this) closeApplyLeaveModal();" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px); overflow-y: auto; padding: 20px;">
  <div class="hrms-modal-content" style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 580px; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); max-height: 92vh; overflow-y: auto;">
    
    <!-- Modal Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 14px;">
      <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-calendar-plus" style="color: #0092df;"></i>
        <span>Faculty Leave Application</span>
      </h3>
      <button type="button" onclick="closeApplyLeaveModal();" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">&times;</button>
    </div>

    <!-- Modal Form -->
    <form method="POST" action="<?php echo ADMIN_URL; ?>/leaves" id="applyLeaveForm">
      <input type="hidden" name="action" value="apply_leave">

      <!-- Faculty Member Selection (If Super Admin) -->
      <?php if ($current_user['role'] === 'super_admin'): ?>
        <div style="margin-bottom: 16px;">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Select Faculty Member *</label>
          <select name="staff_id" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600;">
            <option value="">-- Choose Faculty Member --</option>
            <?php foreach ($staff_dropdown as $st_opt): ?>
              <option value="<?php echo $st_opt['id']; ?>">
                <?php echo htmlspecialchars($st_opt['full_name']); ?> (<?php echo htmlspecialchars($st_opt['employee_code']); ?> &bull; <?php echo htmlspecialchars($st_opt['department']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <!-- Leave Category -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Leave Category / Type *</label>
        <select name="leave_type" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600;">
          <option value="Casual Leave">Casual Leave (CL)</option>
          <option value="Medical Leave">Medical Leave (ML / Sick Leave)</option>
          <option value="Academic Duty">Academic / Examination Duty</option>
          <option value="Emergency Leave">Emergency / Urgent Leave</option>
          <option value="Maternity / Paternity Leave">Maternity / Paternity Leave</option>
          <option value="Unpaid Leave">Leave Without Pay (LWP)</option>
        </select>
      </div>

      <!-- Date Range (Start & End) -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Start Date *</label>
          <input type="date" id="leaveStartDate" name="start_date" value="<?php echo date('Y-m-d'); ?>" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;" onchange="recalculateLeaveDays();">
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">End Date *</label>
          <input type="date" id="leaveEndDate" name="end_date" value="<?php echo date('Y-m-d'); ?>" required class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;" onchange="recalculateLeaveDays();">
        </div>
      </div>

      <!-- Live Duration Display Banner -->
      <div style="margin-bottom: 16px;">
        <div style="background: #f0f9ff; border: 1.5px dashed #0092df; border-radius: 10px; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-weight: 700; font-size: 0.82rem; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">Computed Leave Duration:</span>
          <span id="leaveDaysDisplay" style="font-size: 1.15rem; font-weight: 800; color: #0092df; font-family: 'Outfit', sans-serif;">1 Day</span>
        </div>
      </div>

      <!-- Reason Textarea -->
      <div style="margin-bottom: 20px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Reason / Academic Contingency *</label>
        <textarea name="reason" rows="3" required placeholder="Describe the reason for leave and hand-over arrangements if any..." class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
      </div>

      <?php if ($current_user['role'] === 'super_admin'): ?>
        <!-- Pre-Approve Checkbox (Admin Only) -->
        <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
          <input type="checkbox" name="pre_approve" id="preApproveCheck" value="1" style="width: 16px; height: 16px; accent-color: #0092df;">
          <label for="preApproveCheck" style="font-size: 13px; font-weight: 600; color: #334155; cursor: pointer;">
            Directly mark this leave application as <strong>Approved</strong>
          </label>
        </div>
      <?php endif; ?>

      <!-- Footer Buttons -->
      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
        <button type="button" onclick="closeApplyLeaveModal();" class="roaster-btn secondary" style="padding: 8px 18px; font-size: 0.85rem; font-weight: 600;">Cancel</button>
        <button type="submit" class="roaster-add-btn" style="padding: 8px 20px; font-size: 0.85rem; font-weight: 700; border-radius: 8px;">
          <i class="fa-solid fa-paper-plane"></i>
          <span>Submit Application</span>
        </button>
      </div>

    </form>
  </div>
</div>

<script>
function openApplyLeaveModal() {
  const modal = document.getElementById('applyLeaveModal');
  if (modal) {
    modal.style.display = 'flex';
    recalculateLeaveDays();
  }
}

function closeApplyLeaveModal() {
  const modal = document.getElementById('applyLeaveModal');
  if (modal) {
    modal.style.display = 'none';
  }
}

function recalculateLeaveDays() {
  const sInput = document.getElementById('leaveStartDate');
  const eInput = document.getElementById('leaveEndDate');
  const display = document.getElementById('leaveDaysDisplay');
  if (!sInput || !eInput || !display) return;

  const s = new Date(sInput.value);
  const e = new Date(eInput.value);
  if (isNaN(s) || isNaN(e)) return;

  if (e < s) {
    display.textContent = 'Invalid (End before Start)';
    display.style.color = '#dc2626';
    return;
  }

  const diffTime = Math.abs(e - s);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
  display.textContent = diffDays + (diffDays === 1 ? ' Day' : ' Days');
  display.style.color = '#0092df';
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeApplyLeaveModal();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
