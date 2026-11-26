<?php
/**
 * KSV Academy HRMS - Student / Parent Study Notes Portal
 * Designed to exactly match the Student Overview (dashboard.php) design, cards, and structure.
 * Strictly lists private study notes & reference materials for enrolled scholars (Secure Read-Only View).
 */

// Defense-in-depth HTTP security headers
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
if (in_array($current_user['role'], ['student', 'parent'], true)) {
    if (!has_menu_permission('notes') && !has_menu_permission('note-record')) {
        set_flash('error', 'You do not have permission to view study notes.');
        header('Location: ' . PARENT_URL . '/dashboard');
        exit;
    }
}

// Session-based request rate limiting (Max 120 page requests per minute)
$now = time();
if (!isset($_SESSION['parent_notes_req_attempts']) || !is_array($_SESSION['parent_notes_req_attempts'])) {
    $_SESSION['parent_notes_req_attempts'] = [];
}
$_SESSION['parent_notes_req_attempts'] = array_filter($_SESSION['parent_notes_req_attempts'], function($timestamp) use ($now) {
    return ($now - (int)$timestamp) < 60;
});
if (count($_SESSION['parent_notes_req_attempts']) >= 120) {
    http_response_code(429);
    exit('Rate limit exceeded. Please wait a moment before trying again.');
}
$_SESSION['parent_notes_req_attempts'][] = $now;

$page_title = 'Study Notes & Materials | KSV Academy HRMS';
$page_heading = 'Study Notes';
$page_subheading = 'Official Academic Notes, Reference Materials & Syllabus Documents';
$active_menu = 'notes';

$db = get_db_connection();
ensure_note_records_table($db);

$active_session = get_active_session();
$session_id = $active_session ? intval($active_session['id']) : 2;
$school_settings = get_school_settings();

// ── Student Resolution (Identical to dashboard.php) ──
$student = null;
$selected_student_id = intval($_GET['student_id'] ?? ($_GET['id'] ?? 0));

// Check for Impersonation Session (Super Admin only)
if (!empty($_SESSION['impersonated_student']) && $current_user['role'] === 'super_admin') {
    $s_id = intval($_SESSION['impersonated_student']['id']);
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$s_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) $selected_student_id = intval($student['id']);
}

// 1. Direct query parameter override (?student_id=X)
if ($selected_student_id > 0 && !$student) {
    if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$selected_student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) $_SESSION['active_student_id'] = intval($student['id']);
    } elseif ($current_user['role'] === 'parent') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$selected_student_id, $current_user['id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) $_SESSION['active_student_id'] = intval($student['id']);
    }
}

