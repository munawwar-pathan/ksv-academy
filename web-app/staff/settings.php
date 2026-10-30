<?php
/**
 * Faculty & Staff Portal - Settings (General Appearance & Security)
 */
require_once __DIR__ . '/../config.php';

// Requires logged in staff or super admin
$current_user = require_login(['staff', 'super_admin']);

$page_title = 'Settings | Faculty Portal';
$page_heading = 'Faculty Settings';
$page_subheading = 'Personal Preferences, System Appearance & Account Security';
$active_menu = 'settings';

$db = get_db_connection();
$active_session = get_active_session();
$school_settings = get_school_settings();

// Get Staff Profile Info if exists
$stmt = $db->prepare("SELECT * FROM staff_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$current_user['id']]);
$staff_profile = $stmt->fetch();

$current_tab = $_GET['tab'] ?? 'general';
if (!in_array($current_tab, ['general', 'security'])) {
    $current_tab = 'general';
}

// ── Handle Actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Change Password
    if ($action === 'change_password') {
        $current_pass = trim($_POST['current_password'] ?? '');
        $new_pass = trim($_POST['new_password'] ?? '');
        $confirm_pass = trim($_POST['confirm_password'] ?? '');

        // Fetch fresh user record
        $u_stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $u_stmt->execute([$current_user['id']]);
        $u_fresh = $u_stmt->fetch();

        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            set_flash('error', 'All password fields are required.');
        } elseif (!$u_fresh || ($current_pass !== $u_fresh['password'] && !password_verify($current_pass, $u_fresh['password']))) {
            set_flash('error', 'Your current password is incorrect. Please check and try again.');
        } elseif ($new_pass !== $confirm_pass) {
            set_flash('error', 'New password and confirm password do not match.');
        } elseif (strlen($new_pass) < 6) {
            set_flash('error', 'New password must be at least 6 characters long.');
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed, $current_user['id']]);
            set_flash('success', 'Your password has been changed successfully! Your account credentials have been updated.');
        }

        header('Location: ' . STAFF_URL . '/settings?tab=security');
        exit;
    }

    // 2. Save Theme Preference (Instant AJAX)
    if ($action === 'save_theme') {
        $theme = in_array($_POST['theme'] ?? '', ['light', 'dark']) ? $_POST['theme'] : 'light';
        // Update school settings table or persist
        try {
            $stmt = $db->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES ('theme_mode', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$theme]);
        } catch (Exception $e) {}

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'theme' => $theme]);
        exit;
    }
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Settings Tabs Navigation ── */
.settings-nav-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--hrms-card);
  padding: 8px 12px;
  border-radius: 12px;
  border: 1px solid var(--hrms-border);
  margin-bottom: 24px;
  box-shadow: var(--hrms-shadow-sm);
  width: fit-content;
}

.settings-tab-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 20px;
  border-radius: 9px;
  font-size: 0.86rem;
  font-weight: 700;
  font-family: inherit;
  color: var(--hrms-text-muted);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  text-decoration: none;
}

.settings-tab-pill:hover {
  color: var(--hrms-text);
  background: rgba(0, 146, 223, 0.08);
}

.settings-tab-pill.active {
  color: #ffffff;
  background: linear-gradient(135deg, #0092df 0%, #0066aa 100%);
  box-shadow: 0 4px 12px rgba(0, 146, 223, 0.3);
}

.settings-tab-pane {
  display: none;
}

.settings-tab-pane.active {
  display: block;
  animation: fadeIn 0.22s ease forwards;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ── Live Academy / Security Preview Card ── */
.academy-preview-card {
  background: var(--hrms-card);
  border: 1px solid var(--hrms-border);
  border-radius: var(--hrms-radius-lg);
  padding: 24px;
  box-shadow: var(--hrms-shadow-sm);
  position: relative;
  overflow: hidden;
}

.academy-preview-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 5px;
  background: linear-gradient(90deg, #0092df, #005a9c);
}

.academy-meta-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--hrms-border);
  font-size: 0.82rem;
}

.academy-meta-row:last-child {
  border-bottom: none;
}

.academy-meta-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  background: rgba(0, 146, 223, 0.08);
  color: #0284c7;
  flex-shrink: 0;
}

.academy-meta-label {
  display: block;
  font-size: 0.68rem;
  color: var(--hrms-text-muted);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 2px;
}

.academy-meta-val {
  display: block;
  font-size: 0.84rem;
  color: var(--hrms-text);
  font-weight: 700;
}

.settings-group-card {
  background: var(--hrms-card);
  border: 1px solid var(--hrms-border);
  border-radius: var(--hrms-radius-lg);
  padding: 22px 24px;
  margin-bottom: 20px;
  box-shadow: var(--hrms-shadow-sm);
}

.form-section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--hrms-border);
}

.form-section-icon {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  flex-shrink: 0;
}

