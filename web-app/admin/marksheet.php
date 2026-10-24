<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
if ($current_user['role'] === 'staff') {
    require_menu_permission('admin_marksheet');
}
$page_title = 'Report Card | KSV Academy HRMS';
$page_heading = 'Report Card';
$page_subheading = 'Official Academic Statement of Marks, Examination Registers & Printable Report Cards';
$active_menu = 'marksheet';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Filters like student-id.php & students.php
$search = trim($_GET['search'] ?? '');
$filter_standard = trim($_GET['standard'] ?? '');
$standards = ['8th Standard', '9th Standard', '10th Standard', '11th Science', '11th Commerce', '12th Science', '12th Commerce'];

// Selected student & exam
$selected_student_id = intval($_GET['student_id'] ?? ($_GET['id'] ?? 0));
$selected_marksheet_id = intval($_GET['marksheet_id'] ?? 0);

// If logged in as student or parent, restrict to their student
if ($current_user['role'] === 'student' || $current_user['role'] === 'parent') {
    $my_stu = $db->query("SELECT id FROM students WHERE parent_user_id = {$current_user['id']} LIMIT 1")->fetch();
    if ($my_stu) {
        $selected_student_id = $my_stu['id'];
    }
}

// Fetch all students in the active session for top dropdown list
$all_students_stmt = $db->prepare("
    SELECT id, student_name, roll_no, standard, photo, admission_no, parent_phone, blood_group, dob, parent_name, section, qr_code, gender 
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

// Helper for generating marksheet pagination links
function get_marksheet_pagination_url($target_page, $search, $filter_standard, $selected_student_id, $selected_marksheet_id, $limit) {
    $params = [];
    if ($selected_student_id > 0) $params['student_id'] = $selected_student_id;
    if ($selected_marksheet_id > 0) $params['marksheet_id'] = $selected_marksheet_id;
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_standard)) $params['standard'] = $filter_standard;
    if ($limit != 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return ADMIN_URL . '/marksheet' . (!empty($params) ? '?' . http_build_query($params) : '') . '#marksheetDirectoryCard';
}

// Default selected student:
if ($selected_student_id === 0) {
    if (!empty($table_students)) {
        $selected_student_id = $table_students[0]['id'];
    } elseif (!empty($all_students_list)) {
        $selected_student_id = $all_students_list[0]['id'];
    }
}

// Active session academic statistics for stat cards:
$total_enrolled_count = count($all_students_list);
$eval_stats_stmt = $db->prepare("
    SELECT COUNT(*) as cnt, AVG(percentage) as avg_pct, 
           SUM(CASE WHEN result_status = 'PASS' THEN 1 ELSE 0 END) as pass_cnt 
    FROM marksheets 
    WHERE session_id = ?
");
$eval_stats_stmt->execute([$session_id]);
$eval_stats = $eval_stats_stmt->fetch();
$total_marksheets_count = intval($eval_stats['cnt'] ?? 0);
$avg_academic_pct = $eval_stats['avg_pct'] !== null ? round($eval_stats['avg_pct'], 1) : 0;
$passed_count = intval($eval_stats['pass_cnt'] ?? 0);
$pass_rate = $total_marksheets_count > 0 ? round(($passed_count / $total_marksheets_count) * 100) : 0;

// Query summary of marksheets per student in active session
$stu_ms_summary = [];
$ms_summary_stmt = $db->prepare("
    SELECT m.student_id, COUNT(m.id) as total_marksheets, 
           SUBSTRING_INDEX(GROUP_CONCAT(m.exam_name ORDER BY m.id DESC SEPARATOR '||'), '||', 1) as latest_exam_name,
           SUBSTRING_INDEX(GROUP_CONCAT(m.percentage ORDER BY m.id DESC SEPARATOR '||'), '||', 1) as latest_percentage,
           SUBSTRING_INDEX(GROUP_CONCAT(m.overall_grade ORDER BY m.id DESC SEPARATOR '||'), '||', 1) as latest_grade,
           SUBSTRING_INDEX(GROUP_CONCAT(m.result_status ORDER BY m.id DESC SEPARATOR '||'), '||', 1) as latest_status,
           SUBSTRING_INDEX(GROUP_CONCAT(m.id ORDER BY m.id DESC SEPARATOR '||'), '||', 1) as latest_marksheet_id
    FROM marksheets m
    WHERE m.session_id = ?
    GROUP BY m.student_id
");
$ms_summary_stmt->execute([$session_id]);
while ($row = $ms_summary_stmt->fetch()) {
    $stu_ms_summary[$row['student_id']] = $row;
}

// Handle Add/Generate Marksheet Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_marksheet') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $exam_name = trim($_POST['exam_name'] ?? 'Annual Examination');
        $exam_date = $_POST['exam_date'] ?? date('Y-m-d');
        $remarks = trim($_POST['remarks'] ?? 'Good academic discipline and consistent effort.');

        $subjects = $_POST['subjects'] ?? [];
        $obtained_list = $_POST['obtained'] ?? [];
        $max_list = $_POST['max_marks'] ?? [];

        $subjects_data = [];
        $total_obtained = 0;
        $total_max = 0;

        for ($i = 0; $i < count($subjects); $i++) {
            $sub_name = trim($subjects[$i]);
            if (empty($sub_name)) continue;
            
            $obt = floatval($obtained_list[$i] ?? 0);
            $max_m = floatval($max_list[$i] ?? 100);
            $pass_m = 35;

            $pct_sub = $max_m > 0 ? ($obt / $max_m) * 100 : 0;
            if ($pct_sub >= 90) $sub_grade = 'A+';
            elseif ($pct_sub >= 80) $sub_grade = 'A';
            elseif ($pct_sub >= 70) $sub_grade = 'B+';
            elseif ($pct_sub >= 60) $sub_grade = 'B';
            elseif ($pct_sub >= 50) $sub_grade = 'C';
            elseif ($pct_sub >= 35) $sub_grade = 'D';
            else $sub_grade = 'F';

            $subjects_data[] = [
                'name' => $sub_name,
                'max' => $max_m,
                'pass' => $pass_m,
                'obtained' => $obt,
                'grade' => $sub_grade
            ];

            $total_obtained += $obt;
            $total_max += $max_m;
        }

        $overall_percentage = $total_max > 0 ? round(($total_obtained / $total_max) * 100, 2) : 0;
        
        if ($overall_percentage >= 90) $overall_grade = 'A+';
        elseif ($overall_percentage >= 80) $overall_grade = 'A';
        elseif ($overall_percentage >= 70) $overall_grade = 'B+';
        elseif ($overall_percentage >= 60) $overall_grade = 'B';
        elseif ($overall_percentage >= 50) $overall_grade = 'C';
        elseif ($overall_percentage >= 35) $overall_grade = 'D';
        else $overall_grade = 'F';

        $result_status = $overall_percentage >= 35 ? 'PASS' : 'FAIL';

        $stmt = $db->prepare("
            INSERT INTO marksheets (session_id, student_id, exam_name, exam_date, subjects_json, total_obtained, total_max, percentage, overall_grade, result_status, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $session_id, $student_id, $exam_name, $exam_date, json_encode($subjects_data),
            $total_obtained, $total_max, $overall_percentage, $overall_grade, $result_status, $remarks
        ]);
        $new_ms_id = $db->lastInsertId();

        set_flash('success', "Marksheet generated successfully for student #{$student_id}!");
        
        // Preserve filters on redirect
        $qp_redir = ['student_id' => $student_id, 'marksheet_id' => $new_ms_id];
        if (!empty($search)) $qp_redir['search'] = $search;
        if (!empty($filter_standard)) $qp_redir['standard'] = $filter_standard;

        header("Location: " . ADMIN_URL . "/marksheet?" . http_build_query($qp_redir) . "#report-card-view");
        exit;
    }
}

// Fetch marksheets for this student
$marksheets_stmt = $db->prepare("SELECT * FROM marksheets WHERE student_id = ? AND session_id = ? ORDER BY id DESC");
$marksheets_stmt->execute([$selected_student_id, $session_id]);
$student_marksheets = $marksheets_stmt->fetchAll();

// Select active marksheet to display
$active_marksheet = null;
if ($selected_marksheet_id > 0) {
    foreach ($student_marksheets as $ms) {
        if ($ms['id'] == $selected_marksheet_id) {
            $active_marksheet = $ms;
            break;
        }
    }
}
if (!$active_marksheet && !empty($student_marksheets)) {
    $active_marksheet = $student_marksheets[0];
}

// Fetch student profile
$student_profile = null;
if ($selected_student_id > 0) {
    $st_stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $st_stmt->execute([$selected_student_id]);
    $student_profile = $st_stmt->fetch();
}

