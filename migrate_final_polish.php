<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        echo "<h2>Finalizing System Polish & Performance (Phase 13)...</h2>";

        // 1. Activity Logs Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(255) NOT NULL,
                target_type VARCHAR(50) NULL,
                target_id INT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        echo "✅ Created 'activity_logs' table for real analytics.<br>";

        // 2. Add Performance Indexes
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
            } catch (PDOException $e) {
                echo " - Index '$name' already exists or failed.<br>";
            }
        }

        echo "<h3>All Optimizations Applied!</h3>";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>
<br><a href="index.php">Back to Dashboard</a>