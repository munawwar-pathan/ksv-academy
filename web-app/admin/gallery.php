<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'admin']);
$page_title = 'Gallery | KSV Academy HRMS';
$page_heading = 'Media Gallery';
$page_subheading = 'Visual Media Archive, Campus Events & Student Life Moments';
$active_menu = 'gallery';

$db = get_db_connection();
$active_session = get_active_session();

// ── Handle Actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add Photo
    if ($action === 'add_photo') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Campus');
        $image = '';

        // Process File Upload
        if (isset($_FILES['image_file']) && !empty($_FILES['image_file']['name'])) {
            $p_name = $_FILES['image_file']['name'];
            $p_tmp = $_FILES['image_file']['tmp_name'];
            $p_ext = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
            $allowed_img_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
            if (in_array($p_ext, $allowed_img_exts)) {
                $p_dir = __DIR__ . '/../uploads/gallery';
                if (!is_dir($p_dir)) {
                    mkdir($p_dir, 0777, true);
                }
                $filename = 'gal_' . time() . '_' . rand(1000, 9999) . '.' . $p_ext;
                $dest = $p_dir . '/' . $filename;
                if (move_uploaded_file($p_tmp, $dest)) {
                    $image = '/web-app/uploads/gallery/' . $filename;
                }
            }
        }

        // Fallback to Image URL if no file uploaded
        if (empty($image) && !empty($_POST['image_url'])) {
            $image = trim($_POST['image_url']);
        }

        if (!empty($title) && !empty($image)) {
            $stmt = $db->prepare("INSERT INTO gallery (title, category, image, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$title, $category, $image]);
            set_flash('success', "Media '{$title}' added to gallery successfully!");
        } else {
            set_flash('error', "Please provide both a title and an image.");
        }

        $redirect_view = $_POST['redirect_view'] ?? 'table';
        header('Location: ' . ADMIN_URL . '/gallery?view=' . urlencode($redirect_view));
        exit;
    }

    // 2. Edit Photo
    if ($action === 'edit_photo') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Campus');
        $image = trim($_POST['current_image'] ?? '');

        // New File Upload
        if (isset($_FILES['image_file']) && !empty($_FILES['image_file']['name'])) {
            $p_name = $_FILES['image_file']['name'];
            $p_tmp = $_FILES['image_file']['tmp_name'];
            $p_ext = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
            $allowed_img_exts = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
            if (in_array($p_ext, $allowed_img_exts)) {
                $p_dir = __DIR__ . '/../uploads/gallery';
                if (!is_dir($p_dir)) {
                    mkdir($p_dir, 0777, true);
                }
                $filename = 'gal_' . time() . '_' . rand(1000, 9999) . '.' . $p_ext;
                $dest = $p_dir . '/' . $filename;
                if (move_uploaded_file($p_tmp, $dest)) {
                    $image = '/web-app/uploads/gallery/' . $filename;
                }
            }
        } elseif (!empty($_POST['image_url'])) {
            $image = trim($_POST['image_url']);
        }

        if ($id > 0 && !empty($title) && !empty($image)) {
            $stmt = $db->prepare("UPDATE gallery SET title = ?, category = ?, image = ? WHERE id = ?");
            $stmt->execute([$title, $category, $image, $id]);
            set_flash('success', "Gallery media updated successfully!");
        } else {
            set_flash('error', "Title and image cannot be empty.");
        }

        $redirect_view = $_POST['redirect_view'] ?? 'table';
        header('Location: ' . ADMIN_URL . '/gallery?view=' . urlencode($redirect_view));
        exit;
    }

    // 3. Delete Photo
    if ($action === 'delete_photo') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("SELECT image FROM gallery WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            if ($item) {
                // If local uploaded file, delete from disk
                if (strpos($item['image'], '/web-app/uploads/gallery/') === 0) {
                    $local_path = __DIR__ . '/..' . str_replace('/web-app', '', $item['image']);
                    if (file_exists($local_path)) {
                        @unlink($local_path);
                    }
                }
                $del = $db->prepare("DELETE FROM gallery WHERE id = ?");
                $del->execute([$id]);
                set_flash('success', "Media deleted from gallery.");
            }
        }
        $redirect_view = $_POST['redirect_view'] ?? 'table';
        header('Location: ' . ADMIN_URL . '/gallery?view=' . urlencode($redirect_view));
        exit;
    }
}

// ── Query Data ──────────────────────────────────────────────────────────────
$filter_category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');
$view_mode = trim($_GET['view'] ?? 'table'); // 'table' or 'grid'

// Pagination Setup (10, 20, 30, 40, 50)
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

$where_clauses = ["1=1"];
$params = [];

if (!empty($filter_category) && $filter_category !== 'all') {
    $where_clauses[] = "category = ?";
    $params[] = $filter_category;
}

