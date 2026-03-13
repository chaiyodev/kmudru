<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$categories = [];
if ($pdo) {
    $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM documents WHERE category_id = c.id) as doc_count FROM categories c");
    $categories = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หมวดหมู่ความรู้ | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
    <div class="app-container">
        <!-- Standardized Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Viewport -->
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>หมวดหมู่ความรู้</h2>
                    <p>สำรวจองค์ความรู้แยกตามรายวิชาและสายงาน</p>
                </div>
                <div class="header-actions">
                    <a href="category_create.php" class="btn-primary"><i data-lucide="plus"></i>สร้างหมวดหมู่ใหม่</a>
                </div>
            </header>

            <div class="knowledge-grid">
                <?php foreach ($categories as $cat): ?>
                    <div class="card-knowledge"
                        onclick="location.href='browse.php?search=<?php echo urlencode($cat['name']); ?>'"
                        style="cursor:pointer;">
                        <div
                            style="width: 48px; height: 48px; border-radius: 12px; background: hsl(var(--primary) / 0.1); color: var(--teal-primary); display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                            <i data-lucide="folder" style="width: 24px;"></i>
                        </div>
                        <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <p class="card-excerpt"><?php echo htmlspecialchars($cat['description']); ?></p>
                        <div class="card-footer" style="border: none; padding-top: 1rem;">
                            <span
                                style="font-size: 0.8125rem; font-weight: 600; color: var(--teal-primary);"><?php echo $cat['doc_count']; ?>
                                บทความ</span>
                            <i data-lucide="chevron-right" style="width: 16px; color: hsl(var(--muted-foreground));"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>