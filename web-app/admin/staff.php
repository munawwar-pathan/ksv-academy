<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
$page_title = 'Faculty & Staff Directory | KSV Academy HRMS';
$page_heading = 'Faculty & Staff Management';
$page_subheading = 'Academic Mentors, Lecturers, Department Heads & Administrative Staff Directory';
$active_menu = 'staff';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];

// Filter & Search
$search = trim($_GET['search'] ?? '');
$filter_dept = trim($_GET['department'] ?? '');

// Pagination & Limits Setup (10, 20, 30, 40, 50)
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Handle Add Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? 'staff');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? 'Science');
        $designation = trim($_POST['designation'] ?? 'Lecturer');
        $qualification = trim($_POST['qualification'] ?? 'M.Sc, B.Ed');
        $salary = floatval($_POST['salary'] ?? 55000);
        $blood_group = trim($_POST['blood_group'] ?? 'B+');
        $address = trim($_POST['address'] ?? '');

        if (!empty($full_name) && !empty($username)) {
            try {
                // Check if username already exists
                $chk = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    set_flash('error', "Username '{$username}' is already taken. Please choose another.");
                    header('Location: ' . ADMIN_URL . '/staff');
                    exit;
                }

                // 1. Create User
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, role, email, phone, full_name, status) VALUES (?, ?, 'staff', ?, ?, ?, 'active')");
                $stmt->execute([$username, $hash, $email, $phone, $full_name]);
                $user_id = $db->lastInsertId();

                // 2. Generate Employee Code
                $next_emp = $db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM staff_profiles")->fetchColumn();
                $emp_code = 'KSV-FAC-' . str_pad($next_emp, 3, '0', STR_PAD_LEFT);
                $qr_code = 'STAFF-' . $emp_code;

                // 3. Create Staff Profile
                $stmt_prof = $db->prepare("
                    INSERT INTO staff_profiles (
                        session_id, user_id, employee_code, department, designation, qualification,
                        joining_date, monthly_salary, phone, email, address, blood_group, qr_code, today_status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        CURDATE(), ?, ?, ?, ?, ?, ?, 'Present'
                    )
                ");
                $stmt_prof->execute([
                    $session_id, $user_id, $emp_code, $department, $designation, $qualification,
                    $salary, $phone, $email, $address, $blood_group, $qr_code
                ]);

                set_flash('success', "Faculty member {$full_name} ({$emp_code}) added successfully with username '{$username}'!");
                header('Location: ' . ADMIN_URL . '/staff');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Error adding staff: ' . $e->getMessage());
            }
        }
    }

    // Update Staff Profile
    if ($action === 'update_staff') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? 'Science');
        $designation = trim($_POST['designation'] ?? 'Lecturer');
        $qualification = trim($_POST['qualification'] ?? 'M.Sc, B.Ed');
        $salary = floatval($_POST['salary'] ?? 55000);
        $blood_group = trim($_POST['blood_group'] ?? 'B+');
        $address = trim($_POST['address'] ?? '');

        if ($staff_id > 0 && !empty($full_name)) {
            try {
                $user_id = $db->query("SELECT user_id FROM staff_profiles WHERE id = {$staff_id}")->fetchColumn();
                if ($user_id) {
                    $stmt_u = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt_u->execute([$full_name, $email, $phone, $user_id]);

                    if (!empty($_POST['password'])) {
                        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
                    }
                }

                $stmt_p = $db->prepare("
                    UPDATE staff_profiles 
                    SET department = ?, designation = ?, qualification = ?, monthly_salary = ?, phone = ?, email = ?, address = ?, blood_group = ?
                    WHERE id = ?
                ");
                $stmt_p->execute([$department, $designation, $qualification, $salary, $phone, $email, $address, $blood_group, $staff_id]);

                set_flash('success', "Faculty profile for {$full_name} updated successfully!");
                header('Location: ' . ADMIN_URL . '/staff');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Error updating staff: ' . $e->getMessage());
            }
        }
    }

    // Delete Staff
    if ($action === 'delete_staff') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        if ($staff_id > 0) {
            $user_id = $db->query("SELECT user_id FROM staff_profiles WHERE id = {$staff_id}")->fetchColumn();
            $db->prepare("DELETE FROM staff_profiles WHERE id = ?")->execute([$staff_id]);
            if ($user_id) {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            }
            set_flash('success', "Faculty member record removed successfully.");
            header('Location: ' . ADMIN_URL . '/staff');
            exit;
        }
    }
}

