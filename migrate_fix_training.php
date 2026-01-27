<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        echo "<h2>Fixing Trainings Table...</h2>";

        // Add created_by column
        $pdo->exec("
            ALTER TABLE trainings 
            ADD COLUMN IF NOT EXISTS created_by INT,
            ADD CONSTRAINT fk_training_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;
        ");
        echo "✅ Added 'created_by' column.<br>";

        // Update existing records to link to Admin (ID 1) as a fallback
        $pdo->exec("UPDATE trainings SET created_by = 1 WHERE created_by IS NULL");
        echo "✅ Updated existing records to default admin.<br>";

        echo "<h3>Fix Complete!</h3>";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>
<br><a href="training.php">Try Again</a>