<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        $pdo->exec("
            -- Discussion Posts
            CREATE TABLE IF NOT EXISTS community_posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                community_id INT,
                user_id INT,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            -- Resources / Files
            CREATE TABLE IF NOT EXISTS community_resources (
                id INT AUTO_INCREMENT PRIMARY KEY,
                community_id INT,
                user_id INT,
                title VARCHAR(255) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_type VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");
        echo "CoP Features Migration Successful!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>