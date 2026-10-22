<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin']);
$page_title = 'Settings | KSV Academy HRMS';
$page_heading = 'Settings';
$page_subheading = 'Academy Profile, General Configuration & Academic Sessions Management';
$active_menu = 'school-settings';

$db = get_db_connection();
$active_session = get_active_session();
$current_tab = $_GET['tab'] ?? 'general';
if (!in_array($current_tab, ['general', 'sessions', 'security'])) {
    $current_tab = 'general';
}

// ── Handle Actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Save General Settings
    if ($action === 'save_settings') {
        $fields = [
            'school_name', 'tagline', 'affiliation', 'phone', 'email',
            'address', 'website', 'currency_symbol', 'principal_name',
            'logo_url', 'stamp_text', 'sms_alerts'
        ];

        $stmt = $db->prepare("INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            $stmt->execute([$f, $val]);
        }

        set_flash('success', 'General settings and academy profile updated successfully!');
        header('Location: ' . ADMIN_URL . '/school-settings?tab=general');
        exit;
    }

    // 2. Add New Academic Session
    if ($action === 'add_session') {
        $session_name = trim($_POST['session_name'] ?? '');
        $start_date = $_POST['start_date'] ?? date('Y-04-01');
        $end_date = $_POST['end_date'] ?? date('Y-03-31', strtotime('+1 year'));
        $is_current = !empty($_POST['is_current']) ? 1 : 0;

        if (!empty($session_name)) {
            try {
                if ($is_current) {
                    $db->exec("UPDATE academic_sessions SET is_current = 0");
                }
                $stmt = $db->prepare("INSERT INTO academic_sessions (session_name, start_date, end_date, is_current, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$session_name, $start_date, $end_date, $is_current]);
                $new_id = $db->lastInsertId();

                if ($is_current) {
                    $_SESSION['active_session_id'] = $new_id;
                }

                set_flash('success', "Academic session '{$session_name}' created successfully!");
                header('Location: ' . ADMIN_URL . '/school-settings?tab=sessions');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Error creating session: ' . $e->getMessage());
                header('Location: ' . ADMIN_URL . '/school-settings?tab=sessions');
                exit;
            }
        }
    }

    // 3. Set As Default Current Session
    if ($action === 'set_current') {
        $sess_id = intval($_POST['session_id'] ?? 0);
        if ($sess_id > 0) {
            $db->exec("UPDATE academic_sessions SET is_current = 0");
            $stmt = $db->prepare("UPDATE academic_sessions SET is_current = 1 WHERE id = ?");
            $stmt->execute([$sess_id]);
            $_SESSION['active_session_id'] = $sess_id;
            set_flash('success', 'Default active academic session updated!');
            header('Location: ' . ADMIN_URL . '/school-settings?tab=sessions');
            exit;
        }
    }

    // 4. Change Administrator Password
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

        header('Location: ' . ADMIN_URL . '/school-settings?tab=security');
        exit;
    }

    // 5. Reset Other User Account Password
    if ($action === 'reset_user_password') {
        $target_user_id = intval($_POST['target_user_id'] ?? 0);
        $target_new_pass = trim($_POST['target_new_password'] ?? '');

        if ($target_user_id <= 0 || empty($target_new_pass)) {
            set_flash('error', 'Please select a user and enter a new password.');
        } elseif (strlen($target_new_pass) < 6) {
            set_flash('error', 'Password must be at least 6 characters long.');
        } else {
            $u_stmt = $db->prepare("SELECT id, username, full_name FROM users WHERE id = ? LIMIT 1");
            $u_stmt->execute([$target_user_id]);
            $target_u = $u_stmt->fetch();

            if ($target_u) {
                $hashed = password_hash($target_new_pass, PASSWORD_DEFAULT);
                $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->execute([$hashed, $target_user_id]);
                set_flash('success', "Password for '{$target_u['full_name']} (@{$target_u['username']})' reset successfully!");
            } else {
                set_flash('error', 'User account not found.');
            }
        }

        header('Location: ' . ADMIN_URL . '/school-settings?tab=security');
        exit;
    }
}

// ── Fetch Data ───────────────────────────────────────────────────────────────
$school_settings = get_school_settings();

// Fetch all system users for security account management
$all_system_users = $db->query("SELECT id, username, full_name, role, status FROM users ORDER BY role ASC, full_name ASC")->fetchAll();

