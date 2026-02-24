<?php
require_once 'includes/db.php';
$pdo = get_pdo();
$stmt = $pdo->query("SHOW COLUMNS FROM documents");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($cols);
?>