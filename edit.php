<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id === 0 || !is_logged_in()) {
    header("Location: index.php");
    exit;
}

// Fetch Document
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    die("Document not found.");
}

// Check if user is owner or admin
if ($doc['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
    die("Access Denied: You do not have permission to edit this document.");
}

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $title = trim($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $content = trim($_POST['content']);
    $tags = trim($_POST['tags']);

    try {
        $stmt = $pdo->prepare("UPDATE documents SET title = ?, content = ?, category_id = ?, tags = ? WHERE id = ?");
        $stmt->execute([$title, $content, $category_id, $tags, $id]);
        $message = "บันทึกการเปลี่ยนแปลงเรียบร้อยแล้ว!";
        
        // Update local doc variable to reflect changes immediately
        $doc['title'] = $title;
        $doc['content'] = $content;
        $doc['category_id'] = $category_id;
        $doc['tags'] = $tags;
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
    <title>แก้ไขข้อมูล | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .edit-form-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: hsl(var(--foreground));
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            font-family: inherit;
            font-size: 0.875rem;
            outline: none;
            transition: var(--transition-base);
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--teal-primary);
            box-shadow: 0 0 0 2px hsl(var(--primary)/0.1);
        }
        .form-textarea {
            min-height: 250px;
            resize: vertical;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <a href="view.php?id=<?php echo $id; ?>" style="color: var(--teal-primary); text-decoration: none; font-weight: 600; font-size: 0.875rem;">ย้อนกลับไปหน้าเนื้อหา</a>
                        <i data-lucide="chevron-right" style="width: 14px; color: #94a3b8;"></i>
                        <span style="font-size: 0.875rem; color: #64748b;">แก้ไข</span>
                    </div>
                    <h2>แก้ไขเอกสาร/บทความ</h2>
                </div>
            </header>

            <?php if ($message): ?>
                <div style="background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid hsl(142 76% 36% / 0.2); max-width: 800px; margin-left: auto; margin-right: auto;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: hsl(0 84% 60% / 0.1); color: hsl(0 84% 60%); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid hsl(0 84% 60% / 0.2); max-width: 800px; margin-left: auto; margin-right: auto;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="edit-form-container">
                <form action="edit.php?id=<?php echo $id; ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">หัวข้อ (Title)</label>
                        <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($doc['title']); ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">หมวดหมู่</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">เลือกหมวดหมู่...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $doc['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">แท็ก (Tags)</label>
                            <input type="text" name="tags" class="form-input" value="<?php echo htmlspecialchars($doc['tags'] ?? ''); ?>" placeholder="คั่นด้วยคอมม่า เช่น AI, การสอน">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">เนื้อหา / รายละเอียด (Content)</label>
                        <textarea name="content" class="form-textarea" required><?php echo htmlspecialchars($doc['content']); ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn-primary" style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">ยกเลิก</a>
                        <button type="submit" class="btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
