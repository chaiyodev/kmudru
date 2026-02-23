<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if (!$pdo) {
    die("Database connection failed. Please ensure MySQL is running and udruwisdom_db exists.");
}

try {
    echo "<h2>🚀 Starting Full System Migration v2...</h2>";

    // 1. Users Table Upgrades
    echo "🔍 Updating 'users' table...<br>";
    $user_cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $missing_user_cols = [
        'avatar' => "VARCHAR(255) DEFAULT NULL",
        'email_prefs' => "TEXT DEFAULT NULL",
        'department' => "VARCHAR(255) DEFAULT NULL",
        'specialty' => "VARCHAR(255) DEFAULT NULL",
        'bio' => "TEXT DEFAULT NULL",
        'points' => "INT DEFAULT 0",
        'portfolio' => "TEXT DEFAULT NULL",
        'phone' => "VARCHAR(20) DEFAULT NULL"
    ];
    foreach ($missing_user_cols as $col => $def) {
        if (!in_array($col, $user_cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $def");
            echo "✅ Added '$col' to 'users'.<br>";
        }
    }

    // 2. Documents Table Upgrades
    echo "🔍 Updating 'documents' table...<br>";
    $doc_cols = $pdo->query("SHOW COLUMNS FROM documents")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('type', $doc_cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN type ENUM('document', 'wiki', 'qa') DEFAULT 'document' AFTER user_id");
        echo "✅ Added 'type' to 'documents'.<br>";
    }
    if (!in_array('tags', $doc_cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN tags TEXT DEFAULT NULL AFTER views");
        echo "✅ Added 'tags' to 'documents'.<br>";
    }

    // 3. Document Likes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT,
            user_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_like (document_id, user_id),
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    echo "✅ Table 'document_likes' is ready.<br>";

    // 4. Chat Messages
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT,
            receiver_id INT,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    echo "✅ Table 'chat_messages' is ready.<br>";

    // 5. Training / LMS Tables
    echo "🔍 Updating 'trainings' table...<br>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS trainings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            duration VARCHAR(50),
            category_id INT,
            link VARCHAR(255),
            thumbnail VARCHAR(255) DEFAULT NULL,
            level ENUM('Beginner', 'Intermediate', 'Advanced') DEFAULT 'Beginner',
            objectives TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        );
    ");

    $training_cols = $pdo->query("SHOW COLUMNS FROM trainings")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_by', $training_cols)) {
        $pdo->exec("ALTER TABLE trainings ADD COLUMN created_by INT DEFAULT NULL AFTER category_id");
        $pdo->exec("ALTER TABLE trainings ADD CONSTRAINT fk_trainings_users FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "✅ Added 'created_by' to 'trainings'.<br>";
    }
    echo "✅ Table 'trainings' is ready.<br>";

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
    echo "✅ Table 'course_lessons' is ready.<br>";

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
    echo "✅ Table 'course_progress' is ready.<br>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS quizzes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            question TEXT NOT NULL,
            options JSON NOT NULL,
            correct_answer VARCHAR(10) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES trainings(id) ON DELETE CASCADE
        );
    ");
    echo "✅ Table 'quizzes' is ready.<br>";

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
    echo "✅ Table 'user_quiz_results' is ready.<br>";

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
    echo "✅ Table 'certificates' is ready.<br>";

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
    echo "✅ Table 'survey_responses' is ready.<br>";

    // 6. Community (CoP) Tables
    echo "🔍 Updating 'communities' table...<br>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS communities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            icon VARCHAR(50) DEFAULT '🤝',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $community_cols = $pdo->query("SHOW COLUMNS FROM communities")->fetchAll(PDO::FETCH_COLUMN);
    $missing_community_cols = [
        'category_id' => "INT DEFAULT NULL",
        'color_theme' => "VARCHAR(20) DEFAULT '#14b8a6'",
        'cover_image' => "VARCHAR(255) DEFAULT NULL",
        'is_public' => "TINYINT(1) DEFAULT 1",
        'author_id' => "INT DEFAULT NULL"
    ];
    foreach ($missing_community_cols as $col => $def) {
        if (!in_array($col, $community_cols)) {
            $pdo->exec("ALTER TABLE communities ADD COLUMN $col $def");
            echo "✅ Added '$col' to 'communities'.<br>";
        }
    }
    echo "✅ Table 'communities' is ready.<br>";

    $pdo->exec("
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
    echo "✅ Table 'community_members' is ready.<br>";

    // 7. Analytics & Logging
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(255) NOT NULL,
            target_type VARCHAR(50) NULL,
            target_id INT NULL,
            details TEXT,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Table 'activity_logs' is ready.<br>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visitor_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            page_visited VARCHAR(255) NULL,
            referrer TEXT NULL,
            is_internal TINYINT(1) DEFAULT 0,
            user_id INT NULL,
            visit_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✅ Table 'visitor_stats' is ready.<br>";

    // 8. Performance Optimization
    echo "🚀 Optimizing database with indexes...<br>";
    $indexes = [
        "idx_docs_type_status" => "CREATE INDEX idx_docs_type_status ON documents(type, status)",
        "idx_docs_cat_status" => "CREATE INDEX idx_docs_cat_status ON documents(category_id, status)",
        "idx_users_role_points" => "CREATE INDEX idx_users_role_points ON users(role, points)",
        "idx_training_cat" => "CREATE INDEX idx_training_cat ON trainings(category_id)",
        "idx_comments_doc" => "CREATE INDEX idx_comments_doc ON comments(document_id)",
        "idx_likes_doc" => "CREATE INDEX idx_likes_doc ON document_likes(document_id)"
    ];
    foreach ($indexes as $name => $sql) {
        try {
            $pdo->exec($sql);
            echo " - Index '$name' created.<br>";
        } catch (PDOException $e) { /* ignore already exists */
        }
    }

    echo "<h3>🎉 Full System Migration v2 Complete!</h3>";

} catch (PDOException $e) {
    die("❌ Migration Error: " . $e->getMessage());
}
?>
<br><a href="index.php">Go to Dashboard</a>