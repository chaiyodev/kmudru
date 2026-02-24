<?php
$start = microtime(true);
require_once 'includes/db.php';
require_once 'includes/sidebar.php';
$end = microtime(true);

header('Content-Type: application/json');
echo json_encode([
    'execution_time_ms' => round(($end - $start) * 1000, 2),
    'memory_usage_kb' => round(memory_get_usage() / 1024, 2),
    'db_status' => (get_pdo() !== null) ? 'connected' : 'failed'
]);
?>