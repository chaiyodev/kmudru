<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
require_once '../includes/rate_limit.php';

check_rate_limit('notif_mark', 60, 20);

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$uid = $_SESSION['user_id'];
$pdo = get_pdo();

if (!$pdo) {
    echo json_encode(['error' => 'DB Connection failed']);
    exit;
}

try {
    // 1. We mark only *currently existing* notifications as seen in dropdown
    // to provide the 'count disappears' effect user with the 'number must disappear' request.
    mark_all_read($pdo, $uid);
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    error_log("Mark-read API error: " . $e->getMessage());
    echo json_encode(['error' => 'เกิดข้อผิดพลาดภายในระบบ']);
}