/* ── Form Inputs & Labels ── */
.form-group {
  display: flex;
  flex-direction: column;
}

.input-field-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--hrms-text);
  margin-bottom: 6px;
}

.req {
  color: #ef4444;
}

.settings-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--hrms-border);
  border-radius: 8px;
  font-size: 0.88rem;
  font-family: inherit;
  color: var(--hrms-text);
  background: var(--hrms-card);
  transition: all 0.2s ease;
  outline: none;
}

.settings-input:focus {
  border-color: #0092df;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.15);
}

/* ── Eye Toggle Password Wrapper ── */
.password-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input-wrap input {
  padding-right: 44px;
  width: 100%;
}

.password-toggle-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  font-size: 0.95rem;
  padding: 4px 6px;
  transition: color 0.15s ease;
}

.password-toggle-btn:hover {
  color: #0284c7;
}

/* Strength Meter */
.strength-meter-bar {
  height: 4px;
  background: #e2e8f0;
  border-radius: 2px;
  margin-top: 6px;
  overflow: hidden;
}

.strength-meter-fill {
  height: 100%;
  width: 0%;
  transition: width 0.3s ease, background-color 0.3s ease;
}

[data-theme="dark"] .strength-meter-bar {
  background: #273952;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS FOR STAFF SETTINGS
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  /* Settings Tabs Navigation */
  .settings-nav-bar {
    width: 100% !important;
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    padding: 6px !important;
    box-sizing: border-box !important;
    margin-bottom: 16px !important;
  }
  .settings-tab-pill {
    width: 100% !important;
    justify-content: center !important;
    padding: 9px 12px !important;
    font-size: 0.82rem !important;
  }

  /* 2-Column Grid Layout in Both Tabs */
  .settings-grid-layout,
  .settings-tab-pane > div[style*="grid-template-columns"] {
    grid-template-columns: 1fr !important;
    gap: 16px !important;
    margin-bottom: 20px !important;
  }

  /* Left Appearance Card */
  .settings-group-card,
  .appearance-card {
    padding: 16px 14px !important;
    border-radius: 12px !important;
    margin-bottom: 16px !important;
  }
  .form-section-header {
    gap: 10px !important;
    padding-bottom: 12px !important;
    margin-bottom: 12px !important;
  }
  .form-section-icon {
    width: 32px !important;
    height: 32px !important;
    font-size: 0.95rem !important;
  }

  /* Theme Switch Options */
  .theme-switch-grid {
    grid-template-columns: 1fr 1fr !important;
    gap: 10px !important;
  }
  .theme-option-box {
    padding: 8px !important;
    border-radius: 10px !important;
  }
  .theme-wireframe {
    height: 54px !important;
  }

  /* Faculty Profile Summary Card */
  .staff-profile-card,
  .hrms-card {
    border-radius: 12px !important;
  }
  .hrms-card-header {
    padding: 14px 16px !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 10px !important;
  }
  .hrms-card-header .card-title {
    font-size: 0.96rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
  .hrms-card-header > span {
    align-self: flex-start !important;
  }
  .profile-details-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
  }
  .profile-details-grid > div,
  .profile-info-box {
    padding: 10px 12px !important;
  }

  /* Tab 2: Security Profile Preview Card */
  .academy-preview-card {
    padding: 16px 14px !important;
    border-radius: 12px !important;
    margin-bottom: 16px !important;
  }
  .academy-meta-row {
    padding: 8px 0 !important;
    font-size: 0.78rem !important;
  }
  .academy-meta-icon {
    width: 28px !important;
    height: 28px !important;
    font-size: 0.78rem !important;
  }
  .academy-meta-val {
    font-size: 0.80rem !important;
  }

  /* Change Password Card & Form */
  .change-password-card .hrms-card-header {
    padding: 14px 16px !important;
  }
  .change-password-form,
  .change-password-card form {
    padding: 16px 14px !important;
  }
  .password-form-inner,
  .change-password-card form > div {
    max-width: 100% !important;
    gap: 14px !important;
  }
  .settings-input {
    height: 40px !important;
    font-size: 0.84rem !important;
    padding: 8px 40px 8px 12px !important;
  }
  .password-submit-btn,
  .change-password-card button[type="submit"] {
    width: 100% !important;
    height: 42px !important;
    justify-content: center !important;
    font-size: 0.84rem !important;
    border-radius: 9px !important;
    box-sizing: border-box !important;
  }
}

