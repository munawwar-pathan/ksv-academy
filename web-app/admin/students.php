<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
if ($current_user['role'] === 'staff') {
    require_menu_permission('admin_students');
}
$page_title = 'Student Roaster & Profiles';
$page_heading = 'Student Roaster';
$page_subheading = 'Enrolled Scholars, Academic Batches, Admission Profiles & Document Repositories';
$active_menu = 'students';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];

// Filter by search and standard
$search = trim($_GET['search'] ?? '');
$filter_standard = trim($_GET['standard'] ?? '');

// Handle Direct Login as Student (GET request from "Login Direct" button in new tab)
if (isset($_GET['action']) && $_GET['action'] === 'direct_login') {
    $target_student_id = intval($_GET['student_id'] ?? 0);
    if ($target_student_id > 0) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$target_student_id]);
        $target_student = $stmt->fetch();
        if ($target_student) {
            $stu_user = get_or_create_student_user($target_student, $db);

            // Preserve admin session so admin tab is never logged out
            if (!empty($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), ['super_admin', 'staff'])) {
                $_SESSION['admin_user_id'] = $_SESSION['user_id'];
                $_SESSION['admin_username'] = $_SESSION['username'];
                $_SESSION['admin_role'] = $_SESSION['role'];
                $_SESSION['admin_full_name'] = $_SESSION['full_name'];
            }

            // Set student active session
            $_SESSION['user_id'] = $stu_user['id'];
            $_SESSION['username'] = $stu_user['username'];
            $_SESSION['role'] = 'student';
            $_SESSION['full_name'] = $stu_user['full_name'];
            $_SESSION['email'] = $stu_user['email'];
            $_SESSION['avatar'] = !empty($target_student['photo']) ? $target_student['photo'] : $stu_user['avatar'];
            $_SESSION['active_student_id'] = $target_student['id'];
            $_SESSION['impersonated_student'] = true;

            set_flash('success', "Logged in as student " . htmlspecialchars($target_student['student_name']) . " (Roll: " . htmlspecialchars($target_student['roll_no']) . ")!");
            header('Location: ' . PARENT_URL . '/dashboard');
            exit;
        } else {
            set_flash('error', "Student record not found.");
            header('Location: ' . ADMIN_URL . '/students');
            exit;
        }
    }
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Register New Student
    if ($action === 'register_student') {
        $student_name = trim($_POST['student_name'] ?? '');
        $roll_no = trim($_POST['roll_no'] ?? '');
        $admission_no = trim($_POST['admission_no'] ?? '');
        $standard = trim($_POST['standard'] ?? '10th Standard');
        $section = trim($_POST['section'] ?? 'A');
        $batch_timing = trim($_POST['batch_timing'] ?? '07:30 AM - 01:30 PM');
        $parent_name = trim($_POST['parent_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        $parent_phone = trim($_POST['parent_phone'] ?? '');
        $student_phone = trim($_POST['student_phone'] ?? '');
        $student_email = trim($_POST['student_email'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $blood_group = trim($_POST['blood_group'] ?? 'O+');
        $address = trim($_POST['address'] ?? '');
        $total_fees = floatval($_POST['total_fees'] ?? 45000);
        $paid_fees = floatval($_POST['paid_fees'] ?? 0);

        // Upload Profile Picture
        $photo = '/assets/imgs/ksv-logo.jpg';
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $p_tmp = $_FILES['profile_picture']['tmp_name'];
            $p_name = $_FILES['profile_picture']['name'];
            $p_ext = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
            $allowed_img_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
            if (in_array($p_ext, $allowed_img_exts)) {
                $p_dir = __DIR__ . '/../uploads/students';
                if (!is_dir($p_dir)) {
                    mkdir($p_dir, 0777, true);
                }
                $photo_filename = 'stu_' . time() . '_' . rand(1000, 9999) . '.' . $p_ext;
                $dest_photo = $p_dir . '/' . $photo_filename;
                if (move_uploaded_file($p_tmp, $dest_photo)) {
                    $photo = '/web-app/uploads/students/' . $photo_filename;
                }
            }
        }

        // Upload Academic Files (single input choosing multiple files)
        $academic_docs = [];
        if (isset($_FILES['academic_files']) && !empty($_FILES['academic_files']['name'])) {
            $files_array = $_FILES['academic_files'];
            $file_count = is_array($files_array['name']) ? count($files_array['name']) : 0;
            $docs_dir = __DIR__ . '/../uploads/academic_docs';
            if (!is_dir($docs_dir)) {
                mkdir($docs_dir, 0777, true);
            }

            for ($i = 0; $i < $file_count; $i++) {
                if ($files_array['error'][$i] === UPLOAD_ERR_OK) {
                    $orig_name = $files_array['name'][$i];
                    $tmp_doc = $files_array['tmp_name'][$i];
                    $doc_ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                    $safe_doc_name = 'doc_' . time() . '_' . rand(100, 999) . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $orig_name);
                    $dest_doc = $docs_dir . '/' . $safe_doc_name;
                    if (move_uploaded_file($tmp_doc, $dest_doc)) {
                        $academic_docs[] = [
                            'name' => $orig_name,
                            'url' => '/web-app/uploads/academic_docs/' . $safe_doc_name,
                            'ext' => $doc_ext,
                            'size' => filesize($dest_doc),
                            'date' => date('d M Y')
                        ];
                    }
                }
            }
        }
        $academic_files_json = !empty($academic_docs) ? json_encode($academic_docs) : null;

        if (!empty($student_name)) {
            try {
                // Generate roll no if empty
                if (empty($roll_no)) {
                    $next_num = $db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM students")->fetchColumn();
                    $roll_no = 'KSV-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
                }
                if (empty($admission_no)) {
                    $admission_no = 'ADM-' . date('Y') . '-' . rand(100, 999);
                }

                $qr_code = 'STU-' . $roll_no;

                $stmt = $db->prepare("
                    INSERT INTO students (
                        session_id, student_name, roll_no, admission_no, standard, section,
                        batch_timing, parent_name, mother_name, parent_phone, student_phone, student_email,
                        gender, dob, blood_group, address, total_fees, paid_fees, qr_code, attendance_percentage,
                        photo, academic_files
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, 100.00,
                        ?, ?
                    )
                ");
                $stmt->execute([
                    $session_id, $student_name, $roll_no, $admission_no, $standard, $section,
                    $batch_timing, $parent_name, $mother_name, $parent_phone, $student_phone, $student_email,
                    $gender, $dob, $blood_group, $address, $total_fees, $paid_fees, $qr_code,
                    $photo, $academic_files_json
                ]);
                $new_student_id = $db->lastInsertId();

                // Automatically link/create user account for this student
                $stmt_new = $db->prepare("SELECT * FROM students WHERE id = ?");
                $stmt_new->execute([$new_student_id]);
                $new_stu = $stmt_new->fetch();
                if ($new_stu) {
                    get_or_create_student_user($new_stu, $db);
                }

                // If initial fee was paid, record fee payment
                if ($paid_fees > 0) {
                    $rcpt = 'RCPT-' . strtoupper(substr(uniqid(), 7));
                    $stmt_fee = $db->prepare("
                        INSERT INTO fee_payments (session_id, student_id, receipt_no, amount, payment_method, payment_date, fee_type, remarks)
                        VALUES (?, ?, ?, ?, 'Cash', CURDATE(), 'Admission Fee', 'Initial fee payment during registration')
                    ");
                    $stmt_fee->execute([$session_id, $new_student_id, $rcpt, $paid_fees]);
                }

                set_flash('success', "Student {$student_name} (Roll: {$roll_no}) added to Student Roaster successfully!");
                header('Location: ' . ADMIN_URL . '/students');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Registration error: ' . $e->getMessage());
            }
        }
    }

    // 2. Update Student Profile
    if ($action === 'update_student') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $student_name = trim($_POST['student_name'] ?? '');
        $standard = trim($_POST['standard'] ?? '');
        $section = trim($_POST['section'] ?? 'A');
        $batch_timing = trim($_POST['batch_timing'] ?? '07:30 AM - 01:30 PM');
        $roll_no = trim($_POST['roll_no'] ?? '');
        $admission_no = trim($_POST['admission_no'] ?? '');
        $parent_name = trim($_POST['parent_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        $parent_phone = trim($_POST['parent_phone'] ?? '');
        $student_phone = trim($_POST['student_phone'] ?? '');
        $student_email = trim($_POST['student_email'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $dob = !empty($_POST['dob']) ? trim($_POST['dob']) : null;
        $blood_group = trim($_POST['blood_group'] ?? 'O+');
        $address = trim($_POST['address'] ?? '');
        $total_fees = floatval($_POST['total_fees'] ?? 0);
        $paid_fees = floatval($_POST['paid_fees'] ?? 0);

        if ($student_id > 0 && !empty($student_name)) {
            try {
                // Fetch current student record
                $curr_stmt = $db->prepare("SELECT photo, academic_files FROM students WHERE id = ?");
                $curr_stmt->execute([$student_id]);
                $curr_stu = $curr_stmt->fetch(PDO::FETCH_ASSOC);

                $photo = $curr_stu['photo'] ?? '/assets/imgs/ksv-logo.jpg';
                $docs_list = [];
                if (!empty($curr_stu['academic_files'])) {
                    $decoded = json_decode($curr_stu['academic_files'], true);
                    if (is_array($decoded)) {
                        $docs_list = $decoded;
                    }
                }

                // Check if new profile photo was uploaded
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $p_tmp = $_FILES['profile_picture']['tmp_name'];
                    $p_name = $_FILES['profile_picture']['name'];
                    $p_ext = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
                    $allowed_img_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
                    if (in_array($p_ext, $allowed_img_exts)) {
                        $p_dir = __DIR__ . '/../uploads/students';
                        if (!is_dir($p_dir)) {
                            mkdir($p_dir, 0777, true);
                        }
                        $photo_filename = 'stu_' . time() . '_' . rand(1000, 9999) . '.' . $p_ext;
                        $dest_photo = $p_dir . '/' . $photo_filename;
                        if (move_uploaded_file($p_tmp, $dest_photo)) {
                            $photo = '/web-app/uploads/students/' . $photo_filename;
                        }
                    }
                }

                // Check if new academic files are attached
                if (isset($_FILES['academic_files']) && !empty($_FILES['academic_files']['name'])) {
                    $files_array = $_FILES['academic_files'];
                    $file_count = is_array($files_array['name']) ? count($files_array['name']) : 0;
                    $docs_dir = __DIR__ . '/../uploads/academic_docs';
                    if (!is_dir($docs_dir)) {
                        mkdir($docs_dir, 0777, true);
                    }

                    for ($i = 0; $i < $file_count; $i++) {
                        if ($files_array['error'][$i] === UPLOAD_ERR_OK) {
                            $orig_name = $files_array['name'][$i];
                            $tmp_doc = $files_array['tmp_name'][$i];
                            $doc_ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                            $safe_doc_name = 'doc_' . time() . '_' . rand(100, 999) . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $orig_name);
                            $dest_doc = $docs_dir . '/' . $safe_doc_name;
                            if (move_uploaded_file($tmp_doc, $dest_doc)) {
                                $docs_list[] = [
                                    'name' => $orig_name,
                                    'url' => '/web-app/uploads/academic_docs/' . $safe_doc_name,
                                    'ext' => $doc_ext,
                                    'size' => filesize($dest_doc),
                                    'date' => date('d M Y')
                                ];
                            }
                        }
                    }
                }
                $academic_files_json = !empty($docs_list) ? json_encode($docs_list) : null;

                $stmt = $db->prepare("
                    UPDATE students SET
                        student_name = ?, roll_no = ?, admission_no = ?, standard = ?, section = ?,
                        batch_timing = ?, parent_name = ?, mother_name = ?, parent_phone = ?, student_phone = ?, student_email = ?, gender = ?,
                        dob = ?, blood_group = ?, address = ?, total_fees = ?, paid_fees = ?,
                        photo = ?, academic_files = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $student_name, $roll_no, $admission_no, $standard, $section,
                    $batch_timing, $parent_name, $mother_name, $parent_phone, $student_phone, $student_email, $gender,
                    $dob, $blood_group, $address, $total_fees, $paid_fees,
                    $photo, $academic_files_json,
                    $student_id
                ]);

                set_flash('success', "Student profile for {$student_name} updated successfully!");
                header('Location: ' . ADMIN_URL . '/students');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Update error: ' . $e->getMessage());
            }
        }
    }

    // 3. Delete Student
    if ($action === 'delete_student') {
        $del_id = intval($_POST['student_id'] ?? 0);
        if ($del_id > 0) {
            $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([$del_id]);
            set_flash('success', "Student record deleted from roaster.");
            header('Location: ' . ADMIN_URL . '/students');
            exit;
        }
    }

    // 4. Change Student Password
    if ($action === 'change_student_password') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (empty($new_password) || empty($confirm_password)) {
            set_flash('error', 'Please enter and confirm the new password.');
        } elseif ($new_password !== $confirm_password) {
            set_flash('error', 'New password and confirm password do not match.');
        } elseif (strlen($new_password) < 4) {
            set_flash('error', 'New password must be at least 4 characters long.');
        } else {
            $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
            $stmt->execute([$student_id]);
            $stu = $stmt->fetch();
            if ($stu) {
                $stu_user = get_or_create_student_user($stu, $db);
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $stu_user['id']]);
                set_flash('success', "Password for student " . htmlspecialchars($stu['student_name']) . " (Roll: " . htmlspecialchars($stu['roll_no']) . ") updated successfully!");
            } else {
                set_flash('error', 'Student record not found.');
            }
        }
        header('Location: ' . ADMIN_URL . '/students');
        exit;
    }
}

