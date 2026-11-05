<?php
/**
 * Quick Note Modal Component (Yellow Post-it Style)
 * Matches realistic sticky-note stationery with ruled lines, dog-ear folded corner,
 * and automatic server + localStorage persistence.
 */
if (!defined('APP_INIT') && !defined('HRMS_URL')) {
    require_once __DIR__ . '/../config.php';
}

$qn_user = get_logged_user();
$qn_user_id = $qn_user ? intval($qn_user['id']) : 0;
$qn_csrf = get_csrf_token();
?>

<!-- Quick Note Sticky Note Modal Overlay -->
<div id="quickNoteModal" class="quick-note-overlay" aria-hidden="true" role="dialog" aria-labelledby="quickNoteModalTitle">
  <div class="quick-note-paper" id="quickNotePaper" onclick="event.stopPropagation();">
    
    <!-- Dog-ear Corner Fold (Bottom Left) -->
    <div class="quick-note-fold-bg"></div>
    <div class="quick-note-fold"></div>

    <!-- Header Band with Dashed Divider -->
    <div class="quick-note-header">
      <div class="quick-note-title" id="quickNoteModalTitle">
        <i class="fa-solid fa-pencil"></i>
        <span>QUICK NOTE</span>
      </div>
      <button type="button" class="quick-note-close" id="quickNoteCloseBtn" aria-label="Close Note" title="Close (Esc)">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Ruled Paper Body -->
    <div class="quick-note-body">
      <textarea id="quickNoteTextarea" class="quick-note-textarea" placeholder="Type your notes here..." spellcheck="true" autocomplete="off"></textarea>
      
      <!-- Auto-save Status Pill -->
      <div class="quick-note-status-badge" id="quickNoteStatusBadge">
        <i class="fa-solid fa-check"></i>
        <span>Saved</span>
      </div>
    </div>

    <!-- Hidden Metadata Tokens -->
    <input type="hidden" id="quickNoteCsrf" value="<?php echo htmlspecialchars($qn_csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="quickNoteUserId" value="<?php echo $qn_user_id; ?>">
  </div>
</div>

<style>
/* ── Quick Note Sticky Note Styles ── */
.header-note-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #fef9c3;
  border: 1.5px solid #fde047;
  color: #854d0e;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  padding: 0;
  flex-shrink: 0;
  position: relative;
}
.header-note-btn:hover {
  background: #fef08a;
  border-color: #eab308;
  color: #713f12;
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(234, 179, 8, 0.25);
}
.header-note-btn:active {
  transform: translateY(0);
}
[data-theme="dark"] .header-note-btn {
  background: rgba(253, 224, 71, 0.14) !important;
  border-color: rgba(253, 224, 71, 0.35) !important;
  color: #fef08a !important;
}
[data-theme="dark"] .header-note-btn:hover {
  background: rgba(253, 224, 71, 0.24) !important;
  border-color: #fde047 !important;
}

/* Modal Overlay */
.quick-note-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.52);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  z-index: 99999;
  display: none;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.22s ease;
  padding: 16px;
  box-sizing: border-box;
}
.quick-note-overlay.active {
  display: flex;
  opacity: 1;
}

