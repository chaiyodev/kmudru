<?php
/**
 * UDRU Wisdom - Database Sync & Fix Tool
 * This script ensures all tables and columns needed for the latest features are present.
 */

require_once 'includes/db.php';
require_once 'includes/notifications.php';

$pdo = get_pdo();

if (!$pdo) {
    die("Database connection failed. Please check includes/config.php");
}

echo "<h2>UDRU Wisdom - Database Sync Tool</h2>";
echo "<ul>";

try {
    // 1. Initialize Notifications
    if (init_notification_tables($pdo)) {
        echo "<li>Notification tables initialized.</li>";
    }

    // 2. Fix activity_logs table (add user_agent if missing)
    $stmt = $pdo->query("SHOW COLUMNS FROM activity_logs LIKE 'user_agent'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE activity_logs ADD COLUMN user_agent VARCHAR(255) AFTER ip_address");
        echo "<li>Added 'user_agent' column to 'activity_logs'.</li>";
    }
    
    // 3. Add 'last_activity' to users table (needed for online status)
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_activity'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "<li>Added 'last_activity' column to 'users' for online status tracking.</li>";
    }

    // 4. Create visitor_stats table if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        page_visited VARCHAR(200),
        referrer VARCHAR(255),
        is_internal TINYINT(1) DEFAULT 0,
        user_id INT DEFAULT NULL,
        visit_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (visit_date),
        INDEX (page_visited)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<li>'visitor_stats' table ready.</li>";

    // 5. Create community_members if missing (referenced in auth.php)
    // (Already in setup.sql, but just in case)
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        community_id INT,
        user_id INT,
        role ENUM('leader', 'moderator', 'member') DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (community_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<li>'community_members' table ready.</li>";

    echo "</ul><p style='color: green; font-weight: bold;'>All systems synchronized successfully!</p>";
    echo "<a href='index.php'>Go to Dashboard</a>";

} catch (PDOException $e) {
    echo "</ul><p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
