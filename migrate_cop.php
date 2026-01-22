<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS communities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                icon VARCHAR(50) DEFAULT 'users',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS community_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                community_id INT,
                user_id INT,
                role ENUM('leader', 'member') DEFAULT 'member',
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_member (community_id, user_id),
                FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");

        $check = $pdo->query("SELECT COUNT(*) FROM communities")->fetchColumn();
        if ($check == 0) {
            $pdo->exec("
                INSERT INTO communities (name, description, icon) VALUES 
                ('AI & Data Science', 'กลุ่มแลกเปลี่ยนเรียนรู้ด้าน AI และวิทยาการข้อมูล เพื่อพัฒนางานวิจัยและบริการวิชาการ', 'brain'),
                ('UDRU Innovation', 'กลุ่มขับเคลื่อนนวัตกรรมและเทคโนโลยีดิจิทัลภายในมหาวิทยาลัย', 'lightbulb'),
                ('Academic Excellence', 'เทคนิคการเขียนบทความ และการเตรียมตัวเข้าสู่ตำแหน่งทางวิชาการ', 'award'),
                ('Smart Administration', 'กลุ่มพัฒนาทักษะการบริหารจัดการสมัยใหม่และระบบ Paperless', 'monitor');
            ");
        }
        echo "Migration Successful!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Database connection failed.";
}
?>