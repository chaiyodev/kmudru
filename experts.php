<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pdo = get_pdo();
$experts = [];
if ($pdo) {
    $stmt = $pdo->query("SELECT *, (SELECT COUNT(*) FROM documents WHERE user_id = users.id) as doc_count FROM users WHERE role != 'reader' ORDER BY points DESC LIMIT 10");
    $experts = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายชื่อผู้เชี่ยวชาญ | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
    <div class="app-container">
        <!-- Standardized Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>ทำความรู้จักผู้เชี่ยวชาญ UDRU</h2>
                    <p>รวบรวมบุคลากรที่มีความรู้ความสามารถโดดเด่นในแต่ละสาขา</p>
                </div>
            </header>
            <div class="knowledge-grid" style="grid-template-columns: 1fr;">
                <?php foreach ($experts as $expert): ?>
                    <div class="card-knowledge" style="flex-direction: row; align-items: center; gap: 2rem;">
                        <div
                            style="width: 80px; height: 80px; border-radius: 20px; background: var(--teal-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; flex-shrink: 0;">
                            <?php echo strtoupper(substr($expert['username'], 0, 1)); ?>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($expert['full_name']); ?></h3>
                            <p
                                style="font-size: 0.875rem; color: var(--teal-primary); font-weight: 600; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($expert['specialty'] ?? 'ผู้เชี่ยวชาญทั่วไป'); ?>
                            </p>
                            <div
                                style="display: flex; gap: 1.5rem; color: hsl(var(--muted-foreground)); font-size: 0.8125rem;">
                                <span><i data-lucide="award" style="width: 14px; vertical-align: middle;"></i>
                                    <?php echo $expert['points']; ?> แต้มปัญญา</span>
                                <span><i data-lucide="file-text" style="width: 14px; vertical-align: middle;"></i>
                                    <?php echo $expert['doc_count']; ?> บทความ</span>
                            </div>
                        </div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: hsl(var(--muted));">
                            #<?php echo array_search($expert, $experts) + 1; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>