<?php
// Defense-in-depth HTTP security headers
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'admin']);
$page_title = 'Academic Toppers Management | KSV Academy HRMS';
$page_heading = 'Academic Toppers Management';
$page_subheading = 'Manage Merit Scholars, Board Toppers, High Percentage Achievers & Awards';
$active_menu = 'toppers';

$db = get_db_connection();
// Retrieve active academic session for Toppers (Isolated to Toppers functionality)
$topper_active_session = function_exists('get_toppers_active_session') ? get_toppers_active_session() : get_active_session();
$topper_active_session_id = intval($topper_active_session['id'] ?? 2);
$topper_active_session_name = $topper_active_session['session_name'] ?? '2025-2026';

// Pass to layout-header so header session switcher updates ONLY Toppers session, not entire ERP
$session_switch_param = 'set_topper_active_session';
$active_session = $topper_active_session;
$session_id = $topper_active_session_id;

// Fetch all academic sessions for dropdowns and filtering
$all_sessions = function_exists('get_all_sessions') ? get_all_sessions() : [];
if (empty($all_sessions)) {
    try {
        $all_sessions = $db->query("SELECT * FROM academic_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $all_sessions = [
            ['id' => 3, 'session_name' => '2026-2027', 'is_current' => 0],
            ['id' => 2, 'session_name' => '2025-2026', 'is_current' => 1],
            ['id' => 1, 'session_name' => '2024-2025', 'is_current' => 0]
        ];
    }
}
$session_names_map = [];
foreach ($all_sessions as $as) {
    $session_names_map[intval($as['id'])] = $as['session_name'];
}

