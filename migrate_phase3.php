<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        // 1. Update Documents table for new types and tags
        $pdo->exec("
            ALTER TABLE documents 
            MODIFY COLUMN type ENUM('document', 'wiki', 'qa') DEFAULT 'document',
            ADD COLUMN IF NOT EXISTS tags TEXT;
        ");

        // 2. Update Users table for profiles and points
        $pdo->exec("
            ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS specialty VARCHAR(255),
            ADD COLUMN IF NOT EXISTS bio TEXT,
            ADD COLUMN IF NOT EXISTS points INT DEFAULT 0,
            ADD COLUMN IF NOT EXISTS department VARCHAR(100);
        ");

        // 3. Create document_likes table
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

        // 4. Create chat_messages table
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

        // 5. Create trainings table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS trainings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                duration VARCHAR(50),
                category_id INT,
                link VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            );
        ");

        echo "Phase 3 Migration Successful!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Database connection failed.";
}
?>