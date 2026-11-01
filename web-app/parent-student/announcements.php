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

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
if (in_array($current_user['role'], ['student', 'parent'], true)) {
    require_menu_permission('announcements');
}

// Session-based request rate limiting (Max 120 page views per minute)
$now = time();
if (!isset($_SESSION['parent_announcements_req_attempts']) || !is_array($_SESSION['parent_announcements_req_attempts'])) {
    $_SESSION['parent_announcements_req_attempts'] = [];
}
$_SESSION['parent_announcements_req_attempts'] = array_filter($_SESSION['parent_announcements_req_attempts'], function($timestamp) use ($now) {
    return ($now - (int)$timestamp) < 60;
});
if (count($_SESSION['parent_announcements_req_attempts']) >= 120) {
    http_response_code(429);
    exit('Rate limit exceeded. Please wait a moment before trying again.');
}
$_SESSION['parent_announcements_req_attempts'][] = $now;

$page_title = 'Academy Noticeboard & Circulars | KSV Academy HRMS';
$page_heading = 'Academy Noticeboard & Circulars';
$page_subheading = 'Official Academic Notifications, Examination Schedules & Important Academy Alerts';
$active_menu = 'announcements';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session ? intval($active_session['id']) : 1;

// Handle Actions (Broadcast Notice - Super Admin Only)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // 1. Authorization check
    if ($current_user['role'] !== 'super_admin') {
        http_response_code(403);
        set_flash('danger', 'Unauthorized action. Only administrators can publish circulars.');
        header('Location: ' . PARENT_URL . '/announcements');
        exit;
    }

    // 2. CSRF Token Verification
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash('danger', 'Security token invalid or expired. Please refresh the page and try again.');
        header('Location: ' . PARENT_URL . '/announcements');
        exit;
    }

    // 3. Rate limiting for POST actions (Max 15 notices per 5 minutes)
    if (!isset($_SESSION['announcement_post_attempts']) || !is_array($_SESSION['announcement_post_attempts'])) {
        $_SESSION['announcement_post_attempts'] = [];
    }
    $_SESSION['announcement_post_attempts'] = array_filter($_SESSION['announcement_post_attempts'], function($t) use ($now) {
        return ($now - (int)$t) < 300;
    });
    if (count($_SESSION['announcement_post_attempts']) >= 15) {
        set_flash('danger', 'Notice publishing rate limit reached. Please wait a few minutes before trying again.');
        header('Location: ' . PARENT_URL . '/announcements');
        exit;
    }

    $action = trim((string)($_POST['action'] ?? 'create'));

    if ($action === 'delete') {
        $notice_id = intval($_POST['id'] ?? 0);
        if ($notice_id > 0) {
            $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$notice_id]);
            set_flash('success', 'Notice deleted successfully from the academy board.');
        } else {
            set_flash('danger', 'Invalid notice specified.');
        }
        header('Location: ' . PARENT_URL . '/announcements');
        exit;
    }

    // Create Notice
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255);
    $content = mb_substr(trim((string)($_POST['content'] ?? '')), 0, 10000);
    $raw_target = trim((string)($_POST['target_role'] ?? 'all'));
    $allowed_targets = ['all', 'parent', 'student', 'staff'];
    $target = in_array($raw_target, $allowed_targets, true) ? $raw_target : 'all';

    if (mb_strlen($title) === 0 || mb_strlen($content) === 0) {
        set_flash('danger', 'Please provide both notice headline and details.');
        header('Location: ' . PARENT_URL . '/announcements');
        exit;
    }

    $posted_by = !empty($current_user['full_name']) 
        ? mb_substr(trim((string)$current_user['full_name']), 0, 100) 
        : (!empty($current_user['username']) ? mb_substr(trim((string)$current_user['username']), 0, 100) : 'Directorate');

    $stmt = $db->prepare("INSERT INTO announcements (title, content, target_role, posted_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $content, $target, $posted_by]);
    $_SESSION['announcement_post_attempts'][] = $now;

    set_flash('success', 'Notice published successfully on the academy board!');
    header('Location: ' . PARENT_URL . '/announcements');
    exit;
}

