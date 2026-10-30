<?php
/**
 * KSV Academy HRMS & Portal - Configuration & DB Connection
 */

// Global Timezone: Indian Standard Time (IST / UTC+05:30)
date_default_timezone_set('Asia/Kolkata');

// Security Hardening: Session Cookie Protection
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Environment Detection (Live vs Localhost)
$http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
$is_local_env = (
    empty($http_host) ||
    in_array($http_host, ['localhost', '127.0.0.1', '::1']) ||
    strpos($http_host, 'localhost:') === 0 ||
    strpos($http_host, '127.0.0.1:') === 0 ||
    preg_match('/\.local$/i', $http_host)
);

if ($is_local_env) {
    // ----------------------------------------------------
    // LOCALHOST ENVIRONMENT
    // ----------------------------------------------------
    define('IS_LIVE', false);
    define('LIVE_URL', 'https://ksvacademy.org');
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', 3306);
    define('DB_NAME', 'ksvacademy');
    define('DB_USER', 'root');
    define('DB_PASS', 'myPassword$10');
    define('DB_PASS_ALT', 'myPasswprd$10');
} else {
    // ----------------------------------------------------
    // LIVE SERVER ENVIRONMENT (https://ksvacademy.org)
    // ----------------------------------------------------
    define('IS_LIVE', true);
    define('LIVE_URL', 'https://ksvacademy.org');
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
    define('DB_NAME', 'u471423132_ksvacademy');
    define('DB_USER', 'u471423132_ksvacademy');
    define('DB_PASS', '@KsvAdmin2027');
    define('DB_PASS_ALT', '@KsvAdmin2027');
}

// App Paths
define('HRMS_URL', '/web-app');
define('WEBAPP_URL', '/web-app');
define('ADMIN_URL', '/web-app/admin');
define('STAFF_URL', '/web-app/staff');
define('PARENT_URL', '/web-app/parent-student');
define('APP_NAME', 'KSV Academy Web App');

/**
 * Get PDO Database Connection
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $passwords = [DB_PASS];
    if (defined('DB_PASS_ALT') && DB_PASS_ALT !== DB_PASS) {
        $passwords[] = DB_PASS_ALT;
    }
    if (!defined('IS_LIVE') || !IS_LIVE) {
        $passwords[] = '';
    }

    $last_exception = null;

    // 1. Primary connection attempt using configured DB_HOST
    foreach ($passwords as $pwd) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, $pwd, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+05:30'");
            return $pdo;
        } catch (PDOException $e) {
            $last_exception = $e;
        }
    }

    // 2. Fallback host attempt (toggle between localhost and 127.0.0.1)
    $fallback_host = (DB_HOST === '127.0.0.1') ? 'localhost' : '127.0.0.1';
    foreach ($passwords as $pwd) {
        try {
            $dsn = "mysql:host=" . $fallback_host . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, $pwd, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+05:30'");
            return $pdo;
        } catch (PDOException $e) {
            $last_exception = $e;
        }
    }

    die("<div style='font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;'>
        <h3>Database Connection Failed</h3>
        <p>Could not connect to database <code>" . htmlspecialchars(DB_NAME) . "</code> on <code>" . htmlspecialchars(DB_HOST) . "</code>. Please check MySQL service status.</p>
        <p><small>" . htmlspecialchars($last_exception ? $last_exception->getMessage() : 'Unknown error') . "</small></p>
    </div>");
}

/**
 * Get Currently Logged-in User
 */
function get_logged_user() {
    $uid = $_SESSION['user_id'] ?? null;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // If browsing an admin route and an admin session exists, maintain admin user
    if (!empty($_SESSION['admin_user_id']) && strpos($uri, '/web-app/admin') !== false) {
        $uid = $_SESSION['admin_user_id'];
    }

    if (empty($uid)) {
        return null;
    }

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$uid]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Require Login and optionally specific role
 */
function require_login($allowed_roles = []) {
    $user = get_logged_user();
    if (!$user) {
        $_SESSION['flash_error'] = 'Please log in to access this page.';
        header('Location: ' . HRMS_URL . '/login');
        exit;
    }

    if (!empty($allowed_roles)) {
        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }

        if (!in_array($user['role'], $allowed_roles)) {
            // If active user is student from admin impersonation, restore admin session for admin/staff routes
            if (!empty($_SESSION['admin_user_id']) && (in_array('super_admin', $allowed_roles) || in_array('staff', $allowed_roles))) {
                $_SESSION['user_id'] = $_SESSION['admin_user_id'];
                unset($_SESSION['impersonated_student'], $_SESSION['active_student_id']);
                $user = get_logged_user();
                if ($user && in_array($user['role'], $allowed_roles)) {
                    return $user;
                }
            }

            $_SESSION['flash_error'] = 'Access denied. You do not have permission for that section.';
            $dest = get_dashboard_url($user['role']);
            $current_uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if ($dest === $current_uri || rtrim($dest, '/') === rtrim($current_uri, '/')) {
                header('Location: ' . HRMS_URL . '/login');
                exit;
            }
            header('Location: ' . $dest);
            exit;
        }
    }

    return $user;
}

