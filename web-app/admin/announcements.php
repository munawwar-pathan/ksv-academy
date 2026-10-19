<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
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
        header('Location: ' . ADMIN_URL . '/announcements');
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

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
  <div>
    <span style="font-size: 0.9rem; color: var(--hrms-text-muted); font-weight: 600;">
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
    <h2 class="card-title"><i class="fa-solid fa-bullhorn"></i> Active Noticeboard Announcements</h2>
  </div>
  <div class="hrms-card-body">
    <div class="notice-list">
      <?php if (empty($notices)): ?>
        <p style="text-align: center; color: var(--hrms-text-muted); padding: 24px;">No notices published yet.</p>
      <?php else: ?>
        <?php foreach ($notices as $ann): ?>
          <div class="notice-item" style="padding: 18px 20px; margin-bottom: 8px;">
            <div class="notice-header">
              <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--hrms-navy);"><?php echo htmlspecialchars($ann['title']); ?></h3>
              <span class="notice-time"><i class="fa-regular fa-calendar"></i> <?php echo format_date($ann['created_at'], 'd M Y, h:i A'); ?></span>
            </div>
            <p class="notice-desc" style="font-size: 0.9rem; line-height: 1.6; color: var(--hrms-text); margin: 8px 0;"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
            <div style="margin-top: 10px; font-size: 0.76rem; color: var(--hrms-primary); font-weight: 700; display: flex; align-items: center; gap: 12px;">
              <span><i class="fa-solid fa-user-tie"></i> Posted by: <?php echo htmlspecialchars($ann['posted_by']); ?></span>
              <span class="badge-status badge-approved" style="text-transform: uppercase;">Target: <?php echo htmlspecialchars($ann['target_role']); ?></span>
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
    <form method="POST" action="<?php echo ADMIN_URL; ?>/announcements">
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
