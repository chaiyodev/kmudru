<?php
require_once 'c:/xampp/htdocs/udruwisdom/includes/db.php';
$pdo = get_pdo();
if (!$pdo) {
    echo "Connection failed\n";
    exit;
}
$doc_id = 7;
$stmt = $pdo->prepare("SELECT * FROM attachments WHERE document_id = ?");
$stmt->execute([$doc_id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Attachments for document ID $doc_id:\n";
print_r($attachments);

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nDocument details:\n";
print_r($doc);
