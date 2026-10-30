<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
require_menu_permission('announcements');
$page_title = 'Announcements & Notices';
$page_heading = 'Academy Noticeboard & Circulars';
$page_subheading = 'Official Notifications, Academic Schedules & Important Alerts';
$active_menu = 'announcements';

$db = get_db_connection();

// Handle Post Notice (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target = trim($_POST['target_role'] ?? 'all');
    if (!empty($title) && !empty($content)) {
        $stmt = $db->prepare("INSERT INTO announcements (title, content, target_role, posted_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $target, $current_user['full_name']]);
        set_flash('success', 'Notice published successfully on the academy board!');
        header('Location: ' . STAFF_URL . '/announcements');
        exit;
    }
}

// Fetch Announcements
if ($current_user['role'] === 'super_admin') {
    $notices = $db->query("SELECT * FROM announcements ORDER BY id DESC")->fetchAll();
} elseif ($current_user['role'] === 'staff') {
    $notices = $db->query("SELECT * FROM announcements WHERE target_role IN ('all', 'staff') ORDER BY id DESC")->fetchAll();
} else {
    $notices = $db->query("SELECT * FROM announcements WHERE target_role IN ('all', 'parent') ORDER BY id DESC")->fetchAll();
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Staff Announcements & Noticeboard Styles ── */
.announcements-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}
.announcements-count-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 0.84rem;
  color: #64748b;
  font-weight: 600;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.announcements-count-badge strong {
  color: #0284c7;
  font-weight: 800;
}
.announcements-count-badge .count-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #0284c7;
}

.notice-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.notice-item {
  padding: 18px 20px;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-left: 4px solid #0284c7;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
  transition: all 0.2s ease;
}
.notice-item:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  border-left-color: #0284c7;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}
.notice-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 8px;
}
.notice-header h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.4;
  font-family: 'Outfit', sans-serif;
}
.notice-time {
  font-size: 0.74rem;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  padding: 3px 8px;
  border-radius: 6px;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  flex-shrink: 0;
}
.notice-desc {
  font-size: 0.88rem;
  line-height: 1.6;
  color: #334155;
  margin: 10px 0;
  word-break: break-word;
}
.notice-footer {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px dashed #e2e8f0;
  font-size: 0.76rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
}
.notice-poster {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #475569;
  font-weight: 600;
}
.notice-poster i {
  color: #0284c7;
}
.notice-target-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.70rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}
.notice-target-badge.role-all {
  background: #f0fdf4;
  color: #166534;
  border-color: #bbf7d0;
}
.notice-target-badge.role-staff {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: #bfdbfe;
}
.notice-target-badge.role-parent {
  background: #fdf4ff;
  color: #a21caf;
  border-color: #f5d0fe;
}

/* ══════════════════════════════════════════════════════
   MOBILE & RESPONSIVE REFINEMENTS
   ══════════════════════════════════════════════════════ */

/* Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  .announcements-topbar {
    margin-bottom: 14px;
    gap: 8px;
  }
  .announcements-topbar > div:first-child {
    width: 100%;
  }
  .announcements-count-badge {
    width: 100%;
    justify-content: center;
    box-sizing: border-box;
    padding: 8px 12px;
    font-size: 0.80rem;
  }
  .announcements-topbar .btn-hrms {
    width: 100%;
    justify-content: center;
    box-sizing: border-box;
  }

  .hrms-card {
    border-radius: 10px !important;
  }
  .hrms-card-header {
    padding: 12px 14px !important;
  }
  .hrms-card-header h2.card-title {
    font-size: 0.92rem !important;
  }
  .hrms-card-body {
    padding: 12px 12px !important;
  }

  .notice-list {
    gap: 10px;
  }
  .notice-item {
    padding: 14px 14px !important;
    border-radius: 10px !important;
  }
  .notice-header {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 6px !important;
    margin-bottom: 6px !important;
  }
  .notice-header h3 {
    font-size: 0.94rem !important;
    line-height: 1.35 !important;
  }
  .notice-time {
    font-size: 0.70rem !important;
    padding: 2px 7px !important;
  }
  .notice-desc {
    font-size: 0.82rem !important;
    line-height: 1.5 !important;
    margin: 8px 0 !important;
  }
  .notice-footer {
    font-size: 0.72rem !important;
    gap: 6px !important;
    margin-top: 10px !important;
    padding-top: 8px !important;
  }

  /* Modal Responsiveness */
  .hrms-modal-card {
    width: calc(100% - 24px) !important;
    max-width: 100% !important;
    margin: 12px auto !important;
    border-radius: 12px !important;
  }
  .hrms-modal-header {
    padding: 12px 16px !important;
  }
  .hrms-modal-body {
    padding: 16px 14px !important;
  }
}

/* Micro Phones (<= 380px) */
@media (max-width: 380px) {
  .notice-footer {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 6px !important;
  }
  .notice-target-badge {
    align-self: flex-start !important;
  }
}

/* ══════════════════════════════════════════════════════
   DARK MODE REFINEMENTS
   ══════════════════════════════════════════════════════ */