// Sessions Pagination Setup (10, 20, 30, 40, 50) - Only for sessions tab
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Total count of academic sessions
$total_sessions_count = (int)$db->query("SELECT COUNT(*) FROM academic_sessions")->fetchColumn();
$total_pages = max(1, ceil($total_sessions_count / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_sessions_count > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_sessions_count);

// Fetch paginated sessions with statistics
$sessions_stmt = $db->prepare("
    SELECT s.*,
        (SELECT COUNT(*) FROM students WHERE session_id = s.id) as student_count,
        (SELECT COUNT(*) FROM staff_profiles WHERE session_id = s.id) as staff_count,
        (SELECT COALESCE(SUM(amount), 0) FROM fee_payments WHERE session_id = s.id) as total_fees_collected,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE session_id = s.id) as total_expenses
    FROM academic_sessions s
    ORDER BY s.id DESC
    LIMIT ? OFFSET ?
");
$sessions_stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
$sessions_stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
$sessions_stmt->execute();
$sessions_list = $sessions_stmt->fetchAll();

// Fetch active session stats for the top banner
$active_stat_stmt = $db->prepare("
    SELECT s.*,
        (SELECT COUNT(*) FROM students WHERE session_id = s.id) as student_count,
        (SELECT COUNT(*) FROM staff_profiles WHERE session_id = s.id) as staff_count,
        (SELECT COALESCE(SUM(amount), 0) FROM fee_payments WHERE session_id = s.id) as total_fees_collected,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE session_id = s.id) as total_expenses
    FROM academic_sessions s
    WHERE s.id = ?
    LIMIT 1
");
$active_stat_stmt->execute([$active_session['id']]);
$active_session_stats = $active_stat_stmt->fetch() ?: [
    'student_count' => 0,
    'staff_count' => 0,
    'total_fees_collected' => 0,
    'total_expenses' => 0
];

// Helper to build sessions pagination URL
function get_sessions_pagination_url($target_page, $limit) {
    $params = ['tab' => 'sessions'];
    if ($limit !== 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return ADMIN_URL . '/school-settings?' . http_build_query($params);
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Settings Module Styles ── */
.settings-tab-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  border-radius: 9px;
  font-size: 0.84rem;
  font-weight: 700;
  font-family: inherit;
  color: #64748b;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  text-decoration: none;
}
.settings-tab-pill:hover {
  color: #0f172a;
  background: #f1f5f9;
}
.settings-tab-pill.active {
  color: #ffffff;
  background: linear-gradient(135deg, #0092df 0%, #0066aa 100%);
  box-shadow: 0 4px 12px rgba(0, 146, 223, 0.3);
}
.settings-tab-pill .tab-badge {
  font-size: 0.72rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}
.settings-tab-pill:not(.active) .tab-badge {
  background: #e2e8f0;
  color: #475569;
}

/* Tab Panes */
.settings-tab-pane {
  display: none;
}
.settings-tab-pane.active {
  display: block;
  animation: fadeIn 0.25s ease forwards;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ── Live Academy Preview Card ── */
.academy-preview-card {
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
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
  height: 6px;
  background: linear-gradient(90deg, #0092df, #005a9c);
}
.academy-avatar-wrapper {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  margin: 10px auto 16px;
  position: relative;
  box-shadow: 0 8px 24px rgba(0, 146, 223, 0.2);
  border: 3px solid #ffffff;
  background: #ffffff;
}
.academy-avatar-img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  display: block;
}
.academy-meta-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.82rem;
}
.academy-meta-row:last-child {
  border-bottom: none;
}
.academy-meta-icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  background: #f1f5f9;
  color: #0284c7;
  flex-shrink: 0;
}
.academy-meta-label {
  display: block;
  font-size: 0.68rem;
  color: #64748b;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 2px;
}
.academy-meta-val {
  display: block;
  font-size: 0.82rem;
  color: #0f172a;
  font-weight: 700;
}

/* ── Grouped Form Card ── */
.settings-group-card {
  background: #ffffff;
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
  border-bottom: 1px solid #f1f5f9;
  margin-bottom: 18px;
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

.input-field-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 6px;
}
.input-field-label .req {
  color: #ef4444;
}

.settings-input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.86rem;
  font-family: inherit;
  color: #0f172a;
  background: #ffffff;
  transition: all 0.2s ease;
  outline: none;
}
.settings-input:focus {
  border-color: #0092df;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.15);
}

/* ── Sessions Executive Hero Banner ── */
.active-session-hero {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0369a1 100%);
  color: #ffffff;
  border-radius: 16px;
  padding: 24px 28px;
  margin-bottom: 24px;
  box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25), 0 8px 10px -6px rgba(15, 23, 42, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
  position: relative;
  overflow: hidden;
}
.active-session-hero::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}
.session-scope-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  background: rgba(56, 189, 248, 0.18);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.35);
  padding: 4px 11px;
  border-radius: 20px;
  margin-bottom: 10px;
}
.session-hero-stats {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.session-stat-chip {
  background: rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 12px;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 140px;
}
.session-stat-chip-icon {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
}
.session-stat-chip-icon.students { background: rgba(56, 189, 248, 0.2); color: #38bdf8; }
.session-stat-chip-icon.staff { background: rgba(168, 85, 247, 0.2); color: #c084fc; }
.session-stat-chip-icon.fees { background: rgba(52, 211, 153, 0.2); color: #34d399; }
.session-stat-chip-icon.expenses { background: rgba(248, 113, 113, 0.2); color: #f87171; }
.session-stat-chip-val {
  font-size: 1.08rem;
  font-weight: 800;
  color: #ffffff;
  font-family: 'Outfit', sans-serif;
  line-height: 1.1;
}
.session-stat-chip-lbl {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  margin-top: 2px;
}

/* ── Table Badges and Chips ── */
.session-name-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}
.session-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  background: rgba(0, 146, 223, 0.1);
  color: #0092df;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}
.session-icon-box.active {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}
.session-title-text {
  font-weight: 800;
  font-size: 0.95rem;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
  display: flex;
  align-items: center;
  gap: 8px;
}
.badge-default-star {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
  font-size: 0.68rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.session-date-range {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: #475569;
  font-weight: 600;
  white-space: nowrap;
}
.session-metric-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.78rem;
  font-weight: 800;
  padding: 4px 10px;
  border-radius: 7px;
  letter-spacing: -0.01em;
}
.session-metric-chip.blue {
  background: #e0f2fe;
  color: #0369a1;
  border: 1px solid #bae6fd;
}
.session-metric-chip.purple {
  background: #f3e8ff;
  color: #7e22ce;
  border: 1px solid #e9d5ff;
}
.session-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 11px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 800;
}
.session-status-badge.active {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}
.session-status-badge.inactive {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}
.session-status-badge .pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.25);
  display: inline-block;
  animation: pulseSession 2s infinite;
}
@keyframes pulseSession {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(34, 197, 94, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}
.session-active-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  font-weight: 800;
  color: #059669;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  padding: 5px 12px;
  border-radius: 8px;
}
.action-btn-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 7px;
  font-size: 0.78rem;
  font-weight: 700;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}
.action-btn-pill.primary {
  background: #0092df;
  color: #ffffff;
}
.action-btn-pill.primary:hover {
  background: #007cbd;
  box-shadow: 0 3px 10px rgba(0, 146, 223, 0.3);
}
.action-btn-pill.secondary {
  background: #ffffff;
  color: #475569;
  border: 1px solid #cbd5e1;
}
.action-btn-pill.secondary:hover {
  background: #0f172a;
  color: #ffffff;
  border-color: #0f172a;
}

/* ── Security & Password Styles ── */
.password-input-group {
  position: relative;
  display: flex;
  align-items: center;
}
.password-input-group input {
  padding-right: 44px !important;
}
.password-toggle-btn {
  position: absolute;
  right: 8px;
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 8px;
  font-size: 0.95rem;
  transition: color 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
}
.password-toggle-btn:hover {
  color: #0092df;
}
.pwd-strength-container {
  margin-top: 8px;
}
.pwd-strength-bar {
  height: 5px;
  border-radius: 4px;
  background: #e2e8f0;
  overflow: hidden;
  position: relative;
}
.pwd-strength-fill {
  height: 100%;
  width: 0%;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border-radius: 4px;
}
.pwd-strength-text {
  font-size: 0.72rem;
  font-weight: 700;
  margin-top: 5px;
  display: flex;
  justify-content: space-between;
}
</style>


