<?php
session_start();
require_once 'includes/db.php';
$pdo = get_pdo();
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();

if ($admin) {
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['username'] = $admin['username'];
    $_SESSION['full_name'] = $admin['full_name'];
    $_SESSION['role'] = 'admin';
    echo "✔ Admin session established for " . htmlspecialchars($admin['username']);
} else {
    echo "✖ No admin user found in database.";
}
?>