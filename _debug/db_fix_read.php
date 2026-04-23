<?php
/**
 * Database Fix: Add is_read column to chat_messages table
 */
require_once 'includes/db.php';

$pdo = get_pdo();

if ($pdo) {
    try {
        // 1. Check/Add is_read to chat_messages
        $check = $pdo->query("SHOW COLUMNS FROM chat_messages LIKE 'is_read'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE chat_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
            echo "<h2 style='color: green;'>✅ สำเร็จ! เพิ่มคอลัมน์ is_read ใน chat_messages เรียบร้อยแล้ว</h2>";
        } else {
            echo "<h2 style='color: orange;'>ℹ️ คอลัมน์ is_read ใน chat_messages มีอยู่ในระบบแล้ว</h2>";
        }

        // 2. Initialize notifications table just in case (optional but helpful)
        require_once 'includes/notifications.php';
        if (function_exists('init_notification_tables')) {
            init_notification_tables($pdo);
            echo "<p>✅ ตรวจสอบ/เพิ่มตาราง notifications เรียบร้อยแล้ว</p>";
        }

        echo "<p><a href='chat.php'>กลับไปที่กล่องข้อความ</a></p>";
    } catch (PDOException $e) {
        echo "<h2 style='color: red;'>❌ เกิดข้อผิดพลาด:</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
} else {
    echo "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
}
?>
