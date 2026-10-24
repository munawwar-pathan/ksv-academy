<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
$page_title = 'Announcements & Notices';
$page_heading = 'Announcements';
$page_subheading = 'Official notifications, circulars and campus broadcasts';
$active_menu = 'announcements';

$db = get_db_connection();

// Handle Delete Notice (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $current_user['role'] === 'super_admin') {
    $notice_id = (int)($_POST['id'] ?? 0);
    if ($notice_id > 0) {
        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$notice_id]);
        set_flash('success', 'Notice deleted successfully from the academy board.');
        header('Location: ' . ADMIN_URL . '/announcements');
        exit;
    }
}

// Handle Post Notice (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'create') && $current_user['role'] === 'super_admin') {
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
    $notices = $db->query("SELECT * FROM announcements ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($current_user['role'] === 'staff') {
    $notices = $db->query("SELECT * FROM announcements WHERE target_role IN ('all', 'staff') ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $notices = $db->query("SELECT * FROM announcements WHERE target_role IN ('all', 'parent') ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// Compute statistics
$total_count = count($notices);
$count_all = 0;
$count_staff = 0;
$count_parent = 0;

foreach ($notices as $n) {
    if ($n['target_role'] === 'all') $count_all++;
    elseif ($n['target_role'] === 'staff') $count_staff++;
    elseif ($n['target_role'] === 'parent') $count_parent++;
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<!-- ── Top Metric KPI Cards ── -->
<div class="stat-grid" style="margin-bottom: 20px;">
  <!-- 1. Total Notices -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Circulars</span>
          <span class="stat-number"><?php echo $total_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-bullhorn"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-newspaper" style="color: #0284c7; margin-right: 4px;"></i> Active Board</span>
      <span style="color: #0284c7; font-weight: 700;"><?php echo $total_count; ?> Notices</span>
    </div>
  </div>

  <!-- 2. For Everyone -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">All Audiences</span>
          <span class="stat-number"><?php echo $count_all; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-globe"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #10b981;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-users-viewfinder" style="color: #10b981; margin-right: 4px;"></i> Staff &amp; Students</span>
      <span style="color: #10b981; font-weight: 700;"><?php echo $count_all; ?> Circulars</span>
    </div>
  </div>

  <!-- 3. Faculty & Staff -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Faculty &amp; Staff</span>
          <span class="stat-number"><?php echo $count_staff; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-chalkboard-user"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #7c3aed;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-user-shield" style="color: #7c3aed; margin-right: 4px;"></i> Internal Faculty</span>
      <span style="color: #7c3aed; font-weight: 700;"><?php echo $count_staff; ?> Circulars</span>
    </div>
  </div>

  <!-- 4. Parents & Students -->
  <div class="stat-card orange">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Parents &amp; Students</span>
          <span class="stat-number"><?php echo $count_parent; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-people-roof"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #f59e0b;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><i class="fa-solid fa-graduation-cap" style="color: #f59e0b; margin-right: 4px;"></i> Guardian Desk</span>
      <span style="color: #f59e0b; font-weight: 700;"><?php echo $count_parent; ?> Circulars</span>
    </div>
  </div>
</div>

<!-- ── Responsive Toolbar ── -->
<div class="ann-toolbar-card">
  <!-- Left: Filter Tabs -->
  <div class="ann-tabs-nav">
    <button type="button" class="ann-tab-pill active" data-filter="all" onclick="filterAnnouncements('all', this)">
      <i class="fa-solid fa-list-check"></i>
      <span>All Notices</span>
      <span class="ann-tab-count"><?php echo $total_count; ?></span>
    </button>
    <button type="button" class="ann-tab-pill" data-filter="all-target" onclick="filterAnnouncements('all-target', this)">
      <i class="fa-solid fa-globe"></i>
      <span>Everyone</span>
      <span class="ann-tab-count"><?php echo $count_all; ?></span>
    </button>
    <button type="button" class="ann-tab-pill" data-filter="staff" onclick="filterAnnouncements('staff', this)">
      <i class="fa-solid fa-chalkboard-user"></i>
      <span>Staff Only</span>
      <span class="ann-tab-count"><?php echo $count_staff; ?></span>
    </button>
    <button type="button" class="ann-tab-pill" data-filter="parent" onclick="filterAnnouncements('parent', this)">
      <i class="fa-solid fa-people-roof"></i>
      <span>Parents</span>
      <span class="ann-tab-count"><?php echo $count_parent; ?></span>
    </button>
  </div>

  <!-- Right: Search & Action Button -->
  <div class="ann-actions-group">
    <div class="ann-search-wrapper">
      <i class="fa-solid fa-magnifying-glass ann-search-icon"></i>
      <input type="text" id="annSearchInput" class="ann-search-input" placeholder="Search notices, title, content..." oninput="handleAnnSearch()">
      <button type="button" id="annSearchClear" class="ann-search-clear" onclick="clearAnnSearch()" style="display: none;" title="Clear search">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <?php if ($current_user['role'] === 'super_admin'): ?>
      <button type="button" class="btn-hrms btn-hrms-primary ann-broadcast-btn" onclick="openModal('announcementModal')">
        <i class="fa-solid fa-bullhorn"></i>
        <span>Broadcast Notice</span>
      </button>
    <?php endif; ?>
  </div>
</div>

<!-- ── Main Notices List Card ── -->
<div class="hrms-card ann-main-card">
  <div class="hrms-card-header ann-main-card-header">
    <div class="ann-header-title-wrap">
      <h2 class="card-title"><i class="fa-solid fa-bullhorn"></i> Active Noticeboard Announcements</h2>
      <span class="ann-records-badge" id="annCountBadge"><?php echo $total_count; ?> Notices</span>
    </div>
  </div>
  <div class="hrms-card-body ann-main-card-body">
    <div class="notice-list ann-list" id="announcementsContainer">
      <?php if (empty($notices)): ?>
        <div class="ann-empty-state">
          <div class="ann-empty-icon"><i class="fa-solid fa-bullhorn"></i></div>
          <h3 class="ann-empty-title">No notices published yet</h3>
          <p class="ann-empty-desc">Official academic notifications, schedules, and alerts will appear here once published.</p>
          <?php if ($current_user['role'] === 'super_admin'): ?>
            <button type="button" class="btn-hrms btn-hrms-primary" style="margin-top: 14px;" onclick="openModal('announcementModal')">
              <i class="fa-solid fa-plus"></i> Broadcast First Notice
            </button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php foreach ($notices as $ann): ?>
          <div class="notice-item ann-item" data-target="<?php echo htmlspecialchars($ann['target_role']); ?>" data-id="<?php echo $ann['id']; ?>">
            <div class="notice-header ann-item-header">
              <div class="ann-item-title-box">
                <span class="ann-target-badge-icon target-<?php echo htmlspecialchars($ann['target_role']); ?>" title="Target: <?php echo ucfirst(htmlspecialchars($ann['target_role'])); ?>">
                  <?php if ($ann['target_role'] === 'staff'): ?>
                    <i class="fa-solid fa-chalkboard-user"></i>
                  <?php elseif ($ann['target_role'] === 'parent'): ?>
                    <i class="fa-solid fa-people-roof"></i>
                  <?php else: ?>
                    <i class="fa-solid fa-globe"></i>
                  <?php endif; ?>
                </span>
                <h3 class="ann-item-title"><?php echo htmlspecialchars($ann['title']); ?></h3>
              </div>
              <div class="ann-item-meta-right">
                <span class="notice-time ann-item-time">
                  <i class="fa-regular fa-calendar-days"></i> <?php echo format_date($ann['created_at'], 'd M Y, h:i A'); ?>
                </span>
                <?php if ($current_user['role'] === 'super_admin'): ?>
                  <button type="button" class="ann-delete-btn" title="Delete notice" onclick="openDeleteNoticeModal(<?php echo $ann['id']; ?>, '<?php echo htmlspecialchars(addslashes($ann['title'])); ?>')">
                    <i class="fa-regular fa-trash-can"></i>
                  </button>
                <?php endif; ?>
              </div>
            </div>

            <p class="notice-desc ann-item-desc"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>

            <div class="ann-item-footer">
              <div class="ann-posted-by">
                <i class="fa-solid fa-user-tie"></i>
                <span>Posted by: <strong><?php echo htmlspecialchars($ann['posted_by']); ?></strong></span>
              </div>
              <div class="ann-target-pill-wrap">
                <span class="badge-status ann-target-pill target-<?php echo htmlspecialchars($ann['target_role']); ?>">
                  <i class="fa-solid fa-circle-dot"></i>
                  <span>Target: <?php echo ($ann['target_role'] === 'all') ? 'All (Everyone)' : ucfirst(htmlspecialchars($ann['target_role'])); ?></span>
                </span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Dynamic Empty Search Result State -->
        <div class="ann-empty-state" id="annSearchEmpty" style="display: none;">
          <div class="ann-empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
          <h3 class="ann-empty-title">No matching circulars found</h3>
          <p class="ann-empty-desc">We couldn't find any announcements matching your current filters or search term.</p>
          <button type="button" class="btn-hrms btn-hrms-secondary" style="margin-top: 14px;" onclick="resetAnnFilters()">
            <i class="fa-solid fa-rotate-left"></i> Reset Search &amp; Filters
          </button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal: Post Announcement -->
<?php if ($current_user['role'] === 'super_admin'): ?>
<div class="hrms-modal" id="announcementModal">
  <div class="hrms-modal-card ann-modal-card">
    <div class="hrms-modal-header ann-modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div class="ann-modal-icon-badge">
          <i class="fa-solid fa-bullhorn"></i>
        </div>
        <div>
          <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--hrms-navy); margin: 0;">Broadcast New Circular</h3>
          <p style="font-size: 0.78rem; color: var(--hrms-text-muted); margin: 2px 0 0;">Publish official notice to faculty, parents or entire academy</p>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('announcementModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo ADMIN_URL; ?>/announcements">
      <input type="hidden" name="action" value="create">
      <div class="hrms-modal-body ann-modal-body">
        <div class="form-group">
          <label class="form-label" style="font-weight: 700; font-size: 0.82rem;">Notice Headline / Title <span style="color: #ef4444;">*</span></label>
          <input type="text" name="title" class="hrms-input" placeholder="e.g. Sunday Mock Test Schedule &amp; Timings" required autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label" style="font-weight: 700; font-size: 0.82rem;">Target Audience <span style="color: #ef4444;">*</span></label>
          <select name="target_role" class="hrms-input" required>
            <option value="all">Everyone (All Faculty, Parents &amp; Students)</option>
            <option value="staff">Faculty &amp; Staff Only</option>
            <option value="parent">Parents &amp; Students Only</option>
          </select>
          <span style="font-size: 0.74rem; color: var(--hrms-text-muted); display: block; margin-top: 4px;">
            <i class="fa-solid fa-circle-info"></i> Circular will only appear on the dashboard of selected user roles.
          </span>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-weight: 700; font-size: 0.82rem;">Notice Message Details <span style="color: #ef4444;">*</span></label>
          <textarea name="content" class="hrms-input" rows="5" placeholder="Write full notice details, instructions, date/time and necessary contacts..." required></textarea>
        </div>
      </div>
      <div class="hrms-modal-footer ann-modal-footer">
        <button type="button" class="btn-hrms btn-hrms-secondary" onclick="closeModal('announcementModal')">
          Cancel
        </button>
        <button type="submit" class="btn-hrms btn-hrms-primary">
          <i class="fa-solid fa-paper-plane"></i> Publish Notice
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Delete Announcement Confirmation -->
<div class="hrms-modal" id="deleteNoticeModal">
  <div class="hrms-modal-card ann-delete-modal-card">
    <div class="hrms-modal-header ann-modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div class="ann-modal-icon-badge delete">
          <i class="fa-solid fa-trash-can"></i>
        </div>
        <div>
          <h3 style="font-size: 1.05rem; font-weight: 800; color: #dc2626; margin: 0;">Delete Announcement</h3>
          <p style="font-size: 0.78rem; color: var(--hrms-text-muted); margin: 2px 0 0;">Permanent removal from noticeboard</p>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('deleteNoticeModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?php echo ADMIN_URL; ?>/announcements">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="deleteNoticeId" value="">
      <div class="hrms-modal-body ann-modal-body">
        <p style="font-size: 0.88rem; color: var(--hrms-text); margin: 0 0 10px; line-height: 1.5;">
          Are you sure you want to permanently delete this circular?
        </p>
        <div class="ann-delete-preview-box">
          <i class="fa-solid fa-quote-left" style="color: #cbd5e1; margin-right: 6px;"></i>
          <strong id="deleteNoticeTitle" style="color: var(--hrms-navy);"></strong>
        </div>
        <p style="font-size: 0.78rem; color: #ef4444; margin: 10px 0 0;">
          <i class="fa-solid fa-triangle-exclamation"></i> This action cannot be reversed. The notice will immediately disappear from all user dashboards.
        </p>
      </div>
      <div class="hrms-modal-footer ann-modal-footer">
        <button type="button" class="btn-hrms btn-hrms-secondary" onclick="closeModal('deleteNoticeModal')">
          Cancel
        </button>
        <button type="submit" class="btn-hrms btn-hrms-danger" style="background: #dc2626; color: #fff; border: 1px solid #dc2626;">
          <i class="fa-solid fa-trash-can"></i> Yes, Delete Notice
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
let currentAudienceFilter = 'all';

function filterAnnouncements(filter, tabBtn) {
  currentAudienceFilter = filter;
  
  // Update active tab UI
  document.querySelectorAll('.ann-tab-pill').forEach(btn => btn.classList.remove('active'));
  if (tabBtn) tabBtn.classList.add('active');
  
  applyFilters();
}

function handleAnnSearch() {
  const query = (document.getElementById('annSearchInput').value || '').trim();
  const clearBtn = document.getElementById('annSearchClear');
  if (clearBtn) {
    clearBtn.style.display = query.length > 0 ? 'inline-flex' : 'none';
  }
  applyFilters();
}

function clearAnnSearch() {
  const searchInput = document.getElementById('annSearchInput');
  if (searchInput) {
    searchInput.value = '';
    searchInput.focus();
  }
  const clearBtn = document.getElementById('annSearchClear');
  if (clearBtn) clearBtn.style.display = 'none';
  applyFilters();
}

function resetAnnFilters() {
  clearAnnSearch();
  const allTab = document.querySelector('.ann-tab-pill[data-filter="all"]');
  if (allTab) {
    filterAnnouncements('all', allTab);
  }
}

function applyFilters() {
  const query = (document.getElementById('annSearchInput')?.value || '').toLowerCase().trim();
  const items = document.querySelectorAll('.ann-item');
  let visibleCount = 0;

  items.forEach(item => {
    const target = item.getAttribute('data-target') || 'all';
    const text = item.textContent.toLowerCase();
    
    // Check audience filter
    let audienceMatch = false;
    if (currentAudienceFilter === 'all') {
      audienceMatch = true;
    } else if (currentAudienceFilter === 'all-target') {
      audienceMatch = (target === 'all');
    } else if (currentAudienceFilter === 'staff') {
      audienceMatch = (target === 'staff');
    } else if (currentAudienceFilter === 'parent') {
      audienceMatch = (target === 'parent');
    }

    // Check search query
    const searchMatch = !query || text.includes(query);

    if (audienceMatch && searchMatch) {
      item.style.display = '';
      visibleCount++;
    } else {
      item.style.display = 'none';
    }
  });

  // Update badge count
  const badge = document.getElementById('annCountBadge');
  if (badge) {
    badge.textContent = visibleCount + ' ' + (visibleCount === 1 ? 'Notice' : 'Notices');
  }

  // Handle empty state
  const searchEmpty = document.getElementById('annSearchEmpty');
  if (searchEmpty) {
    if (visibleCount === 0 && items.length > 0) {
      searchEmpty.style.display = 'block';
    } else {
      searchEmpty.style.display = 'none';
    }
  }
}

function openDeleteNoticeModal(id, title) {
  const idInput = document.getElementById('deleteNoticeId');
  const titleDisplay = document.getElementById('deleteNoticeTitle');
  if (idInput) idInput.value = id;
  if (titleDisplay) titleDisplay.textContent = title;
  openModal('deleteNoticeModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