// Active session academic statistics for stat cards:
$stats_stmt = $db->prepare("
    SELECT COUNT(*) as total_staff, 
           COUNT(DISTINCT department) as total_depts, 
           COALESCE(SUM(monthly_salary), 0) as total_payroll, 
           COALESCE(AVG(monthly_salary), 0) as avg_salary 
    FROM staff_profiles 
    WHERE session_id = ?
");
$stats_stmt->execute([$session_id]);
$stats = $stats_stmt->fetch();
$total_staff_count = intval($stats['total_staff'] ?? 0);
$total_depts_count = intval($stats['total_depts'] ?? 0);
$total_payroll_val = floatval($stats['total_payroll'] ?? 0);
$avg_salary_val = floatval($stats['avg_salary'] ?? 0);

// Build WHERE filter conditions
$where_clauses = ["sp.session_id = ?"];
$where_params = [$session_id];

if (!empty($search)) {
    $where_clauses[] = "(u.full_name LIKE ? OR sp.employee_code LIKE ? OR sp.department LIKE ? OR sp.designation LIKE ? OR u.phone LIKE ? OR sp.phone LIKE ?)";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
}

if (!empty($filter_dept)) {
    $where_clauses[] = "sp.department = ?";
    $where_params[] = $filter_dept;
}

$where_sql = implode(' AND ', $where_clauses);

// Count total matching staff records
$count_stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM staff_profiles sp 
    JOIN users u ON sp.user_id = u.id 
    WHERE {$where_sql}
");
$count_stmt->execute($where_params);
$total_records = intval($count_stmt->fetchColumn());

// Calculate total pages & offset
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Query staff for current table page
$query = "
    SELECT sp.*, u.username, u.full_name, u.email as user_email, u.phone as user_phone, u.status as user_status, u.avatar
    FROM staff_profiles sp
    JOIN users u ON sp.user_id = u.id
    WHERE {$where_sql}
    ORDER BY sp.id ASC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($query);
$bind_idx = 1;
foreach ($where_params as $val) {
    $stmt->bindValue($bind_idx++, $val);
}
$stmt->bindValue($bind_idx++, $limit, PDO::PARAM_INT);
$stmt->bindValue($bind_idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$staff_members = $stmt->fetchAll();

// Pagination indicators
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Helper for generating staff pagination links
function get_staff_pagination_url($target_page, $search, $filter_dept, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_dept)) $params['department'] = $filter_dept;
    if ($limit != 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return ADMIN_URL . '/staff' . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Dynamic Department list combining existing and standard departments
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
    'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)',
    'linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)',
    'linear-gradient(135deg, #059669 0%, #047857 100%)',
    'linear-gradient(135deg, #d97706 0%, #b45309 100%)',
    'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)',
];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Faculty Directory Styling ── */
.staff-avatar-img {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #e0f2fe;
}
.staff-avatar-init {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  font-weight: 700;
  font-family: 'Outfit', sans-serif;
  flex-shrink: 0;
}

.emp-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 3px 8px;
  border-radius: 6px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.78rem;
  font-weight: 700;
  white-space: nowrap;
}

.dept-pill-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.76rem;
  font-weight: 600;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  white-space: nowrap;
}
.dept-pill-badge.science {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #1d4ed8;
}
.dept-pill-badge.math {
  background: #faf5ff;
  border-color: #e9d5ff;
  color: #7e22ce;
}
.dept-pill-badge.bio {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #047857;
}
.dept-pill-badge.director {
  background: #fffbeb;
  border-color: #fde68a;
  color: #b45309;
}

.qual-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.76rem;
  font-weight: 600;
  max-width: 170px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.salary-badge {
  font-weight: 700;
  font-size: 0.92rem;
  color: #0f172a;
}

.status-indicator-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 20px;
  font-size: 0.74rem;
  font-weight: 600;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #047857;
  white-space: nowrap;
}
.status-indicator-pill .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
}
</style>

