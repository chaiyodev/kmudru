<?php
require_once 'includes/db.php';

$pdo = get_pdo();
if ($pdo) {
    echo "<h2>Applying Database Changes (Phase 6)...</h2>";
    try {
        // Add google_id column if not exists
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(100) UNIQUE NULL AFTER password");
        echo "✅ Column 'google_id' added successfully.<br>";

        echo "<h3>Migration Complete!</h3>";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
} else {
    echo "❌ Database connection failed.";
}
?>
<br><a href="login.php">Back to Login</a>