<?php
/**
 * KSV Academy - Public Study Notes & Reference Library
 * Open-access academic subject notes, question banks, and revision materials.
 * Strictly lists public notes with a secure read-only document viewer (no download feature).
 */

// Defense-in-depth HTTP security headers
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/web-app/config.php';

$active_page = 'notes';
$page_title = 'Study Notes & Reference Material | KSV Academy Ulwe | Free Academic Notes & Question Banks';
$page_description = 'Access official syllabus revision notes, formula sheets, chapter summaries, and practice problem sets prepared by KSV Academy faculty in Ulwe Sector 19. Available for Classes 5th-12th, IIT-JEE, NEET, and Degree courses.';
$page_keywords = 'KSV Academy notes, coaching study material Ulwe, 10th board revision notes, IIT JEE physics notes, NEET chemistry notes, 12th commerce accountancy notes, free question banks Ulwe, Salam Khan physics notes';
$canonical_url = rtrim(IS_LIVE ? LIVE_URL : SITE_URL, '/') . '/notes';
$page_image = rtrim(IS_LIVE ? LIVE_URL : SITE_URL, '/') . '/assets/imgs/students/classroom-learning.jpg';

$db = get_db_connection();
ensure_note_records_table($db);

// Academic Categories for Filter Dropdown
$academic_categories = get_default_academic_categories();
$flat_predefined_standards = [];
foreach ($academic_categories as $cat => $std_list) {
    foreach ($std_list as $s_item) {
        $flat_predefined_standards[] = $s_item;
    }
}
$db_standards = $db->query("SELECT DISTINCT standard FROM note_records WHERE visibility = 'public' AND standard IS NOT NULL AND standard != '' AND standard NOT IN ('All Classes / General', 'All Standards / General')")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$custom_db_standards = array_values(array_diff($db_standards, $flat_predefined_standards));

