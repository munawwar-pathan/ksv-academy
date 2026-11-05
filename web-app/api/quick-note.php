<?php
// Defense-in-depth headers
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
}

require_once __DIR__ . '/../config.php';

$user = get_logged_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = intval($user['id']);
$db = get_db_connection();

// Allowed: Super Admin, or Staff with quick_note / note permission
$can_use_note = false;
if ($user['role'] === 'super_admin') {
    $can_use_note = true;
} elseif ($user['role'] === 'staff') {
    $perms = get_staff_permissions($user_id);
    if (in_array('quick_note', $perms, true) || in_array('note', $perms, true)) {
        $can_use_note = true;
    }
}

if (!$can_use_note) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied: Quick Note access required']);
    exit;
}

// GET: Retrieve user quick note
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("SELECT note_content, updated_at FROM admin_quick_notes WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'note_content' => $row ? ($row['note_content'] ?? '') : '',
        'updated_at' => $row ? $row['updated_at'] : null
    ]);
    exit;
}

// POST: Save user quick note
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token']);
        exit;
    }

    $content = (string)($_POST['note_content'] ?? '');
    // Bound content to safe length (100,000 characters)
    if (mb_strlen($content) > 100000) {
        $content = mb_substr($content, 0, 100000);
    }

    $stmt = $db->prepare("
        INSERT INTO admin_quick_notes (user_id, note_content, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE note_content = VALUES(note_content), updated_at = NOW()
    ");
    $stmt->execute([$user_id, $content]);

    echo json_encode([
        'success' => true,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
exit;