// Verification QR code for marksheet
$verification_qr_url = '';
if ($student_profile && $active_marksheet) {
    $qr_payload = 'KSV-EVAL-ID:' . $active_marksheet['id'] . '|ROLL:' . $student_profile['roll_no'] . '|SCORE:' . $active_marksheet['percentage'] . '%|' . $active_marksheet['overall_grade'];
    $verification_qr_url = get_qr_code_url($qr_payload, '120x120');
}

// Build query string to preserve search & filter
$qp_filter = [];
if (!empty($search)) $qp_filter['search'] = $search;
if (!empty($filter_standard)) $qp_filter['standard'] = $filter_standard;
$filter_qs = !empty($qp_filter) ? '&' . http_build_query($qp_filter) : '';

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
/* ── Strict A4 Single-Page Print Setup ── */
@media print {
  @page {
    size: A4 portrait;
    margin: 8mm 10mm;
  }
  
  html, body {
    margin: 0 !important;
    padding: 0 !important;
    background: #ffffff !important;
    width: 100% !important;
    height: 100% !important;
    overflow: hidden !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  body * {
    visibility: hidden;
  }

  .hrms-sidebar,
  .hrms-topbar,
  .no-print,
  .academic-stat-grid,
  .scholar-control-card,
  .roaster-toolbar-card,
  .hrms-card.no-print,
  .table-card,
  #student-directory,
  .modal-backdrop,
  header,
  footer,
  nav {
    display: none !important;
  }

  .marksheet-print-canvas,
  .marksheet-print-canvas * {
    visibility: visible;
  }

  .marksheet-print-canvas {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    right: 0 !important;
    margin: 0 auto !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    background: #ffffff !important;
    box-shadow: none !important;
    border: 2px solid #0092df !important;
    border-radius: 12px !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: avoid !important;
    break-after: avoid !important;
    page-break-before: avoid !important;
    break-before: avoid !important;
  }

  .report-header-banner,
  .report-details-grid,
  .report-table,
  .report-summary-kpi-row,
  .report-remarks-card,
  .report-signatures-row {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
}

/* ── Academic KPI Statistics Grid ── */
.academic-stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 16px;
  margin-bottom: 22px;
}
.academic-stat-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
  transition: all 0.25s ease;
}
.academic-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}
.stat-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}
.stat-icon-wrap.blue { background: #e0f2fe; color: #0284c7; }
.stat-icon-wrap.emerald { background: #dcfce7; color: #15803d; }
.stat-icon-wrap.purple { background: #f3e8ff; color: #7e22ce; }
.stat-icon-wrap.amber { background: #fef3c7; color: #b45309; }

.stat-metric-val {
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
  font-family: 'Outfit', sans-serif;
}
.stat-metric-lbl {
  font-size: 0.78rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-top: 3px;
}

/* ── Scholar Control Banner ── */
.scholar-control-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 16px 22px;
  margin-bottom: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
}
.current-scholar-pill {
  display: flex;
  align-items: center;
  gap: 14px;
}
.scholar-mini-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #e0f2fe;
}
.scholar-mini-init {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  font-weight: 800;
  font-family: 'Outfit', sans-serif;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.1);
}
.current-scholar-meta h4 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
  display: flex;
  align-items: center;
  gap: 8px;
}
.current-scholar-meta .meta-pills {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 3px;
  font-size: 0.75rem;
}

/* ── Modern Premium Report Card Container ── */
.report-card-container {
  max-width: 860px;
  margin: 0 auto 34px;
  background: #ffffff;
  border-radius: 16px;
  border: 2px solid #0092df;
  box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(0, 146, 223, 0.15);
  position: relative;
  overflow: hidden;
  transition: all 0.25s ease;
}

/* Gold pinstripe inner accent border */
.report-card-container::before {
  content: '';
  position: absolute;
  inset: 6px;
  border: 1px dashed rgba(0, 146, 223, 0.35);
  border-radius: 10px;
  pointer-events: none;
}

.report-header-banner {
  position: relative;
  z-index: 1;
  text-align: center;
  border-bottom: 2px solid #0092df;
}
.report-brand-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.report-school-title {
  margin: 0;
  font-weight: 900;
  color: #007cbd;
  letter-spacing: 0.5px;
  font-family: 'Outfit', sans-serif;
  line-height: 1.15;
}
.report-school-tagline {
  font-weight: 800;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-top: 2px;
}

.report-session-ribbon {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #0092df 0%, #0074b3 100%);
  color: #ffffff;
  border-radius: 30px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 1px;
  box-shadow: 0 3px 10px rgba(0, 146, 223, 0.25);
}

/* Student & Exam Details Box */
.report-details-grid {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}
.detail-item {
  display: flex;
  align-items: baseline;
  gap: 8px;
}
.detail-item strong {
  color: #475569;
  font-weight: 700;
  min-width: 105px;
}
.detail-item span {
  color: #0f172a;
}

/* Statement of Marks Table */
.report-table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #cbd5e1;
}
.report-table th, .report-table td {
  border: 1px solid #cbd5e1;
}
.report-table thead th {
  background: #0f172a;
  color: #ffffff;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  text-align: center;
}
.report-table thead th:first-child {
  text-align: left;
}
.report-table tbody tr:nth-child(even) {
  background: #fcfdfe;
}
.report-table tbody tr:hover {
  background: #f0f9ff;
}

/* Performance Summary KPI Row */
.report-summary-kpi-row {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
}
.summary-kpi-card {
  border-radius: 10px;
  text-align: center;
  border: 1px solid transparent;
}
.summary-kpi-card.percentage {
  background: #f0fdf4;
  border-color: #bbf7d0;
}
.summary-kpi-card.grade {
  background: #eff6ff;
  border-color: #bfdbfe;
}
.summary-kpi-card.pass {
  background: #f0fdf4;
  border-color: #86efac;
}
.summary-kpi-card.fail {
  background: #fef2f2;
  border-color: #fca5a5;
}
.kpi-title {
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 4px;
}
.kpi-val {
  font-weight: 900;
  font-family: 'Outfit', sans-serif;
  line-height: 1.1;
}

/* Remarks */
.report-remarks-card {
  position: relative;
  z-index: 1;
  color: #334155;
  background: #f8fafc;
  border-radius: 8px;
  border-left: 4px solid #0092df;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.report-remarks-card i {
  color: #0092df;
  font-size: 1.1rem;
  margin-top: 2px;
}

