<?php
require_once 'includes/config.php';
$config = require 'includes/config.php';

echo "<h2>Database Connection Test</h2>";
echo "Configured Database Name: " . ($config['db']['name'] ?? 'NOT SET') . "<br>";

$host = $config['db']['host'] ?? 'localhost';
$user = $config['db']['user'] ?? 'root';
$pass = $config['db']['pass'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    echo "<h3>Available Databases:</h3><ul>";
    $stmt = $pdo->query("SHOW DATABASES");
    while ($row = $stmt->fetchColumn()) {
        echo "<li>$row</li>";
    }
    echo "</ul>";
    
    $target_db = $config['db']['name'];
    try {
        $pdo->exec("USE `$target_db` ");
        echo "<p style='color:green;'>Successfully connected to and switched to database: $target_db</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red;'>Failed to connect to database '$target_db': " . $e->getMessage() . "</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>Failed to connect to MySQL host: " . $e->getMessage() . "</p>";
}
