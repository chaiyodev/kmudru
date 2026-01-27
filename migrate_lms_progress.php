<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        echo "<h2>Upgrading to LMS Phase 2 (Progress Tracking)...</h2>";

        // Create course_progress table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_progress (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                lesson_id INT NOT NULL,
                course_id INT NOT NULL,
                completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_progress (user_id, lesson_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (lesson_id) REFERENCES course_lessons(id) ON DELETE CASCADE,
                FOREIGN KEY (course_id) REFERENCES trainings(id) ON DELETE CASCADE
            );
        ");
        echo "✅ Created 'course_progress' table.<br>";

        echo "<h3>LMS Progress Upgrade Complete!</h3>";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>
<br><a href="training.php">Back to Courses</a>