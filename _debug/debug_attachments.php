<?php
/**
 * UDRU Wisdom - Debug Attachments Tool
 * เปิดไฟล์นี้ผ่าน browser: http://localhost/udruwisdom/debug_attachments.php
 * ลบไฟล์นี้หลังใช้งานเสร็จ
 */
require_once 'includes/db.php';
$pdo = get_pdo();
if (!$pdo) { die("<h2 style='color:red'>❌ DB connection failed</h2>"); }

echo "<html><head><meta charset='utf-8'><title>Debug Attachments</title></head><body style='font-family:sans-serif;max-width:900px;margin:2rem auto;'>";
echo "<h1>🔍 Debug Attachments Tool</h1>";

// 1. Check tables
echo "<h3>1. ตรวจสอบตารางในฐานข้อมูล</h3>";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$check_tables = ['documents', 'attachments', 'document_images', 'document_likes', 'comments'];
echo "<table border='1' cellpadding='8'><tr><th>Table</th><th>Status</th></tr>";
foreach ($check_tables as $t) {
    $status = in_array($t, $tables) ? '✅ มีอยู่' : '❌ ไม่มี';
    echo "<tr><td>$t</td><td>$status</td></tr>";
}
echo "</table>";

// 2. Create document_images table if missing 
if (!in_array('document_images', $tables)) {
    echo "<p style='color:orange'>⚠️ ตาราง document_images ไม่มี - กำลังสร้าง...</p>";
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "<p style='color:green'>✅ สร้างตาราง document_images เรียบร้อย</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

// 3. Check attachments data
echo "<h3>2. ข้อมูลไฟล์แนบในระบบ</h3>";
try {
    $stmt = $pdo->query("SELECT a.*, d.title as doc_title FROM attachments a LEFT JOIN documents d ON a.document_id = d.id ORDER BY a.id DESC LIMIT 20");
    $attachments = $stmt->fetchAll();
    if (empty($attachments)) {
        echo "<p style='color:orange'>⚠️ ไม่พบไฟล์แนบในฐานข้อมูลเลย (ตาราง attachments ว่าง)</p>";
    } else {
        echo "<table border='1' cellpadding='8'><tr><th>ID</th><th>Doc ID</th><th>ชื่อเอกสาร</th><th>ชื่อไฟล์</th><th>Path</th><th>ไฟล์อยู่?</th></tr>";
        foreach ($attachments as $a) {
            $exists = file_exists($a['file_path']) ? '✅' : '❌';
            echo "<tr><td>{$a['id']}</td><td>{$a['document_id']}</td><td>" . htmlspecialchars($a['doc_title'] ?? '-') . "</td><td>" . htmlspecialchars($a['file_name']) . "</td><td>{$a['file_path']}</td><td>$exists</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

// 4. List all documents
echo "<h3>3. เอกสารทั้งหมด (ล่าสุด 20 รายการ)</h3>";
$docs = $pdo->query("SELECT id, title, type, created_at FROM documents ORDER BY id DESC LIMIT 20")->fetchAll();
echo "<table border='1' cellpadding='8'><tr><th>ID</th><th>ชื่อ</th><th>ประเภท</th><th>วันที่สร้าง</th><th>ดู</th></tr>";
foreach ($docs as $d) {
    echo "<tr><td>{$d['id']}</td><td>" . htmlspecialchars($d['title']) . "</td><td>{$d['type']}</td><td>{$d['created_at']}</td><td><a href='view.php?id={$d['id']}'>เปิด</a></td></tr>";
}
echo "</table>";

// 5. Upload dir files
echo "<h3>4. ไฟล์ในโฟลเดอร์ uploads/</h3>";
$files = glob('uploads/*');
echo "<ul>";
foreach ($files as $f) {
    if (!is_dir($f)) {
        echo "<li>" . basename($f) . " (" . round(filesize($f)/1024, 1) . " KB)</li>";
    }
}
echo "</ul>";

echo "<hr><p style='color:green; font-weight:bold;'>เสร็จสิ้นการตรวจสอบ! กรุณาลบไฟล์ debug_attachments.php หลังใช้งาน</p>";
echo "</body></html>";
