<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        echo "<h2>Phase 9: Full LMS Suite Setup...</h2>";

        // 1. Quizzes Table (Stores questions for a course)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quizzes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                question TEXT NOT NULL,
                options JSON NOT NULL, -- Stores options as ['A'=>'...', 'B'=>'...']
                correct_answer VARCHAR(10) NOT NULL, -- e.g. 'A', 'B'
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (course_id) REFERENCES trainings(id) ON DELETE CASCADE
            );
        ");
        echo "✅ Created 'quizzes' table.<br>";

        // 2. Quiz Results (Tracks user scores)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_quiz_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                course_id INT NOT NULL,
                score INT NOT NULL,
                total_questions INT NOT NULL,
                passed BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (course_id) REFERENCES trainings(id) ON DELETE CASCADE
            );
        ");
        echo "✅ Created 'user_quiz_results' table.<br>";

        // 3. Certificates (Issued upon passing)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS certificates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                course_id INT NOT NULL,
                certificate_code VARCHAR(50) NOT NULL UNIQUE,
                issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (course_id) REFERENCES trainings(id) ON DELETE CASCADE
            );
        ");
        echo "✅ Created 'certificates' table.<br>";

        // 4. Survey Responses (Feedback)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS survey_responses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                course_id INT NOT NULL,
                rating INT NOT NULL COMMENT '1-5 Stars',
                feedback TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (course_id) REFERENCES trainings(id) ON DELETE CASCADE
            );
        ");
        echo "✅ Created 'survey_responses' table.<br>";

        echo "<h3>Phase 9 Setup Complete!</h3>";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>
<br><a href="training.php">Back to Dashboard</a>