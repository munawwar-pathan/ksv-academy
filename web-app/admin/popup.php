<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'admin']);
$page_title = 'Popup Slider Management | KSV Academy HRMS';
$page_heading = 'Promo Popup Slider';
$page_subheading = 'Manage Home & Admission Promo Modal Slides, Posters & Headlines';
$active_menu = 'popup';

$db = get_db_connection();
$active_session = get_active_session();

// Target directory for popup images: /assets/imgs/popup
$popup_fs_dir = realpath(__DIR__ . '/../../assets/imgs/popup') ?: (__DIR__ . '/../../assets/imgs/popup');
if (!is_dir($popup_fs_dir)) {
    @mkdir($popup_fs_dir, 0777, true);
}

// ── Ensure Database Table Exists ─────────────────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS popup_sliders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        image VARCHAR(500) NOT NULL,
        caption VARCHAR(255) DEFAULT '',
        link_url VARCHAR(255) DEFAULT '/admissions',
        sort_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    // ignore
}

// Helper: Get image dimension string
function get_image_dimension_info($web_path) {
    global $popup_fs_dir;
    $fs_path = null;

    if (strpos($web_path, '/assets/imgs/popup/') !== false) {
        $filename = basename($web_path);
        $candidate = $popup_fs_dir . '/' . $filename;
        if (file_exists($candidate)) {
            $fs_path = $candidate;
        }
    }

    if (!$fs_path) {
        $candidate2 = realpath(__DIR__ . '/../../' . ltrim($web_path, '/'));
        if ($candidate2 && file_exists($candidate2)) {
            $fs_path = $candidate2;
        }
    }

    if ($fs_path && file_exists($fs_path)) {
        $info = @getimagesize($fs_path);
        if ($info && !empty($info[0]) && !empty($info[1])) {
            $w = $info[0];
            $h = $info[1];
            $ratio = round($w / $h, 2);
            $ratio_text = ($ratio >= 0.62 && $ratio <= 0.70) ? '2:3' : ($w . ':' . $h);
            return [
                'width' => $w,
                'height' => $h,
                'string' => "{$w} × {$h} px",
                'ratio' => $ratio_text,
                'is_recommended' => ($ratio >= 0.60 && $ratio <= 0.72)
            ];
        }
    }

    return [
        'width' => null,
        'height' => null,
        'string' => '1024 × 1536 px (Rec.)',
        'ratio' => '2:3',
        'is_recommended' => true
    ];
}

// Scan popup directory for existing files
$existing_popup_files = [];
if (is_dir($popup_fs_dir)) {
    $files = scandir($popup_fs_dir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..' || $f === '.DS_Store') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif'])) {
            $file_path = $popup_fs_dir . '/' . $f;
            $info = @getimagesize($file_path);
            $dim_str = ($info && !empty($info[0])) ? "{$info[0]} × {$info[1]} px" : '';
            $existing_popup_files[] = [
                'filename' => $f,
                'web_path' => '/assets/imgs/popup/' . $f,
                'dimension' => $dim_str,
                'width' => $info[0] ?? null,
                'height' => $info[1] ?? null,
                'size_kb' => round(filesize($file_path) / 1024, 1)
            ];
        }
    }
}

// ── Handle Actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add Slide
    if ($action === 'add_slide') {
        $title = trim($_POST['title'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $link_url = trim($_POST['link_url'] ?? '/admissions');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $image = '';

        // Check if an existing file was chosen from popup folder
        if (!empty($_POST['selected_existing_file'])) {
            $chosen = basename(trim($_POST['selected_existing_file']));
            if (file_exists($popup_fs_dir . '/' . $chosen)) {
                $image = '/assets/imgs/popup/' . $chosen;
            }
        }

        // Process File Upload directly into assets/imgs/popup/
        if (isset($_FILES['image_file']) && !empty($_FILES['image_file']['name'])) {
            $p_name = $_FILES['image_file']['name'];
            $p_tmp = $_FILES['image_file']['tmp_name'];
            $p_ext = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

            if (in_array($p_ext, $allowed_exts)) {
                $clean_basename = 'popup-' . time() . '-' . rand(100, 999) . '.' . $p_ext;
                $dest = $popup_fs_dir . '/' . $clean_basename;
                if (move_uploaded_file($p_tmp, $dest)) {
                    $image = '/assets/imgs/popup/' . $clean_basename;
                }
            }
        }

        // Fallback to manual image URL/path if provided
        if (empty($image) && !empty($_POST['image_url'])) {
            $image = trim($_POST['image_url']);
        }

        if (!empty($title) && !empty($image)) {
            $stmt = $db->prepare("INSERT INTO popup_sliders (title, image, caption, link_url, sort_order, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $image, $caption, $link_url, $sort_order, $status]);
            set_flash('success', "Slide '{$title}' added successfully!");
        } else {
            set_flash('error', "Please provide a Slide Title and select or upload an Image.");
        }

        $redirect_view = $_POST['redirect_view'] ?? 'grid';
        header('Location: ' . ADMIN_URL . '/popup?status=' . urlencode($_GET['status'] ?? 'all') . '&view=' . urlencode($redirect_view));
        exit;
    }

    // 2. Edit Slide
    if ($action === 'edit_slide') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $link_url = trim($_POST['link_url'] ?? '/admissions');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $image = trim($_POST['current_image'] ?? '');

        // Check if existing file selected
        if (!empty($_POST['selected_existing_file'])) {
            $chosen = basename(trim($_POST['selected_existing_file']));
            if (file_exists($popup_fs_dir . '/' . $chosen)) {
                $image = '/assets/imgs/popup/' . $chosen;
            }
        }

        // Process File Upload
        if (isset($_FILES['image_file']) && !empty($_FILES['image_file']['name'])) {
            $p_name = $_FILES['image_file']['name'];
            $p_tmp = $_FILES['image_file']['tmp_name'];
            $p_ext = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

            if (in_array($p_ext, $allowed_exts)) {
                $clean_basename = 'popup-' . time() . '-' . rand(100, 999) . '.' . $p_ext;
                $dest = $popup_fs_dir . '/' . $clean_basename;
                if (move_uploaded_file($p_tmp, $dest)) {
                    $image = '/assets/imgs/popup/' . $clean_basename;
                }
            }
        } elseif (!empty($_POST['image_url'])) {
            $image = trim($_POST['image_url']);
        }

        if ($id > 0 && !empty($title) && !empty($image)) {
            $stmt = $db->prepare("UPDATE popup_sliders SET title = ?, image = ?, caption = ?, link_url = ?, sort_order = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $image, $caption, $link_url, $sort_order, $status, $id]);
            set_flash('success', "Popup slide updated successfully!");
        } else {
            set_flash('error', "Slide Title and Image cannot be empty.");
        }

        $redirect_view = $_POST['redirect_view'] ?? 'grid';
        header('Location: ' . ADMIN_URL . '/popup?status=' . urlencode($_GET['status'] ?? 'all') . '&view=' . urlencode($redirect_view));
        exit;
    }

    // 3. Toggle Status (Active / Inactive)
    if ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT status FROM popup_sliders WHERE id = ?");
            $stmt->execute([$id]);
            $current_status = $stmt->fetchColumn();
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';
            $upd = $db->prepare("UPDATE popup_sliders SET status = ? WHERE id = ?");
            $upd->execute([$new_status, $id]);
            set_flash('success', "Slide status changed to " . ucfirst($new_status) . ".");
        }
        $redirect_view = $_POST['redirect_view'] ?? 'grid';
        header('Location: ' . ADMIN_URL . '/popup?status=' . urlencode($_GET['status'] ?? 'all') . '&view=' . urlencode($redirect_view));
        exit;
    }

    // 4. Delete Slide
    if ($action === 'delete_slide') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM popup_sliders WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', "Popup slide removed successfully.");
        }
        $redirect_view = $_POST['redirect_view'] ?? 'grid';
        header('Location: ' . ADMIN_URL . '/popup?status=' . urlencode($_GET['status'] ?? 'all') . '&view=' . urlencode($redirect_view));
        exit;
    }
}