<!-- ══════════════════════════════════════════════════════
     ROASTER TOOLBAR CARD & TAB NAVIGATOR
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-bottom: 24px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Segmented Tab Buttons -->
    <div style="display: flex; gap: 8px; align-items: center; background: #f1f5f9; padding: 4px; border-radius: 11px;">
      
      <button type="button" class="settings-tab-pill <?php echo $current_tab === 'general' ? 'active' : ''; ?>" onclick="switchSettingsTab('general')">
        <i class="fa-solid fa-sliders"></i>
        <span>General Profile &amp; Branding</span>
      </button>

      <button type="button" class="settings-tab-pill <?php echo $current_tab === 'sessions' ? 'active' : ''; ?>" onclick="switchSettingsTab('sessions')">
        <i class="fa-solid fa-timeline"></i>
        <span>Academic Sessions</span>
        <span class="tab-badge"><?php echo $total_sessions_count; ?></span>
      </button>

      <button type="button" class="settings-tab-pill <?php echo $current_tab === 'security' ? 'active' : ''; ?>" onclick="switchSettingsTab('security')">
        <i class="fa-solid fa-shield-halved"></i>
        <span>Security</span>
      </button>

    </div>

    <!-- Right: Context Action -->
    <div class="roaster-toolbar-actions">
      <div class="scholars-count-badge" id="tabStatusBadge">
        <?php if ($current_tab === 'sessions'): ?>
          <span class="count-dot" style="background: #10b981;"></span>
          <span>Academic Calendar Mode</span>
        <?php elseif ($current_tab === 'security'): ?>
          <span class="count-dot" style="background: #6366f1;"></span>
          <span>Security &amp; Credentials Mode</span>
        <?php else: ?>
          <span class="count-dot" style="background: #0092df;"></span>
          <span>General Profile Mode</span>
        <?php endif; ?>
      </div>

      <div id="generalActions" style="<?php echo $current_tab === 'general' ? '' : 'display: none;'; ?>">
        <button type="button" onclick="document.getElementById('generalSettingsForm').submit();" class="roaster-add-btn" style="padding: 7px 18px; font-size: 0.82rem;" title="Save School Settings">
          <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-floppy-disk"></i></div>
          <span>Save Changes</span>
        </button>
      </div>

      <div id="sessionActions" style="<?php echo $current_tab === 'sessions' ? '' : 'display: none;'; ?>">
        <button type="button" onclick="openNewSessionModal();" class="roaster-add-btn" style="padding: 7px 18px; font-size: 0.82rem;" title="Create New Academic Session">
          <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-plus"></i></div>
          <span>Create Session</span>
        </button>
      </div>

      <div id="securityActions" style="<?php echo $current_tab === 'security' ? '' : 'display: none;'; ?>">
        <button type="button" onclick="document.getElementById('changePasswordForm').submit();" class="roaster-add-btn" style="padding: 7px 18px; font-size: 0.82rem;" title="Update Password">
          <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-key"></i></div>
          <span>Update Password</span>
        </button>
      </div>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     TAB 1: GENERAL SETTINGS & ACADEMY PROFILE
     ══════════════════════════════════════════════════════ -->