/* Yellow Paper Card */
.quick-note-paper {
  position: relative;
  width: 480px;
  max-width: 94vw;
  min-height: 430px;
  max-height: 85vh;
  background: #fff897;
  background: linear-gradient(180deg, #fff688 0%, #fff99c 100%);
  border: 1px solid #ebd966;
  border-radius: 4px;
  box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(215, 195, 90, 0.35);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
  transform: scale(0.92) translateY(12px);
  transition: transform 0.24s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.quick-note-overlay.active .quick-note-paper {
  transform: scale(1) translateY(0);
}

/* Dog-ear corner fold in bottom-left */
.quick-note-fold-bg {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 32px;
  height: 32px;
  background: rgba(0, 0, 0, 0.16);
  clip-path: polygon(0 0, 100% 100%, 0 100%);
  z-index: 8;
  pointer-events: none;
}
.quick-note-fold {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 0;
  border-bottom: 32px solid #ca9a04;
  border-right: 32px solid transparent;
  filter: drop-shadow(2px -2px 3px rgba(0, 0, 0, 0.28));
  z-index: 10;
  pointer-events: none;
}

/* Header */
.quick-note-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 14px 22px;
  border-bottom: 2px dashed #ded479;
  background: transparent;
}
.quick-note-title {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #494112;
  font-size: 1.15rem;
  font-weight: 800;
  font-family: 'Outfit', sans-serif;
  letter-spacing: 0.05em;
  user-select: none;
}
.quick-note-title i {
  font-size: 1.05rem;
  transform: rotate(-10deg);
}
.quick-note-close {
  background: none;
  border: none;
  font-size: 1.35rem;
  color: #494112;
  cursor: pointer;
  padding: 4px 6px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  opacity: 0.8;
  transition: all 0.2s ease;
}
.quick-note-close:hover {
  opacity: 1;
  background: rgba(0, 0, 0, 0.06);
  transform: scale(1.08);
}

/* Body with Notebook Ruled Lines */
.quick-note-body {
  position: relative;
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 0;
  margin: 0;
  overflow: hidden;
  background: transparent;
}
.quick-note-textarea {
  width: 100%;
  flex: 1;
  min-height: 330px;
  max-height: calc(85vh - 110px);
  background: transparent;
  background-image: repeating-linear-gradient(
    to bottom,
    transparent 0px,
    transparent 33px,
    #ebdf81 33px,
    #ebdf81 34px
  );
  background-attachment: local;
  border: none;
  outline: none;
  resize: none;
  font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
  font-size: 16px;
  font-weight: 500;
  line-height: 34px;
  color: #38320e;
  padding: 8px 22px 42px 22px;
  margin: 0;
  box-sizing: border-box;
  overflow-y: auto;
}
.quick-note-textarea::placeholder {
  color: #6d6640;
  opacity: 0.85;
  font-weight: 500;
}
.quick-note-textarea::-webkit-scrollbar {
  width: 6px;
}
.quick-note-textarea::-webkit-scrollbar-track {
  background: transparent;
}
.quick-note-textarea::-webkit-scrollbar-thumb {
  background: rgba(180, 160, 60, 0.35);
  border-radius: 4px;
}
.quick-note-textarea::-webkit-scrollbar-thumb:hover {
  background: rgba(180, 160, 60, 0.6);
}

/* Saved Status Badge (Bottom Right) */
.quick-note-status-badge {
  position: absolute;
  bottom: 12px;
  right: 18px;
  background: rgba(255, 255, 255, 0.94);
  backdrop-filter: blur(4px);
  border: 1px solid rgba(225, 210, 100, 0.7);
  border-radius: 9999px;
  padding: 4px 12px;
  font-size: 0.80rem;
  font-weight: 800;
  color: #494112;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  pointer-events: none;
  transition: all 0.25s ease;
  user-select: none;
  z-index: 12;
}
.quick-note-status-badge i {
  font-size: 0.75rem;
  color: #16a34a;
}

@media (max-width: 768px) {
  .header-note-btn {
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    font-size: 13.5px !important;
    border-radius: 7px !important;
  }
  .quick-note-paper {
    width: 100%;
    max-width: 96vw;
    min-height: 380px;
  }
  .quick-note-header {
    padding: 13px 16px 11px 18px;
  }
  .quick-note-title {
    font-size: 1.05rem;
  }
  .quick-note-body {
    padding: 0;
  }
  .quick-note-textarea {
    padding: 8px 16px 38px 16px;
    font-size: 15px;
  }
}
</style>

<script>
(function() {
  const modal = document.getElementById('quickNoteModal');
  const textarea = document.getElementById('quickNoteTextarea');
  const statusBadge = document.getElementById('quickNoteStatusBadge');
  const closeBtn = document.getElementById('quickNoteCloseBtn');
  const openBtn = document.getElementById('headerQuickNoteBtn');
  const csrfInput = document.getElementById('quickNoteCsrf');
  const userIdInput = document.getElementById('quickNoteUserId');

  if (!modal || !textarea) return;

  const userId = userIdInput ? userIdInput.value : '0';
  const localKey = 'ksv_quick_note_' + userId;
  let saveTimer = null;
  let hasFetchedServer = false;

  function setBadgeState(state) {
    if (!statusBadge) return;
    if (state === 'saving') {
      statusBadge.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin" style="color: #d97706;"></i> <span>Saving...</span>';
    } else if (state === 'saved') {
      statusBadge.innerHTML = '<i class="fa-solid fa-check" style="color: #16a34a;"></i> <span>Saved</span>';
    } else if (state === 'offline') {
      statusBadge.innerHTML = '<i class="fa-solid fa-check" style="color: #16a34a;"></i> <span>Saved</span>';
    }
  }

  function fetchServerNote() {
    if (hasFetchedServer) return;
    fetch('/web-app/api/quick-note', {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
      hasFetchedServer = true;
      if (data && data.success && typeof data.note_content === 'string') {
        const localContent = localStorage.getItem(localKey);
        // Use server content if present, or local fallback
        if (data.note_content.trim() !== '') {
          textarea.value = data.note_content;
          localStorage.setItem(localKey, data.note_content);
        } else if (localContent && localContent.trim() !== '') {
          textarea.value = localContent;
          saveNoteNow();
        }
      }
    })
    .catch(() => {
      // Local fallback
      const localContent = localStorage.getItem(localKey);
      if (localContent) textarea.value = localContent;
    });
  }

  function saveNoteNow() {
    const content = textarea.value;
    localStorage.setItem(localKey, content);
    setBadgeState('saving');

    const csrf = csrfInput ? csrfInput.value : '';
    const body = new URLSearchParams();
    body.append('csrf_token', csrf);
    body.append('note_content', content);

    fetch('/web-app/api/quick-note', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
      if (data && data.success) {
        setBadgeState('saved');
      } else {
        setBadgeState('offline');
      }
    })
    .catch(() => {
      setBadgeState('offline');
    });
  }

  function openQuickNote() {
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    
    // Initial local populate
    const cached = localStorage.getItem(localKey);
    if (cached !== null && textarea.value === '') {
      textarea.value = cached;
    }
    
    // Fetch latest from server
    fetchServerNote();
    
    // Auto-focus textarea
    setTimeout(() => {
      textarea.focus();
      // Position cursor at end
      textarea.setSelectionRange(textarea.value.length, textarea.value.length);
    }, 120);
  }

  function closeQuickNote() {
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    // Save any pending edits
    if (saveTimer) {
      clearTimeout(saveTimer);
      saveNoteNow();
    }
  }

  // Trigger button in header
  if (openBtn) {
    openBtn.addEventListener('click', function(e) {
      e.preventDefault();
      openQuickNote();
    });
  }

  // Close handlers
  if (closeBtn) {
    closeBtn.addEventListener('click', closeQuickNote);
  }
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      closeQuickNote();
    }
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
      closeQuickNote();
    }
  });

  // Debounced Auto-save on typing (400ms)
  textarea.addEventListener('input', function() {
    setBadgeState('saving');
    localStorage.setItem(localKey, textarea.value);
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(saveNoteNow, 400);
  });

  // Preload cached note on page ready
  const initCache = localStorage.getItem(localKey);
  if (initCache !== null) {
    textarea.value = initCache;
  }
})();
</script>
