<?php
/**
 * KSV Academy HRMS & Portal - Configuration & DB Connection
 */

// Global Timezone: Indian Standard Time (IST / UTC+05:30)
date_default_timezone_set('Asia/Kolkata');

// Error Handling: Suppress PHP notices and warnings on frontend UI, log errors securely
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);

// Security Hardening: Session Cookie Protection (only if headers not already sent)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
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
    if (!defined('IS_LIVE')) define('IS_LIVE', false);
    if (!defined('LIVE_URL')) define('LIVE_URL', 'https://ksvacademy.org');
    if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
    if (!defined('DB_PORT')) define('DB_PORT', 3306);
    if (!defined('DB_NAME')) define('DB_NAME', 'ksvacademy');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', 'myPassword$10');
    if (!defined('DB_PASS_ALT')) define('DB_PASS_ALT', 'myPasswprd$10');
} else {
    // ----------------------------------------------------
    // LIVE SERVER ENVIRONMENT (https://ksvacademy.org)
    // ----------------------------------------------------
    if (!defined('IS_LIVE')) define('IS_LIVE', true);
    if (!defined('LIVE_URL')) define('LIVE_URL', 'https://ksvacademy.org');
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_PORT')) define('DB_PORT', 3306);
    if (!defined('DB_NAME')) define('DB_NAME', 'u471423132_ksvacademy');
    if (!defined('DB_USER')) define('DB_USER', 'u471423132_ksvacademy');
    if (!defined('DB_PASS')) define('DB_PASS', '@KsvAdmin2027');
    if (!defined('DB_PASS_ALT')) define('DB_PASS_ALT', '@KsvAdmin2027');
}

// App Paths
if (!defined('HRMS_URL')) define('HRMS_URL', '/web-app');
if (!defined('WEBAPP_URL')) define('WEBAPP_URL', '/web-app');
if (!defined('ADMIN_URL')) define('ADMIN_URL', '/web-app/admin');
if (!defined('STAFF_URL')) define('STAFF_URL', '/web-app/staff');
if (!defined('PARENT_URL')) define('PARENT_URL', '/web-app/parent-student');
if (!defined('APP_NAME')) define('APP_NAME', 'KSV Academy Web App');

// Outbound SMTP Mailer Defaults (Can also be configured in school_settings via Admin Panel)
if (!defined('SMTP_ENABLED')) define('SMTP_ENABLED', false);
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', 'tls');
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'admissions@ksvacademy.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'KSV Academy');

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

    // Security: If called from public website frontend (e.g. /about, /courses), throw exception so try/catch degrades gracefully
    $req_uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_frontend = (strpos($req_uri, '/web-app') === false);
    if ($is_frontend) {
        if ($last_exception) {
            throw $last_exception;
        }
        throw new PDOException("Database connection unavailable.");
    }

    // In web-app context: log details securely, return 500 status, avoid leaking DB topology/credentials
    error_log("Database connection error: " . ($last_exception ? $last_exception->getMessage() : 'Unknown error'));
    http_response_code(500);
    die("<div style='font-family:sans-serif;padding:24px;background:#fee2e2;color:#991b1b;border-radius:8px;max-width:540px;margin:50px auto;text-align:center;'>
        <h3 style='margin-top:0;'>Service Temporarily Unavailable</h3>
        <p>The system was unable to establish a database connection. Please check database service status or try again shortly.</p>
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
function get_or_create_student_user($student, $db = null, $raw_password = 'student') {
    if (!$db) {
        $db = get_db_connection();
    }
    
    $raw_password = (!empty($raw_password)) ? $raw_password : 'student';

    // 1. If user_id already set on student and valid
    if (!empty($student['user_id'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$student['user_id']]);
        $u = $stmt->fetch();
        if ($u) {
            if (!empty($raw_password) && $raw_password !== 'student') {
                $hash = password_hash($raw_password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $u['id']]);
                $u['password'] = $hash;
            }
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
        if (!empty($raw_password) && $raw_password !== 'student') {
            $hash = password_hash($raw_password, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $u['id']]);
            $u['password'] = $hash;
        }
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
    $pass_hash = password_hash($raw_password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (username, password, role, email, phone, full_name, status) VALUES (?, ?, 'student', ?, ?, ?, 'active')");
    $stmt->execute([$uname, $pass_hash, $email, $phone, $student['student_name']]);
    $new_id = $db->lastInsertId();

    $db->prepare("UPDATE students SET user_id = ? WHERE id = ?")->execute([$new_id, $student['id']]);

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$new_id]);
    return $stmt->fetch();
}

