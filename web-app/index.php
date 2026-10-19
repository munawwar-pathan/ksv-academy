<?php
require_once __DIR__ . '/config.php';

$user = get_logged_user();
if ($user) {
    header('Location: ' . get_dashboard_url($user['role']));
    exit;
}

header('Location: ' . HRMS_URL . '/login');
exit;