/* Digital Signatures and Official Seals */
.report-signatures-row {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  border-top: 1px solid #cbd5e1;
  font-weight: 700;
  color: #475569;
}
.sig-block {
  text-align: center;
  width: 150px;
}
.sig-line {
  border-top: 1px solid #94a3b8;
  padding-top: 4px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

/* ══════════════════════════════════════════════════════
   DYNAMIC SUBJECT TIERS (Screen & A4 Sizing)
   tier-few     : 1 - 4 Subjects (Large & spacious)
   tier-medium  : 5 - 7 Subjects (Standard balanced A4 fit)
   tier-compact : 8 - 10 Subjects (Compact content)
   tier-dense   : 11+ Subjects (Ultra-compact content)
   ══════════════════════════════════════════════════════ */

/* --- TIER FEW (<= 4 Subjects): Large & Spacious Content --- */
.report-card-container.tier-few {
  padding: 38px 46px;
}
.report-card-container.tier-few .report-header-banner {
  padding-bottom: 18px;
  margin-bottom: 22px;
}
.report-card-container.tier-few .report-school-title {
  font-size: 28px;
}
.report-card-container.tier-few .report-school-tagline {
  font-size: 13px;
}
.report-card-container.tier-few .report-brand-row img.school-logo-img {
  width: 68px;
  height: 68px;
}
.report-card-container.tier-few .report-session-ribbon {
  padding: 6px 24px;
  font-size: 13px;
  margin-top: 12px;
}
.report-card-container.tier-few .report-details-grid {
  padding: 16px 22px;
  margin-bottom: 22px;
  gap: 16px 28px;
  font-size: 13.5px;
}
.report-card-container.tier-few .report-table {
  margin: 20px 0 24px;
}
.report-card-container.tier-few .report-table th,
.report-card-container.tier-few .report-table td {
  padding: 13px 18px;
  font-size: 13.5px;
}
.report-card-container.tier-few .report-summary-kpi-row {
  margin-bottom: 24px;
  gap: 16px;
}
.report-card-container.tier-few .summary-kpi-card {
  padding: 15px;
}
.report-card-container.tier-few .kpi-val {
  font-size: 26px;
}
.report-card-container.tier-few .kpi-title {
  font-size: 11.5px;
}
.report-card-container.tier-few .report-remarks-card {
  padding: 14px 20px;
  margin-bottom: 34px;
  font-size: 13.5px;
}
.report-card-container.tier-few .report-signatures-row {
  padding-top: 24px;
  font-size: 12px;
}
.report-card-container.tier-few .sig-block .sig-spacer {
  height: 38px;
}
.report-card-container.tier-few .sig-crest-circle {
  width: 56px;
  height: 56px;
}

/* --- TIER MEDIUM (5 - 7 Subjects): Standard Balanced --- */
.report-card-container.tier-medium {
  padding: 26px 36px;
}
.report-card-container.tier-medium .report-header-banner {
  padding-bottom: 14px;
  margin-bottom: 16px;
}
.report-card-container.tier-medium .report-school-title {
  font-size: 24px;
}
.report-card-container.tier-medium .report-school-tagline {
  font-size: 12px;
}
.report-card-container.tier-medium .report-brand-row img.school-logo-img {
  width: 56px;
  height: 56px;
}
.report-card-container.tier-medium .report-session-ribbon {
  padding: 5px 20px;
  font-size: 11.5px;
  margin-top: 10px;
}
.report-card-container.tier-medium .report-details-grid {
  padding: 12px 18px;
  margin-bottom: 16px;
  gap: 10px 22px;
  font-size: 12.5px;
}
.report-card-container.tier-medium .report-table {
  margin: 14px 0 16px;
}
.report-card-container.tier-medium .report-table th,
.report-card-container.tier-medium .report-table td {
  padding: 9px 14px;
  font-size: 12.5px;
}
.report-card-container.tier-medium .report-summary-kpi-row {
  margin-bottom: 16px;
  gap: 12px;
}
.report-card-container.tier-medium .summary-kpi-card {
  padding: 10px 12px;
}
.report-card-container.tier-medium .kpi-val {
  font-size: 21px;
}
.report-card-container.tier-medium .kpi-title {
  font-size: 10.5px;
}
.report-card-container.tier-medium .report-remarks-card {
  padding: 10px 16px;
  margin-bottom: 22px;
  font-size: 12.5px;
}
.report-card-container.tier-medium .report-signatures-row {
  padding-top: 16px;
  font-size: 11.5px;
}
.report-card-container.tier-medium .sig-block .sig-spacer {
  height: 28px;
}
.report-card-container.tier-medium .sig-crest-circle {
  width: 48px;
  height: 48px;
}

/* --- TIER COMPACT (8 - 10 Subjects): Compact content --- */
.report-card-container.tier-compact {
  padding: 20px 30px;
}
.report-card-container.tier-compact .report-header-banner {
  padding-bottom: 10px;
  margin-bottom: 12px;
}
.report-card-container.tier-compact .report-school-title {
  font-size: 21px;
}
.report-card-container.tier-compact .report-school-tagline {
  font-size: 11px;
}
.report-card-container.tier-compact .report-brand-row img.school-logo-img {
  width: 48px;
  height: 48px;
}
.report-card-container.tier-compact .report-session-ribbon {
  padding: 4px 16px;
  font-size: 10.5px;
  margin-top: 8px;
}
.report-card-container.tier-compact .report-details-grid {
  padding: 9px 14px;
  margin-bottom: 12px;
  gap: 8px 18px;
  font-size: 11.5px;
}
.report-card-container.tier-compact .report-table {
  margin: 10px 0 12px;
}
.report-card-container.tier-compact .report-table th,
.report-card-container.tier-compact .report-table td {
  padding: 6.5px 10px;
  font-size: 11.5px;
}
.report-card-container.tier-compact .report-summary-kpi-row {
  margin-bottom: 12px;
  gap: 10px;
}
.report-card-container.tier-compact .summary-kpi-card {
  padding: 8px 10px;
}
.report-card-container.tier-compact .kpi-val {
  font-size: 18px;
}
.report-card-container.tier-compact .kpi-title {
  font-size: 9.5px;
}
.report-card-container.tier-compact .report-remarks-card {
  padding: 8px 12px;
  margin-bottom: 16px;
  font-size: 11.5px;
}
.report-card-container.tier-compact .report-signatures-row {
  padding-top: 12px;
  font-size: 11px;
}
.report-card-container.tier-compact .sig-block .sig-spacer {
  height: 20px;
}
.report-card-container.tier-compact .sig-crest-circle {
  width: 40px;
  height: 40px;
}

/* --- TIER DENSE (11+ Subjects): Ultra-Compact content --- */
.report-card-container.tier-dense {
  padding: 16px 24px;
}
.report-card-container.tier-dense .report-header-banner {
  padding-bottom: 8px;
  margin-bottom: 10px;
}
.report-card-container.tier-dense .report-school-title {
  font-size: 19px;
}
.report-card-container.tier-dense .report-school-tagline {
  font-size: 10px;
}
.report-card-container.tier-dense .report-brand-row img.school-logo-img {
  width: 40px;
  height: 40px;
}
.report-card-container.tier-dense .report-session-ribbon {
  padding: 3px 14px;
  font-size: 9.5px;
  margin-top: 6px;
}
.report-card-container.tier-dense .report-details-grid {
  padding: 7px 12px;
  margin-bottom: 10px;
  gap: 6px 14px;
  font-size: 10.5px;
}
.report-card-container.tier-dense .report-table {
  margin: 8px 0 10px;
}
.report-card-container.tier-dense .report-table th,
.report-card-container.tier-dense .report-table td {
  padding: 5px 8px;
  font-size: 10.5px;
}
.report-card-container.tier-dense .report-summary-kpi-row {
  margin-bottom: 10px;
  gap: 8px;
}
.report-card-container.tier-dense .summary-kpi-card {
  padding: 6px 8px;
}
.report-card-container.tier-dense .kpi-val {
  font-size: 16px;
}
.report-card-container.tier-dense .kpi-title {
  font-size: 9px;
}
.report-card-container.tier-dense .report-remarks-card {
  padding: 6px 10px;
  margin-bottom: 12px;
  font-size: 10.5px;
}
.report-card-container.tier-dense .report-signatures-row {
  padding-top: 10px;
  font-size: 10px;
}
.report-card-container.tier-dense .sig-block .sig-spacer {
  height: 14px;
}
.report-card-container.tier-dense .sig-crest-circle {
  width: 34px;
  height: 34px;
}

/* ══════════════════════════════════════════════════════
   STRICT A4 PRINT OVERRIDES PER TIER (Guaranteed 1 Page)
   ══════════════════════════════════════════════════════ */
@media print {
  /* TIER FEW PRINT */
  .report-card-container.tier-few {
    padding: 24px 30px !important;
  }
  .report-card-container.tier-few .report-header-banner {
    padding-bottom: 12px !important;
    margin-bottom: 16px !important;
  }
  .report-card-container.tier-few .report-school-title {
    font-size: 24px !important;
  }
  .report-card-container.tier-few .report-brand-row img.school-logo-img {
    width: 60px !important;
    height: 60px !important;
  }
  .report-card-container.tier-few .report-session-ribbon {
    padding: 5px 20px !important;
    font-size: 12px !important;
    margin-top: 10px !important;
  }
  .report-card-container.tier-few .report-details-grid {
    padding: 12px 18px !important;
    margin-bottom: 16px !important;
    gap: 10px 22px !important;
    font-size: 12.5px !important;
  }
  .report-card-container.tier-few .report-table {
    margin: 14px 0 16px !important;
  }
  .report-card-container.tier-few .report-table th,
  .report-card-container.tier-few .report-table td {
    padding: 10px 14px !important;
    font-size: 12.5px !important;
  }
  .report-card-container.tier-few .report-summary-kpi-row {
    margin-bottom: 16px !important;
    gap: 12px !important;
  }
  .report-card-container.tier-few .summary-kpi-card {
    padding: 10px 14px !important;
  }
  .report-card-container.tier-few .kpi-val {
    font-size: 22px !important;
  }
  .report-card-container.tier-few .report-remarks-card {
    padding: 10px 16px !important;
    margin-bottom: 20px !important;
    font-size: 12px !important;
  }
  .report-card-container.tier-few .report-signatures-row {
    padding-top: 14px !important;
  }
  .report-card-container.tier-few .sig-block .sig-spacer {
    height: 28px !important;
  }

  /* TIER MEDIUM PRINT (Biswajeet Verma 6 subjects) */
  .report-card-container.tier-medium {
    padding: 14px 22px !important;
  }
  .report-card-container.tier-medium .report-header-banner {
    padding-bottom: 8px !important;
    margin-bottom: 10px !important;
  }
  .report-card-container.tier-medium .report-school-title {
    font-size: 20px !important;
  }
  .report-card-container.tier-medium .report-school-tagline {
    font-size: 10.5px !important;
  }
  .report-card-container.tier-medium .report-brand-row img.school-logo-img {
    width: 48px !important;
    height: 48px !important;
  }
  .report-card-container.tier-medium .report-brand-row .verification-qr-wrap img {
    width: 44px !important;
    height: 44px !important;
  }
  .report-card-container.tier-medium .report-session-ribbon {
    padding: 3px 14px !important;
    font-size: 10px !important;
    margin-top: 6px !important;
  }
  .report-card-container.tier-medium .report-details-grid {
    padding: 8px 14px !important;
    margin-bottom: 10px !important;
    gap: 6px 16px !important;
    font-size: 11px !important;
  }
  .report-card-container.tier-medium .report-table {
    margin: 8px 0 10px !important;
  }
  .report-card-container.tier-medium .report-table th,
  .report-card-container.tier-medium .report-table td {
    padding: 6px 10px !important;
    font-size: 11px !important;
  }
  .report-card-container.tier-medium .report-summary-kpi-row {
    margin-bottom: 10px !important;
    gap: 10px !important;
  }
  .report-card-container.tier-medium .summary-kpi-card {
    padding: 7px 10px !important;
  }
  .report-card-container.tier-medium .kpi-val {
    font-size: 18px !important;
  }
  .report-card-container.tier-medium .kpi-title {
    font-size: 9.5px !important;
  }
  .report-card-container.tier-medium .report-remarks-card {
    padding: 7px 12px !important;
    margin-bottom: 12px !important;
    font-size: 10.5px !important;
  }
  .report-card-container.tier-medium .report-signatures-row {
    padding-top: 10px !important;
  }
  .report-card-container.tier-medium .sig-block .sig-spacer {
    height: 18px !important;
  }
  .report-card-container.tier-medium .sig-block .sig-crest-circle {
    width: 40px !important;
    height: 40px !important;
    font-size: 7px !important;
  }
  .report-card-container.tier-medium .sig-line {
    font-size: 10px !important;
    padding-top: 3px !important;
  }

  /* TIER COMPACT PRINT */
  .report-card-container.tier-compact {
    padding: 10px 18px !important;
  }
  .report-card-container.tier-compact .report-header-banner {
    padding-bottom: 6px !important;
    margin-bottom: 8px !important;
  }
  .report-card-container.tier-compact .report-school-title {
    font-size: 18px !important;
  }
  .report-card-container.tier-compact .report-school-tagline {
    font-size: 9.5px !important;
  }
  .report-card-container.tier-compact .report-brand-row img.school-logo-img {
    width: 42px !important;
    height: 42px !important;
  }
  .report-card-container.tier-compact .report-brand-row .verification-qr-wrap img {
    width: 38px !important;
    height: 38px !important;
  }
  .report-card-container.tier-compact .report-session-ribbon {
    padding: 2px 12px !important;
    font-size: 9px !important;
    margin-top: 4px !important;
  }
  .report-card-container.tier-compact .report-details-grid {
    padding: 6px 12px !important;
    margin-bottom: 8px !important;
    gap: 4px 12px !important;
    font-size: 10px !important;
  }
  .report-card-container.tier-compact .report-table {
    margin: 6px 0 8px !important;
  }
  .report-card-container.tier-compact .report-table th,
  .report-card-container.tier-compact .report-table td {
    padding: 4.5px 8px !important;
    font-size: 10px !important;
  }
  .report-card-container.tier-compact .report-summary-kpi-row {
    margin-bottom: 8px !important;
    gap: 8px !important;
  }
  .report-card-container.tier-compact .summary-kpi-card {
    padding: 5px 8px !important;
  }
  .report-card-container.tier-compact .kpi-val {
    font-size: 16px !important;
  }
  .report-card-container.tier-compact .kpi-title {
    font-size: 8.5px !important;
  }
  .report-card-container.tier-compact .report-remarks-card {
    padding: 5px 10px !important;
    margin-bottom: 8px !important;
    font-size: 9.5px !important;
  }
  .report-card-container.tier-compact .report-signatures-row {
    padding-top: 8px !important;
  }
  .report-card-container.tier-compact .sig-block .sig-spacer {
    height: 12px !important;
  }
  .report-card-container.tier-compact .sig-block .sig-crest-circle {
    width: 34px !important;
    height: 34px !important;
    font-size: 6px !important;
  }
  .report-card-container.tier-compact .sig-line {
    font-size: 9px !important;
    padding-top: 2px !important;
  }

  /* TIER DENSE PRINT */
  .report-card-container.tier-dense {
    padding: 8px 14px !important;
  }
  .report-card-container.tier-dense .report-header-banner {
    padding-bottom: 4px !important;
    margin-bottom: 6px !important;
  }
  .report-card-container.tier-dense .report-school-title {
    font-size: 16px !important;
  }
  .report-card-container.tier-dense .report-school-tagline {
    font-size: 9px !important;
  }
  .report-card-container.tier-dense .report-brand-row img.school-logo-img {
    width: 36px !important;
    height: 36px !important;
  }
  .report-card-container.tier-dense .report-brand-row .verification-qr-wrap img {
    width: 32px !important;
    height: 32px !important;
  }
  .report-card-container.tier-dense .report-session-ribbon {
    padding: 2px 10px !important;
    font-size: 8.5px !important;
    margin-top: 3px !important;
  }
  .report-card-container.tier-dense .report-details-grid {
    padding: 4px 10px !important;
    margin-bottom: 6px !important;
    gap: 3px 10px !important;
    font-size: 9.5px !important;
  }
  .report-card-container.tier-dense .report-table {
    margin: 4px 0 6px !important;
  }
  .report-card-container.tier-dense .report-table th,
  .report-card-container.tier-dense .report-table td {
    padding: 3.5px 6px !important;
    font-size: 9.5px !important;
  }
  .report-card-container.tier-dense .report-summary-kpi-row {
    margin-bottom: 6px !important;
    gap: 6px !important;
  }
  .report-card-container.tier-dense .summary-kpi-card {
    padding: 4px 6px !important;
  }
  .report-card-container.tier-dense .kpi-val {
    font-size: 14px !important;
  }
  .report-card-container.tier-dense .kpi-title {
    font-size: 7.5px !important;
  }
  .report-card-container.tier-dense .report-remarks-card {
    padding: 4px 8px !important;
    margin-bottom: 6px !important;
    font-size: 9px !important;
  }
  .report-card-container.tier-dense .report-signatures-row {
    padding-top: 6px !important;
  }
  .report-card-container.tier-dense .sig-block .sig-spacer {
    height: 8px !important;
  }
  .report-card-container.tier-dense .sig-block .sig-crest-circle {
    width: 28px !important;
    height: 28px !important;
    font-size: 5px !important;
  }
  .report-card-container.tier-dense .sig-line {
    font-size: 8.5px !important;
    padding-top: 1px !important;
  }
}

/* ── Marksheet Directory Table & Badge Styles (Simple & Sober - Like student-id.php) ── */
.marksheet-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.marksheet-table thead th {
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
.marksheet-table tbody td {
  padding: 11px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.84rem;
}
.marksheet-table tbody tr {
  transition: background-color 0.15s ease;
}
.marksheet-table tbody tr:hover {
  background-color: #f8fafc;
}
.marksheet-table tbody tr.active-student-row {
  background-color: #f0f9ff !important;
}
.marksheet-table tbody tr.active-student-row td:first-child {
  border-left: 3px solid #0284c7;
}

/* Badge & Tag Elements */
.ms-roll-pill {
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
.ms-std-pill {
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
.ms-blood-badge {
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
.ms-exam-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 0.74rem;
  font-weight: 600;
  white-space: nowrap;
}
.ms-perf-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 0.74rem;
  font-weight: 700;
  white-space: nowrap;
}
.ms-perf-badge.pass {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}
.ms-perf-badge.fail {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}
.ms-perf-badge.none {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #64748b;
}
.ms-status-active {
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
.ms-status-active .pulse-dot {
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
.ms-status-verified {
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
.ms-status-pending {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #fefce8;
  color: #854d0e;
  border: 1px solid #fef08a;
  font-size: 0.74rem;
  font-weight: 600;
  padding: 2px 9px;
  border-radius: 20px;
  white-space: nowrap;
}
.ms-btn-action {
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
}
.ms-btn-secondary {
  color: #334155;
  background: #ffffff;
  border: 1px solid #cbd5e1;
}
.ms-btn-secondary:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}
</style>


<!-- ══════════════════════════════════════════════════════
     TOP SCHOLAR CONTROL & EXAM SELECTION BAR
     ══════════════════════════════════════════════════════ -->
<div class="scholar-control-card no-print">
  
  <!-- Current Scholar Info Pill -->
  <div class="current-scholar-pill">
    <?php 
      $cur_has_photo = $student_profile && !empty($student_profile['photo']) && $student_profile['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $student_profile['photo']);
      $cur_first_letter = $student_profile ? strtoupper(substr($student_profile['student_name'], 0, 1)) : 'S';
      $cur_avatar_bg = $grad_palette[ord($cur_first_letter) % count($grad_palette)];
    ?>
    <?php if ($cur_has_photo): ?>
      <img src="<?php echo htmlspecialchars($student_profile['photo']); ?>" alt="" class="scholar-mini-avatar">
    <?php else: ?>
      <div class="scholar-mini-init" style="background: <?php echo $cur_avatar_bg; ?>;">
        <?php echo $cur_first_letter; ?>
      </div>
    <?php endif; ?>

    <div class="current-scholar-meta">
      <h4>
        <span><?php echo htmlspecialchars($student_profile['student_name'] ?? 'Select Scholar'); ?></span>
        <?php if ($student_profile): ?>
          <span class="roll-badge-pill" style="font-size: 0.7rem; padding: 2px 6px;">
            <?php echo htmlspecialchars($student_profile['roll_no']); ?>
          </span>
        <?php endif; ?>
      </h4>
      <div class="meta-pills">
        <span style="color: #64748b;">
          <?php echo htmlspecialchars($student_profile['standard'] ?? 'Standard'); ?> &bull; Section <?php echo htmlspecialchars($student_profile['section'] ?? 'A'); ?>
        </span>
        <span style="color: #cbd5e1;">&bull;</span>
        <a href="#student-directory" style="color: var(--hrms-primary); text-decoration: none; font-weight: 700;">
          <i class="fa-solid fa-list-check"></i> Browse Directory
        </a>
      </div>
    </div>
  </div>

  <!-- Right: Exam Selector & Action Buttons -->
  <div class="marksheet-top-actions" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
    
    <?php if (!empty($student_marksheets)): ?>
      <form method="GET" action="<?php echo ADMIN_URL; ?>/marksheet" class="marksheet-exam-form" style="display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;">
        <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
        <?php if (!empty($search)): ?>
          <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
        <?php endif; ?>
        <?php if (!empty($filter_standard)): ?>
          <input type="hidden" name="standard" value="<?php echo htmlspecialchars($filter_standard); ?>">
        <?php endif; ?>
        <?php if ($limit !== 10): ?>
          <input type="hidden" name="limit" value="<?php echo $limit; ?>">
        <?php endif; ?>
        <?php if ($page > 1): ?>
          <input type="hidden" name="page" value="<?php echo $page; ?>">
        <?php endif; ?>
        <label style="font-size: 13px; font-weight: 700; color: #475569; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; flex-shrink: 0;">
          <i class="fa-solid fa-book-bookmark" style="color: #0092df;"></i> <span>Exam:</span>
        </label>
        <select name="marksheet_id" onchange="this.form.submit()" class="hrms-input marksheet-exam-select" style="padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; border: 1.5px solid #cbd5e1; background: #f8fafc;">
          <?php foreach ($student_marksheets as $m_item): ?>
            <option value="<?php echo $m_item['id']; ?>" <?php echo ($active_marksheet && $m_item['id'] == $active_marksheet['id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($m_item['exam_name']); ?> (<?php echo $m_item['percentage']; ?>% • <?php echo $m_item['overall_grade']; ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>

    <?php if ($active_marksheet): ?>
      <button type="button" onclick="window.print();" class="action-primary-btn marksheet-print-btn" style="background: #0092df; color: #ffffff; border: none; padding: 9px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.25);">
        <i class="fa-solid fa-print"></i>
        <span>Print Report Card</span>
      </button>
    <?php endif; ?>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     OFFICIAL PRINTABLE REPORT CARD (High-End Certificate Style)
     ══════════════════════════════════════════════════════ -->
<?php if ($active_marksheet && $student_profile): ?>
  <?php 
    $subjects = json_decode($active_marksheet['subjects_json'], true) ?: [];
    $is_overall_pass = ($active_marksheet['result_status'] === 'PASS');
    $sub_count = count($subjects);
    if ($sub_count <= 4) {
        $tier_class = 'tier-few';
    } elseif ($sub_count <= 7) {
        $tier_class = 'tier-medium';
    } elseif ($sub_count <= 10) {
        $tier_class = 'tier-compact';
    } else {
        $tier_class = 'tier-dense';
    }
  ?>
  <div id="report-card-view" class="marksheet-print-canvas report-card-container <?php echo $tier_class; ?>" data-sub-count="<?php echo $sub_count; ?>">
    
    <!-- Watermark Logo -->
    <div style="position: absolute; top: 52%; left: 50%; transform: translate(-50%, -50%); opacity: 0.04; pointer-events: none; z-index: 0;">
      <img src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="" style="width: 360px; height: 360px;">
    </div>

    <!-- Official Header Section -->
    <div class="report-header-banner">
      <div class="report-brand-row">
        
        <!-- Left: Academy Crest -->
        <div style="width: 80px; text-align: left;">
          <img class="school-logo-img" src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="KSV Logo" style="width: 64px; height: 64px; border-radius: 50%; border: 2px solid #0092df; padding: 2px; background: #ffffff;">
        </div>

        <!-- Center: Institutional Identity -->
        <div style="flex: 1; text-align: center;">
          <h1 class="report-school-title">
            <?php echo htmlspecialchars($school_settings['school_name']); ?>
          </h1>
          <div class="report-school-tagline">
            <?php echo htmlspecialchars($school_settings['tagline']); ?>
          </div>
          <div class="report-school-subinfo" style="font-size: 11px; color: #64748b; margin-top: 4px;">
            <?php echo htmlspecialchars($school_settings['address']); ?> &bull; Helpline: <?php echo htmlspecialchars($school_settings['phone']); ?>
          </div>
        </div>

        <!-- Right: Verification QR Code -->
        <div class="verification-qr-wrap" style="width: 80px; text-align: right;">
          <?php if (!empty($verification_qr_url)): ?>
            <div style="display: inline-block; padding: 4px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.05);" title="Biometric QR Verification Pass">
              <img src="<?php echo $verification_qr_url; ?>" alt="Verification QR" style="width: 58px; height: 58px; display: block;">
              <span class="qr-verify-badge" style="font-size: 7.5px; font-weight: 800; color: #0284c7; text-transform: uppercase; display: block; text-align: center; margin-top: 2px;">VERIFIED</span>
            </div>
          <?php endif; ?>
        </div>

      </div>
      
      <!-- Academic Session Ribbon -->
      <div class="report-session-ribbon">
        <i class="fa-solid fa-award"></i>
        <span>STATEMENT OF ACADEMIC MARKS &bull; SESSION <?php echo htmlspecialchars($active_session['session_name']); ?></span>
      </div>
    </div>

    <!-- Student & Exam Details Box -->
    <div class="report-details-grid">
      <div class="detail-item">
        <strong>Scholar Name:</strong>
        <span style="font-size: 15px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <?php echo htmlspecialchars($student_profile['student_name']); ?>
        </span>
      </div>
      <div class="detail-item">
        <strong>Class &amp; Section:</strong>
        <span style="font-weight: 800; color: #007cbd;">
          <?php echo htmlspecialchars($student_profile['standard']); ?> &bull; Section <?php echo htmlspecialchars($student_profile['section'] ?? 'A'); ?>
        </span>
      </div>

      <div class="detail-item">
        <strong>Roll Number:</strong>
        <span style="font-family: monospace; font-weight: 800; font-size: 14px; color: #0284c7; background: #e0f2fe; padding: 1px 8px; border-radius: 4px;">
          <?php echo htmlspecialchars($student_profile['roll_no']); ?>
        </span>
      </div>
      <div class="detail-item">
        <strong>Father's Name:</strong>
        <span style="font-weight: 700;">
          <?php echo htmlspecialchars($student_profile['parent_name'] ?? '—'); ?>
        </span>
      </div>

      <div class="detail-item">
        <strong>Admission No:</strong>
        <span style="font-family: monospace; color: #475569; font-weight: 600;">
          <?php echo htmlspecialchars($student_profile['admission_no'] ?? 'ADM-2025-081'); ?>
        </span>
      </div>
      <div class="detail-item">
        <strong>Examination:</strong>
        <span style="color: #0f172a; font-weight: 800;">
          <?php echo htmlspecialchars($active_marksheet['exam_name']); ?>
          <span style="font-weight: 600; color: #64748b; font-size: 11.5px;">(<?php echo date('M Y', strtotime($active_marksheet['exam_date'])); ?>)</span>
        </span>
      </div>
    </div>

    <!-- Statement of Marks Table -->
    <div class="report-table-responsive" style="position: relative; z-index: 1;">
      <table class="report-table">
        <thead>
          <tr>
            <th style="width: 6%; text-align: center;">#</th>
            <th style="text-align: left; width: 44%;">SUBJECT TITLE</th>
            <th style="width: 14%;">MAX MARKS</th>
            <th style="width: 14%;">PASS MARKS</th>
            <th style="width: 14%;">OBTAINED</th>
            <th style="width: 14%;">GRADE</th>
          </tr>
        </thead>
        <tbody>
          <?php $idx_sub = 1; ?>
          <?php foreach ($subjects as $sub): ?>
            <tr>
              <td style="text-align: center; color: #94a3b8; font-weight: 700;"><?php echo $idx_sub++; ?></td>
              <td style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($sub['name']); ?></td>
              <td style="text-align: center; color: #64748b; font-weight: 600;"><?php echo $sub['max']; ?></td>
              <td style="text-align: center; color: #64748b; font-weight: 600;"><?php echo $sub['pass']; ?></td>
              <td style="text-align: center; font-weight: 900; font-size: 14px; font-family: 'Outfit', sans-serif; color: <?php echo $sub['obtained'] >= $sub['pass'] ? '#059669' : '#dc2626'; ?>;">
                <?php echo $sub['obtained']; ?>
              </td>
              <td style="text-align: center; font-weight: 800; color: #0284c7;">
                <span class="grade-pill-badge <?php echo $sub['obtained'] >= $sub['pass'] ? 'pass' : 'fail'; ?>">
                  <?php echo $sub['grade']; ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          <!-- Grand Total Row -->
          <tr style="background: #f8fafc; font-weight: 900; font-size: 14px; border-top: 2px solid #0092df;">
            <td colspan="2" style="text-align: left; font-weight: 900; color: #0f172a; letter-spacing: 0.04em;">
              GRAND TOTAL MARKS
            </td>
            <td style="text-align: center; color: #334155;"><?php echo $active_marksheet['total_max']; ?></td>
            <td style="text-align: center; color: #94a3b8;">—</td>
            <td style="text-align: center; color: #007cbd; font-size: 16px; font-family: 'Outfit', sans-serif;"><?php echo $active_marksheet['total_obtained']; ?></td>
            <td style="text-align: center; color: #007cbd;"><?php echo $active_marksheet['overall_grade']; ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Performance Summary KPI Row -->
    <div class="report-summary-kpi-row">
      <div class="summary-kpi-card percentage">
        <div class="kpi-title" style="color: #15803d;">OVERALL PERCENTAGE</div>
        <div class="kpi-val" style="color: #166534;"><?php echo $active_marksheet['percentage']; ?>%</div>
      </div>
      <div class="summary-kpi-card grade">
        <div class="kpi-title" style="color: #1d4ed8;">ACADEMIC GRADE</div>
        <div class="kpi-val" style="color: #1e40af;"><?php echo $active_marksheet['overall_grade']; ?></div>
      </div>
      <div class="summary-kpi-card <?php echo $is_overall_pass ? 'pass' : 'fail'; ?>">
        <div class="kpi-title" style="color: <?php echo $is_overall_pass ? '#15803d' : '#991b1b'; ?>;">EXAMINATION RESULT</div>
        <div class="kpi-val" style="color: <?php echo $is_overall_pass ? '#166534' : '#b91c1c'; ?>; display: flex; align-items: center; justify-content: center; gap: 6px;">
          <i class="fa-solid <?php echo $is_overall_pass ? 'fa-circle-check' : 'fa-circle-xmark'; ?>" style="font-size: 1.1rem;"></i>
          <span><?php echo $active_marksheet['result_status']; ?></span>
        </div>
      </div>
    </div>

    <!-- Remarks Callout -->
    <div class="report-remarks-card">
      <i class="fa-solid fa-quote-left"></i>
      <div>
        <strong>Faculty Evaluation &amp; Discipline Remarks:</strong>
        <div style="margin-top: 3px; font-style: italic; color: #475569;">
          "<?php echo htmlspecialchars($active_marksheet['remarks'] ?? 'Consistent academic effort, good participation and disciplined conduct throughout.'); ?>"
        </div>
      </div>
    </div>

    <!-- Signatures and Official Seals -->
    <div class="report-signatures-row">
      <div class="sig-block">
        <div class="sig-spacer" style="height: 38px;"></div>
        <div class="sig-line">Class Teacher</div>
      </div>
      
      <div class="sig-block">
        <div class="sig-crest-circle" style="width: 54px; height: 54px; border: 2px dashed #0092df; border-radius: 50%; margin: 0 auto 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 8px; color: #0092df; font-weight: 900; line-height: 1.2;">
          <span>KSV</span>
          <span style="font-size: 6.5px; color: #64748b;">OFFICIAL</span>
        </div>
        <div class="sig-crest-lbl" style="font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase;">Institutional Crest</div>
      </div>

      <div class="sig-block">
        <div class="sig-principal-name" style="font-family: 'Outfit', cursive; font-size: 16px; color: #007cbd; height: 28px; font-weight: 800;">Salam Khan</div>
        <div class="sig-line">Principal Signature</div>
      </div>
    </div>

  </div>
<?php else: ?>
  <div id="report-card-view" class="hrms-card" style="text-align: center; padding: 46px 20px; margin-bottom: 28px; border-radius: 14px; border: 1.5px dashed #cbd5e1;">
    <div style="width: 58px; height: 58px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; font-size: 1.6rem;">
      <i class="fa-solid fa-file-circle-question"></i>
    </div>
    <h3 style="margin: 0 0 6px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
      No Marksheet Record Generated Yet
    </h3>
    <p style="margin: 0 0 18px; color: #64748b; font-size: 0.9rem; max-width: 500px; margin-left: auto; margin-right: auto;">
      Scholar <strong><?php echo htmlspecialchars($student_profile['student_name'] ?? 'Selected Scholar'); ?></strong> does not have an evaluation for Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
    </p>
    <?php if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff'): ?>
      <button type="button" onclick="openCreateMarksheetModal(<?php echo $selected_student_id; ?>);" class="action-primary-btn" style="background: #0092df; color: #fff; padding: 10px 22px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(0, 146, 223, 0.25);">
        <i class="fa-solid fa-plus-circle"></i> Generate First Marksheet Now
      </button>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     FILTER TOOLBAR (Exact match to student-id.php & students.php)
     ══════════════════════════════════════════════════════ -->
<div id="student-directory" class="roaster-toolbar-card no-print" style="margin-top: 30px; margin-bottom: 20px;">
  <div class="roaster-toolbar-inner">
    
    <!-- Filter form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/marksheet" class="roaster-filter-form">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      <?php if ($selected_student_id > 0): ?>
        <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
      <?php endif; ?>
      <?php if ($selected_marksheet_id > 0): ?>
        <input type="hidden" name="marksheet_id" value="<?php echo $selected_marksheet_id; ?>">
      <?php endif; ?>

      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student name, roll no, phone..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_marksheet_pagination_url(1, '', $filter_standard, $selected_student_id, $selected_marksheet_id, $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

        <div class="marksheet-filter-row" style="display: flex; gap: 8px; align-items: center;">
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
            <a href="<?php echo ADMIN_URL; ?>/marksheet<?php 
              $rp = [];
              if ($selected_student_id > 0) $rp['student_id'] = $selected_student_id;
              if ($selected_marksheet_id > 0) $rp['marksheet_id'] = $selected_marksheet_id;
              if ($limit !== 10) $rp['limit'] = $limit;
              echo !empty($rp) ? '?' . http_build_query($rp) : '';
            ?>" class="roaster-btn ghost" title="Reset all filters">
              <i class="fa-solid fa-rotate-left"></i> Reset
            </a>
          <?php endif; ?>
        </div>
      </form>

      <!-- Right: Scholars Live Count & Quick Switch to ID Cards -->
      <div class="roaster-toolbar-actions">
        <div class="scholars-count-badge">
          <span class="count-dot"></span>
          <span><strong><?php echo $total_records; ?></strong> Scholars</span>
        </div>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       STUDENT DATA TABLE (Simple & Sober - Like student-id.php)
       ══════════════════════════════════════════════════════ -->
  <div class="hrms-card no-print marksheet-directory-card" id="marksheetDirectoryCard" style="margin-bottom: 30px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
    <div class="hrms-card-header marksheet-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
      <div class="marksheet-title-block">
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-graduation-cap" style="color: #0284c7;"></i>
          Scholar Academic Directory &amp; Marksheet Register
          <span class="marksheet-session-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
            Session <?php echo htmlspecialchars($active_session['session_name']); ?>
          </span>
        </h3>
        <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
          Click <strong>View Report Card</strong> on any student row below to load their official report card above.
        </p>
      </div>

      <div class="marksheet-actions-wrap" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <span class="marksheet-records-count" style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
          Total Records: <strong style="color: #0f172a;"><?php echo $total_records; ?></strong>
        </span>
        <?php if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff'): ?>
          <button type="button" onclick="openCreateMarksheetModal(<?php echo $selected_student_id; ?>);" class="ms-btn-action ms-btn-secondary ms-generate-btn" style="font-size: 0.76rem; gap: 5px;">
            <i class="fa-solid fa-file-circle-plus" style="color: #059669;"></i>
            <span>New Evaluation</span>
          </button>
        <?php endif; ?>
      </div>
    </div>

  <div class="table-responsive">
    <table class="hrms-table marksheet-table">
      <thead>
        <tr>
          <th style="min-width: 165px;">Student Profile</th>
          <th style="min-width: 105px;">Roll &amp; Admission</th>
          <th style="min-width: 115px;">Standard &amp; Section</th>
          <th style="min-width: 115px;">Guardian Info</th>
          <th style="min-width: 120px;">Evaluations / Exam</th>
          <th style="min-width: 120px;">Latest Performance</th>
          <th style="min-width: 85px;">Status</th>
          <th style="min-width: 130px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($table_students)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-user-slash"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Scholars Matching Filters</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                No student records matched your query in Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
              </p>
              <a href="<?php echo ADMIN_URL; ?>/marksheet<?php echo $selected_student_id > 0 ? '?student_id=' . $selected_student_id : ''; ?>" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                <i class="fa-solid fa-rotate-left"></i> Reset All Filters
              </a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($table_students as $stu): ?>
            <?php 
              $is_cur_viewing = ($stu['id'] == $selected_student_id);
              $has_photo = !empty($stu['photo']) && $stu['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $stu['photo']);
              $first_letter = strtoupper(substr($stu['student_name'], 0, 1));

              // Marksheet statistics for this student
              $ms_info = $stu_ms_summary[$stu['id']] ?? null;
              $has_ms = !empty($ms_info) && $ms_info['total_marksheets'] > 0;
              $is_pass = $has_ms && ($ms_info['latest_status'] === 'PASS');

              $switch_url = ADMIN_URL . '/marksheet?student_id=' . $stu['id'] . 
                            (!empty($search) ? '&search=' . urlencode($search) : '') . 
                            (!empty($filter_standard) ? '&standard=' . urlencode($filter_standard) : '') . 
                            ($limit !== 10 ? '&limit=' . $limit : '') .
                            ($page > 1 ? '&page=' . $page : '') .
                            '#report-card-view';
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
                      <span class="ms-blood-badge">Blood: <?php echo htmlspecialchars($stu['blood_group'] ?? 'O+'); ?></span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 2. Roll & Admission -->
              <td>
                <span class="ms-roll-pill">
                  <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
                  <span><?php echo htmlspecialchars($stu['roll_no']); ?></span>
                </span>
                <div style="font-size: 0.74rem; color: #64748b; margin-top: 4px;">
                  Adm: <?php echo htmlspecialchars($stu['admission_no'] ?? '—'); ?>
                </div>
              </td>

              <!-- 3. Standard & Section -->
              <td>
                <span class="ms-std-pill">
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

              <!-- 5. Evaluations / Exam Info -->
              <td>
                <?php if ($has_ms): ?>
                  <span class="ms-exam-chip" title="Evaluations Generated">
                    <i class="fa-solid fa-file-lines" style="color: #0284c7; font-size: 0.72rem;"></i>
                    <span><?php echo $ms_info['total_marksheets']; ?> <?php echo $ms_info['total_marksheets'] == 1 ? 'Evaluation' : 'Evaluations'; ?></span>
                  </span>
                  <div style="font-size: 0.72rem; color: #64748b; margin-top: 3px; max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($ms_info['latest_exam_name']); ?>">
                    <?php echo htmlspecialchars($ms_info['latest_exam_name']); ?>
                  </div>
                <?php else: ?>
                  <span style="font-size: 0.74rem; color: #94a3b8; font-style: italic; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fa-regular fa-folder-open"></i> No Marksheet
                  </span>
                <?php endif; ?>
              </td>

              <!-- 6. Latest Performance -->
              <td>
                <?php if ($has_ms): ?>
                  <div>
                    <span class="ms-perf-badge <?php echo $is_pass ? 'pass' : 'fail'; ?>">
                      <i class="fa-solid <?php echo $is_pass ? 'fa-check' : 'fa-xmark'; ?>" style="font-size: 0.65rem;"></i>
                      <span><?php echo $ms_info['latest_percentage']; ?>% &bull; <?php echo $ms_info['latest_grade']; ?></span>
                    </span>
                  </div>
                  <div style="font-size: 0.70rem; font-weight: 700; color: <?php echo $is_pass ? '#166534' : '#991b1b'; ?>; margin-top: 3px;">
                    <?php echo $ms_info['latest_status']; ?>
                  </div>
                <?php else: ?>
                  <span class="ms-perf-badge none">
                    <i class="fa-solid fa-minus" style="font-size: 0.65rem;"></i>
                    <span>Pending</span>
                  </span>
                <?php endif; ?>
              </td>

              <!-- 7. Status -->
              <td>
                <?php if ($is_cur_viewing): ?>
                  <span class="ms-status-active">
                    <span class="pulse-dot"></span> In Preview
                  </span>
                <?php elseif ($has_ms): ?>
                  <span class="ms-status-verified">
                    <i class="fa-solid fa-check" style="font-size: 0.65rem;"></i> Generated
                  </span>
                <?php else: ?>
                  <span class="ms-status-pending">
                    <i class="fa-solid fa-clock" style="font-size: 0.65rem;"></i> Not Created
                  </span>
                <?php endif; ?>
              </td>

              <!-- 8. Actions -->
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 5px; justify-content: flex-end; align-items: center;">
                  <?php if ($is_cur_viewing): ?>
                    <button type="button" onclick="document.getElementById('report-card-view').scrollIntoView({behavior: 'smooth'});" class="ms-btn-action ms-btn-secondary" style="background: #f0f9ff; border-color: #bae6fd; color: #0284c7;" title="Scroll up to current report card">
                      <i class="fa-solid fa-eye" style="font-size: 0.72rem;"></i>
                      <span>Viewing</span>
                    </button>
                    <?php if ($active_marksheet): ?>
                      <button type="button" onclick="window.print();" class="ms-btn-action ms-btn-secondary" title="Print this Report Card">
                        <i class="fa-solid fa-print" style="color: #0284c7; font-size: 0.72rem;"></i>
                        <span>Print</span>
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <a href="<?php echo $switch_url; ?>" class="ms-btn-action ms-btn-secondary" title="Load student report card into preview">
                      <i class="fa-solid fa-file-lines" style="color: #0284c7; font-size: 0.72rem;"></i>
                      <span>View Report Card</span>
                    </a>
                  <?php endif; ?>

                  <?php if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff'): ?>
                    <button type="button" onclick="openCreateMarksheetModal(<?php echo $stu['id']; ?>);" class="ms-btn-action ms-btn-secondary" style="padding: 5px 8px;" title="Create New Marksheet for this scholar">
                      <i class="fa-solid fa-plus" style="color: #059669; font-size: 0.72rem;"></i>
                    </button>
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
            <option value="<?php echo htmlspecialchars(get_marksheet_pagination_url(1, $search, $filter_standard, $selected_student_id, $selected_marksheet_id, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Scholar Academic Directory Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_marksheet_pagination_url($page - 1, $search, $filter_standard, $selected_student_id, $selected_marksheet_id, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_marksheet_pagination_url($p, $search, $filter_standard, $selected_student_id, $selected_marksheet_id, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_marksheet_pagination_url($page + 1, $search, $filter_standard, $selected_student_id, $selected_marksheet_id, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Session info & evaluation summary -->
    <div style="font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 12px; margin-left: auto;">
      <span>Total Evaluations: <strong style="color: #0284c7;"><?php echo $total_marksheets_count; ?></strong></span>
      <span style="color: #cbd5e1;">|</span>
      <span>Session: <strong style="color: #334155;"><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: Create New Marksheet Form (With Dynamic Calculation)
     ══════════════════════════════════════════════════════ -->
<div id="createMarksheetModal" class="hrms-modal-overlay" onclick="if (event.target === this) closeCreateMarksheetModal();" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px); overflow-y: auto; padding: 20px;">
  <div class="hrms-modal-content" style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 720px; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); max-height: 92vh; overflow-y: auto; border: 1px solid #e2e8f0;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 14px;">
      <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-file-circle-plus" style="color: #0092df;"></i>
        <span>Generate Academic Statement of Marks</span>
      </h3>
      <button type="button" onclick="closeCreateMarksheetModal();" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/marksheet" id="marksheetGenForm">
      <input type="hidden" name="action" value="create_marksheet">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
        <div class="form-group">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Enrolled Scholar *</label>
          <select name="student_id" id="modalStudentSelect" class="hrms-input" required style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
            <?php foreach ($all_students_list as $s): ?>
              <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $selected_student_id ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($s['student_name']); ?> (<?php echo htmlspecialchars($s['roll_no']); ?> &bull; <?php echo htmlspecialchars($s['standard']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Examination Title *</label>
          <select name="exam_name" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
            <option value="Unit Test 1">Unit Test 1</option>
            <option value="First Term Mid-Term Examination">First Term Mid-Term Examination</option>
            <option value="Unit Test 2">Unit Test 2</option>
            <option value="Annual Board Examination">Annual Board Examination</option>
            <option value="Pre-Board Mock Evaluation">Pre-Board Mock Evaluation</option>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Evaluation &amp; Issue Date</label>
        <input type="date" name="exam_date" value="<?php echo date('Y-m-d'); ?>" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px;">
      </div>

      <!-- Subject Wise Statement Inputs -->
      <div style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <label style="font-size: 13px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-book-open" style="color: #0092df;"></i> Subject Wise Statement of Marks:
          </label>
          <button type="button" onclick="addSubjectRow();" style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
            <i class="fa-solid fa-plus"></i> Add Subject
          </button>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;" id="modalSubjectsTable">
          <thead>
            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
              <th style="padding: 10px 12px; text-align: left; font-weight: 800; color: #475569;">Subject Name</th>
              <th style="padding: 10px 12px; text-align: center; width: 22%; font-weight: 800; color: #475569;">Max Marks</th>
              <th style="padding: 10px 12px; text-align: center; width: 24%; font-weight: 800; color: #475569;">Marks Obtained</th>
              <th style="padding: 10px 6px; width: 8%; text-align: center;"></th>
            </tr>
          </thead>
          <tbody id="modalSubjectsBody">
            <?php 
              $default_subjects = ['Mathematics', 'Physics / Science', 'Chemistry / Social Studies', 'English Core', 'Computer Science / IT', 'Hindi / Language'];
              foreach ($default_subjects as $idx => $subj):
            ?>
              <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 6px 10px;">
                  <input type="text" name="subjects[]" value="<?php echo $subj; ?>" class="hrms-input sub-name-input" required style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                </td>
                <td style="padding: 6px 10px;">
                  <input type="number" name="max_marks[]" value="100" min="1" class="hrms-input sub-max-input" oninput="recalcModalTotals();" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; font-size: 13px; font-weight: 600;">
                </td>
                <td style="padding: 6px 10px;">
                  <input type="number" step="0.5" name="obtained[]" value="<?php echo rand(75, 96); ?>" min="0" class="hrms-input sub-obt-input" oninput="recalcModalTotals();" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; font-weight: 800; font-size: 13px; color: #0284c7;">
                </td>
                <td style="padding: 6px 6px; text-align: center;">
                  <button type="button" onclick="removeSubjectRow(this);" style="background: none; border: none; color: #ef4444; font-size: 14px; cursor: pointer;" title="Remove row">&times;</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Dynamic Calculation Preview Ribbon -->
        <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 10px 16px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
          <div>
            <span style="color: #0369a1; font-weight: 700;">Total Score: </span>
            <strong id="calcTotalObtained" style="color: #0f172a; font-size: 15px;">0</strong> / <span id="calcTotalMax" style="color: #64748b;">0</span>
          </div>
          <div>
            <span style="color: #0369a1; font-weight: 700;">Percentage: </span>
            <strong id="calcPercentage" style="color: #0284c7; font-size: 16px;">0%</strong>
          </div>
          <div>
            <span style="color: #0369a1; font-weight: 700;">Predicted Grade: </span>
            <span id="calcGradeBadge" class="grade-pill-badge pass" style="font-size: 12px;">A</span>
          </div>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 22px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Faculty Remarks &amp; Feedback</label>
        <textarea name="remarks" rows="2" class="hrms-input" placeholder="e.g. Excellent conceptual understanding and disciplined work ethic." style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" onclick="closeCreateMarksheetModal();" class="action-btn" style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 9px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; color: #475569;">Cancel</button>
        <button type="submit" class="action-primary-btn" style="background: #0092df; color: #ffffff; border: none; padding: 9px 24px; border-radius: 8px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.25);">Save &amp; Generate Report Card</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateMarksheetModal(studentId = null) {
  const modal = document.getElementById('createMarksheetModal');
  if (!modal) return;
  if (studentId) {
    const sel = document.getElementById('modalStudentSelect');
    if (sel) sel.value = studentId;
  }
  modal.style.display = 'flex';
  recalcModalTotals();
}

function closeCreateMarksheetModal() {
  const modal = document.getElementById('createMarksheetModal');
  if (modal) modal.style.display = 'none';
}

function addSubjectRow() {
  const tbody = document.getElementById('modalSubjectsBody');
  const tr = document.createElement('tr');
  tr.style.borderBottom = '1px solid #f1f5f9';
  tr.innerHTML = `
    <td style="padding: 6px 10px;">
      <input type="text" name="subjects[]" placeholder="Enter Subject Title" class="hrms-input sub-name-input" required style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
    </td>
    <td style="padding: 6px 10px;">
      <input type="number" name="max_marks[]" value="100" min="1" class="hrms-input sub-max-input" oninput="recalcModalTotals();" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; font-size: 13px; font-weight: 600;">
    </td>
    <td style="padding: 6px 10px;">
      <input type="number" step="0.5" name="obtained[]" value="85" min="0" class="hrms-input sub-obt-input" oninput="recalcModalTotals();" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; font-weight: 800; font-size: 13px; color: #0284c7;">
    </td>
    <td style="padding: 6px 6px; text-align: center;">
      <button type="button" onclick="removeSubjectRow(this);" style="background: none; border: none; color: #ef4444; font-size: 14px; cursor: pointer;" title="Remove row">&times;</button>
    </td>
  `;
  tbody.appendChild(tr);
  recalcModalTotals();
}

function removeSubjectRow(btn) {
  const row = btn.closest('tr');
  if (row) {
    row.remove();
    recalcModalTotals();
  }
}

function recalcModalTotals() {
  const maxInputs = document.querySelectorAll('.sub-max-input');
  const obtInputs = document.querySelectorAll('.sub-obt-input');
  let totalMax = 0;
  let totalObt = 0;

  for (let i = 0; i < maxInputs.length; i++) {
    const m = parseFloat(maxInputs[i].value) || 0;
    const o = parseFloat(obtInputs[i]?.value) || 0;
    totalMax += m;
    totalObt += o;
  }

  const pct = totalMax > 0 ? ((totalObt / totalMax) * 100).toFixed(2) : 0;
  let grade = 'F';
  let isPass = pct >= 35;

  if (pct >= 90) grade = 'A+';
  else if (pct >= 80) grade = 'A';
  else if (pct >= 70) grade = 'B+';
  else if (pct >= 60) grade = 'B';
  else if (pct >= 50) grade = 'C';
  else if (pct >= 35) grade = 'D';

  document.getElementById('calcTotalObtained').innerText = totalObt;
  document.getElementById('calcTotalMax').innerText = totalMax;
  document.getElementById('calcPercentage').innerText = pct + '%';

  const badge = document.getElementById('calcGradeBadge');
  if (badge) {
    badge.innerText = grade + (isPass ? ' (PASS)' : ' (FAIL)');
    badge.className = 'grade-pill-badge ' + (isPass ? 'pass' : 'fail');
  }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeCreateMarksheetModal();
  }
});

// Run initial calculation
document.addEventListener('DOMContentLoaded', recalcModalTotals);
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
