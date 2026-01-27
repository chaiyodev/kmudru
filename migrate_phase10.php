<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        echo "<h2>Phase 10: Course Creator Upgrade...</h2>";

        // Add new columns to trainings table
        $pdo->exec("
            ALTER TABLE trainings 
            ADD COLUMN IF NOT EXISTS thumbnail VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS level ENUM('Beginner', 'Intermediate', 'Advanced') DEFAULT 'Beginner',
            ADD COLUMN IF NOT EXISTS objectives TEXT;
        ");
        echo "✅ Added 'thumbnail', 'level', and 'objectives' columns to trainings.<br>";

        echo "<h3>Phase 10 Setup Complete!</h3>";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>
<br><a href="training_create.php">Go to Create Course</a>