/* Micro Devices (<= 360px) */
@media (max-width: 360px) {
  .profile-details-grid {
    grid-template-columns: 1fr !important;
  }
  .theme-switch-grid {
    grid-template-columns: 1fr !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR STAFF SETTINGS
   ══════════════════════════════════════════════════════════════════════ */

/* 1. Top Settings Tabs Navigation */
[data-theme="dark"] .settings-nav-bar {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  border-radius: 12px !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .settings-tab-pill {
  color: #94a3b8 !important;
}
[data-theme="dark"] .settings-tab-pill:hover {
  color: #f8fafc !important;
  background: rgba(255, 255, 255, 0.05) !important;
}
[data-theme="dark"] .settings-tab-pill.active {
  color: #ffffff !important;
  background: linear-gradient(135deg, #0092df 0%, #0066aa 100%) !important;
  box-shadow: 0 4px 12px rgba(0, 146, 223, 0.35) !important;
}

/* 2. Appearance & General Group Cards */
[data-theme="dark"] .settings-group-card,
[data-theme="dark"] .appearance-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  border-radius: 12px !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .form-section-header {
  border-bottom: 1px solid #334155 !important;
}
[data-theme="dark"] .form-section-icon {
  background: rgba(2, 132, 199, 0.18) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .form-section-header h4 {
  color: #f8fafc !important;
}
[data-theme="dark"] .form-section-header p {
  color: #94a3b8 !important;
}

/* Theme Option Cards */
[data-theme="dark"] .theme-option-box {
  background: #0f172a !important;
  border: 2px solid #334155 !important;
  border-radius: 12px !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .theme-option-box:hover {
  border-color: #0284c7 !important;
  background: #1e293b !important;
}
[data-theme="dark"] .theme-option-box.active {
  border-color: #0284c7 !important;
  background: rgba(2, 132, 199, 0.12) !important;
  box-shadow: 0 0 0 1px #0284c7, 0 4px 14px rgba(2, 132, 199, 0.25) !important;
}
[data-theme="dark"] .theme-option-box.active .theme-check-icon {
  color: #0284c7 !important;
}
[data-theme="dark"] .theme-option-box:not(.active) .theme-check-icon {
  color: #475569 !important;
}
[data-theme="dark"] .theme-name {
  color: #f8fafc !important;
}
[data-theme="dark"] .theme-status-footer span:last-child {
  color: #94a3b8 !important;
}

/* Wireframe Previews */
[data-theme="dark"] .light-wireframe {
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .dark-wireframe {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .dark-wireframe .wireframe-header {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .dark-wireframe .wireframe-sidebar {
  background: #1e293b !important;
}
[data-theme="dark"] .dark-wireframe .wireframe-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
}

/* Theme Tips Box */
[data-theme="dark"] .theme-tips-box,
[data-theme="dark"] div[style*="background: rgba(0, 146, 223, 0.05)"] {
  background: rgba(2, 132, 199, 0.1) !important;
  border: 1px solid rgba(2, 132, 199, 0.25) !important;
  border-radius: 12px !important;
}
[data-theme="dark"] .theme-tips-box > div:first-child,
[data-theme="dark"] div[style*="background: rgba(0, 146, 223, 0.05)"] > div:first-child {
  color: #38bdf8 !important;
}
[data-theme="dark"] .theme-tips-box p,
[data-theme="dark"] div[style*="background: rgba(0, 146, 223, 0.05)"] p {
  color: #94a3b8 !important;
}

/* 3. Faculty Profile Summary Card */
[data-theme="dark"] .staff-profile-card,
[data-theme="dark"] .change-password-card,
[data-theme="dark"] .hrms-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  border-radius: 14px !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .hrms-card-header {
  background: #1e293b !important;
  border-bottom: 1px solid #334155 !important;
}
[data-theme="dark"] .hrms-card-header .card-title,
[data-theme="dark"] .hrms-card-header .card-title span {
  color: #f8fafc !important;
}
[data-theme="dark"] .hrms-card-header p {
  color: #94a3b8 !important;
}

/* Active Faculty Status Badge */
[data-theme="dark"] .staff-status-badge,
[data-theme="dark"] .hrms-card-header > span[style*="#f0fdf4"],
[data-theme="dark"] .staff-profile-card .hrms-card-header > span {
  background: rgba(16, 185, 129, 0.15) !important;
  border: 1px solid rgba(16, 185, 129, 0.35) !important;
  color: #34d399 !important;
}

/* Profile Info Grid */
[data-theme="dark"] .profile-info-box,
[data-theme="dark"] .profile-details-grid > div,
[data-theme="dark"] .staff-profile-card div[style*="background: var(--hrms-bg)"] {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  border-radius: 10px !important;
}
[data-theme="dark"] .profile-info-box > div:first-child,
[data-theme="dark"] .profile-details-grid > div > div:first-child {
  color: #94a3b8 !important;
}
[data-theme="dark"] .profile-info-box > div:last-child,
[data-theme="dark"] .profile-details-grid > div > div:last-child {
  color: #f8fafc !important;
}
[data-theme="dark"] .profile-details-grid div[style*="color: #0284c7"],
[data-theme="dark"] .profile-info-box div[style*="color: #0284c7"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] .profile-card-footer,
[data-theme="dark"] .profile-card-body > div[style*="border-top"] {
  border-top: 1px solid #334155 !important;
}
[data-theme="dark"] .profile-card-note,
[data-theme="dark"] .profile-card-body > div[style*="border-top"] > div {
  color: #94a3b8 !important;
}

/* 4. Tab 2: Security Profile Preview Card */
[data-theme="dark"] .academy-preview-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  border-radius: 14px !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}
[data-theme="dark"] .security-profile-badge,
[data-theme="dark"] .academy-preview-card span[style*="background: #ede9fe"] {
  background: rgba(124, 58, 237, 0.15) !important;
  border: 1px solid rgba(124, 58, 237, 0.35) !important;
  color: #c084fc !important;
}
[data-theme="dark"] .academy-preview-card span[style*="color: #10b981"] {
  color: #34d399 !important;
}
[data-theme="dark"] .user-avatar-circle,
[data-theme="dark"] .academy-preview-card div[style*="border: 3px solid #ffffff"] {
  border-color: #334155 !important;
}
[data-theme="dark"] .academy-preview-card h3 {
  color: #f8fafc !important;
}
[data-theme="dark"] .academy-preview-card p[style*="font-family: monospace"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .user-role-badge,
[data-theme="dark"] .academy-preview-card span[style*="background: #f0f9ff"] {
  background: rgba(2, 132, 199, 0.15) !important;
  border: 1px solid rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

/* Security Meta Box */
[data-theme="dark"] .security-meta-box,
[data-theme="dark"] .academy-preview-card div[style*="background: var(--hrms-bg)"] {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  border-radius: 10px !important;
}
[data-theme="dark"] .academy-meta-row {
  border-bottom: 1px solid #334155 !important;
}
[data-theme="dark"] .academy-meta-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .academy-meta-val {
  color: #f8fafc !important;
}

/* Meta Icons Dark Tints */
[data-theme="dark"] .academy-meta-icon[style*="#e0f2fe"],
[data-theme="dark"] .academy-meta-icon[style*="#0284c7"] {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border: 1px solid rgba(2, 132, 199, 0.35) !important;
}
[data-theme="dark"] .academy-meta-icon[style*="#ecfdf5"],
[data-theme="dark"] .academy-meta-icon[style*="#059669"] {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border: 1px solid rgba(16, 185, 129, 0.35) !important;
}
[data-theme="dark"] .academy-meta-icon[style*="#fef2f2"],
[data-theme="dark"] .academy-meta-icon[style*="#dc2626"] {
  background: rgba(239, 68, 68, 0.15) !important;
  color: #f87171 !important;
  border: 1px solid rgba(239, 68, 68, 0.35) !important;
}
[data-theme="dark"] .academy-meta-val[style*="color: #059669"],
[data-theme="dark"] .academy-meta-val span[style*="color: #059669"] {
  color: #34d399 !important;
}

/* Security Guidelines Box */
[data-theme="dark"] .security-guidelines-box,
[data-theme="dark"] .academy-preview-card div[style*="background: rgba(245, 158, 11, 0.05)"] {
  background: rgba(245, 158, 11, 0.08) !important;
  border: 1px dashed rgba(245, 158, 11, 0.3) !important;
  border-radius: 10px !important;
}
[data-theme="dark"] .security-guidelines-box h4,
[data-theme="dark"] .academy-preview-card div[style*="background: rgba(245, 158, 11, 0.05)"] h4 {
  color: #fbbf24 !important;
}
[data-theme="dark"] .security-guidelines-box ul li,
[data-theme="dark"] .academy-preview-card div[style*="background: rgba(245, 158, 11, 0.05)"] ul li {
  color: #cbd5e1 !important;
}

/* 5. Change Password Form */
[data-theme="dark"] .input-field-label {
  color: #f8fafc !important;
}
[data-theme="dark"] .settings-input {
  background: #0f172a !important;
  border: 1px solid #334155 !important;
  border-radius: 8px !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .settings-input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .settings-input:focus {
  border-color: #38bdf8 !important;
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15) !important;
}
[data-theme="dark"] .password-toggle-btn {
  color: #94a3b8 !important;
}
[data-theme="dark"] .password-toggle-btn:hover {
  color: #38bdf8 !important;
}
[data-theme="dark"] .strength-meter-bar {
  background: #334155 !important;
}
[data-theme="dark"] #strengthLabel {
  color: #94a3b8 !important;
}
</style>

<!-- Top Settings Tabs Header -->
<div class="settings-nav-bar">
  <button type="button" class="settings-tab-pill <?php echo $current_tab === 'general' ? 'active' : ''; ?>" onclick="switchStaffTab('general')">
    <i class="fa-solid fa-sliders"></i>
    <span>General</span>
  </button>
  <button type="button" class="settings-tab-pill <?php echo $current_tab === 'security' ? 'active' : ''; ?>" onclick="switchStaffTab('security')">
    <i class="fa-solid fa-shield-halved"></i>
    <span>Security</span>
  </button>
</div>

<!-- ══════════════════════════════════════════════════════
     TAB 1: GENERAL (APPEARANCE - LIGHT OR DARK MODE)
     ══════════════════════════════════════════════════════ -->
<div id="pane-general" class="settings-tab-pane <?php echo $current_tab === 'general' ? 'active' : ''; ?>">
  
  <div class="settings-grid-layout" style="display: grid; grid-template-columns: 360px 1fr; gap: 24px; align-items: start; margin-bottom: 30px;">
    
    <!-- Left Column: Appearance Card (Light or Dark Mode) -->
    <div>
      <div class="settings-group-card appearance-card" style="margin-bottom: 20px;">
        <div class="form-section-header" style="margin-bottom: 16px;">
          <div class="form-section-icon" style="background: rgba(2, 132, 199, 0.1); color: #0284c7;">
            <i class="fa-solid fa-circle-half-stroke"></i>
          </div>
          <div>
            <h4 style="margin: 0; font-size: 1rem; font-weight: 800; font-family: 'Outfit', sans-serif;">
              Appearance
            </h4>
            <p style="margin: 2px 0 0; font-size: 0.76rem; color: #64748b;">
              Customize interface theme for Light or Dark mode
            </p>
          </div>
        </div>

        <div class="theme-switch-grid">
          <!-- Light Mode Option -->
          <div class="theme-option-box <?php echo ($school_settings['theme_mode'] ?? 'light') !== 'dark' ? 'active' : ''; ?>" id="themeCardLight" onclick="selectStaffTheme('light')">
            <div class="theme-wireframe light-wireframe">
              <div class="wireframe-header">
                <div class="wireframe-dot"></div>
                <div class="wireframe-bar"></div>
              </div>
              <div class="wireframe-content">
                <div class="wireframe-sidebar"></div>
                <div class="wireframe-main">
                  <div class="wireframe-card"></div>
                  <div class="wireframe-card"></div>
                </div>
              </div>
            </div>
            <div class="theme-option-label">
              <div class="theme-option-info">
                <i class="fa-solid fa-sun" style="color: #f59e0b;"></i>
                <span class="theme-name">Light</span>
              </div>
              <div class="theme-check-icon"><i class="fa-solid fa-circle-check"></i></div>
            </div>
          </div>

          <!-- Dark Mode Option -->
          <div class="theme-option-box <?php echo ($school_settings['theme_mode'] ?? 'light') === 'dark' ? 'active' : ''; ?>" id="themeCardDark" onclick="selectStaffTheme('dark')">
            <div class="theme-wireframe dark-wireframe">
              <div class="wireframe-header">
                <div class="wireframe-dot"></div>
                <div class="wireframe-bar"></div>
              </div>
              <div class="wireframe-content">
                <div class="wireframe-sidebar"></div>
                <div class="wireframe-main">
                  <div class="wireframe-card"></div>
                  <div class="wireframe-card"></div>
                </div>
              </div>
            </div>
            <div class="theme-option-label">
              <div class="theme-option-info">
                <i class="fa-solid fa-moon" style="color: #38bdf8;"></i>
                <span class="theme-name">Dark</span>
              </div>
              <div class="theme-check-icon"><i class="fa-solid fa-circle-check"></i></div>
            </div>
          </div>
        </div>

        <div class="theme-status-footer" style="margin-top: 14px; display: flex; align-items: center; justify-content: space-between;">
          <span id="themeSavedMessage" style="font-size: 0.74rem; font-weight: 700; color: #10b981; opacity: 0; transition: opacity 0.25s ease;">
            <i class="fa-solid fa-circle-check"></i> Saved successfully!
          </span>
          <span style="font-size: 0.72rem; color: #64748b;">
            Live preview on click
          </span>
        </div>
      </div>

      <!-- Quick Tips Card -->
      <div class="theme-tips-box" style="background: rgba(0, 146, 223, 0.05); border: 1px solid rgba(0, 146, 223, 0.18); border-radius: 12px; padding: 14px 16px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.80rem; font-weight: 700; color: #0284c7; margin-bottom: 6px;">
          <i class="fa-solid fa-circle-info"></i>
          <span>Theme Tips</span>
        </div>
        <p style="margin: 0; font-size: 0.76rem; color: #64748b; line-height: 1.5;">
          Your selected appearance preference is saved automatically and applies across all staff dashboard, attendance, roster, and payroll modules.
        </p>
      </div>
    </div>

    <!-- Right Column: Faculty Profile Summary Card -->
    <div>
      <div class="hrms-card staff-profile-card" style="border-radius: 14px; overflow: hidden; box-shadow: var(--hrms-shadow-sm); border: 1px solid var(--hrms-border);">
        <div class="hrms-card-header" style="border-bottom: 1px solid var(--hrms-border); padding: 18px 24px; background: var(--hrms-card); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 800; font-family: 'Outfit', sans-serif;">
              <i class="fa-solid fa-user-circle" style="color: #0092df;"></i>
              Faculty Profile &amp; Account Details
            </h3>
            <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
              Your official institutional credentials and employment record.
            </p>
          </div>
          <span class="staff-status-badge" style="font-size: 0.74rem; font-weight: 800; color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 3px 10px; border-radius: 20px;">
            <i class="fa-solid fa-circle-check"></i> Active Faculty
          </span>
        </div>

        <div class="profile-card-body" style="padding: 24px;">
          <div class="profile-details-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px;">
            
            <div class="profile-info-box" style="background: var(--hrms-bg); border: 1px solid var(--hrms-border); border-radius: 10px; padding: 14px;">
              <div style="font-size: 0.70rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Faculty Name</div>
              <div style="font-size: 0.95rem; font-weight: 800; color: var(--hrms-text); font-family: 'Outfit', sans-serif;">
                <?php echo htmlspecialchars($current_user['full_name']); ?>
              </div>
            </div>

            <div class="profile-info-box" style="background: var(--hrms-bg); border: 1px solid var(--hrms-border); border-radius: 10px; padding: 14px;">
              <div style="font-size: 0.70rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Employee Code</div>
              <div style="font-size: 0.95rem; font-weight: 800; color: #0284c7; font-family: monospace;">
                <?php echo htmlspecialchars($staff_profile['employee_code'] ?? 'KSV-FAC-0' . $current_user['id']); ?>
              </div>
            </div>

            <div class="profile-info-box" style="background: var(--hrms-bg); border: 1px solid var(--hrms-border); border-radius: 10px; padding: 14px;">
              <div style="font-size: 0.70rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Department</div>
              <div style="font-size: 0.90rem; font-weight: 700; color: var(--hrms-text);">
                <?php echo htmlspecialchars($staff_profile['department'] ?? 'Academics'); ?>
              </div>
            </div>

            <div class="profile-info-box" style="background: var(--hrms-bg); border: 1px solid var(--hrms-border); border-radius: 10px; padding: 14px;">
              <div style="font-size: 0.70rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Designation</div>
              <div style="font-size: 0.90rem; font-weight: 700; color: var(--hrms-text);">
                <?php echo htmlspecialchars($staff_profile['designation'] ?? 'Faculty Member'); ?>
              </div>
            </div>

            <div class="profile-info-box" style="background: var(--hrms-bg); border: 1px solid var(--hrms-border); border-radius: 10px; padding: 14px;">
              <div style="font-size: 0.70rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Username / Login ID</div>
              <div style="font-size: 0.90rem; font-weight: 700; color: var(--hrms-text); font-family: monospace;">
                @<?php echo htmlspecialchars($current_user['username']); ?>
              </div>
            </div>

            <div class="profile-info-box" style="background: var(--hrms-bg); border: 1px solid var(--hrms-border); border-radius: 10px; padding: 14px;">
              <div style="font-size: 0.70rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Email Address</div>
              <div style="font-size: 0.88rem; font-weight: 600; color: var(--hrms-text);">
                <?php echo htmlspecialchars($current_user['email'] ?? '—'); ?>
              </div>
            </div>

          </div>

          <div class="profile-card-footer" style="margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--hrms-border);">
            <div class="profile-card-note" style="font-size: 0.78rem; color: #64748b;">
              To request changes to official academic records or designations, please contact the administrative office.
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     TAB 2: SECURITY (CHANGE PASSWORD)
     ══════════════════════════════════════════════════════ -->
<div id="pane-security" class="settings-tab-pane <?php echo $current_tab === 'security' ? 'active' : ''; ?>">
  
  <div class="settings-grid-layout" style="display: grid; grid-template-columns: 360px 1fr; gap: 24px; align-items: start; margin-bottom: 30px;">
    
    <!-- Left Column: Security Context Profile -->
    <div>
      <div class="academy-preview-card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span class="security-profile-badge" style="font-size: 0.72rem; font-weight: 800; background: #ede9fe; color: #6d28d9; padding: 2px 10px; border-radius: 12px; border: 1px solid #ddd6fe;">
            <i class="fa-solid fa-user-shield"></i> Security Profile
          </span>
          <span style="font-size: 0.72rem; color: #10b981; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
            <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; display: inline-block;"></span> Active
          </span>
        </div>

        <div style="text-align: center; margin: 18px 0 14px;">
          <div class="user-avatar-circle" style="width: 76px; height: 76px; border-radius: 50%; background: linear-gradient(135deg, #0092df, #005a9c); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.85rem; font-weight: 800; margin: 0 auto 12px; box-shadow: 0 8px 20px rgba(0, 146, 223, 0.25); border: 3px solid #ffffff;">
            <?php 
              $initials = 'F';
              if (!empty($current_user['full_name'])) {
                $words = explode(' ', trim($current_user['full_name']));
                $initials = strtoupper(substr($words[0], 0, 1));
              }
              echo $initials;
            ?>
          </div>
          <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--hrms-text); font-family: 'Outfit', sans-serif;">
            <?php echo htmlspecialchars($current_user['full_name']); ?>
          </h3>
          <p style="margin: 3px 0 8px; font-size: 0.8rem; color: #64748b; font-family: monospace;">
            @<?php echo htmlspecialchars($current_user['username']); ?>
          </p>
          <span class="user-role-badge" style="font-size: 0.72rem; font-weight: 800; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 3px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block;">
            <i class="fa-solid fa-chalkboard-user" style="margin-right: 4px;"></i> Faculty &amp; Staff
          </span>
        </div>

        <!-- Security Meta -->
        <div class="security-meta-box" style="background: var(--hrms-bg); border-radius: 10px; padding: 14px; border: 1px solid var(--hrms-border); margin-bottom: 16px;">
          <div class="academy-meta-row" style="padding-top: 0;">
            <div class="academy-meta-icon" style="background: #e0f2fe; color: #0284c7;"><i class="fa-solid fa-envelope"></i></div>
            <div style="min-width: 0; flex: 1;">
              <span class="academy-meta-label">Registered Email</span>
              <span class="academy-meta-val" style="word-break: break-all;"><?php echo htmlspecialchars($current_user['email'] ?? 'faculty@ksvacademy.com'); ?></span>
            </div>
          </div>
          <div class="academy-meta-row">
            <div class="academy-meta-icon" style="background: #ecfdf5; color: #059669;"><i class="fa-solid fa-fingerprint"></i></div>
            <div>
              <span class="academy-meta-label">Account ID</span>
              <span class="academy-meta-val">#USR-<?php echo str_pad($current_user['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>
          </div>
          <div class="academy-meta-row" style="border-bottom: none; padding-bottom: 0;">
            <div class="academy-meta-icon" style="background: #fef2f2; color: #dc2626;"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
              <span class="academy-meta-label">Encryption</span>
              <span class="academy-meta-val" style="color: #059669; font-weight: 800;"><i class="fa-solid fa-circle-check"></i> Bcrypt Hashed</span>
            </div>
          </div>
        </div>

        <!-- Guidelines -->
        <div class="security-guidelines-box" style="background: rgba(245, 158, 11, 0.05); border-radius: 10px; padding: 14px; border: 1px dashed rgba(245, 158, 11, 0.35);">
          <h4 style="margin: 0 0 8px 0; font-size: 0.78rem; font-weight: 800; color: #b45309; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-shield"></i> Security Guidelines
          </h4>
          <ul style="margin: 0; padding-left: 18px; font-size: 0.76rem; color: #64748b; line-height: 1.6;">
            <li>Minimum 6 characters (8+ recommended).</li>
            <li>Mix uppercase, lowercase, numbers &amp; symbols.</li>
            <li>Do not share your login credentials with anyone.</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Right Column: Change Password Form -->
    <div>
      <div class="hrms-card change-password-card" style="border-radius: 14px; overflow: hidden; box-shadow: var(--hrms-shadow-sm); border: 1px solid var(--hrms-border);">
        <div class="hrms-card-header" style="border-bottom: 1px solid var(--hrms-border); padding: 18px 24px; background: var(--hrms-card); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 800; font-family: 'Outfit', sans-serif;">
              <i class="fa-solid fa-key" style="color: #0092df;"></i>
              Change Your Account Password
            </h3>
            <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
              Update your secret login credentials to maintain account security.
            </p>
          </div>
        </div>

        <form method="POST" action="<?php echo STAFF_URL; ?>/settings?tab=security" class="change-password-form" style="padding: 24px;">
          <input type="hidden" name="action" value="change_password">

          <div class="password-form-inner" style="max-width: 520px; display: flex; flex-direction: column; gap: 18px;">
            
            <!-- Current Password -->
            <div class="form-group">
              <label class="input-field-label">Current Password <span class="req">*</span></label>
              <div class="password-input-wrap">
                <input type="password" name="current_password" id="current_pass" required class="settings-input" placeholder="Enter current login password">
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('current_pass', this)">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
            </div>

            <!-- New Password -->
            <div class="form-group">
              <label class="input-field-label">New Password <span class="req">*</span></label>
              <div class="password-input-wrap">
                <input type="password" name="new_password" id="new_pass" required class="settings-input" placeholder="Minimum 6 characters" oninput="checkStaffPasswordStrength(this.value)">
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('new_pass', this)">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <div class="strength-meter-bar">
                <div id="strengthFill" class="strength-meter-fill"></div>
              </div>
              <div id="strengthLabel" style="font-size: 0.72rem; font-weight: 700; color: #94a3b8; margin-top: 4px;">
                Password strength
              </div>
            </div>

            <!-- Confirm New Password -->
            <div class="form-group">
              <label class="input-field-label">Confirm New Password <span class="req">*</span></label>
              <div class="password-input-wrap">
                <input type="password" name="confirm_password" id="confirm_pass" required class="settings-input" placeholder="Re-type new password" oninput="checkStaffPasswordMatch()">
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirm_pass', this)">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <div id="matchFeedback" style="font-size: 0.74rem; font-weight: 700; margin-top: 4px; display: none;"></div>
            </div>

            <div style="padding-top: 10px;">
              <button type="submit" class="roaster-add-btn password-submit-btn" style="padding: 11px 26px; font-size: 0.88rem;">
                <div class="btn-icon-circle"><i class="fa-solid fa-lock"></i></div>
                <span>Update Password</span>
              </button>
            </div>

          </div>

        </form>
      </div>
    </div>

  </div>

</div>

<script>
// Tab Switching
function switchStaffTab(tabId) {
  document.querySelectorAll('.settings-tab-pill').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.settings-tab-pane').forEach(pane => pane.classList.remove('active'));

  const targetPane = document.getElementById('pane-' + tabId);
  if (targetPane) targetPane.classList.add('active');

  const currentBtn = Array.from(document.querySelectorAll('.settings-tab-pill')).find(b => b.textContent.toLowerCase().includes(tabId));
  if (currentBtn) currentBtn.classList.add('active');

  // Push state to URL without reloading
  const newUrl = new URL(window.location);
  newUrl.searchParams.set('tab', tabId);
  window.history.replaceState({}, '', newUrl);
}

// Appearance Selector in Staff Portal
function selectStaffTheme(theme) {
  var lightCard = document.getElementById('themeCardLight');
  var darkCard = document.getElementById('themeCardDark');
  if (lightCard && darkCard) {
    if (theme === 'dark') {
      lightCard.classList.remove('active');
      darkCard.classList.add('active');
    } else {
      darkCard.classList.remove('active');
      lightCard.classList.add('active');
    }
  }

  if (typeof switchAppTheme === 'function') {
    switchAppTheme(theme);
  } else {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('hrms_theme', theme); } catch(e) {}
  }

  fetch('<?php echo STAFF_URL; ?>/settings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=save_theme&theme=' + encodeURIComponent(theme)
  }).then(function(res) { return res.json(); })
    .then(function(data) {
      var msg = document.getElementById('themeSavedMessage');
      if (msg) {
        msg.style.opacity = '1';
        setTimeout(function() { msg.style.opacity = '0'; }, 2200);
      }
    }).catch(function(err) {});
}

// Password Field Eye Toggle
function togglePasswordVisibility(fieldId, btn) {
  const field = document.getElementById(fieldId);
  if (!field) return;
  const isPass = field.getAttribute('type') === 'password';
  field.setAttribute('type', isPass ? 'text' : 'password');
  const icon = btn.querySelector('i');
  if (icon) {
    icon.className = isPass ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
  }
}

// Password Strength Meter
function checkStaffPasswordStrength(val) {
  const fill = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  if (!fill || !label) return;

  if (val.length === 0) {
    fill.style.width = '0%';
    label.textContent = 'Password strength';
    label.style.color = '#94a3b8';
    return;
  }

  let score = 0;
  if (val.length >= 6) score += 25;
  if (val.length >= 8) score += 25;
  if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score += 25;
  if (/[0-9]/.test(val) || /[^A-Za-z0-9]/.test(val)) score += 25;

  fill.style.width = score + '%';
  if (score <= 25) {
    fill.style.backgroundColor = '#ef4444';
    label.textContent = 'Weak password';
    label.style.color = '#ef4444';
  } else if (score <= 50) {
    fill.style.backgroundColor = '#f59e0b';
    label.textContent = 'Medium password';
    label.style.color = '#f59e0b';
  } else if (score <= 75) {
    fill.style.backgroundColor = '#0284c7';
    label.textContent = 'Good password';
    label.style.color = '#0284c7';
  } else {
    fill.style.backgroundColor = '#10b981';
    label.textContent = 'Strong password';
    label.style.color = '#10b981';
  }
}

// Password Match Check
function checkStaffPasswordMatch() {
  const newPass = document.getElementById('new_pass');
  const confPass = document.getElementById('confirm_pass');
  const feedback = document.getElementById('matchFeedback');
  if (!newPass || !confPass || !feedback) return;

  if (confPass.value.length === 0) {
    feedback.style.display = 'none';
    return;
  }

  feedback.style.display = 'block';
  if (newPass.value === confPass.value) {
    feedback.style.color = '#10b981';
    feedback.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match!';
  } else {
    feedback.style.color = '#ef4444';
    feedback.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Passwords do not match yet.';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
