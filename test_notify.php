<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';

$pdo = get_pdo();

// Check if notification table exists and initialize if not
init_notification_tables($pdo);

// Find the admin user or first available user
$stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
$user = $stmt->fetch();

if ($user) {
    $userId = $user['id'];
    $success = create_notification(
        $pdo,
        $userId,
        'system',
        '🎉 การแจ้งเตือนทดสอบระบบ (Test Notification) สำเร็จแล้ว!',
        'notifications.php'
    );

    if ($success) {
        echo "Successfully created test notification for User ID: " . $userId . "\n";
    } else {
        echo "Failed to create notification.\n";
    }
} else {
    echo "No users found in the database to test with.\n";
}
