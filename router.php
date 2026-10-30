<?php
/**
 * Local Development Router for PHP Built-in Server (`php -S localhost:8000 router.php`)
 * Mirrors the rewrite rules defined in .htaccess for Apache.
 */

// Global Timezone: Indian Standard Time (IST / UTC+05:30)
date_default_timezone_set('Asia/Kolkata');

// Error Handling: Suppress PHP notices and warnings on frontend UI, log errors securely
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);

$raw_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = urldecode($raw_path);
$file = __DIR__ . $path;

// Redirect external requests ending with .php to clean URL (e.g. /web-app/login.php -> /web-app/login)
if (preg_match('#^(/web-app/.*?)\.php$#i', $path, $matches)) {
    $clean_url = $matches[1];
    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: {$clean_url}{$qs}", true, 301);
    exit;
}

// If it's a real file that exists, serve it with correct mime type
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimes = [
        'avif' => 'image/avif',
        'webp' => 'image/webp',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
    ];

    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
        readfile($file);
        return true;
    }

    return false;
}

// If directory with index.php exists, serve it
$dir_index = rtrim($file, '/') . '/index.php';
if (is_dir($file) && file_exists($dir_index)) {
    require $dir_index;
    return true;
}

// Clean URL rewrite to .php file (e.g. /about -> /about.php)
$php_file = __DIR__ . rtrim($path, '/') . '.php';
if ($path !== '/' && file_exists($php_file)) {
    require $php_file;
    return true;
}

// Default to index.php
require __DIR__ . '/index.php';
return true;
