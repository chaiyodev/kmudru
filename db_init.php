<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS udruwisdom_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database 'udruwisdom_db' created successfully.<br>";

    // Select database
    $pdo->exec("USE udruwisdom_db");

    // Import setup.sql
    $sql_file = 'setup.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        // Note: This might fail for very large SQL files with complex statements, 
        // but setup.sql is usually manageable.
        $pdo->exec($sql);
        echo "Successfully imported setup.sql.<br>";
    } else {
        echo "Error: setup.sql not found.<br>";
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>