/**
 * Standalone Pure-PHP SMTP Client for KSV Academy
 * Direct RFC 5321 / 5322 socket communication with STARTTLS & SSL support.
 *
 * @param string $to Recipient email address
 * @param string $subject Message subject
 * @param string $html_body HTML message content
 * @param string &$diag_error Reference to store human-readable diagnostic error
 * @param array $config Optional override configuration
 * @return bool True if 250 OK received from SMTP server, false otherwise
 */
function ksv_send_smtp_mail($to, $subject, $html_body, &$diag_error = '', $config = []) {
    $settings = function_exists('get_school_settings') ? get_school_settings() : [];

    $host = !empty($config['host']) ? $config['host'] : (!empty($settings['smtp_host']) ? $settings['smtp_host'] : (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com'));
    $port = !empty($config['port']) ? (int)$config['port'] : (!empty($settings['smtp_port']) ? (int)$settings['smtp_port'] : (defined('SMTP_PORT') ? (int)SMTP_PORT : 587));
    $encryption = strtolower(!empty($config['encryption']) ? $config['encryption'] : (!empty($settings['smtp_encryption']) ? $settings['smtp_encryption'] : (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls')));
    $user = !empty($config['user']) ? $config['user'] : (!empty($settings['smtp_user']) ? $settings['smtp_user'] : (defined('SMTP_USER') ? SMTP_USER : ''));
    $pass = !empty($config['pass']) ? $config['pass'] : (!empty($settings['smtp_pass']) ? $settings['smtp_pass'] : (defined('SMTP_PASS') ? SMTP_PASS : ''));
    $from_email = !empty($config['from_email']) ? $config['from_email'] : (!empty($settings['smtp_from_email']) ? $settings['smtp_from_email'] : (!empty($user) ? $user : (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admissions@ksvacademy.com')));
    $from_name = !empty($config['from_name']) ? $config['from_name'] : (!empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'KSV Academy'));

    if (empty($user) || empty($pass)) {
        $diag_error = 'SMTP credentials (username and password) are not configured';
        return false;
    }

    $timeout = 15;
    $connect_target = ($encryption === 'ssl' || $port === 465) ? 'ssl://' . $host : 'tcp://' . $host;
    
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client($connect_target . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        $diag_error = "Could not connect to {$host}:{$port} ({$errstr})";
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $read_resp = function() use ($socket) {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $data .= $line;
            if (preg_match('/^\d{3}\s/', $line)) break;
        }
        return $data;
    };

    $send_cmd = function($cmd, $expected_code) use ($socket, $read_resp, &$diag_error) {
        fwrite($socket, $cmd . "\r\n");
        $resp = $read_resp();
        $code = substr(trim($resp), 0, 3);
        if ($code !== (string)$expected_code) {
            $diag_error = "SMTP command error: " . trim($resp);
            return false;
        }
        return true;
    };

    // 1. Initial Greeting
    $greeting = $read_resp();
    if (substr(trim($greeting), 0, 3) !== '220') {
        $diag_error = "SMTP greeting rejected: " . trim($greeting);
        fclose($socket);
        return false;
    }

    // 2. EHLO
    $client_host = !empty($_SERVER['HTTP_HOST']) ? preg_replace('/:[0-9]+$/', '', $_SERVER['HTTP_HOST']) : 'localhost';
    if (!$send_cmd("EHLO " . $client_host, 250)) {
        fclose($socket);
        return false;
    }

    // 3. STARTTLS
    if ($encryption === 'tls' && $port !== 465) {
        if (!$send_cmd("STARTTLS", 220)) {
            fclose($socket);
            return false;
        }
        $crypto_ok = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$crypto_ok) {
            $diag_error = "Failed to establish TLS encryption with {$host}";
            fclose($socket);
            return false;
        }
        if (!$send_cmd("EHLO " . $client_host, 250)) {
            fclose($socket);
            return false;
        }
    }

    // 4. AUTH LOGIN
    if (!$send_cmd("AUTH LOGIN", 334)) {
        fclose($socket);
        return false;
    }
    if (!$send_cmd(base64_encode($user), 334)) {
        fclose($socket);
        return false;
    }
    if (!$send_cmd(base64_encode(str_replace(' ', '', $pass)), 235)) {
        fclose($socket);
        return false;
    }

    // 5. Envelope
    if (!$send_cmd("MAIL FROM: <" . $from_email . ">", 250)) {
        fclose($socket);
        return false;
    }
    if (!$send_cmd("RCPT TO: <" . $to . ">", 250)) {
        fclose($socket);
        return false;
    }

    // 6. DATA
    if (!$send_cmd("DATA", 354)) {
        fclose($socket);
        return false;
    }

    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encoded_from_name = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
    $msg_id = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@' . $client_host . '>';
    $date = date('r');

    $headers = [
        "Date: {$date}",
        "Message-ID: {$msg_id}",
        "From: {$encoded_from_name} <{$from_email}>",
        "Reply-To: <{$from_email}>",
        "To: <{$to}>",
        "Subject: {$encoded_subject}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
        "X-Mailer: KSV-Academy-Portal"
    ];

    $clean_body = str_replace(["\r\n", "\r"], "\n", $html_body);
    $stuffed_body = preg_replace('/^\./m', '..', $clean_body);
    $stuffed_body = str_replace("\n", "\r\n", $stuffed_body);

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . $stuffed_body . "\r\n.";
    fwrite($socket, $payload . "\r\n");

    $data_resp = $read_resp();
    if (substr(trim($data_resp), 0, 3) !== '250') {
        $diag_error = "Message rejected by SMTP: " . trim($data_resp);
        fclose($socket);
        return false;
    }

    // 7. QUIT
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

/**
 * Send Welcome & Login Credentials Email to Student
 *
 * @param array $student Student record from database
 * @param string $username Portal login username or roll number
 * @param string $password Raw initial password
 * @param array &$out_diag Output diagnostic details (success, method, preview_url, error)
 * @return bool True if accepted for delivery, false otherwise
 */
function send_student_welcome_email($student, $username, $password, &$out_diag = null) {
    $out_diag = [
        'success' => false,
        'method' => 'none',
        'preview_url' => '',
        'preview_path' => '',
        'error' => ''
    ];

    $recipient_email = trim($student['student_email'] ?? '');
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        $out_diag['error'] = 'Invalid or missing student email address';
        return false;
    }

    $settings = function_exists('get_school_settings') ? get_school_settings() : [];
    $school_name = $settings['school_name'] ?? 'KSV Academy';
    $tagline = $settings['tagline'] ?? 'Where Learning Meets Excellence';
    $school_email = $settings['email'] ?? 'admissions@ksvacademy.com';
    $school_phone = $settings['phone'] ?? '+91 98765 43210';
    $school_address = $settings['address'] ?? 'KSV Campus, Knowledge Valley Road, Sector 12, Academic Hub';

    // Resolve base site & portal login URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $base_url = rtrim($settings['website'] ?? ($protocol . $host), '/');
    $login_url = $base_url . (defined('HRMS_URL') ? HRMS_URL : '/web-app') . '/login?role=parent';

    $student_name = htmlspecialchars($student['student_name'] ?? 'Student', ENT_QUOTES, 'UTF-8');
    $roll_no = htmlspecialchars($student['roll_no'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $admission_no = htmlspecialchars($student['admission_no'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $standard = htmlspecialchars($student['standard'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $section = htmlspecialchars($student['section'] ?? 'A', ENT_QUOTES, 'UTF-8');
    $parent_name = htmlspecialchars($student['parent_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $safe_username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safe_password = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $current_year = date('Y');

    $subject = "Welcome to {$school_name} – Student Portal Login & Credentials (Roll: {$roll_no})";

    $parent_greeting_html = '';
    if (!empty($parent_name)) {
        $parent_greeting_html = '<p style="margin:0 0 16px; font-size:0.92rem; color:#475569; line-height:1.6;">Greetings to you and your parent/guardian <strong>' . $parent_name . '</strong>.</p>';
    }

    $html_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to {$school_name}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b; -webkit-font-smoothing:antialiased;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:24px 12px;">
    <tr>
      <td align="center">
        <!-- Main Card Container -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 25px -5px rgba(15,23,42,0.08), 0 8px 10px -6px rgba(15,23,42,0.04); border:1px solid #e2e8f0;">
          
          <!-- Institutional Header Banner -->
          <tr>
            <td style="background:linear-gradient(135deg, #0a2540 0%, #004b87 50%, #0092df 100%); padding:32px 28px; text-align:center;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center">
                    <div style="display:inline-block; background:#ffffff; border-radius:14px; padding:10px 18px; margin-bottom:14px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                      <span style="font-size:1.3rem; font-weight:900; letter-spacing:0.5px; color:#0a2540;">🎓 {$school_name}</span>
                    </div>
                    <h1 style="margin:0 0 6px; color:#ffffff; font-size:1.55rem; font-weight:800; letter-spacing:-0.3px;">Welcome to KSV Academy</h1>
                    <p style="margin:0; color:#e0f2fe; font-size:0.88rem; font-weight:500; letter-spacing:0.2px;">{$tagline}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Welcome Content -->
          <tr>
            <td style="padding:32px 32px 20px;">
              <p style="margin:0 0 12px; font-size:1.05rem; font-weight:700; color:#0f172a;">
                Dear {$student_name},
              </p>
              {$parent_greeting_html}
              <p style="margin:0 0 20px; font-size:0.92rem; color:#475569; line-height:1.65;">
                We are pleased to welcome you to <strong>{$school_name}</strong>! Your official student account has been created for the academic session. You can now securely sign in to access your daily attendance, examination reports, class timetable, fee statements, and digital student identity.
              </p>

              <!-- Academic Profile Summary Card -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:24px; padding:16px;">
                <tr>
                  <td style="padding:6px 12px; font-size:0.84rem; color:#64748b; width:40%;">Student Name:</td>
                  <td style="padding:6px 12px; font-size:0.88rem; font-weight:700; color:#0f172a;">{$student_name}</td>
                </tr>
                <tr>
                  <td style="padding:6px 12px; font-size:0.84rem; color:#64748b; border-top:1px dashed #e2e8f0;">Roll Number:</td>
                  <td style="padding:6px 12px; font-size:0.88rem; font-weight:700; color:#0284c7; border-top:1px dashed #e2e8f0;">{$roll_no}</td>
                </tr>
                <tr>
                  <td style="padding:6px 12px; font-size:0.84rem; color:#64748b; border-top:1px dashed #e2e8f0;">Class &amp; Section:</td>
                  <td style="padding:6px 12px; font-size:0.88rem; font-weight:700; color:#0f172a; border-top:1px dashed #e2e8f0;">{$standard} &bull; Section {$section}</td>
                </tr>
                <tr>
                  <td style="padding:6px 12px; font-size:0.84rem; color:#64748b; border-top:1px dashed #e2e8f0;">Admission No:</td>
                  <td style="padding:6px 12px; font-size:0.88rem; font-weight:600; color:#475569; border-top:1px dashed #e2e8f0;">{$admission_no}</td>
                </tr>
              </table>

              <!-- Credentials Callout Box -->
              <div style="background:#eff6ff; border:2px solid #0092df; border-radius:14px; padding:22px 24px; margin-bottom:26px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td>
                      <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                        <span style="font-size:1.15rem;">🔐</span>
                        <span style="font-size:0.95rem; font-weight:800; color:#0a2540; text-transform:uppercase; letter-spacing:0.5px;">Your Portal Login Credentials</span>
                      </div>
                      
                      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;">
                        <tr>
                          <td style="padding:8px 0; font-size:0.85rem; color:#475569; width:35%;">Portal Role:</td>
                          <td style="padding:8px 0; font-size:0.9rem; font-weight:800; color:#0a2540;">Parent / Student</td>
                        </tr>
                        <tr>
                          <td style="padding:8px 0; font-size:0.85rem; color:#475569; border-top:1px solid #dbeafe;">Username:</td>
                          <td style="padding:8px 0; font-size:1rem; font-weight:800; color:#0284c7; font-family:Courier, monospace; border-top:1px solid #dbeafe;">
                            <span style="background:#ffffff; border:1px solid #bfdbfe; border-radius:6px; padding:4px 10px; display:inline-block;">{$safe_username}</span>
                          </td>
                        </tr>
                        <tr>
                          <td style="padding:8px 0; font-size:0.85rem; color:#475569; border-top:1px solid #dbeafe;">Initial Password:</td>
                          <td style="padding:8px 0; font-size:1rem; font-weight:800; color:#0f172a; font-family:Courier, monospace; border-top:1px solid #dbeafe;">
                            <span style="background:#ffffff; border:1px solid #bfdbfe; border-radius:6px; padding:4px 10px; display:inline-block; letter-spacing:1px;">{$safe_password}</span>
                          </td>
                        </tr>
                      </table>

                      <p style="margin:14px 0 0; font-size:0.78rem; color:#64748b; line-height:1.5;">
                        <em>Tip: You can sign in using either your Username (<strong>{$safe_username}</strong>) or your Roll Number (<strong>{$roll_no}</strong>). For security, we recommend changing your password after your first login.</em>
                      </p>
                    </td>
                  </tr>
                </table>
              </div>

              <!-- Action Button CTA -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
                <tr>
                  <td align="center">
                    <a href="{$login_url}" target="_blank" rel="noopener noreferrer" style="display:inline-block; background:linear-gradient(135deg, #0092df 0%, #0a2540 100%); color:#ffffff; text-decoration:none; font-size:0.95rem; font-weight:800; padding:14px 34px; border-radius:30px; box-shadow:0 6px 18px rgba(0,146,223,0.35); letter-spacing:0.2px;">
                      Sign In to Student Portal &rarr;
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Student Portal Features Checklist -->
              <div style="border-top:1px solid #e2e8f0; padding-top:20px; margin-bottom:20px;">
                <h4 style="margin:0 0 12px; font-size:0.86rem; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.5px;">Portal Features Available To You:</h4>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:0.84rem; color:#475569;">
                  <tr>
                    <td style="padding:4px 0; width:50%;">&#10004; Biometric attendance tracking</td>
                    <td style="padding:4px 0; width:50%;">&#10004; Examination marksheets &amp; grades</td>
                  </tr>
                  <tr>
                    <td style="padding:4px 0;">&#10004; Fee receipts &amp; outstanding dues</td>
                    <td style="padding:4px 0;">&#10004; Digital ID card &amp; timetable</td>
                  </tr>
                </table>
              </div>

              <!-- Support Section -->
              <div style="background:#f8fafc; border-radius:10px; padding:14px 18px; font-size:0.82rem; color:#64748b; line-height:1.5;">
                <strong>Need Assistance?</strong> If you have questions or trouble accessing your account, please reach our academy office at <a href="mailto:{$school_email}" style="color:#0284c7; text-decoration:none;">{$school_email}</a> or call <strong>{$school_phone}</strong>.
              </div>

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#0a2540; padding:22px 28px; text-align:center; color:#94a3b8; font-size:0.75rem; line-height:1.5;">
              <p style="margin:0 0 6px; color:#cbd5e1; font-weight:600;">{$school_name} &bull; {$school_address}</p>
              <p style="margin:0 0 10px;">&copy; {$current_year} {$school_name}. All rights reserved.</p>
              <p style="margin:0; font-size:0.7rem; color:#64748b;">
                Confidentiality Notice: This automated notification contains individual academic portal credentials intended solely for {$student_name}.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    // 1. Save rendered HTML preview to web-app/uploads/sent_emails/
    $stu_id = intval($student['id'] ?? 0);
    $sent_dir = __DIR__ . '/uploads/sent_emails';
    if (!is_dir($sent_dir)) {
        @mkdir($sent_dir, 0777, true);
    }
    $preview_filename = 'welcome_student_' . $stu_id . '.html';
    $preview_path = $sent_dir . '/' . $preview_filename;
    @file_put_contents($preview_path, $html_body);

    $archive_filename = 'welcome_student_' . $stu_id . '_' . time() . '.html';
    @file_put_contents($sent_dir . '/' . $archive_filename, $html_body);

    $preview_url = (defined('HRMS_URL') ? HRMS_URL : '/web-app') . '/uploads/sent_emails/' . $preview_filename;
    $out_diag['preview_url'] = $preview_url;
    $out_diag['preview_path'] = $preview_path;

    // 2. Outbound Dispatch: Check if SMTP credentials are configured
    $smtp_user = !empty($settings['smtp_user']) ? $settings['smtp_user'] : (defined('SMTP_USER') ? SMTP_USER : '');
    $smtp_pass = !empty($settings['smtp_pass']) ? $settings['smtp_pass'] : (defined('SMTP_PASS') ? SMTP_PASS : '');

    if (!empty($smtp_user) && !empty($smtp_pass)) {
        $diag_err = '';
        $smtp_ok = ksv_send_smtp_mail($recipient_email, $subject, $html_body, $diag_err);
        if ($smtp_ok) {
            $out_diag['success'] = true;
            $out_diag['method'] = 'smtp';
            return true;
        } else {
            $out_diag['success'] = false;
            $out_diag['method'] = 'smtp';
            $out_diag['error'] = 'SMTP dispatch failed: ' . $diag_err;
            error_log("send_student_welcome_email SMTP error: " . $diag_err);
            return false;
        }
    }

    // 3. Fallback when SMTP is not configured
    // On localhost/macOS without SMTP, emails cannot reach external inboxes like Gmail.
    $out_diag['success'] = false;
    $out_diag['method'] = 'local_preview';
    $out_diag['error'] = 'SMTP credentials not configured. Outbound emails to external inboxes like Gmail require SMTP settings in School Settings or config.php.';
    return false;
}

/**
 * Send Welcome & Login Credentials Email to Faculty / Staff Member
 *
 * @param array $staff Staff profile and user details from database
 * @param string $username Portal login username
 * @param string $password Raw initial password
 * @param array &$out_diag Output diagnostic details (success, method, preview_url, error)
 * @return bool True if accepted for delivery, false otherwise
 */
function send_staff_welcome_email($staff, $username, $password, &$out_diag = null) {
    $out_diag = [
        'success' => false,
        'method' => 'none',
        'preview_url' => '',
        'preview_path' => '',
        'error' => ''
    ];

    $recipient_email = trim($staff['email'] ?? ($staff['user_email'] ?? ''));
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        $out_diag['error'] = 'Invalid or missing faculty email address';
        return false;
    }

    $settings = function_exists('get_school_settings') ? get_school_settings() : [];
    $school_name = $settings['school_name'] ?? 'KSV Academy';
    $tagline = $settings['tagline'] ?? 'Where Learning Meets Excellence';
    $school_email = $settings['email'] ?? 'admissions@ksvacademy.com';
    $school_phone = $settings['phone'] ?? '+91 98765 43210';
    $school_address = $settings['address'] ?? 'KSV Campus, Knowledge Valley Road, Sector 12, Academic Hub';

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $base_url = rtrim($settings['website'] ?? ($protocol . $host), '/');
    $login_url = $base_url . (defined('HRMS_URL') ? HRMS_URL : '/web-app') . '/login?role=staff';

    $full_name = htmlspecialchars($staff['full_name'] ?? 'Faculty Member', ENT_QUOTES, 'UTF-8');
    $emp_code = htmlspecialchars($staff['employee_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $department = htmlspecialchars($staff['department'] ?? 'Academic Department', ENT_QUOTES, 'UTF-8');
    $designation = htmlspecialchars($staff['designation'] ?? 'Lecturer', ENT_QUOTES, 'UTF-8');
    $qualification = htmlspecialchars($staff['qualification'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $safe_username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safe_password = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $current_year = date('Y');

    $subject = "Welcome to KSV Academy Faculty – Portal Login Credentials (ID: {$emp_code})";

    $html_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to KSV Academy Faculty &amp; Staff</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b; -webkit-font-smoothing:antialiased;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:24px 12px;">
    <tr>
      <td align="center">
        <!-- Main Card Container -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 25px -5px rgba(15,23,42,0.08), 0 8px 10px -6px rgba(15,23,42,0.04); border:1px solid #e2e8f0;">
          
          <!-- Faculty Header Banner -->
          <tr>
            <td style="background:linear-gradient(135deg, #0a2540 0%, #004b87 50%, #0092df 100%); padding:32px 28px; text-align:center;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center">
                    <div style="display:inline-block; background:#ffffff; border-radius:14px; padding:10px 18px; margin-bottom:14px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                      <span style="font-size:1.3rem; font-weight:900; letter-spacing:0.5px; color:#0a2540;">🎓 {$school_name}</span>
                    </div>
                    <h1 style="margin:0 0 6px; color:#ffffff; font-size:1.5rem; font-weight:800; letter-spacing:-0.3px;">Faculty &amp; Workforce Portal</h1>
                    <p style="margin:0; color:#e0f2fe; font-size:0.88rem; font-weight:500; letter-spacing:0.2px;">{$tagline}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Welcome Content -->
          <tr>
            <td style="padding:32px 32px 20px;">
              <p style="margin:0 0 12px; font-size:1.05rem; font-weight:700; color:#0f172a;">
                Dear {$full_name},
              </p>
              <p style="margin:0 0 20px; font-size:0.92rem; color:#475569; line-height:1.65;">
                Welcome to the <strong>{$school_name}</strong> academic and administrative team! Your official faculty member portal account has been created. You can now securely sign in to manage student attendance, lecture periods schedule, examination marks &amp; grading rubrics, leave applications, and institutional payroll records.
              </p>

              <!-- Faculty Profile Summary Card -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:24px; padding:16px;">
                <tr>
                  <td style="padding:6px 12px; font-size:0.84rem; color:#64748b; width:40%;">Faculty Name:</td>
                  <td style="padding:6px 12px; font-size:0.88rem; font-weight:700; color:#0f172a;">{$full_name}</td>
                </tr>
                <tr>
                  <td style="padding:6px 12px; font-size:0.84rem; color:#64748b; border-top:1px dashed #e2e8f0;">Employee Code:</td>
                  <td style="padding:6px 12px; font-size:0.88rem; font-weight:700; color:#0284c7; border-top:1px dashed #e2e8f0;">{$emp_code}</td>
                </tr>
                <tr>
                  <td style="padding:6px 12px; font-size:0.84rem; color:#64748b; border-top:1px dashed #e2e8f0;">Department:</td>
                  <td style="padding:6px 12px; font-size:0.88rem; font-weight:700; color:#0f172a; border-top:1px dashed #e2e8f0;">{$department}</td>
                </tr>
                <tr>
                  <td style="padding:6px 12px; font-size:0.84rem; color:#64748b; border-top:1px dashed #e2e8f0;">Designation:</td>
                  <td style="padding:6px 12px; font-size:0.88rem; font-weight:600; color:#475569; border-top:1px dashed #e2e8f0;">{$designation}</td>
                </tr>
                <tr>
                  <td style="padding:6px 12px; font-size:0.84rem; color:#64748b; border-top:1px dashed #e2e8f0;">Qualification:</td>
                  <td style="padding:6px 12px; font-size:0.88rem; font-weight:600; color:#475569; border-top:1px dashed #e2e8f0;">{$qualification}</td>
                </tr>
              </table>

              <!-- Credentials Callout Box -->
              <div style="background:#eff6ff; border:2px solid #0092df; border-radius:14px; padding:22px 24px; margin-bottom:26px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td>
                      <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                        <span style="font-size:1.15rem;">🔐</span>
                        <span style="font-size:0.95rem; font-weight:800; color:#0a2540; text-transform:uppercase; letter-spacing:0.5px;">Your Faculty Portal Credentials</span>
                      </div>
                      
                      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;">
                        <tr>
                          <td style="padding:8px 0; font-size:0.85rem; color:#475569; width:35%;">Portal Role:</td>
                          <td style="padding:8px 0; font-size:0.9rem; font-weight:800; color:#0a2540;">Staff / Faculty</td>
                        </tr>
                        <tr>
                          <td style="padding:8px 0; font-size:0.85rem; color:#475569; border-top:1px solid #dbeafe;">Username / ID:</td>
                          <td style="padding:8px 0; font-size:1rem; font-weight:800; color:#0284c7; font-family:Courier, monospace; border-top:1px solid #dbeafe;">
                            <span style="background:#ffffff; border:1px solid #bfdbfe; border-radius:6px; padding:4px 10px; display:inline-block;">{$safe_username}</span>
                          </td>
                        </tr>
                        <tr>
                          <td style="padding:8px 0; font-size:0.85rem; color:#475569; border-top:1px solid #dbeafe;">Initial Password:</td>
                          <td style="padding:8px 0; font-size:1rem; font-weight:800; color:#0f172a; font-family:Courier, monospace; border-top:1px solid #dbeafe;">
                            <span style="background:#ffffff; border:1px solid #bfdbfe; border-radius:6px; padding:4px 10px; display:inline-block; letter-spacing:1px;">{$safe_password}</span>
                          </td>
                        </tr>
                      </table>

                      <p style="margin:14px 0 0; font-size:0.78rem; color:#64748b; line-height:1.5;">
                        <em>Tip: You can sign in using either your Username (<strong>{$safe_username}</strong>) or Employee Code (<strong>{$emp_code}</strong>). For security, please update your password after your first login.</em>
                      </p>
                    </td>
                  </tr>
                </table>
              </div>

              <!-- Action Button CTA -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
                <tr>
                  <td align="center">
                    <a href="{$login_url}" target="_blank" rel="noopener noreferrer" style="display:inline-block; background:linear-gradient(135deg, #0092df 0%, #0a2540 100%); color:#ffffff; text-decoration:none; font-size:0.95rem; font-weight:800; padding:14px 34px; border-radius:30px; box-shadow:0 6px 18px rgba(0,146,223,0.35); letter-spacing:0.2px;">
                      Sign In to Faculty Portal &rarr;
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Faculty Portal Features Checklist -->
              <div style="border-top:1px solid #e2e8f0; padding-top:20px; margin-bottom:20px;">
                <h4 style="margin:0 0 12px; font-size:0.86rem; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.5px;">Faculty Portal Capabilities:</h4>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:0.84rem; color:#475569;">
                  <tr>
                    <td style="padding:4px 0; width:50%;">&#10004; Mark daily student attendance</td>
                    <td style="padding:4px 0; width:50%;">&#10004; Enter examination scores &amp; grading</td>
                  </tr>
                  <tr>
                    <td style="padding:4px 0;">&#10004; Check allocated lecture periods</td>
                    <td style="padding:4px 0;">&#10004; View payslips &amp; submit leave requests</td>
                  </tr>
                </table>
              </div>

              <!-- Support Section -->
              <div style="background:#f8fafc; border-radius:10px; padding:14px 18px; font-size:0.82rem; color:#64748b; line-height:1.5;">
                <strong>Administrative Helpdesk:</strong> For portal technical assistance or account inquiries, contact the administration office at <a href="mailto:{$school_email}" style="color:#0284c7; text-decoration:none;">{$school_email}</a> or call <strong>{$school_phone}</strong>.
              </div>

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#0a2540; padding:22px 28px; text-align:center; color:#94a3b8; font-size:0.75rem; line-height:1.5;">
              <p style="margin:0 0 6px; color:#cbd5e1; font-weight:600;">{$school_name} &bull; {$school_address}</p>
              <p style="margin:0 0 10px;">&copy; {$current_year} {$school_name}. All rights reserved.</p>
              <p style="margin:0; font-size:0.7rem; color:#64748b;">
                Confidentiality Notice: This automated message contains official faculty credentials intended solely for {$full_name}.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    // 1. Save rendered HTML preview
    $staff_id = intval($staff['id'] ?? 0);
    $sent_dir = __DIR__ . '/uploads/sent_emails';
    if (!is_dir($sent_dir)) {
        @mkdir($sent_dir, 0777, true);
    }
    $preview_filename = 'welcome_staff_' . $staff_id . '.html';
    $preview_path = $sent_dir . '/' . $preview_filename;
    @file_put_contents($preview_path, $html_body);

    $archive_filename = 'welcome_staff_' . $staff_id . '_' . time() . '.html';
    @file_put_contents($sent_dir . '/' . $archive_filename, $html_body);

    $preview_url = (defined('HRMS_URL') ? HRMS_URL : '/web-app') . '/uploads/sent_emails/' . $preview_filename;
    $out_diag['preview_url'] = $preview_url;
    $out_diag['preview_path'] = $preview_path;

    // 2. Outbound Dispatch: Check if SMTP credentials configured
    $smtp_user = !empty($settings['smtp_user']) ? $settings['smtp_user'] : (defined('SMTP_USER') ? SMTP_USER : '');
    $smtp_pass = !empty($settings['smtp_pass']) ? $settings['smtp_pass'] : (defined('SMTP_PASS') ? SMTP_PASS : '');

    if (!empty($smtp_user) && !empty($smtp_pass)) {
        $diag_err = '';
        $smtp_ok = ksv_send_smtp_mail($recipient_email, $subject, $html_body, $diag_err);
        if ($smtp_ok) {
            $out_diag['success'] = true;
            $out_diag['method'] = 'smtp';
            return true;
        } else {
            $out_diag['success'] = false;
            $out_diag['method'] = 'smtp';
            $out_diag['error'] = 'SMTP dispatch failed: ' . $diag_err;
            error_log("send_staff_welcome_email SMTP error: " . $diag_err);
            return false;
        }
    }

    // 3. Fallback when SMTP is not configured
    $out_diag['success'] = false;
    $out_diag['method'] = 'local_preview';
    $out_diag['error'] = 'SMTP credentials not configured. Outbound emails to external inboxes require SMTP settings in School Settings or config.php.';
    return false;
}

/**
 * Default Staff Permissions
 */
if (!defined('DEFAULT_STAFF_PERMISSIONS')) {
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
}

/**
 * Default Student / Parent Permissions
 */
if (!defined('DEFAULT_STUDENT_PERMISSIONS')) {
    define('DEFAULT_STUDENT_PERMISSIONS', [
        'dashboard',
        'attendance',
        'fees',
        'student-id',
        'marksheet',
        'announcements',
        'settings'
    ]);
}

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
        'theme_mode' => 'light',
        'smtp_enabled' => 'no',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_from_email' => 'admissions@ksvacademy.com',
        'smtp_from_name' => 'KSV Academy'
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
 * CSRF Protection Helpers
 */
if (!function_exists('get_csrf_token')) {
    function get_csrf_token() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], (string)$token);
    }
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