<div id="pane-general" class="settings-tab-pane <?php echo $current_tab === 'general' ? 'active' : ''; ?>">
  
  <div style="display: grid; grid-template-columns: 340px 1fr; gap: 24px; align-items: start; margin-bottom: 30px;">
    
    <!-- Left Column: Live Academy Identity Preview Card -->
    <div class="academy-preview-card">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 0.72rem; font-weight: 800; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 12px; border: 1px solid #bae6fd;">
          <i class="fa-solid fa-eye"></i> Live Identity Preview
        </span>
        <span style="font-size: 0.72rem; color: #64748b; font-weight: 700;">Verified Seal</span>
      </div>

      <!-- Avatar with Live Preview -->
      <div class="academy-avatar-wrapper">
        <img id="previewLogo" src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="Academy Logo" class="academy-avatar-img">
      </div>

      <div style="text-align: center; margin-bottom: 16px;">
        <h3 id="previewName" style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <?php echo htmlspecialchars($school_settings['school_name'] ?? 'KSV Academy'); ?>
        </h3>
        <p id="previewTagline" style="margin: 4px 0 0; font-size: 0.78rem; color: #64748b; font-style: italic;">
          &ldquo;<?php echo htmlspecialchars($school_settings['tagline'] ?? 'Where Learning Meets Excellence'); ?>&rdquo;
        </p>
        <div style="margin-top: 8px;">
          <span id="previewAffiliation" style="font-size: 0.72rem; font-weight: 800; color: #059669; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 2px 10px; border-radius: 20px; display: inline-block;">
            <i class="fa-solid fa-award"></i> <?php echo htmlspecialchars($school_settings['affiliation'] ?? 'Affiliated Institution'); ?>
          </span>
        </div>
      </div>

      <!-- Metadata List -->
      <div style="background: #ffffff; border-radius: 10px; padding: 12px 14px; border: 1px solid #e2e8f0; margin-bottom: 16px;">
        
        <div class="academy-meta-row">
          <div class="academy-meta-icon"><i class="fa-solid fa-user-tie"></i></div>
          <div style="flex: 1; min-width: 0;">
            <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 700;">PRINCIPAL / DIRECTOR</div>
            <div id="previewPrincipal" style="font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              <?php echo htmlspecialchars($school_settings['principal_name'] ?? 'Not Specified'); ?>
            </div>
          </div>
        </div>

        <div class="academy-meta-row">
          <div class="academy-meta-icon"><i class="fa-solid fa-phone"></i></div>
          <div style="flex: 1; min-width: 0;">
            <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 700;">CONTACT HOTLINE</div>
            <div id="previewPhone" style="font-weight: 700; color: #334155;">
              <?php echo htmlspecialchars($school_settings['phone'] ?? '—'); ?>
            </div>
          </div>
        </div>

        <div class="academy-meta-row">
          <div class="academy-meta-icon"><i class="fa-solid fa-envelope"></i></div>
          <div style="flex: 1; min-width: 0;">
            <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 700;">EMAIL DISPATCH</div>
            <div id="previewEmail" style="font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              <?php echo htmlspecialchars($school_settings['email'] ?? '—'); ?>
            </div>
          </div>
        </div>

        <div class="academy-meta-row">
          <div class="academy-meta-icon"><i class="fa-solid fa-location-dot"></i></div>
          <div style="flex: 1; min-width: 0;">
            <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 700;">CAMPUS ADDRESS</div>
            <div id="previewAddress" style="font-weight: 600; color: #475569; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
              <?php echo htmlspecialchars($school_settings['address'] ?? '—'); ?>
            </div>
          </div>
        </div>

      </div>

      <div style="font-size: 0.74rem; color: #64748b; background: rgba(0, 146, 223, 0.06); padding: 10px 12px; border-radius: 8px; border: 1px solid rgba(0, 146, 223, 0.15); line-height: 1.4;">
        <i class="fa-solid fa-info-circle" style="color: #0092df;"></i>
        These details automatically imprint across student ID cards, report cards, fee receipts, and faculty payslips.
      </div>
    </div>

    <!-- Right Column: Settings Form Groups -->
    <div>
      <form id="generalSettingsForm" method="POST" action="<?php echo ADMIN_URL; ?>/school-settings">
        <input type="hidden" name="action" value="save_settings">

        <!-- Group 1: Institution Identity & Authority -->
        <div class="settings-group-card">
          <div class="form-section-header">
            <div class="form-section-icon" style="background: rgba(0, 146, 223, 0.1); color: #0092df;">
              <i class="fa-solid fa-school"></i>
            </div>
            <div>
              <h4 style="margin: 0; font-size: 0.98rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
                Institution Identity &amp; Accreditation
              </h4>
              <p style="margin: 2px 0 0; font-size: 0.76rem; color: #64748b;">
                Primary school titles, board recognition, and leadership authority.
              </p>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div class="form-group">
              <label class="input-field-label">School / Academy Name <span class="req">*</span></label>
              <input type="text" name="school_name" id="input_school_name" value="<?php echo htmlspecialchars($school_settings['school_name']); ?>" required class="settings-input" placeholder="e.g. KSV Academy" oninput="syncPreview('previewName', this.value, 'KSV Academy')">
            </div>

            <div class="form-group">
              <label class="input-field-label">Motto / Tagline</label>
              <input type="text" name="tagline" id="input_tagline" value="<?php echo htmlspecialchars($school_settings['tagline']); ?>" class="settings-input" placeholder="e.g. Where Learning Meets Excellence" oninput="syncPreviewTagline(this.value)">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div class="form-group">
              <label class="input-field-label">Board Affiliation &amp; Reg No.</label>
              <input type="text" name="affiliation" id="input_affiliation" value="<?php echo htmlspecialchars($school_settings['affiliation']); ?>" class="settings-input" placeholder="e.g. CBSE Affiliated - Reg #KSV-ACAD-2024" oninput="syncPreviewAffiliation(this.value)">
            </div>

            <div class="form-group">
              <label class="input-field-label">Principal / Director Name</label>
              <input type="text" name="principal_name" id="input_principal_name" value="<?php echo htmlspecialchars($school_settings['principal_name']); ?>" class="settings-input" placeholder="e.g. Mr. Salam Khan (M.Sc, B.Ed)" oninput="syncPreview('previewPrincipal', this.value, 'Not Specified')">
            </div>
          </div>

          <div class="form-group">
            <label class="input-field-label">Campus Physical Address</label>
            <input type="text" name="address" id="input_address" value="<?php echo htmlspecialchars($school_settings['address']); ?>" class="settings-input" placeholder="Full street address, city, state, postal code" oninput="syncPreview('previewAddress', this.value, '—')">
          </div>
        </div>

        <!-- Group 2: Public Contact & Dispatch Channels -->
        <div class="settings-group-card">
          <div class="form-section-header">
            <div class="form-section-icon" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
              <i class="fa-solid fa-address-book"></i>
            </div>
            <div>
              <h4 style="margin: 0; font-size: 0.98rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
                Contact &amp; Public Channels
              </h4>
              <p style="margin: 2px 0 0; font-size: 0.76rem; color: #64748b;">
                Official telephone lines, email dispatches, and online portal URL.
              </p>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div class="form-group">
              <label class="input-field-label">Contact Phone</label>
              <input type="text" name="phone" id="input_phone" value="<?php echo htmlspecialchars($school_settings['phone']); ?>" class="settings-input" placeholder="+91 98765 43210" oninput="syncPreview('previewPhone', this.value, '—')">
            </div>

            <div class="form-group">
              <label class="input-field-label">Official Email Address</label>
              <input type="email" name="email" id="input_email" value="<?php echo htmlspecialchars($school_settings['email']); ?>" class="settings-input" placeholder="admissions@ksvacademy.com" oninput="syncPreview('previewEmail', this.value, '—')">
            </div>

            <div class="form-group">
              <label class="input-field-label">Official Website URL</label>
              <input type="text" name="website" value="<?php echo htmlspecialchars($school_settings['website']); ?>" class="settings-input" placeholder="http://localhost:8000">
            </div>
          </div>
        </div>

        <!-- Group 3: Branding & Formatting Localizations -->
        <div class="settings-group-card">
          <div class="form-section-header">
            <div class="form-section-icon" style="background: rgba(124, 58, 237, 0.1); color: #7c3aed;">
              <i class="fa-solid fa-palette"></i>
            </div>
            <div>
              <h4 style="margin: 0; font-size: 0.98rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
                Branding &amp; Financial Localization
              </h4>
              <p style="margin: 2px 0 0; font-size: 0.76rem; color: #64748b;">
                Visual crest image path, currency symbol, and official stamp imprints.
              </p>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
            <div class="form-group">
              <label class="input-field-label">School Logo Path / URL</label>
              <input type="text" name="logo_url" id="input_logo_url" value="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" class="settings-input" placeholder="/assets/imgs/ksv-logo.jpg" oninput="syncLogoPreview(this.value)">
            </div>

            <div class="form-group">
              <label class="input-field-label">Currency Symbol</label>
              <input type="text" name="currency_symbol" value="<?php echo htmlspecialchars($school_settings['currency_symbol'] ?? '₹'); ?>" class="settings-input" placeholder="₹">
            </div>
          </div>
        </div>

        <!-- Submit Button Footer -->
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px;">
          <button type="submit" class="roaster-add-btn" style="padding: 10px 24px; font-size: 0.88rem;">
            <div class="btn-icon-circle"><i class="fa-solid fa-floppy-disk"></i></div>
            <span>Save General Settings</span>
          </button>
        </div>

      </form>
    </div>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     TAB 2: ACADEMIC SESSIONS MANAGEMENT
     ══════════════════════════════════════════════════════ -->
