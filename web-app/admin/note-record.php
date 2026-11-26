<?php
/**
 * KSV Academy HRMS - Note Record Management
 * Track, upload, and organize study notes & academic materials.
 * Accessible to Super Admin, Admin, and Staff.
 */

require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'admin', 'staff']);
$page_title = 'Note Record | KSV Academy HRMS';
$page_heading = 'Note Record';
$page_subheading = 'Upload, organize, and manage academic subject notes, revision material, and reference files';
$active_menu = 'note-record';

$db = get_db_connection();
ensure_note_records_table($db);

$is_admin = in_array($current_user['role'] ?? '', ['super_admin', 'admin'], true);
$active_session = get_active_session();
$session_id = $active_session['id'] ?? 2;

// Academic Standards & Specialized Course Categories
$academic_categories = get_default_academic_categories();
$flat_predefined_standards = [];
foreach ($academic_categories as $cat => $std_list) {
    foreach ($std_list as $s_item) {
        $flat_predefined_standards[] = $s_item;
    }
}
$db_standards = $db->query("SELECT DISTINCT standard FROM students WHERE session_id = " . intval($session_id) . " AND standard IS NOT NULL AND standard != ''")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$note_db_standards = $db->query("SELECT DISTINCT standard FROM note_records WHERE standard IS NOT NULL AND standard != '' AND standard NOT IN ('All Classes / General', 'All Standards / General')")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$all_db_standards = array_values(array_unique(array_merge($db_standards, $note_db_standards)));
$custom_db_standards = array_values(array_diff($all_db_standards, $flat_predefined_standards));