// 2. Role Specific Resolution
$parent_children = [];
$students_list = [];
if ($current_user['role'] === 'student') {
    $stmt = $db->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$current_user['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student && (!empty($current_user['username']) || !empty($current_user['full_name']))) {
        $stmt = $db->prepare("SELECT * FROM students WHERE (roll_no = ? AND roll_no != '') OR (user_id IS NULL AND student_name = ?) LIMIT 1");
        $stmt->execute([$current_user['username'], $current_user['full_name']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($student) {
        $_SESSION['active_student_id'] = intval($student['id']);
        $selected_student_id = intval($student['id']);
    }
} elseif ($current_user['role'] === 'parent') {
    $c_stmt = $db->prepare("SELECT id, student_name, roll_no, standard, photo, section FROM students WHERE parent_user_id = ? ORDER BY id ASC");
    $c_stmt->execute([$current_user['id']]);
    $parent_children = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($student) && !empty($_SESSION['active_student_id'])) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([intval($_SESSION['active_student_id']), $current_user['id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (empty($student) && !empty($parent_children)) {
        $first_child_id = intval($parent_children[0]['id']);
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$first_child_id, $current_user['id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) $_SESSION['active_student_id'] = intval($student['id']);
    }
    if ($student) $selected_student_id = intval($student['id']);
} else {
    // Super admin / Staff selector list
    $all_students_stmt = $db->prepare("SELECT id, student_name, roll_no, standard FROM students WHERE session_id = ? ORDER BY standard ASC, student_name ASC");
    $all_students_stmt->execute([$session_id]);
    $students_list = $all_students_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($selected_student_id === 0 && !empty($students_list)) {
        $selected_student_id = intval($students_list[0]['id']);
    }
    if ($selected_student_id > 0 && !$student) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$selected_student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$student_id = $student ? intval($student['id']) : 0;
$student_standard = $student['standard'] ?? '';

// Check student photo (Matching dashboard.php)
$has_student_photo = false;
$student_photo_url = '';
if (!empty($student['photo']) && $student['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $student['photo'])) {
    $has_student_photo = true;
    $student_photo_url = $student['photo'];
}

// Academic Standards for Dropdown
$academic_categories = get_default_academic_categories();
$flat_predefined_standards = [];
foreach ($academic_categories as $cat => $std_list) {
    foreach ($std_list as $s_item) {
        $flat_predefined_standards[] = $s_item;
    }
}
$db_standards = $db->query("SELECT DISTINCT standard FROM students WHERE standard IS NOT NULL AND standard != ''")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$note_db_standards = $db->query("SELECT DISTINCT standard FROM note_records WHERE visibility = 'private' AND standard IS NOT NULL AND standard != '' AND standard NOT IN ('All Classes / General', 'All Standards / General')")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$all_db_standards = array_values(array_unique(array_merge($db_standards, $note_db_standards)));
$custom_db_standards = array_values(array_diff($all_db_standards, $flat_predefined_standards));

// ── Filters & Search ──
$search = trim((string)($_GET['search'] ?? ''));
$filter_standard = trim((string)($_GET['standard'] ?? ''));
$filter_subject = trim((string)($_GET['subject'] ?? 'all'));
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, max(5, intval($_GET['limit'] ?? 10)));

// Distinct subjects in private notes
$subjects_list = $db->query("SELECT DISTINCT subject FROM note_records WHERE visibility = 'private' AND subject IS NOT NULL AND subject != '' ORDER BY subject ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];

// ── Query: STRICTLY LIST PRIVATE NOTES ONLY ──
$where = ["n.visibility = 'private'"];
$params = [];

if (!empty($filter_standard) && $filter_standard !== 'all') {
    $where[] = "(n.standard = ? OR n.standard IN ('All Classes / General', 'All Standards / General'))";
    $params[] = $filter_standard;
}

if ($filter_subject !== 'all' && $filter_subject !== '') {
    $where[] = "n.subject = ?";
    $params[] = $filter_subject;
}

if (!empty($search)) {
    $where[] = "(n.title LIKE ? OR n.subject LIKE ? OR n.standard LIKE ? OR n.file_name LIKE ? OR n.description LIKE ?)";
    $like = "%" . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . "%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$where_sql = implode(' AND ', $where);

// Count
$count_stmt = $db->prepare("SELECT COUNT(*) FROM note_records n WHERE {$where_sql}");
$count_stmt->execute($params);
$total_records = intval($count_stmt->fetchColumn());

$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch records
$notes_sql = "
    SELECT n.*, 
           COALESCE(u.full_name, u.username, 'Academy Faculty') AS author_name 
    FROM note_records n 
    LEFT JOIN users u ON n.uploaded_by = u.id 
    WHERE {$where_sql} 
    ORDER BY n.id DESC 
    LIMIT {$limit} OFFSET {$offset}
";
$notes_stmt = $db->prepare($notes_sql);
$notes_stmt->execute($params);
$notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('format_note_bytes')) {
    function format_note_bytes($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ══════════════════════════════════════════════════════════════════════
   PARENT / STUDENT PORTAL STYLES (Identical to dashboard.php Student Overview)
   ══════════════════════════════════════════════════════════════════════ */

/* 4 Academic & Study KPI Summary Cards */
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

/* Stat Cards (Matching dashboard.php / hrms.css) */
.stat-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  position: relative;
  overflow: hidden;
}

.stat-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.stat-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.stat-info {
  display: flex;
  flex-direction: column;
}

.stat-label {
  font-size: 0.72rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 6px;
}

.stat-number {
  font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  font-size: 1.75rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.15;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.stat-icon-wrapper {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  background: #f8fafc;
  color: #475569;
  border: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.stat-card.blue .stat-icon-wrapper { background: #f0f9ff; color: #0284c7; border-color: #e0f2fe; }
.stat-card.green .stat-icon-wrapper { background: #f0fdf4; color: #16a34a; border-color: #dcfce7; }
.stat-card.purple .stat-icon-wrapper { background: #f5f3ff; color: #7c3aed; border-color: #ede9fe; }
.stat-card.amber .stat-icon-wrapper { background: #fffbeb; color: #d97706; border-color: #fef3c7; }

.stat-card-progress {
  width: 100%;
  height: 4px;
  background: #f1f5f9;
  border-radius: 2px;
  overflow: hidden;
  margin: 10px 0 8px;
}

.stat-card-progress-bar {
  height: 100%;
  border-radius: 2px;
}

.stat-card.blue .stat-card-progress-bar { background: #0284c7; }
.stat-card.green .stat-card-progress-bar { background: #16a34a; }
.stat-card.purple .stat-card-progress-bar { background: #7c3aed; }
.stat-card.amber .stat-card-progress-bar { background: #d97706; }

.stat-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.72rem;
  color: #64748b;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid #f1f5f9;
}

/* Main Workstation Card (Matching dashboard.php .periods-card) */
.periods-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
  overflow: hidden;
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
}

.periods-card-header {
  padding: 14px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #ffffff;
  flex-wrap: wrap;
}

.periods-card-header h3.card-title {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
}

.periods-card-footer {
  margin-top: auto;
  padding: 12px 20px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.76rem;
  color: #64748b;
  flex-wrap: wrap;
  gap: 8px;
}

/* Filter Toolbar (Clean & Integrated) */
.notes-filter-toolbar {
  padding: 12px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.notes-search-box {
  position: relative;
  flex: 1;
  min-width: 220px;
}

.notes-search-box i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 0.82rem;
}

.notes-search-input {
  width: 100%;
  padding: 7px 12px 7px 34px;
  font-size: 0.80rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  transition: all 0.2s ease;
}

.notes-search-input:focus {
  border-color: #0284c7;
  outline: none;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.notes-select-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.notes-select-wrapper {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.notes-select-ctrl {
  padding: 7px 28px 7px 12px;
  font-size: 0.78rem;
  font-weight: 600;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
  appearance: none;
  min-width: 150px;
  max-width: 230px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.notes-select-ctrl:focus {
  border-color: #0284c7;
  outline: none;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.notes-select-wrapper i.arrow {
  position: absolute;
  right: 10px;
  color: #94a3b8;
  font-size: 0.68rem;
  pointer-events: none;
}

/* Sober Directory Table (Matching staff-id-table in dashboard.php) */
.staff-id-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: auto;
}

.staff-id-table thead th {
  background: #f8fafc !important;
  color: #475569 !important;
  font-size: 0.68rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.04em !important;
  padding: 10px 14px !important;
  border-bottom: 1px solid #e2e8f0 !important;
  white-space: nowrap !important;
}

.staff-id-table tbody td {
  padding: 12px 14px !important;
  border-bottom: 1px solid #f1f5f9 !important;
  vertical-align: middle !important;
  font-size: 0.78rem !important;
  color: #1e293b !important;
}

.staff-id-table tbody tr {
  transition: background-color 0.15s ease;
}

.staff-id-table tbody tr:hover td {
  background-color: #f8fafc !important;
}

.staff-id-table tbody tr:last-child td {
  border-bottom: none !important;
}

/* File Icon Badges */
.file-badge {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.file-badge.pdf { background: #fee2e2; color: #dc2626; }
.file-badge.image { background: #ecfdf5; color: #059669; }
.file-badge.doc { background: #eff6ff; color: #0284c7; }

/* Subject & Standard Pills */
.subject-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 6px;
  font-size: 0.74rem;
  font-weight: 700;
  background: #f1f5f9;
  color: #334155;
  border: 1px solid #e2e8f0;
  white-space: nowrap;
}

.standard-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 6px;
  font-size: 0.73rem;
  font-weight: 700;
  background: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
  white-space: nowrap;
}

/* Secure View Button (Only Action Button) */
.note-view-btn {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
  color: #ffffff;
  border: none;
  font-weight: 700;
  font-size: 0.78rem;
  padding: 6px 14px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
  transition: all 0.15s ease;
  text-decoration: none;
}

.note-view-btn:hover {
  background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
  box-shadow: 0 4px 10px rgba(2, 132, 199, 0.35);
  transform: translateY(-1px);
  color: #ffffff;
}

/* Modal Note Viewer (Protected Read-Only) */
#noteViewerModal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 15, 25, 0.75);
  backdrop-filter: blur(4px);
  z-index: 1050;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

#noteViewerModal.show {
  display: flex !important;
}

#noteViewerModal .viewer-modal-card {
  width: 100%;
  max-width: 920px;
  max-height: calc(100vh - 32px);
  max-height: calc(100dvh - 32px);
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.4);
  margin: auto;
}

#noteViewerModal .viewer-modal-header {
  flex-shrink: 0;
  padding: 14px 20px;
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

#noteViewerModal .viewer-modal-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 16px;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
}

#noteViewerModal .viewer-modal-footer {
  flex-shrink: 0;
  padding: 12px 20px;
  background: #ffffff;
  border-top: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME SPECIFICS (Matching dashboard.php Student Overview)
   ══════════════════════════════════════════════════════════════════════ */
[data-theme="dark"] .periods-card,
[data-theme="dark"] .periods-card-header {
  background: #1e293b !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .periods-card-header h3.card-title {
  color: #f8fafc !important;
}

/* 4 Academic & Financial KPI Cards */
[data-theme="dark"] .periods-stat-grid .stat-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .periods-stat-grid .stat-label {
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-stat-grid .stat-number {
  color: #f8fafc !important;
}

[data-theme="dark"] .periods-stat-grid .stat-card-footer {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-stat-grid .stat-card-progress {
  background: #334155 !important;
}

[data-theme="dark"] .periods-stat-grid .stat-card.blue .stat-icon-wrapper {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border: 1px solid rgba(2, 132, 199, 0.3) !important;
}

[data-theme="dark"] .periods-stat-grid .stat-card.green .stat-icon-wrapper {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border: 1px solid rgba(16, 185, 129, 0.3) !important;
}

[data-theme="dark"] .periods-stat-grid .stat-card.amber .stat-icon-wrapper {
  background: rgba(245, 158, 11, 0.15) !important;
  color: #fbbf24 !important;
  border: 1px solid rgba(245, 158, 11, 0.3) !important;
}

[data-theme="dark"] .periods-stat-grid .stat-card.purple .stat-icon-wrapper {
  background: rgba(139, 92, 246, 0.15) !important;
  color: #a78bfa !important;
  border: 1px solid rgba(139, 92, 246, 0.3) !important;
}

[data-theme="dark"] .notes-filter-toolbar,
[data-theme="dark"] .periods-card-footer {
  background: #0f172a !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .notes-search-input,
[data-theme="dark"] .notes-select-ctrl {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table thead th {
  background: #0f172a !important;
  border-bottom-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .staff-id-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .staff-id-table tbody tr:hover td {
  background-color: #18263e !important;
}

[data-theme="dark"] .subject-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .standard-pill {
  background: rgba(30, 58, 138, 0.3) !important;
  border-color: rgba(59, 130, 246, 0.4) !important;
  color: #93c5fd !important;
}

[data-theme="dark"] #noteViewerModal .viewer-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
}

[data-theme="dark"] #noteViewerModal .viewer-modal-header,
[data-theme="dark"] #noteViewerModal .viewer-modal-footer {
  background: #1e293b !important;
  border-color: #334155 !important;
}

[data-theme="dark"] #noteViewerModal .viewer-modal-body {
  background: #0f172a !important;
}

[data-theme="dark"] .protected-doc-badge {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
  color: #34d399 !important;
}

[data-theme="dark"] .child-switcher-bar {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

/* ══════════════════════════════════════════════════════════════════════
   MOBILE & TABLET RESPONSIVE ADAPTATIONS (<= 768px)
   ══════════════════════════════════════════════════════════════════════ */
@media (max-width: 768px) {
  .notes-container {
    padding: 0 4px;
  }

  /* 4 KPI Stat Cards: 2x2 grid */
  .periods-stat-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
    margin-bottom: 16px !important;
  }

  .stat-card {
    padding: 12px 14px !important;
    border-radius: 10px !important;
  }

  .stat-label {
    font-size: 0.68rem !important;
    margin-bottom: 4px !important;
  }

  .stat-number {
    font-size: 1.18rem !important;
    line-height: 1.2 !important;
  }

  .stat-icon-wrapper {
    width: 32px !important;
    height: 32px !important;
    font-size: 0.85rem !important;
  }

  .stat-card-progress {
    margin: 8px 0 6px !important;
    height: 4px !important;
  }

  .stat-card-footer {
    font-size: 0.67rem !important;
    margin-top: 6px !important;
    padding-top: 6px !important;
    flex-wrap: wrap !important;
    gap: 4px !important;
  }

  /* Child Switcher / Admin Preview Bar */
  .child-switcher-bar {
    padding: 10px 12px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
    margin-bottom: 16px !important;
  }

  .child-switcher-bar form {
    width: 100% !important;
  }

  .child-switcher-bar select,
  .child-switcher-bar .notes-select-ctrl {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
  }

  /* Workstation Card */
  .periods-card {
    border-radius: 10px !important;
    margin-bottom: 16px !important;
  }

  .periods-card-header {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 8px !important;
  }

  .periods-card-header > div:last-child {
    width: 100% !important;
    display: flex !important;
    justify-content: space-between !important;
    border-top: 1px dashed #e2e8f0;
    padding-top: 6px;
  }

  .periods-card-header h3.card-title {
    font-size: 0.88rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }

  /* Integrated Filter Toolbar */
  .notes-filter-toolbar {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 10px !important;
  }

  .notes-search-box {
    width: 100% !important;
    min-width: 100% !important;
    flex: none !important;
  }

  .notes-search-input {
    font-size: 0.78rem !important;
    padding: 8px 12px 8px 34px !important;
  }

  .notes-select-group {
    width: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
  }

  .notes-select-wrapper {
    width: 100% !important;
  }

  .notes-select-ctrl {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    padding: 8px 28px 8px 12px !important;
    font-size: 0.78rem !important;
  }

  .notes-filter-actions {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    width: 100% !important;
  }

  .notes-filter-actions .roaster-btn {
    flex: 1 !important;
    justify-content: center !important;
    padding: 8px 12px !important;
    font-size: 0.78rem !important;
  }

  /* Table Horizontal Scrolling & Sizing */
  .table-responsive {
    width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
  }

  .staff-id-table {
    min-width: 660px !important;
  }

  .staff-id-table thead th {
    padding: 8px 10px !important;
    font-size: 0.66rem !important;
  }

  .staff-id-table tbody td {
    padding: 10px 10px !important;
    font-size: 0.76rem !important;
  }

  .file-badge {
    width: 30px !important;
    height: 30px !important;
    font-size: 0.85rem !important;
  }

  .subject-pill, .standard-pill {
    font-size: 0.70rem !important;
    padding: 2px 7px !important;
  }

  .note-view-btn {
    padding: 5px 10px !important;
    font-size: 0.74rem !important;
  }

  /* Pagination Footer */
  .periods-card-footer {
    padding: 10px 14px !important;
    flex-direction: column !important;
    align-items: center !important;
    text-align: center !important;
    gap: 8px !important;
    font-size: 0.72rem !important;
  }

  /* Modal Note Viewer on Mobile */
  #noteViewerModal {
    padding: 8px !important;
  }

  #noteViewerModal .viewer-modal-card {
    max-height: calc(100vh - 16px) !important;
    max-height: calc(100dvh - 16px) !important;
    border-radius: 12px !important;
  }

  #noteViewerModal .viewer-modal-header {
    padding: 10px 12px !important;
    gap: 8px !important;
  }

  #noteViewerModal #viewerFileIcon {
    width: 28px !important;
    height: 28px !important;
    font-size: 0.80rem !important;
  }

  #noteViewerModal #viewerNoteTitle {
    font-size: 0.88rem !important;
    max-width: 160px !important;
  }

  #noteViewerModal .protected-doc-badge {
    font-size: 0.62rem !important;
    padding: 1px 5px !important;
  }

  #noteViewerModal .viewer-modal-body {
    padding: 10px !important;
  }

  #noteViewerModal #viewerPdfFrame {
    height: 58vh !important;
    min-height: 280px !important;
  }

  #noteViewerModal #viewerImageWrap {
    height: 58vh !important;
    min-height: 280px !important;
  }

  #noteViewerModal .viewer-modal-footer {
    padding: 10px 12px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
  }

  #noteViewerModal #viewerDescriptionWrap {
    font-size: 0.72rem !important;
    width: 100% !important;
  }

  #noteViewerModal .viewer-modal-footer > div {
    width: 100% !important;
  }

  #noteViewerModal .viewer-modal-footer .roaster-btn {
    width: 100% !important;
    justify-content: center !important;
    padding: 8px 16px !important;
  }
}

/* Micro Devices (<= 380px) */
@media (max-width: 380px) {
  .periods-stat-grid {
    grid-template-columns: 1fr !important;
    gap: 8px !important;
  }

  #noteViewerModal #viewerNoteTitle {
    max-width: 120px !important;
  }
}
</style>

<div class="notes-container" style="max-width: 1380px; margin: 0 auto;">

  <!-- Multi-Child Switcher for Parents with multiple wards -->
  <?php if ($current_user['role'] === 'parent' && count($parent_children) > 1): ?>
    <div class="child-switcher-bar" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 16px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
      <span style="font-size: 0.80rem; font-weight: 600; color: #475569;">
        <i class="fa-solid fa-users" style="color: #0284c7;"></i> Switch Enrolled Child:
      </span>
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <?php foreach ($parent_children as $pc): ?>
          <a href="<?php echo htmlspecialchars(PARENT_URL . '/notes?student_id=' . intval($pc['id']), ENT_QUOTES, 'UTF-8'); ?>" 
             class="roaster-btn <?php echo intval($pc['id']) === $student_id ? 'primary' : 'secondary'; ?>" 
             style="font-size: 0.76rem; padding: 5px 12px; border-radius: 6px; text-decoration: none;">
            <?php echo htmlspecialchars($pc['student_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($pc['standard'] ?? '', ENT_QUOTES, 'UTF-8'); ?>)
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Super Admin / Staff Preview Selector -->
  <?php if (($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff') && !empty($students_list)): ?>
    <div class="child-switcher-bar" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 16px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
      <span style="font-size: 0.80rem; font-weight: 600; color: #475569;">
        <i class="fa-solid fa-user-gear" style="color: #0284c7;"></i> Preview as Scholar:
      </span>
      <form method="GET" action="<?php echo htmlspecialchars(PARENT_URL . '/notes', ENT_QUOTES, 'UTF-8'); ?>" style="display: flex; align-items: center; gap: 8px; margin: 0;">
        <select name="student_id" onchange="this.form.submit()" class="hrms-input notes-select-ctrl" style="min-width: 260px;">
          <?php foreach ($students_list as $sl): ?>
            <option value="<?php echo $sl['id']; ?>" <?php echo $sl['id'] == $selected_student_id ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($sl['student_name']); ?> (Roll: <?php echo htmlspecialchars($sl['roll_no']); ?> &bull; <?php echo htmlspecialchars($sl['standard']); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════════════════
       2. 4 ACADEMIC & STUDY KPI SUMMARY CARDS (Identical to Student Overview)
       ══════════════════════════════════════════════════════ -->
  <div class="periods-stat-grid">

    <!-- 1. Total Private Study Notes -->
    <div class="stat-card blue">
      <div>
        <div class="stat-card-top">
          <div class="stat-info">
            <span class="stat-label">Private Study Notes</span>
            <span class="stat-number"><?php echo number_format($total_records); ?></span>
          </div>
          <div class="stat-icon-wrapper">
            <i class="fa-solid fa-book-bookmark"></i>
          </div>
        </div>
        <div class="stat-card-progress">
          <div class="stat-card-progress-bar" style="width: 100%;"></div>
        </div>
      </div>
      <div class="stat-card-footer">
        <span>Curriculum Study Library</span>
        <span style="color: #0284c7; font-weight: 700;">Verified</span>
      </div>
    </div>

    <!-- 2. Academic Subjects Covered -->
    <div class="stat-card green">
      <div>
        <div class="stat-card-top">
          <div class="stat-info">
            <span class="stat-label">Subjects Covered</span>
            <span class="stat-number"><?php echo number_format(count($subjects_list)); ?></span>
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
        <span>Academic Modules</span>
        <span style="color: #059669; font-weight: 700;">Active</span>
      </div>
    </div>

    <!-- 3. Enrolled Standard -->
    <div class="stat-card amber">
      <div>
        <div class="stat-card-top">
          <div class="stat-info">
            <span class="stat-label">Enrolled Standard</span>
            <span class="stat-number" style="font-size: 1.12rem; line-height: 1.3; margin-top: 3px; max-width: 100%; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo htmlspecialchars($student_standard ?: 'All Classes'); ?>">
              <?php echo htmlspecialchars($student_standard ?: 'All Classes'); ?>
            </span>
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
        <span>Academic Batch</span>
        <span style="color: #d97706; font-weight: 700;">Allocated</span>
      </div>
    </div>

    <!-- 4. Academic Session -->
    <div class="stat-card purple">
      <div>
        <div class="stat-card-top">
          <div class="stat-info">
            <span class="stat-label">Academic Session</span>
            <span class="stat-number" style="font-size: 1.25rem; line-height: 1.2; margin-top: 2px;">
              <?php echo htmlspecialchars($active_session['session_name'] ?? '2025-2026'); ?>
            </span>
          </div>
          <div class="stat-icon-wrapper">
            <i class="fa-solid fa-calendar-check"></i>
          </div>
        </div>
        <div class="stat-card-progress">
          <div class="stat-card-progress-bar" style="width: 100%;"></div>
        </div>
      </div>
      <div class="stat-card-footer">
        <span>Session <?php echo htmlspecialchars($active_session['session_name'] ?? ''); ?></span>
        <span style="color: #7c3aed; font-weight: 700;">Active</span>
      </div>
    </div>

  </div>

  <!-- ══════════════════════════════════════════════════════
       3. WORKSTATION CARD (Matching dashboard.php .periods-card)
       ══════════════════════════════════════════════════════ -->
  <div class="periods-card">
    
    <!-- Card Header -->
    <div class="periods-card-header">
      <div style="flex: 1; min-width: 0;">
        <h3 class="card-title">
          <i class="fa-solid fa-book-open-reader" style="color: #0284c7;"></i>
          <span>Academic Study Notes Directory</span>
          <span style="font-size: 0.68rem; font-weight: 700; background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.25); padding: 2px 7px; border-radius: 5px;">
            <i class="fa-solid fa-lock" style="font-size: 0.60rem;"></i> Private Notes
          </span>
        </h3>
        <p style="margin: 2px 0 0; font-size: 0.74rem; color: #64748b;">
          Official syllabus subject study notes, diagram sheets &amp; revision files (Secure Read-Only View).
        </p>
      </div>

      <div style="font-size: 0.76rem; font-weight: 700; color: #64748b;">
        Total Records: <strong style="color: #0284c7;"><?php echo number_format($total_records); ?></strong>
      </div>
    </div>

    <!-- Integrated Search & Filter Toolbar -->
    <form method="GET" action="<?php echo htmlspecialchars(PARENT_URL . '/notes', ENT_QUOTES, 'UTF-8'); ?>" class="notes-filter-toolbar">
      <?php if ($selected_student_id > 0): ?>
        <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
      <?php endif; ?>

      <!-- Search Input -->
      <div class="notes-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search title, subject, file name, keywords..." class="notes-search-input">
      </div>

      <div class="notes-select-group">
        <!-- Academic Standard Filter -->
        <div class="notes-select-wrapper">
          <select name="standard" onchange="this.form.submit()" class="notes-select-ctrl" title="Filter by Class / Standard">
            <option value="">All Academic Standards</option>
            <?php echo render_standards_dropdown_options($filter_standard, false, $academic_categories, $custom_db_standards); ?>
          </select>
          <i class="fa-solid fa-chevron-down arrow"></i>
        </div>

        <!-- Subject Filter -->
        <div class="notes-select-wrapper">
          <select name="subject" onchange="this.form.submit()" class="notes-select-ctrl" title="Filter by Subject">
            <option value="all">All Academic Subjects (<?php echo count($subjects_list); ?>)</option>
            <?php foreach ($subjects_list as $subj): ?>
              <option value="<?php echo htmlspecialchars($subj, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_subject === $subj ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($subj, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <i class="fa-solid fa-chevron-down arrow"></i>
        </div>

        <div class="notes-filter-actions">
          <button type="submit" class="roaster-btn secondary" style="padding: 7px 14px; font-size: 0.78rem;">
            <i class="fa-solid fa-filter"></i> Filter
          </button>

          <?php if (!empty($search) || !empty($filter_standard) || $filter_subject !== 'all'): ?>
            <a href="<?php echo htmlspecialchars(PARENT_URL . '/notes' . ($selected_student_id > 0 ? '?student_id=' . $selected_student_id : ''), ENT_QUOTES, 'UTF-8'); ?>" class="roaster-btn secondary" style="padding: 7px 12px; font-size: 0.78rem;" title="Reset filters">
              <i class="fa-solid fa-rotate-left"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </form>

    <!-- Sober Directory Table (Matching staff-id-table in dashboard.php) -->
    <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
      <table class="staff-id-table">
        <thead>
          <tr>
            <th style="width: 48px; text-align: center;">#ID</th>
            <th>Note Title &amp; Topic</th>
            <th>Subject</th>
            <th>Standard / Class</th>
            <th>Attached Document</th>
            <th>Uploaded By</th>
            <th style="text-align: right; width: 100px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($notes)): ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 48px 20px; color: #64748b;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.3rem;">
                  <i class="fa-solid fa-folder-open"></i>
                </div>
                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">No Private Study Notes Found</h4>
                <p style="margin: 4px 0 0; font-size: 0.78rem; color: #64748b;">
                  <?php if (!empty($search) || !empty($filter_standard) || $filter_subject !== 'all'): ?>
                    No private study notes match your search filters. Try clearing filters.
                  <?php else: ?>
                    No private notes have been published yet for your academic batch.
                  <?php endif; ?>
                </p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($notes as $n): ?>
              <?php 
                $ftype = strtolower($n['file_type'] ?? 'pdf');
                $is_pdf = ($ftype === 'pdf');
                $is_img = in_array($ftype, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                $badge_cls = $is_pdf ? 'pdf' : ($is_img ? 'image' : 'doc');
                $badge_icon = $is_pdf ? 'fa-solid fa-file-pdf' : ($is_img ? 'fa-solid fa-file-image' : 'fa-solid fa-file-lines');
              ?>
              <tr>
                <!-- #ID -->
                <td style="text-align: center; font-weight: 700; color: #64748b; font-family: ui-monospace, monospace;">
                  #<?php echo intval($n['id']); ?>
                </td>

                <!-- Note Title & Topic -->
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="file-badge <?php echo $badge_cls; ?>">
                      <i class="<?php echo $badge_icon; ?>"></i>
                    </div>
                    <div>
                      <div style="font-weight: 700; color: #0f172a; font-size: 0.84rem; line-height: 1.3;">
                        <?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                      <?php if (!empty($n['description'])): ?>
                        <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
                          <?php echo htmlspecialchars(mb_substr($n['description'], 0, 75) . (mb_strlen($n['description']) > 75 ? '...' : ''), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>

                <!-- Subject -->
                <td>
                  <span class="subject-pill">
                    <i class="fa-solid fa-book" style="color: #0284c7; font-size: 0.68rem;"></i>
                    <?php echo htmlspecialchars($n['subject'], ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </td>

                <!-- Standard / Class -->
                <td>
                  <span class="standard-pill">
                    <i class="fa-solid fa-graduation-cap" style="color: #2563eb; font-size: 0.68rem;"></i>
                    <?php echo htmlspecialchars($n['standard'] ?? 'All Standards / General', ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </td>

                <!-- Attached Document -->
                <td>
                  <div style="display: flex; flex-direction: column; gap: 2px;">
                    <span style="font-weight: 600; font-size: 0.76rem; color: #0f172a; max-width: 170px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($n['file_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                      <i class="fa-solid fa-paperclip" style="color: #64748b; font-size: 0.68rem;"></i>
                      <?php echo htmlspecialchars($n['file_name'] ?? 'document.' . $ftype, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span style="font-size: 0.69rem; color: #64748b;">
                      <?php echo strtoupper($ftype); ?> &bull; <?php echo format_note_bytes($n['file_size']); ?>
                    </span>
                  </div>
                </td>

                <!-- Uploaded By & Date -->
                <td>
                  <div style="display: flex; flex-direction: column;">
                    <span style="font-size: 0.76rem; font-weight: 600; color: #0f172a;">
                      <?php echo htmlspecialchars($n['author_name'] ?? 'Faculty', ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span style="font-size: 0.69rem; color: #64748b;">
                      <?php echo date('d M Y', strtotime($n['created_at'])); ?>
                    </span>
                  </div>
                </td>

                <!-- Action: View Button Only (Strictly Read-Only, No Download) -->
                <td style="text-align: right;">
                  <button type="button" class="note-view-btn" onclick='openNoteViewer(<?php echo json_encode($n, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="View Note Document">
                    <i class="fa-solid fa-eye"></i>
                    <span>View</span>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Card Footer: Pagination (Matching dashboard.php) -->
    <?php if ($total_pages > 1): ?>
      <div class="periods-card-footer">
        <div>
          Showing <strong><?php echo $start_record; ?></strong> to <strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> private notes
        </div>
        <div style="display: flex; gap: 4px;">
          <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="<?php echo htmlspecialchars(PARENT_URL . '/notes?page=' . $p . (!empty($filter_standard) ? '&standard=' . urlencode($filter_standard) : '') . ($filter_subject !== 'all' ? '&subject=' . urlencode($filter_subject) : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . ($selected_student_id > 0 ? '&student_id=' . $selected_student_id : ''), ENT_QUOTES, 'UTF-8'); ?>" 
               style="padding: 4px 10px; border-radius: 6px; font-weight: 700; text-decoration: none; border: 1px solid #cbd5e1; font-size: 0.76rem; <?php echo $p === $page ? 'background: #0284c7; color: #ffffff; border-color: #0284c7;' : 'background: #ffffff; color: #334155;'; ?>">
              <?php echo $p; ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: NOTE VIEWER POPUP (Secure Read-Only, No Download)
     ══════════════════════════════════════════════════════ -->
<div id="noteViewerModal">
  <div class="viewer-modal-card">
    
    <!-- Modal Header -->
    <div class="viewer-modal-header">
      <div style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">
        <div id="viewerFileIcon" class="file-badge pdf">
          <i class="fa-solid fa-file-pdf"></i>
        </div>
        <div style="min-width: 0; flex: 1;">
          <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <h3 id="viewerNoteTitle" style="margin: 0; font-size: 0.98rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 480px;">
              Note Document
            </h3>
            <span class="protected-doc-badge" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.68rem; font-weight: 700; color: #059669; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 2px 7px; border-radius: 5px; white-space: nowrap;">
              <i class="fa-solid fa-shield-halved"></i> Read-Only View
            </span>
          </div>
          <div style="display: flex; align-items: center; gap: 8px; margin-top: 3px; flex-wrap: wrap;">
            <span id="viewerSubjectBadge" class="subject-pill" style="padding: 2px 8px; font-size: 0.70rem;">Subject</span>
            <span id="viewerStandardBadge" class="standard-pill" style="padding: 2px 8px; font-size: 0.70rem;">Standard</span>
            <span id="viewerFileSize" style="font-size: 0.70rem; color: #64748b;">0 KB</span>
          </div>
        </div>
      </div>

      <button type="button" class="modal-close-btn" onclick="closeNoteViewerModal()" aria-label="Close modal" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Body: Interactive Document Preview -->
    <div class="viewer-modal-body">
      <!-- PDF View Container with disabled download/print toolbar -->
      <div id="viewerPdfWrap" style="display: none; width: 100%; height: 100%; position: relative;">
        <iframe id="viewerPdfFrame" src="" style="width: 100%; height: 68vh; border: none; border-radius: 8px; background: #ffffff;"></iframe>
      </div>

      <!-- Image View Container (Protected from Dragging & Right-Click) -->
      <div id="viewerImageWrap" style="display: none; width: 100%; height: 68vh; overflow: auto; text-align: center; background: #0f172a; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center; user-select: none; -webkit-user-select: none;">
        <img id="viewerImage" src="" alt="Note Preview" draggable="false" oncontextmenu="return false;" style="max-width: 100%; max-height: 64vh; object-fit: contain; border-radius: 6px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4); user-select: none; -webkit-user-select: none; -webkit-user-drag: none; pointer-events: none;">
      </div>

      <!-- Fallback Non-previewable Container -->
      <div id="viewerFallbackWrap" style="display: none; text-align: center; padding: 60px 20px; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; height: 100%;">
        <div style="width: 60px; height: 60px; border-radius: 12px; background: #eff6ff; color: #0284c7; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 12px;">
          <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h4 style="margin: 0; font-size: 1rem; color: #0f172a; font-weight: 700;">Protected Document</h4>
        <p style="margin: 6px 0 0; font-size: 0.80rem; color: #64748b; max-width: 480px; margin-inline: auto;">This academic document is protected under academy security policies and is restricted to secure read-only view.</p>
      </div>
    </div>

    <!-- Modal Footer: Only Description, Security Badge and Close Button -->
    <div class="viewer-modal-footer">
      <div id="viewerDescriptionWrap" style="flex: 1; min-width: 220px; font-size: 0.78rem; color: #475569; line-height: 1.4;">
        <span id="viewerDescription"></span>
      </div>

      <div style="display: flex; align-items: center; gap: 10px;">
        <button type="button" onclick="closeNoteViewerModal()" class="roaster-btn secondary" style="padding: 7px 20px; font-size: 0.82rem; font-weight: 700;">
          Close
        </button>
      </div>
    </div>

  </div>
</div>

<script>
function openNoteViewer(note) {
  if (!note) return;
  const modal = document.getElementById('noteViewerModal');
  if (!modal) return;

  const fpath = note.file_path || '';
  const ftype = (note.file_type || '').toLowerCase();
  const fsize = note.file_size ? formatFileSize(note.file_size) : '';

  // Title & Badges
  document.getElementById('viewerNoteTitle').innerText = note.title || 'Untitled Note';
  document.getElementById('viewerSubjectBadge').innerHTML = '<i class="fa-solid fa-book" style="color: #0284c7; font-size: 0.70rem;"></i> ' + escapeHtml(note.subject || 'General');
  document.getElementById('viewerStandardBadge').innerHTML = '<i class="fa-solid fa-graduation-cap" style="color: #2563eb; font-size: 0.70rem;"></i> ' + escapeHtml(note.standard || 'All Classes');
  document.getElementById('viewerFileSize').innerText = (ftype.toUpperCase()) + (fsize ? ' • ' + fsize : '');

  // Description
  const descEl = document.getElementById('viewerDescription');
  if (descEl) {
    descEl.innerText = note.description ? 'Description: ' + note.description : '';
  }

  // Preview Switcher
  const pdfWrap = document.getElementById('viewerPdfWrap');
  const imgWrap = document.getElementById('viewerImageWrap');
  const fallbackWrap = document.getElementById('viewerFallbackWrap');
  const pdfFrame = document.getElementById('viewerPdfFrame');
  const imgEl = document.getElementById('viewerImage');
  const iconEl = document.getElementById('viewerFileIcon');

  pdfWrap.style.display = 'none';
  imgWrap.style.display = 'none';
  fallbackWrap.style.display = 'none';
  pdfFrame.src = '';
  imgEl.src = '';

  const isPdf = (ftype === 'pdf') || fpath.endsWith('.pdf');
  const isImg = ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ftype) || /\.(jpe?g|png|webp|gif)$/i.test(fpath);

  if (isPdf) {
    pdfWrap.style.display = 'block';
    // Append #toolbar=0&navpanes=0 to hide built-in browser download and print toolbar
    pdfFrame.src = fpath + '#toolbar=0&navpanes=0&scrollbar=1&view=FitH';
    iconEl.className = 'file-badge pdf';
    iconEl.innerHTML = '<i class="fa-solid fa-file-pdf"></i>';
  } else if (isImg) {
    imgWrap.style.display = 'flex';
    imgEl.src = fpath;
    iconEl.className = 'file-badge image';
    iconEl.innerHTML = '<i class="fa-solid fa-file-image"></i>';
  } else {
    fallbackWrap.style.display = 'block';
    iconEl.className = 'file-badge doc';
    iconEl.innerHTML = '<i class="fa-solid fa-file-lines"></i>';
  }

  modal.setAttribute('data-opened-at', Date.now());
  modal.classList.add('show');
}

function closeNoteViewerModal() {
  const modal = document.getElementById('noteViewerModal');
  if (!modal) return;
  modal.classList.remove('show');
  const pdfFrame = document.getElementById('viewerPdfFrame');
  if (pdfFrame) pdfFrame.src = '';
  const imgEl = document.getElementById('viewerImage');
  if (imgEl) imgEl.src = '';
}

function formatFileSize(bytes) {
  if (!bytes || isNaN(bytes)) return '';
  const units = ['B', 'KB', 'MB', 'GB'];
  let b = Math.max(bytes, 0);
  let pow = Math.floor((b ? Math.log(b) : 0) / Math.log(1024));
  pow = Math.min(pow, units.length - 1);
  b /= Math.pow(1024, pow);
  return b.toFixed(1) + ' ' + units[pow];
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// Security: Close on Escape, block Print / Save shortcuts while modal is open
document.addEventListener('keydown', function(e) {
  const modal = document.getElementById('noteViewerModal');
  const isOpen = modal && modal.classList.contains('show');

  if (e.key === 'Escape') {
    closeNoteViewerModal();
    return;
  }

  // Block Ctrl+S, Cmd+S (Save), Ctrl+P, Cmd+P (Print), Ctrl+U (Source) while viewer is active
  if (isOpen && (e.ctrlKey || e.metaKey)) {
    const k = e.key.toLowerCase();
    if (k === 's' || k === 'p' || k === 'u') {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  }
}, true);

document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('noteViewerModal');
  if (!modal) return;
  let mouseDownOnBackdrop = false;

  // Security: Disable right-click context menu inside the viewer
  modal.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    return false;
  });

  modal.addEventListener('mousedown', function(e) {
    mouseDownOnBackdrop = (e.target === this);
  });

  modal.addEventListener('click', function(e) {
    const openedAt = parseInt(modal.getAttribute('data-opened-at') || '0', 10);
    if (Date.now() - openedAt < 200) return;
    if (e.target === this && mouseDownOnBackdrop) {
      closeNoteViewerModal();
    }
    mouseDownOnBackdrop = false;
  });
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
