<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    // Reset admin password to 'admin123'
    $new_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$new_password]);

    if ($stmt->rowCount() > 0) {
        echo "Admin password reset to 'admin123' successfully.";
    } else {
        // If admin doesn't exist, create it
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES ('admin', ?, 'System Administrator', 'admin@udruwisdom.udru.ac.th', 'admin')");
        $stmt->execute([$new_password]);
        echo "Admin user created with password 'admin123'.";
    }
} else {
    echo "DB connection failed.";
}
?>