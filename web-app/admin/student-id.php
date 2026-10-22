<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
$page_title = 'Student ID Card | KSV Academy HRMS';
$page_heading = 'Student Identity Card & Attendance Pass';
$page_subheading = 'Official Academic Identification Badge with Biometric QR Code';
$active_menu = 'student-id';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Filters like students.php
$search = trim($_GET['search'] ?? '');
$filter_standard = trim($_GET['standard'] ?? '');
$standards = ['8th Standard', '9th Standard', '10th Standard', '11th Science', '11th Commerce', '12th Science', '12th Commerce'];

// Get selected student ID
$selected_id = intval($_GET['id'] ?? 0);

// If student or parent role logged in, lock to their student profile
if ($current_user['role'] === 'student' || $current_user['role'] === 'parent') {
    $my_stu = $db->query("SELECT id FROM students WHERE parent_user_id = {$current_user['id']} LIMIT 1")->fetch();
    if ($my_stu) {
        $selected_id = $my_stu['id'];
    }
}

// Fetch all students in the active session for top dropdown list
$all_students_stmt = $db->prepare("
    SELECT id, student_name, roll_no, standard, photo, admission_no, parent_phone, blood_group, dob, parent_name, section, qr_code 
    FROM students 
    WHERE session_id = ? 
    ORDER BY standard ASC, roll_no ASC, student_name ASC
");
$all_students_stmt->execute([$session_id]);
$all_students_list = $all_students_stmt->fetchAll();

// Pagination & Limits Setup (10, 20, 30, 40, 50)
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
    $where_clauses[] = "(student_name LIKE ? OR roll_no LIKE ? OR admission_no LIKE ? OR parent_phone LIKE ?)";
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

// Query students for the paginated table directory
$query = "SELECT * FROM students WHERE {$where_sql} ORDER BY standard ASC, roll_no ASC, student_name ASC LIMIT ? OFFSET ?";
$stmt_table = $db->prepare($query);
$bind_idx = 1;
foreach ($where_params as $val) {
    $stmt_table->bindValue($bind_idx++, $val);
}
$stmt_table->bindValue($bind_idx++, $limit, PDO::PARAM_INT);
$stmt_table->bindValue($bind_idx++, $offset, PDO::PARAM_INT);
$stmt_table->execute();
$table_students = $stmt_table->fetchAll();

// Pagination indicators
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Helper for generating student ID directory pagination links
function get_student_id_pagination_url($target_page, $search, $filter_standard, $selected_id, $limit) {
    $params = [];
    if ($selected_id > 0) $params['id'] = $selected_id;
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_standard)) $params['standard'] = $filter_standard;
    if ($limit != 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return ADMIN_URL . '/student-id' . (!empty($params) ? '?' . http_build_query($params) : '') . '#studentIdDirectoryCard';
}

// Default selected student:
// 1. If ID requested and exists, keep it
// 2. Otherwise default to first student in the filtered table list
// 3. If filtered list is empty, default to first student in session
if ($selected_id === 0) {
    if (!empty($table_students)) {
        $selected_id = $table_students[0]['id'];
    } elseif (!empty($all_students_list)) {
        $selected_id = $all_students_list[0]['id'];
    }
}

// Active navigation list for Prev / Next card switcher:
// Get all matching student records for smooth Prev/Next card navigation
if (!empty($search) || !empty($filter_standard)) {
    $nav_stmt = $db->prepare("SELECT id FROM students WHERE {$where_sql} ORDER BY standard ASC, roll_no ASC, student_name ASC");
    $nav_stmt->execute($where_params);
    $nav_list = $nav_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $nav_list = $all_students_list;
}

// Find current student index for Previous / Next navigation
$current_index = 0;
foreach ($nav_list as $idx => $s_item) {
    if ($s_item['id'] == $selected_id) {
        $current_index = $idx;
        break;
    }
}
$prev_student_id = ($current_index > 0) ? $nav_list[$current_index - 1]['id'] : null;
$next_student_id = ($current_index < count($nav_list) - 1) ? $nav_list[$current_index + 1]['id'] : null;

