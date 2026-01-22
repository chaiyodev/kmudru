<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

echo "<h1>Debug Info</h1>";

// 1. Check Session
echo "<h2>Session Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// 2. Check Database User
echo "<h2>Database User (admin)</h2>";
$pdo = get_pdo();
if ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();
    echo "<pre>";
    print_r($user);
    echo "</pre>";
} else {
    echo "Database connection failed.";
}
?>