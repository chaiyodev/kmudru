<?php
require_once 'includes/db.php';
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM documents WHERE id = 9');
$stmt->execute();
$data = $stmt->fetch();
header('Content-Type: application/json');
echo json_encode($data);
?>