/**
 * Get or Create User Account for a Student
 */
function get_or_create_student_user($student, $db = null) {
    if (!$db) {
        $db = get_db_connection();
    }
    
    // 1. If user_id already set on student and valid
    if (!empty($student['user_id'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$student['user_id']]);
        $u = $stmt->fetch();
        if ($u) {
            return $u;
        }
    }

    // 2. Try finding by matching roll_no or username or full_name
    $clean_roll = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $student['roll_no'] ?? ''));
    $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR username = ? OR full_name = ?) AND role = 'student' LIMIT 1");
    $stmt->execute([$clean_roll, $student['roll_no'] ?? '', $student['student_name'] ?? '']);
    $u = $stmt->fetch();

    if ($u) {
        $db->prepare("UPDATE students SET user_id = ? WHERE id = ?")->execute([$u['id'], $student['id']]);
        return $u;
    }

    // 3. Create fresh user
    $uname = $clean_roll ?: ("student_" . $student['id']);
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$uname]);
    if ($stmt->fetch()) {
        $uname = $uname . "_" . $student['id'];
    }

    $email = !empty($student['student_email']) ? $student['student_email'] : ($uname . "@ksvacademy.net");
    $phone = !empty($student['student_phone']) ? $student['student_phone'] : "";
    $pass_hash = password_hash("student", PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (username, password, role, email, phone, full_name, status) VALUES (?, ?, 'student', ?, ?, ?, 'active')");
    $stmt->execute([$uname, $pass_hash, $email, $phone, $student['student_name']]);
    $new_id = $db->lastInsertId();

    $db->prepare("UPDATE students SET user_id = ? WHERE id = ?")->execute([$new_id, $student['id']]);

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$new_id]);
    return $stmt->fetch();
}

/**
 * Default Staff Permissions
 */
define('DEFAULT_STAFF_PERMISSIONS', [
    'dashboard',
    'myself-attendance',
    'admin_students',
    'admin_student_attendance',
    'admin_fees',
    'admin_student_id',
    'admin_marksheet',
    'staff-id',
    'staff-periods',
    'leaves',
    'payroll',
    'announcements',
    'admin_expenses',
    'admin_expense_categories',
    'admin_expense_report'
]);

/**
 * Default Student / Parent Permissions
 */
define('DEFAULT_STUDENT_PERMISSIONS', [
    'dashboard',
    'attendance',
    'fees',
    'student-id',
    'marksheet',
    'announcements',
    'settings'
]);

/**
 * Get permitted menu keys for a staff member
 */
