<?php
require_once 'includes/db.php';

$pdo = get_pdo();

if (!$pdo) {
    die("Database connection failed!");
}

try {
    // 1. Create Experts Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS experts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            specialty VARCHAR(255) NOT NULL,
            faculty VARCHAR(255) DEFAULT NULL,
            bio TEXT,
            contact_email VARCHAR(255) DEFAULT NULL,
            available_for_consult BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "<li>Created table: experts</li>";

    // 2. Create Activity Logs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            target_id INT DEFAULT NULL,
            target_type VARCHAR(50) DEFAULT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "<li>Created table: activity_logs</li>";

    // 3. Add Indexes (Performance Optimization)
    // Avoid duplicate key errors by catching exceptions or checking first
    $indexesToCreate = [
        "documents" => ["idx_type_status" => "(type, status)"],
        "trainings" => ["idx_category_id" => "(category_id)"],
        "course_progress" => ["idx_user_id" => "(user_id)"]
    ];

    foreach ($indexesToCreate as $table => $indexes) {
        foreach ($indexes as $indexName => $columns) {
            try {
                $pdo->exec("CREATE INDEX $indexName ON $table $columns");
                echo "<li>Added index $indexName on $table</li>";
            } catch (PDOException $e) {
                // MySQL throws error if index already exists
                echo "<li>Index $indexName on $table may already exist (" . $e->getMessage() . ")</li>";
            }
        }
    }

    echo "<h3>Database schema update completed successfully!</h3>";

} catch (PDOException $e) {
    die("<h3>Error updating database schema: " . $e->getMessage() . "</h3>");
}
?>
