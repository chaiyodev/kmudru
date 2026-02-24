<?php
require_once 'includes/db.php';
$pdo = get_pdo();
$stmt = $pdo->query("SELECT id, username, role FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($users);
?>