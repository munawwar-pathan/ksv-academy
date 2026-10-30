<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
$page_title = 'Faculty & Staff Directory | KSV Academy HRMS';
$page_heading = 'Staff Management';
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

// Handle Direct Login as Staff (GET request from "Login Direct" button in new tab)
if (isset($_GET['action']) && $_GET['action'] === 'direct_login') {
    $target_staff_id = intval($_GET['staff_id'] ?? 0);
    if ($target_staff_id > 0) {
        $stmt = $db->prepare("
            SELECT sp.*, u.id as u_id, u.username, u.full_name, u.email as user_email, u.phone as user_phone, u.avatar, u.role
            FROM staff_profiles sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.id = ? AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$target_staff_id]);
        $target_staff = $stmt->fetch();
        if ($target_staff) {
            // Preserve admin session so admin tab is never logged out
            if (!empty($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), ['super_admin'])) {
                $_SESSION['admin_user_id'] = $_SESSION['user_id'];
                $_SESSION['admin_username'] = $_SESSION['username'];
                $_SESSION['admin_role'] = $_SESSION['role'];
                $_SESSION['admin_full_name'] = $_SESSION['full_name'];
            }

            // Set staff active session
            $_SESSION['user_id'] = $target_staff['u_id'];
            $_SESSION['username'] = $target_staff['username'];
            $_SESSION['role'] = 'staff';
            $_SESSION['full_name'] = $target_staff['full_name'];
            $_SESSION['email'] = $target_staff['email'] ?: $target_staff['user_email'];
            $_SESSION['avatar'] = $target_staff['avatar'];
            $_SESSION['active_staff_id'] = $target_staff['id'];
            $_SESSION['impersonated_staff'] = true;

            set_flash('success', "Logged in as faculty member " . htmlspecialchars($target_staff['full_name']) . " (" . htmlspecialchars($target_staff['employee_code']) . ")!");
            $dest_url = get_dashboard_url('staff', $target_staff['u_id']);
            header('Location: ' . $dest_url);
            exit;
        } else {
            set_flash('error', "Faculty member not found or inactive.");
            header('Location: ' . ADMIN_URL . '/staff');
            exit;
        }
    }
}

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
        $specialization = trim($_POST['specialization'] ?? $department);
        $experience = trim($_POST['experience'] ?? '5+ Years');
        $highlight = trim($_POST['highlight'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $user_status = in_array($_POST['user_status'] ?? 'active', ['active', 'inactive']) ? $_POST['user_status'] : 'active';
        
        $photo = '/assets/imgs/ksv-logo.jpg';
        $avatar = '';

        // Handle Photo Upload
        if (isset($_FILES['photo_file']) && !empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $p_name = $_FILES['photo_file']['name'];
            $p_tmp = $_FILES['photo_file']['tmp_name'];
            $p_ext = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
            if (in_array($p_ext, ['jpg', 'jpeg', 'png', 'webp', 'avif'])) {
                $basename = 'faculty-' . time() . '-' . rand(100, 999) . '.' . $p_ext;
                $dest = __DIR__ . '/../../assets/imgs/faculties/' . $basename;
                if (move_uploaded_file($p_tmp, $dest)) {
                    @chmod($dest, 0644);
                    $photo = '/assets/imgs/faculties/' . $basename;
                    $avatar = $basename;
                }
            }
        } elseif (!empty($_POST['photo_path'])) {
            $photo = trim($_POST['photo_path']);
            $avatar = basename($photo);
        }

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
                $stmt = $db->prepare("INSERT INTO users (username, password, role, email, phone, full_name, avatar, status) VALUES (?, ?, 'staff', ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $email, $phone, $full_name, $avatar, $user_status]);
                $user_id = $db->lastInsertId();

                // 2. Generate Employee Code
                $next_emp = $db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM staff_profiles")->fetchColumn();
                $emp_code = 'KSV-FAC-' . str_pad($next_emp, 3, '0', STR_PAD_LEFT);
                $qr_code = 'STAFF-' . $emp_code;

                // 3. Create Staff Profile
                $stmt_prof = $db->prepare("
                    INSERT INTO staff_profiles (
                        session_id, user_id, employee_code, department, designation, qualification,
                        joining_date, monthly_salary, phone, email, address, blood_group, qr_code, today_status,
                        photo, specialization, experience, bio, highlight, sort_order
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        CURDATE(), ?, ?, ?, ?, ?, ?, 'Present',
                        ?, ?, ?, ?, ?, ?
                    )
                ");
                $stmt_prof->execute([
                    $session_id, $user_id, $emp_code, $department, $designation, $qualification,
                    $salary, $phone, $email, $address, $blood_group, $qr_code,
                    $photo, $specialization, $experience, $bio, $highlight, $sort_order
                ]);

                $live_note = ($user_status === 'active') ? 'Live on website faculty list' : 'Inactive in draft';
                set_flash('success', "Faculty member {$full_name} ({$emp_code}) registered successfully! ({$live_note})");
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
        $specialization = trim($_POST['specialization'] ?? '');
        $experience = trim($_POST['experience'] ?? '');
        $highlight = trim($_POST['highlight'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $user_status = in_array($_POST['user_status'] ?? 'active', ['active', 'inactive']) ? $_POST['user_status'] : 'active';
        $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) 
            ? json_encode(array_values(array_map('trim', $_POST['permissions']))) 
            : json_encode([]);

        // Handle Photo Upload
        $new_photo = null;
        $new_avatar = null;
        if (isset($_FILES['photo_file']) && !empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $p_name = $_FILES['photo_file']['name'];
            $p_tmp = $_FILES['photo_file']['tmp_name'];
            $p_ext = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
            if (in_array($p_ext, ['jpg', 'jpeg', 'png', 'webp', 'avif'])) {
                $basename = 'faculty-' . time() . '-' . rand(100, 999) . '.' . $p_ext;
                $dest = __DIR__ . '/../../assets/imgs/faculties/' . $basename;
                if (move_uploaded_file($p_tmp, $dest)) {
                    @chmod($dest, 0644);
                    $new_photo = '/assets/imgs/faculties/' . $basename;
                    $new_avatar = $basename;
                }
            }
        } elseif (!empty($_POST['photo_path'])) {
            $new_photo = trim($_POST['photo_path']);
            $new_avatar = basename($new_photo);
        }

        if ($staff_id > 0 && !empty($full_name)) {
            try {
                $stmt_uid = $db->prepare("SELECT user_id FROM staff_profiles WHERE id = ?");
                $stmt_uid->execute([$staff_id]);
                $user_id = $stmt_uid->fetchColumn();
                if ($user_id) {
                    if ($new_avatar !== null) {
                        $stmt_u = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ?, avatar = ? WHERE id = ?");
                        $stmt_u->execute([$full_name, $email, $phone, $user_status, $new_avatar, $user_id]);
                    } else {
                        $stmt_u = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
                        $stmt_u->execute([$full_name, $email, $phone, $user_status, $user_id]);
                    }

                    if (!empty($_POST['password'])) {
                        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
                    }
                }

                if ($new_photo !== null) {
                    $stmt_p = $db->prepare("
                        UPDATE staff_profiles 
                        SET department = ?, designation = ?, qualification = ?, monthly_salary = ?, phone = ?, email = ?, address = ?, blood_group = ?, menu_permissions = ?,
                            specialization = ?, experience = ?, highlight = ?, bio = ?, sort_order = ?, photo = ?
                        WHERE id = ?
                    ");
                    $stmt_p->execute([
                        $department, $designation, $qualification, $salary, $phone, $email, $address, $blood_group, $permissions,
                        $specialization, $experience, $highlight, $bio, $sort_order, $new_photo, $staff_id
                    ]);
                } else {
                    $stmt_p = $db->prepare("
                        UPDATE staff_profiles 
                        SET department = ?, designation = ?, qualification = ?, monthly_salary = ?, phone = ?, email = ?, address = ?, blood_group = ?, menu_permissions = ?,
                            specialization = ?, experience = ?, highlight = ?, bio = ?, sort_order = ?
                        WHERE id = ?
                    ");
                    $stmt_p->execute([
                        $department, $designation, $qualification, $salary, $phone, $email, $address, $blood_group, $permissions,
                        $specialization, $experience, $highlight, $bio, $sort_order, $staff_id
                    ]);
                }

                $status_txt = ($user_status === 'active') ? 'Active (Live on website)' : 'Inactive (Hidden from website)';
                set_flash('success', "Faculty profile for {$full_name} updated successfully! Status: {$status_txt}.");
                header('Location: ' . ADMIN_URL . '/staff');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Error updating staff: ' . $e->getMessage());
            }
        }
    }

    // Toggle Staff Status (Active / Inactive)
    if ($action === 'toggle_staff_status') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        if ($staff_id > 0) {
            $stmt = $db->prepare("SELECT u.id, u.status, u.full_name FROM staff_profiles sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ?");
            $stmt->execute([$staff_id]);
            $u = $stmt->fetch();
            if ($u) {
                $new_status = ($u['status'] === 'active') ? 'inactive' : 'active';
                $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $u['id']]);
                $lbl = ($new_status === 'active') ? 'ACTIVE (live on website faculty list)' : 'INACTIVE (hidden from website)';
                set_flash('success', "Faculty member '{$u['full_name']}' is now {$lbl}.");
            }
        }
        header('Location: ' . ADMIN_URL . '/staff');
        exit;
    }

    // Delete Staff
    if ($action === 'delete_staff') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        if ($staff_id > 0) {
            $stmt_uid = $db->prepare("SELECT user_id FROM staff_profiles WHERE id = ?");
            $stmt_uid->execute([$staff_id]);
            $user_id = $stmt_uid->fetchColumn();
            $db->prepare("DELETE FROM staff_profiles WHERE id = ?")->execute([$staff_id]);
            if ($user_id) {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            }
            set_flash('success', "Faculty member record removed successfully.");
            header('Location: ' . ADMIN_URL . '/staff');
            exit;
        }
    }

    // Change Staff Password
    if ($action === 'change_staff_password') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (empty($new_password) || empty($confirm_password)) {
            set_flash('error', 'Please enter and confirm the new password.');
        } elseif ($new_password !== $confirm_password) {
            set_flash('error', 'New password and confirm password do not match.');
        } elseif (strlen($new_password) < 4) {
            set_flash('error', 'New password must be at least 4 characters long.');
        } else {
            $stmt = $db->prepare("
                SELECT sp.*, u.id as u_id, u.full_name, sp.employee_code 
                FROM staff_profiles sp 
                JOIN users u ON sp.user_id = u.id 
                WHERE sp.id = ? 
                LIMIT 1
            ");
            $stmt->execute([$staff_id]);
            $sm = $stmt->fetch();
            if ($sm && !empty($sm['u_id'])) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $sm['u_id']]);
                set_flash('success', "Password for faculty member " . htmlspecialchars($sm['full_name']) . " (" . htmlspecialchars($sm['employee_code']) . ") updated successfully!");
            } else {
                set_flash('error', 'Faculty member not found.');
            }
        }
        header('Location: ' . ADMIN_URL . '/staff');
        exit;
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
    ORDER BY CASE WHEN sp.sort_order = 0 THEN 9999 ELSE sp.sort_order END ASC, sp.id ASC
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
/* ── Staff ID Directory Table & Badge Styles (Simple & Sober - Matching staff-id) ── */
.staff-id-table {
  width: 100%;
  min-width: 1260px;
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
.sid-status-active {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  font-size: 0.74rem;
  font-weight: 600;
  padding: 2px 9px;
  border-radius: 20px;
  white-space: nowrap;
}

/* Sober Action Icon Buttons */
.sid-action-btn {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 0.76rem;
  padding: 0;
  text-decoration: none;
}
.sid-action-btn:hover {
  transform: translateY(-1px);
}
.sid-action-btn.view {
  color: #0284c7;
  border-color: #bae6fd;
  background: #f0f9ff;
}
.sid-action-btn.view:hover {
  background: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
}
.sid-action-btn.idcard {
  color: #0891b2;
  border-color: #cffafe;
  background: #ecfeff;
}
.sid-action-btn.idcard:hover {
  background: #0891b2;
  color: #ffffff;
  border-color: #0891b2;
}
.sid-action-btn.edit {
  color: #4f46e5;
  border-color: #c7d2fe;
  background: #eef2ff;
}
.sid-action-btn.edit:hover {
  background: #4f46e5;
  color: #ffffff;
  border-color: #4f46e5;
}
.sid-action-btn.delete {
  color: #dc2626;
  border-color: #fecaca;
  background: #fef2f2;
}
.sid-action-btn.delete:hover {
  background: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
}

/* ── Login Column & Buttons (Simple & Sober) ── */
.sid-login-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-wrap: nowrap;
}
.btn-student-login {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 0.73rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.btn-student-login:hover {
  background: #dcfce7;
  border-color: #86efac;
  color: #14532d;
  transform: translateY(-1px);
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.btn-student-pwd {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #fefce8;
  color: #854d0e;
  border: 1px solid #fef08a;
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 0.73rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.btn-student-pwd:hover {
  background: #fef9c3;
  border-color: #fde047;
  color: #713f12;
  transform: translateY(-1px);
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

/* ── Staff Menu Permissions UI (Simple, Sober & Premium) ── */
.perm-box-container {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px 16px;
  margin-top: 8px;
}
.perm-section-label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #475569;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.perm-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(195px, 1fr));
  gap: 10px;
}
.perm-card {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  padding: 9px 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
  box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.perm-card:hover {
  border-color: #0284c7;
  background: #f0f9ff;
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(2, 132, 199, 0.08);
}
.perm-card input[type="checkbox"] {
  accent-color: #0284c7;
  width: 16px;
  height: 16px;
  cursor: pointer;
  margin: 0;
  flex-shrink: 0;
}
.perm-card-text {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.80rem;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
}
.perm-card-text i {
  color: #0284c7;
  font-size: 0.84rem;
  width: 15px;
  text-align: center;
  flex-shrink: 0;
}
.btn-perm-toggle {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-perm-toggle:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
  color: #0f172a;
}

/* ══════════════════════════════════════════════════════════════════════
   DARK MODE ENHANCEMENTS — FACULTY & STAFF DIRECTORY
   ══════════════════════════════════════════════════════════════════════ */

/* 1. Stat Metric Cards Strip */
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
[data-theme="dark"] .stat-card.purple .stat-icon-wrapper {
  background: rgba(139, 92, 246, 0.15) !important;
  color: #a78bfa !important;
  border-color: rgba(139, 92, 246, 0.3) !important;
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
[data-theme="dark"] .stat-card-footer span[style*="color:#059669"],
[data-theme="dark"] .stat-card-footer span[style*="color: #059669"] {
  color: #34d399 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color:#7c3aed"],
[data-theme="dark"] .stat-card-footer span[style*="color: #7c3aed"] {
  color: #a78bfa !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color:#d97706"],
[data-theme="dark"] .stat-card-footer span[style*="color: #d97706"] {
  color: #fbbf24 !important;
}

/* 2. Faculty Search & Filter Toolbar */
[data-theme="dark"] .roaster-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .roaster-search-box {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .roaster-search-box:focus-within {
  background: #0f172a !important;
  border-color: var(--hrms-primary, #0092df) !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .roaster-search-box i {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-search-box input {
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-search-box input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .roaster-search-box .clear-search-btn {
  background: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-search-box .clear-search-btn:hover {
  background: #475569 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-select-box > i:first-child,
[data-theme="dark"] .roaster-select-box .select-chevron {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-select-box select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-select-box select:focus {
  background: #0f172a !important;
  border-color: var(--hrms-primary, #0092df) !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .roaster-select-box select option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-btn.secondary {
  background: #334155 !important;
  color: #f1f5f9 !important;
  border: 1px solid #475569 !important;
}
[data-theme="dark"] .roaster-btn.secondary:hover {
  background: #475569 !important;
  color: #ffffff !important;
}
[data-theme="dark"] .roaster-btn.ghost {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-btn.ghost:hover {
  background: rgba(239, 68, 68, 0.15) !important;
  color: #f87171 !important;
}
[data-theme="dark"] .scholars-count-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .scholars-count-badge strong {
  color: #f8fafc !important;
}

/* 3. Main Directory Card & Table Header */
[data-theme="dark"] .staff-directory-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .staff-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .staff-title-block .card-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .staff-session-badge {
  background: #0f172a !important;
  color: #cbd5e1 !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .staff-records-count {
  color: #94a3b8 !important;
}
[data-theme="dark"] .staff-records-count strong {
  color: #f8fafc !important;
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
[data-theme="dark"] .staff-id-table tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.02) !important;
}

/* User cell & Avatar */
[data-theme="dark"] .staff-id-table tbody td .user-cell img {
  border-color: #334155 !important;
}
[data-theme="dark"] .staff-id-table tbody td .user-cell > div:first-child[style*="background: #f1f5f9"],
[data-theme="dark"] .staff-id-table tbody td .user-cell > div:first-child[style*="background:#f1f5f9"] {
  background: #334155 !important;
  color: #f8fafc !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .staff-id-table tbody td .user-cell > div > div:first-child {
  color: #f8fafc !important;
}
[data-theme="dark"] .staff-id-table tbody td .user-cell > div > div:last-child {
  color: #94a3b8 !important;
}

/* Table Text & Values */
[data-theme="dark"] .staff-id-table tbody td div[style*="color: #475569"],
[data-theme="dark"] .staff-id-table tbody td div[style*="color:#475569"] {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .staff-id-table tbody td div[style*="color: #334155"],
[data-theme="dark"] .staff-id-table tbody td div[style*="color:#334155"] {
  color: #e2e8f0 !important;
}
[data-theme="dark"] .staff-id-table tbody td div[style*="color: #0f172a"],
[data-theme="dark"] .staff-id-table tbody td div[style*="color:#0f172a"] {
  color: #f8fafc !important;
}
[data-theme="dark"] .staff-id-table tbody td:nth-child(5) div[style*="color: #0f172a"],
[data-theme="dark"] .staff-id-table tbody td:nth-child(5) div[style*="color:#0f172a"] {
  color: #34d399 !important;
}
[data-theme="dark"] .staff-id-table tbody td a[style*="color: #334155"],
[data-theme="dark"] .staff-id-table tbody td a[style*="color:#334155"] {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .staff-id-table tbody td a[style*="color: #64748b"],
[data-theme="dark"] .staff-id-table tbody td a[style*="color:#64748b"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .staff-id-table tbody td span[style*="color: #64748b"],
[data-theme="dark"] .staff-id-table tbody td span[style*="color:#64748b"] {
  color: #94a3b8 !important;
}

/* Badges & Pills */
[data-theme="dark"] .sid-code-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .sid-dept-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .sid-dept-pill i {
  color: #94a3b8 !important;
}
[data-theme="dark"] .sid-blood-badge {
  background: rgba(225, 29, 72, 0.15) !important;
  border-color: rgba(225, 29, 72, 0.3) !important;
  color: #fb7185 !important;
}
[data-theme="dark"] .sid-status-active {
  background: rgba(22, 101, 52, 0.2) !important;
  color: #4ade80 !important;
  border-color: rgba(22, 101, 52, 0.4) !important;
}
[data-theme="dark"] .btn-student-login {
  background: rgba(22, 101, 52, 0.2) !important;
  color: #4ade80 !important;
  border-color: rgba(22, 101, 52, 0.4) !important;
}
[data-theme="dark"] .btn-student-login:hover {
  background: rgba(22, 101, 52, 0.35) !important;
  color: #86efac !important;
  border-color: rgba(22, 101, 52, 0.6) !important;
}
[data-theme="dark"] .btn-student-pwd {
  background: rgba(202, 138, 4, 0.2) !important;
  color: #facc15 !important;
  border-color: rgba(202, 138, 4, 0.4) !important;
}
[data-theme="dark"] .btn-student-pwd:hover {
  background: rgba(202, 138, 4, 0.35) !important;
  color: #fef08a !important;
  border-color: rgba(202, 138, 4, 0.6) !important;
}

/* Action Buttons */
[data-theme="dark"] .sid-action-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .sid-action-btn.view {
  color: #38bdf8 !important;
  border-color: rgba(2, 132, 199, 0.4) !important;
  background: rgba(2, 132, 199, 0.1) !important;
}
[data-theme="dark"] .sid-action-btn.view:hover {
  background: #0284c7 !important;
  color: #ffffff !important;
  border-color: #0284c7 !important;
}
[data-theme="dark"] .sid-action-btn.edit {
  color: #a5b4fc !important;
  border-color: rgba(99, 102, 241, 0.4) !important;
  background: rgba(99, 102, 241, 0.1) !important;
}
[data-theme="dark"] .sid-action-btn.edit:hover {
  background: #6366f1 !important;
  color: #ffffff !important;
  border-color: #6366f1 !important;
}
[data-theme="dark"] .sid-action-btn.delete {
  color: #f87171 !important;
  border-color: rgba(239, 68, 68, 0.4) !important;
  background: rgba(239, 68, 68, 0.1) !important;
}
[data-theme="dark"] .sid-action-btn.delete:hover {
  background: #dc2626 !important;
  color: #ffffff !important;
  border-color: #dc2626 !important;
}
[data-theme="dark"] .sid-action-btn.idcard {
  color: #22d3ee !important;
  border-color: rgba(6, 182, 212, 0.4) !important;
  background: rgba(6, 182, 212, 0.1) !important;
}
[data-theme="dark"] .sid-action-btn.idcard:hover {
  background: #0891b2 !important;
  color: #ffffff !important;
  border-color: #0891b2 !important;
}

/* Empty State */
[data-theme="dark"] .staff-id-table tbody td div[style*="background: #f1f5f9"],
[data-theme="dark"] .staff-id-table tbody td div[style*="background:#f1f5f9"] {
  background: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .staff-id-table tbody td h4 {
  color: #f8fafc !important;
}
[data-theme="dark"] .staff-id-table tbody td p strong {
  color: #f8fafc !important;
}

/* Pagination & Controls */
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
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #64748b !important;
}
[data-theme="dark"] .pagination-ellipsis {
  color: #64748b !important;
}
[data-theme="dark"] .pagination-controls-wrap div[style*="color: #64748b"],
[data-theme="dark"] .pagination-controls-wrap div[style*="color:#64748b"] {
  color: #94a3b8 !important;
}

/* 4. Modals General Structure */
[data-theme="dark"] .hrms-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5) !important;
}
[data-theme="dark"] .hrms-modal-header {
  background: #1e293b !important;
  border-bottom: 1px solid #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-modal-header h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-modal-header p {
  color: #94a3b8 !important;
}
[data-theme="dark"] .modal-close-btn {
  color: #94a3b8 !important;
}
[data-theme="dark"] .modal-close-btn:hover {
  background: rgba(255, 255, 255, 0.08) !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-modal-body {
  background: #1e293b !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-modal-footer {
  background: #0f172a !important;
  border-top: 1px solid #334155 !important;
}
[data-theme="dark"] .hrms-modal-card .btn-hrms.btn-hrms-outline,
[data-theme="dark"] .hrms-modal-card .btn-hrms-outline {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .hrms-modal-card .btn-hrms.btn-hrms-outline:hover,
[data-theme="dark"] .hrms-modal-card .btn-hrms-outline:hover {
  background: #334155 !important;
  border-color: #475569 !important;
  color: #f8fafc !important;
}

/* Modal Inputs & Labels */
[data-theme="dark"] .hrms-modal .form-group label,
[data-theme="dark"] .hrms-modal label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .hrms-modal input.hrms-input,
[data-theme="dark"] .hrms-modal select.hrms-input,
[data-theme="dark"] .hrms-modal textarea.hrms-input,
[data-theme="dark"] .hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-modal input.hrms-input:focus,
[data-theme="dark"] .hrms-modal select.hrms-input:focus,
[data-theme="dark"] .hrms-modal textarea.hrms-input:focus,
[data-theme="dark"] .hrms-input:focus {
  border-color: var(--hrms-primary, #0092df) !important;
  background: #0f172a !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.25) !important;
}
[data-theme="dark"] .hrms-modal input.hrms-input::placeholder,
[data-theme="dark"] .hrms-modal textarea.hrms-input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .hrms-modal select.hrms-input option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #edit_employee_code {
  background: #0b1329 !important;
  border-color: #334155 !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] input[name="salary"] {
  color: #34d399 !important;
}

/* 5. Modal 2: #viewStaffModal (Faculty Profile Details) */
[data-theme="dark"] #viewStaffModal .view-staff-banner {
  background: linear-gradient(135deg, #0c4a6e 0%, #075985 100%) !important;
  border-color: #0369a1 !important;
}
[data-theme="dark"] #viewStaffModal #view_sm_name {
  color: #ffffff !important;
}
[data-theme="dark"] #viewStaffModal .view-staff-meta span {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] #viewStaffModal .view-staff-meta span strong {
  color: #f8fafc !important;
}
[data-theme="dark"] #viewStaffModal .view-staff-meta span #view_sm_username {
  color: #38bdf8 !important;
}
[data-theme="dark"] #viewStaffModal .view-staff-meta span #view_sm_dept {
  color: #38bdf8 !important;
}
[data-theme="dark"] #viewStaffModal .hrms-modal-body div[style*="background: #f8fafc"],
[data-theme="dark"] #viewStaffModal .hrms-modal-body div[style*="background:#f8fafc"] {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] #viewStaffModal .hrms-modal-body h5 {
  color: #cbd5e1 !important;
}
[data-theme="dark"] #viewStaffModal .hrms-modal-body span[style*="color: #64748b"],
[data-theme="dark"] #viewStaffModal .hrms-modal-body span[style*="color:#64748b"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] #viewStaffModal .hrms-modal-body strong {
  color: #f8fafc !important;
}
[data-theme="dark"] #viewStaffModal #view_sm_salary {
  color: #34d399 !important;
}
[data-theme="dark"] #viewStaffModal #view_sm_blood {
  color: #fb7185 !important;
}
[data-theme="dark"] #viewStaffModal #view_sm_qr {
  color: #38bdf8 !important;
}

/* 6. Modal 3: #editStaffModal Permissions UI */
[data-theme="dark"] #editStaffModal label[style*="color: #0f172a"],
[data-theme="dark"] #editStaffModal label[style*="color:#0f172a"] {
  color: #f8fafc !important;
}
[data-theme="dark"] #editStaffModal div[style*="color: #64748b"],
[data-theme="dark"] #editStaffModal div[style*="color:#64748b"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .perm-box-container {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .perm-section-label {
  color: #94a3b8 !important;
  border-top-color: #334155 !important;
}
[data-theme="dark"] .perm-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .perm-card:hover {
  background: #1e3a5f !important;
  border-color: #0284c7 !important;
}
[data-theme="dark"] .perm-card-text {
  color: #f8fafc !important;
}
[data-theme="dark"] .perm-card-text i {
  color: #38bdf8 !important;
}
[data-theme="dark"] .btn-perm-toggle {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .btn-perm-toggle:hover {
  background: #334155 !important;
  border-color: #475569 !important;
  color: #ffffff !important;
}

/* 7. Modal 4: #changeStaffPasswordModal */
[data-theme="dark"] #changeStaffPasswordModal .hrms-modal-header > div > div:first-child {
  background: rgba(202, 138, 4, 0.15) !important;
  border-color: rgba(202, 138, 4, 0.3) !important;
  color: #fbbf24 !important;
}
[data-theme="dark"] #changeStaffPasswordModal div[style*="background: #f8fafc"],
[data-theme="dark"] #changeStaffPasswordModal div[style*="background:#f8fafc"] {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] #pwd_staff_name {
  color: #f8fafc !important;
}
[data-theme="dark"] #pwd_staff_username {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] #changeStaffPasswordModal div[style*="color: #64748b"],
[data-theme="dark"] #changeStaffPasswordModal div[style*="color:#64748b"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] #changeStaffPasswordModal button[title="Show/Hide password"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] #changeStaffPasswordModal button[title="Show/Hide password"]:hover {
  color: #f8fafc !important;
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
     FACULTY & STAFF DIRECTORY TABLE (Simple & Sober - Like staff-id)
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card staff-directory-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
  <div class="hrms-card-header staff-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div class="staff-title-block">
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-chalkboard-user" style="color: #0284c7;"></i>
        Faculty &amp; Staff Directory
        <span class="staff-session-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Academic lecturers, department chairs, and administrative leadership registered in the academy.
      </p>
    </div>
    <div class="staff-records-count" style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
      <span>Total Records: <strong style="color: #0f172a;"><?php echo $total_records; ?></strong></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="hrms-table staff-id-table">
      <thead>
        <tr>
          <th style="min-width: 250px;">Faculty Member</th>
          <th style="min-width: 115px;">Employee ID</th>
          <th style="min-width: 155px;">Department &amp; Post</th>
          <th style="min-width: 135px;">Qualification &amp; Blood</th>
          <th style="min-width: 115px;">Monthly Salary</th>
          <th style="min-width: 140px;">Contact Info</th>
          <th style="min-width: 85px;">Status</th>
          <th style="min-width: 215px;">Login</th>
          <th style="min-width: 105px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($staff_members)): ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-user-slash"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Faculty Records Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
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
              $phone_val = $sm['phone'] ?: $sm['user_phone'];
              $email_val = $sm['email'] ?: $sm['user_email'];
            ?>
            <tr>
              
              <!-- 1. Faculty Member Profile -->
              <td style="white-space: nowrap;">
                <div class="user-cell" style="display: flex; align-items: center; gap: 12px; min-width: 240px;">
                  <?php if (!empty($photo_url)): ?>
                    <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo htmlspecialchars($sm['full_name']); ?>" style="width: 40px; height: 40px; min-width: 40px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="width: 40px; height: 40px; min-width: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php else: ?>
                    <div style="width: 40px; height: 40px; min-width: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php endif; ?>
                  
                  <div style="min-width: 0;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem; font-family: 'Outfit', sans-serif; line-height: 1.25; white-space: nowrap;">
                      <?php echo htmlspecialchars($sm['full_name']); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.73rem; color: #64748b; margin-top: 2px; white-space: nowrap;">
                      <span style="font-family: monospace; color: #0284c7; font-weight: 600;">@<?php echo htmlspecialchars($sm['username']); ?></span>
                      <span>&bull;</span>
                      <span>Joined <?php echo !empty($sm['joining_date']) ? date('M Y', strtotime($sm['joining_date'])) : 'Active'; ?></span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 2. Employee ID -->
              <td>
                <span class="sid-code-pill">
                  <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
                  <span><?php echo htmlspecialchars($sm['employee_code']); ?></span>
                </span>
                <div style="font-size: 0.72rem; color: #64748b; margin-top: 4px; display: flex; align-items: center; gap: 4px; white-space: nowrap;">
                  <i class="fa-solid fa-qrcode" style="color: #0284c7; font-size: 0.65rem;"></i>
                  <span>QR Pass Verified</span>
                </div>
              </td>

              <!-- 3. Department & Designation -->
              <td>
                <span class="sid-dept-pill">
                  <i class="fa-solid fa-building" style="font-size: 0.65rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($sm['department']); ?></span>
                </span>
                <div style="font-size: 0.74rem; font-weight: 600; color: #475569; margin-top: 4px; white-space: nowrap;">
                  <?php echo htmlspecialchars($sm['designation']); ?>
                </div>
              </td>

              <!-- 4. Qualification & Blood -->
              <td>
                <div style="font-size: 0.78rem; font-weight: 600; color: #334155; white-space: nowrap;" title="<?php echo htmlspecialchars($sm['qualification']); ?>">
                  <?php echo htmlspecialchars($sm['qualification']); ?>
                </div>
                <?php if (!empty($sm['blood_group'])): ?>
                  <div style="margin-top: 4px;">
                    <span class="sid-blood-badge">Blood: <?php echo htmlspecialchars($sm['blood_group']); ?></span>
                  </div>
                <?php endif; ?>
              </td>

              <!-- 5. Monthly Salary -->
              <td>
                <div style="font-weight: 700; color: #0f172a; font-size: 0.86rem; font-family: 'Outfit', sans-serif; white-space: nowrap;">
                  <?php echo format_inr($sm['monthly_salary']); ?>
                </div>
                <div style="font-size: 0.72rem; color: #64748b; font-weight: 500; margin-top: 2px; white-space: nowrap;">
                  Per Month
                </div>
              </td>

              <!-- 6. Contact Information -->
              <td>
                <?php if (!empty($phone_val)): ?>
                  <div style="white-space: nowrap;">
                    <a href="tel:<?php echo htmlspecialchars($phone_val); ?>" style="font-size: 0.74rem; color: #334155; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-weight: 600; white-space: nowrap;">
                      <i class="fa-solid fa-phone" style="color: #0284c7; font-size: 0.65rem;"></i>
                      <span><?php echo htmlspecialchars($phone_val); ?></span>
                    </a>
                  </div>
                <?php endif; ?>
                <?php if (!empty($email_val)): ?>
                  <div style="margin-top: 3px; white-space: nowrap;">
                    <a href="mailto:<?php echo htmlspecialchars($email_val); ?>" style="font-size: 0.72rem; color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;">
                      <i class="fa-solid fa-envelope" style="color: #94a3b8; font-size: 0.65rem;"></i>
                      <span><?php echo htmlspecialchars($email_val); ?></span>
                    </a>
                  </div>
                <?php endif; ?>
              </td>

              <!-- 7. Status & Active/Pause Toggle -->
              <td>
                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                  <?php if ($sm['user_status'] === 'active'): ?>
                    <span class="sid-status-active" title="Active • Live in website faculty section">
                      <i class="fa-solid fa-circle-check" style="font-size: 0.65rem;"></i> Active
                    </span>
                  <?php else: ?>
                    <span class="sid-status-inactive" style="background: rgba(217, 119, 6, 0.12); color: #d97706; border: 1px solid rgba(217, 119, 6, 0.3); border-radius: 20px; padding: 2px 8px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Inactive • Hidden from website">
                      <i class="fa-solid fa-circle-pause" style="font-size: 0.65rem;"></i> Inactive
                    </span>
                  <?php endif; ?>

                  <?php if ($current_user['role'] === 'super_admin'): ?>
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/staff" style="margin: 0;">
                      <input type="hidden" name="action" value="toggle_staff_status">
                      <input type="hidden" name="staff_id" value="<?php echo $sm['id']; ?>">
                      <button type="submit" style="background: none; border: none; padding: 0; font-size: 0.70rem; color: <?php echo $sm['user_status'] === 'active' ? '#d97706' : '#059669'; ?>; cursor: pointer; text-decoration: underline; font-weight: 600;">
                        <?php echo $sm['user_status'] === 'active' ? 'Pause' : 'Activate'; ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>

              <!-- 8. Login Actions (Login Direct & Change Password) -->
              <td>
                <div class="sid-login-wrap">
                  <a href="<?php echo ADMIN_URL; ?>/staff?action=direct_login&staff_id=<?php echo $sm['id']; ?>" target="_blank" class="btn-student-login" title="Login Directly as <?php echo htmlspecialchars($sm['full_name']); ?> in a new tab">
                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.7rem;"></i>
                    <span>Login Direct</span>
                  </a>

                  <button type="button" class="btn-student-pwd" title="Change Password for <?php echo htmlspecialchars($sm['full_name']); ?>" onclick='openChangeStaffPasswordModal(<?php echo htmlspecialchars(json_encode($sm), ENT_QUOTES, "UTF-8"); ?>)'>
                    <i class="fa-solid fa-key" style="font-size: 0.7rem;"></i>
                    <span>Change Password</span>
                  </button>
                </div>
              </td>

              <!-- 9. Actions (View Profile, Edit, Delete) -->
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 5px; justify-content: flex-end; align-items: center;">
                  
                  <!-- Eye Icon: View Profile -->
                  <button type="button" class="sid-action-btn view" title="View Faculty Profile" onclick='viewStaffDetails(<?php echo htmlspecialchars(json_encode($sm), ENT_QUOTES, "UTF-8"); ?>)'>
                    <i class="fa-solid fa-eye"></i>
                  </button>

                  <!-- Pen Icon: Edit Profile -->
                  <button type="button" class="sid-action-btn edit" title="Edit Faculty Profile" onclick='openEditStaffModal(<?php echo htmlspecialchars(json_encode($sm), ENT_QUOTES, "UTF-8"); ?>)'>
                    <i class="fa-solid fa-pencil"></i>
                  </button>

                  <!-- Trash Icon: Delete Profile -->
                  <?php if ($current_user['role'] === 'super_admin'): ?>
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/staff" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars(addslashes($sm['full_name'])); ?> and their user credentials?');">
                      <input type="hidden" name="action" value="delete_staff">
                      <input type="hidden" name="staff_id" value="<?php echo $sm['id']; ?>">
                      <button type="submit" class="sid-action-btn delete" title="Delete Faculty Record">
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

    <form method="POST" action="<?php echo ADMIN_URL; ?>/staff" enctype="multipart/form-data">
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

        <!-- Specialization & Experience for Faculty Website Card -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Specialization / Subject Focus</label>
            <input type="text" name="specialization" placeholder="e.g. Organic &amp; Physical Chemistry" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Teaching Experience</label>
            <input type="text" name="experience" placeholder="e.g. 10+ Years" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
        </div>

        <!-- Frontend Display Sequence & Website Publishing Status -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Website Sort Order (#1 shows first)</label>
            <input type="number" name="sort_order" value="<?php echo count($staff_members) + 1; ?>" min="0" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Website Status</label>
            <select name="user_status" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
              <option value="active">Active (Visible on Homepage &amp; Faculty page)</option>
              <option value="inactive">Inactive (Hidden from public website)</option>
            </select>
          </div>
        </div>

        <!-- Faculty Portrait Photo Upload -->
        <div class="form-group" style="margin-bottom: 16px; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 10px; padding: 12px 14px;">
          <label style="display: block; font-size: 12px; font-weight: 800; margin-bottom: 4px; color: #166534;">
            <i class="fa-solid fa-camera"></i> Faculty Portrait Photo (Optional)
          </label>
          <input type="file" name="photo_file" accept="image/*" class="hrms-input" style="width: 100%; padding: 6px 8px; font-size: 12px; border: 1px dashed #16a34a; border-radius: 6px; background: #fff;">
          <span style="font-size: 11px; color: #15803d; display: block; margin-top: 3px;">Upload portrait photo for website faculty card. Stored in <code>assets/imgs/faculties/</code>.</span>
        </div>

        <!-- Key Credential Highlight -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Key Highlight / Credential Badge</label>
          <input type="text" name="highlight" placeholder="e.g. 15 Years Experience • Master of Organic Chemistry" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
        </div>

        <!-- Bio / Description -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Faculty Bio / Teaching Methodology</label>
          <textarea name="bio" rows="2" class="hrms-input" placeholder="A brief profile explaining teaching philosophy, achievements and student mentorship approach..." style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
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

      <div class="hrms-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
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
      <div class="view-staff-banner" style="display: flex; align-items: center; gap: 18px; padding: 18px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd; border-radius: 14px; margin-bottom: 20px;">
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
          <div class="view-staff-meta" style="display: flex; flex-wrap: wrap; gap: 6px; font-size: 0.75rem;">
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
    <div class="hrms-modal-footer" style="display: flex; justify-content: flex-end; align-items: center; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
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

    <form method="POST" action="<?php echo ADMIN_URL; ?>/staff" enctype="multipart/form-data">
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

        <!-- Row 3b: Specialization & Experience for Faculty Website Card -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Specialization / Subject Focus</label>
            <input type="text" name="specialization" id="edit_specialization" placeholder="e.g. Physics &amp; Mathematics" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Teaching Experience</label>
            <input type="text" name="experience" id="edit_experience" placeholder="e.g. 10+ Years" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
        </div>

        <!-- Row 3c: Website Sort Order & Status -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Website Sort Order (#1 shows first)</label>
            <input type="number" name="sort_order" id="edit_sort_order" min="0" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
          </div>
          <div class="form-group">
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Website Publishing Status</label>
            <select name="user_status" id="edit_user_status" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
              <option value="active">Active (Visible on Homepage &amp; Faculty page)</option>
              <option value="inactive">Inactive (Hidden from public website)</option>
            </select>
          </div>
        </div>

        <!-- Row 3d: Faculty Portrait Photo Preview & Replace -->
        <div class="form-group" style="margin-bottom: 16px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px 14px;">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
            Faculty Portrait Photo
          </label>
          <div style="display: flex; gap: 14px; align-items: center;">
            <div style="width: 50px; height: 65px; border-radius: 8px; overflow: hidden; background: #0f172a; flex-shrink: 0; border: 1px solid #cbd5e1;">
              <img id="edit_photo_preview" src="/assets/imgs/ksv-logo.jpg" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div style="flex: 1;">
              <input type="file" name="photo_file" accept="image/*" class="hrms-input" style="width: 100%; padding: 6px 8px; font-size: 12px; border: 1px dashed #0284c7; border-radius: 6px; background: #fff;">
              <span style="font-size: 11px; color: #64748b; display: block; margin-top: 3px;">Select a new portrait image file to replace current photo.</span>
            </div>
          </div>
        </div>

        <!-- Row 3e: Credential Highlight & Bio -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Key Highlight / Credential Badge</label>
          <input type="text" name="highlight" id="edit_highlight" placeholder="e.g. 10+ Years Experience • NEET, IIT-JEE Specialist" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Faculty Bio / Teaching Methodology</label>
          <textarea name="bio" id="edit_bio" rows="2" class="hrms-input" placeholder="A brief profile explaining teaching philosophy, achievements and student mentorship approach..." style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
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

        <!-- Row 7: Menu Permissions (Role Access Control) -->
        <div class="form-group" style="margin-top: 18px; margin-bottom: 0;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
            <div>
              <label style="display: flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 2px; font-family: 'Outfit', sans-serif;">
                <i class="fa-solid fa-user-shield" style="color: #0284c7;"></i>
                Menu Access Permissions
              </label>
              <div style="font-size: 11.5px; color: #64748b;">
                When this staff member logs in, they will only have access to the menus checked below.
              </div>
            </div>
            <div style="display: flex; gap: 6px; margin-top: 2px;">
              <button type="button" class="btn-perm-toggle" onclick="toggleAllStaffPerms(true)">Select All</button>
              <button type="button" class="btn-perm-toggle" onclick="toggleAllStaffPerms(false)">Deselect All</button>
            </div>
          </div>

          <div class="perm-box-container">
            <div class="perm-section-label">Faculty Portal Menus</div>
            <div class="perm-grid" style="margin-bottom: 12px;">
              
              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="dashboard" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-gauge-high"></i>
                  <span>My Dashboard</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="myself-attendance" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-calendar-check"></i>
                  <span>MySelf Attendance</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="staff-id" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-id-card"></i>
                  <span>My Staff ID Card</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="staff-periods" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-clock-rotate-left"></i>
                  <span>Staff Periods</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="leaves" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-plane-departure"></i>
                  <span>Apply for Leave</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="payroll" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-file-invoice-dollar"></i>
                  <span>Salary Slips</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="announcements" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-bullhorn"></i>
                  <span>Noticeboard</span>
                </span>
              </label>

            </div>

            <div class="perm-section-label" style="border-top: 1px dashed #cbd5e1; padding-top: 10px; margin-top: 10px;">
              Students &amp; Academics Access (Optional)
            </div>
            <div class="perm-grid" style="margin-bottom: 12px;">
              
              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_students" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-user-graduate"></i>
                  <span>Student Roaster</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_student_attendance" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-qrcode"></i>
                  <span>Attendance &amp; QR Scan</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_fees" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-receipt"></i>
                  <span>Fee Management</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_student_id" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-id-badge"></i>
                  <span>Student ID Card</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_marksheet" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-file-lines"></i>
                  <span>Report Card</span>
                </span>
              </label>

            </div>

            <div class="perm-section-label" style="border-top: 1px dashed #cbd5e1; padding-top: 10px; margin-top: 10px;">
              HR &amp; Operations Access (Optional)
            </div>
            <div class="perm-grid">

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_staff_periods" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-clock-rotate-left"></i>
                  <span>Staff Periods</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_staff_attendance" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-calendar-check"></i>
                  <span>Staff Attendance</span>
                </span>
              </label>

            </div>

            <div class="perm-section-label" style="border-top: 1px dashed #cbd5e1; padding-top: 10px; margin-top: 10px;">
              Expense Management Access (Optional)
            </div>
            <div class="perm-grid">

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_expenses" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-wallet"></i>
                  <span>Expenses List &amp; Add</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_expense_categories" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-tags"></i>
                  <span>Expense Categories</span>
                </span>
              </label>

              <label class="perm-card">
                <input type="checkbox" name="permissions[]" value="admin_expense_report" class="edit-staff-perm-cb">
                <span class="perm-card-text">
                  <i class="fa-solid fa-chart-pie"></i>
                  <span>Expense Report</span>
                </span>
              </label>

            </div>
          </div>
        </div>

      </div>

      <div class="hrms-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
        <button type="button" onclick="closeModal('editStaffModal');" class="btn-hrms btn-hrms-outline">Cancel</button>
        <button type="submit" class="login-submit-btn" style="width: auto; margin-top: 0; padding: 10px 24px;">
          <i class="fa-solid fa-check"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 4: Change Faculty Password (Simple & Sober) -->
<div class="hrms-modal" id="changeStaffPasswordModal">
  <div class="hrms-modal-card" style="max-width: 480px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background: #fefce8; color: #a16207; border: 1px solid #fef08a; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-key"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Change Faculty Password
          </h3>
          <p style="margin: 2px 0 0; font-size: 0.75rem; color: #64748b;">Update faculty member portal authentication credentials</p>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('changeStaffPasswordModal')" aria-label="Close modal">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/staff" id="changeStaffPasswordForm">
      <input type="hidden" name="action" value="change_staff_password">
      <input type="hidden" name="staff_id" id="pwd_staff_id" value="">

      <div class="hrms-modal-body" style="padding: 22px 24px;">
        <!-- Staff Info Chip -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
          <div>
            <div style="font-weight: 700; color: #0f172a; font-size: 0.92rem; font-family: 'Outfit', sans-serif;" id="pwd_staff_name">—</div>
            <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
              Emp ID: <strong style="color: #0284c7;" id="pwd_staff_code">—</strong> &bull; Dept: <span id="pwd_staff_dept">—</span>
            </div>
          </div>
          <span style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; font-family: monospace;" id="pwd_staff_username">@username</span>
        </div>

        <!-- New Password -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
            <span>New Password *</span>
            <span style="font-weight: 500; font-size: 0.72rem; color: #94a3b8;">Min. 4 characters</span>
          </label>
          <div style="position: relative;">
            <input type="password" name="new_password" id="staff_new_password" class="hrms-input" required minlength="4" placeholder="Enter new password" style="width: 100%; padding-right: 40px;">
            <button type="button" onclick="toggleStaffPassword('staff_new_password', this)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px; font-size: 0.85rem;" title="Show/Hide password">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="form-group" style="margin-bottom: 10px;">
          <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
            Confirm New Password *
          </label>
          <div style="position: relative;">
            <input type="password" name="confirm_password" id="staff_confirm_password" class="hrms-input" required minlength="4" placeholder="Re-enter new password" style="width: 100%; padding-right: 40px;">
            <button type="button" onclick="toggleStaffPassword('staff_confirm_password', this)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px; font-size: 0.85rem;" title="Show/Hide password">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <div style="font-size: 0.74rem; color: #64748b; margin-top: 8px; line-height: 1.4;">
          <i class="fa-solid fa-circle-info" style="color: #0284c7;"></i>
          The faculty member can use this password to log in to their faculty portal with their username or employee ID.
        </div>
      </div>

      <div class="hrms-modal-footer" style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
        <button type="button" onclick="closeModal('changeStaffPasswordModal')" class="btn-hrms btn-hrms-outline">Cancel</button>
        <button type="submit" class="roaster-add-btn" style="padding: 8px 20px; font-size: 0.82rem;">
          <i class="fa-solid fa-key"></i> <span>Update Password</span>
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

  const specEl = document.getElementById('edit_specialization');
  if (specEl) specEl.value = sm.specialization || sm.department || '';

  const expEl = document.getElementById('edit_experience');
  if (expEl) expEl.value = sm.experience || '';

  const highEl = document.getElementById('edit_highlight');
  if (highEl) highEl.value = sm.highlight || '';

  const bioEl = document.getElementById('edit_bio');
  if (bioEl) bioEl.value = sm.bio || '';

  const sortEl = document.getElementById('edit_sort_order');
  if (sortEl) sortEl.value = sm.sort_order || 0;

  const statusEl = document.getElementById('edit_user_status');
  if (statusEl) statusEl.value = sm.user_status || 'active';

  const previewEl = document.getElementById('edit_photo_preview');
  if (previewEl) {
    previewEl.src = sm.photo || (sm.avatar ? ('/assets/imgs/faculties/' + sm.avatar) : '/assets/imgs/ksv-logo.jpg');
  }

  // Populate Menu Permissions
  let perms = [];
  if (sm.menu_permissions) {
    try {
      perms = typeof sm.menu_permissions === 'string' ? JSON.parse(sm.menu_permissions) : sm.menu_permissions;
    } catch (e) {
      perms = [];
    }
  } else {
    // Default faculty portal permissions if not customized yet
    perms = [
      'dashboard', 'myself-attendance', 
      'admin_students', 'admin_student_attendance', 'admin_fees', 'admin_student_id', 'admin_marksheet', 
      'staff-id', 'staff-periods', 'leaves', 'payroll', 'announcements',
      'admin_expenses', 'admin_expense_categories', 'admin_expense_report'
    ];
  }
  if (!Array.isArray(perms)) {
    perms = [
      'dashboard', 'myself-attendance', 
      'admin_students', 'admin_student_attendance', 'admin_fees', 'admin_student_id', 'admin_marksheet', 
      'staff-id', 'staff-periods', 'leaves', 'payroll', 'announcements',
      'admin_expenses', 'admin_expense_categories', 'admin_expense_report'
    ];
  }
  document.querySelectorAll('#editStaffModal .edit-staff-perm-cb').forEach(cb => {
    cb.checked = perms.includes(cb.value) || 
      (cb.value === 'admin_students' && perms.includes('students')) ||
      (cb.value === 'admin_student_attendance' && perms.includes('student-attendance')) ||
      (cb.value === 'admin_fees' && perms.includes('fees')) ||
      (cb.value === 'admin_student_id' && perms.includes('student-id')) ||
      (cb.value === 'admin_marksheet' && perms.includes('marksheet')) ||
      (cb.value === 'admin_staff_periods' && perms.includes('staff-periods')) ||
      (cb.value === 'staff-periods' && (perms.includes('admin_staff_periods') || perms.includes('staff-periods'))) ||
      (cb.value === 'admin_expenses' && (perms.includes('expenses') || perms.includes('admin_expenses'))) ||
      (cb.value === 'admin_expense_categories' && (perms.includes('expense-categories') || perms.includes('admin_expense_categories'))) ||
      (cb.value === 'admin_expense_report' && (perms.includes('expense-report') || perms.includes('admin_expense_report')));
  });

  openModal('editStaffModal');
}

function toggleAllStaffPerms(checkAll) {
  document.querySelectorAll('#editStaffModal .edit-staff-perm-cb').forEach(cb => {
    cb.checked = Boolean(checkAll);
  });
}

function editFromViewModal() {
  if (currentViewingStaff) {
    openEditStaffModal(currentViewingStaff);
  }
}

function openChangeStaffPasswordModal(sm) {
  if (!sm) return;
  document.getElementById('pwd_staff_id').value = sm.id || '';
  document.getElementById('pwd_staff_name').textContent = sm.full_name || 'Faculty Member';
  document.getElementById('pwd_staff_code').textContent = sm.employee_code || '—';
  document.getElementById('pwd_staff_dept').textContent = sm.department || '—';
  document.getElementById('pwd_staff_username').textContent = '@' + (sm.username || 'staff');
  document.getElementById('staff_new_password').value = '';
  document.getElementById('staff_confirm_password').value = '';
  openModal('changeStaffPasswordModal');
}

function toggleStaffPassword(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    if (icon) {
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    }
  } else {
    input.type = 'password';
    if (icon) {
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal('addStaffModal');
    closeModal('viewStaffModal');
    closeModal('editStaffModal');
    closeModal('changeStaffPasswordModal');
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
