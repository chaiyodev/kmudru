<?php
// Script to link former database with new system requirements
require_once 'includes/db.php';
$pdo = get_pdo();

if (!$pdo) {
    die("Database connection failed. Check config.php");
}

try {
    echo "<h2>🔄 Linking Data from kmud_db to UDRU Wisdom System...</h2>";

    // 1. Check/Add new system columns to existing users table
    $user_cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $missing_user_cols = [
        'avatar' => "VARCHAR(255) DEFAULT NULL",
        'email_prefs' => "TEXT DEFAULT NULL",
        'department' => "VARCHAR(255) DEFAULT NULL",
        'specialty' => "VARCHAR(255) DEFAULT NULL",
        'bio' => "TEXT DEFAULT NULL",
        'points' => "INT DEFAULT 0",
        'portfolio' => "TEXT DEFAULT NULL",
        'phone' => "VARCHAR(20) DEFAULT NULL"
    ];
    foreach ($missing_user_cols as $col => $def) {
        if (!in_array($col, $user_cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $def");
            echo "✅ Added '$col' to 'users'.<br>";
        }
    }

    // 2. Check/Add new system columns to existing documents table
    $doc_cols = $pdo->query("SHOW COLUMNS FROM documents")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('type', $doc_cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN type ENUM('document', 'wiki', 'qa') DEFAULT 'document' AFTER user_id");
        echo "✅ Added 'type' to 'documents'.<br>";
    }
    if (!in_array('tags', $doc_cols)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN tags TEXT DEFAULT NULL AFTER views");
        echo "✅ Added 'tags' to 'documents'.<br>";
    }

    // 3. Ensure all utility tables exist (Likes, Chat, Training, Communities)
    // Run the full migration logic on THIS database
    include 'full_migration.php';

    echo "<h3>🎉 Success! Your original data is now integrated with the new system.</h3>";

} catch (PDOException $e) {
    die("❌ Error during integration: " . $e->getMessage());
}
?>
<br><a href="index.php">Go to Dashboard</a>