// Extract distinct subjects present in public notes
$public_subjects = $db->query("SELECT DISTINCT subject FROM note_records WHERE visibility = 'public' AND subject IS NOT NULL AND subject != '' ORDER BY subject ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Filters & Search
$search = trim((string)($_GET['search'] ?? ''));
$filter_standard = trim((string)($_GET['standard'] ?? ''));
$filter_subject = trim((string)($_GET['subject'] ?? 'all'));

// Query: Strictly list public notes only
$where = ["n.visibility = 'public'"];
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

// Total public notes count
$total_public_stmt = $db->query("SELECT COUNT(*) FROM note_records WHERE visibility = 'public'");
$total_public_count = intval($total_public_stmt->fetchColumn());

// Matching notes query
$notes_sql = "
    SELECT n.*, 
           COALESCE(u.full_name, u.username, 'Academy Faculty') AS author_name 
    FROM note_records n 
    LEFT JOIN users u ON n.uploaded_by = u.id 
    WHERE {$where_sql} 
    ORDER BY n.id DESC
";
$notes_stmt = $db->prepare($notes_sql);
$notes_stmt->execute($params);
$notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
$matching_count = count($notes);

if (!function_exists('format_public_note_size')) {
    function format_public_note_size($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════════════════
     1. HERO HEADER BANNER (Consistent with Public Website)
     ══════════════════════════════════════════════════════ -->
<section class="about-hero-banner">
  <div class="container text-center">
    <div class="about-banner-content">
      
      <!-- Dual Eyebrows -->
      <div class="hero-eyebrows-wrap" style="justify-content: center; margin-bottom: 16px;">
        <div class="hero-eyebrow">
          <span class="hero-live-dot" style="background: #0284c7;"></span>
          <span>Open Academic Library • Sector 19, Ulwe</span>
        </div>
        <div class="hero-trust-badge" style="background: rgba(2, 132, 199, 0.10); border-color: rgba(2, 132, 199, 0.25); color: #0284c7;">
          <i class="fa-solid fa-book-bookmark"></i>
          <span>Curated by Faculty • 100% Free Online Access</span>
        </div>
      </div>

      <!-- Main Heading -->
      <h1 class="about-banner-title">
        Study Notes &amp; <span class="hero-highlight">Reference Material</span>
      </h1>

      <!-- Slogan / Motto Tagline -->
      <div class="hero-motto-row" style="justify-content: center; margin-bottom: 16px;">
        <span class="motto-lead"><i class="fa-solid fa-sparkles"></i> Concept Clarity &amp; Exam Revision</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Chapter Summaries, Formula Sheets &amp; Question Banks</span>
      </div>

      <!-- Lead Description -->
      <p class="about-banner-desc" style="max-width: 820px; margin: 0 auto 24px;">
        Empowering students with structured, high-yield academic study notes prepared by KSV Academy expert teachers. Access official syllabus materials online anytime with our secure read-only document viewer.
      </p>

      <!-- Quick Stats Counter Strip -->
      <div class="hero-stats-row" style="max-width: 760px; margin: 0 auto;">
        <div class="stat-item">
          <span class="stat-number"><?php echo number_format($total_public_count); ?>+</span>
          <span class="stat-label">Public Notes</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number"><?php echo count($public_subjects); ?>+</span>
          <span class="stat-label">Subjects Covered</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number">5th-Degree</span>
          <span class="stat-label">All Standards</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number">100%</span>
          <span class="stat-label">Free &amp; Verified</span>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════
     2. STUDY NOTES DIRECTORY & FILTER SECTION
     ══════════════════════════════════════════════════════ -->
<section class="section-py" id="notesSection" style="padding-top: 36px; padding-bottom: 60px;">
  <div class="container">

    <!-- Integrated Filter & Search Toolbar -->
    <div class="public-notes-toolbar-card">
      <form method="GET" action="<?php echo htmlspecialchars(url('notes'), ENT_QUOTES, 'UTF-8'); ?>" class="public-notes-filter-form">
        
        <!-- Search Input -->
        <div class="public-search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search notes by title, topic, subject, or keywords..." class="public-search-input">
        </div>

        <!-- Filter Dropdowns Row -->
        <div class="public-filter-controls">
          
          <!-- Standard / Class Filter -->
          <div class="public-select-wrapper">
            <select name="standard" onchange="this.form.submit()" class="public-select-ctrl" title="Filter by Standard / Class">
              <option value="">All Academic Standards</option>
              <?php echo render_standards_dropdown_options($filter_standard, false, $academic_categories, $custom_db_standards); ?>
            </select>
            <i class="fa-solid fa-chevron-down arrow"></i>
          </div>

          <!-- Subject Filter -->
          <div class="public-select-wrapper">
            <select name="subject" onchange="this.form.submit()" class="public-select-ctrl" title="Filter by Subject">
              <option value="all">All Academic Subjects (<?php echo count($public_subjects); ?>)</option>
              <?php foreach ($public_subjects as $subj): ?>
                <option value="<?php echo htmlspecialchars($subj, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_subject === $subj ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($subj, ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-chevron-down arrow"></i>
          </div>

          <!-- Actions -->
          <div class="public-filter-buttons">
            <button type="submit" class="btn btn-primary btn-sm" style="padding: 9px 18px; font-size: 0.82rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
              <i class="fa-solid fa-filter"></i>
              <span>Filter</span>
            </button>

            <?php if (!empty($search) || !empty($filter_standard) || $filter_subject !== 'all'): ?>
              <a href="<?php echo htmlspecialchars(url('notes'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline btn-sm" style="padding: 9px 14px; font-size: 0.82rem;" title="Clear all filters">
                <i class="fa-solid fa-rotate-left"></i> Reset
              </a>
            <?php endif; ?>
          </div>

        </div>

      </form>

      <!-- Active Status Strip -->
      <div class="public-toolbar-status-row">
        <span class="status-count-badge">
          <i class="fa-solid fa-book-open"></i> Showing <strong><?php echo $matching_count; ?></strong> Public Notes
        </span>
        <span class="status-secure-badge">
          <i class="fa-solid fa-shield-halved"></i> Secure Read-Only Viewer Enabled
        </span>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         3. PUBLIC STUDY NOTES CARDS GRID
         ══════════════════════════════════════════════════════ -->
    <?php if (empty($notes)): ?>
      <div class="public-notes-empty-state">
        <div class="empty-icon-box">
          <i class="fa-solid fa-folder-open"></i>
        </div>
        <h3 class="empty-title">No Study Notes Found</h3>
        <p class="empty-desc">
          <?php if (!empty($search) || !empty($filter_standard) || $filter_subject !== 'all'): ?>
            No study notes matched your selected filters or search terms. Try clearing or broadening your search.
          <?php else: ?>
            Public study notes will be published here soon by our faculty. Please check back shortly!
          <?php endif; ?>
        </p>
        <?php if (!empty($search) || !empty($filter_standard) || $filter_subject !== 'all'): ?>
          <a href="<?php echo htmlspecialchars(url('notes'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-sm" style="margin-top: 14px;">
            <i class="fa-solid fa-rotate-left"></i> View All Notes
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="public-notes-grid">
        <?php foreach ($notes as $n): ?>
          <?php 
            $ftype = strtolower($n['file_type'] ?? 'pdf');
            $is_pdf = ($ftype === 'pdf');
            $is_img = in_array($ftype, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
            $badge_cls = $is_pdf ? 'pdf' : ($is_img ? 'image' : 'doc');
            $badge_icon = $is_pdf ? 'fa-solid fa-file-pdf' : ($is_img ? 'fa-solid fa-file-image' : 'fa-solid fa-file-lines');
          ?>
          <div class="public-note-card">
            
            <!-- Card Header: Badges inline horizontal flow -->
            <div class="note-card-top">
              <span class="note-type-badge <?php echo $badge_cls; ?>">
                <i class="<?php echo $badge_icon; ?>"></i>
                <span><?php echo strtoupper($ftype); ?></span>
              </span>
              <span class="subject-pill">
                <i class="fa-solid fa-book" style="color: #0284c7; font-size: 0.68rem;"></i>
                <span><?php echo htmlspecialchars($n['subject'], ENT_QUOTES, 'UTF-8'); ?></span>
              </span>
              <span class="standard-pill">
                <i class="fa-solid fa-graduation-cap" style="color: #2563eb; font-size: 0.68rem;"></i>
                <span><?php echo htmlspecialchars($n['standard'] ?? 'All Classes', ENT_QUOTES, 'UTF-8'); ?></span>
              </span>
            </div>

            <!-- Card Body: Title & Description -->
            <div class="note-card-body">
              <h3 class="note-card-title">
                <?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?>
              </h3>
              
              <p class="note-card-desc">
                <?php if (!empty($n['description'])): ?>
                  <?php echo htmlspecialchars(mb_substr($n['description'], 0, 110) . (mb_strlen($n['description']) > 110 ? '...' : ''), ENT_QUOTES, 'UTF-8'); ?>
                <?php else: ?>
                  Official syllabus study material and reference sheet for academic revision and conceptual preparation.
                <?php endif; ?>
              </p>
            </div>

            <!-- Card Meta: File size & Upload date (Ellipsis protected container) -->
            <div class="note-card-meta">
              <span class="meta-item meta-item-file" title="File Name: <?php echo htmlspecialchars($n['file_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa-solid fa-paperclip"></i>
                <span class="meta-file-text"><?php echo htmlspecialchars($n['file_name'] ?? 'document.' . $ftype, ENT_QUOTES, 'UTF-8'); ?></span>
              </span>
              <span class="meta-item meta-item-size">
                <i class="fa-solid fa-hard-drive"></i>
                <span><?php echo format_public_note_size($n['file_size']); ?></span>
              </span>
            </div>

            <!-- Card Footer: View Note Button (Strictly No Download) -->
            <div class="note-card-footer">
              <button type="button" class="btn btn-primary btn-view-note w-100" onclick='openPublicNoteViewer(<?php echo json_encode($n, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                <i class="fa-solid fa-eye"></i>
                <span>View Note</span>
              </button>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- ══════════════════════════════════════════════════════
     4. SECURE READ-ONLY DOCUMENT VIEWER MODAL
     ══════════════════════════════════════════════════════ -->
<div id="publicNoteViewerModal" class="public-viewer-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="publicViewerNoteTitle">
  <div class="public-viewer-card">
    
    <!-- Modal Header -->
    <div class="public-viewer-header">
      <div class="public-viewer-header-info">
        <div id="publicViewerFileIcon" class="note-type-badge pdf">
          <i class="fa-solid fa-file-pdf"></i>
        </div>
        <div class="public-viewer-title-meta">
          <div class="title-row">
            <h3 id="publicViewerNoteTitle" class="viewer-title">Note Document</h3>
            <span class="viewer-readonly-badge">
              <i class="fa-solid fa-shield-halved"></i> Read-Only View
            </span>
          </div>
          <div class="viewer-badges-row">
            <span id="publicViewerSubjectBadge" class="subject-pill">Subject</span>
            <span id="publicViewerStandardBadge" class="standard-pill">Standard</span>
            <span id="publicViewerFileSize" class="size-pill">0 KB</span>
          </div>
        </div>
      </div>

      <button type="button" class="public-viewer-close-btn" onclick="closePublicNoteViewer()" aria-label="Close dialog">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Body: Interactive Document Preview -->
    <div class="public-viewer-body">
      <!-- PDF View Container with disabled download/print toolbar -->
      <div id="publicViewerPdfWrap" style="display: none; width: 100%; height: 100%; position: relative;">
        <iframe id="publicViewerPdfFrame" src="" style="width: 100%; height: 70vh; border: none; border-radius: 8px; background: #ffffff;"></iframe>
      </div>

      <!-- Image View Container (Protected from Dragging & Right-Click) -->
      <div id="publicViewerImageWrap" style="display: none; width: 100%; height: 70vh; overflow: auto; text-align: center; background: #0f172a; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center; user-select: none; -webkit-user-select: none;">
        <img id="publicViewerImage" src="" alt="Note Preview" draggable="false" oncontextmenu="return false;" style="max-width: 100%; max-height: 66vh; object-fit: contain; border-radius: 6px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4); user-select: none; -webkit-user-select: none; -webkit-user-drag: none; pointer-events: none;">
      </div>

      <!-- Fallback Non-previewable Container -->
      <div id="publicViewerFallbackWrap" style="display: none; text-align: center; padding: 60px 20px; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; height: 100%;">
        <div style="width: 60px; height: 60px; border-radius: 12px; background: #eff6ff; color: #0284c7; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 12px;">
          <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h4 style="margin: 0; font-size: 1rem; color: #0f172a; font-weight: 700;">Protected Document</h4>
        <p style="margin: 6px 0 0; font-size: 0.80rem; color: #64748b; max-width: 480px; margin-inline: auto;">This educational resource is protected under academy security policies and is restricted to online read-only view.</p>
      </div>
    </div>

    <!-- Modal Footer: Description & Close Button (No Download) -->
    <div class="public-viewer-footer">
      <div id="publicViewerDescriptionWrap" class="viewer-desc-wrap">
        <span id="publicViewerDescription"></span>
      </div>

      <div class="viewer-actions-wrap">
        <button type="button" onclick="closePublicNoteViewer()" class="btn btn-outline" style="padding: 8px 24px; font-weight: 700; font-size: 0.84rem;">
          Close
        </button>
      </div>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     5. STYLES & SCRIPT FOR PUBLIC STUDY NOTES
     ══════════════════════════════════════════════════════ -->
<style>
/* Toolbar Card */
.public-notes-toolbar-card {
  background: var(--bg-surface, #ffffff);
  border: 1px solid var(--border-subtle, #e2e8f0);
  border-radius: 14px;
  padding: 18px 22px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
  margin-bottom: 28px;
}

.public-notes-filter-form {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
}

.public-search-box {
  position: relative;
  flex: 1;
  min-width: 260px;
}

.public-search-box i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted, #94a3b8);
  font-size: 0.85rem;
}

.public-search-input {
  width: 100%;
  padding: 9px 14px 9px 38px;
  font-size: 0.84rem;
  border: 1px solid var(--border-subtle, #cbd5e1);
  border-radius: 8px;
  background: var(--bg-surface, #ffffff);
  color: var(--text-primary, #0f172a);
  transition: all var(--transition-fast, 0.2s ease);
}

.public-search-input:focus {
  border-color: var(--primary-600, #0284c7);
  outline: none;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.15);
}

.public-filter-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.public-select-wrapper {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.public-select-ctrl {
  padding: 9px 28px 9px 14px;
  font-size: 0.82rem;
  font-weight: 600;
  border: 1px solid var(--border-subtle, #cbd5e1);
  border-radius: 8px;
  background: var(--bg-surface, #ffffff);
  color: var(--text-secondary, #334155);
  cursor: pointer;
  appearance: none;
  min-width: 160px;
  max-width: 240px;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.public-select-ctrl:focus {
  border-color: var(--primary-600, #0284c7);
  outline: none;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.15);
}

.public-select-wrapper i.arrow {
  position: absolute;
  right: 12px;
  color: var(--text-muted, #94a3b8);
  font-size: 0.68rem;
  pointer-events: none;
}

.public-filter-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.public-toolbar-status-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px dashed var(--border-subtle, #e2e8f0);
  font-size: 0.78rem;
  color: var(--text-muted, #64748b);
  flex-wrap: wrap;
  gap: 8px;
}

.status-count-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--text-secondary, #334155);
}

.status-count-badge strong {
  color: var(--primary-600, #0284c7);
}

.status-secure-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #059669;
  font-weight: 600;
  background: rgba(16, 185, 129, 0.08);
  padding: 3px 10px;
  border-radius: 12px;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

/* Public Notes Cards Grid */
.public-notes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 22px;
}

.public-note-card {
  background: var(--bg-surface, #ffffff);
  border: 1px solid var(--border-subtle, #e2e8f0);
  border-radius: 14px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
  transition: all var(--transition-fast, 0.2s ease);
  position: relative;
}

.public-note-card:hover {
  border-color: var(--primary-400, #38bdf8);
  box-shadow: 0 8px 24px rgba(0, 146, 223, 0.10);
  transform: translateY(-3px);
}

.note-card-top {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.note-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.70rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  flex-shrink: 0;
}

.note-type-badge.pdf { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.note-type-badge.image { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.note-type-badge.doc { background: #eff6ff; color: #0284c7; border: 1px solid #bfdbfe; }

.subject-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.71rem;
  font-weight: 700;
  background: var(--bg-surface-soft, #f1f5f9);
  color: var(--text-secondary, #334155);
  border: 1px solid var(--border-subtle, #e2e8f0);
}

.standard-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.71rem;
  font-weight: 700;
  background: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
}

.note-card-body {
  flex: 1;
  margin-bottom: 12px;
}

.note-card-title {
  font-family: var(--font-heading, 'Outfit', sans-serif);
  font-size: 0.98rem;
  font-weight: 800;
  color: var(--text-primary, #0f172a);
  line-height: 1.35;
  margin: 0 0 6px;
  word-break: break-word;
}

.note-card-desc {
  font-size: 0.80rem;
  color: var(--text-muted, #64748b);
  line-height: 1.5;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.note-card-meta {
  background: var(--bg-surface-soft, #f8fafc);
  border: 1px solid var(--border-subtle, #e2e8f0);
  border-radius: 8px;
  padding: 7px 10px;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 0.74rem;
  color: var(--text-muted, #64748b);
  box-sizing: border-box;
  width: 100%;
}

.meta-item-file {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  flex: 1;
  overflow: hidden;
}

.meta-item-file i {
  color: var(--primary-600, #0284c7);
  font-size: 0.75rem;
  flex-shrink: 0;
}

.meta-file-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
  font-weight: 500;
  color: var(--text-secondary, #334155);
}

.meta-item-size {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
  flex-shrink: 0;
  font-weight: 700;
  font-size: 0.72rem;
  color: var(--text-muted, #64748b);
}

.meta-item-size i {
  font-size: 0.68rem;
  color: #94a3b8;
}

.btn-view-note {
  font-weight: 700;
  font-size: 0.85rem;
  padding: 9px 18px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  border-radius: 8px;
  width: 100%;
}

/* Empty State */
.public-notes-empty-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--bg-surface, #ffffff);
  border: 1px solid var(--border-subtle, #e2e8f0);
  border-radius: 14px;
  margin-top: 10px;
}

.empty-icon-box {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--bg-surface-soft, #f1f5f9);
  color: var(--text-muted, #94a3b8);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  margin: 0 auto 14px;
}

.empty-title {
  font-family: var(--font-heading, 'Outfit', sans-serif);
  font-size: 1.10rem;
  font-weight: 800;
  color: var(--text-primary, #0f172a);
  margin: 0 0 6px;
}

.empty-desc {
  font-size: 0.84rem;
  color: var(--text-muted, #64748b);
  max-width: 480px;
  margin: 0 auto;
  line-height: 1.5;
}

/* ══════════════════════════════════════════════════════
   POPUP MODAL: SECURE READ-ONLY VIEWER
   ══════════════════════════════════════════════════════ */
.public-viewer-modal-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 15, 25, 0.78);
  backdrop-filter: blur(5px);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.public-viewer-modal-backdrop.show {
  display: flex !important;
}

.public-viewer-card {
  width: 100%;
  max-width: 920px;
  max-height: calc(100vh - 32px);
  max-height: calc(100dvh - 32px);
  display: flex;
  flex-direction: column;
  background: var(--bg-surface, #ffffff);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.45);
  margin: auto;
  border: 1px solid var(--border-subtle, #e2e8f0);
}

.public-viewer-header {
  flex-shrink: 0;
  padding: 14px 20px;
  background: var(--bg-surface, #ffffff);
  border-bottom: 1px solid var(--border-subtle, #e2e8f0);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.public-viewer-header-info {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
}

.public-viewer-title-meta {
  min-width: 0;
  flex: 1;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.viewer-title {
  margin: 0;
  font-size: 0.98rem;
  font-weight: 800;
  color: var(--text-primary, #0f172a);
  font-family: var(--font-heading, 'Outfit', sans-serif);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 480px;
}

.viewer-readonly-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.68rem;
  font-weight: 700;
  color: #059669;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  padding: 2px 7px;
  border-radius: 5px;
  white-space: nowrap;
}

.viewer-badges-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 3px;
  flex-wrap: wrap;
}

.size-pill {
  font-size: 0.70rem;
  color: var(--text-muted, #64748b);
}

.public-viewer-close-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border-subtle, #e2e8f0);
  background: var(--bg-surface-soft, #f8fafc);
  color: var(--text-muted, #64748b);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  font-size: 0.85rem;
  transition: all 0.2s ease;
}

.public-viewer-close-btn:hover {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fca5a5;
}

.public-viewer-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 16px;
  background: var(--bg-surface-soft, #f8fafc);
  display: flex;
  flex-direction: column;
}

.public-viewer-footer {
  flex-shrink: 0;
  padding: 12px 20px;
  background: var(--bg-surface, #ffffff);
  border-top: 1px solid var(--border-subtle, #e2e8f0);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.viewer-desc-wrap {
  flex: 1;
  min-width: 200px;
  font-size: 0.78rem;
  color: var(--text-muted, #475569);
  line-height: 1.4;
}

/* Dark Theme Adaptations */
[data-theme="dark"] .public-notes-toolbar-card,
[data-theme="dark"] .public-note-card,
[data-theme="dark"] .public-notes-empty-state,
[data-theme="dark"] .public-viewer-card,
[data-theme="dark"] .public-viewer-header,
[data-theme="dark"] .public-viewer-footer {
  background: #1e293b !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .public-search-input,
[data-theme="dark"] .public-select-ctrl {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}

[data-theme="dark"] .note-card-title,
[data-theme="dark"] .viewer-title,
[data-theme="dark"] .empty-title {
  color: #f8fafc !important;
}

[data-theme="dark"] .public-viewer-body {
  background: #0f172a !important;
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

[data-theme="dark"] .viewer-readonly-badge {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
  color: #34d399 !important;
}

[data-theme="dark"] .note-card-meta {
  background: #0f172a !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .meta-file-text {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .meta-item-size {
  color: #94a3b8 !important;
}

/* ══════════════════════════════════════════════════════
   MOBILE RESPONSIVE ADAPTATIONS (<= 768px & <= 520px)
   ══════════════════════════════════════════════════════ */
@media (max-width: 768px) {
  /* Prevent any horizontal page overflow */
  html, body {
    overflow-x: hidden !important;
    max-width: 100% !important;
  }

  /* Hero Banner Mobile */
  .about-hero-banner {
    padding: 26px 0 20px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
  }

  .about-hero-banner .container {
    padding-left: 12px !important;
    padding-right: 12px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .about-banner-content {
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .hero-eyebrows-wrap {
    flex-direction: column !important;
    align-items: center !important;
    gap: 8px !important;
    margin-bottom: 12px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .hero-eyebrow,
  .hero-trust-badge {
    font-size: 0.72rem !important;
    padding: 5px 12px !important;
    text-align: center !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    white-space: normal !important;
    word-break: break-word !important;
  }

  .about-banner-title {
    font-size: 1.75rem !important;
    line-height: 1.25 !important;
    margin: 10px 0 !important;
    letter-spacing: -0.01em !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
  }

  .hero-motto-row {
    flex-direction: column !important;
    align-items: center !important;
    gap: 4px !important;
    font-size: 0.86rem !important;
    margin-bottom: 12px !important;
    text-align: center !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    word-break: break-word !important;
  }

  .hero-motto-row .motto-divider {
    display: none !important;
  }

  .about-banner-desc {
    font-size: 0.86rem !important;
    line-height: 1.55 !important;
    margin: 0 auto 18px !important;
    padding: 0 4px !important;
    word-break: break-word !important;
    box-sizing: border-box !important;
  }

  .hero-stats-row {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
    padding: 12px 14px !important;
    border-radius: 12px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .hero-stats-row .stat-item {
    align-items: center !important;
    text-align: center !important;
  }

  .hero-stats-row .stat-number {
    font-size: 1.30rem !important;
  }

  .hero-stats-row .stat-label {
    font-size: 0.70rem !important;
  }

  .hero-stats-row .stat-divider {
    display: none !important;
  }

  /* Section Padding & Container */
  #notesSection {
    padding-top: 18px !important;
    padding-bottom: 40px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
  }

  #notesSection .container {
    padding-left: 12px !important;
    padding-right: 12px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
  }

  /* Toolbar Card Mobile */
  .public-notes-toolbar-card {
    padding: 14px 12px !important;
    margin-bottom: 16px !important;
    border-radius: 12px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
  }

  .public-notes-filter-form {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .public-search-box {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
  }

  .public-search-input {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    height: 42px !important;
    font-size: 16px !important;
    padding: 8px 12px 8px 36px !important;
    border-radius: 8px !important;
  }

  .public-search-box i {
    left: 12px !important;
    font-size: 0.82rem !important;
  }

  .public-filter-controls {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
  }

  .public-select-wrapper {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    display: block !important;
  }

  .public-select-ctrl {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    height: 42px !important;
    font-size: 16px !important;
    padding: 8px 28px 8px 12px !important;
    border-radius: 8px !important;
  }

  .public-filter-buttons {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    display: flex !important;
    gap: 8px !important;
  }

  .public-filter-buttons .btn {
    flex: 1 !important;
    height: 42px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.86rem !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    box-sizing: border-box !important;
  }

  .public-toolbar-status-row {
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 8px !important;
    margin-top: 10px !important;
    padding-top: 10px !important;
    font-size: 0.74rem !important;
    flex-wrap: wrap !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .status-count-badge {
    font-size: 0.74rem !important;
  }

  .status-secure-badge {
    font-size: 0.70rem !important;
    padding: 2px 8px !important;
  }

  /* Public Notes Cards Grid - Flex column prevents CSS grid min-content overflow */
  .public-notes-grid {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    gap: 12px !important;
  }

  .public-note-card {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    padding: 14px 14px 12px !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05) !important;
  }

  .note-card-top {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    flex-wrap: wrap !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    margin-bottom: 8px !important;
  }

  .note-type-badge {
    font-size: 0.68rem !important;
    padding: 2.5px 7px !important;
    border-radius: 5px !important;
    flex-shrink: 0 !important;
  }

  .subject-pill,
  .standard-pill {
    font-size: 0.68rem !important;
    padding: 2.5px 7px !important;
    border-radius: 5px !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .subject-pill span,
  .standard-pill span {
    display: inline-block !important;
    max-width: 150px !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    vertical-align: middle !important;
  }

  .note-card-body {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    margin-bottom: 8px !important;
  }

  .note-card-title {
    font-size: 0.94rem !important;
    font-weight: 800 !important;
    line-height: 1.35 !important;
    margin: 0 0 4px !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
    width: 100% !important;
    max-width: 100% !important;
  }

  .note-card-desc {
    font-size: 0.78rem !important;
    line-height: 1.45 !important;
    margin: 0 !important;
    display: -webkit-box !important;
    -webkit-line-clamp: 2 !important;
    -webkit-box-orient: vertical !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
    width: 100% !important;
    max-width: 100% !important;
  }

  .note-card-meta {
    padding: 6px 10px !important;
    border-radius: 7px !important;
    margin-bottom: 12px !important;
    font-size: 0.72rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 8px !important;
    box-sizing: border-box !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    overflow: hidden !important;
  }

  .meta-item-file {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    min-width: 0 !important;
    max-width: calc(100% - 65px) !important;
    flex: 1 1 auto !important;
    overflow: hidden !important;
  }

  .meta-file-text {
    font-size: 0.72rem !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    min-width: 0 !important;
    max-width: 100% !important;
    display: block !important;
  }

  .meta-item-size {
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    font-size: 0.70rem !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
  }

  .btn-view-note {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    height: 40px !important;
    min-height: 40px !important;
    font-size: 0.85rem !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
  }

  /* Empty State Mobile */
  .public-notes-empty-state {
    padding: 36px 14px !important;
    border-radius: 12px !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .empty-icon-box {
    width: 44px !important;
    height: 44px !important;
    font-size: 1.15rem !important;
  }

  .empty-title {
    font-size: 0.98rem !important;
  }

  .empty-desc {
    font-size: 0.78rem !important;
  }

  /* Document Viewer Modal Mobile */
  .public-viewer-modal-backdrop {
    padding: 8px !important;
  }

  .public-viewer-card {
    max-height: calc(100vh - 16px) !important;
    max-height: calc(100dvh - 16px) !important;
    border-radius: 14px !important;
  }

  .public-viewer-header {
    padding: 11px 13px !important;
    gap: 8px !important;
    flex-shrink: 0 !important;
  }

  .public-viewer-header-info {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    min-width: 0 !important;
    flex: 1 !important;
  }

  .public-viewer-title-meta {
    min-width: 0 !important;
    flex: 1 !important;
  }

  .title-row {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    flex-wrap: wrap !important;
  }

  .viewer-title {
    font-size: 0.88rem !important;
    font-weight: 800 !important;
    line-height: 1.25 !important;
    color: var(--text-primary, #0f172a) !important;
    max-width: none !important;
    white-space: normal !important;
    display: -webkit-box !important;
    -webkit-line-clamp: 2 !important;
    -webkit-box-orient: vertical !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
  }

  .viewer-readonly-badge {
    font-size: 0.62rem !important;
    padding: 2px 6px !important;
    border-radius: 4px !important;
    flex-shrink: 0 !important;
  }

  .viewer-badges-row {
    display: flex !important;
    align-items: center !important;
    gap: 5px !important;
    margin-top: 3px !important;
    flex-wrap: wrap !important;
  }

  .public-viewer-close-btn {
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;
    border-radius: 8px !important;
    font-size: 0.90rem !important;
  }

  .public-viewer-body {
    padding: 8px !important;
    flex: 1 !important;
    min-height: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
  }

  #publicViewerPdfWrap {
    flex: 1 !important;
    height: 100% !important;
    min-height: 0 !important;
    width: 100% !important;
    -webkit-overflow-scrolling: touch !important;
    overflow-y: auto !important;
  }

  #publicViewerPdfFrame {
    width: 100% !important;
    height: 60vh !important;
    min-height: 280px !important;
    border-radius: 6px !important;
  }

  #publicViewerImageWrap {
    flex: 1 !important;
    height: 60vh !important;
    min-height: 280px !important;
    max-height: none !important;
    padding: 8px !important;
    border-radius: 6px !important;
  }

  #publicViewerImage {
    max-width: 100% !important;
    max-height: 100% !important;
    object-fit: contain !important;
  }

  .public-viewer-footer {
    padding: 10px 13px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
    flex-shrink: 0 !important;
  }

  .viewer-desc-wrap {
    font-size: 0.74rem !important;
    line-height: 1.35 !important;
    max-height: 44px !important;
    overflow-y: auto !important;
  }

  .viewer-actions-wrap {
    width: 100% !important;
  }

  .viewer-actions-wrap .btn {
    width: 100% !important;
    height: 40px !important;
    min-height: 40px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.86rem !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
  }
}

/* Extra-Small / Phone Specific (< 520px) */
@media (max-width: 520px) {
  .about-banner-title {
    font-size: 1.55rem !important;
  }

  /* Fullscreen Reader Modal on Mobile Phones */
  .public-viewer-modal-backdrop {
    padding: 0 !important;
  }

  .public-viewer-card {
    width: 100% !important;
    height: 100% !important;
    height: 100dvh !important;
    max-height: 100dvh !important;
    border-radius: 0 !important;
    margin: 0 !important;
    border: none !important;
  }

  #publicViewerPdfFrame,
  #publicViewerImageWrap {
    height: 100% !important;
    min-height: 0 !important;
    flex: 1 !important;
  }

  .public-viewer-body {
    padding: 6px !important;
  }
}

/* Micro-Devices (< 360px) */
@media (max-width: 360px) {
  .hero-stats-row {
    grid-template-columns: 1fr !important;
    gap: 8px !important;
  }

  .about-banner-title {
    font-size: 1.40rem !important;
  }
}
</style>

<script>
function openPublicNoteViewer(note) {
  if (!note) return;
  const modal = document.getElementById('publicNoteViewerModal');
  if (!modal) return;

  const fpath = note.file_path || '';
  const ftype = (note.file_type || '').toLowerCase();
  const fsize = note.file_size ? formatPublicFileSize(note.file_size) : '';

  // Title & Badges
  document.getElementById('publicViewerNoteTitle').innerText = note.title || 'Untitled Note';
  document.getElementById('publicViewerSubjectBadge').innerHTML = '<i class="fa-solid fa-book" style="color: #0284c7; font-size: 0.70rem;"></i> ' + escapePublicHtml(note.subject || 'General');
  document.getElementById('publicViewerStandardBadge').innerHTML = '<i class="fa-solid fa-graduation-cap" style="color: #2563eb; font-size: 0.70rem;"></i> ' + escapePublicHtml(note.standard || 'All Classes');
  document.getElementById('publicViewerFileSize').innerText = (ftype.toUpperCase()) + (fsize ? ' • ' + fsize : '');

  // Description
  const descEl = document.getElementById('publicViewerDescription');
  if (descEl) {
    descEl.innerText = note.description ? 'Description: ' + note.description : 'Official KSV Academy study note reference.';
  }

  // Preview Switcher
  const pdfWrap = document.getElementById('publicViewerPdfWrap');
  const imgWrap = document.getElementById('publicViewerImageWrap');
  const fallbackWrap = document.getElementById('publicViewerFallbackWrap');
  const pdfFrame = document.getElementById('publicViewerPdfFrame');
  const imgEl = document.getElementById('publicViewerImage');
  const iconEl = document.getElementById('publicViewerFileIcon');

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
    iconEl.className = 'note-type-badge pdf';
    iconEl.innerHTML = '<i class="fa-solid fa-file-pdf"></i>';
  } else if (isImg) {
    imgWrap.style.display = 'flex';
    imgEl.src = fpath;
    iconEl.className = 'note-type-badge image';
    iconEl.innerHTML = '<i class="fa-solid fa-file-image"></i>';
  } else {
    fallbackWrap.style.display = 'block';
    iconEl.className = 'note-type-badge doc';
    iconEl.innerHTML = '<i class="fa-solid fa-file-lines"></i>';
  }

  modal.setAttribute('data-opened-at', Date.now());
  modal.classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closePublicNoteViewer() {
  const modal = document.getElementById('publicNoteViewerModal');
  if (!modal) return;
  modal.classList.remove('show');
  document.body.style.overflow = '';
  const pdfFrame = document.getElementById('publicViewerPdfFrame');
  if (pdfFrame) pdfFrame.src = '';
  const imgEl = document.getElementById('publicViewerImage');
  if (imgEl) imgEl.src = '';
}

function formatPublicFileSize(bytes) {
  if (!bytes || isNaN(bytes)) return '';
  const units = ['B', 'KB', 'MB', 'GB'];
  let b = Math.max(bytes, 0);
  let pow = Math.floor((b ? Math.log(b) : 0) / Math.log(1024));
  pow = Math.min(pow, units.length - 1);
  b /= Math.pow(1024, pow);
  return b.toFixed(1) + ' ' + units[pow];
}

function escapePublicHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// Security: Close on Escape, block Print / Save shortcuts while modal is open
document.addEventListener('keydown', function(e) {
  const modal = document.getElementById('publicNoteViewerModal');
  const isOpen = modal && modal.classList.contains('show');

  if (e.key === 'Escape' && isOpen) {
    closePublicNoteViewer();
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
  const modal = document.getElementById('publicNoteViewerModal');
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
      closePublicNoteViewer();
    }
    mouseDownOnBackdrop = false;
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