// ── Handle Actions (Upload, Edit, Delete, Toggle Visibility) ────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // 1. CSRF Token Verification
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash('error', 'Security token invalid or expired. Please refresh the page and try again.');
        header('Location: ' . ADMIN_URL . '/note-record');
        exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));

    // Safe redirect url
    $calc_redirect_url = function() {
        if (!empty($_POST['redirect_url']) && strpos($_POST['redirect_url'], ADMIN_URL) === 0) {
            return $_POST['redirect_url'];
        }
        $std = trim((string)($_GET['standard'] ?? ''));
        $sub = trim((string)($_GET['subject'] ?? ''));
        $vis = in_array($_GET['visibility'] ?? 'all', ['all', 'public', 'private'], true) ? $_GET['visibility'] : 'all';
        $search = trim((string)($_GET['search'] ?? ''));
        $limit = intval($_GET['limit'] ?? 10);
        $params = [];
        if (!empty($std) && $std !== 'all') $params['standard'] = $std;
        if (!empty($sub) && $sub !== 'all') $params['subject'] = $sub;
        if (!empty($vis) && $vis !== 'all') $params['visibility'] = $vis;
        if (!empty($search)) $params['search'] = $search;
        if ($limit !== 10) $params['limit'] = $limit;
        $qs = !empty($params) ? '?' . http_build_query($params) : '';
        return ADMIN_URL . '/note-record' . $qs;
    };

    // ── 1. Create / Add Note ──
    if ($action === 'create') {
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255);
        $subject = mb_substr(trim((string)($_POST['subject'] ?? '')), 0, 150);

        // Academic Standard / Class (Like student roaster with custom option)
        $raw_standard = trim((string)($_POST['standard'] ?? '10th Standard'));
        if ($raw_standard === '__other__' && !empty($_POST['custom_standard'])) {
            $standard = mb_substr(trim((string)$_POST['custom_standard']), 0, 100);
        } else {
            $standard = mb_substr($raw_standard !== '' ? $raw_standard : 'All Standards / General', 0, 100);
        }

        $raw_visibility = trim((string)($_POST['visibility'] ?? 'public'));
        $visibility = in_array($raw_visibility, ['public', 'private'], true) ? $raw_visibility : 'public';
        $description = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 2000);

        if ($title === '' || $subject === '') {
            set_flash('error', 'Note Title and Subject are required fields.');
            header('Location: ' . $calc_redirect_url());
            exit;
        }

        // File validation & upload
        if (!isset($_FILES['note_file']) || $_FILES['note_file']['error'] !== UPLOAD_ERR_OK) {
            $err_code = $_FILES['note_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $err_msg = 'Please select a valid PDF, JPG, or JPEG file to upload.';
            if ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
                $err_msg = 'The uploaded file exceeds the allowed file size limit.';
            }
            set_flash('error', $err_msg);
            header('Location: ' . $calc_redirect_url());
            exit;
        }

        $file = $_FILES['note_file'];
        $orig_name = basename($file['name']);
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowed_exts, true)) {
            set_flash('error', 'Invalid file type. Only PDF, JPG, and JPEG documents are permitted.');
            header('Location: ' . $calc_redirect_url());
            exit;
        }

        // Max 50MB
        if ($file['size'] > 50 * 1024 * 1024) {
            set_flash('error', 'Uploaded document exceeds the maximum 50MB size limit.');
            header('Location: ' . $calc_redirect_url());
            exit;
        }

        $upload_dir = dirname(__DIR__) . '/uploads/notes';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }

        $new_filename = 'note_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest_path = $upload_dir . '/' . $new_filename;

        if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
            set_flash('error', 'Failed to store uploaded file on the server. Please check folder permissions.');
            header('Location: ' . $calc_redirect_url());
            exit;
        }

        $web_path = '/web-app/uploads/notes/' . $new_filename;
        $file_size = (int)$file['size'];

        $stmt = $db->prepare("
            INSERT INTO note_records 
            (session_id, title, subject, standard, description, file_path, file_name, file_size, file_type, visibility, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $session_id,
            $title,
            $subject,
            $standard,
            $description,
            $web_path,
            $orig_name,
            $file_size,
            $ext,
            $visibility,
            $current_user['id'] ?? null
        ]);

        set_flash('success', "Note '{$title}' uploaded successfully for {$standard} ({$subject})!");
        header('Location: ' . $calc_redirect_url());
        exit;
    }

    // ── 2. Update Note ──
    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255);
        $subject = mb_substr(trim((string)($_POST['subject'] ?? '')), 0, 150);

        // Academic Standard / Class
        $raw_standard = trim((string)($_POST['standard'] ?? '10th Standard'));
        if ($raw_standard === '__other__' && !empty($_POST['custom_standard'])) {
            $standard = mb_substr(trim((string)$_POST['custom_standard']), 0, 100);
        } else {
            $standard = mb_substr($raw_standard !== '' ? $raw_standard : 'All Standards / General', 0, 100);
        }

        $raw_visibility = trim((string)($_POST['visibility'] ?? 'public'));
        $visibility = in_array($raw_visibility, ['public', 'private'], true) ? $raw_visibility : 'public';
        $description = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 2000);

        if ($id <= 0 || $title === '' || $subject === '') {
            set_flash('error', 'Invalid request. Title and Subject are required.');
            header('Location: ' . $calc_redirect_url());
            exit;
        }

        // Fetch existing
        $cur_stmt = $db->prepare("SELECT * FROM note_records WHERE id = ? LIMIT 1");
        $cur_stmt->execute([$id]);
        $existing = $cur_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            set_flash('error', 'Note record not found or already deleted.');
            header('Location: ' . $calc_redirect_url());
            exit;
        }

        // Check if new file was uploaded
        if (isset($_FILES['note_file']) && $_FILES['note_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['note_file'];
            $orig_name = basename($file['name']);
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];

            if (!in_array($ext, $allowed_exts, true)) {
                set_flash('error', 'Invalid file type. Only PDF, JPG, and JPEG documents are permitted.');
                header('Location: ' . $calc_redirect_url());
                exit;
            }

            if ($file['size'] > 50 * 1024 * 1024) {
                set_flash('error', 'Uploaded document exceeds the maximum 50MB size limit.');
                header('Location: ' . $calc_redirect_url());
                exit;
            }

            $upload_dir = dirname(__DIR__) . '/uploads/notes';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }

            $new_filename = 'note_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest_path = $upload_dir . '/' . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                // Delete old file if exists
                if (!empty($existing['file_path'])) {
                    $old_file_full = realpath(dirname(__DIR__, 2) . $existing['file_path']);
                    if ($old_file_full && file_exists($old_file_full) && strpos($old_file_full, $upload_dir) === 0) {
                        @unlink($old_file_full);
                    }
                }
                $web_path = '/web-app/uploads/notes/' . $new_filename;
                $file_size = (int)$file['size'];

                $up_stmt = $db->prepare("
                    UPDATE note_records 
                    SET title = ?, subject = ?, standard = ?, description = ?, visibility = ?, file_path = ?, file_name = ?, file_size = ?, file_type = ? 
                    WHERE id = ?
                ");
                $up_stmt->execute([$title, $subject, $standard, $description, $visibility, $web_path, $orig_name, $file_size, $ext, $id]);
            } else {
                set_flash('error', 'Could not replace the file. Note metadata was not updated.');
                header('Location: ' . $calc_redirect_url());
                exit;
            }
        } else {
            // Update metadata only
            $up_stmt = $db->prepare("
                UPDATE note_records 
                SET title = ?, subject = ?, standard = ?, description = ?, visibility = ? 
                WHERE id = ?
            ");
            $up_stmt->execute([$title, $subject, $standard, $description, $visibility, $id]);
        }

        set_flash('success', "Note record #{$id} updated successfully!");
        header('Location: ' . $calc_redirect_url());
        exit;
    }

    // ── 3. Toggle Visibility ──
    if ($action === 'toggle_visibility') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $cur_stmt = $db->prepare("SELECT visibility FROM note_records WHERE id = ? LIMIT 1");
            $cur_stmt->execute([$id]);
            $cur_vis = $cur_stmt->fetchColumn();
            if ($cur_vis !== false) {
                $new_vis = ($cur_vis === 'public') ? 'private' : 'public';
                $db->prepare("UPDATE note_records SET visibility = ? WHERE id = ?")->execute([$new_vis, $id]);
                set_flash('success', "Note visibility changed to " . ucfirst($new_vis) . ".");
            }
        }
        header('Location: ' . $calc_redirect_url());
        exit;
    }

    // ── 4. Delete Note ──
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $cur_stmt = $db->prepare("SELECT file_path, title FROM note_records WHERE id = ? LIMIT 1");
            $cur_stmt->execute([$id]);
            $row = $cur_stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (!empty($row['file_path'])) {
                    $upload_dir = realpath(dirname(__DIR__) . '/uploads/notes');
                    $file_to_del = realpath(dirname(__DIR__, 2) . $row['file_path']);
                    if ($file_to_del && file_exists($file_to_del) && $upload_dir && strpos($file_to_del, $upload_dir) === 0) {
                        @unlink($file_to_del);
                    }
                }
                $del_stmt = $db->prepare("DELETE FROM note_records WHERE id = ?");
                $del_stmt->execute([$id]);
                set_flash('success', "Note '" . htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') . "' permanently removed.");
            }
        }
        header('Location: ' . $calc_redirect_url());
        exit;
    }
}

// ── Query Parameters: Search, Filter, Sort & Pagination ─────────────────────
$raw_standard = trim((string)($_GET['standard'] ?? ''));
$filter_standard = ($raw_standard !== '' && $raw_standard !== 'all') ? $raw_standard : '';

$raw_subject = trim((string)($_GET['subject'] ?? 'all'));
$filter_subject = ($raw_subject !== '' && $raw_subject !== 'all') ? $raw_subject : 'all';

$raw_visibility = (string)($_GET['visibility'] ?? 'all');
$filter_visibility = in_array($raw_visibility, ['all', 'public', 'private'], true) ? $raw_visibility : 'all';

$search = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 100);
$allowed_limits = [10, 20, 30, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits, true)) $limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));

// ── Overall Metric Counts ───────────────────────────────────────────────────
$total_all_notes = (int)$db->query("SELECT COUNT(*) FROM note_records")->fetchColumn();
$total_public_notes = (int)$db->query("SELECT COUNT(*) FROM note_records WHERE visibility = 'public'")->fetchColumn();
$total_private_notes = (int)$db->query("SELECT COUNT(*) FROM note_records WHERE visibility = 'private'")->fetchColumn();
$total_distinct_subjects = (int)$db->query("SELECT COUNT(DISTINCT subject) FROM note_records")->fetchColumn();