<!-- ══════════════════════════════════════════════════════
     FACULTY & STAFF KPI OVERVIEW CARDS (Matches students.php)
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid" style="margin-bottom: 22px;">
  
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Active Faculty &amp; Staff</span>
          <span class="stat-number"><?php echo $total_staff_count; ?></span>
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
      <span style="color:#059669; font-weight:700;"><i class="fa-solid fa-check"></i> Active</span>
    </div>
  </div>

  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Academic Departments</span>
          <span class="stat-number"><?php echo $total_depts_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-layer-group"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Teaching &amp; Admin Wings</span>
      <span style="color:#7c3aed; font-weight:700;">Active Wings</span>
    </div>
  </div>

  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Monthly Payroll</span>
          <span class="stat-number"><?php echo format_inr($total_payroll_val); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Monthly Salary Commitment</span>
      <span style="color:#059669; font-weight:700;">On Schedule</span>
    </div>
  </div>

  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Average Salary</span>
          <span class="stat-number"><?php echo format_inr($avg_salary_val); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-wallet"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Per Faculty Member</span>
      <span style="color:#d97706; font-weight:700;">Standard Scale</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     FILTER TOOLBAR (Exact Match to students.php)
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print">
  <div class="roaster-toolbar-inner">
    
    <!-- Filter form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/staff" class="roaster-filter-form">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">

      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, code, dept, phone..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_staff_pagination_url(1, '', $filter_dept, $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <div class="roaster-select-box">
        <i class="fa-solid fa-filter"></i>
        <select name="department" onchange="this.form.submit()">
          <option value="">All Academic Departments</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?php echo $dept; ?>" <?php echo $filter_dept === $dept ? 'selected' : ''; ?>>
              <?php echo $dept; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <button type="submit" class="roaster-btn secondary">
        <i class="fa-solid fa-sliders"></i> Filter
      </button>

      <?php if (!empty($search) || !empty($filter_dept)): ?>
        <a href="<?php echo ADMIN_URL; ?>/staff<?php echo $limit !== 10 ? '?limit=' . $limit : ''; ?>" class="roaster-btn ghost" title="Reset all filters">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Faculty Live Count & Add Faculty Button -->
    <div class="roaster-toolbar-actions">
      <div class="scholars-count-badge">
        <span class="count-dot"></span>
        <span><strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'Faculty' : 'Faculty'; ?></span>
      </div>

      <?php if ($current_user['role'] === 'super_admin'): ?>
        <button type="button" class="roaster-add-btn" onclick="openAddStaffModal()">
          <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
          <span>Add Faculty</span>
        </button>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     FACULTY & STAFF DIRECTORY TABLE
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card" style="margin-bottom: 30px;">
  <div class="hrms-card-header" style="border-bottom: 1px solid var(--hrms-border);">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-chalkboard-user" style="color: var(--hrms-primary);"></i>
        Faculty &amp; Staff Directory
        <span style="font-size: 0.78rem; font-weight: 700; background: #f1f5f9; color: #475569; padding: 2px 10px; border-radius: 20px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 4px 0 0; font-size: 0.8rem; color: #64748b;">
        Academic lecturers, department chairs, and administrative leadership registered in the academy.
      </p>
    </div>
  </div>

  <div class="table-responsive">
    <table class="hrms-table">
      <thead>
        <tr>
          <th style="min-width: 220px;">Faculty Profile</th>
          <th style="min-width: 140px;">Employee ID</th>
          <th style="min-width: 170px;">Department &amp; Designation</th>
          <th style="min-width: 150px;">Qualification &amp; Blood</th>
          <th style="min-width: 140px;">Monthly Salary</th>
          <th style="min-width: 170px;">Contact Information</th>
          <th style="min-width: 100px;">Status</th>
          <th style="min-width: 120px; text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($staff_members)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 56px 24px; color: #64748b;">
              <div style="width: 58px; height: 58px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; font-size: 1.5rem; color: #94a3b8;">
                <i class="fa-solid fa-user-slash"></i>
              </div>
              <h4 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Faculty Records Found</h4>
              <p style="font-size: 0.84rem; color: #64748b; margin: 0 0 16px;">
                No faculty members matched your query for Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
              </p>
              <?php if (!empty($search) || !empty($filter_dept)): ?>
                <a href="<?php echo ADMIN_URL; ?>/staff" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                  <i class="fa-solid fa-rotate-left"></i> Reset All Filters
                </a>
              <?php else: ?>
                <?php if ($current_user['role'] === 'super_admin'): ?>
                  <button type="button" class="roaster-add-btn" onclick="openAddStaffModal()" style="display: inline-flex;">
                    <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
                    <span>Add First Faculty Member</span>
                  </button>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($staff_members as $sm): ?>
            <?php 
              $photo_url = get_staff_photo_url($sm);
              $first_letter = strtoupper(substr($sm['full_name'], 0, 1));
              $avatar_bg = $grad_palette[ord($first_letter) % count($grad_palette)];

              // Department styling badge class
              $dept_lower = strtolower($sm['department']);
              $dept_class = '';
              if (strpos($dept_lower, 'direction') !== false || strpos($dept_lower, 'founder') !== false) {
                $dept_class = 'director';
              } elseif (strpos($dept_lower, 'math') !== false) {
                $dept_class = 'math';
              } elseif (strpos($dept_lower, 'bio') !== false) {
                $dept_class = 'bio';
              } elseif (strpos($dept_lower, 'science') !== false || strpos($dept_lower, 'chem') !== false || strpos($dept_lower, 'phys') !== false) {
                $dept_class = 'science';
              }

              $phone_val = $sm['phone'] ?: $sm['user_phone'];
              $email_val = $sm['email'] ?: $sm['user_email'];
            ?>
            <tr>
              
              <!-- 1. Faculty Profile -->
              <td>
                <div class="user-cell" style="gap: 12px; align-items: center;">
                  <?php if (!empty($photo_url)): ?>
                    <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo htmlspecialchars($sm['full_name']); ?>" class="staff-avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="staff-avatar-init" style="background: <?php echo $avatar_bg; ?>; display: none;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php else: ?>
                    <div class="staff-avatar-init" style="background: <?php echo $avatar_bg; ?>;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php endif; ?>
                  
                  <div class="cell-meta">
                    <span class="cell-name" style="font-size: 0.95rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
                      <?php echo htmlspecialchars($sm['full_name']); ?>
                    </span>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.73rem; color: #64748b; margin-top: 2px;">
                      <span style="font-family: monospace; color: #0284c7; font-weight: 700;">@<?php echo htmlspecialchars($sm['username']); ?></span>
                      <span>&bull;</span>
                      <span><i class="fa-solid fa-calendar-day" style="color: #94a3b8; font-size: 0.68rem;"></i> <?php echo !empty($sm['joining_date']) ? date('M Y', strtotime($sm['joining_date'])) : 'Active'; ?></span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 2. Employee ID & QR -->
              <td>
                <span class="emp-code-pill">
                  <i class="fa-solid fa-id-badge" style="font-size: 0.72rem;"></i>
                  <?php echo htmlspecialchars($sm['employee_code']); ?>
                </span>
                <div style="font-size: 0.72rem; color: #64748b; margin-top: 3px; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                  <i class="fa-solid fa-qrcode" style="color: #0284c7; font-size: 0.68rem;"></i>
                  <span>QR Pass Verified</span>
                </div>
              </td>

              <!-- 3. Department & Designation -->
              <td>
                <span class="dept-pill-badge <?php echo $dept_class; ?>">
                  <i class="fa-solid fa-graduation-cap" style="font-size: 0.68rem;"></i>
                  <?php echo htmlspecialchars($sm['department']); ?>
                </span>
                <div style="font-size: 0.76rem; font-weight: 700; color: #475569; margin-top: 4px;">
                  <?php echo htmlspecialchars($sm['designation']); ?>
                </div>
              </td>

              <!-- 4. Qualification & Blood -->
              <td>
                <div class="qual-chip" title="<?php echo htmlspecialchars($sm['qualification']); ?>">
                  <i class="fa-solid fa-award" style="color: #0092df; font-size: 0.68rem;"></i>
                  <span><?php echo htmlspecialchars($sm['qualification']); ?></span>
                </div>
                <?php if (!empty($sm['blood_group'])): ?>
                  <div style="font-size: 0.72rem; color: #64748b; margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-droplet" style="color: #ef4444; font-size: 0.65rem;"></i>
                    <span>Blood: <strong><?php echo htmlspecialchars($sm['blood_group']); ?></strong></span>
                  </div>
                <?php endif; ?>
              </td>

              <!-- 5. Monthly Salary -->
              <td>
                <div class="salary-badge">
                  <?php echo format_inr($sm['monthly_salary']); ?>
                </div>
                <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 600;">
                  Per Month
                </div>
              </td>

              <!-- 6. Contact Information -->
              <td>
                <?php if (!empty($phone_val)): ?>
                  <div>
                    <a href="tel:<?php echo htmlspecialchars($phone_val); ?>" class="guardian-phone-chip" style="font-size: 0.76rem;">
                      <i class="fa-solid fa-phone" style="color: var(--hrms-primary); font-size: 0.68rem;"></i>
                      <?php echo htmlspecialchars($phone_val); ?>
                    </a>
                  </div>
                <?php endif; ?>
                <?php if (!empty($email_val)): ?>
                  <div style="margin-top: 3px;">
                    <a href="mailto:<?php echo htmlspecialchars($email_val); ?>" style="font-size: 0.74rem; color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                      <i class="fa-solid fa-envelope" style="color: #94a3b8; font-size: 0.68rem;"></i>
                      <span><?php echo htmlspecialchars($email_val); ?></span>
                    </a>
                  </div>
                <?php endif; ?>
              </td>

              <!-- 7. Status -->
              <td>
                <span class="status-indicator-pill">
                  <span class="dot"></span>
                  <span>Active</span>
                </span>
              </td>

              <!-- 8. Actions (STRICTLY ONLY: Eye, Pen, Trash like students.php) -->
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                  
                  <!-- Eye Icon: View Profile -->
                  <button type="button" class="action-icon-btn view" title="View Faculty Profile" onclick='viewStaffDetails(<?php echo htmlspecialchars(json_encode($sm), ENT_QUOTES, "UTF-8"); ?>)'>
                    <i class="fa-solid fa-eye"></i>
                  </button>

                  <!-- Pen Icon: Edit Profile -->
                  <button type="button" class="action-icon-btn edit" title="Edit Faculty Profile" onclick='openEditStaffModal(<?php echo htmlspecialchars(json_encode($sm), ENT_QUOTES, "UTF-8"); ?>)'>
                    <i class="fa-solid fa-pen"></i>
                  </button>

                  <!-- Trash Icon: Delete Profile -->
                  <?php if ($current_user['role'] === 'super_admin'): ?>
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/staff" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars(addslashes($sm['full_name'])); ?> and their user credentials?');">
                      <input type="hidden" name="action" value="delete_staff">
                      <input type="hidden" name="staff_id" value="<?php echo $sm['id']; ?>">
                      <button type="submit" class="action-icon-btn delete" title="Delete Faculty Record">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  <?php endif; ?>

                </div>
              </td>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Table Footer Pagination & Per-Page Controls (Matches students.php) -->
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
            <option value="<?php echo htmlspecialchars(get_staff_pagination_url(1, $search, $filter_dept, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Faculty &amp; Staff Directory Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_staff_pagination_url($page - 1, $search, $filter_dept, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_staff_pagination_url($p, $search, $filter_dept, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_staff_pagination_url($page + 1, $search, $filter_dept, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Status Legend -->
    <div style="display: flex; align-items: center; gap: 16px; font-size: 0.78rem; color: #64748b; margin-left: auto;">
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
        Active Verified
      </span>
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <i class="fa-solid fa-shield-halved" style="color: #0284c7; font-size: 0.75rem;"></i>
        Biometric Pass
      </span>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL 1: Add Faculty / Staff Member Form
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="addStaffModal">
  <div class="hrms-modal-card" style="max-width: 680px;">
    
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(0, 146, 223, 0.1); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
          <i class="fa-solid fa-user-plus"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Register New Faculty Member
          </h3>
          <p style="margin: 2px 0 0; font-size: 0.75rem; color: #64748b;">Add lecturer, department mentor or administrative staff</p>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('addStaffModal')" aria-label="Close modal">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/staff">
      <input type="hidden" name="action" value="add_staff">

      <div class="hrms-modal-body" style="padding: 24px; max-height: calc(85vh - 140px); overflow-y: auto;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Full Name *</label>
            <input type="text" name="full_name" class="hrms-input" required placeholder="e.g. Prof. Rameshwar Mishra" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Portal Username *</label>
            <input type="text" name="username" class="hrms-input" required placeholder="e.g. rameshwar" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Initial Portal Password *</label>
            <input type="password" name="password" class="hrms-input" required value="staff" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Department *</label>
            <select name="department" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
              <?php foreach ($departments as $d): ?>
                <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Designation / Title</label>
            <input type="text" name="designation" value="Senior Lecturer" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Academic Qualification</label>
            <input type="text" name="qualification" value="M.Sc (Chemistry), B.Ed" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Phone Number *</label>
            <input type="text" name="phone" placeholder="+91 98765 43210" class="hrms-input" required style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Email Address</label>
            <input type="email" name="email" placeholder="faculty@ksvacademy.com" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Monthly Fixed Salary (₹) *</label>
            <input type="number" step="0.01" name="salary" value="55000" class="hrms-input" required style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 800; color: #059669;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Blood Group</label>
            <select name="blood_group" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
              <option value="B+">B+</option>
              <option value="O+">O+</option>
              <option value="A+">A+</option>
              <option value="AB+">AB+</option>
              <option value="B-">B-</option>
              <option value="O-">O-</option>
              <option value="A-">A-</option>
              <option value="AB-">AB-</option>
            </select>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Residential Address</label>
          <textarea name="address" rows="2" class="hrms-input" placeholder="e.g. Sector 19, Ulwe, Navi Mumbai..." style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
        <button type="button" onclick="closeModal('addStaffModal');" class="btn-hrms btn-hrms-outline">Cancel</button>
        <button type="submit" class="action-primary-btn" style="background: #0092df; color: #ffffff; border: none; padding: 9px 24px; border-radius: 8px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.25);">Register Faculty Member</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL 2: View Faculty / Staff Profile Details
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="viewStaffModal">
  <div class="hrms-modal-card" style="max-width: 680px;">
    
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(0, 146, 223, 0.1); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
          <i class="fa-solid fa-id-card"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Faculty Profile Details
          </h3>
          <p style="margin: 2px 0 0; font-size: 0.75rem; color: #64748b;">Comprehensive Academic &amp; Personal Record</p>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('viewStaffModal')" aria-label="Close modal">&times;</button>
    </div>

    <div class="hrms-modal-body" style="padding: 24px; max-height: calc(85vh - 140px); overflow-y: auto;">
      
      <!-- Profile Header Banner -->
      <div style="display: flex; align-items: center; gap: 18px; padding: 18px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd; border-radius: 14px; margin-bottom: 20px;">
        <div id="view_sm_avatar" style="width: 64px; height: 64px; border-radius: 50%; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 800; font-family: 'Outfit', sans-serif; box-shadow: 0 4px 10px rgba(0,0,0,0.1); flex-shrink: 0; background: linear-gradient(135deg, #0092df 0%, #007cbd 100%);">
          A
        </div>
        <div style="flex: 1;">
          <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
            <h4 id="view_sm_name" style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">—</h4>
            <span class="status-indicator-pill" style="margin: 0;">
              <span class="dot"></span>
              <span>Active</span>
            </span>
          </div>
          <div style="display: flex; flex-wrap: wrap; gap: 6px; font-size: 0.75rem;">
            <span style="background: #ffffff; color: #0369a1; padding: 3px 9px; border-radius: 6px; font-weight: 700; border: 1px solid #bae6fd;">
              <i class="fa-solid fa-graduation-cap"></i> <span id="view_sm_dept">—</span>
            </span>
            <span style="background: #ffffff; color: #475569; padding: 3px 9px; border-radius: 6px; font-weight: 700; border: 1px solid #cbd5e1;">
              Code: <strong id="view_sm_code" style="color: #0f172a;">—</strong>
            </span>
            <span style="background: #ffffff; color: #475569; padding: 3px 9px; border-radius: 6px; font-weight: 700; border: 1px solid #cbd5e1;">
              User: <strong id="view_sm_username" style="color: #0284c7;">@—</strong>
            </span>
          </div>
        </div>
      </div>

      <!-- Detail Cards Grid -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
        
        <!-- Card 1: Department & Designation -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
          <h5 style="margin: 0 0 12px; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #475569;">
            <i class="fa-solid fa-briefcase" style="color: #0092df; margin-right: 6px;"></i> Role &amp; Academic Status
          </h5>
          <div style="display: grid; gap: 9px; font-size: 0.83rem;">
            <div style="display: flex; justify-content: space-between;"><span style="color: #64748b;">Department:</span> <strong id="view_sm_card_dept" style="color: #0f172a;">—</strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: #64748b;">Designation:</span> <strong id="view_sm_card_desig" style="color: #0f172a;">—</strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: #64748b;">Qualification:</span> <strong id="view_sm_qual" style="color: #0f172a;">—</strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: #64748b;">Blood Group:</span> <strong id="view_sm_blood" style="color: #ef4444;">—</strong></div>
          </div>
        </div>

        <!-- Card 2: Compensation & Date -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
          <h5 style="margin: 0 0 12px; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #475569;">
            <i class="fa-solid fa-money-check-dollar" style="color: #10b981; margin-right: 6px;"></i> Payroll &amp; Employment
          </h5>
          <div style="display: grid; gap: 9px; font-size: 0.83rem;">
            <div style="display: flex; justify-content: space-between;"><span style="color: #64748b;">Monthly Salary:</span> <strong id="view_sm_salary" style="color: #059669; font-size: 0.95rem;">₹—</strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: #64748b;">Joining Date:</span> <strong id="view_sm_joining" style="color: #0f172a;">—</strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: #64748b;">Biometric QR:</span> <strong id="view_sm_qr" style="color: #0284c7;">STAFF-PASS</strong></div>
            <div style="display: flex; justify-content: space-between;"><span style="color: #64748b;">Account Status:</span> <strong style="color: #166534;">Active Verified</strong></div>
          </div>
        </div>

      </div>

      <!-- Card 3: Contact & Address -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 0;">
        <h5 style="margin: 0 0 12px; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #475569;">
          <i class="fa-solid fa-address-book" style="color: #7c3aed; margin-right: 6px;"></i> Contact &amp; Residential Location
        </h5>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.83rem;">
          <div><span style="color: #64748b;">Phone:</span> <strong id="view_sm_phone" style="color: #0f172a; display: block; margin-top: 2px;">—</strong></div>
          <div><span style="color: #64748b;">Email:</span> <strong id="view_sm_email" style="color: #0f172a; display: block; margin-top: 2px;">—</strong></div>
          <div style="grid-column: span 2;"><span style="color: #64748b;">Residential Address:</span> <strong id="view_sm_address" style="color: #0f172a; display: block; margin-top: 2px;">—</strong></div>
        </div>
      </div>

    </div>

    <!-- Modal Footer Actions -->
    <div style="display: flex; justify-content: flex-end; align-items: center; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
      <button type="button" onclick="closeModal('viewStaffModal')" class="btn-hrms btn-hrms-outline">
        Close
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL 3: Edit Faculty / Staff Profile Form
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="editStaffModal">
  <div class="hrms-modal-card" style="max-width: 700px;">
    
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-user-pen" style="color: var(--hrms-primary); margin-right: 8px;"></i>
          Edit Faculty Profile
        </h3>
        <p style="margin: 2px 0 0; font-size: 0.75rem; color: #64748b;">
          Update personal details, academic title, compensation, and contact record
        </p>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('editStaffModal')" aria-label="Close modal">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/staff">
      <input type="hidden" name="action" value="update_staff">
      <input type="hidden" name="staff_id" id="edit_staff_id" value="">

      <div class="hrms-modal-body" style="padding: 24px; max-height: calc(85vh - 140px); overflow-y: auto;">
        
        <!-- Row 1: Full Name & Employee Code -->
        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Full Name *</label>
            <input type="text" name="full_name" id="edit_full_name" class="hrms-input" required style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 700;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Employee Code</label>
            <input type="text" id="edit_employee_code" class="hrms-input" readonly style="width: 100%; padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; background: #f8fafc; font-family: monospace; font-weight: 700; color: #0284c7;">
          </div>
        </div>

        <!-- Row 2: Department & Designation -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Department *</label>
            <select name="department" id="edit_department" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
              <?php foreach ($departments as $d): ?>
                <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Designation / Title</label>
            <input type="text" name="designation" id="edit_designation" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
        </div>

        <!-- Row 3: Qualification & Blood Group -->
        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Academic Qualification</label>
            <input type="text" name="qualification" id="edit_qualification" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Blood Group</label>
            <select name="blood_group" id="edit_blood_group" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
              <option value="B+">B+</option>
              <option value="O+">O+</option>
              <option value="A+">A+</option>
              <option value="AB+">AB+</option>
              <option value="B-">B-</option>
              <option value="O-">O-</option>
              <option value="A-">A-</option>
              <option value="AB-">AB-</option>
            </select>
          </div>
        </div>

        <!-- Row 4: Contact Phone & Email -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Phone Number *</label>
            <input type="text" name="phone" id="edit_phone" class="hrms-input" required style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Email Address</label>
            <input type="email" name="email" id="edit_email" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
        </div>

        <!-- Row 5: Monthly Salary & Password Reset -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Monthly Fixed Salary (₹) *</label>
            <input type="number" step="0.01" name="salary" id="edit_salary" class="hrms-input" required style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 800; color: #059669;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Reset Password (Optional)</label>
            <input type="password" name="password" placeholder="Leave blank to keep current" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
        </div>

        <!-- Row 6: Address -->
        <div class="form-group" style="margin-bottom: 0;">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Residential Address</label>
          <textarea name="address" id="edit_address" rows="2" class="hrms-input" placeholder="e.g. Sector 19, Ulwe, Navi Mumbai..." style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
        </div>

      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
        <button type="button" onclick="closeModal('editStaffModal');" class="btn-hrms btn-hrms-outline">Cancel</button>
        <button type="submit" class="login-submit-btn" style="width: auto; margin-top: 0; padding: 10px 24px;">
          <i class="fa-solid fa-check"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<script>
let currentViewingStaff = null;

function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add('show');
    modal.style.display = 'flex';
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('show');
    modal.style.display = 'none';
  }
}

