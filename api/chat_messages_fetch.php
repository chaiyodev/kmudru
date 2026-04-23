<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/rate_limit.php';

check_rate_limit('chat_fetch', 60, 30);

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$target_id = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if ($target_id <= 0) {
    echo json_encode(['messages' => []]);
    exit;
}

$pdo = get_pdo();
if (!$pdo) {
    echo json_encode(['error' => 'DB Connection failed']);
    exit;
}

try {
    // 1. Mark incoming messages as read
    $stmt_read = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt_read->execute([$target_id, $user_id]);

    // 2. Fetch new messages
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND id > ? ORDER BY created_at ASC");
    $stmt->execute([$user_id, $target_id, $target_id, $user_id, $last_id]);
    $messages = $stmt->fetchAll();

    // Map sender info for JS (optional but helpful)
    foreach ($messages as &$m) {
        $m['is_sent'] = ($m['sender_id'] == $user_id);
        $m['time'] = date('H:i', strtotime($m['created_at']));
    }

    echo json_encode(['messages' => $messages]);
} catch (PDOException $e) {
    error_log("Chat API error: " . $e->getMessage());
    echo json_encode(['error' => 'เกิดข้อผิดพลาดภายในระบบ']);
}