// Fetch Announcements based on role using prepared statements
if ($current_user['role'] === 'super_admin') {
    $stmt = $db->prepare("SELECT * FROM announcements ORDER BY id DESC");
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($current_user['role'] === 'staff') {
    $stmt = $db->prepare("SELECT * FROM announcements WHERE target_role IN ('all', 'staff') ORDER BY id DESC");
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare("SELECT * FROM announcements WHERE target_role IN ('all', 'parent', 'student') ORDER BY id DESC");
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$total_notices = count($notices);

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Simple, Sober & Clean Noticeboard Styles ── */
.periods-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(15,23,42,0.06), 0 4px 12px -2px rgba(15,23,42,0.04);
  margin-bottom: 24px;
  overflow: hidden;
  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
}

.periods-card-header {
  padding: 14px 18px;
  border-bottom: 1px solid #f1f5f9;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.notice-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px 18px;
}

.notice-item {
  padding: 14px 16px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-left: 3px solid #0284c7;
  transition: all 0.15s ease;
}

.notice-item:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  border-left-color: #0284c7;
}

.notice-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
}

.notice-title {
  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
  font-weight: 700;
  font-size: 0.95rem;
  color: #0f172a;
}

.notice-time {
  font-size: 0.72rem;
  font-weight: 500;
  color: #64748b;
  white-space: nowrap;
}

.notice-desc {
  font-size: 0.84rem;
  color: #475569;
  line-height: 1.55;
  margin: 0 0 10px;
  white-space: pre-line;
}

.notice-footer-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.73rem;
  color: #64748b;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.notice-badge-target {
  font-size: 0.70rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 5px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  text-transform: capitalize;
}

.notice-badge-target.target-all {
  background: #ecfdf5;
  color: #059669;
  border-color: #a7f3d0;
}

.notice-badge-target.target-parent,
.notice-badge-target.target-student {
  background: #f0f9ff;
  color: #0284c7;
  border-color: #bae6fd;
}

.notice-badge-target.target-staff {
  background: #faf5ff;
  color: #7c3aed;
  border-color: #e9d5ff;
}

.periods-card-footer {
  padding: 12px 18px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.76rem;
  color: #64748b;
  flex-wrap: wrap;
  gap: 10px;
}

.roaster-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.76rem;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.15s ease;
  font-family: inherit;
  text-decoration: none;
}

.roaster-btn.primary {
  background: #0284c7;
  color: #ffffff;
}

.roaster-btn.primary:hover {
  background: #0369a1;
}

.empty-notice-state {
  text-align: center;
  padding: 40px 16px;
  color: #64748b;
}

.empty-notice-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 10px;
  font-size: 1.2rem;
  color: #94a3b8;
}

/* ══════════════════════════════════════════════════════
   RESPONSIVE DESIGN (TABLET & MOBILE)
   ══════════════════════════════════════════════════════ */
@media (max-width: 768px) {
  .periods-card {
    border-radius: 10px !important;
    margin-bottom: 16px !important;
  }
  .periods-card-header {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 10px !important;
  }
  .periods-card-header > div:first-child {
    width: 100% !important;
  }
  .periods-card-header .card-title {
    font-size: 0.90rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
  .periods-card-header .card-title span {
    font-size: 0.66rem !important;
  }
  .periods-card-header p {
    font-size: 0.72rem !important;
    line-height: 1.4 !important;
  }
  .periods-card-header .roaster-btn {
    width: 100% !important;
    justify-content: center !important;
    padding: 8px 14px !important;
    font-size: 0.80rem !important;
    box-sizing: border-box !important;
  }

  .notice-list {
    padding: 12px !important;
    gap: 10px !important;
  }
  .notice-item {
    padding: 12px 14px !important;
    border-radius: 8px !important;
  }
  .notice-header {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 4px !important;
    margin-bottom: 6px !important;
  }
  .notice-title {
    font-size: 0.88rem !important;
    line-height: 1.35 !important;
    word-break: break-word !important;
  }
  .notice-time {
    font-size: 0.68rem !important;
  }
  .notice-desc {
    font-size: 0.80rem !important;
    line-height: 1.5 !important;
    margin: 6px 0 10px !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
  }
  .notice-footer-meta {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 6px !important;
    font-size: 0.70rem !important;
    padding-top: 8px !important;
  }
  .notice-badge-target {
    font-size: 0.66rem !important;
    padding: 2px 7px !important;
    align-self: flex-start !important;
  }

  .periods-card-footer {
    padding: 10px 14px !important;
    font-size: 0.72rem !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 4px !important;
  }

  .empty-notice-state {
    padding: 30px 14px !important;
  }

  /* Modal Responsive */
  #announcementModal .hrms-modal-card {
    width: calc(100% - 24px) !important;
    max-width: 100% !important;
    margin: 16px auto !important;
    border-radius: 10px !important;
  }
  #announcementModal .hrms-modal-header {
    padding: 12px 14px !important;
  }
  #announcementModal .hrms-modal-body {
    padding: 14px !important;
  }
}