// Fetch selected student details
$student = null;
if ($selected_id > 0) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$selected_id]);
    $student = $stmt->fetch();
}

// Check student photo
$has_student_photo = false;
$student_photo_url = '';
if ($student && !empty($student['photo'])) {
    if ($student['photo'] !== '/assets/imgs/ksv-logo.jpg') {
        $photo_file = __DIR__ . '/../..' . $student['photo'];
        if (file_exists($photo_file)) {
            $has_student_photo = true;
            $student_photo_url = $student['photo'];
        }
    }
}

// Generate QR Code data string (STU-{id}-{roll_no})
$qr_data = $student ? ($student['qr_code'] ?: 'STU-' . $student['roll_no']) : 'KSV-STUDENT';
$qr_image_url = get_qr_code_url($qr_data, '220x220');

// Avatar gradient fallback
$first_letter = $student ? strtoupper(substr($student['student_name'], 0, 1)) : 'S';
$grad_palette = [
    'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)',
    'linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)',
    'linear-gradient(135deg, #059669 0%, #047857 100%)',
    'linear-gradient(135deg, #d97706 0%, #b45309 100%)',
    'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)',
];
$avatar_bg = $grad_palette[ord($first_letter) % count($grad_palette)];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<!-- html2canvas for High-Resolution Card Export -->
<script src="<?php echo HRMS_URL; ?>/assets/js/html2canvas.min.js"></script>

<style>
/* ── Premium PVC Student ID Card Styles ── */
.id-card-stage {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  gap: 40px;
  flex-wrap: wrap;
  padding: 10px 0 30px;
}

.id-badge-holder {
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Simulated Lanyard Clip at top */
.lanyard-clip {
  width: 48px;
  height: 24px;
  background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
  border-radius: 6px 6px 0 0;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
  position: relative;
  z-index: 5;
  margin-bottom: -10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.lanyard-clip::after {
  content: '';
  width: 20px;
  height: 6px;
  background: #334155;
  border-radius: 3px;
}

/* Card Physical Dimension (Standard CR80 Ratio 85.6mm x 54mm) */
.pvc-card {
  width: 326px;
  min-height: 512px;
  background: #ffffff;
  border-radius: 18px;
  box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.2), 0 0 0 1px rgba(15, 23, 42, 0.08);
  overflow: hidden;
  position: relative;
  display: flex;
  flex-direction: column;
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
  user-select: none;
}
.pvc-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 28px 55px -12px rgba(0, 146, 223, 0.25), 0 0 0 1px rgba(0, 146, 223, 0.2);
}

/* Top Lanyard Punch Slot */
.card-lanyard-slot {
  width: 44px;
  height: 10px;
  background: #f1f5f9;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  margin: 10px auto 0;
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.12);
  position: relative;
  z-index: 4;
}

/* Card Front Header Banner */
.card-front-header {
  background: linear-gradient(135deg, #0f172a 0%, #0092df 65%, #0284c7 100%);
  color: #ffffff;
  padding: 10px 16px 38px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.card-front-header::before {
  content: '';
  position: absolute;
  top: -40px;
  right: -40px;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  pointer-events: none;
}
.card-front-header::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  right: 0;
  height: 24px;
  background: #ffffff;
  clip-path: ellipse(56% 100% at 50% 100%);
}

.cf-logo-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.cf-logo-wrap {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: #ffffff;
  padding: 2.5px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
}
.cf-logo-wrap img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 7px;
}
.cf-brand-name {
  font-family: 'Outfit', sans-serif;
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 0.02em;
  color: #ffffff;
  line-height: 1.15;
}
.cf-sub-tag {
  font-size: 8.5px;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: rgba(255, 255, 255, 0.85);
  font-weight: 700;
  margin-top: 2px;
}

