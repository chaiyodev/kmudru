<?php
require_once 'includes/db.php';
$pdo = get_pdo();

if ($pdo) {
    try {
        // Find a user ID (defaulting to 1 if not found)
        $user_id = $pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn() ?: 1;
        // Find a category ID
        $category_id = $pdo->query("SELECT id FROM categories LIMIT 1")->fetchColumn() ?: 1;

        $docs = [
            [
                'title' => 'แนวทางการทำวิจัยสถาบันเพื่อพัฒนาคุณภาพการศึกษา',
                'content' => 'เอกสารฉบับนี้รวบรวมเทคนิคการทำวิจัยสถาบัน (Institutional Research) ที่มุ่งเน้นการนำข้อมูลมาใช้ในการตัดสินใจและปรับปรุงกระบวนการทำงานภายในมหาวิทยาลัยราชภัฏอุดรธานี',
                'tags' => 'วิจัย, คุณภาพการศึกษา, R2R'
            ],
            [
                'title' => 'สรุปเกณฑ์การประกันคุณภาพการศึกษา AUN-QA ฉบับล่าสุด',
                'content' => 'สรุปประเด็นสำคัญและตัวบ่งชี้ของเกณฑ์ AUN-QA (ASEAN University Network Quality Assurance) เพื่อเตรียมความพร้อมของหลักสูตรในการรับการประเมิน',
                'tags' => 'AUN-QA, ประกันคุณภาพ, มาตรฐาน'
            ]
        ];

        $stmt = $pdo->prepare("INSERT INTO documents (title, content, category_id, user_id, type, tags, status) VALUES (?, ?, ?, ?, 'document', ?, 'published')");
        foreach ($docs as $doc) {
            $stmt->execute([$doc['title'], $doc['content'], $category_id, $user_id, $doc['tags']]);
            echo "✅ Added: " . $doc['title'] . "<br>";
        }
        echo "<h3>Done! Added 2 more documents for balance.</h3>";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>