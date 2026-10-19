<?php
require_once __DIR__ . '/../config.php';

require_login(['super_admin']);

// Redirect to unified Settings page (Sessions tab)
header('Location: ' . ADMIN_URL . '/school-settings?tab=sessions');
exit;
