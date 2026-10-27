<?php
$req_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$sub = trim(str_replace('/web-app', '', $req_path), '/');
if (!empty($sub)) {
    $file = __DIR__ . '/' . basename($sub) . '.php';
    if (file_exists($file)) {
        require $file;
        exit;
    }
}
require_once __DIR__ . '/config.php';

$user = get_logged_user();
if ($user) {
    header('Location: ' . get_dashboard_url($user['role']));
    exit;
}

header('Location: ' . HRMS_URL . '/login');
exit;
