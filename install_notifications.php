<?php
require_once 'includes/db.php';
require_once 'includes/notifications.php';

try {
    $pdo->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(255),
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (is_read),
        CONSTRAINT fk_notifications_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add email_prefs column if not exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_prefs'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_prefs TEXT DEFAULT NULL COMMENT 'JSON: preference for email notifications'");
        echo "Column 'email_prefs' added to users table.<br>";
    }

    echo "Notification System Installed Successfully! <br>";
    echo "<a href='index.php'>Back to Home</a>";

} catch (PDOException $e) {
    die("Installation Failed: " . $e->getMessage());
}
?>