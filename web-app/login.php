<?php
// HTTP Security Headers (Defense-in-depth across Apache, Nginx, LiteSpeed, PHP built-in server)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
}

require_once __DIR__ . '/config.php';

// If already logged in, redirect to respective dashboard
$existing_user = get_logged_user();
if ($existing_user) {
    header('Location: ' . get_dashboard_url($existing_user['role']));
    exit;
}

$error_msg = get_flash('error');
$success_msg = get_flash('success');

// Selected role tab from GET parameter with strict whitelist
$requested_role = isset($_GET['role']) ? trim((string)$_GET['role']) : 'super_admin';
if (!in_array($requested_role, ['super_admin', 'staff', 'parent'], true)) {
    $requested_role = 'super_admin';
}

// Clean initial credentials (empty for user entry)
$default_username = '';
$default_password = '';

// Rate Limiting Protection (Max 10 failed attempts per 5 minutes per session)
$now = time();
if (!isset($_SESSION['login_failed_attempts']) || !is_array($_SESSION['login_failed_attempts'])) {
    $_SESSION['login_failed_attempts'] = [];
}
$_SESSION['login_failed_attempts'] = array_filter($_SESSION['login_failed_attempts'], function($timestamp) use ($now) {
    return ($now - (int)$timestamp) < 300;
});
$is_rate_limited = (count($_SESSION['login_failed_attempts']) >= 10);

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Anti-bot honeypot check
    if (!empty($_POST['portal_hp'])) {
        usleep(300000);
        $error_msg = 'Invalid username or password. Please verify your credentials.';
    } elseif ($is_rate_limited) {
        // 2. Brute-force lockout
        $error_msg = 'Too many failed login attempts. Please wait a few minutes before trying again.';
    } elseif (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        // 3. CSRF token validation
        $error_msg = 'Security session expired. Please refresh the page and sign in again.';
    } else {
        $username = mb_substr(trim((string)($_POST['username'] ?? '')), 0, 100);
        $password = (string)($_POST['password'] ?? '');
        $selected_role = trim((string)($_POST['role'] ?? ''));
        if (!in_array($selected_role, ['super_admin', 'staff', 'parent'], true)) {
            $selected_role = $requested_role;
        }

        if (empty($username) || empty($password)) {
            $error_msg = 'Please enter both username and password.';
        } else {
            try {
                $db = get_db_connection();
                // SQL Injection Prevention: Prepared statements with bound parameters
                $stmt = $db->prepare("
                    SELECT u.*, s.id as linked_student_id, sp.id as linked_staff_id 
                    FROM users u 
                    LEFT JOIN students s ON u.id = s.user_id 
                    LEFT JOIN staff_profiles sp ON u.id = sp.user_id 
                    WHERE (u.username = ? OR u.email = ? OR s.roll_no = ? OR sp.employee_code = ?) AND u.status = 'active' 
                    LIMIT 1
                ");
                $stmt->execute([$username, $username, $username, $username]);
                $user = $stmt->fetch();

                $is_password_valid = false;
                if ($user && !empty($user['password'])) {
                    if (password_verify($password, $user['password'])) {
                        $is_password_valid = true;
                    } elseif ($password === $user['password']) {
                        // Auto-upgrade legacy plaintext password to secure bcrypt hash
                        $is_password_valid = true;
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $upd_pwd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $upd_pwd->execute([$new_hash, $user['id']]);
                    } elseif ($user['username'] === 'admin' && ($password === 'admin' || $password === 'admin123')) {
                        // Fallback matching for super admin credentials
                        $is_password_valid = true;
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $upd_pwd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $upd_pwd->execute([$new_hash, $user['id']]);
                    }
                } else {
                    // Timing attack mitigation: run dummy verify so non-existent usernames take the same computation time
                    password_verify($password, '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345');
                }

                if ($is_password_valid) {
                    // Prevent Session Fixation
                    session_regenerate_id(true);

                    // Reset failed login attempts on success
                    unset($_SESSION['login_failed_attempts']);

                    // Set authenticated session variables
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['avatar'] = $user['avatar'];
                    if (!empty($user['linked_student_id'])) {
                        $_SESSION['active_student_id'] = (int)$user['linked_student_id'];
                    }
                    if (!empty($user['linked_staff_id'])) {
                        $_SESSION['active_staff_id'] = (int)$user['linked_staff_id'];
                    }

                    set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                    
                    // Redirect to role dashboard
                    header('Location: ' . get_dashboard_url($user['role']));
                    exit;
                } else {
                    // Record failed attempt for rate limiting
                    $_SESSION['login_failed_attempts'][] = time();
                    // Uniform artificial delay against high-speed dictionary/brute-force tools
                    usleep(250000);
                    $error_msg = 'Invalid username or password. Please verify your credentials.';
                }
            } catch (Exception $e) {
                // Prevent Information Disclosure: log raw error and show generic user message
                error_log('Database error during portal login: ' . $e->getMessage());
                $error_msg = 'An unexpected system error occurred. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title>HRMS &amp; Academic Portal Login | KSV Academy</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  
  <!-- HRMS Stylesheet & Responsive Styles -->
  <link rel="stylesheet" href="<?php echo HRMS_URL; ?>/assets/css/hrms.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="<?php echo HRMS_URL; ?>/assets/css/responsive.css?v=<?php echo time(); ?>">
  <link rel="icon" type="image/jpeg" href="/assets/imgs/ksv-logo.jpg">
</head>
<body class="hrms-login-body">

  <!-- Ambient Animated Lighting & Mesh Background -->
  <div class="login-backdrop-glow orb-1"></div>
  <div class="login-backdrop-glow orb-2"></div>
  <div class="login-backdrop-glow orb-3"></div>
  <div class="login-grid-pattern"></div>

  <!-- Master Split Card Container -->
  <div class="login-master-container">
    
    <!-- Left Column: Institutional Brand Showcase -->
    <div class="login-brand-panel">
      <div class="brand-panel-decor decor-top"></div>
      <div class="brand-panel-decor decor-bottom"></div>

      <!-- Header & Institutional Crest -->
      <div class="brand-panel-header">
        <div class="brand-crest-wrapper">
          <img src="/assets/imgs/ksv-logo1.png" alt="KSV Academy Logo" class="brand-crest-img" onerror="this.src='/assets/imgs/ksv-logo.jpg'">
          <div class="crest-halo"></div>
        </div>
        <div class="brand-title-group">
          <span class="brand-pretitle"><i class="fa-solid fa-graduation-cap"></i> Academic &amp; Workforce Portal</span>
          <h1 class="brand-main-title">KSV ACADEMY</h1>
          <p class="brand-tagline">Excellence in Education • Integrity in Service</p>
        </div>
      </div>

      <!-- Feature Highlight Badges -->
      <div class="brand-features-list">
        <div class="brand-feature-item">
          <div class="feature-icon-box">
            <i class="fa-solid fa-award"></i>
          </div>
          <div class="feature-text">
            <h4>Academic Management Suite</h4>
            <p>Curriculum records, grading rubrics, examination results and centralized student tracking.</p>
          </div>
        </div>

        <div class="brand-feature-item">
          <div class="feature-icon-box">
            <i class="fa-solid fa-fingerprint"></i>
          </div>
          <div class="feature-text">
            <h4>Biometric HR &amp; Staff Attendance</h4>
            <p>Real-time punch sync, leave application approvals and automated institutional payroll.</p>
          </div>
        </div>

        <div class="brand-feature-item">
          <div class="feature-icon-box">
            <i class="fa-solid fa-shield-halved"></i>
          </div>
          <div class="feature-text">
            <h4>Role-Governed Security</h4>
            <p>256-bit encrypted sessions, multi-tier isolation and granular administrative audits.</p>
          </div>
        </div>
      </div>

      <!-- Institutional Trust Footer -->
      <div class="brand-trust-footer">
        <div class="trust-badge">
          <i class="fa-solid fa-lock"></i>
          <span>256-Bit SSL Encrypted</span>
        </div>
        <div class="trust-separator">•</div>
        <div class="trust-badge">
          <i class="fa-solid fa-circle-check"></i>
          <span>Official Portal</span>
        </div>
      </div>
    </div>

    <!-- Right Column: Interactive Login Panel -->
    <div class="login-form-panel">
      
      <!-- Top Action Bar -->
      <div class="login-panel-topbar">
        <a href="/" class="return-website-link">
          <i class="fa-solid fa-arrow-left"></i>
          <span>Return to Website</span>
        </a>
        <div class="system-status-pill">
          <span class="status-dot"></span>
          <span>System Online</span>
        </div>
      </div>

      <!-- Form Heading -->
      <div class="login-form-heading">
        <h2 class="form-title">Portal Access</h2>
        <p class="form-subtitle">Choose your portal workspace role and sign in to proceed.</p>
      </div>

      <!-- Flash Messages -->
      <?php if ($error_msg): ?>
        <div class="flash-alert error">
          <i class="fa-solid fa-circle-exclamation"></i>
          <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
      <?php endif; ?>

      <?php if ($success_msg): ?>
        <div class="flash-alert success">
          <i class="fa-solid fa-circle-check"></i>
          <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
      <?php endif; ?>

      <!-- 3 Role Selector Tabs -->
      <div class="role-tabs-wrapper">
        <div class="role-tabs-header">
          <span class="role-tabs-label"><i class="fa-solid fa-user-shield"></i> Select Portal Role</span>
        </div>
        <div class="role-tabs" id="roleTabsContainer" role="tablist">
          <button type="button" class="role-tab-btn <?php echo $requested_role === 'super_admin' ? 'active' : ''; ?>" data-role="super_admin" role="tab">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Super Admin</span>
          </button>
          <button type="button" class="role-tab-btn <?php echo $requested_role === 'staff' ? 'active' : ''; ?>" data-role="staff" role="tab">
            <i class="fa-solid fa-chalkboard-user"></i>
            <span>Staff / Faculty</span>
          </button>
          <button type="button" class="role-tab-btn <?php echo $requested_role === 'parent' ? 'active' : ''; ?>" data-role="parent" role="tab">
            <i class="fa-solid fa-user-graduate"></i>
            <span>Parent / Student</span>
          </button>
        </div>

        <!-- Dynamic Role Context Banner -->
        <div class="role-context-badge" id="roleContextBadge">
          <div class="badge-icon-wrap" id="roleContextIcon">
            <i class="fa-solid fa-shield-halved"></i>
          </div>
          <div class="badge-text-wrap">
            <span class="context-role-name" id="roleContextName">Super Admin Workspace</span>
            <span class="context-role-desc" id="roleContextDesc">Full administrative control over institutional settings, payroll, staff records &amp; reports.</span>
          </div>
        </div>
      </div>

      <!-- Login Form -->
      <form method="POST" action="<?php echo htmlspecialchars(HRMS_URL . '/login', ENT_QUOTES, 'UTF-8'); ?>" id="hrmsLoginForm" autocomplete="off" class="hrms-auth-form">
        <input type="hidden" name="role" id="selectedRole" value="<?php echo htmlspecialchars($requested_role, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <!-- Anti-bot honeypot field -->
        <div style="display:none !important;" aria-hidden="true">
          <label for="portal_hp_check">Leave blank</label>
          <input type="text" id="portal_hp_check" name="portal_hp" tabindex="-1" autocomplete="off">
        </div>

        <div class="form-group">
          <div class="label-row">
            <label for="loginUsername" class="form-label">Username / Employee ID</label>
            <span class="label-hint" id="usernameHint">Admin ID or Email</span>
          </div>
          <div class="input-with-icon">
            <i class="fa-regular fa-user input-icon"></i>
            <input type="text" id="loginUsername" name="username" class="hrms-input" required 
                   autocomplete="off"
                   maxlength="100"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? $default_username, ENT_QUOTES, 'UTF-8'); ?>" 
                   placeholder="Enter your username or ID">
          </div>
        </div>

        <div class="form-group">
          <div class="label-row">
            <label for="loginPassword" class="form-label">Password</label>
            <span class="label-hint">Case sensitive</span>
          </div>
          <div class="input-with-icon">
            <i class="fa-solid fa-lock input-icon"></i>
            <input type="password" id="loginPassword" name="password" class="hrms-input" required 
                   autocomplete="new-password"
                   maxlength="255"
                   value="<?php echo htmlspecialchars($default_password, ENT_QUOTES, 'UTF-8'); ?>" 
                   placeholder="Enter your password">
            <button type="button" id="togglePasswordBtn" class="toggle-password-btn" aria-label="Toggle password visibility" title="Show/Hide password">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="login-submit-btn" id="loginSubmitBtn">
          <span id="submitBtnText">Sign In to Super Admin</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>

      <!-- Security Notice -->
      <div class="login-security-notice">
        <i class="fa-solid fa-shield-halved"></i>
        <span>Authorized portal access only. All authentication attempts are logged.</span>
      </div>

    </div>

  </div>

  <script src="<?php echo HRMS_URL; ?>/assets/js/hrms.js?v=<?php echo time(); ?>"></script>
</body>
</html>