function get_staff_permissions($user_or_staff_id, $db = null) {
    if (!$db) {
        $db = get_db_connection();
    }
    try {
        $stmt = $db->prepare("SELECT menu_permissions FROM staff_profiles WHERE user_id = ? OR id = ? LIMIT 1");
        $stmt->execute([$user_or_staff_id, $user_or_staff_id]);
        $row = $stmt->fetch();
        if ($row && !empty($row['menu_permissions'])) {
            $decoded = json_decode($row['menu_permissions'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    } catch (Exception $e) {
        // fallback
    }
    return DEFAULT_STAFF_PERMISSIONS;
}

/**
 * Get permitted menu keys for a student or parent member
 */
function get_student_permissions($user_or_student_id, $db = null) {
    if (!$db) {
        $db = get_db_connection();
    }
    try {
        $stmt = $db->prepare("SELECT menu_permissions FROM students WHERE user_id = ? OR parent_user_id = ? OR id = ? LIMIT 1");
        $stmt->execute([$user_or_student_id, $user_or_student_id, $user_or_student_id]);
        $row = $stmt->fetch();
        if ($row && !empty($row['menu_permissions'])) {
            $decoded = json_decode($row['menu_permissions'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    } catch (Exception $e) {
        // fallback
    }
    return DEFAULT_STUDENT_PERMISSIONS;
}

/**
 * Check if the currently logged-in user has permission for a specific menu
 */
function has_menu_permission($menu_key, $user = null) {
    if (!$user) {
        $user = get_logged_user();
    }
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'super_admin') {
        return true;
    }
    if ($user['role'] === 'staff') {
        $perms = get_staff_permissions($user['id']);
        if (in_array($menu_key, $perms)) {
            return true;
        }
        $aliases = [
            'admin_students' => ['students', 'admin_students'],
            'students' => ['students', 'admin_students'],
            'admin_student_attendance' => ['student-attendance', 'admin_student_attendance'],
            'student-attendance' => ['student-attendance', 'admin_student_attendance'],
            'admin_fees' => ['fees', 'admin_fees'],
            'fees' => ['fees', 'admin_fees'],
            'admin_student_id' => ['student-id', 'admin_student_id'],
            'student-id' => ['student-id', 'admin_student_id'],
            'admin_marksheet' => ['marksheet', 'admin_marksheet'],
            'marksheet' => ['marksheet', 'admin_marksheet'],
            'admin_staff_periods' => ['staff-periods', 'admin_staff_periods'],
            'staff-periods' => ['staff-periods', 'admin_staff_periods'],
            'admin_expenses' => ['expenses', 'admin_expenses'],
            'expenses' => ['expenses', 'admin_expenses'],
            'admin_expense_categories' => ['expense-categories', 'admin_expense_categories'],
            'expense-categories' => ['expense-categories', 'admin_expense_categories'],
            'admin_expense_report' => ['expense-report', 'admin_expense_report'],
            'expense-report' => ['expense-report', 'admin_expense_report'],
        ];
        if (isset($aliases[$menu_key])) {
            foreach ($aliases[$menu_key] as $alias) {
                if (in_array($alias, $perms)) {
                    return true;
                }
            }
        }
        return false;
    }
    if ($user['role'] === 'student' || $user['role'] === 'parent') {
        $perms = get_student_permissions($user['id']);
        if (in_array($menu_key, $perms)) {
            return true;
        }
        $aliases = [
            'dashboard' => ['dashboard'],
            'attendance' => ['attendance'],
            'fees' => ['fees'],
            'student-id' => ['student-id'],
            'marksheet' => ['marksheet'],
            'announcements' => ['announcements'],
            'settings' => ['settings'],
        ];
        if (isset($aliases[$menu_key])) {
            foreach ($aliases[$menu_key] as $alias) {
                if (in_array($alias, $perms)) {
                    return true;
                }
            }
        }
        return false;
    }
    return false;
}

/**
 * Require a specific menu permission or redirect with flash message
 */
function require_menu_permission($menu_key, $user = null) {
    if (!$user) {
        $user = get_logged_user();
    }
    if (!$user) {
        header('Location: ' . HRMS_URL . '/login');
        exit;
    }
    if ($user['role'] === 'super_admin') {
        return true;
    }
    if ($user['role'] === 'staff') {
        if (!has_menu_permission($menu_key, $user)) {
            $_SESSION['flash_error'] = 'Access denied. You do not have permission to access that section.';
            $target = get_dashboard_url('staff', $user['id']);
            $current_uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if ($target === $current_uri || rtrim($target, '/') === rtrim($current_uri, '/')) {
                header('Location: ' . HRMS_URL . '/login');
                exit;
            }
            header('Location: ' . $target);
            exit;
        }
    }
    if ($user['role'] === 'student' || $user['role'] === 'parent') {
        if (!has_menu_permission($menu_key, $user)) {
            $_SESSION['flash_error'] = 'Access denied. You do not have permission to access that section.';
            $target = PARENT_URL . '/dashboard';
            $current_uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if ($target === $current_uri || rtrim($target, '/') === rtrim($current_uri, '/')) {
                header('Location: ' . HRMS_URL . '/login');
                exit;
            }
            header('Location: ' . $target);
            exit;
        }
    }
    return true;
}

/**
 * Get Destination Dashboard by Role (Clean URLs without .php)
 */
function get_dashboard_url($role, $user_id = null) {
    switch ($role) {
        case 'super_admin':
            return ADMIN_URL . '/dashboard';
        case 'staff':
            if ($user_id) {
                $perms = get_staff_permissions($user_id);
                if (!in_array('dashboard', $perms)) {
                    $routes = [
                        'attendance' => STAFF_URL . '/attendance',
                        'staff-id' => STAFF_URL . '/staff-id',
                        'leaves' => STAFF_URL . '/leaves',
                        'students' => STAFF_URL . '/students',
                        'payroll' => STAFF_URL . '/payroll',
                        'announcements' => STAFF_URL . '/announcements',
                        'admin_students' => ADMIN_URL . '/students',
                        'admin_student_attendance' => ADMIN_URL . '/student-attendance',
                        'admin_marksheet' => ADMIN_URL . '/marksheet',
                        'admin_staff_periods' => ADMIN_URL . '/staff-periods',
                        'admin_staff_attendance' => ADMIN_URL . '/staff-attendance',
                    ];
                    foreach ($perms as $p) {
                        if (!empty($routes[$p])) {
                            return $routes[$p];
                        }
                    }
                }
            }
            return STAFF_URL . '/dashboard';
        case 'parent':
        case 'student':
            return PARENT_URL . '/dashboard';
        default:
            return HRMS_URL . '/login';
    }
}

/**
 * Academic Session Management
 */
function get_all_sessions() {
    try {
        $db = get_db_connection();
        return $db->query("SELECT * FROM academic_sessions ORDER BY id DESC")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function get_active_session() {
    $db = get_db_connection();
    
    // Check if user manually switched session in session store
    if (!empty($_SESSION['active_session_id'])) {
        $stmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['active_session_id']]);
        $sess = $stmt->fetch();
        if ($sess) {
            return $sess;
        }
    }

    // Default to current academic session
    $stmt = $db->query("SELECT * FROM academic_sessions WHERE is_current = 1 LIMIT 1");
    $sess = $stmt->fetch();
    if (!$sess) {
        // Fallback to highest ID
        $sess = $db->query("SELECT * FROM academic_sessions ORDER BY id DESC LIMIT 1")->fetch();
    }

    if ($sess) {
        $_SESSION['active_session_id'] = $sess['id'];
        return $sess;
    }

    // Default fallback object
    return ['id' => 1, 'session_name' => '2025-2026', 'is_current' => 1];
}

// Global handle session switch GET trigger (e.g. ?switch_session=2)
if (isset($_GET['switch_session']) && is_numeric($_GET['switch_session'])) {
    $sw_id = intval($_GET['switch_session']);
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM academic_sessions WHERE id = ? LIMIT 1");
    $stmt->execute([$sw_id]);
    $found = $stmt->fetch();
    if ($found) {
        $_SESSION['active_session_id'] = $found['id'];
        $_SESSION['flash_success'] = "Switched active academic session to " . htmlspecialchars($found['session_name']) . ".";
    }
    // Clean URL query and redirect back
    $req_uri = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $req_uri);
    exit;
}

/**
 * School Settings Helpers
 */
function get_school_settings() {
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = [];
    try {
        $db = get_db_connection();
        $rows = $db->query("SELECT setting_key, setting_value FROM school_settings")->fetchAll();
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Exception $e) {
        // use default fallbacks
    }

    $defaults = [
        'school_name' => 'KSV Academy',
        'tagline' => 'Where Learning Meets Excellence',
        'affiliation' => 'CBSE Affiliated - Reg #KSV-ACAD-2024',
        'phone' => '+91 98765 43210',
        'email' => 'admissions@ksvacademy.com',
        'address' => 'KSV Campus, Knowledge Valley Road, Sector 12, Academic Hub',
        'website' => 'http://localhost:8000',
        'currency_symbol' => '₹',
        'principal_name' => 'Mr. Salam Khan (M.Sc, B.Ed)',
        'logo_url' => '/assets/imgs/ksv-logo.jpg',
        'stamp_text' => 'OFFICIAL SEAL - KSV ACADEMY',
        'theme_mode' => 'light'
    ];

    $settings = array_merge($defaults, $settings);
    return $settings;
}

function get_school_setting($key, $default = '') {
    $all = get_school_settings();
    return $all[$key] ?? $default;
}

/**
 * QR Code Generator (Uses SVG / standard sharp renderer)
 */
function get_qr_code_url($data, $size = '160x160') {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . '&data=' . urlencode($data);
}

/**
 * Flash message helpers
 */
function set_flash($type, $msg) {
    $_SESSION['flash_' . $type] = $msg;
}

function get_flash($type) {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

/**
 * Formatting Utilities
 */
function format_inr($amount) {
    return '₹' . number_format((float)$amount, 2);
}

function format_date($date, $format = 'd M Y') {
    if (!$date) return '—';
    return date($format, strtotime($date));
}

function format_time($time) {
    if (!$time) return '—';
    return date('h:i A', strtotime($time));
}

/**
 * Ensure form_data table exists
 */
function ensure_form_data_table($db = null) {
    if (!$db) {
        $db = get_db_connection();
    }
    static $ensured = false;
    if ($ensured) return;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS form_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            form_type VARCHAR(50) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            email VARCHAR(150) DEFAULT NULL,
            parent_name VARCHAR(150) DEFAULT NULL,
            course VARCHAR(150) DEFAULT NULL,
            batch_timing VARCHAR(150) DEFAULT NULL,
            school_name VARCHAR(255) DEFAULT NULL,
            query_type VARCHAR(150) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'unread',
            notes TEXT DEFAULT NULL,
            source_page VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_form_type (form_type),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $ensured = true;
    } catch (Exception $e) {
        // ignore
    }
}

/**
 * Get count of unread form submissions
 */
function get_unread_form_data_count() {
    static $count = null;
    if ($count !== null) return $count;
    try {
        $db = get_db_connection();
        ensure_form_data_table($db);
        $stmt = $db->query("SELECT COUNT(*) FROM form_data WHERE status = 'unread'");
        $count = (int) $stmt->fetchColumn();
        return $count;
    } catch (Exception $e) {
        return 0;
    }
}