// Handle GET switcher trigger for Toppers active session (?set_topper_active_session=2)
if (isset($_GET['set_topper_active_session']) && is_numeric($_GET['set_topper_active_session'])) {
    // Referer verification against cross-origin triggering
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $ref_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $curr_host = parse_url(SITE_URL, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '');
        if ($ref_host && $curr_host && strcasecmp($ref_host, $curr_host) !== 0) {
            set_flash('error', 'Unauthorized cross-origin session switch request.');
            header('Location: ' . ADMIN_URL . '/toppers');
            exit;
        }
    }

    $target_sid = intval($_GET['set_topper_active_session']);
    $stmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = ? LIMIT 1");
    $stmt->execute([$target_sid]);
    $found_sess = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($found_sess) {
        $stmt_s1 = $db->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES ('toppers_active_session_id', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt_s1->execute([(string)$found_sess['id']]);

        $stmt_s2 = $db->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES ('toppers_active_session_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt_s2->execute([(string)$found_sess['session_name']]);

        set_flash('success', 'Active session for Academic Toppers successfully set to Session ' . htmlspecialchars($found_sess['session_name'], ENT_QUOTES, 'UTF-8') . ' (Only for Toppers).');
        header('Location: ' . ADMIN_URL . '/toppers?session=' . urlencode($found_sess['session_name']));
        exit;
    }
}

// Session-based request rate limiting (Max 120 page views per minute)
$now = time();
if (!isset($_SESSION['admin_toppers_req_attempts']) || !is_array($_SESSION['admin_toppers_req_attempts'])) {
    $_SESSION['admin_toppers_req_attempts'] = [];
}
$_SESSION['admin_toppers_req_attempts'] = array_filter($_SESSION['admin_toppers_req_attempts'], function($timestamp) use ($now) {
    return ($now - (int)$timestamp) < 60;
});
if (count($_SESSION['admin_toppers_req_attempts']) >= 120) {
    http_response_code(429);
    exit('Rate limit exceeded. Please wait a moment before trying again.');
}
$_SESSION['admin_toppers_req_attempts'][] = $now;

// Target directory for topper photos: /assets/imgs/toppers/
$toppers_fs_dir = realpath(__DIR__ . '/../../assets/imgs/toppers') ?: (__DIR__ . '/../../assets/imgs/toppers');
if (!is_dir($toppers_fs_dir)) {
    @mkdir($toppers_fs_dir, 0755, true);
}

// ── Ensure Database Table Exists & Has Session Columns ────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `toppers` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(150) NOT NULL,
      `percentage` VARCHAR(50) NOT NULL,
      `subject` VARCHAR(150) NOT NULL,
      `session_id` INT NULL DEFAULT 2,
      `session_name` VARCHAR(50) NULL DEFAULT '2025-2026',
      `image` VARCHAR(255) NULL,
      `description` TEXT NULL,
      `year_or_batch` VARCHAR(50) NULL,
      `rank_title` VARCHAR(100) NULL,
      `display_order` INT NOT NULL DEFAULT 0,
      `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Add session columns if table existed without them
    $cols = $db->query("SHOW COLUMNS FROM toppers")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('session_id', $cols, true)) {
        $db->exec("ALTER TABLE toppers ADD COLUMN session_id INT NULL DEFAULT 2 AFTER subject");
    }
    if (!in_array('session_name', $cols, true)) {
        $db->exec("ALTER TABLE toppers ADD COLUMN session_name VARCHAR(50) NULL DEFAULT '2025-2026' AFTER session_id");
    }
} catch (Exception $e) {}

// Helper: Process and validate image upload
$process_topper_image_upload = function($file_field) use ($toppers_fs_dir) {
    if (!isset($_FILES[$file_field]) || empty($_FILES[$file_field]['name'])) {
        return ['path' => '', 'error' => ''];
    }
    if ($_FILES[$file_field]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => '', 'error' => ''];
    }
    if ($_FILES[$file_field]['error'] !== UPLOAD_ERR_OK) {
        return ['path' => '', 'error' => 'Upload failed with error code: ' . intval($_FILES[$file_field]['error'])];
    }

    $p_name = $_FILES[$file_field]['name'];
    $p_tmp = $_FILES[$file_field]['tmp_name'];
    $p_size = $_FILES[$file_field]['size'];

    // 10MB maximum file size
    if ($p_size > 10 * 1024 * 1024) {
        return ['path' => '', 'error' => 'Uploaded file exceeds the maximum allowed size of 10MB.'];
    }
    if (!is_uploaded_file($p_tmp)) {
        return ['path' => '', 'error' => 'Invalid file upload source.'];
    }

    $p_ext = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
    if (!in_array($p_ext, $allowed_exts, true)) {
        return ['path' => '', 'error' => "Unsupported file format '.{$p_ext}'. Please upload JPG, PNG, WEBP, or AVIF."];
    }

    // MIME verification
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $p_tmp) : '';
    if ($finfo) finfo_close($finfo);

    $valid_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    if (!in_array($mime, $valid_mimes, true)) {
        return ['path' => '', 'error' => 'Uploaded file is not a valid image.'];
    }

    // Header integrity check
    $img_info = @getimagesize($p_tmp);
    if ($img_info === false) {
        return ['path' => '', 'error' => 'Corrupt or unreadable image file header.'];
    }

    if (!is_dir($toppers_fs_dir)) {
        @mkdir($toppers_fs_dir, 0755, true);
    }

    $clean_basename = 'topper-' . time() . '-' . bin2hex(random_bytes(5)) . '.' . $p_ext;
    $dest = $toppers_fs_dir . '/' . $clean_basename;
    if (move_uploaded_file($p_tmp, $dest)) {
        @chmod($dest, 0644);
        return ['path' => '/assets/imgs/toppers/' . $clean_basename, 'error' => ''];
    }
    return ['path' => '', 'error' => 'Failed to write image file to assets/imgs/toppers/.'];
};

// ── Handle Actions (Create, Update, Delete, Toggle Status) ─────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // 1. CSRF Token Verification
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash('error', 'Security token invalid or expired. Please refresh the page and try again.');
        header('Location: ' . ADMIN_URL . '/toppers');
        exit;
    }

    // 2. Action Rate Limiting (Max 25 modifications per 5 minutes)
    if (!isset($_SESSION['admin_toppers_action_attempts']) || !is_array($_SESSION['admin_toppers_action_attempts'])) {
        $_SESSION['admin_toppers_action_attempts'] = [];
    }
    $_SESSION['admin_toppers_action_attempts'] = array_filter($_SESSION['admin_toppers_action_attempts'], function($t) use ($now) {
        return ($now - (int)$t) < 300;
    });
    if (count($_SESSION['admin_toppers_action_attempts']) >= 25) {
        set_flash('error', 'Action rate limit reached. Please wait a few moments before trying again.');
        header('Location: ' . ADMIN_URL . '/toppers');
        exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));

    // Handle Set Toppers Active Session (Toppers Only, NOT entire ERP)
    if ($action === 'set_topper_active_session') {
        $target_sid = intval($_POST['topper_active_session_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = ? LIMIT 1");
        $stmt->execute([$target_sid]);
        $found_sess = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($found_sess) {
            $stmt_s1 = $db->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES ('toppers_active_session_id', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt_s1->execute([(string)$found_sess['id']]);

            $stmt_s2 = $db->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES ('toppers_active_session_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt_s2->execute([(string)$found_sess['session_name']]);

            set_flash('success', 'Active session for Academic Toppers successfully set to Session ' . htmlspecialchars($found_sess['session_name']) . ' (Only for Toppers).');
            header('Location: ' . ADMIN_URL . '/toppers?session=' . urlencode($found_sess['session_name']));
            exit;
        }
        set_flash('error', 'Selected academic session not found.');
        header('Location: ' . ADMIN_URL . '/toppers');
        exit;
    }

    // Helper: Clean control characters and trim
    $clean_text = function($val, $max_len = 255) {
        $val = trim((string)$val);
        $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val);
        return mb_substr($val, 0, $max_len);
    };

    // Handle Create Topper
    if ($action === 'create') {
        $name = $clean_text($_POST['name'] ?? '', 150);
        $percentage = $clean_text($_POST['percentage'] ?? '', 50);
        $subject = $clean_text($_POST['subject'] ?? '', 150);
        $description = $clean_text($_POST['description'] ?? '', 5000);
        $rank_title = $clean_text($_POST['rank_title'] ?? '', 100);
        $year_or_batch = $clean_text($_POST['year_or_batch'] ?? '', 50);
        $display_order = max(0, min(9999, intval($_POST['display_order'] ?? 0)));
        $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

        // Session ID & Session Name Mapping
        $topper_session_id = intval($_POST['session_id'] ?? $session_id);
        $topper_session_name = $session_names_map[$topper_session_id] ?? ($active_session['session_name'] ?? '2025-2026');

        if (empty($name) || empty($percentage) || empty($subject)) {
            set_flash('error', 'Name, Percentage, and Subject are required fields.');
            header('Location: ' . ADMIN_URL . '/toppers');
            exit;
        }

        // Process Image
        $upload_res = $process_topper_image_upload('topper_image');
        if (!empty($upload_res['error'])) {
            set_flash('error', $upload_res['error']);
            header('Location: ' . ADMIN_URL . '/toppers');
            exit;
        }

        $image_path = $upload_res['path'];
        // If no file uploaded, check if manual path or fallback given
        if (empty($image_path)) {
            $manual_img = trim((string)($_POST['manual_image_path'] ?? ''));
            if (!empty($manual_img) && preg_match('#^/(assets/imgs/|assets/img/)[a-zA-Z0-9_\-\.\/]+$#i', $manual_img)) {
                $image_path = $manual_img;
            } else {
                $image_path = '/assets/imgs/ksv-logo.jpg';
            }
        }

        $stmt = $db->prepare("INSERT INTO toppers (name, percentage, subject, session_id, session_name, image, description, rank_title, year_or_batch, display_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $percentage, $subject, $topper_session_id, $topper_session_name, $image_path, $description, $rank_title, $year_or_batch, $display_order, $status]);
        $_SESSION['admin_toppers_action_attempts'][] = $now;

        set_flash('success', 'Academic Topper "' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" added successfully!');
        header('Location: ' . ADMIN_URL . '/toppers');
        exit;
    }

    // Handle Update Topper
    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            set_flash('error', 'Invalid topper record ID specified.');
            header('Location: ' . ADMIN_URL . '/toppers');
            exit;
        }

        // Fetch existing record
        $stmt_ex = $db->prepare("SELECT * FROM toppers WHERE id = ? LIMIT 1");
        $stmt_ex->execute([$id]);
        $existing = $stmt_ex->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            set_flash('error', 'Topper record not found.');
            header('Location: ' . ADMIN_URL . '/toppers');
            exit;
        }

        $name = $clean_text($_POST['name'] ?? '', 150);
        $percentage = $clean_text($_POST['percentage'] ?? '', 50);
        $subject = $clean_text($_POST['subject'] ?? '', 150);
        $description = $clean_text($_POST['description'] ?? '', 5000);
        $rank_title = $clean_text($_POST['rank_title'] ?? '', 100);
        $year_or_batch = $clean_text($_POST['year_or_batch'] ?? '', 50);
        $display_order = max(0, min(9999, intval($_POST['display_order'] ?? 0)));
        $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

        // Session ID & Session Name Mapping
        $topper_session_id = intval($_POST['session_id'] ?? ($existing['session_id'] ?: $session_id));
        $topper_session_name = $session_names_map[$topper_session_id] ?? ($existing['session_name'] ?? '2025-2026');

        if (empty($name) || empty($percentage) || empty($subject)) {
            set_flash('error', 'Name, Percentage, and Subject are required fields.');
            header('Location: ' . ADMIN_URL . '/toppers');
            exit;
        }

        // Check if new image uploaded
        $image_path = $existing['image'];
        $upload_res = $process_topper_image_upload('topper_image');
        if (!empty($upload_res['error'])) {
            set_flash('error', $upload_res['error']);
            header('Location: ' . ADMIN_URL . '/toppers');
            exit;
        }
        if (!empty($upload_res['path'])) {
            $image_path = $upload_res['path'];
            // Clean up old uploaded file to prevent disk bloat
            if (!empty($existing['image']) && strpos($existing['image'], '/assets/imgs/toppers/') === 0 && $existing['image'] !== $image_path) {
                $old_file = realpath(__DIR__ . '/../../' . ltrim($existing['image'], '/'));
                if ($old_file && strpos($old_file, $toppers_fs_dir) === 0 && file_exists($old_file)) {
                    @unlink($old_file);
                }
            }
        }

        $stmt = $db->prepare("UPDATE toppers SET name = ?, percentage = ?, subject = ?, session_id = ?, session_name = ?, image = ?, description = ?, rank_title = ?, year_or_batch = ?, display_order = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $percentage, $subject, $topper_session_id, $topper_session_name, $image_path, $description, $rank_title, $year_or_batch, $display_order, $status, $id]);
        $_SESSION['admin_toppers_action_attempts'][] = $now;

        set_flash('success', 'Topper record updated successfully!');
        header('Location: ' . ADMIN_URL . '/toppers');
        exit;
    }

    // Handle Delete Topper
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            set_flash('error', 'Invalid topper record ID specified.');
            header('Location: ' . ADMIN_URL . '/toppers');
            exit;
        }

        // Fetch image before deletion to securely clean up uploaded file
        $stmt_f = $db->prepare("SELECT image FROM toppers WHERE id = ? LIMIT 1");
        $stmt_f->execute([$id]);
        $old_img = $stmt_f->fetchColumn();

        $stmt = $db->prepare("DELETE FROM toppers WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['admin_toppers_action_attempts'][] = $now;

        if (!empty($old_img) && strpos($old_img, '/assets/imgs/toppers/') === 0) {
            $file_to_del = realpath(__DIR__ . '/../../' . ltrim($old_img, '/'));
            if ($file_to_del && strpos($file_to_del, $toppers_fs_dir) === 0 && file_exists($file_to_del)) {
                @unlink($file_to_del);
            }
        }

        set_flash('success', 'Topper record deleted successfully.');
        header('Location: ' . ADMIN_URL . '/toppers');
        exit;
    }

    // Handle Toggle Status
    if ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $new_status = ($_POST['status'] ?? '') === 'active' ? 'inactive' : 'active';
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE toppers SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            set_flash('success', 'Topper status updated to ' . ucfirst($new_status) . '.');
        }
        header('Location: ' . ADMIN_URL . '/toppers');
        exit;
    }
}

// ── Search & Filter State ───────────────────────────────────────────────────
$search_query = trim((string)($_GET['q'] ?? ''));
$raw_status = $_GET['status'] ?? 'all';
$filter_status = in_array($raw_status, ['all', 'active', 'inactive'], true) ? $raw_status : 'all';

// Default to active session for only this functionality (not entire archive unless 'all' is explicitly selected)
$raw_session = isset($_GET['session']) ? trim((string)$_GET['session']) : null;
$filter_session = ($raw_session !== null && $raw_session !== '') ? $raw_session : $topper_active_session_name;
$raw_view = $_GET['view'] ?? 'table';
$current_view = in_array($raw_view, ['table', 'grid'], true) ? $raw_view : 'table';

// Pagination Parameters
$allowed_limits = [2, 5, 10, 20, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits, true)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Build Filtering SQL
$where_clauses = ["1=1"];
$params = [];

if (!empty($search_query)) {
    // Escape LIKE meta-characters (\, %, _) to avoid wildcard injection & scanning DoS
    $escaped_search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search_query);
    $where_clauses[] = "(name LIKE ? OR subject LIKE ? OR description LIKE ? OR rank_title LIKE ?)";
    $like_term = '%' . $escaped_search . '%';
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
}

if ($filter_status !== 'all') {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
}

if ($filter_session !== 'all') {
    $where_clauses[] = "(session_name = ? OR session_id = ?)";
    $params[] = $filter_session;
    $params[] = intval($filter_session);
}

$where_sql = implode(' AND ', $where_clauses);

// Count total matching records for pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM toppers WHERE {$where_sql}");
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();

// Calculate Total Pages & Offset
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Query Toppers with LIMIT and OFFSET
$sql = "SELECT * FROM toppers WHERE {$where_sql} ORDER BY display_order ASC, id DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$bind_idx = 1;
foreach ($params as $val) {
    $stmt->bindValue($bind_idx++, $val);
}
$stmt->bindValue($bind_idx++, $limit, PDO::PARAM_INT);
$stmt->bindValue($bind_idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$toppers_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination Record Indicators
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Helper for Pagination URLs
function get_topper_pagination_url($target_page, $search, $filter_status, $limit, $view = 'table', $session = null) {
    if ($session === null) {
        $session = $GLOBALS['filter_session'] ?? $GLOBALS['topper_active_session_name'] ?? 'all';
    }
    $params = [];
    if (!empty($search)) $params['q'] = $search;
    if (!empty($filter_status) && $filter_status !== 'all') $params['status'] = $filter_status;
    if (!empty($session)) $params['session'] = $session;
    if ($limit != 10) $params['limit'] = $limit;
    if (!empty($view) && $view !== 'table') $params['view'] = $view;
    if ($target_page > 1) $params['page'] = $target_page;
    return ADMIN_URL . '/toppers' . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Metrics for Stat Cards Strip (Scoped to Active Session or Selected View)
$metrics_clauses = ["1=1"];
$metrics_params = [];
if ($filter_session !== 'all') {
    $metrics_clauses[] = "(session_name = ? OR session_id = ?)";
    $metrics_params[] = $filter_session;
    $metrics_params[] = intval($filter_session);
}
$m_sql = implode(' AND ', $metrics_clauses);
$m_stmt = $db->prepare("SELECT * FROM toppers WHERE {$m_sql}");
$m_stmt->execute($metrics_params);
$session_toppers = $m_stmt ? $m_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$global_total = count($session_toppers);
$global_active = 0;
$highest_pct = '0%';
$pct_max_float = 0.0;
$unique_subjects = [];

foreach ($session_toppers as $at) {
    if ($at['status'] === 'active') {
        $global_active++;
    }
    $val = floatval(preg_replace('/[^0-9.]/', '', $at['percentage']));
    if ($val > $pct_max_float) {
        $pct_max_float = $val;
        $highest_pct = $at['percentage'];
    }
    if (!empty($at['subject'])) {
        $unique_subjects[trim($at['subject'])] = true;
    }
}
$unique_subjects_count = count($unique_subjects);
$active_percentage = $global_total > 0 ? round(($global_active / $global_total) * 100) : 0;

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Toppers Roaster Table & Badge Styles (Matching students.php) ── */
.student-id-table {
  width: 100%;
  min-width: 1100px;
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
.sid-score-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-family: 'Outfit', sans-serif;
  font-weight: 900;
  font-size: 0.95rem;
  color: #d97706;
  background: #fef3c7;
  border: 1px solid #fde68a;
  padding: 2px 10px;
  border-radius: 6px;
  white-space: nowrap;
}
.sid-fee-due {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.70rem;
  font-weight: 700;
  color: #dc2626;
  background: #fef2f2;
  border: 1px solid #fecaca;
  padding: 2px 7px;
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
  padding: 2px 7px;
  border-radius: 4px;
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

/* Dark Theme Adaptations */
[data-theme="dark"] .student-id-table thead th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .student-id-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .student-id-table tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.02) !important;
}
[data-theme="dark"] .sid-std-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .sid-roll-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .sid-score-badge {
  background: rgba(245, 158, 11, 0.15) !important;
  border-color: rgba(245, 158, 11, 0.3) !important;
  color: #fbbf24 !important;
}
[data-theme="dark"] .sid-action-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .sid-action-btn.edit {
  background: rgba(99, 102, 241, 0.15) !important;
  border-color: rgba(99, 102, 241, 0.3) !important;
  color: #818cf8 !important;
}
[data-theme="dark"] .sid-action-btn.delete {
  background: rgba(239, 68, 68, 0.15) !important;
  border-color: rgba(239, 68, 68, 0.3) !important;
  color: #f87171 !important;
}
[data-theme="dark"] .pagination-controls-wrap {
  background: #1e293b !important;
  border-top-color: #334155 !important;
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
[data-theme="dark"] .roaster-table-legend {
  border-top-color: #334155 !important;
}
</style>

<!-- KPI Summary Strip (Matching students.php stat-grid) -->
<div class="stat-grid" style="margin-bottom: 22px;">
  
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Toppers</span>
          <span class="stat-number"><?php echo $global_total; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-trophy"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Academic Merit <strong>Scholars</strong></span>
      <span style="color:#059669; font-weight:700;"><i class="fa-solid fa-check"></i> Verified</span>
    </div>
  </div>

  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Active on Website</span>
          <span class="stat-number"><?php echo $global_active; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-circle-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $active_percentage; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Live on Public Portal</span>
      <span style="color:#059669; font-weight:700;"><?php echo $active_percentage; ?>% Published</span>
    </div>
  </div>

  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Highest Score</span>
          <span class="stat-number"><?php echo htmlspecialchars($highest_pct, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-crown"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Top Academic Benchmark</span>
      <span style="color:#d97706; font-weight:700;">Record Score</span>
    </div>
  </div>

  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Distinction Streams</span>
          <span class="stat-number"><?php echo $unique_subjects_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-award"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Science, Commerce &amp; Pre-Med</span>
      <span style="color:#2563eb; font-weight:700;">Streams</span>
    </div>
  </div>

</div>

<!-- Modern Filter Toolbar & Action Buttons (Matching students.php) -->
<div class="roaster-toolbar-card">
  <div class="roaster-toolbar-inner">
    
    <!-- Filter form -->
    <form method="GET" action="<?php echo htmlspecialchars(ADMIN_URL . '/toppers', ENT_QUOTES, 'UTF-8'); ?>" class="roaster-filter-form">
      <input type="hidden" name="limit" value="<?php echo intval($limit); ?>">
      <input type="hidden" name="view" value="<?php echo htmlspecialchars($current_view, ENT_QUOTES, 'UTF-8'); ?>">

      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="q" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search name, subject, or description..." autocomplete="off" maxlength="100">
        <?php if (!empty($search_query)): ?>
          <a href="<?php echo htmlspecialchars(get_topper_pagination_url(1, '', $filter_status, $limit, $current_view), ENT_QUOTES, 'UTF-8'); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <div class="roaster-select-box">
        <i class="fa-solid fa-calendar-days"></i>
        <select name="session" onchange="this.form.submit()">
          <option value="<?php echo htmlspecialchars($topper_active_session_name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filter_session === $topper_active_session_name) ? 'selected' : ''; ?>>
            Active: <?php echo htmlspecialchars($topper_active_session_name, ENT_QUOTES, 'UTF-8'); ?> (Toppers)
          </option>
          <option value="all" <?php echo $filter_session === 'all' ? 'selected' : ''; ?>>All Sessions (Entire Archive)</option>
          <?php foreach ($all_sessions as $sess): ?>
            <?php if ($sess['session_name'] !== $topper_active_session_name): ?>
              <option value="<?php echo htmlspecialchars($sess['session_name'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filter_session == $sess['session_name'] || $filter_session == $sess['id']) ? 'selected' : ''; ?>>
                Session <?php echo htmlspecialchars($sess['session_name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <div class="roaster-select-box">
        <i class="fa-solid fa-filter"></i>
        <select name="status" onchange="this.form.submit()">
          <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
          <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active Only</option>
          <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <button type="submit" class="roaster-btn secondary">
        <i class="fa-solid fa-sliders"></i> Filter
      </button>

      <?php if (!empty($search_query) || $filter_status !== 'all' || $filter_session !== $topper_active_session_name): ?>
        <a href="<?php echo htmlspecialchars(ADMIN_URL . '/toppers' . ($limit !== 10 ? '?limit=' . $limit : ''), ENT_QUOTES, 'UTF-8'); ?>" class="roaster-btn ghost" title="Reset all filters">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Toppers Live Count, View Mode & Add Button -->
    <div class="roaster-toolbar-actions">
      
      <!-- View Switcher -->
      <div style="display: inline-flex; align-items: center; gap: 4px; background: var(--hrms-card, #ffffff); border: 1px solid var(--hrms-border, #e2e8f0); border-radius: 8px; padding: 2px;">
        <a href="<?php echo htmlspecialchars(get_topper_pagination_url($page, $search_query, $filter_status, $limit, 'table'), ENT_QUOTES, 'UTF-8'); ?>" 
           class="roaster-btn <?php echo $current_view === 'table' ? 'primary' : 'secondary'; ?>" 
           style="padding: 4px 10px; font-size: 0.76rem; border: none; text-decoration: none;" title="Table View">
          <i class="fa-solid fa-list"></i> Table
        </a>
        <a href="<?php echo htmlspecialchars(get_topper_pagination_url($page, $search_query, $filter_status, $limit, 'grid'), ENT_QUOTES, 'UTF-8'); ?>" 
           class="roaster-btn <?php echo $current_view === 'grid' ? 'primary' : 'secondary'; ?>" 
           style="padding: 4px 10px; font-size: 0.76rem; border: none; text-decoration: none;" title="Card Grid View">
          <i class="fa-solid fa-grip"></i> Cards
        </a>
      </div>

      <!-- Set Active Session (Toppers Only) Button -->
      <button type="button" class="roaster-btn secondary" onclick="openModal('setTopperActiveSessionModal')" title="Set active academic session for Toppers only" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 0.78rem;">
        <i class="fa-solid fa-calendar-check" style="color: #0284c7;"></i>
        <span>Active: <strong><?php echo htmlspecialchars($topper_active_session_name); ?></strong></span>
      </button>

      <div class="scholars-count-badge">
        <span class="count-dot"></span>
        <span><strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'Topper' : 'Toppers'; ?></span>
      </div>

      <button type="button" class="roaster-add-btn" onclick="openModal('addTopperModal')">
        <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
        <span>Add Topper</span>
      </button>
    </div>

  </div>
</div>

<!-- Main Toppers Card Container (Matching students.php) -->
<div class="hrms-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
  
  <!-- Card Header -->
  <div class="hrms-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-trophy" style="color: #0284c7;"></i>
        Academy Merit Scholars &amp; Rank Holders
        <span style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Live Toppers
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Manage student images, board percentages, subjects and accomplishment remarks.
      </p>
    </div>
    <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
      <span>Total Records: <strong style="color: #0f172a;"><?php echo $total_records; ?></strong></span>
    </div>
  </div>

  <!-- Data Presentation -->
  <?php if (empty($toppers_list)): ?>
    <div style="text-align: center; padding: 50px 20px; color: #64748b;">
      <div style="width: 52px; height: 52px; border-radius: 50%; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin: 0 auto 12px;">
        <i class="fa-solid fa-trophy"></i>
      </div>
      <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Topper Records Found</h4>
      <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
        <?php echo !empty($search_query) ? 'No toppers matched your search filter.' : 'You have not added any academic toppers yet.'; ?>
      </p>
      <button type="button" class="roaster-add-btn" onclick="openModal('addTopperModal')" style="display: inline-flex;">
        <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
        <span>Add First Topper</span>
      </button>
    </div>
  <?php elseif ($current_view === 'grid'): ?>
    
    <!-- Cards Grid View -->
    <div style="padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 20px;">
      <?php foreach ($toppers_list as $t): ?>
        <?php
          $photo_url = !empty($t['image']) ? $t['image'] : '/assets/imgs/ksv-logo.jpg';
          $is_active = ($t['status'] === 'active');
        ?>
        <div style="background: var(--hrms-card, #ffffff); border: 1.5px solid <?php echo $is_active ? 'var(--hrms-border, #e2e8f0)' : '#fca5a5'; ?>; border-radius: 12px; overflow: hidden; box-shadow: var(--hrms-shadow-sm); display: flex; flex-direction: column;">
          
          <div style="position: relative; height: 200px; background: #f1f5f9; overflow: hidden;">
            <img src="<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
            
            <div style="position: absolute; top: 10px; left: 10px; background: rgba(15, 23, 42, 0.85); color: #ffffff; padding: 3px 8px; border-radius: 6px; font-size: 0.70rem; font-weight: 800;">
              <i class="fa-solid fa-crown" style="color: #f59e0b;"></i> <?php echo htmlspecialchars($t['rank_title'] ?: 'Topper', ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <div style="position: absolute; bottom: 10px; right: 10px; background: #0092df; color: #ffffff; padding: 4px 10px; border-radius: 8px; font-size: 0.92rem; font-weight: 800; font-family: 'Outfit', sans-serif;">
              <?php echo htmlspecialchars($t['percentage'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
          </div>

          <div style="padding: 16px; flex: 1; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 6px;">
              <h4 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--hrms-text, #0f172a); font-family: 'Outfit', sans-serif;">
                <?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>
              </h4>
              <span style="font-size: 0.68rem; font-weight: 800; padding: 2px 7px; border-radius: 10px; background: <?php echo $is_active ? '#dcfce7; color: #15803d;' : '#fee2e2; color: #b91c1c;'; ?>">
                <?php echo ucfirst($t['status']); ?>
              </span>
            </div>

            <div style="font-size: 0.78rem; font-weight: 700; color: #0284c7; margin-bottom: 8px;">
              <i class="fa-solid fa-book-bookmark"></i> <?php echo htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; flex-wrap: wrap;">
              <span style="font-size: 0.70rem; font-weight: 700; color: #4338ca; background: #e0e7ff; border: 1px solid #c7d2fe; padding: 2px 7px; border-radius: 6px;">
                <i class="fa-regular fa-calendar"></i> Session <?php echo htmlspecialchars($t['session_name'] ?: '2025-2026', ENT_QUOTES, 'UTF-8'); ?>
              </span>
              <span class="sid-roll-pill" style="font-size: 0.70rem;">
                <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($t['year_or_batch'] ?: 'Batch 2026', ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>

            <p style="font-size: 0.80rem; color: var(--hrms-text-muted, #64748b); line-height: 1.45; margin: 0 0 14px; flex: 1;">
              <?php echo htmlspecialchars(mb_substr($t['description'] ?? '', 0, 120), ENT_QUOTES, 'UTF-8'); ?><?php echo mb_strlen($t['description'] ?? '') > 120 ? '...' : ''; ?>
            </p>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--hrms-border, #e2e8f0); padding-top: 12px; gap: 6px;">
              <span style="font-size: 0.72rem; color: #94a3b8;">
                Order: <strong>#<?php echo intval($t['display_order']); ?></strong>
              </span>

              <div style="display: flex; gap: 6px;">
                <button type="button" class="sid-action-btn edit" onclick="editTopper(<?php echo htmlspecialchars(json_encode($t, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit Topper">
                  <i class="fa-solid fa-pencil"></i>
                </button>
                <button type="button" class="sid-action-btn delete" onclick="confirmDeleteTopper(<?php echo intval($t['id']); ?>, <?php echo htmlspecialchars(json_encode((string)$t['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)" title="Delete Topper">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </div>

          </div>

        </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>

    <!-- Table View (Matching students.php student-id-table) -->
    <div class="table-responsive" style="overflow-x: auto;">
      <table class="hrms-table student-id-table">
        <thead>
          <tr>
            <th style="width: 50px; text-align: center;">#</th>
            <th style="min-width: 220px;">Scholar Profile</th>
            <th style="text-align: center; min-width: 130px;">Score &amp; %</th>
            <th style="min-width: 180px;">Subject / Stream</th>
            <th style="min-width: 140px;">Rank &amp; Batch</th>
            <th style="min-width: 220px;">Achievement Remarks</th>
            <th style="text-align: center; min-width: 100px;">Status</th>
            <th style="text-align: right; min-width: 100px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($toppers_list as $idx => $t): ?>
            <?php
              $photo_url = !empty($t['image']) ? $t['image'] : '/assets/imgs/ksv-logo.jpg';
              $is_active = ($t['status'] === 'active');
              $has_real_photo = !empty($t['image']) && file_exists(__DIR__ . '/../..' . $t['image']);
              $first_letter = strtoupper(substr($t['name'], 0, 1));
            ?>
            <tr>
              <!-- # Order / Index -->
              <td style="text-align: center; color: var(--hrms-text-muted, #64748b); font-size: 0.76rem; font-weight: 700;">
                <?php echo intval($t['display_order']) ?: ($offset + $idx + 1); ?>
              </td>

              <!-- Scholar Profile (User cell with avatar & title) -->
              <td>
                <div class="user-cell" style="display: flex; align-items: center; gap: 12px;">
                  <?php if ($has_real_photo): ?>
                    <img src="<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php else: ?>
                    <img src="<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php endif; ?>

                  <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.90rem; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                      <?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 6px;">
                      <span style="color: #f59e0b; font-weight: 700;"><i class="fa-solid fa-crown" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($t['rank_title'] ?: 'Topper', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Score & Percentage -->
              <td style="text-align: center;">
                <span class="sid-score-badge">
                  <i class="fa-solid fa-award" style="font-size: 0.78rem;"></i>
                  <?php echo htmlspecialchars($t['percentage'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>

              <!-- Subject / Stream -->
              <td>
                <span class="sid-std-pill">
                  <i class="fa-solid fa-book-bookmark" style="color: #0284c7;"></i>
                  <?php echo htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>

              <!-- Rank, Batch & Session -->
              <td>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                  <span class="sid-roll-pill">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <?php echo htmlspecialchars($t['year_or_batch'] ?: 'Batch 2026', ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                  <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.70rem; font-weight: 700; color: #4338ca; background: #e0e7ff; border: 1px solid #c7d2fe; padding: 1px 6px; border-radius: 4px; width: fit-content;">
                    <i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($t['session_name'] ?: '2025-2026', ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </div>
              </td>

              <!-- Achievement Remarks -->
              <td>
                <span style="font-size: 0.78rem; color: var(--hrms-text-muted, #64748b); display: block; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($t['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($t['description'] ?? 'No remarks provided.', ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>

              <!-- Status Toggle -->
              <td style="text-align: center;">
                <form method="POST" action="<?php echo ADMIN_URL; ?>/toppers" style="display: inline; margin: 0;">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="id" value="<?php echo intval($t['id']); ?>">
                  <input type="hidden" name="status" value="<?php echo htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8'); ?>">
                  <button type="submit" style="background: none; border: none; cursor: pointer; padding: 0;" title="Click to toggle status">
                    <?php if ($is_active): ?>
                      <span class="sid-fee-paid" style="cursor: pointer;">
                        <i class="fa-solid fa-circle-check"></i> Active
                      </span>
                    <?php else: ?>
                      <span class="sid-fee-due" style="cursor: pointer;">
                        <i class="fa-solid fa-circle-xmark"></i> Inactive
                      </span>
                    <?php endif; ?>
                  </button>
                </form>
              </td>

              <!-- Action Buttons -->
              <td style="text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; align-items: center; gap: 6px;">
                  <button type="button" class="sid-action-btn edit" onclick="editTopper(<?php echo htmlspecialchars(json_encode($t, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit Topper Details">
                    <i class="fa-solid fa-pencil"></i>
                  </button>
                  <button type="button" class="sid-action-btn delete" onclick="confirmDeleteTopper(<?php echo intval($t['id']); ?>, <?php echo htmlspecialchars(json_encode((string)$t['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)" title="Delete Topper Record">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php endif; ?>

  <!-- Table Pagination & Per-Page Controls (Matching students.php) -->
  <div class="pagination-controls-wrap">
    
    <!-- Left: Showing summary & Limit Selector -->
    <div class="pagination-meta-group">
      <span style="font-size: 0.82rem; color: #475569;">
        <?php if ($total_records > 0): ?>
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> toppers
        <?php else: ?>
          Showing <strong>0</strong> toppers
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_topper_pagination_url(1, $search_query, $filter_status, $l, $current_view), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Toppers Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_topper_pagination_url($page - 1, $search_query, $filter_status, $limit, $current_view), ENT_QUOTES, 'UTF-8') : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_topper_pagination_url($p, $search_query, $filter_status, $limit, $current_view), ENT_QUOTES, 'UTF-8'); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_topper_pagination_url($page + 1, $search_query, $filter_status, $limit, $current_view), ENT_QUOTES, 'UTF-8') : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Website Status Legend -->
    <div class="roaster-table-legend">
      <span style="font-weight: 700; color: #475569; font-size: 0.74rem;">Website Status:</span>
      <div class="legend-item"><span class="legend-dot green"></span> Published Active</div>
      <div class="legend-item"><span class="legend-dot red"></span> Draft Inactive</div>
    </div>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: ADD NEW TOPPER
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="addTopperModal">
  <div class="hrms-modal-card" style="max-width: 560px; width: 100%; background: var(--hrms-card); border-radius: 12px; overflow: hidden; box-shadow: var(--hrms-shadow-lg);">
    
    <div class="hrms-modal-header" style="background: var(--hrms-card); border-bottom: 1px solid var(--hrms-border); padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
      <h3 style="font-size: 1rem; font-weight: 800; color: var(--hrms-text); margin: 0; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-trophy" style="color: #f59e0b;"></i> Add Academic Topper
      </h3>
      <button type="button" class="modal-close-btn" style="background: none; border: none; cursor: pointer; color: var(--hrms-text-muted); font-size: 1.1rem;" onclick="closeModal('addTopperModal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/toppers" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="action" value="create">

      <div class="hrms-modal-body" style="padding: 20px; max-height: 75vh; overflow-y: auto;">
        
        <!-- Academic Session (FIRST FIELD) -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-calendar-days" style="color: #0284c7;"></i> Academic Session <span style="color: #ef4444;">*</span>
          </label>
          <select name="session_id" class="hrms-input" required style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text); font-weight: 600;">
            <?php foreach ($all_sessions as $sess): ?>
              <option value="<?php echo intval($sess['id']); ?>" <?php echo ($sess['id'] == $topper_active_session_id) ? 'selected' : ''; ?>>
                Session <?php echo htmlspecialchars($sess['session_name'], ENT_QUOTES, 'UTF-8'); ?> <?php echo ($sess['id'] == $topper_active_session_id) ? '(Active - Toppers)' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span style="font-size: 0.70rem; color: var(--hrms-text-muted); margin-top: 4px; display: block;">
            Select which academic session this merit topper belongs to.
          </span>
        </div>

        <!-- Image Upload Field -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 6px; display: block;">
            Scholar Photograph (Image)
          </label>
          <input type="file" name="topper_image" accept="image/jpeg,image/png,image/webp,image/avif" class="hrms-input" style="width: 100%; padding: 8px; font-size: 0.82rem; border: 1.5px dashed var(--hrms-border); border-radius: 8px; background: var(--hrms-bg);">
          <span style="font-size: 0.70rem; color: var(--hrms-text-muted); margin-top: 4px; display: block;">
            Accepted formats: JPG, PNG, WEBP. Max size: 10MB.
          </span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
          <!-- Name -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Scholar Name <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" name="name" maxlength="150" class="hrms-input" placeholder="e.g. Aarav Sharma" required style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>

          <!-- Percentage -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Percentage / Score <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" name="percentage" maxlength="50" class="hrms-input" placeholder="e.g. 98.8%" required style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>
        </div>

        <!-- Subject -->
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
            Subject / Academic Stream <span style="color: #ef4444;">*</span>
          </label>
          <input type="text" name="subject" maxlength="150" class="hrms-input" placeholder="e.g. Science (Physics & Mathematics) or Commerce" required style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
          <!-- Rank Title -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Rank / Title
            </label>
            <input type="text" name="rank_title" maxlength="100" class="hrms-input" placeholder="e.g. Academy 1st Rank" style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>

          <!-- Year / Batch -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Examination / Batch
            </label>
            <input type="text" name="year_or_batch" maxlength="50" class="hrms-input" placeholder="e.g. HSC Board 2026" style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>
        </div>

        <!-- Description -->
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
            Description / Achievement Remarks
          </label>
          <textarea name="description" rows="3" maxlength="5000" class="hrms-input" placeholder="Enter student's achievement remarks, subject-wise scores, mentorship quote, or future ambitions..." style="width: 100%; padding: 8px 12px; font-size: 0.82rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text); resize: vertical;"></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <!-- Display Order -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Display Order
            </label>
            <input type="number" name="display_order" value="0" min="0" class="hrms-input" style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>

          <!-- Status -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Visibility Status
            </label>
            <select name="status" class="hrms-input" style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
              <option value="active">Active (Visible on Website)</option>
              <option value="inactive">Inactive (Draft / Hidden)</option>
            </select>
          </div>
        </div>

        <div style="margin-top: 20px;">
          <button type="submit" class="roaster-add-btn" style="width: 100%; justify-content: center; padding: 10px; font-size: 0.86rem;">
            <i class="fa-solid fa-check"></i> Save &amp; Publish Topper
          </button>
        </div>

      </div>
    </form>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: EDIT TOPPER
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="editTopperModal">
  <div class="hrms-modal-card" style="max-width: 560px; width: 100%; background: var(--hrms-card); border-radius: 12px; overflow: hidden; box-shadow: var(--hrms-shadow-lg);">
    
    <div class="hrms-modal-header" style="background: var(--hrms-card); border-bottom: 1px solid var(--hrms-border); padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
      <h3 style="font-size: 1rem; font-weight: 800; color: var(--hrms-text); margin: 0; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-pen-to-square" style="color: #0092df;"></i> Edit Academic Topper
      </h3>
      <button type="button" class="modal-close-btn" style="background: none; border: none; cursor: pointer; color: var(--hrms-text-muted); font-size: 1.1rem;" onclick="closeModal('editTopperModal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/toppers" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit_id" value="">

      <div class="hrms-modal-body" style="padding: 20px; max-height: 75vh; overflow-y: auto;">
        
        <!-- Academic Session (FIRST FIELD) -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-calendar-days" style="color: #0284c7;"></i> Academic Session <span style="color: #ef4444;">*</span>
          </label>
          <select name="session_id" id="edit_session_id" class="hrms-input" required style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text); font-weight: 600;">
            <?php foreach ($all_sessions as $sess): ?>
              <option value="<?php echo intval($sess['id']); ?>">
                Session <?php echo htmlspecialchars($sess['session_name'], ENT_QUOTES, 'UTF-8'); ?> <?php echo !empty($sess['is_current']) ? '(Current Active)' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span style="font-size: 0.70rem; color: var(--hrms-text-muted); margin-top: 4px; display: block;">
            Update which academic session this topper belongs to.
          </span>
        </div>

        <!-- Image Preview & Replacement -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 6px; display: block;">
            Scholar Photograph (Image)
          </label>
          <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <img id="edit_image_preview" src="" alt="" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 1px solid var(--hrms-border); background: #f1f5f9;">
            <div style="font-size: 0.74rem; color: var(--hrms-text-muted);">
              Current photo. To replace it, select a new file below:
            </div>
          </div>
          <input type="file" name="topper_image" accept="image/jpeg,image/png,image/webp,image/avif" class="hrms-input" style="width: 100%; padding: 8px; font-size: 0.82rem; border: 1.5px dashed var(--hrms-border); border-radius: 8px; background: var(--hrms-bg);">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
          <!-- Name -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Scholar Name <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" name="name" id="edit_name" maxlength="150" class="hrms-input" required style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>

          <!-- Percentage -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Percentage / Score <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" name="percentage" id="edit_percentage" maxlength="50" class="hrms-input" required style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>
        </div>

        <!-- Subject -->
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
            Subject / Academic Stream <span style="color: #ef4444;">*</span>
          </label>
          <input type="text" name="subject" id="edit_subject" maxlength="150" class="hrms-input" required style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
          <!-- Rank Title -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Rank / Title
            </label>
            <input type="text" name="rank_title" id="edit_rank_title" maxlength="100" class="hrms-input" style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>

          <!-- Year / Batch -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Examination / Batch
            </label>
            <input type="text" name="year_or_batch" id="edit_year_or_batch" maxlength="50" class="hrms-input" style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>
        </div>

        <!-- Description -->
        <div class="form-group" style="margin-bottom: 14px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
            Description / Achievement Remarks
          </label>
          <textarea name="description" id="edit_description" rows="3" maxlength="5000" class="hrms-input" style="width: 100%; padding: 8px 12px; font-size: 0.82rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text); resize: vertical;"></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <!-- Display Order -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Display Order
            </label>
            <input type="number" name="display_order" id="edit_display_order" min="0" class="hrms-input" style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
          </div>

          <!-- Status -->
          <div class="form-group">
            <label class="form-label" style="font-size: 0.78rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 4px; display: block;">
              Visibility Status
            </label>
            <select name="status" id="edit_status" class="hrms-input" style="width: 100%; padding: 8px 12px; font-size: 0.84rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text);">
              <option value="active">Active (Visible on Website)</option>
              <option value="inactive">Inactive (Draft / Hidden)</option>
            </select>
          </div>
        </div>

        <div style="margin-top: 20px;">
          <button type="submit" class="roaster-add-btn" style="width: 100%; justify-content: center; padding: 10px; font-size: 0.86rem;">
            <i class="fa-solid fa-check"></i> Save Changes
          </button>
        </div>

      </div>
    </form>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: DELETE TOPPER CONFIRMATION
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="deleteTopperModal">
  <div class="hrms-modal-card" style="max-width: 440px; width: 100%; background: var(--hrms-card); border-radius: 12px; overflow: hidden; box-shadow: var(--hrms-shadow-lg);">
    
    <div class="hrms-modal-header" style="background: var(--hrms-card); border-bottom: 1px solid var(--hrms-border); padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
      <h3 style="font-size: 0.98rem; font-weight: 800; color: #ef4444; margin: 0; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-triangle-exclamation"></i> Delete Topper Record
      </h3>
      <button type="button" class="modal-close-btn" style="background: none; border: none; cursor: pointer; color: var(--hrms-text-muted); font-size: 1.1rem;" onclick="closeModal('deleteTopperModal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/toppers">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="delete_id" value="">

      <div class="hrms-modal-body" style="padding: 20px; text-align: center;">
        <div style="width: 50px; height: 50px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin: 0 auto 14px;">
          <i class="fa-solid fa-trash"></i>
        </div>
        <p style="font-size: 0.88rem; color: var(--hrms-text); margin: 0 0 6px; font-weight: 700;">
          Are you sure you want to delete this record?
        </p>
        <p style="font-size: 0.80rem; color: var(--hrms-text-muted); margin: 0 0 20px;" id="delete_name_label">
          Scholar will be removed from the public website.
        </p>

        <div style="display: flex; gap: 10px; justify-content: center;">
          <button type="button" class="roaster-btn secondary" onclick="closeModal('deleteTopperModal')" style="padding: 8px 18px; font-size: 0.82rem;">
            Cancel
          </button>
          <button type="submit" class="roaster-btn primary" style="background: #ef4444; padding: 8px 18px; font-size: 0.82rem; border-color: #dc2626;">
            Yes, Delete Topper
          </button>
        </div>
      </div>
    </form>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: SET TOPPER ACTIVE SESSION (TOPPERS ONLY)
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="setTopperActiveSessionModal">
  <div class="hrms-modal-card" style="max-width: 480px; width: 100%; background: var(--hrms-card); border-radius: 12px; overflow: hidden; box-shadow: var(--hrms-shadow-lg);">
    
    <div class="hrms-modal-header" style="background: var(--hrms-card); border-bottom: 1px solid var(--hrms-border); padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
      <h3 style="font-size: 1rem; font-weight: 800; color: var(--hrms-text); margin: 0; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-calendar-check" style="color: #0284c7;"></i> Set Active Session for Toppers
      </h3>
      <button type="button" class="modal-close-btn" style="background: none; border: none; cursor: pointer; color: var(--hrms-text-muted); font-size: 1.1rem;" onclick="closeModal('setTopperActiveSessionModal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/toppers">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="action" value="set_topper_active_session">

      <div class="hrms-modal-body" style="padding: 20px;">
        
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 0.80rem; color: #1e40af; line-height: 1.5;">
          <i class="fa-solid fa-circle-info" style="color: #3b82f6; margin-right: 4px;"></i>
          <strong>Toppers Only:</strong> This sets which academic session is active for the <strong>Toppers Hall of Fame</strong> (on this admin page and the public website). It will <strong>NOT</strong> change the active session for Students, Fees, Attendance, or the rest of the ERP.
        </div>

        <div class="form-group" style="margin-bottom: 18px;">
          <label class="form-label" style="font-size: 0.80rem; font-weight: 700; color: var(--hrms-text); margin-bottom: 6px; display: block;">
            Select Active Session for Toppers <span style="color: #ef4444;">*</span>
          </label>
          <select name="topper_active_session_id" class="hrms-input" required style="width: 100%; padding: 10px 12px; font-size: 0.88rem; border: 1px solid var(--hrms-border); border-radius: 8px; background: var(--hrms-card); color: var(--hrms-text); font-weight: 700;">
            <?php foreach ($all_sessions as $sess): ?>
              <option value="<?php echo intval($sess['id']); ?>" <?php echo $sess['id'] == $topper_active_session_id ? 'selected' : ''; ?>>
                Session <?php echo htmlspecialchars($sess['session_name'], ENT_QUOTES, 'UTF-8'); ?> <?php echo $sess['id'] == $topper_active_session_id ? '(Current Active for Toppers)' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
          <button type="button" class="roaster-btn secondary" onclick="closeModal('setTopperActiveSessionModal')" style="padding: 8px 16px; font-size: 0.82rem;">
            Cancel
          </button>
          <button type="submit" class="roaster-btn primary" style="padding: 8px 18px; font-size: 0.82rem;">
            <i class="fa-solid fa-check"></i> Set Active Session
          </button>
        </div>

      </div>
    </form>

  </div>
</div>

<script>
function editTopper(data) {
  if (!data) return;
  document.getElementById('edit_id').value = data.id || '';
  if (document.getElementById('edit_session_id')) {
    document.getElementById('edit_session_id').value = data.session_id || '2';
  }
  document.getElementById('edit_name').value = data.name || '';
  document.getElementById('edit_percentage').value = data.percentage || '';
  document.getElementById('edit_subject').value = data.subject || '';
  document.getElementById('edit_rank_title').value = data.rank_title || '';
  document.getElementById('edit_year_or_batch').value = data.year_or_batch || '';
  document.getElementById('edit_description').value = data.description || '';
  document.getElementById('edit_display_order').value = data.display_order || 0;
  document.getElementById('edit_status').value = data.status || 'active';

  const preview = document.getElementById('edit_image_preview');
  if (preview) {
    const rawImg = (data && data.image) ? String(data.image).trim() : '';
    const isSafeImg = /^(https?:\/\/|\/assets\/|assets\/)/i.test(rawImg);
    preview.src = isSafeImg ? rawImg : '/assets/imgs/ksv-logo.jpg';
  }

  openModal('editTopperModal');
}

function confirmDeleteTopper(id, name) {
  document.getElementById('delete_id').value = id;
  const label = document.getElementById('delete_name_label');
  if (label) {
    label.textContent = 'Scholar "' + name + '" will be permanently deleted from the database.';
  }
  openModal('deleteTopperModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>

