<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        // 1. Create community_announcements table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS community_announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                community_id INT NOT NULL,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 2. Add last_activity to users table if not exists
        $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_activity'")->fetch();
        if (!$result) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL");
        }

        // 3. Add community_id and status to community_posts if not exists (checking existing schema from cop_view.php)
        // From cop_view.php it seems community_posts already exists. 
        // Let's ensure it has a good structure if it's missing from migrate_cop.php
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS community_posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                community_id INT NOT NULL,
                user_id INT NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        echo "Migration Successful! Added announcements table and activity tracking.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Database connection failed.";
}
?>