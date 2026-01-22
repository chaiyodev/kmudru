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
    $title = $_POST['title'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    $duration = $_POST['duration'];
    $link = $_POST['link'];

    try {
        $stmt = $pdo->prepare("INSERT INTO trainings (title, description, duration, category_id, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $duration, $category_id, $link]);
        $message = "บันทึกคอร์สการเรียนรู้ใหม่เรียบร้อยแล้ว!";
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างหลักสูตร | KM Portal</title>
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
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-label {
            font-size: 0.9375rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            display: block;
        }

        .form-input {
            padding: 0.875rem 1rem;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Viewport -->
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>สร้างหลักสูตรการเรียนรู้</h2>
                    <p>รวบรวมแหล่งการเรียนรู้ภายนอกและภายในเพื่อการพัฒนาตนเอง</p>
                </div>
            </header>

            <?php if ($message): ?>
                <div
                    style="background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid hsl(142 76% 36% / 0.2);">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div style="max-width: 900px;">
                <form action="training_create.php" method="POST" class="creation-card">
                    <div class="form-group">
                        <label class="form-label">ชื่อหลักสูตร (Course Name)</label>
                        <input type="text" name="title" class="form-input"
                            placeholder="ระบุชื่อวิชาหรือหลักสูตรที่เปิดสอน" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                        <div>
                            <label class="form-label">ระยะเวลา (Duration)</label>
                            <input type="text" name="duration" class="form-input" placeholder="เช่น 2 ชม. 30 นาที">
                        </div>
                        <div>
                            <label class="form-label">หมวดหมู่</label>
                            <select name="category_id" class="form-select" style="padding: 0.875rem;" required>
                                <option value="">เลือกหมวดหมู่ที่เหมาะสม...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ลิงก์เข้าสู่การอบรม (External Link)</label>
                        <input type="url" name="link" class="form-input"
                            placeholder="ระบุ URL ของคอร์สเรียน (เช่น Coursera, YouTube, Zoom)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">รายละเอียดหลักสูตร</label>
                        <textarea name="description" class="form-textarea"
                            placeholder="อธิบายเนื้อหา สิ่งที่จะได้รับจากการเรียน และกลุ่มเป้าหมาย..."></textarea>
                    </div>

                    <div
                        style="display: flex; gap: 1rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                        <button type="submit" class="btn-primary" style="padding: 0.75rem 3rem;">สร้างหลักสูตร</button>
                        <a href="training.php" class="btn-primary"
                            style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>