// ── Query Slider Data ────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? 'all');
$view_mode = trim($_GET['view'] ?? 'grid'); // 'grid' or 'table'

$query = "SELECT * FROM popup_sliders WHERE 1=1";
$params = [];

if ($filter_status === 'active') {
    $query .= " AND status = 'active'";
} elseif ($filter_status === 'inactive') {
    $query .= " AND status = 'inactive'";
}

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR caption LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY sort_order ASC, id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$slides = $stmt->fetchAll();

// Counts
$total_count = intval($db->query("SELECT COUNT(*) FROM popup_sliders")->fetchColumn());
$active_count = intval($db->query("SELECT COUNT(*) FROM popup_sliders WHERE status = 'active'")->fetchColumn());
$inactive_count = intval($db->query("SELECT COUNT(*) FROM popup_sliders WHERE status = 'inactive'")->fetchColumn());

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Popup Management Enhanced Styles ── */
.dim-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f1f5f9;
  color: #334155;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}
.dim-badge.recommended {
  background: #ecfdf5;
  color: #059669;
  border-color: #a7f3d0;
}

/* Card Styling for Portrait Flyers */
.popup-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 22px;
}
.popup-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
  transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
}
.popup-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 30px rgba(0, 146, 223, 0.16);
  border-color: #0092df;
}

/* 2:3 Aspect Ratio Container for Poster */
.popup-poster-wrap {
  position: relative;
  width: 100%;
  padding-top: 135%; /* ~2:3 portrait banner aspect ratio */
  background: #0f172a;
  overflow: hidden;
}
.popup-poster-img {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}
.popup-card:hover .popup-poster-img {
  transform: scale(1.04);
}

