<?php
require_once 'includes/db.php';

$pdo = get_pdo();
$username = 'admin';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// 1. Check if admin exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

echo "<h2>Password Reset Tool</h2>";

if ($user) {
    echo "Found user 'admin' (ID: " . $user['id'] . ")<br>";

    // 2. Force Update Password
    $update = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE username = ?");
    if ($update->execute([$hash, $username])) {
        echo "<h3 style='color: green;'>✅ Password Reset Successful!</h3>";
        echo "User: <b>admin</b><br>";
        echo "Pass: <b>admin123</b><br>";
        echo "<br>New Hash: " . $hash;
    } else {
        echo "<h3 style='color: red;'>❌ Update Failed</h3>";
    }
} else {
    echo "User 'admin' not found. Creating it now...<br>";

    // 3. Create if not exists
    $insert = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, 'System Administrator', 'admin@kmud.ubru.ac.th', 'admin')");
    if ($insert->execute([$username, $hash])) {
        echo "<h3 style='color: green;'>✅ Admin User Created!</h3>";
        echo "User: <b>admin</b><br>";
        echo "Pass: <b>admin123</b><br>";
    } else {
        echo "<h3 style='color: red;'>❌ Creation Failed</h3>";
    }
}
?>