if (!empty($search)) {
    $where_clauses[] = "(title LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where_clauses);

// Count total filtered records
$count_query = "SELECT COUNT(*) FROM gallery WHERE {$where_sql}";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();

$total_pages = max(1, ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch paginated records
$query = "SELECT * FROM gallery WHERE {$where_sql} ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);
$p_idx = 1;
foreach ($params as $param_val) {
    $stmt->bindValue($p_idx++, $param_val);
}
$stmt->bindValue($p_idx++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($p_idx++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$gallery_items = $stmt->fetchAll();

// Get list of distinct categories for filters
$categories_stmt = $db->query("SELECT category, COUNT(*) as cnt FROM gallery GROUP BY category ORDER BY category ASC");
$category_counts = $categories_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_all_count = array_sum($category_counts);

// Helper function to build pagination url
function get_gallery_pagination_url($target_page, $filter_category, $search, $view_mode, $limit) {
    $p = [];
    if (!empty($filter_category) && $filter_category !== 'all') $p['category'] = $filter_category;
    if (!empty($search)) $p['search'] = $search;
    if ($view_mode !== 'table') $p['view'] = $view_mode;
    if ($limit !== 10) $p['limit'] = $limit;
    if ($target_page > 1) $p['page'] = $target_page;
    $q = !empty($p) ? '?' . http_build_query($p) : '';
    return ADMIN_URL . '/gallery' . $q;
}

// Helper function to return icon for category
function get_category_icon($cat) {
    $c = strtolower(trim($cat));
    if (strpos($c, 'camp') !== false) return 'fa-landmark';
    if (strpos($c, 'event') !== false) return 'fa-champagne-glasses';
    if (strpos($c, 'sport') !== false) return 'fa-trophy';
    if (strpos($c, 'acad') !== false) return 'fa-graduation-cap';
    if (strpos($c, 'celeb') !== false) return 'fa-wand-magic-sparkles';
    if (strpos($c, 'class') !== false) return 'fa-chalkboard-user';
    return 'fa-tag';
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Gallery Specific Enhanced Styles ── */
.gallery-tab-pill {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 700;
  color: #64748b;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  text-decoration: none;
  white-space: nowrap;
}
.gallery-tab-pill:hover {
  color: #0f172a;
  background: #f1f5f9;
}
.gallery-tab-pill.active {
  color: #ffffff;
  background: linear-gradient(135deg, #0092df 0%, #0066aa 100%);
  box-shadow: 0 4px 14px rgba(0, 146, 223, 0.3);
}
.gallery-tab-pill .tab-badge {
  font-size: 0.7rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.28);
  color: #ffffff;
}
.gallery-tab-pill:not(.active) .tab-badge {
  background: #e2e8f0;
  color: #475569;
}

/* ── Media Directory Table & Badges (Simple & Sober like Staff ID) ── */
.staff-id-table {
  width: 100%;
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
  padding: 10px 12px;
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

/* Category Badges */
.cat-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 5px;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.cat-pill.campus { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; }
.cat-pill.events { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.cat-pill.sports { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.cat-pill.academics { background: #faf5ff; color: #7c3aed; border: 1px solid #e9d5ff; }
.cat-pill.celebrations { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }
.cat-pill.other { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

/* Table Thumbnail */
.gallery-thumb-container {
  width: 58px;
  height: 42px;
  border-radius: 6px;
  overflow: hidden;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
  transition: all 0.2s ease;
  margin: 0 auto;
}
.gallery-thumb-container:hover {
  transform: scale(1.05);
  border-color: #0284c7;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}
.gallery-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.gallery-thumb-overlay {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  opacity: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 0.78rem;
  transition: opacity 0.15s ease;
}
.gallery-thumb-container:hover .gallery-thumb-overlay {
  opacity: 1;
}

/* Action Buttons */
.action-btn-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
}
.action-btn-pill.primary {
  background: #0284c7;
  color: #ffffff;
  border: 1px solid #0284c7;
}
.action-btn-pill.primary:hover {
  background: #0369a1;
  border-color: #0369a1;
}
.action-btn-pill.secondary {
  background: #ffffff;
  color: #475569;
  border: 1px solid #cbd5e1;
}
.action-btn-pill.secondary:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
}

/* Grid Cards */
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 24px;
  margin-bottom: 30px;
}
.gallery-card {
  background: #ffffff;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  box-shadow: 0 3px 14px rgba(0,0,0,0.04);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
}
.gallery-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 16px 32px -8px rgba(15, 23, 42, 0.12);
  border-color: #cbd5e1;
}
.gallery-card-img-wrap {
  width: 100%;
  height: 195px;
  background: #0f172a;
  position: relative;
  overflow: hidden;
  cursor: pointer;
}
.gallery-card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.gallery-card:hover .gallery-card-img {
  transform: scale(1.08);
}
.gallery-card-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 2;
  box-shadow: 0 4px 10px rgba(0,0,0,0.18);
}
.gallery-card-quick-zoom {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 0.85rem;
  opacity: 0;
  transform: scale(0.9);
  transition: all 0.2s ease;
}
.gallery-card:hover .gallery-card-quick-zoom {
  opacity: 1;
  transform: scale(1);
}
.gallery-card-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 40%, rgba(15, 23, 42, 0.75) 100%);
  display: flex;
  align-items: flex-end;
  padding: 14px;
  opacity: 0.9;
}
.gallery-card-body {
  padding: 18px 20px;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.gallery-card-title {
  font-size: 0.98rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px;
  line-height: 1.35;
  font-family: 'Outfit', sans-serif;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.gallery-card-meta {
  font-size: 0.76rem;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 14px;
}
.gallery-card-actions {
  display: flex;
  gap: 8px;
  border-top: 1px solid #f1f5f9;
  padding-top: 14px;
  justify-content: flex-end;
}

/* Lightbox Modal */
.lightbox-modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(10, 15, 25, 0.92);
  backdrop-filter: blur(12px);
  z-index: 2000;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.lightbox-modal.show {
  display: flex;
}
.lightbox-content {
  max-width: 920px;
  width: 100%;
  background: #0f172a;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.75);
  border: 1px solid rgba(255, 255, 255, 0.15);
  animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.lightbox-img-wrap {
  width: 100%;
  max-height: 68vh;
  background: #000000;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.lightbox-img {
  max-width: 100%;
  max-height: 68vh;
  object-fit: contain;
}
.lightbox-footer {
  padding: 16px 24px;
  background: #1e293b;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #ffffff;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

/* File Upload Dropzone */
.gallery-dropzone {
  border: 2px dashed #cbd5e1;
  border-radius: 12px;
  padding: 22px 18px;
  text-align: center;
  background: #f8fafc;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}
.gallery-dropzone:hover {
  border-color: #0092df;
  background: #f0f9ff;
}
</style>

<!-- ══════════════════════════════════════════════════════
     TOP TOOLBAR CARD & CONTROLS
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-bottom: 24px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Category Pills with Icons -->
    <div style="display: flex; gap: 6px; align-items: center; background: #f1f5f9; padding: 4px; border-radius: 12px; flex-wrap: wrap;">
      
      <a href="?category=all&view=<?php echo urlencode($view_mode); ?><?php echo $limit !== 10 ? '&limit=' . $limit : ''; ?>" class="gallery-tab-pill <?php echo (empty($filter_category) || $filter_category === 'all') ? 'active' : ''; ?>">
        <i class="fa-solid fa-layer-group"></i>
        <span>All Photos</span>
        <span class="tab-badge"><?php echo $total_all_count; ?></span>
      </a>

      <?php foreach ($category_counts as $cat_name => $count): ?>
        <?php $cat_icon = get_category_icon($cat_name); ?>
        <a href="?category=<?php echo urlencode($cat_name); ?>&view=<?php echo urlencode($view_mode); ?><?php echo $limit !== 10 ? '&limit=' . $limit : ''; ?>" class="gallery-tab-pill <?php echo $filter_category === $cat_name ? 'active' : ''; ?>">
          <i class="fa-solid <?php echo $cat_icon; ?>"></i>
          <span><?php echo htmlspecialchars($cat_name); ?></span>
          <span class="tab-badge"><?php echo $count; ?></span>
        </a>
      <?php endforeach; ?>

    </div>

    <!-- Right: Search, View Switcher & Add Button -->
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
      
      <!-- Search Form -->
      <form method="GET" action="<?php echo ADMIN_URL; ?>/gallery" style="display: flex; align-items: center; position: relative;">
        <?php if (!empty($filter_category)): ?>
          <input type="hidden" name="category" value="<?php echo htmlspecialchars($filter_category); ?>">
        <?php endif; ?>
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
        <?php if ($limit !== 10): ?>
          <input type="hidden" name="limit" value="<?php echo $limit; ?>">
        <?php endif; ?>
        
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; color: #94a3b8; font-size: 0.82rem; pointer-events: none;"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search photos..." style="padding: 7px 14px 7px 32px; border-radius: 9px; border: 1.5px solid #cbd5e1; font-size: 0.82rem; outline: none; width: 180px; background: #ffffff; transition: border-color 0.2s ease;">
        <?php if (!empty($search)): ?>
          <a href="?category=<?php echo urlencode($filter_category); ?>&view=<?php echo urlencode($view_mode); ?><?php echo $limit !== 10 ? '&limit=' . $limit : ''; ?>" style="position: absolute; right: 10px; color: #94a3b8; text-decoration: none; font-size: 0.85rem;" title="Clear search">&times;</a>
        <?php endif; ?>
      </form>

      <!-- View Switcher -->
      <div style="display: flex; background: #f1f5f9; padding: 3px; border-radius: 9px; border: 1px solid #e2e8f0;">
        <a href="?view=table<?php echo !empty($filter_category) ? '&category=' . urlencode($filter_category) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $limit !== 10 ? '&limit=' . $limit : ''; ?>" class="action-btn-pill <?php echo $view_mode === 'table' ? 'primary' : 'secondary'; ?>" style="padding: 6px 12px; border: none; <?php echo $view_mode === 'table' ? '' : 'background: transparent; color: #64748b;'; ?>" title="Table View">
          <i class="fa-solid fa-table-list"></i>
          <span>Table</span>
        </a>
        <a href="?view=grid<?php echo !empty($filter_category) ? '&category=' . urlencode($filter_category) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $limit !== 10 ? '&limit=' . $limit : ''; ?>" class="action-btn-pill <?php echo $view_mode === 'grid' ? 'primary' : 'secondary'; ?>" style="padding: 6px 12px; border: none; <?php echo $view_mode === 'grid' ? '' : 'background: transparent; color: #64748b;'; ?>" title="Grid View">
          <i class="fa-solid fa-grip"></i>
          <span>Grid</span>
        </a>
      </div>

      <!-- Upload Button -->
      <button type="button" onclick="openAddModal();" class="roaster-add-btn" style="padding: 7px 18px; font-size: 0.82rem;" title="Add Photo to Gallery">
        <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-plus"></i></div>
        <span>Upload Photo</span>
      </button>

    </div>

  </div>
</div>

<?php if (empty($gallery_items)): ?>
  <!-- Empty State -->
  <div class="hrms-card" style="text-align: center; padding: 60px 20px; margin-bottom: 30px; border-radius: 14px;">
    <div style="width: 64px; height: 64px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 16px;">
      <i class="fa-solid fa-images"></i>
    </div>
    <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 6px; font-family: 'Outfit', sans-serif;">
      No Gallery Photos Found
    </h3>
    <p style="font-size: 0.85rem; color: #64748b; max-width: 440px; margin: 0 auto 20px;">
      <?php if (!empty($search) || !empty($filter_category)): ?>
        No photos match your current search or category filter. Try resetting filters or uploading a new image.
      <?php else: ?>
        There are currently no media items in your school gallery. Start showcasing campus moments by uploading photos!
      <?php endif; ?>
    </p>
    <button type="button" onclick="openAddModal();" class="roaster-add-btn" style="display: inline-flex;">
      <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
      <span>Upload First Photo</span>
    </button>
  </div>
<?php else: ?>

  <?php if ($view_mode === 'grid'): ?>
    <!-- ══════════════════════════════════════════════════════
         VIEW MODE 1: VISUAL CARD GRID
         ══════════════════════════════════════════════════════ -->
    <div class="gallery-grid">
      <?php foreach ($gallery_items as $item): ?>
        <?php
          $cat_key = strtolower(trim($item['category']));
          $badge_class = 'other';
          if (strpos($cat_key, 'camp') !== false) $badge_class = 'campus';
          elseif (strpos($cat_key, 'event') !== false) $badge_class = 'events';
          elseif (strpos($cat_key, 'sport') !== false) $badge_class = 'sports';
          elseif (strpos($cat_key, 'acad') !== false) $badge_class = 'academics';
          elseif (strpos($cat_key, 'celeb') !== false) $badge_class = 'celebrations';
          $icon_class = get_category_icon($item['category']);
        ?>
        <div class="gallery-card">
          <div class="gallery-card-img-wrap" onclick="openLightbox('<?php echo htmlspecialchars($item['image']); ?>', '<?php echo htmlspecialchars(addslashes($item['title'])); ?>', '<?php echo htmlspecialchars(addslashes($item['category'])); ?>')">
            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="gallery-card-img" onerror="this.src='/assets/imgs/ksv-logo.png'">
            
            <div class="gallery-card-badge">
              <span class="cat-pill <?php echo $badge_class; ?>">
                <i class="fa-solid <?php echo $icon_class; ?>"></i>
                <?php echo htmlspecialchars($item['category']); ?>
              </span>
            </div>

            <div class="gallery-card-quick-zoom" title="Preview Photo">
              <i class="fa-solid fa-expand"></i>
            </div>

            <div class="gallery-card-overlay">
              <span style="color: #ffffff; font-size: 0.78rem; font-weight: 700; display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-magnifying-glass-plus"></i> Click to preview
              </span>
            </div>
          </div>

          <div class="gallery-card-body">
            <div>
              <h4 class="gallery-card-title"><?php echo htmlspecialchars($item['title']); ?></h4>
              <div class="gallery-card-meta">
                <i class="fa-regular fa-calendar-check" style="color: #94a3b8;"></i>
                <span><?php echo date('d M Y, h:i A', strtotime($item['created_at'])); ?></span>
              </div>
            </div>

            <div class="gallery-card-actions">
              <button type="button" onclick="openLightbox('<?php echo htmlspecialchars($item['image']); ?>', '<?php echo htmlspecialchars(addslashes($item['title'])); ?>', '<?php echo htmlspecialchars(addslashes($item['category'])); ?>')" class="action-btn-pill secondary" style="padding: 6px 11px; font-size: 0.76rem;" title="View Preview">
                <i class="fa-solid fa-eye"></i>
              </button>

              <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>);" class="action-btn-pill primary" style="padding: 6px 12px; font-size: 0.76rem;" title="Edit Media Details">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>Edit</span>
              </button>

              <form method="POST" action="<?php echo ADMIN_URL; ?>/gallery" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete \'<?php echo addslashes($item['title']); ?>\'?');">
                <input type="hidden" name="action" value="delete_photo">
                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                <input type="hidden" name="redirect_view" value="grid">
                <button type="submit" class="action-btn-pill secondary" style="padding: 6px 10px; font-size: 0.76rem; color: #dc2626; border-color: #fecdd3;" title="Delete Media">
                  <i class="fa-solid fa-trash-can"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Grid View Footer Pagination & Per-Page Controls -->
    <div class="hrms-card no-print" style="margin-top: 24px; margin-bottom: 30px; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 18px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
      <div class="pagination-controls-wrap">
        
        <!-- Left: Showing summary & Limit Selector -->
        <div class="pagination-meta-group">
          <span style="font-size: 0.82rem; color: #475569;">
            <?php if ($total_records > 0): ?>
              Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'photo' : 'photos'; ?>
            <?php else: ?>
              Showing <strong>0</strong> photos
            <?php endif; ?>
          </span>

          <div class="pagination-limit-box">
            <label for="perPageLimitSelectGrid" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
            <select id="perPageLimitSelectGrid" class="pagination-limit-select" onchange="window.location.href = this.value;">
              <?php foreach ($allowed_limits as $l): ?>
                <option value="<?php echo htmlspecialchars(get_gallery_pagination_url(1, $filter_category, $search, $view_mode, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
                  <?php echo $l; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Center/Right: Navigation page buttons -->
        <?php if ($total_pages > 1): ?>
          <nav class="pagination-nav" aria-label="Media Gallery Grid Pagination">
            <!-- Prev Page -->
            <a href="<?php echo $page > 1 ? htmlspecialchars(get_gallery_pagination_url($page - 1, $filter_category, $search, $view_mode, $limit)) : '#'; ?>" 
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
              <a href="<?php echo htmlspecialchars(get_gallery_pagination_url($p, $filter_category, $search, $view_mode, $limit)); ?>" 
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
            <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_gallery_pagination_url($page + 1, $filter_category, $search, $view_mode, $limit)) : '#'; ?>" 
               class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
               aria-label="Next Page"
               title="Next Page">
              <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
            </a>
          </nav>
        <?php endif; ?>

      </div>
    </div>

  <?php else: ?>
    <!-- ══════════════════════════════════════════════════════
         VIEW MODE 2: DIRECTORY TABLE (Default)
         ══════════════════════════════════════════════════════ -->
    <div class="hrms-card" style="margin-bottom: 24px; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; background: #ffffff;">
      
      <div class="hrms-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 18px; background: #ffffff;">
        <div>
          <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
            <i class="fa-solid fa-images" style="color: #0284c7;"></i>
            Media Directory &amp; Photo Records
          </h3>
          <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
            Manage and catalog all campus photography, event archives, and visual records.
          </p>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
          <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.74rem; font-weight: 600; color: #475569; background: #f8fafc; padding: 3px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <i class="fa-solid fa-photo-film" style="color: #0284c7; font-size: 0.72rem;"></i>
            <?php echo $total_records; ?> Total Photos
          </span>
        </div>
      </div>

      <div class="table-responsive">
        <table class="hrms-table staff-id-table">
          <thead>
            <tr>
              <th style="width: 76px; text-align: center;">Photo</th>
              <th style="min-width: 220px;">Title &amp; File Details</th>
              <th style="min-width: 150px;">Category</th>
              <th style="min-width: 140px;">Uploaded Date</th>
              <th style="text-align: right; width: 120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($gallery_items)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 36px 20px;">
                  <i class="fa-regular fa-images" style="font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 8px;"></i>
                  <span>No media records found matching current filter.</span>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($gallery_items as $item): ?>
                <?php
                  $cat_key = strtolower(trim($item['category']));
                  $badge_class = 'other';
                  if (strpos($cat_key, 'camp') !== false) $badge_class = 'campus';
                  elseif (strpos($cat_key, 'event') !== false) $badge_class = 'events';
                  elseif (strpos($cat_key, 'sport') !== false) $badge_class = 'sports';
                  elseif (strpos($cat_key, 'acad') !== false) $badge_class = 'academics';
                  elseif (strpos($cat_key, 'celeb') !== false) $badge_class = 'celebrations';
                  $icon_class = get_category_icon($item['category']);
                ?>
                <tr>
                  <!-- 1. Image Thumbnail -->
                  <td style="text-align: center;">
                    <div class="gallery-thumb-container" onclick="openLightbox('<?php echo htmlspecialchars($item['image']); ?>', '<?php echo htmlspecialchars(addslashes($item['title'])); ?>', '<?php echo htmlspecialchars(addslashes($item['category'])); ?>')" title="Click to view full preview">
                      <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="gallery-thumb-img" onerror="this.src='/assets/imgs/ksv-logo.png'">
                      <div class="gallery-thumb-overlay">
                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                      </div>
                    </div>
                  </td>

                  <!-- 2. Title -->
                  <td>
                    <div style="font-weight: 700; font-size: 0.88rem; color: #0f172a; font-family: 'Outfit', sans-serif;">
                      <?php echo htmlspecialchars($item['title']); ?>
                    </div>
                    <div style="font-size: 0.72rem; color: #64748b; margin-top: 2px;">
                      Path: <?php echo htmlspecialchars(substr($item['image'], 0, 42) . (strlen($item['image']) > 42 ? '...' : '')); ?>
                    </div>
                  </td>

                  <!-- 3. Category -->
                  <td>
                    <span class="cat-pill <?php echo $badge_class; ?>">
                      <i class="fa-solid <?php echo $icon_class; ?>"></i>
                      <?php echo htmlspecialchars($item['category']); ?>
                    </span>
                  </td>

                  <!-- 4. Uploaded Date -->
                  <td>
                    <span style="font-size: 0.82rem; color: #475569; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                      <i class="fa-regular fa-calendar" style="color: #94a3b8; font-size: 0.74rem;"></i>
                      <?php echo date('d M Y', strtotime($item['created_at'])); ?>
                    </span>
                  </td>

                  <!-- 5. Actions -->
                  <td style="text-align: right;">
                    <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                      <button type="button" onclick="openLightbox('<?php echo htmlspecialchars($item['image']); ?>', '<?php echo htmlspecialchars(addslashes($item['title'])); ?>', '<?php echo htmlspecialchars(addslashes($item['category'])); ?>')" class="action-btn-pill secondary" style="padding: 4px 8px;" title="Preview Photo">
                        <i class="fa-solid fa-eye" style="font-size: 0.72rem;"></i>
                      </button>

                      <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>);" class="action-btn-pill primary" style="padding: 4px 8px;" title="Edit Media Details">
                        <i class="fa-solid fa-pencil" style="font-size: 0.72rem;"></i>
                      </button>

                      <form method="POST" action="<?php echo ADMIN_URL; ?>/gallery" style="display: inline;" onsubmit="return confirm('Delete \'<?php echo addslashes($item['title']); ?>\' from gallery?');">
                        <input type="hidden" name="action" value="delete_photo">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="redirect_view" value="table">
                        <button type="submit" class="action-btn-pill secondary" style="padding: 4px 8px; color: #dc2626; border-color: #fecdd3;" title="Delete Photo">
                          <i class="fa-solid fa-trash-can" style="font-size: 0.72rem;"></i>
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

      <!-- Table Footer Pagination & Per-Page Controls (Matches HRMS standard) -->
      <div class="pagination-controls-wrap">
        
        <!-- Left: Showing summary & Limit Selector -->
        <div class="pagination-meta-group">
          <span style="font-size: 0.82rem; color: #475569;">
            <?php if ($total_records > 0): ?>
              Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'photo' : 'photos'; ?>
            <?php else: ?>
              Showing <strong>0</strong> photos
            <?php endif; ?>
          </span>

          <div class="pagination-limit-box">
            <label for="perPageLimitSelectTable" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
            <select id="perPageLimitSelectTable" class="pagination-limit-select" onchange="window.location.href = this.value;">
              <?php foreach ($allowed_limits as $l): ?>
                <option value="<?php echo htmlspecialchars(get_gallery_pagination_url(1, $filter_category, $search, $view_mode, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
                  <?php echo $l; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Center/Right: Navigation page buttons -->
        <?php if ($total_pages > 1): ?>
          <nav class="pagination-nav" aria-label="Media Gallery Table Pagination">
            <!-- Prev Page -->
            <a href="<?php echo $page > 1 ? htmlspecialchars(get_gallery_pagination_url($page - 1, $filter_category, $search, $view_mode, $limit)) : '#'; ?>" 
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
              <a href="<?php echo htmlspecialchars(get_gallery_pagination_url($p, $filter_category, $search, $view_mode, $limit)); ?>" 
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
            <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_gallery_pagination_url($page + 1, $filter_category, $search, $view_mode, $limit)) : '#'; ?>" 
               class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
               aria-label="Next Page"
               title="Next Page">
              <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
            </a>
          </nav>
        <?php endif; ?>

      </div>

    </div>
  <?php endif; ?>

<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     MODAL 1: ADD / UPLOAD MEDIA
     ══════════════════════════════════════════════════════ -->
<div id="addMediaModal" class="hrms-modal" onclick="if (event.target === this) closeAddModal();">
  <div class="hrms-modal-card" style="max-width: 500px;">
    
    <div class="hrms-modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(0, 146, 223, 0.12); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-cloud-arrow-up"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Upload Gallery Media
          </h3>
          <span style="font-size: 0.74rem; color: #64748b;">Add photo with title, category and image</span>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal();" style="border: none; cursor: pointer; padding: 4px 8px; font-size: 1.2rem;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="hrms-modal-body">
      <form method="POST" action="<?php echo ADMIN_URL; ?>/gallery" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_photo">
        <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_mode); ?>">

        <!-- 1. Photo Title -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Photo Title <span style="color: #ef4444;">*</span>
          </label>
          <input type="text" name="title" class="form-control" required placeholder="e.g. Annual Sports Day 2026 Athletics" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 0.86rem;">
        </div>

        <!-- 2. Category -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Category <span style="color: #ef4444;">*</span>
          </label>
          <select name="category" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 0.86rem; background: #ffffff;">
            <option value="Campus">Campus</option>
            <option value="Events">Events</option>
            <option value="Sports">Sports</option>
            <option value="Academics">Academics</option>
            <option value="Celebrations">Celebrations</option>
            <option value="Classrooms">Classrooms</option>
            <option value="General">General</option>
          </select>
        </div>

        <!-- 3. Image File Upload -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Upload Image File <span style="color: #ef4444;">*</span>
          </label>
          <div class="gallery-dropzone" onclick="document.getElementById('addImageFile').click();">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 1.8rem; color: #0092df; margin-bottom: 8px;"></i>
            <div style="font-size: 0.84rem; font-weight: 700; color: #0f172a;">Click to browse image file</div>
            <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">Supports JPG, PNG, WEBP, AVIF</div>
            <input type="file" name="image_file" id="addImageFile" accept="image/*" style="display: none;" onchange="previewUploadImage(this, 'addImgPreview');">
          </div>
        </div>

        <!-- Or Image URL -->
        <div class="form-group" style="margin-bottom: 20px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Or Image URL / Relative Path
          </label>
          <input type="text" name="image_url" id="addImageUrl" placeholder="/assets/imgs/gallers/gallery-1.png" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 0.84rem;" oninput="previewUrlImage(this.value, 'addImgPreview');">
        </div>

        <!-- Live Preview Box -->
        <div id="addImgPreviewWrap" style="margin-bottom: 22px; display: none; text-align: center; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 12px;">
          <img id="addImgPreview" src="" alt="Preview" style="max-height: 140px; max-width: 100%; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
          <div style="font-size: 0.74rem; color: #64748b; margin-top: 6px;">Selected Image Preview</div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
          <button type="button" onclick="closeAddModal();" class="roaster-btn secondary" style="padding: 9px 18px;">
            Cancel
          </button>
          <button type="submit" class="roaster-add-btn" style="padding: 9px 20px;">
            <div class="btn-icon-circle"><i class="fa-solid fa-check"></i></div>
            <span>Upload Photo</span>
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL 2: EDIT MEDIA DETAILS
     ══════════════════════════════════════════════════════ -->
<div id="editMediaModal" class="hrms-modal" onclick="if (event.target === this) closeEditModal();">
  <div class="hrms-modal-card" style="max-width: 500px;">
    
    <div class="hrms-modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(0, 146, 223, 0.12); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-pen-to-square"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Edit Gallery Media
          </h3>
          <span style="font-size: 0.74rem; color: #64748b;">Update photo title, category, or replace image</span>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeEditModal();" style="border: none; cursor: pointer; padding: 4px 8px; font-size: 1.2rem;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="hrms-modal-body">
      <form method="POST" action="<?php echo ADMIN_URL; ?>/gallery" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit_photo">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="current_image" id="edit_current_image">
        <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_mode); ?>">

        <!-- 1. Photo Title -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Photo Title <span style="color: #ef4444;">*</span>
          </label>
          <input type="text" name="title" id="edit_title" class="form-control" required style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 0.86rem;">
        </div>

        <!-- 2. Category -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Category <span style="color: #ef4444;">*</span>
          </label>
          <select name="category" id="edit_category" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 0.86rem; background: #ffffff;">
            <option value="Campus">Campus</option>
            <option value="Events">Events</option>
            <option value="Sports">Sports</option>
            <option value="Academics">Academics</option>
            <option value="Celebrations">Celebrations</option>
            <option value="Classrooms">Classrooms</option>
            <option value="General">General</option>
          </select>
        </div>

        <!-- 3. Current Image Preview & Replace Option -->
        <div style="margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px;">
          <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 8px;">Current Image</label>
          <div style="display: flex; align-items: center; gap: 14px;">
            <img id="edit_preview_img" src="" alt="Current Image" style="width: 90px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #cbd5e1;">
            <div style="font-size: 0.76rem; color: #64748b; line-height: 1.4;">
              To change this photo, select a new file below or enter a new image path.
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Upload New Image File (Optional)
          </label>
          <input type="file" name="image_file" accept="image/*" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 0.84rem; background: #ffffff;">
        </div>

        <div class="form-group" style="margin-bottom: 22px;">
          <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Or Update Image URL / Path
          </label>
          <input type="text" name="image_url" id="edit_image_url" placeholder="/assets/imgs/gallers/gallery-1.png" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 0.84rem;">
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
          <button type="button" onclick="closeEditModal();" class="roaster-btn secondary" style="padding: 9px 18px;">
            Cancel
          </button>
          <button type="submit" class="roaster-add-btn" style="padding: 9px 20px;">
            <div class="btn-icon-circle"><i class="fa-solid fa-check"></i></div>
            <span>Update Photo</span>
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL 3: LIGHTBOX PREVIEW
     ══════════════════════════════════════════════════════ -->
<div id="lightboxModal" class="lightbox-modal" onclick="if (event.target === this) closeLightbox();">
  <div class="lightbox-content">
    <div style="padding: 14px 22px; display: flex; justify-content: space-between; align-items: center; background: #0f172a; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span id="lbCategory" class="cat-pill other" style="font-size: 0.72rem;">Category</span>
        <h4 id="lbTitle" style="margin: 0; font-size: 1rem; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;">Photo Preview</h4>
      </div>
      <button type="button" onclick="closeLightbox();" style="background: none; border: none; color: #94a3b8; font-size: 1.4rem; cursor: pointer;">&times;</button>
    </div>

    <div class="lightbox-img-wrap">
      <img id="lbImage" src="" alt="Full Preview" class="lightbox-img">
    </div>

    <div class="lightbox-footer">
      <span style="font-size: 0.8rem; opacity: 0.85;">
        <i class="fa-solid fa-shield-halved" style="color: #38bdf8; margin-right: 5px;"></i>
        KSV Academy Media Archive
      </span>
      <div style="display: flex; gap: 8px;">
        <a id="lbOriginalLink" href="#" target="_blank" class="action-btn-pill secondary" style="padding: 6px 14px; font-size: 0.78rem; text-decoration: none;">
          <i class="fa-solid fa-arrow-up-right-from-square"></i>
          <span>Open Original</span>
        </a>
        <button type="button" onclick="closeLightbox();" class="action-btn-pill primary" style="padding: 6px 14px; font-size: 0.78rem;">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function openAddModal() {
  const modal = document.getElementById('addMediaModal');
  if (modal) modal.classList.add('show');
}
function closeAddModal() {
  const modal = document.getElementById('addMediaModal');
  if (modal) modal.classList.remove('show');
}

function openEditModal(item) {
  document.getElementById('edit_id').value = item.id;
  document.getElementById('edit_title').value = item.title;
  document.getElementById('edit_category').value = item.category;
  document.getElementById('edit_current_image').value = item.image;
  document.getElementById('edit_image_url').value = item.image;
  document.getElementById('edit_preview_img').src = item.image;

  const modal = document.getElementById('editMediaModal');
  if (modal) modal.classList.add('show');
}
function closeEditModal() {
  const modal = document.getElementById('editMediaModal');
  if (modal) modal.classList.remove('show');
}

function openLightbox(imgSrc, title, category) {
  document.getElementById('lbImage').src = imgSrc;
  document.getElementById('lbTitle').innerText = title;
  document.getElementById('lbOriginalLink').href = imgSrc;
  
  const catEl = document.getElementById('lbCategory');
  catEl.innerText = category;
  catEl.className = 'cat-pill other';
  const c = category.toLowerCase();
  if (c.includes('camp')) catEl.className = 'cat-pill campus';
  else if (c.includes('event')) catEl.className = 'cat-pill events';
  else if (c.includes('sport')) catEl.className = 'cat-pill sports';
  else if (c.includes('acad')) catEl.className = 'cat-pill academics';
  else if (c.includes('celeb')) catEl.className = 'cat-pill celebrations';

  const lb = document.getElementById('lightboxModal');
  if (lb) lb.classList.add('show');
}
function closeLightbox() {
  const lb = document.getElementById('lightboxModal');
  if (lb) lb.classList.remove('show');
}

// Live Image Previews in Add Modal
function previewUploadImage(input, targetImgId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById(targetImgId);
      img.src = e.target.result;
      document.getElementById(targetImgId + 'Wrap').style.display = 'block';
    }
    reader.readAsDataURL(input.files[0]);
  }
}

function previewUrlImage(url, targetImgId) {
  if (url.trim() !== '') {
    const img = document.getElementById(targetImgId);
    img.src = url.trim();
    document.getElementById(targetImgId + 'Wrap').style.display = 'block';
  }
}

// Close modals on Escape key
window.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeAddModal();
    closeEditModal();
    closeLightbox();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
