<?php
/**
 * Database Sample Configuration
 * Rename this file to db.php and update the values below.
 */
$host = 'localhost';
$db = 'kmud_db';
$user = 'root'; // Default XAMPP user
$pass = '';     // Default XAMPP password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // For development, handle gracefully
    $pdo = null;
}

function get_pdo()
{
    global $pdo;
    return $pdo;
}
?>