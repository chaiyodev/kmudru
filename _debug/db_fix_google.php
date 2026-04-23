<?php
/**
 * Database Fix: Add google_id column to users table
 */
require_once 'includes/db.php';

$pdo = get_pdo();

if ($pdo) {
    try {
        // Check if column exists
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'");
        if ($check->rowCount() == 0) {
            // Column missing, ADD IT
            $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER role");
            echo "<h2 style='color: green;'>✅ สำเร็จ! เพิ่มคอลัมน์ google_id เรียบร้อยแล้ว</h2>";
            echo "<p>ตอนนี้คุณสามารถไปที่หน้า <a href='login.php'>Login</a> และทดสอบการเข้าด้วย Google ได้เลยครับ!</p>";
        } else {
            echo "<h2 style='color: orange;'>ℹ️ คอลัมน์ google_id มีอยู่ในระบบแล้ว</h2>";
            echo "<p>หากยังมี Error เดิมอยู่ รบกวนแจ้งผมอีกครั้งนะครับ</p>";
        }
    } catch (PDOException $e) {
        echo "<h2 style='color: red;'>❌ เกิดข้อผิดพลาด:</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
} else {
    echo "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
}
?>
