<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        echo "<h2>Upgrading to LMS (Phase 8)...</h2>";

        // Create course_lessons table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_lessons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                video_url VARCHAR(255),
                duration VARCHAR(50),
                order_index INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (course_id) REFERENCES trainings(id) ON DELETE CASCADE
            );
        ");
        echo "✅ Created 'course_lessons' table.<br>";

        echo "<h3>LMS Upgrade Complete!</h3>";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>
<br><a href="training.php">Back to Courses</a>