<?php
require_once 'includes/db.php';
$pdo = get_pdo();
if ($pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
}
?>