@media (max-width: 380px) {
  .periods-card-header {
    padding: 10px 12px !important;
  }
  .periods-card-header .card-title {
    font-size: 0.84rem !important;
  }
  .notice-list {
    padding: 10px 8px !important;
  }
  .notice-item {
    padding: 10px !important;
  }
  .notice-title {
    font-size: 0.84rem !important;
  }
  .notice-desc {
    font-size: 0.76rem !important;
  }
  .periods-card-footer {
    padding: 8px 10px !important;
    font-size: 0.70rem !important;
  }
}

/* ══════════════════════════════════════════════════════
   DARK MODE COMPATIBILITY & REFINEMENTS
   ══════════════════════════════════════════════════════ */
/* 1. Main Noticeboard Card */
[data-theme="dark"] .periods-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .periods-card-header {
  background: #1e293b !important;
  border-bottom: 1px solid #334155 !important;
}

[data-theme="dark"] .periods-card-header .card-title,
[data-theme="dark"] .periods-card-header .card-title[style*="color: #0f172a"],
[data-theme="dark"] .periods-card-header .card-title[style*="color:#0f172a"] {
  color: #f8fafc !important;
}

[data-theme="dark"] .periods-card-header .card-title i {
  color: #38bdf8 !important;
}

[data-theme="dark"] .periods-card-header .card-title span,
[data-theme="dark"] .periods-card-header .card-title span[style*="background: #f8fafc"],
[data-theme="dark"] .periods-card-header .card-title span[style*="background:#f8fafc"] {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border: 1px solid #334155 !important;
}

[data-theme="dark"] .periods-card-header p,
[data-theme="dark"] .periods-card-header p[style*="color: #64748b"],
[data-theme="dark"] .periods-card-header p[style*="color:#64748b"] {
  color: #94a3b8 !important;
}

/* 2. Notice Items */
[data-theme="dark"] .notice-item {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  border-left: 3px solid #0284c7 !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .notice-item:hover {
  background: #162032 !important;
  border-color: #475569 !important;
  border-left-color: #38bdf8 !important;
}

[data-theme="dark"] .notice-title {
  color: #f8fafc !important;
}

[data-theme="dark"] .notice-time {
  color: #94a3b8 !important;
}

[data-theme="dark"] .notice-time i {
  color: #38bdf8 !important;
}