// Ensure both globally available
window.openModal = openModal;
window.closeModal = closeModal;

function openAddStaffModal() {
  openModal('addStaffModal');
}

function closeAddStaffModal() {
  closeModal('addStaffModal');
}

function viewStaffDetails(sm) {
  if (!sm) return;
  currentViewingStaff = sm;
  
  // Name & Header
  const nameEl = document.getElementById('view_sm_name');
  if (nameEl) nameEl.textContent = sm.full_name || '—';
  
  const deptEl = document.getElementById('view_sm_dept');
  if (deptEl) deptEl.textContent = sm.department || 'General';
  
  const codeEl = document.getElementById('view_sm_code');
  if (codeEl) codeEl.textContent = sm.employee_code || '—';
  
  const userEl = document.getElementById('view_sm_username');
  if (userEl) userEl.textContent = '@' + (sm.username || 'user');

  // Avatar initial
  const avatarEl = document.getElementById('view_sm_avatar');
  if (avatarEl) {
    const firstLetter = (sm.full_name || 'F').charAt(0).toUpperCase();
    avatarEl.textContent = firstLetter;
  }

  // Card 1
  const cardDeptEl = document.getElementById('view_sm_card_dept');
  if (cardDeptEl) cardDeptEl.textContent = sm.department || '—';
  
  const cardDesigEl = document.getElementById('view_sm_card_desig');
  if (cardDesigEl) cardDesigEl.textContent = sm.designation || 'Lecturer';
  
  const qualEl = document.getElementById('view_sm_qual');
  if (qualEl) qualEl.textContent = sm.qualification || '—';
  
  const bloodEl = document.getElementById('view_sm_blood');
  if (bloodEl) bloodEl.textContent = sm.blood_group || '—';

  // Card 2
  const salEl = document.getElementById('view_sm_salary');
  if (salEl) {
    const salVal = parseFloat(sm.monthly_salary) || 0;
    salEl.textContent = '₹' + salVal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }
  
  const joinEl = document.getElementById('view_sm_joining');
  if (joinEl) {
    joinEl.textContent = sm.joining_date ? new Date(sm.joining_date).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'}) : 'Active';
  }
  
  const qrEl = document.getElementById('view_sm_qr');
  if (qrEl) qrEl.textContent = sm.qr_code || ('STAFF-' + sm.employee_code);

  // Card 3
  const phoneEl = document.getElementById('view_sm_phone');
  if (phoneEl) phoneEl.textContent = sm.phone || sm.user_phone || '—';
  
  const emailEl = document.getElementById('view_sm_email');
  if (emailEl) emailEl.textContent = sm.email || sm.user_email || '—';
  
  const addrEl = document.getElementById('view_sm_address');
  if (addrEl) addrEl.textContent = sm.address || 'Campus Staff Quarters, KSV Academy, Sector 12';

  // Footer links
  const idLink = document.getElementById('view_sm_id_card_link');
  if (idLink) idLink.href = '<?php echo ADMIN_URL; ?>/staff-id?id=' + sm.id;
  
  const payLink = document.getElementById('view_sm_payroll_link');
  if (payLink) payLink.href = '<?php echo ADMIN_URL; ?>/payroll?staff_id=' + sm.id;

  openModal('viewStaffModal');
}