.cf-session-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(15, 23, 42, 0.35);
  backdrop-filter: blur(4px);
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 8.5px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-top: 6px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Photo and Smart Chip Row */
.cf-photo-container {
  position: relative;
  margin-top: -38px;
  z-index: 5;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.cf-smart-chip {
  position: absolute;
  top: 14px;
  left: 20px;
  width: 32px;
  height: 24px;
  background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 50%, #d97706 100%);
  border-radius: 5px;
  border: 1px solid #b45309;
  box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.6), 0 2px 4px rgba(0,0,0,0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: #78350f;
}

.cf-photo-frame {
  width: 98px;
  height: 98px;
  border-radius: 50%;
  border: 4px solid #ffffff;
  box-shadow: 0 6px 16px rgba(0, 146, 223, 0.28);
  background: #f8fafc;
  overflow: hidden;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cf-photo-frame img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.cf-photo-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 38px;
  font-weight: 900;
  font-family: 'Outfit', sans-serif;
}

/* Card Front Body Details */
.card-front-body {
  padding: 8px 20px 14px;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.cf-student-name {
  margin: 6px 0 2px;
  font-size: 17px;
  font-weight: 900;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
  letter-spacing: -0.01em;
  text-align: center;
}
.cf-standard-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #e0f2fe;
  color: #0284c7;
  padding: 3px 12px;
  border-radius: 12px;
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: 0.02em;
  margin-bottom: 12px;
  border: 1px solid #bae6fd;
}

/* Key Value Table */
.cf-info-list {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  background: #f8fafc;
  margin-bottom: 10px;
}
.cf-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 5px 10px;
  font-size: 11px;
  border-bottom: 1px solid #eef2f6;
}
.cf-info-row:last-child {
  border-bottom: none;
}
.cf-label {
  color: #64748b;
  font-weight: 700;
  font-size: 9.5px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.cf-val {
  color: #0f172a;
  font-weight: 700;
  text-align: right;
}
.cf-val.mono {
  font-family: monospace;
  font-size: 11.5px;
  color: #0284c7;
  font-weight: 800;
}
.cf-blood-badge {
  background: #fee2e2;
  color: #dc2626;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 800;
}

/* Card Front Sign & Validity Footer */
.cf-footer {
  width: 100%;
  margin-top: auto;
  padding-top: 8px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  border-top: 1.5px dashed #e2e8f0;
}
.cf-valid-box {
  text-align: left;
}
.cf-valid-lbl {
  font-size: 8px;
  color: #94a3b8;
  font-weight: 800;
  text-transform: uppercase;
}
.cf-valid-val {
  font-size: 10px;
  font-weight: 800;
  color: #334155;
  font-family: monospace;
}
.cf-sign-box {
  text-align: right;
}
.cf-sign-img {
  font-family: 'Outfit', cursive;
  font-size: 13px;
  font-weight: 800;
  color: #0284c7;
  letter-spacing: 0.5px;
}
.cf-sign-lbl {
  font-size: 8px;
  color: #94a3b8;
  font-weight: 800;
  text-transform: uppercase;
  border-top: 1px solid #cbd5e1;
  padding-top: 2px;
}

/* ── Card Back Styles ── */
.card-back-header {
  background: #0f172a;
  color: #ffffff;
  padding: 12px 14px 10px;
  text-align: center;
  position: relative;
}
.cb-title {
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #38bdf8;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.card-back-body {
  padding: 14px 18px 12px;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

/* QR Code Container */
.cb-qr-frame {
  padding: 8px;
  background: #ffffff;
  border: 2px solid #0092df;
  border-radius: 12px;
  box-shadow: 0 4px 14px rgba(0, 146, 223, 0.2);
  margin-top: 4px;
}
.cb-qr-frame img {
  width: 140px;
  height: 140px;
  display: block;
  border-radius: 4px;
}
.cb-qr-code-text {
  font-family: monospace;
  font-size: 12px;
  font-weight: 800;
  color: #0284c7;
  margin-top: 6px;
  letter-spacing: 0.05em;
}
.cb-qr-hint {
  font-size: 9.5px;
  color: #64748b;
  margin: 3px 0 10px;
  font-weight: 600;
}

/* Instructions Box */
.cb-instructions {
  width: 100%;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 9.5px;
  color: #475569;
  text-align: left;
  line-height: 1.45;
  margin-bottom: 8px;
}
.cb-instructions strong {
  color: #0f172a;
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  display: block;
  margin-bottom: 3px;
}
.cb-instructions ol {
  margin: 0;
  padding-left: 14px;
}

/* Card Back Contact Footer */
.cb-footer {
  width: 100%;
  margin-top: auto;
  padding-top: 8px;
  border-top: 1px solid #e2e8f0;
  font-size: 9.5px;
  color: #64748b;
  line-height: 1.35;
}
.cb-footer strong {
  color: #0f172a;
  font-size: 10px;
}

/* ── Student ID Directory Table & Badge Styles (Simple & Sober - Like staff-id) ── */
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
.student-id-table tbody tr.active-student-row {
  background-color: #f0f9ff !important;
}
.student-id-table tbody tr.active-student-row td:first-child {
  border-left: 3px solid #0284c7;
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
.sid-qr-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 3px 8px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.73rem;
  font-weight: 600;
  white-space: nowrap;
}
.sid-status-active {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0f9ff;
  color: #0284c7;
  border: 1px solid #bae6fd;
  font-size: 0.74rem;
  font-weight: 600;
  padding: 2px 9px;
  border-radius: 20px;
  white-space: nowrap;
}
.sid-status-active .pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #0284c7;
  box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7);
  animation: pulse-ring 1.8s infinite;
}
@keyframes pulse-ring {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(2, 132, 199, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
}
.sid-status-verified {
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
.sid-btn-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  font-size: 0.76rem;
  font-weight: 600;
  border-radius: 6px;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.sid-btn-secondary {
  color: #334155;
  background: #ffffff;
  border: 1px solid #cbd5e1;
}
.sid-btn-secondary:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}

/* ── Print Setup for CR80 Standard ID Card ── */
@media print {
  body * {
    visibility: hidden;
  }
  .id-card-printable-area, .id-card-printable-area * {
    visibility: visible;
  }
  .id-card-printable-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    display: flex !important;
    justify-content: center;
    gap: 30px;
    padding: 20px;
    background: #ffffff !important;
  }
  .lanyard-clip, .card-label-badge {
    display: none !important;
  }
  .pvc-card {
    box-shadow: none !important;
    border: 1px solid #94a3b8 !important;
    break-inside: avoid;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .no-print {
    display: none !important;
  }
}
</style>

<!-- ══════════════════════════════════════════════════════
     STUDENT ID CARD TOP TOOLBAR & ACTION BAR
     ══════════════════════════════════════════════════════ -->
<?php
  // Build query string to preserve search & filter
  $qp_filter = [];
  if (!empty($search)) $qp_filter['search'] = $search;
  if (!empty($filter_standard)) $qp_filter['standard'] = $filter_standard;
  if ($limit !== 10) $qp_filter['limit'] = $limit;
  if ($page > 1) $qp_filter['page'] = $page;
  $filter_qs = !empty($qp_filter) ? '&' . http_build_query($qp_filter) : '';
  $prev_card_url = $prev_student_id ? (ADMIN_URL . '/student-id?id=' . $prev_student_id . $filter_qs . '#id-card-stage') : '#';
  $next_card_url = $next_student_id ? (ADMIN_URL . '/student-id?id=' . $next_student_id . $filter_qs . '#id-card-stage') : '#';
?>
<div class="roaster-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Selected Student Label -->
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <h3 style="margin: 0; font-size: 1.08rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-id-card" style="color: var(--hrms-primary);"></i>
        <span><?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?></span>
        <span style="font-size: 0.78rem; font-weight: 800; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
          <?php echo htmlspecialchars($student['roll_no'] ?? ''); ?>
        </span>
        <span style="font-size: 0.78rem; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 9px; border-radius: 6px;">
          <?php echo htmlspecialchars($student['standard'] ?? ''); ?>
        </span>
      </h3>
    </div>

    <!-- Right: Print and Download Actions -->
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
      
      <!-- Download Front PNG -->
      <button type="button" onclick="downloadCardImage('card_front_element', 'Front');" class="roaster-btn secondary" style="gap: 6px;" title="Save Front Card as High-Resolution Image">
        <i class="fa-solid fa-download"></i>
        <span>Front (PNG)</span>
      </button>

      <!-- Download Back PNG -->
      <button type="button" onclick="downloadCardImage('card_back_element', 'Back');" class="roaster-btn secondary" style="gap: 6px;" title="Save Back Card with QR Code as High-Resolution Image">
        <i class="fa-solid fa-download"></i>
        <span>Back (PNG)</span>
      </button>

      <!-- Print Both Sides -->
      <button type="button" onclick="window.print();" class="roaster-add-btn" style="gap: 8px;" title="Print official ID card (Front & Back)">
        <div class="btn-icon-circle"><i class="fa-solid fa-print"></i></div>
        <span>Print ID Card</span>
      </button>

    </div>

  </div>
</div>

<?php if ($student): ?>
  <!-- ══════════════════════════════════════════════════════
       ID CARD STAGE: FRONT & BACK SIDES WITH LANYARDS
       ══════════════════════════════════════════════════════ -->
  <div class="id-card-printable-area id-card-stage" id="id-card-stage">
    
    <!-- ── CARD FRONT ── -->
    <div class="id-badge-holder">
      <div class="lanyard-clip no-print"></div>
      
      <div class="pvc-card" id="card_front_element">
        
        <!-- Punch Slot -->
        <div class="card-lanyard-slot"></div>

        <!-- Header Banner -->
        <div class="card-front-header">
          <div class="cf-logo-row">
            <div class="cf-logo-wrap">
              <img src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="Logo" onerror="this.src='/assets/imgs/ksv-logo.jpg'">
            </div>
            <div>
              <div class="cf-brand-name"><?php echo htmlspecialchars($school_settings['school_name']); ?></div>
              <div class="cf-sub-tag">Academic Excellence &amp; HRMS</div>
            </div>
          </div>
          <div class="cf-session-pill">
            <i class="fa-solid fa-graduation-cap"></i>
            <span>Identity Card &bull; Session <?php echo htmlspecialchars($active_session['session_name']); ?></span>
          </div>
        </div>

        <!-- Student Photo & Smart Chip -->
        <div class="cf-photo-container">
          <!-- Gold Metallic Smart Chip -->
          <div class="cf-smart-chip" title="Biometric Smart Badge">
            <i class="fa-solid fa-microchip"></i>
          </div>

          <!-- Photo Frame -->
          <div class="cf-photo-frame">
            <?php if ($has_student_photo): ?>
              <img src="<?php echo htmlspecialchars($student_photo_url); ?>" alt="<?php echo htmlspecialchars($student['student_name']); ?>">
            <?php else: ?>
              <div class="cf-photo-fallback" style="background: <?php echo $avatar_bg; ?>;">
                <?php echo $first_letter; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Student Profile Details -->
        <div class="card-front-body">
          <h3 class="cf-student-name">
            <?php echo htmlspecialchars($student['student_name']); ?>
          </h3>
          <div class="cf-standard-badge">
            <i class="fa-solid fa-book-open" style="font-size: 9px;"></i>
            <span><?php echo htmlspecialchars($student['standard']); ?> &bull; Sec <?php echo htmlspecialchars($student['section'] ?? 'A'); ?></span>
          </div>

          <!-- Info List -->
          <div class="cf-info-list">
            <div class="cf-info-row">
              <span class="cf-label">Roll Number</span>
              <span class="cf-val mono"><?php echo htmlspecialchars($student['roll_no']); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Admission No</span>
              <span class="cf-val"><?php echo htmlspecialchars($student['admission_no'] ?? 'ADM-' . $student['id']); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Guardian</span>
              <span class="cf-val"><?php echo htmlspecialchars($student['parent_name'] ?? '—'); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Emergency Ph</span>
              <span class="cf-val"><?php echo htmlspecialchars($student['parent_phone'] ?? '—'); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Blood Group</span>
              <span class="cf-val">
                <span class="cf-blood-badge"><?php echo htmlspecialchars($student['blood_group'] ?? 'O+'); ?></span>
              </span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Date of Birth</span>
              <span class="cf-val"><?php echo format_date($student['dob']); ?></span>
            </div>
          </div>

          <!-- Footer: Validity & Principal Sign -->
          <div class="cf-footer">
            <div class="cf-valid-box">
              <div class="cf-valid-lbl">Valid Till</div>
              <div class="cf-valid-val">31 MAR <?php echo date('Y') + 1; ?></div>
            </div>
            <div class="cf-sign-box">
              <div class="cf-sign-img">Salam Khan</div>
              <div class="cf-sign-lbl">Principal Signature</div>
            </div>
          </div>

        </div>

      </div>

      <div class="card-label-badge no-print" style="margin-top: 12px; font-size: 0.76rem; font-weight: 800; color: #64748b; background: #e2e8f0; padding: 4px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.05em;">
        Front Side View
      </div>
    </div>

    <!-- ── CARD BACK (Biometric Scannable QR Code) ── -->
    <div class="id-badge-holder">
      <div class="lanyard-clip no-print"></div>

      <div class="pvc-card" id="card_back_element">
        
        <!-- Punch Slot -->
        <div class="card-lanyard-slot"></div>

        <!-- Back Header Band -->
        <div class="card-back-header">
          <div class="cb-title">
            <i class="fa-solid fa-qrcode"></i>
            <span>Biometric QR Attendance Pass</span>
          </div>
        </div>

        <!-- Back Body -->
        <div class="card-back-body">
          
          <!-- High-Resolution Scannable QR Frame -->
          <div class="cb-qr-frame">
            <img id="back_qr_img" src="<?php echo $qr_image_url; ?>" alt="Biometric QR Code" crossorigin="anonymous">
          </div>

          <div class="cb-qr-code-text">
            <?php echo htmlspecialchars($qr_data); ?>
          </div>
          <div class="cb-qr-hint">
            Scan via KSV Academy Biometric Camera to punch daily attendance.
          </div>

          <!-- Rules & Terms -->
          <div class="cb-instructions">
            <strong>Campus Rules &amp; Regulations:</strong>
            <ol>
              <li>This identity badge is mandatory on campus at all times.</li>
              <li>Present this card for library, lab, and examination entry.</li>
              <li>Non-transferable. Valid only for <?php echo htmlspecialchars($active_session['session_name']); ?>.</li>
              <li>If lost or damaged, report to admin office immediately.</li>
            </ol>
          </div>

          <!-- Campus Contact & Return Address -->
          <div class="cb-footer">
            <strong><?php echo htmlspecialchars($school_settings['school_name']); ?></strong><br>
            <?php echo htmlspecialchars($school_settings['address']); ?><br>
            Emergency: <strong><?php echo htmlspecialchars($school_settings['phone']); ?></strong>
          </div>

        </div>

      </div>

      <div class="card-label-badge no-print" style="margin-top: 12px; font-size: 0.76rem; font-weight: 800; color: #64748b; background: #e2e8f0; padding: 4px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.05em;">
        Back Side View (QR Pass)
      </div>
    </div>

  </div>



  <!-- ══════════════════════════════════════════════════════
       FILTER TOOLBAR (Exact match to students.php)
       ══════════════════════════════════════════════════════ -->
  <div class="roaster-toolbar-card no-print" style="margin-top: 24px; margin-bottom: 20px;">
    <div class="roaster-toolbar-inner">
      
      <!-- Filter form -->
      <form method="GET" action="<?php echo ADMIN_URL; ?>/student-id" class="roaster-filter-form">
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">
        <?php if ($selected_id > 0): ?>
          <input type="hidden" name="id" value="<?php echo $selected_id; ?>">
        <?php endif; ?>

        <div class="roaster-search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, roll no, phone..." autocomplete="off">
          <?php if (!empty($search)): ?>
            <a href="<?php echo htmlspecialchars(get_student_id_pagination_url(1, '', $filter_standard, $selected_id, $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
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
          <a href="<?php echo ADMIN_URL; ?>/student-id<?php 
            $rp = [];
            if ($selected_id > 0) $rp['id'] = $selected_id;
            if ($limit !== 10) $rp['limit'] = $limit;
            echo !empty($rp) ? '?' . http_build_query($rp) : '';
          ?>" class="roaster-btn ghost" title="Reset all filters">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </a>
        <?php endif; ?>
      </form>

      <!-- Right: Scholars Live Count & Link to Roster -->
      <div class="roaster-toolbar-actions">
        <div class="scholars-count-badge">
          <span class="count-dot"></span>
          <span><strong><?php echo $total_records; ?></strong> Scholars</span>
        </div>

        <a href="<?php echo ADMIN_URL; ?>/students" class="roaster-btn secondary" style="text-decoration: none; font-size: 0.82rem; gap: 6px;">
          <i class="fa-solid fa-users"></i>
          <span>Student Roaster</span>
        </a>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       STUDENT DATA TABLE (Simple & Sober - Like staff-id.php)
       ══════════════════════════════════════════════════════ -->
  <div class="hrms-card no-print" id="studentIdDirectoryCard" style="margin-bottom: 30px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
    <div class="hrms-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
      <div>
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-id-card-clip" style="color: #0284c7;"></i>
          Student ID Card Directory
          <span style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
            Session <?php echo htmlspecialchars($active_session['session_name']); ?>
          </span>
        </h3>
        <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
          Click <strong>View ID Card</strong> on any student row below to load their official PVC badge with biometric QR pass.
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
            <th style="min-width: 160px;">Student Profile</th>
            <th style="min-width: 110px;">Roll &amp; Admission</th>
            <th style="min-width: 120px;">Standard &amp; Section</th>
            <th style="min-width: 115px;">Guardian Info</th>
            <th style="min-width: 120px;">Biometric QR Pass</th>
            <th style="min-width: 90px;">Card Status</th>
            <th style="min-width: 115px; text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($table_students)): ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 50px 20px; color: #64748b;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                  <i class="fa-solid fa-user-slash"></i>
                </div>
                <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Scholars Matching Filters</h4>
                <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                  No student records matched your query in Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
                </p>
                <a href="<?php echo ADMIN_URL; ?>/student-id<?php echo $selected_id > 0 ? '?id=' . $selected_id : ''; ?>" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                  <i class="fa-solid fa-rotate-left"></i> Reset All Filters
                </a>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($table_students as $stu): ?>
              <?php 
                $is_cur_viewing = ($stu['id'] == $selected_id);
                $has_photo = !empty($stu['photo']) && $stu['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $stu['photo']);
                $first_letter = strtoupper(substr($stu['student_name'], 0, 1));

                $stu_qr_code = $stu['qr_code'] ?: ('STU-' . $stu['roll_no']);

                $switch_url = ADMIN_URL . '/student-id?id=' . $stu['id'] . 
                              (!empty($search) ? '&search=' . urlencode($search) : '') . 
                              (!empty($filter_standard) ? '&standard=' . urlencode($filter_standard) : '') . 
                              ($limit !== 10 ? '&limit=' . $limit : '') .
                              ($page > 1 ? '&page=' . $page : '') .
                              '#id-card-stage';
              ?>
              <tr class="<?php echo $is_cur_viewing ? 'active-student-row' : ''; ?>">
                
                <!-- 1. Student Profile -->
                <td>
                  <div class="user-cell" style="display: flex; align-items: center; gap: 12px;">
                    <?php if ($has_photo): ?>
                      <img src="<?php echo htmlspecialchars($stu['photo']); ?>" alt="<?php echo htmlspecialchars($stu['student_name']); ?>" class="staff-avatar-img" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                      <div class="staff-avatar-init" style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                        <?php echo $first_letter; ?>
                      </div>
                    <?php else: ?>
                      <div class="staff-avatar-init" style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
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
                <td>
                  <span class="sid-roll-pill">
                    <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
                    <span><?php echo htmlspecialchars($stu['roll_no']); ?></span>
                  </span>
                  <div style="font-size: 0.74rem; color: #64748b; margin-top: 4px;">
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

                <!-- 5. Biometric QR Pass -->
                <td>
                  <span class="sid-qr-chip" title="Biometric QR Pass ID">
                    <i class="fa-solid fa-qrcode" style="color: #0284c7; font-size: 0.72rem;"></i>
                    <span><?php echo htmlspecialchars($stu_qr_code); ?></span>
                  </span>
                </td>

                <!-- 6. Card Status -->
                <td>
                  <?php if ($is_cur_viewing): ?>
                    <span class="sid-status-active">
                      <span class="pulse-dot"></span> In Preview
                    </span>
                  <?php else: ?>
                    <span class="sid-status-verified">
                      <i class="fa-solid fa-check" style="font-size: 0.68rem;"></i> Enrolled
                    </span>
                  <?php endif; ?>
                </td>

                <!-- 7. Actions -->
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                    <?php if ($is_cur_viewing): ?>
                      <button type="button" onclick="document.getElementById('id-card-stage').scrollIntoView({behavior: 'smooth'});" class="sid-btn-action sid-btn-secondary" style="background: #f0f9ff; border-color: #bae6fd; color: #0284c7;" title="Currently loaded into preview">
                        <i class="fa-solid fa-eye" style="font-size: 0.72rem;"></i>
                        <span>Viewing</span>
                      </button>
                      <button type="button" onclick="window.print();" class="sid-btn-action sid-btn-secondary" title="Print this Card">
                        <i class="fa-solid fa-print" style="color: #0284c7; font-size: 0.72rem;"></i>
                        <span>Print</span>
                      </button>
                    <?php else: ?>
                      <a href="<?php echo $switch_url; ?>" class="sid-btn-action sid-btn-secondary" title="Load student card into preview">
                        <i class="fa-solid fa-id-card" style="color: #0284c7; font-size: 0.72rem;"></i>
                        <span>View ID Card</span>
                      </a>
                    <?php endif; ?>
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
              <option value="<?php echo htmlspecialchars(get_student_id_pagination_url(1, $search, $filter_standard, $selected_id, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
                <?php echo $l; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Center/Right: Navigation page buttons -->
      <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav" aria-label="Student ID Directory Pagination">
          <!-- Prev Page -->
          <a href="<?php echo $page > 1 ? htmlspecialchars(get_student_id_pagination_url($page - 1, $search, $filter_standard, $selected_id, $limit)) : '#'; ?>" 
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
            <a href="<?php echo htmlspecialchars(get_student_id_pagination_url($p, $search, $filter_standard, $selected_id, $limit)); ?>" 
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
          <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_student_id_pagination_url($page + 1, $search, $filter_standard, $selected_id, $limit)) : '#'; ?>" 
             class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
             aria-label="Next Page"
             title="Next Page">
            <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
          </a>
        </nav>
      <?php endif; ?>

      <!-- Legend / Session Status -->
      <div style="font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 12px; margin-left: auto;">
        <span style="display: inline-flex; align-items: center; gap: 6px;">
          <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
          Official PVC Pass
        </span>
        <span style="color: #cbd5e1;">|</span>
        <span>Session: <strong style="color: #334155;"><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
      </div>

    </div>
  </div>
  
<?php else: ?>
  <div class="hrms-card" style="text-align: center; padding: 60px 20px;">
    <div style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px;"><i class="fa-solid fa-id-badge"></i></div>
    <h3 style="margin: 0 0 8px; font-weight: 800; color: #0f172a;">No Student Enrolled in Active Session</h3>
    <p style="margin: 0; color: #64748b; font-size: 0.88rem;">Please enroll students in Student Roaster to generate identity cards.</p>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     HIGH-RES PNG DOWNLOAD LOGIC (html2canvas)
     ══════════════════════════════════════════════════════ -->
<script>
function downloadCardImage(elementId, sideLabel) {
  const card = document.getElementById(elementId);
  if (!card) return;

  const stuName = <?php echo json_encode($student['student_name'] ?? 'Student'); ?>;
  const rollNo = <?php echo json_encode($student['roll_no'] ?? 'KSV'); ?>;
  const filename = `Student_ID_${sideLabel}_${stuName.replace(/\s+/g, '_')}_${rollNo}.png`;

  html2canvas(card, {
    scale: 2.5,
    useCORS: true,
    backgroundColor: null,
    logging: false
  }).then(canvas => {
    const link = document.createElement('a');
    link.download = filename;
    link.href = canvas.toDataURL('image/png');
    link.click();
  }).catch(err => {
    console.error('Download error:', err);
    alert('Unable to generate high-resolution image. Please try again.');
  });
}
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