[data-theme="dark"] .notice-desc {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .notice-footer-meta {
  border-top: 1px solid #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .notice-footer-meta strong {
  color: #f8fafc !important;
}

[data-theme="dark"] .notice-footer-meta span i[style*="color: #0284c7"],
[data-theme="dark"] .notice-footer-meta span i[style*="color:#0284c7"],
[data-theme="dark"] .notice-footer-meta span i {
  color: #38bdf8 !important;
}

/* Audience Target Badges */
[data-theme="dark"] .notice-badge-target {
  background: rgba(2, 132, 199, 0.15) !important;
  border: 1px solid rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .notice-badge-target.target-all {
  background: rgba(16, 185, 129, 0.15) !important;
  border: 1px solid rgba(16, 185, 129, 0.35) !important;
  color: #34d399 !important;
}

[data-theme="dark"] .notice-badge-target.target-parent,
[data-theme="dark"] .notice-badge-target.target-student {
  background: rgba(2, 132, 199, 0.15) !important;
  border: 1px solid rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .notice-badge-target.target-staff {
  background: rgba(168, 85, 247, 0.15) !important;
  border: 1px solid rgba(168, 85, 247, 0.35) !important;
  color: #c084fc !important;
}

/* 3. Card Footer */
[data-theme="dark"] .periods-card-footer {
  background: #0f172a !important;
  border-top: 1px solid #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-footer strong {
  color: #38bdf8 !important;
}

/* 4. Empty Notice State */
[data-theme="dark"] .empty-notice-state {
  color: #94a3b8 !important;
}

[data-theme="dark"] .empty-notice-icon {
  background: rgba(2, 132, 199, 0.15) !important;
  border: 1px solid rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .empty-notice-state h4,
[data-theme="dark"] .empty-notice-state h4[style*="color: #0f172a"],
[data-theme="dark"] .empty-notice-state h4[style*="color:#0f172a"] {
  color: #f8fafc !important;
}

[data-theme="dark"] .empty-notice-state p,
[data-theme="dark"] .empty-notice-state p[style*="color: #64748b"],
[data-theme="dark"] .empty-notice-state p[style*="color:#64748b"] {
  color: #94a3b8 !important;
}

/* 5. Buttons */
[data-theme="dark"] .roaster-btn.primary {
  background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
  color: #ffffff !important;
  box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35) !important;
}

[data-theme="dark"] .roaster-btn.primary:hover {
  background: linear-gradient(135deg, #0369a1 0%, #075985 100%) !important;
  box-shadow: 0 6px 18px rgba(2, 132, 199, 0.45) !important;
}

[data-theme="dark"] .roaster-btn.secondary {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .roaster-btn.secondary:hover {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #ffffff !important;
}

/* 6. Broadcast Modal */
[data-theme="dark"] .hrms-modal,
[data-theme="dark"] #announcementModal {
  background: rgba(0, 0, 0, 0.75) !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7) !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-header {
  background: #1e293b !important;
  border-bottom: 1px solid #334155 !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-header h3,
[data-theme="dark"] #announcementModal .hrms-modal-header h3[style*="color: #0f172a"],
[data-theme="dark"] #announcementModal .hrms-modal-header h3[style*="color:#0f172a"] {
  color: #f8fafc !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-header h3 i {
  color: #38bdf8 !important;
}

[data-theme="dark"] #announcementModal .modal-close-btn {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  border-radius: 6px !important;
  color: #94a3b8 !important;
  padding: 4px 8px !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

[data-theme="dark"] #announcementModal .modal-close-btn:hover {
  background: #334155 !important;
  color: #f8fafc !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-body {
  background: #1e293b !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-body label.form-label,
[data-theme="dark"] #announcementModal label.form-label[style*="color: #334155"],
[data-theme="dark"] #announcementModal label.form-label[style*="color:#334155"] {
  color: #cbd5e1 !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-body .hrms-input {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  color: #f8fafc !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-body .hrms-input:focus {
  border-color: #0284c7 !important;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.25) !important;
  outline: none !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-body select.hrms-input option {
  background: #0f172a !important;
  color: #f8fafc !important;
}

[data-theme="dark"] #announcementModal .hrms-modal-body .hrms-input::placeholder {
  color: #64748b !important;
}
</style>

<!-- Main Sober Noticeboard Card -->
<div class="periods-card">
  
  <div class="periods-card-header">
    <div style="flex: 1; min-width: 0;">
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-bullhorn" style="color: #0284c7;"></i>
        Academy Circulars &amp; Alerts
        <span style="font-size: 0.70rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 1px 7px; border-radius: 5px;">
          Official Notices
        </span>
      </h3>
      <p style="margin: 2px 0 0; font-size: 0.74rem; color: #64748b;">
        Important notices, exam schedules &amp; management circulars.
      </p>
    </div>

    <?php if ($current_user['role'] === 'super_admin'): ?>
      <button type="button" class="roaster-btn primary" onclick="openModal('announcementModal')">
        <i class="fa-solid fa-plus"></i> Broadcast Notice
      </button>
    <?php endif; ?>
  </div>

  <div>
    <?php if (empty($notices)): ?>
      <div class="empty-notice-state">
        <div class="empty-notice-icon">
          <i class="fa-solid fa-bell-slash"></i>
        </div>
        <h4 style="font-size: 0.92rem; font-weight: 700; color: #0f172a; margin: 0 0 3px;">No Active Circulars</h4>
        <p style="font-size: 0.78rem; color: #64748b; margin: 0;">Important announcements from academy administration will be posted here.</p>
      </div>
    <?php else: ?>
      <div class="notice-list">
        <?php foreach ($notices as $ann): ?>
          <div class="notice-item">
            <div class="notice-header">
              <span class="notice-title"><?php echo htmlspecialchars($ann['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="notice-time">
                <i class="fa-regular fa-calendar" style="color: #94a3b8; margin-right: 4px;"></i>
                <?php echo !empty($ann['created_at']) ? htmlspecialchars(format_date($ann['created_at'], 'd M Y, h:i A'), ENT_QUOTES, 'UTF-8') : '—'; ?>
              </span>
            </div>
            <p class="notice-desc"><?php echo nl2br(htmlspecialchars($ann['content'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
            <div class="notice-footer-meta">
              <span>
                <i class="fa-regular fa-user" style="color: #0284c7; margin-right: 4px;"></i>
                Posted by: <strong><?php echo htmlspecialchars(!empty($ann['posted_by']) ? $ann['posted_by'] : 'Directorate', ENT_QUOTES, 'UTF-8'); ?></strong>
              </span>
              <span class="notice-badge-target target-<?php echo htmlspecialchars($ann['target_role'] ?? 'all', ENT_QUOTES, 'UTF-8'); ?>">
                Audience: <?php echo htmlspecialchars(($ann['target_role'] ?? '') === 'parent' ? 'Parents & Students' : ((($ann['target_role'] ?? '') === 'staff') ? 'Faculty & Staff' : 'All'), ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="periods-card-footer">
    <span>Official institute notices for students &amp; guardians</span>
    <span><strong><?php echo intval($total_notices); ?></strong> Recent Alerts</span>
  </div>

</div>

<!-- Modal: Post Notice (Super Admin) -->
<?php if ($current_user['role'] === 'super_admin'): ?>
<div class="hrms-modal" id="announcementModal">
  <div class="hrms-modal-card" style="max-width: 520px; width: 100%; background: #ffffff; border-radius: 10px; overflow: hidden;">
    <div class="hrms-modal-header" style="background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
      <h3 style="font-size: 0.98rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-bullhorn" style="color: #0284c7;"></i> Broadcast Notice
      </h3>
      <button type="button" class="modal-close-btn" style="background: none; border: none; cursor: pointer; color: #64748b; font-size: 1rem;" onclick="closeModal('announcementModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo htmlspecialchars(PARENT_URL . '/announcements', ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="action" value="create">
      <div class="hrms-modal-body" style="padding: 18px;">
        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Notice Headline</label>
          <input type="text" name="title" maxlength="255" class="hrms-input" style="width: 100%; padding: 8px 12px; font-size: 0.82rem; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="e.g. Mid-Term Examination Timing" required>
        </div>
        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Target Audience</label>
          <select name="target_role" class="hrms-input" style="width: 100%; padding: 8px 12px; font-size: 0.82rem; border: 1px solid #cbd5e1; border-radius: 6px;">
            <option value="parent">Parents &amp; Students Only</option>
            <option value="all">Everyone (All Faculty &amp; Parents)</option>
            <option value="staff">Faculty &amp; Staff Only</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 0.78rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Notice Details</label>
          <textarea name="content" maxlength="10000" class="hrms-input" rows="4" style="width: 100%; padding: 8px 12px; font-size: 0.82rem; border: 1px solid #cbd5e1; border-radius: 6px; resize: vertical;" placeholder="Enter notice message details..." required></textarea>
        </div>
        <button type="submit" class="roaster-btn primary" style="width: 100%; padding: 9px; font-size: 0.84rem; justify-content: center;">
          <i class="fa-solid fa-paper-plane"></i> Publish Notice
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