[data-theme="dark"] .announcements-count-badge {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .announcements-count-badge strong {
  color: #38bdf8 !important;
}

/* 1. Main Noticeboard Card Shell */
[data-theme="dark"] .hrms-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .hrms-card-header {
  background: #1e293b !important;
  border-bottom: 1px solid #334155 !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .hrms-card-header h2,
[data-theme="dark"] .hrms-card-header .card-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-card-body p[style*="text-align: center"] {
  color: #94a3b8 !important;
}

/* 2. Notice Items */
[data-theme="dark"] .notice-item {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  border-left: 4px solid #0284c7 !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .notice-item:hover {
  background: #1e293b !important;
  border-color: #475569 !important;
  border-left-color: #38bdf8 !important;
}
[data-theme="dark"] .notice-header h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] .notice-time {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .notice-time i {
  color: #38bdf8 !important;
}
[data-theme="dark"] .notice-desc {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .notice-footer {
  border-top: 1px dashed #334155 !important;
  border-top-color: #334155 !important;
}
[data-theme="dark"] .notice-poster {
  color: #94a3b8 !important;
}
[data-theme="dark"] .notice-poster i {
  color: #38bdf8 !important;
}
[data-theme="dark"] .notice-target-badge.role-all {
  background: rgba(16, 185, 129, 0.15) !important;
  border: 1px solid rgba(16, 185, 129, 0.35) !important;
  color: #34d399 !important;
}
[data-theme="dark"] .notice-target-badge.role-staff {
  background: rgba(2, 132, 199, 0.15) !important;
  border: 1px solid rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .notice-target-badge.role-parent {
  background: rgba(168, 85, 247, 0.15) !important;
  border: 1px solid rgba(168, 85, 247, 0.35) !important;
  color: #c084fc !important;
}

/* 3. Modal: Broadcast Notice */
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
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] #announcementModal .hrms-modal-header h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] #announcementModal .modal-close-btn {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] #announcementModal .modal-close-btn:hover {
  background: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #announcementModal .hrms-modal-body {
  background: #1e293b !important;
}
[data-theme="dark"] #announcementModal .form-label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] #announcementModal input.hrms-input,
[data-theme="dark"] #announcementModal select.hrms-input,
[data-theme="dark"] #announcementModal textarea.hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #announcementModal select.hrms-input option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #announcementModal textarea.hrms-input::placeholder,
[data-theme="dark"] #announcementModal input.hrms-input::placeholder {
  color: #64748b !important;
}
</style>

<div class="announcements-topbar">
  <div>
    <span class="announcements-count-badge">
      <span class="count-dot"></span>
      Total Circulars: <strong><?php echo count($notices); ?></strong>
    </span>
  </div>
  <?php if ($current_user['role'] === 'super_admin'): ?>
    <button type="button" class="btn-hrms btn-hrms-primary" onclick="openModal('announcementModal')">
      <i class="fa-solid fa-bullhorn"></i> Broadcast New Notice
    </button>
  <?php endif; ?>
</div>

<div class="hrms-card">
  <div class="hrms-card-header">
    <h2 class="card-title"><i class="fa-solid fa-bullhorn" style="color: #0284c7;"></i> Active Noticeboard Announcements</h2>
  </div>
  <div class="hrms-card-body">
    <div class="notice-list">
      <?php if (empty($notices)): ?>
        <p style="text-align: center; color: var(--hrms-text-muted); padding: 24px;">No notices published yet.</p>
      <?php else: ?>
        <?php foreach ($notices as $ann): ?>
          <?php 
            $role_class = 'role-all';
            if ($ann['target_role'] === 'staff') $role_class = 'role-staff';
            elseif ($ann['target_role'] === 'parent') $role_class = 'role-parent';
          ?>
          <div class="notice-item">
            <div class="notice-header">
              <h3><?php echo htmlspecialchars($ann['title']); ?></h3>
              <span class="notice-time"><i class="fa-regular fa-calendar"></i> <?php echo format_date($ann['created_at'], 'd M Y, h:i A'); ?></span>
            </div>
            <p class="notice-desc"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
            <div class="notice-footer">
              <span class="notice-poster"><i class="fa-solid fa-user-tie"></i> Posted by: <?php echo htmlspecialchars($ann['posted_by']); ?></span>
              <span class="notice-target-badge <?php echo $role_class; ?>">Target: <?php echo htmlspecialchars($ann['target_role']); ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal: Post Announcement -->
<?php if ($current_user['role'] === 'super_admin'): ?>
<div class="hrms-modal" id="announcementModal">
  <div class="hrms-modal-card">
    <div class="hrms-modal-header">
      <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--hrms-navy);"><i class="fa-solid fa-bullhorn" style="color: var(--hrms-primary);"></i> Broadcast Circular</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('announcementModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo STAFF_URL; ?>/announcements">
      <div class="hrms-modal-body">
        <div class="form-group">
          <label class="form-label">Notice Headline</label>
          <input type="text" name="title" class="hrms-input" placeholder="e.g. Sunday Mock Test Timing" required>
        </div>
        <div class="form-group">
          <label class="form-label">Target Audience</label>
          <select name="target_role" class="hrms-input">
            <option value="all">Everyone (All Faculty &amp; Parents)</option>
            <option value="staff">Faculty &amp; Staff Only</option>
            <option value="parent">Parents &amp; Students Only</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Notice Message Details</label>
          <textarea name="content" class="hrms-input" rows="4" placeholder="Enter notice content here..." required></textarea>
        </div>
        <button type="submit" class="login-submit-btn">
          <i class="fa-solid fa-paper-plane"></i> Publish Notice
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
