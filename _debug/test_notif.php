<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$current_user_id = is_logged_in() ? $_SESSION['user_id'] : 0;

if ($current_user_id) {
    // Send a message from another user to the current user
    $sender_id = ($current_user_id == 1) ? 2 : 1; // Assuming user 1 or 2 exist
    
    // Check if sender exists, otherwise find ANY other user
    $sender_check = $pdo->prepare("SELECT id FROM users WHERE id != ? LIMIT 1");
    $sender_check->execute([$current_user_id]);
    $sender_id = $sender_check->fetchColumn();

    if ($sender_id) {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, is_read) VALUES (?, ?, ?, 0)");
        $stmt->execute([$sender_id, $current_user_id, "นี่เป็นข้อความทดสอบสำหรับการแจ้งเตือน!"]);
        echo "✅ ส่งข้อความทดสอบเรียบร้อยแล้ว! (ID: $current_user_id)";
    } else {
        echo "❌ ไม่พบผู้ใช้ท่านอื่นที่จะส่งข้อความ";
    }
} else {
    echo "❌ กรุณาเข้าสู่ระบบก่อนทดสอบ";
}

echo "<br><br><a href='index.php'>กลับหน้าหลัก</a>";
?>
