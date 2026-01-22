<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$categories = [];
if ($pdo) {
    $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $icon = $_POST['icon'];
    $user_id = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO communities (name, description, icon) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $icon]);
        $community_id = $pdo->lastInsertId();

        // Add creator as leader
        $stmt = $pdo->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'leader')");
        $stmt->execute([$community_id, $user_id]);

        $pdo->commit();
        $message = "สร้างชุมชน CoP ใหม่เรียบร้อยแล้ว!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างชุมชน CoP | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .creation-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            box-shadow: rgba(20, 29, 31, 0.05) 0px 1px 2px 0px;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Standardized Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>สร้างชุมชน CoP ใหม่</h2>
                    <p>สร้างพื้นที่เพื่อการแลกเปลี่ยนเรียนรู้ในประเด็นที่คนส่วนใหญ่ให้ความสนใจ</p>
                </div>
            </header>

            <?php if ($message): ?>
                <div
                    style="background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid hsl(142 76% 36% / 0.2);">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div style="max-width: 800px; margin: 0 auto;">
                <form action="cop_create.php" method="POST" class="creation-card">
                    <div class="form-group">
                        <label class="form-label">ชื่อชุมชน (Community Name)</label>
                        <input type="text" name="name" class="form-input"
                            placeholder="เช่น นวัตกรรมการสอนยุคใหม่, IT Support Team" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ไอคอนชุมชน (Emoji)</label>
                        <input type="text" name="icon" class="form-input" placeholder="เช่น 💡, 💻, 📚" value="🤝">
                    </div>

                    <div class="form-group">
                        <label class="form-label">วัตถุประสงค์และรายละเอียด</label>
                        <textarea name="description" class="form-textarea"
                            placeholder="อธิบายเป้าหมายของกลุ่มนี้เพื่อให้เพื่อนสมาชิกเข้าใจ..."></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem; padding-top: 2rem;">
                        <button type="submit" class="btn-primary" style="padding: 0.75rem 3rem;">ยืนยันการสร้าง</button>
                        <a href="cop.php" class="btn-primary"
                            style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>