// Progress bar percentages
$public_pct = $total_all_notes > 0 ? round(($total_public_notes / $total_all_notes) * 100) : 0;
$private_pct = $total_all_notes > 0 ? round(($total_private_notes / $total_all_notes) * 100) : 0;

// Fetch distinct subjects for filter dropdown
$distinct_subjects = $db->query("SELECT DISTINCT subject FROM note_records ORDER BY subject ASC")->fetchAll(PDO::FETCH_COLUMN);

// ── Build Filtered Query ───────────────────────────────────────────────────
$where = ["1=1"];
$params = [];

if (!empty($filter_standard)) {
    $where[] = "standard = ?";
    $params[] = $filter_standard;
}
if ($filter_subject !== 'all') {
    $where[] = "subject = ?";
    $params[] = $filter_subject;
}
if ($filter_visibility !== 'all') {
    $where[] = "visibility = ?";
    $params[] = $filter_visibility;
}
if (!empty($search)) {
    $where[] = "(title LIKE ? OR subject LIKE ? OR standard LIKE ? OR file_name LIKE ? OR description LIKE ?)";
    $like = "%" . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . "%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$where_sql = implode(' AND ', $where);
$count_stmt = $db->prepare("SELECT COUNT(*) FROM note_records WHERE {$where_sql}");
$count_stmt->execute($params);
$total_records = intval($count_stmt->fetchColumn());

$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch paginated notes
$list_stmt = $db->prepare("
    SELECT n.*, u.full_name AS author_name 
    FROM note_records n 
    LEFT JOIN users u ON n.uploaded_by = u.id 
    WHERE {$where_sql} 
    ORDER BY n.id DESC 
    LIMIT ? OFFSET ?
");
$idx = 1;
foreach ($params as $p) {
    $list_stmt->bindValue($idx++, $p);
}
$list_stmt->bindValue($idx++, (int)$limit, PDO::PARAM_INT);
$list_stmt->bindValue($idx++, (int)$offset, PDO::PARAM_INT);
$list_stmt->execute();
$notes = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function for human file sizes
function format_note_bytes($bytes, $precision = 1) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ══════════════════════════════════════════════════════════════════════
   NOTE RECORD STYLES - IDENTICAL TO STUDENTS.PHP / FEES.PHP STANDARD
   ══════════════════════════════════════════════════════════════════════ */

/* Clean File Icon Badges */
.note-file-badge {
  width: 38px;
  height: 38px;
  border-radius: 9px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  flex-shrink: 0;
}
.note-file-badge.pdf {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}
.note-file-badge.image {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
}

/* Visibility Pills */
.visibility-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  transition: all 0.2s ease;
}
.visibility-pill.public {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}
.visibility-pill.public:hover {
  background: #bbf7d0;
}
.visibility-pill.private {
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
}
.visibility-pill.private:hover {
  background: #fde68a;
}

/* Action Icon Buttons */
.note-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  font-size: 0.85rem;
}
.note-action-btn:hover {
  background: #f8fafc;
  color: #0284c7;
  border-color: #0284c7;
  transform: translateY(-1px);
}
.note-action-btn.view:hover { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
.note-action-btn.edit:hover { background: #fef9c3; color: #a16207; border-color: #fde047; }
.note-action-btn.delete:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

/* Modal Dropzone */
.note-dropzone {
  border: 2px dashed #cbd5e1;
  border-radius: 10px;
  padding: 18px 16px;
  text-align: center;
  background: #f8fafc;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-top: 6px;
}
.note-dropzone:hover {
  border-color: #0092df;
  background: #f0f9ff;
}
.note-dropzone i {
  font-size: 1.8rem;
  color: #0284c7;
  margin-bottom: 6px;
}
.note-dropzone-text {
  font-size: 0.84rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 2px;
}
.note-dropzone-sub {
  font-size: 0.74rem;
  color: #64748b;
}

/* Dark Theme Overrides */
[data-theme="dark"] .note-file-badge.pdf { background: rgba(220, 38, 38, 0.15) !important; border-color: #7f1d1d !important; color: #f87171 !important; }
[data-theme="dark"] .note-file-badge.image { background: rgba(37, 99, 235, 0.15) !important; border-color: #1e3a8a !important; color: #60a5fa !important; }
[data-theme="dark"] .note-action-btn { background: #0f172a !important; border-color: #334155 !important; color: #cbd5e1 !important; }
[data-theme="dark"] .note-action-btn:hover { background: #1e293b !important; color: #38bdf8 !important; border-color: #38bdf8 !important; }
[data-theme="dark"] .note-dropzone { background: #0f172a !important; border-color: #334155 !important; }
[data-theme="dark"] .note-dropzone-text { color: #f8fafc !important; }
[data-theme="dark"] .note-dropzone-sub { color: #94a3b8 !important; }

/* ══════════════════════════════════════════════════════
   TOOLBAR: CLEAN, STRUCTURED 2-TIER ENTERPRISE LAYOUT
   ══════════════════════════════════════════════════════ */
.note-toolbar-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 16px 20px;
  margin-bottom: 22px;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
}

.note-toolbar-form {
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 13px;
}

.note-toolbar-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.note-search-wrapper {
  flex: 1 1 300px;
  max-width: 440px;
}

.note-search-box {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 8px 14px;
  height: 40px;
  width: 100%;
  box-sizing: border-box;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.note-search-box:focus-within {
  background: #ffffff;
  border-color: var(--hrms-primary, #0092df);
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.15);
}

.note-search-box i {
  color: #94a3b8;
  font-size: 0.85rem;
}

.note-search-box input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 0.85rem;
  font-family: inherit;
  color: #0f172a;
  width: 100%;
}

.note-toolbar-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-left: auto;
  flex-shrink: 0;
}

.note-toolbar-divider {
  height: 1px;
  background: #f1f5f9;
  width: 100%;
}

.note-toolbar-filters {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.note-filter-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.76rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-right: 2px;
  flex-shrink: 0;
}

.note-select-box {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.note-select-box > i:first-child {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  font-size: 0.78rem;
  pointer-events: none;
}

.note-select-box select {
  height: 38px;
  padding: 6px 30px 6px 32px;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 9px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #0f172a;
  outline: none;
  cursor: pointer;
  appearance: none;
  transition: all 0.2s ease;
  min-width: 140px;
}

.note-select-box.std select {
  min-width: 185px;
  max-width: 230px;
}

.note-select-box.sub select {
  min-width: 150px;
  max-width: 190px;
}

.note-select-box.vis select {
  min-width: 135px;
  max-width: 165px;
}

.note-select-box select:focus {
  background: #ffffff;
  border-color: var(--hrms-primary, #0092df);
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.15);
}

.note-select-box .select-chevron {
  position: absolute;
  right: 11px;
  color: #94a3b8;
  font-size: 0.70rem;
  pointer-events: none;
}

.note-filter-btn {
  height: 38px;
  padding: 0 14px;
  border-radius: 9px;
  font-size: 0.80rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f1f5f9;
  border: 1.5px solid #e2e8f0;
  color: #334155;
  cursor: pointer;
  transition: all 0.2s ease;
}

.note-filter-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.note-reset-btn {
  height: 38px;
  padding: 0 12px;
  border-radius: 9px;
  font-size: 0.80rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #64748b;
  text-decoration: none;
  transition: all 0.2s ease;
}

.note-reset-btn:hover {
  background: #fef2f2;
  color: #dc2626;
}

/* Dark Theme Overrides for Toolbar */
[data-theme="dark"] .note-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .note-search-box {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .note-search-box input {
  color: #f8fafc !important;
}
[data-theme="dark"] .note-search-box:focus-within {
  background: #0f172a !important;
  border-color: var(--hrms-primary, #0092df) !important;
}
[data-theme="dark"] .note-toolbar-divider {
  background: #334155 !important;
}
[data-theme="dark"] .note-filter-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .note-select-box select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f1f5f9 !important;
}
[data-theme="dark"] .note-select-box select option,
[data-theme="dark"] .note-select-box select optgroup {
  background: #0f172a !important;
  color: #f1f5f9 !important;
}
[data-theme="dark"] .note-select-box select:focus {
  border-color: var(--hrms-primary, #0092df) !important;
}
[data-theme="dark"] .note-select-box > i:first-child,
[data-theme="dark"] .note-select-box .select-chevron {
  color: #64748b !important;
}
[data-theme="dark"] .note-filter-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .note-filter-btn:hover {
  background: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .note-reset-btn:hover {
  background: rgba(239, 68, 68, 0.15) !important;
  color: #f87171 !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .note-toolbar-card {
    padding: 12px 14px !important;
  }
  .note-toolbar-top {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 10px !important;
  }
  .note-search-wrapper {
    max-width: 100% !important;
    width: 100% !important;
  }
  .note-toolbar-actions {
    width: 100% !important;
    margin-left: 0 !important;
    justify-content: space-between !important;
  }
  .note-toolbar-filters {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
  }
  .note-select-box {
    width: 100% !important;
  }
  .note-select-box select {
    width: 100% !important;
    max-width: 100% !important;
  }
}
</style>

<!-- ══════════════════════════════════════════════════════
     1. STANDARD KPI SUMMARY STRIP (.stat-grid & .stat-card)
     Identical card design like students.php and fees.php
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid" style="margin-bottom: 22px;">
  
  <!-- Card 1: Total Notes (Blue) -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Notes in Library</span>
          <span class="stat-number"><?php echo $total_all_notes; ?></span>
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
      <span>Academic Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
      <span style="color:#059669; font-weight:700;"><i class="fa-solid fa-check"></i> Active</span>
    </div>
  </div>

  <!-- Card 2: Public Notes (Green) -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Public Study Notes</span>
          <span class="stat-number"><?php echo $total_public_notes; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-earth-americas"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $public_pct; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $public_pct; ?>%</strong> of total library</span>
      <span style="color:#059669; font-weight:700;">Student Portal</span>
    </div>
  </div>

  <!-- Card 3: Private Notes (Amber) -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Private / Staff Notes</span>
          <span class="stat-number"><?php echo $total_private_notes; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-lock"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $private_pct; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Restricted internal documents</span>
      <span style="color:#d97706; font-weight:700;">Internal Only</span>
    </div>
  </div>

  <!-- Card 4: Academic Subjects (Purple) -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Academic Subjects</span>
          <span class="stat-number"><?php echo $total_distinct_subjects; ?></span>
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
      <span>Middle, Secondary &amp; Senior Batches</span>
      <span style="color:#2563eb; font-weight:700;">Curriculum</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     2. STANDARD ENHANCED FILTER TOOLBAR & ADD NOTE BUTTON
     Clean 2-Tier Enterprise Toolbar Layout
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card note-toolbar-card">
  <form method="GET" action="<?php echo htmlspecialchars(ADMIN_URL . '/note-record', ENT_QUOTES, 'UTF-8'); ?>" class="note-toolbar-form">
    <input type="hidden" name="limit" value="<?php echo intval($limit); ?>">

    <!-- Row 1: Search Box on Left, Notes Count Badge & Add Note Button on Right -->
    <div class="note-toolbar-top">
      
      <!-- Search Input -->
      <div class="note-search-wrapper">
        <div class="note-search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search title, standard, subject, file..." autocomplete="off" maxlength="100">
          <?php if (!empty($search)): ?>
            <a href="<?php echo htmlspecialchars(ADMIN_URL . '/note-record' . (!empty($filter_standard) ? '?standard=' . urlencode($filter_standard) : '') . ($filter_subject !== 'all' ? '&subject=' . urlencode($filter_subject) : '') . ($filter_visibility !== 'all' ? '&visibility=' . urlencode($filter_visibility) : '') . ($limit !== 10 ? '&limit=' . $limit : ''), ENT_QUOTES, 'UTF-8'); ?>" class="clear-search-btn" title="Clear search">&times;</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: Live Count Badge & Add Note Button -->
      <div class="note-toolbar-actions">
        <div class="scholars-count-badge">
          <span class="count-dot"></span>
          <span><strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'Note' : 'Notes'; ?></span>
        </div>

        <button type="button" class="roaster-add-btn" onclick="openAddNoteModal()">
          <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
          <span>Add Note</span>
        </button>
      </div>

    </div>

    <!-- Divider Line -->
    <div class="note-toolbar-divider"></div>

    <!-- Row 2: Filter Controls Strip -->
    <div class="note-toolbar-filters">
      <div class="note-filter-label">
        <i class="fa-solid fa-sliders" style="color: #0284c7;"></i>
        <span>Filter By:</span>
      </div>

      <!-- Standard / Class Filter Select -->
      <div class="note-select-box std">
        <i class="fa-solid fa-graduation-cap"></i>
        <select name="standard" onchange="this.form.submit()">
          <option value="">All Academic Classes</option>
          <?php echo render_standards_dropdown_options($filter_standard, false, $academic_categories, $custom_db_standards); ?>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <!-- Subject Filter Select -->
      <div class="note-select-box sub">
        <i class="fa-solid fa-book"></i>
        <select name="subject" onchange="this.form.submit()">
          <option value="all">All Subjects (<?php echo $total_distinct_subjects; ?>)</option>
          <?php foreach ($distinct_subjects as $s): ?>
            <option value="<?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_subject === $s ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <!-- Visibility Filter Select -->
      <div class="note-select-box vis">
        <i class="fa-solid fa-eye"></i>
        <select name="visibility" onchange="this.form.submit()">
          <option value="all" <?php echo $filter_visibility === 'all' ? 'selected' : ''; ?>>All Visibility</option>
          <option value="public" <?php echo $filter_visibility === 'public' ? 'selected' : ''; ?>>Public Only (<?php echo $total_public_notes; ?>)</option>
          <option value="private" <?php echo $filter_visibility === 'private' ? 'selected' : ''; ?>>Private Only (<?php echo $total_private_notes; ?>)</option>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <!-- Submit / Filter Button -->
      <button type="submit" class="note-filter-btn" title="Apply Filters">
        <i class="fa-solid fa-filter"></i>
        <span>Filter</span>
      </button>

      <!-- Reset Filter Button -->
      <?php if (!empty($search) || !empty($filter_standard) || $filter_subject !== 'all' || $filter_visibility !== 'all'): ?>
        <a href="<?php echo htmlspecialchars(ADMIN_URL . '/note-record' . ($limit !== 10 ? '?limit=' . $limit : ''), ENT_QUOTES, 'UTF-8'); ?>" class="note-reset-btn" title="Reset all filters">
          <i class="fa-solid fa-rotate-left"></i>
          <span>Reset</span>
        </a>
      <?php endif; ?>
    </div>

  </form>
</div>

<!-- ══════════════════════════════════════════════════════
     3. NOTE RECORD TABLE CARD
     Identical to .hrms-card & .hrms-table in students.php
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
  <div class="hrms-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-book-bookmark" style="color: #0284c7;"></i>
        Academic Note Records
        <span style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Official subject study notes, question banks, diagrams, and reference documents.
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
          <th style="width: 55px;">#ID</th>
          <th style="min-width: 240px;">Note Title &amp; Topic</th>
          <th style="min-width: 130px;">Subject</th>
          <th style="min-width: 160px;">Standard / Class</th>
          <th style="min-width: 150px;">Document File</th>
          <th style="min-width: 120px;">Visibility</th>
          <th style="min-width: 160px;">Uploaded By &amp; Date</th>
          <th style="min-width: 130px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($notes)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-folder-open"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Notes Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                <?php if (!empty($search) || $filter_standard !== 'all' || $filter_subject !== 'all' || $filter_visibility !== 'all'): ?>
                  No notes in Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong> matched your query.
                <?php else: ?>
                  No academic study notes have been uploaded yet. Click <strong>Add Note</strong> above to upload study materials.
                <?php endif; ?>
              </p>
              <button type="button" class="roaster-add-btn" onclick="openAddNoteModal()" style="display: inline-flex; margin: 0 auto;">
                <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
                <span>Add Note</span>
              </button>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($notes as $n): ?>
            <?php 
              $ftype = strtolower($n['file_type'] ?? 'pdf');
              $is_pdf = ($ftype === 'pdf');
            ?>
            <tr>
              <!-- #ID -->
              <td style="font-weight: 700; color: #64748b;">
                #<?php echo intval($n['id']); ?>
              </td>

              <!-- Note Title & Topic -->
              <td>
                <div style="display: flex; align-items: center; gap: 12px;">
                  <div class="note-file-badge <?php echo $is_pdf ? 'pdf' : 'image'; ?>">
                    <i class="<?php echo $is_pdf ? 'fa-solid fa-file-pdf' : 'fa-solid fa-file-image'; ?>"></i>
                  </div>
                  <div>
                    <div style="font-weight: 800; color: #0f172a; font-size: 0.90rem; line-height: 1.3;">
                      <?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if (!empty($n['description'])): ?>
                      <div style="font-size: 0.76rem; color: #64748b; margin-top: 2px;">
                        <?php echo htmlspecialchars(mb_substr($n['description'], 0, 75) . (mb_strlen($n['description']) > 75 ? '...' : ''), ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <!-- Subject -->
              <td>
                <span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 7px; font-size: 0.78rem; font-weight: 700; background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0;">
                  <i class="fa-solid fa-book" style="color: #0284c7; font-size: 0.72rem;"></i>
                  <?php echo htmlspecialchars($n['subject'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>

              <!-- Academic Standard / Class (Like student roaster) -->
              <td>
                <span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 7px; font-size: 0.76rem; font-weight: 700; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;">
                  <i class="fa-solid fa-graduation-cap" style="color: #2563eb; font-size: 0.72rem;"></i>
                  <?php echo htmlspecialchars($n['standard'] ?? 'All Standards / General', ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>

              <!-- Document File -->
              <td>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                  <span style="font-weight: 700; font-size: 0.78rem; color: #0f172a; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($n['file_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-solid fa-paperclip" style="color: #64748b; font-size: 0.72rem;"></i>
                    <?php echo htmlspecialchars($n['file_name'] ?? 'document.' . $ftype, ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                  <span style="font-size: 0.72rem; color: #64748b;">
                    <?php echo strtoupper($ftype); ?> &bull; <?php echo format_note_bytes($n['file_size']); ?>
                  </span>
                </div>
              </td>

              <!-- Visibility -->
              <td>
                <form method="POST" action="<?php echo ADMIN_URL; ?>/note-record" style="margin: 0; display: inline;">
                  <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                  <input type="hidden" name="action" value="toggle_visibility">
                  <input type="hidden" name="id" value="<?php echo intval($n['id']); ?>">
                  <button type="submit" class="visibility-pill <?php echo $n['visibility'] === 'public' ? 'public' : 'private'; ?>" style="border: none; cursor: pointer;" title="Click to toggle between Public and Private">
                    <i class="<?php echo $n['visibility'] === 'public' ? 'fa-solid fa-globe' : 'fa-solid fa-lock'; ?>"></i>
                    <span><?php echo ucfirst($n['visibility']); ?></span>
                  </button>
                </form>
              </td>

              <!-- Uploaded By & Date -->
              <td>
                <div style="display: flex; flex-direction: column;">
                  <span style="font-size: 0.80rem; font-weight: 700; color: #0f172a;">
                    <?php echo htmlspecialchars($n['author_name'] ?? 'Faculty / Admin', ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                  <span style="font-size: 0.72rem; color: #64748b;">
                    <?php echo date('d M Y, h:i A', strtotime($n['created_at'])); ?>
                  </span>
                </div>
              </td>

              <!-- Action -->
              <td style="text-align: right;">
                <div style="display: inline-flex; align-items: center; gap: 5px;">
                  <!-- View / Open in new tab -->
                  <a href="<?php echo htmlspecialchars($n['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="note-action-btn view" title="View / Open Document in new tab">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </a>

                  <!-- Direct Download -->
                  <a href="<?php echo htmlspecialchars($n['file_path'], ENT_QUOTES, 'UTF-8'); ?>" download="<?php echo htmlspecialchars($n['file_name'] ?? $n['title'], ENT_QUOTES, 'UTF-8'); ?>" class="note-action-btn" title="Download Document">
                    <i class="fa-solid fa-download"></i>
                  </a>

                  <!-- Edit Note -->
                  <button type="button" class="note-action-btn edit" onclick='openEditNoteModal(<?php echo json_encode($n, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit Note Details">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>

                  <!-- Delete Note -->
                  <button type="button" class="note-action-btn delete" onclick="openDeleteNoteModal(<?php echo intval($n['id']); ?>, <?php echo htmlspecialchars(json_encode((string)$n['title']), ENT_QUOTES, 'UTF-8'); ?>)" title="Delete Note Record">
                    <i class="fa-solid fa-trash-can"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination Footer -->
  <?php if ($total_records > 0): ?>
    <div style="padding: 14px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 0.82rem; color: #64748b; background: #ffffff;">
      <div>
        Showing <strong><?php echo $start_record; ?></strong> to <strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> notes
      </div>
      
      <?php if ($total_pages > 1): ?>
        <div style="display: flex; gap: 5px;">
          <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="<?php echo ADMIN_URL; ?>/note-record?standard=<?php echo urlencode($filter_standard); ?>&subject=<?php echo urlencode($filter_subject); ?>&visibility=<?php echo urlencode($filter_visibility); ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>&page=<?php echo $p; ?>" style="padding: 5px 11px; border-radius: 6px; font-weight: 700; text-decoration: none; border: 1px solid #cbd5e1; <?php echo $p === $page ? 'background: #0092df; color: #ffffff; border-color: #0092df;' : 'background: #ffffff; color: #334155;'; ?>">
              <?php echo $p; ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════
     POPUP MODAL: ADD NOTE
     Fields: Title, Subject, Academic Standard / Class, Upload File, Visibility
     ══════════════════════════════════════════════════════ -->
<div id="addNoteModal" class="hrms-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(5px); overflow-y: auto; padding: 20px;">
  <div class="hrms-modal-content" style="background: #ffffff; border-radius: 16px; max-width: 580px; width: 100%; padding: 26px 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); position: relative; max-height: 92vh; overflow-y: auto;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 20px;">
      <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-file-circle-plus" style="color: #0092df;"></i>
        <span>Add Note Record</span>
      </h3>
      <button type="button" onclick="closeAddNoteModal()" style="background: #f1f5f9; border: none; width: 34px; height: 34px; border-radius: 50%; font-size: 20px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/note-record" enctype="multipart/form-data" id="addNoteForm">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

      <!-- Field 1: Title -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Title <span style="color: #ef4444;">*</span>
        </label>
        <input type="text" name="title" id="addNoteTitle" required placeholder="e.g. Chapter 4: Quadratic Equations - Formulas &amp; Solved Practice Problems" class="hrms-input" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-weight: 600;">
      </div>

      <!-- Field 2: Subject -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Subject <span style="color: #ef4444;">*</span>
        </label>
        <input type="text" name="subject" id="addNoteSubject" list="commonSubjectSuggestions" required placeholder="e.g. Mathematics, Physics, Chemistry, Biology..." class="hrms-input" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-weight: 600;">
        <datalist id="commonSubjectSuggestions">
          <option value="Mathematics"></option>
          <option value="Physics"></option>
          <option value="Chemistry"></option>
          <option value="Biology"></option>
          <option value="Science"></option>
          <option value="English Core"></option>
          <option value="Social Studies"></option>
          <option value="Computer Science / IT"></option>
          <option value="Hindi"></option>
          <option value="Accountancy"></option>
          <option value="Business Studies"></option>
          <option value="Economics"></option>
        </datalist>
      </div>

      <!-- Field 3: Academic Standard / Class (Like Student Roaster) -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Academic Standard / Class <span style="color: #ef4444;">*</span>
        </label>
        <select name="standard" id="addNoteStandard" class="hrms-input" required onchange="handleNoteStandardChange('add')" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-weight: 600;">
          <option value="All Standards / General">All Standards / General Batches</option>
          <?php echo render_standards_dropdown_options('10th Standard', true, $academic_categories, $custom_db_standards); ?>
        </select>
        <div id="add_note_custom_standard_box" style="display: none; margin-top: 8px;">
          <input type="text" name="custom_standard" id="add_note_custom_standard" class="hrms-input" placeholder="Enter custom academic class (e.g. 12th Commerce, Diploma)" style="width: 100%; padding: 9px 12px; border: 1.5px solid #0092df; border-radius: 8px;">
        </div>
      </div>

      <!-- Field 4: Upload File (PDF, JPG, JPEG) -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Upload File (PDF, JPG, JPEG) <span style="color: #ef4444;">*</span>
        </label>
        <div class="note-dropzone" onclick="document.getElementById('addNoteFileInput').click();">
          <i class="fa-solid fa-cloud-arrow-up"></i>
          <div class="note-dropzone-text" id="addNoteFileChosenText">Click or Drag &amp; Drop to upload note file</div>
          <div class="note-dropzone-sub">Accepted formats: <strong>PDF, JPG, JPEG</strong> (Max 50MB)</div>
        </div>
        <input type="file" name="note_file" id="addNoteFileInput" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required style="display: none;" onchange="onNoteFileSelected(this, 'addNoteFileChosenText');">
      </div>

      <!-- Field 5: Visibility Dropdown -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Visibility <span style="color: #ef4444;">*</span>
        </label>
        <select name="visibility" id="addNoteVisibility" required class="hrms-input" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-weight: 600;">
          <option value="public" selected>Public (Accessible to students &amp; portal)</option>
          <option value="private">Private (Restricted / Internal staff only)</option>
        </select>
      </div>

      <!-- Optional Description -->
      <div style="margin-bottom: 20px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Description / Summary <span style="color: #64748b; font-weight: 500;">(Optional)</span>
        </label>
        <textarea name="description" rows="2" placeholder="Brief topics covered or reference guidelines..." class="hrms-input" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-size: 0.85rem;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 18px; margin-top: 22px;">
        <button type="button" onclick="closeAddNoteModal()" class="roaster-btn secondary" style="padding: 10px 18px; border-radius: 8px; font-weight: 700;">
          Cancel
        </button>
        <button type="submit" class="roaster-add-btn" style="border-radius: 8px; padding: 10px 22px;">
          <div class="btn-icon-circle"><i class="fa-solid fa-cloud-arrow-up"></i></div>
          <span>Upload &amp; Save Note</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     POPUP MODAL: EDIT NOTE
     ══════════════════════════════════════════════════════ -->
<div id="editNoteModal" class="hrms-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(5px); overflow-y: auto; padding: 20px;">
  <div class="hrms-modal-content" style="background: #ffffff; border-radius: 16px; max-width: 580px; width: 100%; padding: 26px 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); position: relative; max-height: 92vh; overflow-y: auto;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 20px;">
      <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-pen-to-square" style="color: #0284c7;"></i>
        <span>Edit Note Record</span>
      </h3>
      <button type="button" onclick="closeEditNoteModal()" style="background: #f1f5f9; border: none; width: 34px; height: 34px; border-radius: 50%; font-size: 20px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/note-record" enctype="multipart/form-data" id="editNoteForm">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
      <input type="hidden" name="id" id="editNoteId" value="">

      <!-- Title -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Title <span style="color: #ef4444;">*</span>
        </label>
        <input type="text" name="title" id="editNoteTitle" required class="hrms-input" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-weight: 600;">
      </div>

      <!-- Subject -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Subject <span style="color: #ef4444;">*</span>
        </label>
        <input type="text" name="subject" id="editNoteSubject" list="commonSubjectSuggestions" required class="hrms-input" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-weight: 600;">
      </div>

      <!-- Academic Standard / Class (Like student roaster) -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Academic Standard / Class <span style="color: #ef4444;">*</span>
        </label>
        <select name="standard" id="editNoteStandard" class="hrms-input" required onchange="handleNoteStandardChange('edit')" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-weight: 600;">
          <option value="All Standards / General">All Standards / General</option>
          <?php echo render_standards_dropdown_options('', true, $academic_categories, $custom_db_standards); ?>
        </select>
        <div id="edit_note_custom_standard_box" style="display: none; margin-top: 8px;">
          <input type="text" name="custom_standard" id="edit_note_custom_standard" class="hrms-input" placeholder="Enter custom academic class" style="width: 100%; padding: 9px 12px; border: 1.5px solid #0092df; border-radius: 8px;">
        </div>
      </div>

      <!-- Replace File (Optional) -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Replace File <span style="color: #64748b; font-weight: 500;">(Leave empty to keep existing file)</span>
        </label>
        <div class="note-dropzone" onclick="document.getElementById('editNoteFileInput').click();">
          <i class="fa-solid fa-cloud-arrow-up"></i>
          <div class="note-dropzone-text" id="editNoteFileChosenText">Keep current file or click to replace</div>
          <div class="note-dropzone-sub">Accepted: <strong>PDF, JPG, JPEG</strong> (Max 50MB)</div>
        </div>
        <input type="file" name="note_file" id="editNoteFileInput" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" style="display: none;" onchange="onNoteFileSelected(this, 'editNoteFileChosenText');">
      </div>

      <!-- Visibility Dropdown -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Visibility <span style="color: #ef4444;">*</span>
        </label>
        <select name="visibility" id="editNoteVisibility" required class="hrms-input" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-weight: 600;">
          <option value="public">Public (Accessible to students &amp; portal)</option>
          <option value="private">Private (Restricted / Internal staff only)</option>
        </select>
      </div>

      <!-- Description -->
      <div style="margin-bottom: 20px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">
          Description / Summary <span style="color: #64748b; font-weight: 500;">(Optional)</span>
        </label>
        <textarea name="description" id="editNoteDescription" rows="2" class="hrms-input" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-size: 0.85rem;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 18px; margin-top: 22px;">
        <button type="button" onclick="closeEditNoteModal()" class="roaster-btn secondary" style="padding: 10px 18px; border-radius: 8px; font-weight: 700;">
          Cancel
        </button>
        <button type="submit" class="roaster-add-btn" style="border-radius: 8px; padding: 10px 22px;">
          <div class="btn-icon-circle"><i class="fa-solid fa-floppy-disk"></i></div>
          <span>Save Changes</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     POPUP MODAL: DELETE NOTE CONFIRMATION
     ══════════════════════════════════════════════════════ -->
<div id="deleteNoteModal" class="hrms-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(5px); overflow-y: auto; padding: 20px;">
  <div class="hrms-modal-content" style="background: #ffffff; border-radius: 16px; max-width: 440px; width: 100%; padding: 26px 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); position: relative;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #fee2e2; padding-bottom: 14px; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 1.20rem; font-weight: 800; color: #dc2626; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>Delete Note Record</span>
      </h3>
      <button type="button" onclick="closeDeleteNoteModal()" style="background: #f1f5f9; border: none; width: 34px; height: 34px; border-radius: 50%; font-size: 20px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/note-record" id="deleteNoteForm">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
      <input type="hidden" name="id" id="deleteNoteId" value="">

      <p style="font-size: 0.88rem; color: #334155; margin-bottom: 12px;">
        Are you sure you want to permanently delete note <strong id="deleteNoteTitleText" style="color: #0f172a;"></strong>?
      </p>
      <p style="font-size: 0.78rem; color: #ef4444; margin-bottom: 20px; background: #fef2f2; padding: 8px 12px; border-radius: 8px; border: 1px solid #fecaca;">
        <i class="fa-solid fa-circle-exclamation"></i> The attached file will be permanently removed from server storage.
      </p>

      <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1.5px solid #f1f5f9; padding-top: 16px;">
        <button type="button" onclick="closeDeleteNoteModal()" class="roaster-btn secondary" style="padding: 9px 18px; border-radius: 8px; font-weight: 700;">
          Cancel
        </button>
        <button type="submit" class="roaster-btn" style="background: #dc2626; color: #ffffff; border: 1px solid #dc2626; padding: 9px 20px; border-radius: 8px; font-weight: 700; cursor: pointer;">
          <i class="fa-solid fa-trash-can"></i> Yes, Delete Note
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Handle Academic Standard Change (Show/Hide Custom Class Input) ──
function handleNoteStandardChange(mode) {
  const sel = document.getElementById(mode === 'add' ? 'addNoteStandard' : 'editNoteStandard');
  const box = document.getElementById(mode === 'add' ? 'add_note_custom_standard_box' : 'edit_note_custom_standard_box');
  const inp = document.getElementById(mode === 'add' ? 'add_note_custom_standard' : 'edit_note_custom_standard');
  if (!sel || !box || !inp) return;
  
  if (sel.value === '__other__') {
    box.style.display = 'block';
    inp.setAttribute('required', 'required');
    setTimeout(() => { try { inp.focus(); } catch(e){} }, 50);
  } else {
    box.style.display = 'none';
    inp.removeAttribute('required');
    inp.value = '';
  }
}

// ── Modal Handlers ──
function openAddNoteModal() {
  const modal = document.getElementById('addNoteModal');
  if (modal) {
    const stdSel = document.getElementById('addNoteStandard');
    if (stdSel) {
      handleNoteStandardChange('add');
    }
    modal.style.display = 'flex';
    setTimeout(() => {
      const titleInput = document.getElementById('addNoteTitle');
      if (titleInput) titleInput.focus();
    }, 50);
  }
}

function closeAddNoteModal() {
  const modal = document.getElementById('addNoteModal');
  if (modal) modal.style.display = 'none';
}

function openEditNoteModal(data) {
  if (!data) return;
  document.getElementById('editNoteId').value = data.id || '';
  document.getElementById('editNoteTitle').value = data.title || '';
  document.getElementById('editNoteSubject').value = data.subject || '';
  document.getElementById('editNoteVisibility').value = data.visibility || 'public';
  document.getElementById('editNoteDescription').value = data.description || '';

  // Academic Standard prefill
  const editStdSel = document.getElementById('editNoteStandard');
  const editCustomBox = document.getElementById('edit_note_custom_standard_box');
  const editCustomInp = document.getElementById('edit_note_custom_standard');

  if (editStdSel) {
    const val = data.standard || 'All Standards / General';
    let matched = false;
    for (let opt of editStdSel.options) {
      if (opt.value === val) {
        editStdSel.value = val;
        matched = true;
        break;
      }
    }
    if (!matched && val && val !== '__other__') {
      editStdSel.value = '__other__';
      if (editCustomInp) editCustomInp.value = val;
      if (editCustomBox) editCustomBox.style.display = 'block';
    } else {
      if (editCustomBox) editCustomBox.style.display = 'none';
      if (editCustomInp) editCustomInp.value = '';
    }
  }
  
  const textEl = document.getElementById('editNoteFileChosenText');
  if (textEl) {
    textEl.innerHTML = `Current: <strong>${data.file_name || 'document'}</strong> (click to replace)`;
  }
  
  const modal = document.getElementById('editNoteModal');
  if (modal) modal.style.display = 'flex';
}

function closeEditNoteModal() {
  const modal = document.getElementById('editNoteModal');
  if (modal) modal.style.display = 'none';
}

function openDeleteNoteModal(id, title) {
  document.getElementById('deleteNoteId').value = id;
  document.getElementById('deleteNoteTitleText').innerText = title || `#${id}`;
  const modal = document.getElementById('deleteNoteModal');
  if (modal) modal.style.display = 'flex';
}

function closeDeleteNoteModal() {
  const modal = document.getElementById('deleteNoteModal');
  if (modal) modal.style.display = 'none';
}

function onNoteFileSelected(input, labelId) {
  const label = document.getElementById(labelId);
  if (!label) return;
  if (input.files && input.files[0]) {
    const f = input.files[0];
    const sizeMb = (f.size / (1024 * 1024)).toFixed(2);
    label.innerHTML = `<span style="color: #0284c7;"><i class="fa-solid fa-circle-check"></i> Selected:</span> <strong>${f.name}</strong> (${sizeMb} MB)`;
  } else {
    label.innerText = 'Click or Drag & Drop to upload note file';
  }
}

// Close on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeAddNoteModal();
    closeEditNoteModal();
    closeDeleteNoteModal();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