<div id="pane-sessions" class="settings-tab-pane <?php echo $current_tab === 'sessions' ? 'active' : ''; ?>">
  
  <!-- Executive Working Session Hero Card -->
  <div class="active-session-hero">
    <div style="z-index: 1;">
      <div class="session-scope-badge">
        <i class="fa-solid fa-circle-check"></i> Currently Active Working Session
      </div>
      <h2 style="font-size: 1.85rem; font-weight: 800; margin: 0 0 6px 0; color: #ffffff; font-family: 'Outfit', sans-serif; letter-spacing: -0.02em;">
        Academic Year <?php echo htmlspecialchars($active_session['session_name']); ?>
      </h2>
      <p style="margin: 0; font-size: 0.85rem; max-width: 580px; line-height: 1.5; color: #cbd5e1;">
        <i class="fa-regular fa-calendar-check" style="color: #38bdf8; margin-right: 5px;"></i>
        <?php echo date('d M Y', strtotime($active_session['start_date'])); ?> &ndash; <?php echo date('d M Y', strtotime($active_session['end_date'])); ?>
        &nbsp;&bull;&nbsp; Operational context for student admissions, fee ledgers, payroll dispatches, and attendance logs.
      </p>
    </div>

  </div>

  <!-- Sessions Directory Table Card -->
  <div class="hrms-card" style="margin-bottom: 30px; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 18px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
    
    <div class="hrms-card-header" style="border-bottom: 1px solid var(--hrms-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 18px 24px; background: #ffffff;">
      <div>
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-timeline" style="color: var(--hrms-primary);"></i>
          Academic Sessions Directory
        </h3>
        <p style="margin: 4px 0 0; font-size: 0.8rem; color: #64748b;">
          Switch active administrative context to inspect historical student profiles, salary archives, and previous audit balance sheets.
        </p>
      </div>

      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.78rem; font-weight: 800; color: #475569; background: #f8fafc; padding: 6px 14px; border-radius: 20px; border: 1px solid #e2e8f0;">
          <i class="fa-solid fa-layer-group" style="color: #0092df;"></i>
          <?php echo $total_sessions_count; ?> Academic Periods
        </span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="hrms-table">
        <thead>
          <tr>
            <th style="width: 250px;">Session Name</th>
            <th>Duration</th>
            <th style="text-align: center;">Students</th>
            <th style="text-align: center;">Staff</th>
            <th>Fee Collections</th>
            <th>Expenses</th>
            <th style="text-align: center;">Status</th>
            <th style="text-align: right; width: 220px;">Switch &amp; Manage</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sessions_list as $sess): ?>
            <?php $is_active = ($sess['id'] == $active_session['id']); ?>
            <tr style="<?php echo $is_active ? 'background: rgba(0, 146, 223, 0.04);' : ''; ?>">
              
              <!-- Session Name & Default Pill -->
              <td>
                <div class="session-name-cell">
                  <div class="session-icon-box <?php echo $is_active ? 'active' : ''; ?>">
                    <i class="fa-regular fa-calendar-check"></i>
                  </div>
                  <div>
                    <div class="session-title-text">
                      <span><?php echo htmlspecialchars($sess['session_name']); ?></span>
                      <?php if ($sess['is_current']): ?>
                        <span class="badge-default-star"><i class="fa-solid fa-star"></i> Default</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Duration -->
              <td>
                <div class="session-date-range">
                  <i class="fa-regular fa-clock" style="color: #94a3b8;"></i>
                  <span><?php echo date('d M Y', strtotime($sess['start_date'])); ?> &ndash; <?php echo date('d M Y', strtotime($sess['end_date'])); ?></span>
                </div>
              </td>

              <!-- Students -->
              <td style="text-align: center;">
                <span class="session-metric-chip blue">
                  <i class="fa-solid fa-user-graduate"></i> <?php echo $sess['student_count']; ?>
                </span>
              </td>

              <!-- Staff -->
              <td style="text-align: center;">
                <span class="session-metric-chip purple">
                  <i class="fa-solid fa-chalkboard-user"></i> <?php echo $sess['staff_count']; ?>
                </span>
              </td>

              <!-- Fees -->
              <td>
                <strong style="color: #059669; font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                  <?php echo format_inr($sess['total_fees_collected']); ?>
                </strong>
              </td>

              <!-- Expenses -->
              <td>
                <strong style="color: #dc2626; font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                  <?php echo format_inr($sess['total_expenses']); ?>
                </strong>
              </td>

              <!-- Status Badge -->
              <td style="text-align: center;">
                <?php if ($is_active): ?>
                  <span class="session-status-badge active">
                    <span class="pulse-dot"></span> Active Now
                  </span>
                <?php else: ?>
                  <span class="session-status-badge inactive">
                    Archived
                  </span>
                <?php endif; ?>
              </td>

              <!-- Switch & Manage Actions -->
              <td style="text-align: right;">
                <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                  
                  <?php if ($is_active): ?>
                    <span class="session-active-indicator">
                      <i class="fa-solid fa-circle-check"></i> Selected
                    </span>
                  <?php else: ?>
                    <a href="?switch_session=<?php echo $sess['id']; ?>" class="action-btn-pill primary" title="Switch active working session to <?php echo htmlspecialchars($sess['session_name']); ?>">
                      <i class="fa-solid fa-repeat"></i>
                      <span>Switch</span>
                    </a>
                  <?php endif; ?>

                  <?php if (!$sess['is_current']): ?>
                    <form method="POST" action="<?php echo ADMIN_URL; ?>/school-settings" style="display: inline;" onsubmit="return confirm('Make session \'<?php echo addslashes($sess['session_name']); ?>\' the system default starting year?');">
                      <input type="hidden" name="action" value="set_current">
                      <input type="hidden" name="session_id" value="<?php echo $sess['id']; ?>">
                      <button type="submit" class="action-btn-pill secondary" title="Set as default start session">
                        <i class="fa-regular fa-star"></i> Default
                      </button>
                    </form>
                  <?php endif; ?>

                </div>
              </td>

            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Table Footer Pagination & Per-Page Controls (Matches HRMS standard) -->
    <div class="pagination-controls-wrap">
      
      <!-- Left: Showing summary & Limit Selector -->
      <div class="pagination-meta-group">
        <span style="font-size: 0.82rem; color: #475569;">
          <?php if ($total_sessions_count > 0): ?>
            Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_sessions_count; ?></strong> <?php echo $total_sessions_count === 1 ? 'session' : 'sessions'; ?>
          <?php else: ?>
            Showing <strong>0</strong> sessions
          <?php endif; ?>
        </span>

        <div class="pagination-limit-box">
          <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
          <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
            <?php foreach ($allowed_limits as $l): ?>
              <option value="<?php echo htmlspecialchars(get_sessions_pagination_url(1, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
                <?php echo $l; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Center/Right: Navigation page buttons -->
      <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav" aria-label="Academic Sessions Table Pagination">
          <!-- Prev Page -->
          <a href="<?php echo $page > 1 ? htmlspecialchars(get_sessions_pagination_url($page - 1, $limit)) : '#'; ?>" 
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
            <a href="<?php echo htmlspecialchars(get_sessions_pagination_url($p, $limit)); ?>" 
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
          <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_sessions_pagination_url($page + 1, $limit)) : '#'; ?>" 
             class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
             aria-label="Next Page"
             title="Next Page">
            <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
          </a>
        </nav>
      <?php endif; ?>

    </div>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     TAB 3: SECURITY & PASSWORD CREDENTIALS
     ══════════════════════════════════════════════════════ -->
