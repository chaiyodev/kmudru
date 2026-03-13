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
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $title = $_POST['title'];
    $category_id = $_POST['category_id'];
    $content = $_POST['content'];
    $tags = $_POST['tags'];
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO documents (title, content, category_id, user_id, type, tags) VALUES (?, ?, ?, ?, 'qa', ?)");
        $stmt->execute([$title, $content, $category_id, $user_id, $tags]);
        $message = "ส่งคำถามของคุณเข้าสู่ระบบเรียบร้อยแล้ว!";
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
    <title>ตั้งคำถามใหม่ | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .qa-form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 3rem;
            box-shadow: rgba(20, 29, 31, 0.05) 0px 1px 2px 0px;
        }

        .header-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .qa-icon-circle {
            width: 64px;
            height: 64px;
            background: hsl(var(--primary) / 0.1);
            color: var(--teal-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .qa-icon-circle i {
            width: 32px;
            height: 32px;
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

        .form-input-lg {
            font-size: 1.25rem;
            font-weight: 600;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            width: 100%;
            outline: none;
            transition: var(--transition-base);
        }

        .form-input-lg:focus {
            border-color: var(--teal-primary);
            box-shadow: 0 0 0 4px hsl(var(--primary) / 0.1);
        }

        .form-textarea {
            min-height: 200px;
            padding: 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            width: 100%;
            font-family: inherit;
            font-size: 1rem;
            outline: none;
            transition: var(--transition-base);
            resize: vertical;
        }

        .form-textarea:focus {
            border-color: var(--teal-primary);
            box-shadow: 0 0 0 4px hsl(var(--primary) / 0.1);
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
            <div class="header-section">
                <div class="qa-icon-circle"><i data-lucide="help-circle"></i></div>
                <h2 style="font-size: 2.25rem; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 0.5rem;">
                    มีคำถามที่ต้องการคำตอบไหม?</h2>
                <p style="color: hsl(var(--muted-foreground)); font-size: 1.125rem;">
                    ตั้งคำถามของคุณเพื่อให้ผู้เชี่ยวชาญและเพื่อนบุคลากรได้ช่วยเหลือ</p>
            </div>

            <?php if ($message): ?>
                <div
                    style="max-width: 800px; margin: 0 auto 2rem; background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); padding: 1.25rem; border-radius: 1rem; text-align: center; border: 1px solid hsl(142 76% 36% / 0.2);">
                    <i data-lucide="check-circle" style="vertical-align: middle; margin-right: 0.5rem;"></i>
                    <?php echo $message; ?>
                    <div style="margin-top: 1rem;">
                        <a href="browse.php?type=qa" class="btn-primary" style="display: inline-flex;">ดูคำถามทั้งหมด</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="qa-form-container">
                <form action="qa_ask.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-group">
                        <label class="form-label">หัวข้อคำถามของคุณ</label>
                        <input type="text" name="title" class="form-input-lg"
                            placeholder="เช่น 'จะติดตั้งโปรแกรม VPN จากทางบ้านได้อย่างไร?'" required>
                        <p style="margin-top: 0.5rem; font-size: 0.8125rem; color: hsl(var(--muted-foreground));">
                            อธิบายคำถามให้กระชับและเข้าใจง่าย</p>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">หมวดหมู่</label>
                            <select name="category_id" class="form-select" style="padding: 0.875rem;" required>
                                <option value="">เลือกหมวดหมู่ที่เกี่ยวข้อง...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">แท็ก (Tags)</label>
                            <input type="text" name="tags" class="form-input" style="padding: 0.875rem;"
                                placeholder="เช่น VPN, Network, IT">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">รายละเอียดเพิ่มเติม</label>
                        <textarea name="content" class="form-textarea"
                            placeholder="ระบุรายละเอียด หรือเหตุผลที่คุณต้องการข้อมูลนี้..."></textarea>
                    </div>

                    <div
                        style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 2rem;">
                        <a href="index.php" class="btn-primary"
                            style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">ยกเลิก</a>
                        <button type="submit" class="btn-primary" style="padding: 0.75rem 3rem;">ส่งคำถาม</button>
                    </div>
                </form>
            </div>

            <div
                style="max-width: 800px; margin: 3rem auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; text-align: center;">
                <div style="padding: 1.5rem;">
                    <i data-lucide="search" style="color: var(--teal-primary); margin-bottom: 0.75rem;"></i>
                    <h4 style="font-weight: 700; margin-bottom: 0.25rem;">ค้นหาก่อน</h4>
                    <p style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">บางคำถามอาจมีผู้เคยถามไว้แล้ว
                    </p>
                </div>
                <div style="padding: 1.5rem;">
                    <i data-lucide="eye" style="color: var(--teal-primary); margin-bottom: 0.75rem;"></i>
                    <h4 style="font-weight: 700; margin-bottom: 0.25rem;">ชัดเจน</h4>
                    <p style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">ระบุรายละเอียดให้ครบถ้วน</p>
                </div>
                <div style="padding: 1.5rem;">
                    <i data-lucide="award" style="color: var(--teal-primary); margin-bottom: 0.75rem;"></i>
                    <h4 style="font-weight: 700; margin-bottom: 0.25rem;">สร้างปัญญา</h4>
                    <p style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">ทุกคำตอบคือคลังความรู้ของ UDRU
                    </p>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>