function openEditStaffModal(sm) {
  if (!sm && currentViewingStaff) {
    sm = currentViewingStaff;
  }
  if (!sm) return;

  closeModal('viewStaffModal');

  const idEl = document.getElementById('edit_staff_id');
  if (idEl) idEl.value = sm.id || '';
  
  const nameEl = document.getElementById('edit_full_name');
  if (nameEl) nameEl.value = sm.full_name || '';
  
  const codeEl = document.getElementById('edit_employee_code');
  if (codeEl) codeEl.value = sm.employee_code || '';
  
  const deptEl = document.getElementById('edit_department');
  if (deptEl) deptEl.value = sm.department || 'Science';
  
  const desigEl = document.getElementById('edit_designation');
  if (desigEl) desigEl.value = sm.designation || 'Lecturer';
  
  const qualEl = document.getElementById('edit_qualification');
  if (qualEl) qualEl.value = sm.qualification || '';
  
  const bloodEl = document.getElementById('edit_blood_group');
  if (bloodEl) bloodEl.value = sm.blood_group || 'B+';
  
  const phoneEl = document.getElementById('edit_phone');
  if (phoneEl) phoneEl.value = sm.phone || sm.user_phone || '';
  
  const emailEl = document.getElementById('edit_email');
  if (emailEl) emailEl.value = sm.email || sm.user_email || '';
  
  const salEl = document.getElementById('edit_salary');
  if (salEl) salEl.value = sm.monthly_salary || 55000;
  
  const addrEl = document.getElementById('edit_address');
  if (addrEl) addrEl.value = sm.address || '';

  openModal('editStaffModal');
}

function editFromViewModal() {
  if (currentViewingStaff) {
    openEditStaffModal(currentViewingStaff);
  }
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal('addStaffModal');
    closeModal('viewStaffModal');
    closeModal('editStaffModal');
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