<div id="pane-security" class="settings-tab-pane <?php echo $current_tab === 'security' ? 'active' : ''; ?>">
  
  <div style="display: grid; grid-template-columns: 340px 1fr; gap: 24px; align-items: start; margin-bottom: 30px;">
    
    <!-- Left Column: Administrator Security Profile Card -->
    <div>
      <div class="academy-preview-card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 0.72rem; font-weight: 800; background: #ede9fe; color: #6d28d9; padding: 2px 10px; border-radius: 12px; border: 1px solid #ddd6fe;">
            <i class="fa-solid fa-user-shield"></i> Security Context
          </span>
          <span style="font-size: 0.72rem; color: #10b981; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
            <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; display: inline-block;"></span> Active
          </span>
        </div>

        <!-- Admin Avatar & Meta -->
        <div style="text-align: center; margin: 18px 0 14px;">
          <div style="width: 76px; height: 76px; border-radius: 50%; background: linear-gradient(135deg, #0092df, #005a9c); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.85rem; font-weight: 800; margin: 0 auto 12px; box-shadow: 0 8px 20px rgba(0, 146, 223, 0.25); border: 3px solid #ffffff;">
            <?php 
              $initials = 'A';
              if (!empty($current_user['full_name'])) {
                $words = explode(' ', trim($current_user['full_name']));
                $initials = strtoupper(substr($words[0], 0, 1));
              }
              echo $initials;
            ?>
          </div>
          <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            <?php echo htmlspecialchars($current_user['full_name'] ?? 'Administrator'); ?>
          </h3>
          <p style="margin: 3px 0 8px; font-size: 0.8rem; color: #64748b; font-family: monospace;">
            @<?php echo htmlspecialchars($current_user['username']); ?>
          </p>
          <span style="font-size: 0.72rem; font-weight: 800; color: #b45309; background: #fef3c7; border: 1px solid #fde68a; padding: 3px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block;">
            <i class="fa-solid fa-crown" style="margin-right: 3px;"></i> Super Admin
          </span>
        </div>

        <!-- Account Info Details -->
        <div style="background: #ffffff; border-radius: 10px; padding: 14px; border: 1px solid #e2e8f0; margin-bottom: 16px;">
          <div class="academy-meta-row" style="padding-top: 0;">
            <div class="academy-meta-icon" style="background: #e0f2fe; color: #0284c7;"><i class="fa-solid fa-envelope"></i></div>
            <div style="min-width: 0; flex: 1;">
              <span class="academy-meta-label">Admin Email</span>
              <span class="academy-meta-val" style="word-break: break-all;"><?php echo htmlspecialchars($current_user['email'] ?? 'admin@ksvacademy.net'); ?></span>
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
              <span class="academy-meta-label">Password Protection</span>
              <span class="academy-meta-val" style="color: #059669; font-weight: 800;"><i class="fa-solid fa-circle-check"></i> Bcrypt Hashed</span>
            </div>
          </div>
        </div>

        <!-- Security Best Practices -->
        <div style="background: #f8fafc; border-radius: 10px; padding: 14px; border: 1px dashed #cbd5e1;">
          <h4 style="margin: 0 0 10px 0; font-size: 0.78rem; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-lightbulb" style="color: #f59e0b;"></i> Password Guidelines
          </h4>
          <ul style="margin: 0; padding-left: 18px; font-size: 0.76rem; color: #64748b; line-height: 1.6;">
            <li>Use at least 6+ (recommended 8+) characters.</li>
            <li>Mix uppercase, lowercase, numbers &amp; symbols.</li>
            <li>Never reuse passwords across personal portals.</li>
            <li>Change master password periodically for security.</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Right Column: Change Password Forms -->
    <div>
      
      <!-- Primary Card: Change Current Admin Password -->
      <div class="hrms-card" style="margin-bottom: 24px; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 18px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
        <div class="hrms-card-header" style="border-bottom: 1px solid var(--hrms-border); padding: 18px 24px; background: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
          <div>
            <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
              <i class="fa-solid fa-key" style="color: #0092df;"></i>
              Change Administrator Password
            </h3>
            <p style="margin: 4px 0 0; font-size: 0.8rem; color: #64748b;">
              Update your account login credentials. Enter your current password for security verification.
            </p>
          </div>
          <span style="font-size: 0.74rem; font-weight: 800; color: #0369a1; background: #e0f2fe; padding: 4px 12px; border-radius: 20px; border: 1px solid #bae6fd;">
            <i class="fa-solid fa-shield"></i> Master Credential
          </span>
        </div>

        <div style="padding: 24px;">
          <form method="POST" action="<?php echo ADMIN_URL; ?>/school-settings?tab=security" id="changePasswordForm">
            <input type="hidden" name="action" value="change_password">

            <!-- Current Password -->
            <div class="form-group" style="margin-bottom: 20px;">
              <label class="input-field-label">
                Current Password <span class="req">*</span>
              </label>
              <div class="password-input-group">
                <input type="password" name="current_password" id="curr_pass" class="settings-input" required placeholder="Enter current master password" autocomplete="current-password">
                <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('curr_pass', this)" title="Show/Hide Password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <span class="input-help-text">Verify your current login password before updating.</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px;">
              <!-- New Password -->
              <div class="form-group">
                <label class="input-field-label">
                  New Password <span class="req">*</span>
                </label>
                <div class="password-input-group">
                  <input type="password" name="new_password" id="new_pass" class="settings-input" required placeholder="Enter new strong password" minlength="6" oninput="evaluatePasswordStrength(this.value)">
                  <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('new_pass', this)" title="Show/Hide Password">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
                <!-- Strength Meter -->
                <div class="pwd-strength-container">
                  <div class="pwd-strength-bar">
                    <div id="pwdStrengthFill" class="pwd-strength-fill"></div>
                  </div>
                  <div class="pwd-strength-text">
                    <span id="pwdStrengthLabel" style="color: #94a3b8;">Minimum 6 characters</span>
                    <span id="pwdStrengthScore" style="color: #94a3b8;">0%</span>
                  </div>
                </div>
              </div>

              <!-- Confirm New Password -->
              <div class="form-group">
                <label class="input-field-label">
                  Confirm New Password <span class="req">*</span>
                </label>
                <div class="password-input-group">
                  <input type="password" name="confirm_password" id="conf_pass" class="settings-input" required placeholder="Retype new password" minlength="6" oninput="checkPasswordMatch()">
                  <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('conf_pass', this)" title="Show/Hide Password">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
                <div id="matchFeedback" style="font-size: 0.74rem; font-weight: 700; margin-top: 8px; display: none;"></div>
              </div>
            </div>

            <!-- Action Footer -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; border-top: 1px solid #f1f5f9; padding-top: 18px;">
              <button type="reset" class="roaster-btn secondary" style="padding: 10px 18px;" onclick="resetStrengthMeter()">
                <i class="fa-solid fa-rotate-left"></i> Reset
              </button>
              <button type="submit" class="roaster-add-btn" style="padding: 10px 24px; font-size: 0.88rem;">
                <div class="btn-icon-circle"><i class="fa-solid fa-key"></i></div>
                <span>Update Password</span>
              </button>
            </div>

          </form>
        </div>
      </div>

      <!-- Secondary Card: Quick Reset for Staff / User Accounts -->
      <div class="hrms-card" style="border-radius: 14px; overflow: hidden; box-shadow: 0 4px 18px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
        <div class="hrms-card-header" style="border-bottom: 1px solid var(--hrms-border); padding: 18px 24px; background: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
          <div>
            <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.02rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
              <i class="fa-solid fa-users-gear" style="color: #6366f1;"></i>
              Staff &amp; Portal Accounts Credential Reset
            </h3>
            <p style="margin: 4px 0 0; font-size: 0.8rem; color: #64748b;">
              Directly reset access passwords for teachers, accountants, or parents if they get locked out.
            </p>
          </div>
          <span style="font-size: 0.74rem; font-weight: 800; color: #4338ca; background: #e0e7ff; padding: 4px 12px; border-radius: 20px; border: 1px solid #c7d2fe;">
            <i class="fa-solid fa-crown"></i> Super Admin Control
          </span>
        </div>

        <div style="padding: 24px;">
          <form method="POST" action="<?php echo ADMIN_URL; ?>/school-settings?tab=security" onsubmit="return confirm('Are you sure you want to reset password for this user account?');">
            <input type="hidden" name="action" value="reset_user_password">

            <div style="display: grid; grid-template-columns: 1.2fr 1fr auto; gap: 16px; align-items: end;">
              
              <div class="form-group" style="margin-bottom: 0;">
                <label class="input-field-label">
                  Select User Account <span class="req">*</span>
                </label>
                <select name="target_user_id" class="settings-input" required>
                  <option value="">-- Choose Account to Reset --</option>
                  <?php foreach ($all_system_users as $usr): ?>
                    <option value="<?php echo $usr['id']; ?>" <?php echo $usr['id'] == $current_user['id'] ? 'disabled' : ''; ?>>
                      <?php echo htmlspecialchars($usr['full_name']); ?> (@<?php echo htmlspecialchars($usr['username']); ?>) &ndash; [<?php echo strtoupper($usr['role']); ?>]
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group" style="margin-bottom: 0;">
                <label class="input-field-label">
                  New Temporary Password <span class="req">*</span>
                </label>
                <div class="password-input-group">
                  <input type="password" name="target_new_password" id="user_new_pass" class="settings-input" required placeholder="Min 6 characters" minlength="6">
                  <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('user_new_pass', this)" title="Show/Hide Password">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
              </div>

              <div>
                <button type="submit" class="roaster-add-btn" style="padding: 10px 20px; font-size: 0.85rem; height: 42px;">
                  <div class="btn-icon-circle"><i class="fa-solid fa-rotate"></i></div>
                  <span>Reset Password</span>
                </button>
              </div>

            </div>

          </form>
        </div>
      </div>

    </div>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: CREATE NEW ACADEMIC SESSION
     ══════════════════════════════════════════════════════ -->
