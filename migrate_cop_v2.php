<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        // Add new columns to communities
        $pdo->exec("
            ALTER TABLE communities 
            ADD COLUMN IF NOT EXISTS category_id INT AFTER description,
            ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) AFTER icon,
            ADD COLUMN IF NOT EXISTS is_public BOOLEAN DEFAULT TRUE AFTER cover_image,
            ADD COLUMN IF NOT EXISTS color_theme VARCHAR(20) DEFAULT '#14b8a6' AFTER is_public;
        ");

        // Ensure community_members has permissions/roles as discussed (leader/member already exist)

        echo "Database Upgraded Successfully!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>