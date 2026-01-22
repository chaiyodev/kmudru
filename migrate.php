<?php
require_once 'includes/db.php';

$pdo = get_pdo();

if (!$pdo) {
    die("Database connection failed.");
}

$queries = [
    // Add type to documents if not exists
    "ALTER TABLE documents ADD COLUMN IF NOT EXISTS type ENUM('document', 'wiki', 'qa') DEFAULT 'document' AFTER user_id",

    // Add user profile fields
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT 'default.png'",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS specialty VARCHAR(100)",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS points INT DEFAULT 0",

    // Create likes table
    "CREATE TABLE IF NOT EXISTS document_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT,
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_like (document_id, user_id)
    )"
];

foreach ($queries as $query) {
    try {
        $pdo->exec($query);
        echo "Success: $query <br>";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
}

echo "Database migration completed.";