<div id="newSessionModal" class="hrms-modal" onclick="if (event.target === this) closeNewSessionModal();">
  <div class="hrms-modal-card" style="max-width: 480px;">
    
    <div class="hrms-modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 9px; background: rgba(0, 146, 223, 0.12); color: #0092df; display: flex; align-items: center; justify-content: center; font-size: 1.05rem;">
          <i class="fa-solid fa-calendar-plus"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.02rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
            Create Academic Session
          </h3>
          <span style="font-size: 0.74rem; color: #64748b;">Add a new academic year / term calendar</span>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeNewSessionModal();" style="border: none; cursor: pointer; padding: 4px 8px; font-size: 1.2rem;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="hrms-modal-body">
      <form method="POST" action="<?php echo ADMIN_URL; ?>/school-settings">
        <input type="hidden" name="action" value="add_session">

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="input-field-label">
            Session Name <span class="req">*</span>
          </label>
          <input type="text" name="session_name" class="settings-input" required placeholder="YYYY-YYYY e.g. 2026-2027">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px;">
          <div class="form-group">
            <label class="input-field-label">Start Date <span class="req">*</span></label>
            <input type="date" name="start_date" class="settings-input" required value="<?php echo date('Y-04-01', strtotime('+1 year')); ?>">
          </div>
          <div class="form-group">
            <label class="input-field-label">End Date <span class="req">*</span></label>
            <input type="date" name="end_date" class="settings-input" required value="<?php echo date('Y-03-31', strtotime('+2 years')); ?>">
          </div>
        </div>

        <div style="margin-bottom: 22px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
          <label style="display: flex; align-items: center; gap: 10px; font-size: 0.84rem; font-weight: 700; color: #1e293b; cursor: pointer;">
            <input type="checkbox" name="is_current" value="1" style="width: 17px; height: 17px; accent-color: #0092df;">
            <span>Set as Default Current Session immediately</span>
          </label>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
          <button type="button" onclick="closeNewSessionModal();" class="roaster-btn secondary" style="padding: 9px 18px;">
            Cancel
          </button>
          <button type="submit" class="roaster-add-btn" style="padding: 9px 20px;">
            <div class="btn-icon-circle"><i class="fa-solid fa-check"></i></div>
            <span>Save Session</span>
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<script>
function switchSettingsTab(tabName) {
  // Update Buttons
  document.querySelectorAll('.settings-tab-pill').forEach(btn => btn.classList.remove('active'));
  // Update Panes
  document.querySelectorAll('.settings-tab-pane').forEach(pane => pane.classList.remove('active'));

  const targetPane = document.getElementById('pane-' + tabName);
  if (targetPane) {
    targetPane.classList.add('active');
  }

  // Highlight active button
  const buttons = document.querySelectorAll('.settings-tab-pill');
  if (tabName === 'general' && buttons[0]) buttons[0].classList.add('active');
  if (tabName === 'sessions' && buttons[1]) buttons[1].classList.add('active');
  if (tabName === 'security' && buttons[2]) buttons[2].classList.add('active');

  // Toggle toolbar actions
  const genAct = document.getElementById('generalActions');
  const sessAct = document.getElementById('sessionActions');
  const secAct = document.getElementById('securityActions');
  const statusBadge = document.getElementById('tabStatusBadge');

  if (genAct && sessAct && secAct) {
    genAct.style.display = (tabName === 'general') ? 'block' : 'none';
    sessAct.style.display = (tabName === 'sessions') ? 'block' : 'none';
    secAct.style.display = (tabName === 'security') ? 'block' : 'none';

    if (statusBadge) {
      if (tabName === 'general') {
        statusBadge.innerHTML = '<span class="count-dot" style="background: #0092df;"></span><span>General Profile Mode</span>';
      } else if (tabName === 'sessions') {
        statusBadge.innerHTML = '<span class="count-dot" style="background: #10b981;"></span><span>Academic Calendar Mode</span>';
      } else if (tabName === 'security') {
        statusBadge.innerHTML = '<span class="count-dot" style="background: #6366f1;"></span><span>Security &amp; Credentials Mode</span>';
      }
    }
  }

  // Sync URL query without page reload
  const newUrl = new URL(window.location);
  newUrl.searchParams.set('tab', tabName);
  if (tabName !== 'sessions') {
    newUrl.searchParams.delete('page');
    newUrl.searchParams.delete('limit');
  }
  window.history.replaceState({}, '', newUrl);
}

