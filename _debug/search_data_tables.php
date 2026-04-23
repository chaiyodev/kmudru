<?php
/**
 * UDRU Wisdom - Data Locator Tool
 */
require_once 'includes/config.php';
$config = require 'includes/config.php';

$host = $config['db']['host'] ?? 'localhost';
$user = $config['db']['user'] ?? 'root';
$pass = $config['db']['pass'] ?? '';

echo "<h2 style='font-family: Arial;'>ตามหาข้อมูลที่หายไปในระบบ MySQL...</h2>";

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<table border='1' style='width:100%; border-collapse: collapse; font-family: sans-serif;'>";
    echo "<tr style='background: #f1f5f9;'>
            <th>ชื่อฐานข้อมูล</th>
            <th>Users</th>
            <th>Docs (บทความ/QA/Wiki)</th>
            <th>CoP (Communities)</th>
            <th>Experts</th>
            <th>Trainings</th>
          </tr>";

    foreach ($databases as $db) {
        if (in_array($db, ['information_schema', 'mysql', 'performance_schema', 'phpmyadmin'])) continue;
        
        try {
            $pdo->exec("USE `$db` ");
            
            $counts = [];
            $tables_to_check = [
                'users' => 'Users',
                'documents' => 'Docs',
                'communities' => 'CoP',
                'experts' => 'Experts',
                'trainings' => 'Trainings'
            ];
            
            foreach ($tables_to_check as $table => $label) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $counts[$table] = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                } else {
                    $counts[$table] = "-";
                }
            }

            echo "<tr>
                    <td style='padding: 8px; font-weight: bold;'>$db</td>
                    <td style='padding: 8px; text-align: center;'>{$counts['users']}</td>
                    <td style='padding: 8px; text-align: center;'>{$counts['documents']}</td>
                    <td style='padding: 8px; text-align: center;'>{$counts['communities']}</td>
                    <td style='padding: 8px; text-align: center;'>{$counts['experts']}</td>
                    <td style='padding: 8px; text-align: center;'>{$counts['trainings']}</td>
                  </tr>";
        } catch (Exception $e) {
            echo "<tr><td style='padding: 8px; font-weight: bold;'>$db</td><td colspan='5' style='color:red; padding: 8px;'>เข้าถึงไม่ได้: " . $e->getMessage() . "</td></tr>";
        }
    }
    echo "</table>";
    
    echo "<p style='margin-top: 20px; font-weight: bold;'>หากพบฐานข้อมูลที่มีจำนวนข้อมูลแสดงอยู่ ให้แจ้งชื่อฐานข้อมูลนั้นแก่ผมครับ เดี๋ยวผมจะช่วยย้ายกลับมาให้ครับ!</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
