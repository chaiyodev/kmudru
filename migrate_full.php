<?php
/**
 * UDRU Wisdom - Full Database Migration Script
 * This script handles table creation and column updates.
 * Run this only when upgrading the system or setting up for the first time.
 */

require_once 'includes/db.php';
$pdo = get_pdo();

if (!$pdo) {
    die("Database connection failed.");
}

echo "Starting Migration...<br>";

try {
    // 1. Ensure notifications table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
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
    echo "✔ Notifications table checked/created.<br>";

    // 2. Add missing columns to 'users'
    $user_cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    $cols_to_add = [
        'avatar' => "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL",
        'email_prefs' => "ALTER TABLE users ADD COLUMN email_prefs TEXT DEFAULT NULL",
        'department' => "ALTER TABLE users ADD COLUMN department VARCHAR(255) DEFAULT NULL",
        'google_id' => "ALTER TABLE users ADD COLUMN google_id VARCHAR(100) UNIQUE NULL AFTER password",
        'points' => "ALTER TABLE users ADD COLUMN points INT DEFAULT 0",
        'specialty' => "ALTER TABLE users ADD COLUMN specialty VARCHAR(255) DEFAULT NULL",
        'status' => "ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended') DEFAULT 'active' AFTER role"
    ];

    foreach ($cols_to_add as $col => $sql) {
        if (!in_array($col, $user_cols)) {
            $pdo->exec($sql);
            echo "✔ Added column '$col' to 'users'.<br>";
        }
    }

    // 3. Add missing columns to 'documents'
    $doc_cols = $pdo->query("SHOW COLUMNS FROM documents")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('type', $doc_cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN type ENUM('document', 'wiki', 'qa') DEFAULT 'document' AFTER user_id");
        echo "✔ Added column 'type' to 'documents'.<br>";
    }

    if (!in_array('tags', $doc_cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN tags TEXT DEFAULT NULL AFTER views");
        echo "✔ Added column 'tags' to 'documents'.<br>";
    }

    if (!in_array('doc_references', $doc_cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN doc_references TEXT DEFAULT NULL");
        echo "✔ Added column 'doc_references' to 'documents'.<br>";
    }

    if (!in_array('last_editor_id', $doc_cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN last_editor_id INT DEFAULT NULL AFTER user_id");
        $pdo->exec("ALTER TABLE documents ADD CONSTRAINT fk_documents_last_editor FOREIGN KEY (last_editor_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "✔ Added column 'last_editor_id' to 'documents'.<br>";
    }

    // 4. Create document_versions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS document_versions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        user_id INT NOT NULL,
        title_snapshot VARCHAR(255) NOT NULL,
        content_snapshot LONGTEXT NOT NULL,
        references_snapshot TEXT,
        edit_summary VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (document_id),
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✔ Document Versions table checked/created.<br>";

    echo "<b>Migration Completed Successfully!</b><br>";
    echo "<a href='index.php'>Go to Homepage</a>";

} catch (PDOException $e) {
    echo "Migration Error: " . $e->getMessage();
}
?>