// Live Sync Helpers
function syncPreview(elementId, val, fallback) {
  const el = document.getElementById(elementId);
  if (el) {
    el.innerText = val.trim() !== '' ? val.trim() : fallback;
  }
}

function syncPreviewTagline(val) {
  const el = document.getElementById('previewTagline');
  if (el) {
    el.innerText = val.trim() !== '' ? '“' + val.trim() + '”' : '“Where Learning Meets Excellence”';
  }
}

function syncPreviewAffiliation(val) {
  const el = document.getElementById('previewAffiliation');
  if (el) {
    el.innerHTML = '<i class="fa-solid fa-award"></i> ' + (val.trim() !== '' ? val.trim() : 'Affiliated Institution');
  }
}

function syncLogoPreview(val) {
  const img = document.getElementById('previewLogo');
  if (img && val.trim() !== '') {
    img.src = val.trim();
  }
}

function openNewSessionModal() {
  const modal = document.getElementById('newSessionModal');
  if (modal) {
    modal.classList.add('show');
  }
}
function closeNewSessionModal() {
  const modal = document.getElementById('newSessionModal');
  if (modal) {
    modal.classList.remove('show');
  }
}

// Password helpers
function togglePassVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    if (icon) {
      icon.classList.remove('fa-eye', 'fa-regular');
      icon.classList.add('fa-eye-slash', 'fa-solid');
      icon.style.color = '#0092df';
    }
  } else {
    input.type = 'password';
    if (icon) {
      icon.classList.remove('fa-eye-slash', 'fa-solid');
      icon.classList.add('fa-eye', 'fa-regular');
      icon.style.color = '';
    }
  }
}

function evaluatePasswordStrength(val) {
  const fill = document.getElementById('pwdStrengthFill');
  const label = document.getElementById('pwdStrengthLabel');
  const score = document.getElementById('pwdStrengthScore');
  if (!fill || !label || !score) return;

  if (!val || val.length === 0) {
    fill.style.width = '0%';
    label.innerText = 'Minimum 6 characters';
    label.style.color = '#94a3b8';
    score.innerText = '0%';
    score.style.color = '#94a3b8';
    return;
  }

  let s = 0;
  if (val.length >= 6) s += 25;
  if (val.length >= 8) s += 15;
  if (/[A-Z]/.test(val)) s += 20;
  if (/[0-9]/.test(val)) s += 20;
  if (/[^A-Za-z0-9]/.test(val)) s += 20;

  fill.style.width = s + '%';
  score.innerText = s + '%';

  if (s < 40) {
    fill.style.background = '#ef4444';
    label.innerText = 'Weak Password';
    label.style.color = '#ef4444';
    score.style.color = '#ef4444';
  } else if (s < 75) {
    fill.style.background = '#f59e0b';
    label.innerText = 'Moderate Password';
    label.style.color = '#f59e0b';
    score.style.color = '#f59e0b';
  } else {
    fill.style.background = '#10b981';
    label.innerText = 'Strong Password';
    label.style.color = '#10b981';
    score.style.color = '#10b981';
  }

  checkPasswordMatch();
}

function checkPasswordMatch() {
  const newPass = document.getElementById('new_pass');
  const confPass = document.getElementById('conf_pass');
  const feedback = document.getElementById('matchFeedback');
  if (!newPass || !confPass || !feedback) return;

  if (confPass.value.length === 0) {
    feedback.style.display = 'none';
    return;
  }

  feedback.style.display = 'block';
  if (newPass.value === confPass.value) {
    feedback.style.color = '#10b981';
    feedback.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match perfectly!';
  } else {
    feedback.style.color = '#ef4444';
    feedback.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Passwords do not match yet.';
  }
}

function resetStrengthMeter() {
  setTimeout(() => {
    evaluatePasswordStrength('');
    const feedback = document.getElementById('matchFeedback');
    if (feedback) feedback.style.display = 'none';
  }, 50);
}

// Close modal on backdrop click or ESC key
window.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeNewSessionModal();
});

// Run initial toolbar state
document.addEventListener('DOMContentLoaded', function() {
  const activePane = document.querySelector('.settings-tab-pane.active');
  if (activePane) {
    if (activePane.id === 'pane-sessions') {
      switchSettingsTab('sessions');
    } else if (activePane.id === 'pane-security') {
      switchSettingsTab('security');
    } else {
      switchSettingsTab('general');
    }
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
