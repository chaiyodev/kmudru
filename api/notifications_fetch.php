<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
require_once '../includes/rate_limit.php';

check_rate_limit('notif_fetch', 60, 30);

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
    // 1. Get total unread count (combined)
    $unread_count = (int)get_unread_count($pdo, $uid);

    // 2. Fetch latest 5 previews (merged system and chat)
    $stmt = $pdo->prepare("SELECT message, created_at, 'notif' as source FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$uid]);
    $sys_notifs = $stmt->fetchAll();

    $stmt_chat = $pdo->prepare("SELECT m.message, m.created_at, 'chat' as source, u.full_name as sender_name FROM chat_messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? AND m.is_read = 0 ORDER BY m.created_at DESC LIMIT 5");
    $stmt_chat->execute([$uid]);
    $chat_notifs = $stmt_chat->fetchAll();

    $previews = array_merge($sys_notifs, $chat_notifs);
    usort($previews, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $previews = array_slice($previews, 0, 5);

    // Format previews for JS
    foreach ($previews as &$p) {
        $p['display_time'] = date('d M H:i', strtotime($p['created_at']));
        $p['link'] = ($p['source'] == 'chat') ? 'chat.php' : 'notifications.php';
        $p['sender_name'] = isset($p['sender_name']) ? $p['sender_name'] : '';
    }

    echo json_encode([
        'unread_count' => $unread_count,
        'previews' => $previews
    ]);
} catch (PDOException $e) {
    error_log("Notifications API error: " . $e->getMessage());
    echo json_encode(['error' => 'เกิดข้อผิดพลาดภายในระบบ']);
}