// Fetch roaster metrics for active session
$stats_res = $db->query("
    SELECT 
        COUNT(*) as total_students,
        COALESCE(SUM(total_fees), 0) as total_fees,
        COALESCE(SUM(paid_fees), 0) as paid_fees,
        COALESCE(AVG(attendance_percentage), 0) as avg_attendance
    FROM students 
    WHERE session_id = {$session_id}
")->fetch(PDO::FETCH_ASSOC);

$total_stu_count = intval($stats_res['total_students'] ?? 0);
$total_fees_amount = floatval($stats_res['total_fees'] ?? 0);
$total_paid_amount = floatval($stats_res['paid_fees'] ?? 0);
$total_due_amount = max(0, $total_fees_amount - $total_paid_amount);
$avg_att_pct = round(floatval($stats_res['avg_attendance'] ?? 0), 1);
$fee_collection_pct = $total_fees_amount > 0 ? round(($total_paid_amount / $total_fees_amount) * 100) : 0;

// Pagination & limits
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Build WHERE filter conditions
$where_clauses = ["session_id = ?"];
$where_params = [$session_id];

if (!empty($search)) {
    $where_clauses[] = "(student_name LIKE ? OR roll_no LIKE ? OR admission_no LIKE ? OR parent_phone LIKE ? OR student_phone LIKE ? OR student_email LIKE ?)";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
}

if (!empty($filter_standard)) {
    $where_clauses[] = "standard = ?";
    $where_params[] = $filter_standard;
}

$where_sql = implode(' AND ', $where_clauses);

// Count total matching scholars
$count_stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE {$where_sql}");
$count_stmt->execute($where_params);
$total_records = intval($count_stmt->fetchColumn());

// Calculate total pages & offset
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Query scholars for current page
$query = "SELECT s.*, (SELECT username FROM users WHERE id = s.user_id LIMIT 1) as user_username FROM students s WHERE {$where_sql} ORDER BY s.id DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);
$bind_idx = 1;
foreach ($where_params as $val) {
    $stmt->bindValue($bind_idx++, $val);
}
$stmt->bindValue($bind_idx++, $limit, PDO::PARAM_INT);
$stmt->bindValue($bind_idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination indicators
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Helper for generating pagination links
function get_student_pagination_url($target_page, $search, $filter_standard, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_standard)) $params['standard'] = $filter_standard;
    if ($limit != 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return ADMIN_URL . '/students' . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Standards list for filter
$standards = ['8th Standard', '9th Standard', '10th Standard', '11th Science', '11th Commerce', '12th Science', '12th Commerce'];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Student Roaster Table & Badge Styles (Simple & Sober - Matching student-id) ── */
.student-id-table {
  width: 100%;
  min-width: 1260px;
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

/* Fees Badges */
.sid-fee-due {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.70rem;
  font-weight: 700;
  color: #dc2626;
  background: #fef2f2;
  border: 1px solid #fecaca;
  padding: 1px 7px;
  border-radius: 4px;
  white-space: nowrap;
}
.sid-fee-paid {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.70rem;
  font-weight: 700;
  color: #166534;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  padding: 1px 7px;
  border-radius: 4px;
  white-space: nowrap;
}

/* Attendance Chips */
.sid-att-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 0.74rem;
  font-weight: 600;
  white-space: nowrap;
}
.sid-att-chip.high {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}
.sid-att-chip.medium {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #b45309;
}
.sid-att-chip.low {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}
.sid-att-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
}
.sid-att-dot.high { background: #16a34a; }
.sid-att-dot.medium { background: #d97706; }
.sid-att-dot.low { background: #dc2626; }

/* Academic Docs button */
.sid-docs-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 3px 8px;
  border-radius: 5px;
  font-size: 0.74rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}
.sid-docs-chip:hover {
  background: #f0f9ff;
  border-color: #bae6fd;
  color: #0284c7;
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
</style>

<!-- KPI Summary Strip for Student Roaster -->
<div class="stat-grid" style="margin-bottom: 22px;">
  
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Enrolled in Roaster</span>
          <span class="stat-number"><?php echo $total_stu_count; ?></span>
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
      <span style="color:#059669; font-weight:700;"><i class="fa-solid fa-check"></i> Active</span>
    </div>
  </div>

  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Fee Revenue Collected</span>
          <span class="stat-number"><?php echo format_inr($total_paid_amount); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $fee_collection_pct; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $fee_collection_pct; ?>%</strong> of <?php echo format_inr($total_fees_amount); ?></span>
      <span style="color:#d97706; font-weight:700;">Due: <?php echo format_inr($total_due_amount); ?></span>
    </div>
  </div>

  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Average Attendance</span>
          <span class="stat-number"><?php echo $avg_att_pct; ?>%</span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-clipboard-user"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $avg_att_pct; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Calculated across all batches</span>
      <span style="color:#059669; font-weight:700;">Good Standing</span>
    </div>
  </div>

  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Academic Standards</span>
          <span class="stat-number"><?php echo count($standards); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-school"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Middle, Secondary &amp; Senior Batches</span>
      <span style="color:#2563eb; font-weight:700;">CBSE Curriculum</span>
    </div>
  </div>

</div>

<!-- Modern Enhanced Filter Toolbar & Add Student Button -->
<div class="roaster-toolbar-card">
  <div class="roaster-toolbar-inner">
    
    <!-- Filter form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/students" class="roaster-filter-form">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">

      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, roll no, phone..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo ADMIN_URL; ?>/students<?php echo !empty($filter_standard) ? '?standard=' . urlencode($filter_standard) . '&limit=' . $limit : ($limit !== 10 ? '?limit=' . $limit : ''); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <div class="roaster-select-box">
        <i class="fa-solid fa-filter"></i>
        <select name="standard" onchange="this.form.submit()">
          <option value="">All Academic Standards</option>
          <?php foreach ($standards as $std): ?>
            <option value="<?php echo $std; ?>" <?php echo $filter_standard === $std ? 'selected' : ''; ?>>
              <?php echo $std; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <button type="submit" class="roaster-btn secondary">
        <i class="fa-solid fa-sliders"></i> Filter
      </button>

      <?php if (!empty($search) || !empty($filter_standard)): ?>
        <a href="<?php echo ADMIN_URL; ?>/students<?php echo $limit !== 10 ? '?limit=' . $limit : ''; ?>" class="roaster-btn ghost" title="Reset all filters">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Scholars Live Count & Add Student Button -->
    <div class="roaster-toolbar-actions">
      <div class="scholars-count-badge">
        <span class="count-dot"></span>
        <span><strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'Scholar' : 'Scholars'; ?></span>
      </div>

      <button type="button" class="roaster-add-btn" onclick="openModal('registerStudentModal')">
        <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
        <span>Add Student</span>
      </button>
    </div>

  </div>
</div>

<!-- Student Roaster Table (Simple & Sober - Like student-id) -->
<div class="hrms-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
  <div class="hrms-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-users" style="color: #0284c7;"></i>
        Student Roaster
        <span style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Comprehensive student roster with profile pictures, academic records &amp; fee status.
      </p>
    </div>
    <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
      <span>Total Records: <strong style="color: #0f172a;"><?php echo $total_records; ?></strong></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="hrms-table student-id-table">
      <thead>
        <tr>
          <th style="min-width: 200px;">Student Profile</th>
          <th style="min-width: 150px;">Roll &amp; Admission</th>
          <th style="min-width: 130px;">Standard &amp; Section</th>
          <th style="min-width: 130px;">Guardian Info</th>
          <th style="min-width: 140px;">Fees Status</th>
          <th style="min-width: 100px;">Attendance</th>
          <th style="min-width: 95px;">Academic Docs</th>
          <th style="min-width: 220px;">Login</th>
          <th style="min-width: 135px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-user-slash"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Scholars Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                No student records in Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong> matched your query.
              </p>
              <button type="button" class="roaster-add-btn" onclick="openModal('registerStudentModal')" style="display: inline-flex;">
                <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
                <span>Add First Student</span>
              </button>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($students as $stu): ?>
            <?php 
              $total_f = floatval($stu['total_fees']);
              $paid_f = floatval($stu['paid_fees']);
              $due = max(0, $total_f - $paid_f);
              $fee_pct = $total_f > 0 ? min(100, round(($paid_f / $total_f) * 100)) : 100;
              $pct = floatval($stu['attendance_percentage']);
              $docs = !empty($stu['academic_files']) ? json_decode($stu['academic_files'], true) : [];
              $has_photo = !empty($stu['photo']) && $stu['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $stu['photo']);
              $first_letter = strtoupper(substr($stu['student_name'], 0, 1));
            ?>
            <tr>
              <!-- 1. Student Profile (Simple & Sober circular avatar + metadata) -->
              <td>
                <div class="user-cell" style="display: flex; align-items: center; gap: 12px;">
                  <?php if ($has_photo): ?>
                    <img src="<?php echo htmlspecialchars($stu['photo']); ?>" alt="<?php echo htmlspecialchars($stu['student_name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
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
                      <?php echo htmlspecialchars($stu['student_name']); ?>
                    </div>
                    <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 6px;">
                      <span><?php echo htmlspecialchars($stu['gender'] ?? 'Scholar'); ?></span>
                      <span>&bull;</span>
                      <span class="sid-blood-badge">Blood: <?php echo htmlspecialchars($stu['blood_group'] ?? 'O+'); ?></span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 2. Roll & Admission -->
              <td style="white-space: nowrap;">
                <span class="sid-roll-pill">
                  <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
                  <span><?php echo htmlspecialchars($stu['roll_no']); ?></span>
                </span>
                <div style="font-size: 0.74rem; color: #64748b; margin-top: 4px; white-space: nowrap;">
                  Adm: <?php echo htmlspecialchars($stu['admission_no'] ?? '—'); ?>
                </div>
              </td>

              <!-- 3. Standard & Section -->
              <td>
                <span class="sid-std-pill">
                  <i class="fa-solid fa-graduation-cap" style="font-size: 0.65rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($stu['standard']); ?></span>
                </span>
                <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;">
                  Section <?php echo htmlspecialchars($stu['section'] ?? 'A'); ?>
                </div>
              </td>

              <!-- 4. Guardian Info -->
              <td>
                <div style="font-size: 0.78rem; font-weight: 600; color: #334155;">
                  <?php echo htmlspecialchars($stu['parent_name'] ?? '—'); ?>
                </div>
                <div style="font-size: 0.74rem; color: #64748b; margin-top: 3px; display: inline-flex; align-items: center; gap: 5px;">
                  <i class="fa-solid fa-phone" style="font-size: 0.65rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($stu['parent_phone'] ?? '—'); ?></span>
                </div>
              </td>

              <!-- 5. Fees (Paid / Total) with sober micro progress bar -->
              <td>
                <div style="font-weight: 700; color: #0f172a; font-size: 0.85rem; font-family: 'Outfit', sans-serif;">
                  <span><?php echo format_inr($paid_f); ?></span>
                  <span style="font-size: 0.74rem; color: #64748b; font-weight: 500;">/ <?php echo format_inr($total_f); ?></span>
                </div>
                
                <div style="width: 80px; height: 4px; background: #e2e8f0; border-radius: 2px; margin: 5px 0 4px; overflow: hidden;" title="<?php echo $fee_pct; ?>% Fee Paid">
                  <div style="height: 100%; border-radius: 2px; width: <?php echo $fee_pct; ?>%; background: #10b981;"></div>
                </div>

                <?php if ($due > 0): ?>
                  <span class="sid-fee-due">
                    Due: <?php echo format_inr($due); ?>
                  </span>
                <?php else: ?>
                  <span class="sid-fee-paid">
                    <i class="fa-solid fa-check" style="font-size: 0.65rem;"></i> Fully Paid
                  </span>
                <?php endif; ?>
              </td>

              <!-- 6. Attendance with subtle status chip -->
              <td>
                <?php 
                  $att_status = $pct >= 85 ? 'high' : ($pct >= 75 ? 'medium' : 'low');
                ?>
                <span class="sid-att-chip <?php echo $att_status; ?>">
                  <span class="sid-att-dot <?php echo $att_status; ?>"></span>
                  <span><?php echo number_format($pct, 1); ?>%</span>
                </span>
              </td>

              <!-- 7. Academic Docs -->
              <td>
                <?php if (!empty($docs) && is_array($docs)): ?>
                  <button type="button" class="sid-docs-chip" onclick='showStudentDocs(<?php echo json_encode(htmlspecialchars($stu['student_name'])); ?>, <?php echo json_encode($docs); ?>)' title="View attached documents">
                    <i class="fa-solid fa-paperclip" style="font-size: 0.68rem;"></i>
                    <span><?php echo count($docs); ?> <?php echo count($docs) === 1 ? 'Doc' : 'Docs'; ?></span>
                  </button>
                <?php else: ?>
                  <span style="font-size: 0.74rem; color: #94a3b8; font-weight: 500;">None</span>
                <?php endif; ?>
              </td>

              <!-- 8. Login Actions (Login Direct & Change Password) -->
              <td>
                <div class="sid-login-wrap">
                  <a href="<?php echo ADMIN_URL; ?>/students?action=direct_login&student_id=<?php echo $stu['id']; ?>" target="_blank" class="btn-student-login" title="Login Directly as <?php echo htmlspecialchars($stu['student_name']); ?> in a new tab">
                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.7rem;"></i>
                    <span>Login Direct</span>
                  </a>

                  <button type="button" class="btn-student-pwd" title="Change Password for <?php echo htmlspecialchars($stu['student_name']); ?>" onclick='openChangePasswordModal(<?php echo htmlspecialchars(json_encode($stu), ENT_QUOTES, "UTF-8"); ?>)'>
                    <i class="fa-solid fa-key" style="font-size: 0.7rem;"></i>
                    <span>Change Password</span>
                  </button>
                </div>
              </td>

              <!-- 9. Actions (View Profile, Edit, Delete) -->
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 5px; justify-content: flex-end; align-items: center;">
                  
                  <!-- Eye Icon: View Profile -->
                  <button type="button" class="sid-action-btn view" title="View Student Profile" onclick='viewStudentDetails(<?php echo htmlspecialchars(json_encode($stu), ENT_QUOTES, "UTF-8"); ?>)'>
                    <i class="fa-solid fa-eye"></i>
                  </button>

                  <!-- Pencil Icon: Edit Profile -->
                  <button type="button" class="sid-action-btn edit" title="Edit Student Profile" onclick='openEditStudentModal(<?php echo htmlspecialchars(json_encode($stu), ENT_QUOTES, "UTF-8"); ?>)'>
                    <i class="fa-solid fa-pencil"></i>
                  </button>

                  <!-- Trash Icon: Delete Profile -->
                  <form method="POST" action="<?php echo ADMIN_URL; ?>/students" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars(addslashes($stu['student_name'])); ?> from the student roaster?');">
                    <input type="hidden" name="action" value="delete_student">
                    <input type="hidden" name="student_id" value="<?php echo $stu['id']; ?>">
                    <button type="submit" class="sid-action-btn delete" title="Delete Student Record">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>

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
            <option value="<?php echo htmlspecialchars(get_student_pagination_url(1, $search, $filter_standard, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Student Roaster Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_student_pagination_url($page - 1, $search, $filter_standard, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_student_pagination_url($p, $search, $filter_standard, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_student_pagination_url($page + 1, $search, $filter_standard, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Attendance Legend -->
    <div class="roaster-table-legend">
      <span style="font-weight: 700; color: #475569; font-size: 0.74rem;">Attendance:</span>
      <div class="legend-item"><span class="legend-dot green"></span> &ge;85% High</div>
      <div class="legend-item"><span class="legend-dot amber"></span> 75-84% Moderate</div>
      <div class="legend-item"><span class="legend-dot red"></span> &lt;75% Low</div>
    </div>

  </div>
</div>

<!-- Modal 1: Add Student Form (With Profile Picture & Multiple Academic Files) -->
<div class="hrms-modal" id="registerStudentModal">
  <div class="hrms-modal-card" style="max-width: 720px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px;">
      <div>
        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-user-plus" style="color: var(--hrms-primary);"></i> Add Student to Roaster
        </h3>
        <p style="margin: 2px 0 0; font-size: 0.78rem; color: #64748b;">
          Academic Year Session: <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>
        </p>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('registerStudentModal')" aria-label="Close modal">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/students" enctype="multipart/form-data">
      <input type="hidden" name="action" value="register_student">

      <div class="hrms-modal-body" style="padding: 22px; max-height: calc(85vh - 140px); overflow-y: auto;">
        
        <!-- Row 1: Student Name & Standard -->
        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Student Full Name *</label>
            <input type="text" name="student_name" class="hrms-input" required placeholder="e.g. Aarav Ramesh Sharma">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Academic Standard / Class *</label>
            <select name="standard" class="hrms-input" required>
              <?php foreach ($standards as $std): ?>
                <option value="<?php echo $std; ?>"><?php echo $std; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Row 2: Student Contact Phone & Student Email Id (Directly below Name & Standard) -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Student Contact Phone</label>
            <input type="text" name="student_phone" class="hrms-input" placeholder="e.g. +91 98765 43210">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Student Email Id</label>
            <input type="email" name="student_email" class="hrms-input" placeholder="e.g. student@ksvacademy.net">
          </div>
        </div>

        <!-- Row 3: Roll Number, Admission No, Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Roll Number (Auto/Custom)</label>
            <input type="text" name="roll_no" class="hrms-input" placeholder="e.g. KSV-1008">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Admission Number</label>
            <input type="text" name="admission_no" class="hrms-input" placeholder="e.g. ADM-2026-081">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Section</label>
            <input type="text" name="section" class="hrms-input" value="A">
          </div>
        </div>

        <!-- Row 4: Gender, DOB, Blood Group -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Gender</label>
            <select name="gender" class="hrms-input">
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" class="hrms-input">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Blood Group</label>
            <select name="blood_group" class="hrms-input">
              <option value="O+">O+</option>
              <option value="A+">A+</option>
              <option value="B+">B+</option>
              <option value="AB+">AB+</option>
              <option value="O-">O-</option>
              <option value="A-">A-</option>
              <option value="B-">B-</option>
              <option value="AB-">AB-</option>
            </select>
          </div>
        </div>

        <!-- Row 5: Parent / Guardian Info -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Father's / Guardian Name *</label>
            <input type="text" name="parent_name" class="hrms-input" required placeholder="e.g. Ramesh Sharma">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Mother's Name</label>
            <input type="text" name="mother_name" class="hrms-input" placeholder="e.g. Sunita Sharma">
          </div>
        </div>

        <!-- Row 6: Parent Phone & Batch Timings -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Parent Contact Phone *</label>
            <input type="text" name="parent_phone" class="hrms-input" required placeholder="e.g. +91 98765 43210">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Batch Timings</label>
            <input type="text" name="batch_timing" class="hrms-input" value="07:30 AM - 01:30 PM">
          </div>
        </div>

        <!-- Row 7: Profile Picture & Multiple Academic Files (USER REQUIRED FIELDS) -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          
          <!-- Field: Profile Picture -->
          <div class="upload-field-card">
            <label class="form-label" style="display: flex; align-items: center; gap: 6px;">
              <i class="fa-solid fa-camera" style="color: var(--hrms-primary);"></i>
              Profile Picture
            </label>
            <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" class="hrms-input" style="padding: 6px; font-size: 0.8rem;">
            <small style="color: #64748b; font-size: 0.72rem; display: block; margin-top: 4px;">
              Passport photo (JPG, PNG, WebP)
            </small>
          </div>

          <!-- Field: Academic File (Single input with multiple files allowed) -->
          <div class="upload-field-card">
            <label class="form-label" style="display: flex; align-items: center; gap: 6px;">
              <i class="fa-solid fa-folder-open" style="color: #10b981;"></i>
              Academic File (Multiple Docs)
            </label>
            <input type="file" name="academic_files[]" id="academicFilesInput" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt" class="hrms-input" style="padding: 6px; font-size: 0.8rem;">
            <small style="color: #64748b; font-size: 0.72rem; display: block; margin-top: 4px;">
              Select multiple: Marksheet, TC, Certificate
            </small>
          </div>

        </div>

        <!-- Row 7: Residential Address -->
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label">Residential Address</label>
          <textarea name="address" rows="2" class="hrms-input" placeholder="Complete residential address..."></textarea>
        </div>

        <!-- Row 8: Fee Breakdown -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 0; background: #f8fafc; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Total Annual Fees (₹) *</label>
            <input type="number" step="0.01" name="total_fees" value="45000" class="hrms-input" required>
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Initial Fee Paid (₹)</label>
            <input type="number" step="0.01" name="paid_fees" value="15000" class="hrms-input">
          </div>
        </div>

      </div>

      <!-- Modal Footer Actions (Fixed like view popup) -->
      <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
        <button type="button" onclick="closeModal('registerStudentModal')" class="btn-hrms btn-hrms-outline">Cancel</button>
        <button type="submit" class="login-submit-btn" style="width: auto; margin-top: 0; padding: 10px 24px;">
          <i class="fa-solid fa-check"></i> Add Student
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 2: View Student Profile -->
<div class="hrms-modal" id="viewStudentModal">
  <div class="hrms-modal-card" style="max-width: 680px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px;">
      <div style="display:flex; align-items:center; gap:12px;">
        <div style="width:38px; height:38px; border-radius:10px; background:rgba(0,124,189,0.1); color:var(--hrms-primary); display:flex; align-items:center; justify-content:center; font-size:1.15rem;">
          <i class="fa-solid fa-id-card"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
            Student Profile Details
          </h3>
          <p style="margin: 2px 0 0; font-size: 0.75rem; color: #64748b;">Comprehensive Academic &amp; Personal Record</p>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('viewStudentModal')" aria-label="Close modal">&times;</button>
    </div>

    <div class="hrms-modal-body" style="padding: 24px; max-height: calc(85vh - 130px); overflow-y: auto;">
      
      <!-- Profile Header Banner -->
      <div style="display:flex; align-items:center; gap:18px; padding:18px; background:linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border:1px solid #bae6fd; border-radius:14px; margin-bottom:20px;">
        <img id="view_stu_photo" src="/assets/imgs/ksv-logo.jpg" alt="Student Photo" style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid #ffffff; box-shadow:0 4px 10px rgba(0,0,0,0.08);">
        <div style="flex:1;">
          <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px;">
            <h4 id="view_stu_name" style="margin:0; font-size:1.25rem; font-weight:800; color:#0f172a; font-family:'Outfit', sans-serif;">—</h4>
            <span id="view_stu_attendance" class="badge-status badge-present">100%</span>
          </div>
          <div style="display:flex; flex-wrap:wrap; gap:6px; font-size:0.75rem;">
            <span style="background:#ffffff; color:#0369a1; padding:3px 9px; border-radius:6px; font-weight:700; border:1px solid #bae6fd;">
              <i class="fa-solid fa-graduation-cap"></i> <span id="view_stu_standard">—</span> (<span id="view_stu_section">—</span>)
            </span>
            <span style="background:#ffffff; color:#475569; padding:3px 9px; border-radius:6px; font-weight:700; border:1px solid #cbd5e1;">
              Roll: <strong id="view_stu_roll" style="color:#0f172a;">—</strong>
            </span>
            <span style="background:#ffffff; color:#475569; padding:3px 9px; border-radius:6px; font-weight:700; border:1px solid #cbd5e1;">
              Adm: <strong id="view_stu_adm" style="color:#0f172a;">—</strong>
            </span>
          </div>
        </div>
      </div>

      <!-- Detail Cards Grid -->
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:18px;">
        
        <!-- Card 1: Academic & Batch -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
          <h5 style="margin:0 0 12px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">
            <i class="fa-solid fa-book-open" style="color:var(--hrms-primary); margin-right:6px;"></i> Academic &amp; Batch
          </h5>
          <div style="display:grid; gap:9px; font-size:0.83rem;">
            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">Class / Standard:</span> <strong id="view_stu_card_standard" style="color:#0f172a;">—</strong></div>
            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">Assigned Section:</span> <strong id="view_stu_card_section" style="color:#0f172a;">—</strong></div>
            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">Batch Timing:</span> <strong id="view_stu_batch" style="color:#0f172a;">—</strong></div>
          </div>
        </div>

        <!-- Card 2: Fee Ledger -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
          <h5 style="margin:0 0 12px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">
            <i class="fa-solid fa-receipt" style="color:#10b981; margin-right:6px;"></i> Fee Ledger Summary
          </h5>
          <div style="display:grid; gap:9px; font-size:0.83rem;">
            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">Total Annual Fee:</span> <strong id="view_stu_total_fees" style="color:#0f172a;">—</strong></div>
            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">Amount Paid:</span> <strong id="view_stu_paid_fees" style="color:#059669;">—</strong></div>
            <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">Pending Balance:</span> <span id="view_stu_due_fees">—</span></div>
          </div>
        </div>

      </div>

      <!-- Card 3: Personal & Contact Information -->
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:18px;">
        <h5 style="margin:0 0 12px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">
          <i class="fa-solid fa-users" style="color:#7c3aed; margin-right:6px;"></i> Guardian &amp; Personal Info
        </h5>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:0.83rem;">
          <div><span style="color:#64748b; font-size:0.75rem;">Father's / Guardian Name</span><strong id="view_stu_parent" style="display:block; color:#0f172a; margin-top:2px;">—</strong></div>
          <div><span style="color:#64748b; font-size:0.75rem;">Mother's Name</span><strong id="view_stu_mother" style="display:block; color:#0f172a; margin-top:2px;">—</strong></div>
          <div><span style="color:#64748b; font-size:0.75rem;">Parent Contact Phone</span><a id="view_stu_phone" href="#" style="display:block; color:var(--hrms-primary); font-weight:700; text-decoration:none; margin-top:2px;">—</a></div>
          <div><span style="color:#64748b; font-size:0.75rem;">Student Contact Phone</span><a id="view_stu_student_phone" href="#" style="display:block; color:var(--hrms-primary); font-weight:700; text-decoration:none; margin-top:2px;">—</a></div>
          <div style="grid-column: span 2;"><span style="color:#64748b; font-size:0.75rem;">Student Email Id</span><a id="view_stu_student_email" href="#" style="display:block; color:var(--hrms-primary); font-weight:700; text-decoration:none; margin-top:2px;">—</a></div>
          <div><span style="color:#64748b; font-size:0.75rem;">Date of Birth</span><strong id="view_stu_dob" style="display:block; color:#0f172a; margin-top:2px;">—</strong></div>
          <div><span style="color:#64748b; font-size:0.75rem;">Gender</span><strong id="view_stu_gender" style="display:block; color:#0f172a; margin-top:2px;">—</strong></div>
          <div><span style="color:#64748b; font-size:0.75rem;">Blood Group</span><strong id="view_stu_blood" style="display:block; color:#0f172a; margin-top:2px;">—</strong></div>
          <div style="grid-column: span 2;"><span style="color:#64748b; font-size:0.75rem;">Residential Address</span><strong id="view_stu_address" style="display:block; color:#0f172a; margin-top:2px;">—</strong></div>
        </div>
      </div>

      <!-- Card 4: Academic Documents Attached -->
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
        <h5 style="margin:0 0 12px; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">
          <i class="fa-solid fa-folder-open" style="color:#f59e0b; margin-right:6px;"></i> Academic Documents &amp; Files
        </h5>
        <div id="view_stu_docs_list"></div>
      </div>

    </div>

    <!-- Modal Footer Actions -->
    <div style="display:flex; justify-content:flex-end; align-items:center; padding:14px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
      <button type="button" onclick="closeModal('viewStudentModal')" class="btn-hrms btn-hrms-outline">Close</button>
    </div>

  </div>
</div>

<!-- Modal 3: Edit Student Profile -->
<div class="hrms-modal" id="editStudentModal">
  <div class="hrms-modal-card" style="max-width: 720px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px;">
      <div>
        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-user-pen" style="color: var(--hrms-primary);"></i> Edit Student Profile
        </h3>
        <p style="margin: 2px 0 0; font-size: 0.78rem; color: #64748b;">
          Modify student details, update profile photo, or append academic documents
        </p>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('editStudentModal')" aria-label="Close modal">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/students" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_student">
      <input type="hidden" name="student_id" id="edit_student_id" value="">

      <div class="hrms-modal-body" style="padding: 22px; max-height: calc(85vh - 130px); overflow-y: auto;">
        
        <!-- Row 1: Student Name & Standard -->
        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Student Full Name *</label>
            <input type="text" name="student_name" id="edit_student_name" class="hrms-input" required>
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Academic Standard / Class *</label>
            <select name="standard" id="edit_standard" class="hrms-input" required>
              <?php foreach ($standards as $std): ?>
                <option value="<?php echo $std; ?>"><?php echo $std; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Row 2: Student Contact Phone & Student Email Id (Directly below Name & Standard) -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Student Contact Phone</label>
            <input type="text" name="student_phone" id="edit_student_phone" class="hrms-input" placeholder="e.g. +91 98765 43210">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Student Email Id</label>
            <input type="email" name="student_email" id="edit_student_email" class="hrms-input" placeholder="e.g. student@ksvacademy.net">
          </div>
        </div>

        <!-- Row 3: Roll Number, Admission No, Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Roll Number</label>
            <input type="text" name="roll_no" id="edit_roll_no" class="hrms-input">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Admission Number</label>
            <input type="text" name="admission_no" id="edit_admission_no" class="hrms-input">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Section</label>
            <input type="text" name="section" id="edit_section" class="hrms-input">
          </div>
        </div>

        <!-- Row 4: Gender, DOB, Blood Group -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Gender</label>
            <select name="gender" id="edit_gender" class="hrms-input">
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" id="edit_dob" class="hrms-input">
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Blood Group</label>
            <select name="blood_group" id="edit_blood_group" class="hrms-input">
              <option value="O+">O+</option>
              <option value="A+">A+</option>
              <option value="B+">B+</option>
              <option value="AB+">AB+</option>
              <option value="O-">O-</option>
              <option value="A-">A-</option>
              <option value="B-">B-</option>
              <option value="AB-">AB-</option>
            </select>
          </div>
        </div>

        <!-- Row 5: Parent / Guardian Info -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Father's / Guardian Name *</label>
            <input type="text" name="parent_name" id="edit_parent_name" class="hrms-input" required>
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Mother's Name</label>
            <input type="text" name="mother_name" id="edit_mother_name" class="hrms-input">
          </div>
        </div>

        <!-- Row 6: Parent Phone & Batch Timings -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Parent Contact Phone *</label>
            <input type="text" name="parent_phone" id="edit_parent_phone" class="hrms-input" required>
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Batch Timings</label>
            <input type="text" name="batch_timing" id="edit_batch_timing" class="hrms-input">
          </div>
        </div>

        <!-- Row 7: Profile Picture & Academic Files -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          
          <!-- Field: Profile Picture with Preview -->
          <div class="upload-field-card">
            <label class="form-label" style="display: flex; align-items: center; justify-content: space-between;">
              <span><i class="fa-solid fa-camera" style="color: var(--hrms-primary);"></i> Profile Picture</span>
            </label>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
              <img id="edit_photo_preview" src="/assets/imgs/ksv-logo.jpg" alt="Current Photo" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #cbd5e1;">
              <span style="font-size: 0.72rem; color: #64748b;">Current photo. Select below to replace.</span>
            </div>
            <input type="file" name="profile_picture" id="editProfilePictureInput" accept="image/*" class="hrms-input" style="padding: 6px; font-size: 0.8rem;">
          </div>

          <!-- Field: Academic File (Multiple Docs) -->
          <div class="upload-field-card">
            <label class="form-label" style="display: flex; align-items: center; justify-content: space-between;">
              <span><i class="fa-solid fa-folder-open" style="color: #10b981;"></i> Academic Files (Add More)</span>
            </label>
            <div style="margin-bottom: 8px;">
              <span id="edit_docs_count" style="font-size: 0.75rem; color: #0284c7; font-weight: 700;">No existing files</span>
            </div>
            <input type="file" name="academic_files[]" id="editAcademicFilesInput" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt" class="hrms-input" style="padding: 6px; font-size: 0.8rem;">
            <small style="color: #64748b; font-size: 0.7rem; display: block; margin-top: 4px;">
              Select multiple files to append to student documents
            </small>
          </div>

        </div>

        <!-- Row 7: Residential Address -->
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label">Residential Address</label>
          <textarea name="address" id="edit_address" rows="2" class="hrms-input"></textarea>
        </div>

        <!-- Row 8: Fee Breakdown -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 0; background: #f8fafc; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0;">
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Total Annual Fees (₹) *</label>
            <input type="number" step="0.01" name="total_fees" id="edit_total_fees" class="hrms-input" required>
          </div>
          <div class="form-group" style="margin: 0;">
            <label class="form-label">Paid Fees (₹)</label>
            <input type="number" step="0.01" name="paid_fees" id="edit_paid_fees" class="hrms-input">
          </div>
        </div>

      </div>

      <!-- Modal Footer Actions (Fixed like view popup) -->
      <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
        <button type="button" onclick="closeModal('editStudentModal')" class="btn-hrms btn-hrms-outline">Cancel</button>
        <button type="submit" class="login-submit-btn" style="width: auto; margin-top: 0; padding: 10px 24px;">
          <i class="fa-solid fa-check"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 4: Academic Documents Viewer -->
<div class="hrms-modal" id="studentDocsModal">
  <div class="hrms-modal-card" style="max-width: 540px;">
    <div class="hrms-modal-header">
      <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-folder-open" style="color: var(--hrms-primary);"></i> Academic Documents
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('studentDocsModal')" aria-label="Close modal">&times;</button>
    </div>
    <div class="hrms-modal-body" style="padding: 20px;">
      <p style="font-size: 0.84rem; color: #64748b; margin-bottom: 14px;">
        Attached academic files for scholar: <strong id="docModalStudentName" style="color:#0f172a;"></strong>
      </p>
      <div id="docModalFilesList"></div>
    </div>
  </div>
</div>

<!-- Modal 5: Change Student Password (Simple & Sober) -->
<div class="hrms-modal" id="changeStudentPasswordModal">
  <div class="hrms-modal-card" style="max-width: 480px;">
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background: #fefce8; color: #a16207; border: 1px solid #fef08a; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-key"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Change Student Password
          </h3>
          <p style="margin: 2px 0 0; font-size: 0.75rem; color: #64748b;">Update student portal authentication credentials</p>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('changeStudentPasswordModal')" aria-label="Close modal">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/students" id="changeStudentPasswordForm">
      <input type="hidden" name="action" value="change_student_password">
      <input type="hidden" name="student_id" id="pwd_modal_student_id" value="">

      <div class="hrms-modal-body" style="padding: 22px 24px;">
        <!-- Student Info Chip -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
          <div>
            <div style="font-weight: 700; color: #0f172a; font-size: 0.92rem; font-family: 'Outfit', sans-serif;" id="pwd_modal_student_name">—</div>
            <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
              Roll: <strong style="color: #0284c7;" id="pwd_modal_roll_no">—</strong> &bull; Standard: <span id="pwd_modal_standard">—</span>
            </div>
          </div>
          <span style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; font-family: monospace;" id="pwd_modal_username">@username</span>
        </div>

        <!-- New Password -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
            <span>New Password *</span>
            <span style="font-weight: 500; font-size: 0.72rem; color: #94a3b8;">Min. 4 characters</span>
          </label>
          <div style="position: relative;">
            <input type="password" name="new_password" id="modal_new_password" class="hrms-input" required minlength="4" placeholder="Enter new password" style="width: 100%; padding-right: 40px;">
            <button type="button" onclick="togglePasswordVisibility('modal_new_password', this)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px; font-size: 0.85rem;" title="Show/Hide password">
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
            <input type="password" name="confirm_password" id="modal_confirm_password" class="hrms-input" required minlength="4" placeholder="Re-enter new password" style="width: 100%; padding-right: 40px;">
            <button type="button" onclick="togglePasswordVisibility('modal_confirm_password', this)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px; font-size: 0.85rem;" title="Show/Hide password">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <div style="font-size: 0.74rem; color: #64748b; margin-top: 8px; line-height: 1.4;">
          <i class="fa-solid fa-circle-info" style="color: #0284c7;"></i>
          The student can use this password to log in at the portal with their username or roll number.
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
        <button type="button" onclick="closeModal('changeStudentPasswordModal')" class="btn-hrms btn-hrms-outline">Cancel</button>
        <button type="submit" class="roaster-add-btn" style="padding: 8px 20px; font-size: 0.82rem;">
          <i class="fa-solid fa-key"></i> <span>Update Password</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
let currentViewingStudent = null;

function escapeHtml(text) {
  if (!text) return '';
  return text.toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const parts = dateStr.split('-');
  if (parts.length === 3) {
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }
  return dateStr;
}

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function viewStudentDetails(stu) {
  currentViewingStudent = stu;
  
  // Basic info
  document.getElementById('view_stu_name').textContent = stu.student_name || '—';
  document.getElementById('view_stu_roll').textContent = stu.roll_no || '—';
  document.getElementById('view_stu_adm').textContent = stu.admission_no || '—';
  document.getElementById('view_stu_standard').textContent = stu.standard || '—';
  document.getElementById('view_stu_card_standard').textContent = stu.standard || '—';
  
  const sec = stu.section ? stu.section : 'A';
  document.getElementById('view_stu_section').textContent = 'Sec ' + sec;
  document.getElementById('view_stu_card_section').textContent = 'Section ' + sec;
  document.getElementById('view_stu_batch').textContent = stu.batch_timing || 'Morning';
  
  // Photo
  const photoEl = document.getElementById('view_stu_photo');
  if (stu.photo && stu.photo.trim() !== '') {
    photoEl.src = stu.photo;
  } else {
    photoEl.src = '/assets/imgs/ksv-logo.jpg';
  }
  photoEl.onerror = function() { this.src = '/assets/imgs/ksv-logo.jpg'; };

  // Personal & Guardian
  document.getElementById('view_stu_gender').textContent = stu.gender || '—';
  document.getElementById('view_stu_dob').textContent = stu.dob ? formatDate(stu.dob) : '—';
  document.getElementById('view_stu_blood').textContent = stu.blood_group || '—';
  document.getElementById('view_stu_parent').textContent = stu.parent_name || '—';
  document.getElementById('view_stu_mother').textContent = stu.mother_name || '—';
  
  const phoneEl = document.getElementById('view_stu_phone');
  phoneEl.textContent = stu.parent_phone || '—';
  if (stu.parent_phone) {
    phoneEl.href = 'tel:' + stu.parent_phone;
  } else {
    phoneEl.removeAttribute('href');
  }

  const stuPhoneEl = document.getElementById('view_stu_student_phone');
  if (stuPhoneEl) {
    stuPhoneEl.textContent = stu.student_phone || '—';
    if (stu.student_phone) {
      stuPhoneEl.href = 'tel:' + stu.student_phone;
    } else {
      stuPhoneEl.removeAttribute('href');
    }
  }

  const stuEmailEl = document.getElementById('view_stu_student_email');
  if (stuEmailEl) {
    stuEmailEl.textContent = stu.student_email || '—';
    if (stu.student_email) {
      stuEmailEl.href = 'mailto:' + stu.student_email;
    } else {
      stuEmailEl.removeAttribute('href');
    }
  }

  document.getElementById('view_stu_address').textContent = stu.address || '—';
  
  // Attendance badge
  const attPct = parseFloat(stu.attendance_percentage || 0);
  const attBadge = document.getElementById('view_stu_attendance');
  attBadge.textContent = attPct.toFixed(1) + '% Att.';
  attBadge.className = 'badge-status ' + (attPct >= 85 ? 'badge-present' : (attPct >= 75 ? 'badge-leave' : 'badge-absent'));

  // Fees calculation
  const total = parseFloat(stu.total_fees || 0);
  const paid = parseFloat(stu.paid_fees || 0);
  const due = Math.max(0, total - paid);
  document.getElementById('view_stu_total_fees').textContent = '₹' + total.toLocaleString('en-IN');
  document.getElementById('view_stu_paid_fees').textContent = '₹' + paid.toLocaleString('en-IN');
  
  const dueEl = document.getElementById('view_stu_due_fees');
  if (due > 0) {
    dueEl.innerHTML = '<span style="color:#dc2626; font-weight:800; background:#fef2f2; border:1px solid #fecaca; padding:1px 6px; border-radius:4px;">₹' + due.toLocaleString('en-IN') + ' Due</span>';
  } else {
    dueEl.innerHTML = '<span style="color:#059669; font-weight:800; background:#ecfdf5; border:1px solid #a7f3d0; padding:1px 6px; border-radius:4px;"><i class="fa-solid fa-check"></i> Fully Paid</span>';
  }

  // Academic Documents list
  const docsContainer = document.getElementById('view_stu_docs_list');
  docsContainer.innerHTML = '';
  let docs = [];
  try {
    if (typeof stu.academic_files === 'string' && stu.academic_files.trim() !== '') {
      docs = JSON.parse(stu.academic_files);
    } else if (Array.isArray(stu.academic_files)) {
      docs = stu.academic_files;
    }
  } catch(e) {}

  if (Array.isArray(docs) && docs.length > 0) {
    docs.forEach(doc => {
      const item = document.createElement('div');
      item.className = 'docs-list-item';
      let icon = 'fa-file-pdf';
      if (['jpg', 'jpeg', 'png', 'webp'].includes(doc.ext)) icon = 'fa-file-image';
      else if (['doc', 'docx'].includes(doc.ext)) icon = 'fa-file-word';

      item.innerHTML = `
        <div style="display:flex; align-items:center; gap:10px;">
          <i class="fa-solid ${icon}" style="color:var(--hrms-primary); font-size:1.15rem;"></i>
          <div>
            <div style="font-weight:700; font-size:0.83rem; color:#0f172a;">${escapeHtml(doc.name)}</div>
            <small style="color:#64748b; font-size:0.7rem;">${formatBytes(doc.size)} • ${doc.date || 'Attached'}</small>
          </div>
        </div>
        <a href="${doc.url}" target="_blank" rel="noopener noreferrer" class="btn-hrms btn-hrms-outline btn-hrms-sm" style="font-weight:600; font-size:0.75rem; padding:5px 12px; display:inline-flex; align-items:center; gap:6px;">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> View Document
        </a>
      `;
      docsContainer.appendChild(item);
    });
  } else {
    docsContainer.innerHTML = '<p style="color:#94a3b8; font-size:0.8rem; margin:0; font-style:italic;">No academic documents attached for this scholar.</p>';
  }

  openModal('viewStudentModal');
}

function openEditStudentModal(stu) {
  if (!stu && currentViewingStudent) {
    stu = currentViewingStudent;
  }
  if (!stu) return;

  closeModal('viewStudentModal');

  document.getElementById('edit_student_id').value = stu.id || '';
  document.getElementById('edit_student_name').value = stu.student_name || '';
  document.getElementById('edit_standard').value = stu.standard || '8th Standard';
  document.getElementById('edit_roll_no').value = stu.roll_no || '';
  document.getElementById('edit_admission_no').value = stu.admission_no || '';
  document.getElementById('edit_section').value = stu.section || 'A';
  document.getElementById('edit_gender').value = stu.gender || 'Male';
  document.getElementById('edit_dob').value = stu.dob || '';
  document.getElementById('edit_blood_group').value = stu.blood_group || 'O+';
  document.getElementById('edit_parent_name').value = stu.parent_name || '';
  document.getElementById('edit_mother_name').value = stu.mother_name || '';
  document.getElementById('edit_parent_phone').value = stu.parent_phone || '';
  document.getElementById('edit_student_phone').value = stu.student_phone || '';
  document.getElementById('edit_student_email').value = stu.student_email || '';
  document.getElementById('edit_batch_timing').value = stu.batch_timing || '07:30 AM - 01:30 PM';
  document.getElementById('edit_address').value = stu.address || '';
  document.getElementById('edit_total_fees').value = stu.total_fees || 0;
  document.getElementById('edit_paid_fees').value = stu.paid_fees || 0;

  // Photo preview
  const prevImg = document.getElementById('edit_photo_preview');
  if (stu.photo && stu.photo.trim() !== '') {
    prevImg.src = stu.photo;
  } else {
    prevImg.src = '/assets/imgs/ksv-logo.jpg';
  }

  // Academic docs summary
  let docs = [];
  try {
    if (typeof stu.academic_files === 'string' && stu.academic_files.trim() !== '') {
      docs = JSON.parse(stu.academic_files);
    } else if (Array.isArray(stu.academic_files)) {
      docs = stu.academic_files;
    }
  } catch(e) {}
  const countEl = document.getElementById('edit_docs_count');
  if (countEl) {
    countEl.textContent = docs.length > 0 ? docs.length + ' existing file(s) attached' : 'No existing files';
  }

  openModal('editStudentModal');
}

function showStudentDocs(studentName, docs) {
  document.getElementById('docModalStudentName').textContent = studentName;
  const container = document.getElementById('docModalFilesList');
  container.innerHTML = '';

  if (!docs || docs.length === 0) {
    container.innerHTML = '<p style="text-align:center; color:#94a3b8; padding:20px;">No documents attached.</p>';
  } else {
    docs.forEach(doc => {
      const item = document.createElement('div');
      item.className = 'docs-list-item';
      
      let icon = 'fa-file-pdf';
      if (['jpg', 'jpeg', 'png', 'webp'].includes(doc.ext)) icon = 'fa-file-image';
      else if (['doc', 'docx'].includes(doc.ext)) icon = 'fa-file-word';

      item.innerHTML = `
        <div style="display:flex; align-items:center; gap:10px;">
          <i class="fa-solid ${icon}" style="color:var(--hrms-primary); font-size:1.2rem;"></i>
          <div>
            <div style="font-weight:700; font-size:0.84rem; color:#0f172a;">${escapeHtml(doc.name)}</div>
            <small style="color:#64748b; font-size:0.72rem;">Uploaded: ${doc.date || '—'}</small>
          </div>
        </div>
        <a href="${doc.url}" target="_blank" rel="noopener noreferrer" class="btn-hrms btn-hrms-outline btn-hrms-sm" style="font-weight:600; font-size:0.76rem; padding:5px 12px; display:inline-flex; align-items:center; gap:6px;">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> View Document
        </a>
      `;
      container.appendChild(item);
    });
  }

  openModal('studentDocsModal');
}

function openChangePasswordModal(stu) {
  if (!stu) return;
  document.getElementById('pwd_modal_student_id').value = stu.id || '';
  document.getElementById('pwd_modal_student_name').textContent = stu.student_name || 'Student';
  document.getElementById('pwd_modal_roll_no').textContent = stu.roll_no || '—';
  document.getElementById('pwd_modal_standard').textContent = stu.standard || '—';

  let uname = stu.user_username || '';
  if (!uname && stu.roll_no) {
    uname = stu.roll_no.toLowerCase().replace(/[^a-z0-9]/g, '');
  }
  document.getElementById('pwd_modal_username').textContent = '@' + (uname || 'student');
  document.getElementById('modal_new_password').value = '';
  document.getElementById('modal_confirm_password').value = '';
  openModal('changeStudentPasswordModal');
}

function togglePasswordVisibility(inputId, btn) {
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
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>

