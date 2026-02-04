<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        echo "<h2>Updating Database for Experts and Logs...</h2>";

        // 1. Update activity_logs for 'details' column
        $pdo->exec("ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS details TEXT AFTER target_type");
        echo "✅ Updated 'activity_logs' with 'details' column.<br>";

        // 2. Create visitor_stats table if not exists
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
        echo "✅ Created 'visitor_stats' table.<br>";

        // 3. Update users table for better expert profiles
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS portfolio TEXT AFTER specialty");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email");
        echo "✅ Updated 'users' table with portfolio and phone columns.<br>";

        echo "<h3>Migration Successful!</h3>";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>
<br><a href="index.php">Back to Dashboard</a>