.popup-poster-top-bar {
  position: absolute;
  top: 10px;
  left: 10px;
  right: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 2;
}
.popup-order-pill {
  background: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(6px);
  color: #ffffff;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 3px 9px;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}
.popup-status-pill {
  font-size: 0.7rem;
  font-weight: 800;
  padding: 4px 10px;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  backdrop-filter: blur(6px);
}
.popup-status-pill.active {
  background: rgba(16, 185, 129, 0.9);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
}
.popup-status-pill.inactive {
  background: rgba(100, 116, 139, 0.85);
  color: #ffffff;
}

.popup-dim-overlay {
  position: absolute;
  bottom: 8px;
  left: 8px;
  right: 8px;
  z-index: 2;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(15, 23, 42, 0.78);
  backdrop-filter: blur(8px);
  color: #ffffff;
  padding: 5px 10px;
  border-radius: 8px;
  font-size: 0.7rem;
  font-weight: 600;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.popup-card-content {
  padding: 16px;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}
.popup-card-title {
  font-family: 'Outfit', sans-serif;
  font-size: 0.98rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.35;
  margin: 0 0 6px 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.popup-card-caption {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0 0 12px 0;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.popup-card-footer {
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

/* Guidance Dimension Callout Box */
.dimension-guide-box {
  background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
  border: 1.5px dashed #0092df;
  border-radius: 14px;
  padding: 16px 20px;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.guide-badge-pill {
  background: #ffffff;
  border: 1px solid #bae6fd;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 800;
  color: #0369a1;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 2px 6px rgba(0, 146, 223, 0.08);
}

/* Existing Image Choice Strip */
.existing-files-strip {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 10px;
  max-height: 160px;
  overflow-y: auto;
  padding: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-top: 8px;
}
.existing-file-thumb {
  position: relative;
  aspect-ratio: 2/3;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid #e2e8f0;
  cursor: pointer;
  background: #0f172a;
  transition: all 0.2s ease;
}
.existing-file-thumb:hover {
  border-color: #0092df;
  transform: scale(1.03);
}
.existing-file-thumb.selected {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.35);
}
.existing-file-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.existing-file-badge {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: rgba(15, 23, 42, 0.85);
  color: #ffffff;
  font-size: 0.62rem;
  font-weight: 700;
  text-align: center;
  padding: 2px 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Drag & Drop Zone */
.popup-dropzone {
  border: 2px dashed #cbd5e1;
  border-radius: 12px;
  padding: 22px 16px;
  text-align: center;
  background: #f8fafc;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}
.popup-dropzone:hover {
  border-color: #0092df;
  background: #f0f9ff;
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR POPUP SLIDER MANAGEMENT
   ══════════════════════════════════════════════════════════════════════ */

/* 1. Dimension Guidance Box */
[data-theme="dark"] .dimension-guide-box {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .dimension-guide-box h4 {
  color: #f8fafc !important;
}
[data-theme="dark"] .dimension-guide-box p {
  color: #94a3b8 !important;
}
[data-theme="dark"] .dimension-guide-box code {
  background: #0f172a !important;
  color: #38bdf8 !important;
  border: 1px solid #334155 !important;
  padding: 2px 6px;
  border-radius: 4px;
}
[data-theme="dark"] .guide-badge-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
  box-shadow: none !important;
}
[data-theme="dark"] .guide-badge-pill strong {
  color: inherit !important;
}
[data-theme="dark"] .guide-badge-pill[style*="#bbf7d0"],
[data-theme="dark"] .guide-badge-pill[style*="#166534"] {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
  color: #34d399 !important;
}

/* 2. Main Toolbar Card & Filters */
[data-theme="dark"] .roaster-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .roaster-toolbar-card div[style*="background: #f1f5f9"] {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .roaster-toolbar-card a[style*="#64748b"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-toolbar-card a[style*="#64748b"]:hover {
  color: #f8fafc !important;
  background: #1e293b !important;
}
[data-theme="dark"] .roaster-toolbar-card a span[style*="#e2e8f0"] {
  background: #1e293b !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .roaster-toolbar-card input[name="search"] {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-toolbar-card input[name="search"]:focus {
  border-color: #0092df !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .roaster-toolbar-card input[name="search"]::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .roaster-toolbar-card a[href*="view=grid"][style*="#fff"],
[data-theme="dark"] .roaster-toolbar-card a[href*="view=table"][style*="#fff"] {
  background: #1e293b !important;
  color: #0092df !important;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3) !important;
}
[data-theme="dark"] .roaster-toolbar-card a[href*="view=grid"][style*="transparent"],
[data-theme="dark"] .roaster-toolbar-card a[href*="view=table"][style*="transparent"] {
  color: #94a3b8 !important;
  background: transparent !important;
}
[data-theme="dark"] .roaster-toolbar-card a[href*="view=grid"]:hover,
[data-theme="dark"] .roaster-toolbar-card a[href*="view=table"]:hover {
  color: #f8fafc !important;
}

/* 3. Empty State Card */
[data-theme="dark"] div[style*="border: 1.5px dashed #cbd5e1"] {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] div[style*="border: 1.5px dashed #cbd5e1"] div[style*="background: #f0f9ff"] {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] div[style*="border: 1.5px dashed #cbd5e1"] h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] div[style*="border: 1.5px dashed #cbd5e1"] p {
  color: #94a3b8 !important;
}

/* 4. Grid View: Popup Cards */
[data-theme="dark"] .popup-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .popup-card:hover {
  border-color: #0092df !important;
  box-shadow: 0 12px 30px rgba(0, 146, 223, 0.22) !important;
}
[data-theme="dark"] .popup-card-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .popup-card-caption {
  color: #94a3b8 !important;
}
[data-theme="dark"] .popup-card-content div[style*="#64748b"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .popup-card-content code {
  background: #0f172a !important;
  color: #38bdf8 !important;
  border: 1px solid #334155 !important;
  padding: 2px 6px;
  border-radius: 4px;
}
[data-theme="dark"] .popup-card-footer {
  border-top-color: #334155 !important;
}
[data-theme="dark"] .popup-card-footer .action-btn-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .popup-card-footer .action-btn-pill:hover {
  background: #1e293b !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .action-btn-pill[style*="#d97706"] {
  color: #fbbf24 !important;
}
[data-theme="dark"] .action-btn-pill[style*="#059669"] {
  color: #34d399 !important;
}
[data-theme="dark"] .popup-card-footer button[onclick^="openEditModal"] {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .popup-card-footer button[onclick^="openEditModal"]:hover {
  background: #0092df !important;
  border-color: #0092df !important;
  color: #ffffff !important;
}
[data-theme="dark"] .popup-card-footer button[title="Delete Slide"] {
  background: rgba(220, 38, 38, 0.15) !important;
  border-color: rgba(220, 38, 38, 0.3) !important;
  color: #f87171 !important;
}
[data-theme="dark"] .popup-card-footer button[title="Delete Slide"]:hover {
  background: #dc2626 !important;
  border-color: #dc2626 !important;
  color: #ffffff !important;
}

/* 5. Directory Table View */
[data-theme="dark"] div:has(> div > table),
[data-theme="dark"] div[style*="box-shadow: 0 2px 10px rgba(0,0,0,0.03);"]:not(.roaster-toolbar-card) {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] table thead tr {
  background: #0f172a !important;
  border-bottom-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] table thead th {
  color: #94a3b8 !important;
}
[data-theme="dark"] table tbody tr {
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] table tbody tr:hover {
  background: rgba(255, 255, 255, 0.02) !important;
}
[data-theme="dark"] table tbody td {
  color: #cbd5e1 !important;
}
[data-theme="dark"] table tbody td:first-child {
  color: #f8fafc !important;
}
[data-theme="dark"] table tbody td div[style*="color: #0f172a;"] {
  color: #f8fafc !important;
}
[data-theme="dark"] table tbody td div[style*="color: #64748b;"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] table tbody td div[style*="color: #94a3b8;"] {
  color: #64748b !important;
}
[data-theme="dark"] table tbody td[style*="color: #0092df;"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] table tbody td div[style*="border: 1px solid #cbd5e1;"] {
  border-color: #334155 !important;
}
[data-theme="dark"] .dim-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .dim-badge.recommended {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
  color: #34d399 !important;
}
[data-theme="dark"] table tbody button[onclick^="openEditModal"] {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] table tbody button[onclick^="openEditModal"]:hover {
  background: #0092df !important;
  border-color: #0092df !important;
  color: #ffffff !important;
}
[data-theme="dark"] table tbody form button {
  background: rgba(220, 38, 38, 0.15) !important;
  border-color: rgba(220, 38, 38, 0.3) !important;
  color: #f87171 !important;
}
[data-theme="dark"] table tbody form button:hover {
  background: #dc2626 !important;
  border-color: #dc2626 !important;
  color: #ffffff !important;
}

/* 6. Modals (Add & Edit Slide Modals) */
[data-theme="dark"] .hrms-modal {
  background: rgba(0, 0, 0, 0.7) !important;
}
[data-theme="dark"] .hrms-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5) !important;
}
[data-theme="dark"] .hrms-modal-card > div:first-child {
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .hrms-modal-card h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-modal-card span[style*="#64748b"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .hrms-modal-card button[onclick^="close"]:not([style*="padding"]) {
  color: #94a3b8 !important;
}
[data-theme="dark"] .hrms-modal-card button[onclick^="close"]:not([style*="padding"]):hover {
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-modal-card label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .hrms-modal-card label code {
  background: #0f172a !important;
  color: #38bdf8 !important;
  border: 1px solid #334155 !important;
  padding: 2px 4px;
  border-radius: 4px;
}
[data-theme="dark"] .hrms-modal-card input[type="text"],
[data-theme="dark"] .hrms-modal-card input[type="number"],
[data-theme="dark"] .hrms-modal-card select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-modal-card input[type="text"]:focus,
[data-theme="dark"] .hrms-modal-card input[type="number"]:focus,
[data-theme="dark"] .hrms-modal-card select:focus {
  border-color: #0092df !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .hrms-modal-card select option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-modal-card div[style*="background: #f0fdf4"] {
  background: rgba(16, 185, 129, 0.12) !important;
  border-color: rgba(16, 185, 129, 0.25) !important;
}
[data-theme="dark"] .hrms-modal-card div[style*="background: #f0fdf4"] div[style*="color: #166534;"] {
  color: #34d399 !important;
}
[data-theme="dark"] .hrms-modal-card div[style*="background: #f0fdf4"] div[style*="color: #15803d;"] {
  color: #a7f3d0 !important;
}
[data-theme="dark"] .popup-dropzone {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .popup-dropzone:hover {
  background: #1e293b !important;
  border-color: #0092df !important;
}
[data-theme="dark"] .popup-dropzone div[style*="color: #0f172a;"] {
  color: #f8fafc !important;
}
[data-theme="dark"] .popup-dropzone div[style*="color: #64748b;"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .popup-dropzone code {
  background: #1e293b !important;
  color: #38bdf8 !important;
  border: 1px solid #334155 !important;
  padding: 2px 4px;
  border-radius: 4px;
}
[data-theme="dark"] #addDimStatus,
[data-theme="dark"] #editDimStatus {
  color: #38bdf8 !important;
}
[data-theme="dark"] #addPreviewWrap,
[data-theme="dark"] div[style*="padding: 12px; background: #f8fafc; border: 1px solid #cbd5e1;"] {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] #addPreviewDimText,
[data-theme="dark"] #editCurrentDimText {
  color: #f8fafc !important;
}
[data-theme="dark"] #addPreviewDimNote,
[data-theme="dark"] div[style*="color: #64748b; margin-top: 2px;"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] #editNewPreviewWrap {
  background: rgba(16, 185, 129, 0.12) !important;
  border-color: rgba(16, 185, 129, 0.25) !important;
}
[data-theme="dark"] #editNewDimText {
  color: #34d399 !important;
}
[data-theme="dark"] #editNewDimText + div {
  color: #a7f3d0 !important;
}
[data-theme="dark"] .existing-files-strip {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .existing-file-thumb {
  border-color: #334155 !important;
}
[data-theme="dark"] .existing-file-thumb:hover {
  border-color: #0092df !important;
}
[data-theme="dark"] .existing-file-thumb.selected {
  border-color: #10b981 !important;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.35) !important;
}
[data-theme="dark"] .hrms-modal-card button[onclick^="close"][style*="padding"] {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .hrms-modal-card button[onclick^="close"][style*="padding"]:hover {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #f8fafc !important;
}

/* ── Popup Toolbar Layout & Mobile Responsive ── */
.popup-toolbar-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
}

.popup-status-tabs {
  display: flex;
  gap: 6px;
  align-items: center;
}

.popup-controls-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: nowrap;
}

.popup-search-form {
  position: relative;
  display: flex;
  align-items: center;
}

.popup-search-form input[name="search"] {
  width: 220px;
  height: 38px;
  box-sizing: border-box;
}

.popup-view-toggle {
  display: flex;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
}

.popup-add-btn {
  height: 38px;
  padding: 0 16px;
  font-size: 0.82rem;
  flex-shrink: 0;
  gap: 7px;
}

@media (max-width: 768px) {
  .popup-toolbar-card {
    padding: 12px 14px !important;
    border-radius: 14px !important;
    margin-bottom: 16px !important;
  }

  .popup-toolbar-inner {
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 10px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  /* Row 1: Status Tabs */
  .popup-status-tabs {
    display: flex !important;
    width: 100% !important;
    gap: 4px !important;
    box-sizing: border-box !important;
  }

  .popup-status-tabs a {
    flex: 1 1 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 6px 6px !important;
    font-size: 0.78rem !important;
    text-align: center !important;
    white-space: nowrap !important;
    gap: 4px !important;
  }

  .popup-status-tabs a span:last-child {
    font-size: 0.70rem !important;
    padding: 1px 5px !important;
  }

  /* Controls Row: Search + View Toggle on Line 2, Add Button on Line 3 */
  .popup-controls-wrap {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: 8px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    min-width: 0 !important;
  }

  /* Line 2 left: Search */
  .popup-search-form {
    flex: 1 1 0 !important;
    width: 0 !important;
    min-width: 0 !important;
    position: relative !important;
    display: flex !important;
    align-items: center !important;
  }

  .popup-search-form input[name="search"] {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    height: 38px !important;
    box-sizing: border-box !important;
    font-size: 0.82rem !important;
    padding: 6px 26px 6px 34px !important;
    border-radius: 9px !important;
  }

  .popup-search-form i.fa-magnifying-glass {
    left: 12px !important;
    font-size: 0.80rem !important;
  }

  /* Line 2 right: View Toggle */
  .popup-view-toggle {
    padding: 2px !important;
    border-radius: 8px !important;
    flex-shrink: 0 !important;
    display: flex !important;
    gap: 2px !important;
    background: #f1f5f9 !important;
  }

  .popup-view-toggle a {
    padding: 6px 9px !important;
    font-size: 0.80rem !important;
    border-radius: 6px !important;
  }

  /* Line 3: Add Slide Button shifted to next line */
  .popup-add-btn {
    flex: 1 1 100% !important;
    width: 100% !important;
    height: 40px !important;
    padding: 0 16px !important;
    font-size: 0.84rem !important;
    font-weight: 700 !important;
    border-radius: 10px !important;
    white-space: nowrap !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    box-sizing: border-box !important;
    margin-top: 2px !important;
  }
}

/* Micro screens (<= 380px) */
@media (max-width: 380px) {
  .popup-controls-wrap {
    gap: 6px !important;
  }
  .popup-search-form input[name="search"] {
    font-size: 0.76rem !important;
    padding-left: 28px !important;
    padding-right: 20px !important;
  }
  .popup-search-form i.fa-magnifying-glass {
    left: 9px !important;
    font-size: 0.74rem !important;
  }
  .popup-view-toggle a {
    padding: 5px 7px !important;
    font-size: 0.76rem !important;
  }
  .popup-add-btn {
    height: 38px !important;
    font-size: 0.80rem !important;
  }
}
</style>

<!-- ══════════════════════════════════════════════════════
     TOP METRICS & RECOMMENDED DIMENSION NOTIFICATION
     ══════════════════════════════════════════════════════ -->
<div class="dimension-guide-box no-print">
  <div style="display: flex; align-items: center; gap: 14px;">
    <div style="width: 44px; height: 44px; border-radius: 12px; background: #0092df; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.35);">
      <i class="fa-solid fa-ruler-combined"></i>
    </div>
    <div>
      <h4 style="margin: 0; font-size: 0.96rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
        Image Dimension Requirement for Popup Slides
      </h4>
      <p style="margin: 2px 0 0 0; font-size: 0.8rem; color: #475569;">
        Images in this section are loaded directly from <code>/assets/imgs/popup/</code> and displayed in the automatic reload promo modal.
      </p>
    </div>
  </div>

  <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <div class="guide-badge-pill">
      <i class="fa-solid fa-expand" style="color: #0092df;"></i>
      <span>Recommended: <strong>1024 × 1536 px</strong></span>
    </div>
    <div class="guide-badge-pill" style="border-color: #bbf7d0; color: #166534;">
      <i class="fa-solid fa-mobile-screen" style="color: #10b981;"></i>
      <span>Aspect Ratio: <strong>2:3 (Portrait Flyer)</strong></span>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     TOOLBAR: FILTERS, SEARCH & ADD BUTTON
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card popup-toolbar-card no-print" style="margin-bottom: 24px; padding: 16px 20px; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
  <div class="popup-toolbar-inner">
    
    <!-- Left: Status Tabs -->
    <div class="popup-status-tabs" style="display: flex; gap: 6px; align-items: center; background: #f1f5f9; padding: 4px; border-radius: 12px;">
      <a href="?status=all&view=<?php echo urlencode($view_mode); ?>" style="text-decoration: none; padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; font-weight: 700; color: <?php echo $filter_status === 'all' ? '#fff' : '#64748b'; ?>; background: <?php echo $filter_status === 'all' ? '#0092df' : 'transparent'; ?>;">
        <span>All Slides</span>
        <span style="font-size: 0.72rem; padding: 2px 6px; border-radius: 10px; background: <?php echo $filter_status === 'all' ? 'rgba(255,255,255,0.3)' : '#e2e8f0'; ?>;"><?php echo $total_count; ?></span>
      </a>

      <a href="?status=active&view=<?php echo urlencode($view_mode); ?>" style="text-decoration: none; padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; font-weight: 700; color: <?php echo $filter_status === 'active' ? '#fff' : '#64748b'; ?>; background: <?php echo $filter_status === 'active' ? '#10b981' : 'transparent'; ?>;">
        <span>Active</span>
        <span style="font-size: 0.72rem; padding: 2px 6px; border-radius: 10px; background: <?php echo $filter_status === 'active' ? 'rgba(255,255,255,0.3)' : '#e2e8f0'; ?>;"><?php echo $active_count; ?></span>
      </a>

      <a href="?status=inactive&view=<?php echo urlencode($view_mode); ?>" style="text-decoration: none; padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; font-weight: 700; color: <?php echo $filter_status === 'inactive' ? '#fff' : '#64748b'; ?>; background: <?php echo $filter_status === 'inactive' ? '#64748b' : 'transparent'; ?>;">
        <span>Inactive</span>
        <span style="font-size: 0.72rem; padding: 2px 6px; border-radius: 10px; background: <?php echo $filter_status === 'inactive' ? 'rgba(255,255,255,0.3)' : '#e2e8f0'; ?>;"><?php echo $inactive_count; ?></span>
      </a>
    </div>

    <!-- Controls: Search + View Toggle + Add Button -->
    <div class="popup-controls-wrap">
      <!-- Search -->
      <form method="GET" action="<?php echo ADMIN_URL; ?>/popup" class="popup-search-form" style="display: flex; align-items: center; position: relative;">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; color: #94a3b8; font-size: 0.84rem;"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search slides by title..." style="padding: 8px 14px 8px 36px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.82rem; outline: none;">
        <?php if (!empty($search)): ?>
          <a href="?status=<?php echo urlencode($filter_status); ?>&view=<?php echo urlencode($view_mode); ?>" style="position: absolute; right: 10px; color: #94a3b8; text-decoration: none;">
            <i class="fa-solid fa-circle-xmark"></i>
          </a>
        <?php endif; ?>
      </form>

      <!-- View mode switch -->
      <div class="popup-view-toggle" style="display: flex; background: #f1f5f9; padding: 3px; border-radius: 8px;">
        <a href="?status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>&view=grid" title="Grid View" style="padding: 6px 10px; border-radius: 6px; color: <?php echo $view_mode === 'grid' ? '#0092df' : '#64748b'; ?>; background: <?php echo $view_mode === 'grid' ? '#fff' : 'transparent'; ?>; box-shadow: <?php echo $view_mode === 'grid' ? '0 1px 4px rgba(0,0,0,0.08)' : 'none'; ?>; text-decoration: none;">
          <i class="fa-solid fa-table-cells-large"></i>
        </a>
        <a href="?status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>&view=table" title="Table View" style="padding: 6px 10px; border-radius: 6px; color: <?php echo $view_mode === 'table' ? '#0092df' : '#64748b'; ?>; background: <?php echo $view_mode === 'table' ? '#fff' : 'transparent'; ?>; box-shadow: <?php echo $view_mode === 'table' ? '0 1px 4px rgba(0,0,0,0.08)' : 'none'; ?>; text-decoration: none;">
          <i class="fa-solid fa-list"></i>
        </a>
      </div>

      <!-- Add Slide Button -->
      <button type="button" onclick="openAddModal();" class="roaster-add-btn popup-add-btn" style="background: linear-gradient(135deg, #0092df 0%, #0066aa 100%); color: #ffffff; display: flex; align-items: center; border-radius: 10px; border: none; cursor: pointer; font-weight: 700; box-shadow: 0 4px 14px rgba(0, 146, 223, 0.35);">
        <i class="fa-solid fa-plus"></i>
        <span>Add Slide</span>
      </button>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MAIN CONTENT: SLIDES LIST (GRID OR TABLE)
     ══════════════════════════════════════════════════════ -->
<?php if (empty($slides)): ?>
  <div style="background: #ffffff; border-radius: 16px; padding: 60px 20px; text-align: center; border: 1.5px dashed #cbd5e1;">
    <div style="width: 64px; height: 64px; border-radius: 50%; background: #f0f9ff; color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 16px;">
      <i class="fa-solid fa-window-restore"></i>
    </div>
    <h3 style="margin: 0 0 6px 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
      No Popup Slides Found
    </h3>
    <p style="margin: 0 0 20px 0; font-size: 0.84rem; color: #64748b; max-width: 440px; margin-left: auto; margin-right: auto;">
      Add your first poster flyer slide with title and portrait image (1024 × 1536 px recommended) to showcase promotional offers.
    </p>
    <button type="button" onclick="openAddModal();" style="background: #0092df; color: #ffffff; padding: 10px 22px; border-radius: 10px; font-size: 0.86rem; font-weight: 700; border: none; cursor: pointer;">
      <i class="fa-solid fa-plus"></i> Add New Slide
    </button>
  </div>
<?php else: ?>

  <?php if ($view_mode === 'grid'): ?>
    <!-- GRID VIEW -->
    <div class="popup-grid">
      <?php foreach ($slides as $slide): 
        $dim = get_image_dimension_info($slide['image']);
        $img_src = (strpos($slide['image'], '/') === 0) ? $slide['image'] : ('/' . $slide['image']);
      ?>
        <div class="popup-card">
          <!-- Poster Top Image with 2:3 Aspect Ratio -->
          <div class="popup-poster-wrap">
            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($slide['title']); ?>" class="popup-poster-img" loading="lazy">
            
            <div class="popup-poster-top-bar">
              <span class="popup-order-pill" title="Slide Sort Order">
                <i class="fa-solid fa-arrow-down-1-9"></i> #<?php echo intval($slide['sort_order']); ?>
              </span>
              <span class="popup-status-pill <?php echo $slide['status']; ?>">
                <?php echo ucfirst($slide['status']); ?>
              </span>
            </div>

            <!-- Detected Image Dimensions Badge on Poster -->
            <div class="popup-dim-overlay">
              <span><i class="fa-solid fa-ruler"></i> <?php echo htmlspecialchars($dim['string']); ?></span>
              <span style="color: #38bdf8;"><i class="fa-solid fa-ratio"></i> <?php echo htmlspecialchars($dim['ratio']); ?></span>
            </div>
          </div>

          <!-- Body Content -->
          <div class="popup-card-content">
            <h4 class="popup-card-title" title="<?php echo htmlspecialchars($slide['title']); ?>">
              <?php echo htmlspecialchars($slide['title']); ?>
            </h4>

            <?php if (!empty($slide['caption'])): ?>
              <p class="popup-card-caption">
                <?php echo htmlspecialchars($slide['caption']); ?>
              </p>
            <?php else: ?>
              <p class="popup-card-caption" style="color: #94a3b8; font-style: italic;">
                No subtitle caption
              </p>
            <?php endif; ?>

            <div style="font-size: 0.74rem; color: #64748b; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <i class="fa-solid fa-link" style="color: #0092df;"></i>
              <code><?php echo htmlspecialchars($slide['link_url'] ?: '/admissions'); ?></code>
            </div>

            <!-- Card Actions -->
            <div class="popup-card-footer">
              <!-- Toggle Status Form -->
              <form method="POST" action="<?php echo ADMIN_URL; ?>/popup?status=<?php echo urlencode($filter_status); ?>&view=grid" style="margin: 0;">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                <input type="hidden" name="redirect_view" value="grid">
                <button type="submit" class="action-btn-pill" style="padding: 5px 10px; font-size: 0.72rem; font-weight: 700; border-radius: 6px; border: 1px solid #cbd5e1; background: #f8fafc; cursor: pointer; color: <?php echo $slide['status'] === 'active' ? '#d97706' : '#059669'; ?>;" title="Click to toggle status">
                  <i class="fa-solid <?php echo $slide['status'] === 'active' ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                  <span><?php echo $slide['status'] === 'active' ? 'Pause' : 'Activate'; ?></span>
                </button>
              </form>

              <div style="display: flex; gap: 6px;">
                <!-- Edit Button -->
                <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($slide)); ?>);" style="background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; border-radius: 6px; padding: 5px 10px; font-size: 0.76rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                  <i class="fa-solid fa-pen-to-square"></i>
                  <span>Edit</span>
                </button>

                <!-- Delete Button -->
                <form method="POST" action="<?php echo ADMIN_URL; ?>/popup?status=<?php echo urlencode($filter_status); ?>&view=grid" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete slide \'<?php echo addslashes($slide['title']); ?>\'?');">
                  <input type="hidden" name="action" value="delete_slide">
                  <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                  <input type="hidden" name="redirect_view" value="grid">
                  <button type="submit" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecdd3; border-radius: 6px; padding: 5px 8px; font-size: 0.76rem; cursor: pointer;" title="Delete Slide">
                    <i class="fa-solid fa-trash-can"></i>
                  </button>
                </form>
              </div>

            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <!-- TABLE VIEW -->
    <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.84rem;">
          <thead>
            <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; color: #475569; font-weight: 700; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em;">
              <th style="padding: 14px 16px; width: 60px;">Order</th>
              <th style="padding: 14px 16px; width: 80px;">Poster</th>
              <th style="padding: 14px 16px;">Title &amp; Caption</th>
              <th style="padding: 14px 16px;">Dimension Info</th>
              <th style="padding: 14px 16px;">Target Link</th>
              <th style="padding: 14px 16px; width: 100px;">Status</th>
              <th style="padding: 14px 16px; text-align: right; width: 130px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($slides as $slide): 
              $dim = get_image_dimension_info($slide['image']);
              $img_src = (strpos($slide['image'], '/') === 0) ? $slide['image'] : ('/' . $slide['image']);
            ?>
              <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                <td style="padding: 14px 16px; font-weight: 800; color: #0f172a;">
                  #<?php echo intval($slide['sort_order']); ?>
                </td>
                <td style="padding: 14px 16px;">
                  <div style="width: 50px; height: 75px; border-radius: 8px; overflow: hidden; background: #0f172a; border: 1px solid #cbd5e1;">
                    <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($slide['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                  </div>
                </td>
                <td style="padding: 14px 16px;">
                  <div style="font-weight: 800; color: #0f172a; font-size: 0.88rem; margin-bottom: 2px;">
                    <?php echo htmlspecialchars($slide['title']); ?>
                  </div>
                  <div style="color: #64748b; font-size: 0.78rem;">
                    <?php echo htmlspecialchars($slide['caption'] ?: '—'); ?>
                  </div>
                </td>
                <td style="padding: 14px 16px;">
                  <span class="dim-badge <?php echo $dim['is_recommended'] ? 'recommended' : ''; ?>">
                    <i class="fa-solid fa-ruler"></i> <?php echo htmlspecialchars($dim['string']); ?>
                  </span>
                  <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 3px;">
                    Ratio: <?php echo htmlspecialchars($dim['ratio']); ?>
                  </div>
                </td>
                <td style="padding: 14px 16px; color: #0092df; font-family: monospace; font-size: 0.78rem;">
                  <?php echo htmlspecialchars($slide['link_url'] ?: '/admissions'); ?>
                </td>
                <td style="padding: 14px 16px;">
                  <span class="popup-status-pill <?php echo $slide['status']; ?>" style="font-size: 0.68rem; padding: 3px 8px;">
                    <?php echo ucfirst($slide['status']); ?>
                  </span>
                </td>
                <td style="padding: 14px 16px; text-align: right;">
                  <div style="display: flex; gap: 6px; justify-content: flex-end;">
                    <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($slide)); ?>);" style="background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; border-radius: 6px; padding: 6px 10px; font-size: 0.76rem; font-weight: 700; cursor: pointer;">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/popup?status=<?php echo urlencode($filter_status); ?>&view=table" style="margin: 0;" onsubmit="return confirm('Delete slide \'<?php echo addslashes($slide['title']); ?>\'?');">
                      <input type="hidden" name="action" value="delete_slide">
                      <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                      <input type="hidden" name="redirect_view" value="table">
                      <button type="submit" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecdd3; border-radius: 6px; padding: 6px 10px; font-size: 0.76rem; cursor: pointer;">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     MODAL 1: ADD POPUP SLIDE
     ══════════════════════════════════════════════════════ -->
<div id="addSlideModal" class="hrms-modal" onclick="if (event.target === this) closeAddModal();">
  <div class="hrms-modal-card" style="background: #ffffff; border-radius: 18px; width: 100%; max-width: 620px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.25);">
    
    <div style="padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(0, 146, 223, 0.12); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
          <i class="fa-solid fa-file-circle-plus"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Add New Popup Slide
          </h3>
          <span style="font-size: 0.76rem; color: #64748b;">Specify Title and Image (1024 × 1536 px recommended)</span>
        </div>
      </div>
      <button type="button" onclick="closeAddModal();" style="background: none; border: none; font-size: 1.3rem; color: #94a3b8; cursor: pointer;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div style="padding: 24px;">
      <form method="POST" action="<?php echo ADMIN_URL; ?>/popup?status=<?php echo urlencode($filter_status); ?>&view=<?php echo urlencode($view_mode); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_slide">
        <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_mode); ?>">
        <input type="hidden" name="selected_existing_file" id="addSelectedExistingFile" value="">

        <!-- 1. Slide Title -->
        <div style="margin-bottom: 18px;">
          <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Slide Title <span style="color: #ef4444;">*</span>
          </label>
          <input type="text" name="title" required placeholder="e.g. Admissions &amp; Scholarship 2026-27" style="width: 100%; padding: 11px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.88rem; outline: none; box-sizing: border-box;">
        </div>

        <!-- 2. Subtitle / Caption -->
        <div style="margin-bottom: 18px;">
          <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Caption / Promo Subtitle
          </label>
          <input type="text" name="caption" placeholder="e.g. Up to 60% Scholarship • Admissions 2026–27" style="width: 100%; padding: 11px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.88rem; outline: none; box-sizing: border-box;">
        </div>

        <!-- 3. Image Dimension Callout Inside Form -->
        <div style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 10px; padding: 12px 14px; margin-bottom: 16px;">
          <div style="display: flex; align-items: center; gap: 8px; color: #166534; font-size: 0.82rem; font-weight: 800;">
            <i class="fa-solid fa-circle-info"></i>
            <span>Required Image Dimension: 1024 × 1536 px (Aspect Ratio 2:3)</span>
          </div>
          <div style="font-size: 0.74rem; color: #15803d; margin-top: 4px; line-height: 1.4;">
            For sharpest clarity and perfect vertical banner display in the reload promo popup modal. Minimum recommended: 800 × 1200 px. Supported formats: PNG, JPG, JPEG, WEBP.
          </div>
        </div>

        <!-- 4. Upload New Image File -->
        <div style="margin-bottom: 16px;">
          <label style="display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            <span>Slider Image <span style="color: #ef4444;">*</span></span>
            <span id="addDimStatus" style="font-size: 0.74rem; font-weight: 700; color: #0284c7;"></span>
          </label>

          <div class="popup-dropzone" onclick="document.getElementById('addSlideFile').click();">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2rem; color: #0092df; margin-bottom: 8px;"></i>
            <div style="font-size: 0.86rem; font-weight: 700; color: #0f172a;">Click to browse image or drag file here</div>
            <div style="font-size: 0.74rem; color: #64748b; margin-top: 3px;">
              📐 Dimension: 1024 × 1536 px (2:3) • Saved directly into <code>assets/imgs/popup/</code>
            </div>
            <input type="file" name="image_file" id="addSlideFile" accept="image/*" style="display: none;" onchange="handleImageSelection(this, 'addPreviewWrap', 'addPreviewImg', 'addDimStatus', 'addSelectedExistingFile');">
          </div>
        </div>

        <!-- Live Preview of Selected Image & Detected Dimensions -->
        <div id="addPreviewWrap" style="display: none; margin-bottom: 18px; padding: 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px;">
          <div style="display: flex; gap: 14px; align-items: center;">
            <div style="width: 60px; height: 90px; border-radius: 8px; overflow: hidden; background: #0f172a; flex-shrink: 0;">
              <img id="addPreviewImg" src="" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div>
              <div id="addPreviewDimText" style="font-size: 0.84rem; font-weight: 800; color: #0f172a;"></div>
              <div id="addPreviewDimNote" style="font-size: 0.74rem; color: #64748b; margin-top: 2px;"></div>
            </div>
          </div>
        </div>

        <!-- 5. Select From Existing Files in assets/imgs/popup/ -->
        <?php if (!empty($existing_popup_files)): ?>
          <div style="margin-bottom: 18px;">
            <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">
              Or Pick from Existing Images in <code>assets/imgs/popup/</code>:
            </label>
            <div class="existing-files-strip">
              <?php foreach ($existing_popup_files as $ex_file): ?>
                <div class="existing-file-thumb" onclick="selectExistingFile('<?php echo htmlspecialchars($ex_file['filename']); ?>', '<?php echo htmlspecialchars($ex_file['web_path']); ?>', '<?php echo htmlspecialchars($ex_file['dimension']); ?>', 'addSelectedExistingFile', 'addPreviewWrap', 'addPreviewImg', 'addPreviewDimText', 'addDimStatus', this);">
                  <img src="<?php echo htmlspecialchars($ex_file['web_path']); ?>" alt="<?php echo htmlspecialchars($ex_file['filename']); ?>" loading="lazy">
                  <div class="existing-file-badge"><?php echo htmlspecialchars($ex_file['dimension'] ?: $ex_file['filename']); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- 6. Target Link & Sort Order -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 18px;">
          <div>
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
              Target Link URL
            </label>
            <input type="text" name="link_url" value="/admissions" placeholder="/admissions" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.86rem; box-sizing: border-box;">
          </div>
          <div>
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
              Sort Order
            </label>
            <input type="number" name="sort_order" value="<?php echo count($slides) + 1; ?>" min="0" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.86rem; box-sizing: border-box;">
          </div>
        </div>

        <!-- 7. Status -->
        <div style="margin-bottom: 24px;">
          <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Status
          </label>
          <select name="status" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.86rem; background: #fff; box-sizing: border-box;">
            <option value="active">Active (Visible in promo slider)</option>
            <option value="inactive">Inactive (Hidden)</option>
          </select>
        </div>

        <!-- Footer Buttons -->
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" onclick="closeAddModal();" style="padding: 10px 18px; border-radius: 10px; border: 1.5px solid #cbd5e1; background: #f8fafc; font-size: 0.84rem; font-weight: 700; color: #64748b; cursor: pointer;">
            Cancel
          </button>
          <button type="submit" style="padding: 10px 22px; border-radius: 10px; border: none; background: #0092df; color: #ffffff; font-size: 0.86rem; font-weight: 700; cursor: pointer;">
            <i class="fa-solid fa-check"></i> Save Slide
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL 2: EDIT POPUP SLIDE
     ══════════════════════════════════════════════════════ -->
<div id="editSlideModal" class="hrms-modal" onclick="if (event.target === this) closeEditModal();">
  <div class="hrms-modal-card" style="background: #ffffff; border-radius: 18px; width: 100%; max-width: 620px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.25);">
    
    <div style="padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(0, 146, 223, 0.12); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
          <i class="fa-solid fa-pen-to-square"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Edit Popup Slide
          </h3>
          <span style="font-size: 0.76rem; color: #64748b;">Modify slide title, dimension-compliant image &amp; settings</span>
        </div>
      </div>
      <button type="button" onclick="closeEditModal();" style="background: none; border: none; font-size: 1.3rem; color: #94a3b8; cursor: pointer;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div style="padding: 24px;">
      <form method="POST" action="<?php echo ADMIN_URL; ?>/popup?status=<?php echo urlencode($filter_status); ?>&view=<?php echo urlencode($view_mode); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit_slide">
        <input type="hidden" name="id" id="editSlideId" value="">
        <input type="hidden" name="current_image" id="editSlideCurrentImage" value="">
        <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_mode); ?>">
        <input type="hidden" name="selected_existing_file" id="editSelectedExistingFile" value="">

        <!-- Title -->
        <div style="margin-bottom: 18px;">
          <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Slide Title <span style="color: #ef4444;">*</span>
          </label>
          <input type="text" name="title" id="editSlideTitle" required style="width: 100%; padding: 11px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.88rem; outline: none; box-sizing: border-box;">
        </div>

        <!-- Caption -->
        <div style="margin-bottom: 18px;">
          <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Caption / Promo Subtitle
          </label>
          <input type="text" name="caption" id="editSlideCaption" style="width: 100%; padding: 11px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.88rem; outline: none; box-sizing: border-box;">
        </div>

        <!-- Current Image Preview -->
        <div style="margin-bottom: 16px; padding: 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px;">
          <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 8px;">
            Active Image &amp; Detected Dimensions:
          </label>
          <div style="display: flex; gap: 14px; align-items: center;">
            <div style="width: 60px; height: 90px; border-radius: 8px; overflow: hidden; background: #0f172a; flex-shrink: 0;">
              <img id="editCurrentPreviewImg" src="" alt="Active Image" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div>
              <div id="editCurrentDimText" style="font-size: 0.84rem; font-weight: 800; color: #0f172a;"></div>
              <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
                📐 Recommended: 1024 × 1536 px (Aspect Ratio 2:3)
              </div>
            </div>
          </div>
        </div>

        <!-- Replace Image -->
        <div style="margin-bottom: 16px;">
          <label style="display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            <span>Replace Image File (Optional)</span>
            <span id="editDimStatus" style="font-size: 0.74rem; font-weight: 700; color: #0284c7;"></span>
          </label>

          <div class="popup-dropzone" onclick="document.getElementById('editSlideFile').click();">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 1.8rem; color: #0092df; margin-bottom: 6px;"></i>
            <div style="font-size: 0.84rem; font-weight: 700; color: #0f172a;">Click to select new image file</div>
            <div style="font-size: 0.72rem; color: #64748b; margin-top: 2px;">
              📐 Dimension: 1024 × 1536 px • Stored in <code>assets/imgs/popup/</code>
            </div>
            <input type="file" name="image_file" id="editSlideFile" accept="image/*" style="display: none;" onchange="handleImageSelection(this, 'editNewPreviewWrap', 'editNewPreviewImg', 'editDimStatus', 'editSelectedExistingFile');">
          </div>
        </div>

        <!-- New preview if picked -->
        <div id="editNewPreviewWrap" style="display: none; margin-bottom: 16px; padding: 12px; background: #ecfdf5; border: 1px solid #86efac; border-radius: 10px;">
          <div style="display: flex; gap: 14px; align-items: center;">
            <div style="width: 60px; height: 90px; border-radius: 8px; overflow: hidden; background: #0f172a; flex-shrink: 0;">
              <img id="editNewPreviewImg" src="" alt="New Image" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div>
              <div id="editNewDimText" style="font-size: 0.84rem; font-weight: 800; color: #065f46;"></div>
              <div style="font-size: 0.74rem; color: #047857; margin-top: 2px;">Ready to replace upon saving</div>
            </div>
          </div>
        </div>

        <!-- Pick from Existing Files -->
        <?php if (!empty($existing_popup_files)): ?>
          <div style="margin-bottom: 18px;">
            <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">
              Or Switch to Another File in <code>assets/imgs/popup/</code>:
            </label>
            <div class="existing-files-strip">
              <?php foreach ($existing_popup_files as $ex_file): ?>
                <div class="existing-file-thumb" onclick="selectExistingFile('<?php echo htmlspecialchars($ex_file['filename']); ?>', '<?php echo htmlspecialchars($ex_file['web_path']); ?>', '<?php echo htmlspecialchars($ex_file['dimension']); ?>', 'editSelectedExistingFile', 'editNewPreviewWrap', 'editNewPreviewImg', 'editNewDimText', 'editDimStatus', this);">
                  <img src="<?php echo htmlspecialchars($ex_file['web_path']); ?>" alt="<?php echo htmlspecialchars($ex_file['filename']); ?>" loading="lazy">
                  <div class="existing-file-badge"><?php echo htmlspecialchars($ex_file['dimension'] ?: $ex_file['filename']); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Link & Order -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 18px;">
          <div>
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
              Target Link URL
            </label>
            <input type="text" name="link_url" id="editSlideLink" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.86rem; box-sizing: border-box;">
          </div>
          <div>
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
              Sort Order
            </label>
            <input type="number" name="sort_order" id="editSlideOrder" min="0" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.86rem; box-sizing: border-box;">
          </div>
        </div>

        <!-- Status -->
        <div style="margin-bottom: 24px;">
          <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Status
          </label>
          <select name="status" id="editSlideStatus" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 0.86rem; background: #fff; box-sizing: border-box;">
            <option value="active">Active (Visible)</option>
            <option value="inactive">Inactive (Hidden)</option>
          </select>
        </div>

        <!-- Footer Buttons -->
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" onclick="closeEditModal();" style="padding: 10px 18px; border-radius: 10px; border: 1.5px solid #cbd5e1; background: #f8fafc; font-size: 0.84rem; font-weight: 700; color: #64748b; cursor: pointer;">
            Cancel
          </button>
          <button type="submit" style="padding: 10px 22px; border-radius: 10px; border: none; background: #0092df; color: #ffffff; font-size: 0.86rem; font-weight: 700; cursor: pointer;">
            <i class="fa-solid fa-check"></i> Update Slide
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     JAVASCRIPT: MODAL LOGIC & DIMENSION VERIFICATION
     ══════════════════════════════════════════════════════ -->
<script>
// Open Add Modal
function openAddModal() {
  const modal = document.getElementById('addSlideModal');
  if (modal) {
    modal.classList.add('show');
  }
}

function closeAddModal() {
  const modal = document.getElementById('addSlideModal');
  if (modal) {
    modal.classList.remove('show');
  }
}

// Open Edit Modal with populated data
function openEditModal(slide) {
  document.getElementById('editSlideId').value = slide.id;
  document.getElementById('editSlideTitle').value = slide.title || '';
  document.getElementById('editSlideCaption').value = slide.caption || '';
  document.getElementById('editSlideLink').value = slide.link_url || '/admissions';
  document.getElementById('editSlideOrder').value = slide.sort_order || 0;
  document.getElementById('editSlideStatus').value = slide.status || 'active';
  document.getElementById('editSlideCurrentImage').value = slide.image || '';

  var curImg = document.getElementById('editCurrentPreviewImg');
  var src = slide.image.startsWith('/') ? slide.image : ('/' + slide.image);
  curImg.src = src;

  // Read dimensions of current image
  var testImg = new Image();
  testImg.onload = function() {
    var ratio = (this.naturalWidth / this.naturalHeight).toFixed(2);
    document.getElementById('editCurrentDimText').innerHTML = 
      'Current: <strong>' + this.naturalWidth + ' × ' + this.naturalHeight + ' px</strong> (' + ratio + ' ratio)';
  };
  testImg.src = src;

  document.getElementById('editNewPreviewWrap').style.display = 'none';
  document.getElementById('editSelectedExistingFile').value = '';

  const modal = document.getElementById('editSlideModal');
  if (modal) {
    modal.classList.add('show');
  }
}

function closeEditModal() {
  const modal = document.getElementById('editSlideModal');
  if (modal) {
    modal.classList.remove('show');
  }
}

// Handle Image File Selection & Real-time Dimension Inspection
function handleImageSelection(input, previewWrapId, previewImgId, statusId, hiddenInputId) {
  if (input.files && input.files[0]) {
    var file = input.files[0];
    var reader = new FileReader();

    reader.onload = function(e) {
      var img = new Image();
      img.onload = function() {
        var w = this.naturalWidth;
        var h = this.naturalHeight;
        var ratio = (w / h).toFixed(2);
        var isRec = (w >= 750 && ratio >= 0.60 && ratio <= 0.72);

        var wrap = document.getElementById(previewWrapId);
        var previewImg = document.getElementById(previewImgId);
        previewImg.src = e.target.result;
        wrap.style.display = 'block';

        var statusEl = document.getElementById(statusId);
        var dimTextEl = document.getElementById(previewWrapId === 'addPreviewWrap' ? 'addPreviewDimText' : 'editNewDimText');

        if (dimTextEl) {
          dimTextEl.innerHTML = 'Detected: <strong>' + w + ' × ' + h + ' px</strong> (Ratio: ' + ratio + ')';
        }

        if (statusEl) {
          if (isRec) {
            statusEl.innerHTML = '<span style="color:#059669;"><i class="fa-solid fa-circle-check"></i> ' + w + '×' + h + ' px (Perfect fit)</span>';
          } else {
            statusEl.innerHTML = '<span style="color:#d97706;"><i class="fa-solid fa-triangle-exclamation"></i> ' + w + '×' + h + ' px (Rec: 1024×1536)</span>';
          }
        }

        // Reset existing file selection since user picked a local file
        if (hiddenInputId) {
          document.getElementById(hiddenInputId).value = '';
        }
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

// Select an existing image from assets/imgs/popup/
function selectExistingFile(filename, webPath, dimStr, hiddenInputId, previewWrapId, previewImgId, dimTextId, statusId, el) {
  document.getElementById(hiddenInputId).value = filename;

  // Visual selection highlighting
  var parent = el.parentNode;
  var items = parent.querySelectorAll('.existing-file-thumb');
  items.forEach(function(item) { item.classList.remove('selected'); });
  el.classList.add('selected');

  var wrap = document.getElementById(previewWrapId);
  var previewImg = document.getElementById(previewImgId);
  previewImg.src = webPath;
  wrap.style.display = 'block';

  var statusEl = document.getElementById(statusId);
  if (statusEl) {
    statusEl.innerHTML = '<span style="color:#059669;"><i class="fa-solid fa-check"></i> Selected: ' + filename + ' (' + (dimStr || '1024×1536') + ')</span>';
  }

  var dimText = document.getElementById(dimTextId);
  if (dimText) {
    dimText.innerHTML = 'Selected: <strong>' + filename + '</strong> (' + (dimStr || '1024 × 1536 px') + ')';
  }
}

// Escape key closes modals
window.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeAddModal();
    closeEditModal();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
