<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$pdo = get_pdo();
$user_id = $_SESSION['user_id'];
$user_info = null;
$my_docs = [];
$stats = ['total' => 0, 'views' => 0, 'likes' => 0];

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT d.*, c.name as category_name FROM documents d LEFT JOIN categories c ON d.category_id = c.id WHERE d.user_id = ? ORDER BY d.created_at DESC");
        $stmt->execute([$user_id]);
        $my_docs = $stmt->fetchAll();

        $stats['total'] = count($my_docs);
        foreach ($my_docs as $doc) {
            $stats['views'] += $doc['views'];
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_likes dl JOIN documents d ON dl.document_id = d.id WHERE d.user_id = ?");
        $stmt->execute([$user_id]);
        $stats['likes'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
    }
}

if (!$user_info) {
    header("Location: index.php");
    exit;
}

$type_labels = ['document' => 'เอกสาร', 'wiki' => 'Wiki', 'qa' => 'Q&A'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ของฉัน | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .profile-card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            text-align: center;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 30px;
            background: var(--teal-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 auto 1.5rem;
            box-shadow: 0 0 0 6px hsl(var(--primary) / 0.1);
        }

        .xp-card {
            background: hsl(var(--primary) / 0.1);
            color: var(--teal-primary);
            padding: 1.5rem;
            border-radius: 1rem;
            text-align: left;
            margin: 2rem 0;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>โปรไฟล์ของฉัน</h2>
                    <p>จัดการข้อมูลส่วนตัวและดูผลงานที่คุณได้แบ่งปันให้กับชุมชน</p>
                </div>
                <div class="header-actions">
                    <a href="create.php" class="btn-primary"><i data-lucide="plus"></i>สร้างเนื้อหาใหม่</a>
                </div>
            </header>

            <!-- Stats Grid -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2.5rem;">
                <div
                    style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span
                            style="font-size: 0.8125rem; font-weight: 600; color: hsl(var(--muted-foreground));">ผลงานของฉัน</span>
                        <div style="color: var(--teal-primary); opacity: 0.8;"><i data-lucide="file-text"></i></div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800;"><?php echo $stats['total']; ?></div>
                </div>
                <div
                    style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span
                            style="font-size: 0.8125rem; font-weight: 600; color: hsl(var(--muted-foreground));">ยอดเข้าชมรวม</span>
                        <div style="color: var(--teal-primary); opacity: 0.8;"><i data-lucide="eye"></i></div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800;"><?php echo $stats['views']; ?></div>
                </div>
                <div
                    style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span
                            style="font-size: 0.8125rem; font-weight: 600; color: hsl(var(--muted-foreground));">ยอดถูกใจ</span>
                        <div style="color: hsl(339 90% 50%); opacity: 0.8;"><i data-lucide="heart"></i></div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800;"><?php echo $stats['likes']; ?></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                <!-- Profile Sidebar -->
                <div class="profile-card">
                    <div class="profile-avatar"><?php echo strtoupper(substr($user_info['username'], 0, 2)); ?></div>
                    <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem;">
                        <?php echo htmlspecialchars($user_info['full_name']); ?></h3>
                    <p style="color: hsl(var(--muted-foreground)); font-size: 0.9375rem; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($user_info['specialty'] ?? 'ผู้ใช้งานทั่วไป'); ?></p>
                    <p style="color: hsl(var(--muted-foreground)); font-size: 0.8125rem;">
                        <?php echo htmlspecialchars($user_info['department'] ?? 'ยังไม่ระบุหน่วยงาน'); ?></p>

                    <div class="xp-card">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <i data-lucide="award" style="width: 20px;"></i>
                            <span style="font-weight: 700;">คะแนนสะสม (XP)</span>
                        </div>
                        <div style="font-size: 2.5rem; font-weight: 900;"><?php echo $user_info['points'] ?? 0; ?></div>
                        <p style="font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.8;">
                            สร้างผลงานเพื่อรับคะแนนเพิ่มเติม</p>
                    </div>

                    <div style="text-align: left; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                        <h5 style="font-weight: 700; font-size: 0.875rem; margin-bottom: 0.75rem;">เกี่ยวกับฉัน</h5>
                        <p style="font-size: 0.875rem; color: hsl(var(--muted-foreground)); line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($user_info['bio'] ?? 'ยังไม่มีคำแนะนำตัว คลิกปุ่มด้านล่างเพื่อเพิ่มข้อมูล...')); ?>
                        </p>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <a href="experts_create.php" class="btn-primary"
                            style="justify-content: center;">แก้ไขโปรไฟล์</a>
                        <a href="settings.php" class="btn-primary"
                            style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); justify-content: center;">ตั้งค่าบัญชี</a>
                    </div>
                </div>

                <!-- My Works Section -->
                <div>
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 800;">ผลงานของฉัน (<?php echo $stats['total']; ?>)
                        </h3>
                        <a href="create.php"
                            style="font-size: 0.875rem; color: var(--teal-primary); font-weight: 600; text-decoration: none;">+
                            สร้างใหม่</a>
                    </div>

                    <?php if (empty($my_docs)): ?>
                        <div
                            style="background: white; border-radius: 1.5rem; border: 1px solid var(--border-color); text-align: center; padding: 5rem 2rem;">
                            <div
                                style="width: 80px; height: 80px; background: hsl(var(--muted)/0.5); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: hsl(var(--muted-foreground));">
                                <i data-lucide="file-plus" style="width: 40px; height: 40px;"></i>
                            </div>
                            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem;">
                                คุณยังไม่ได้สร้างเนื้อหาใดๆ</h3>
                            <p
                                style="color: hsl(var(--muted-foreground)); margin-bottom: 2rem; max-width: 400px; margin-left: auto; margin-right: auto;">
                                เริ่มต้นแบ่งปันความรู้แรกของคุณวันนี้เพื่อรับแต้ม XP และช่วยเหลือเพื่อนบุคลากร!</p>
                            <a href="create.php" class="btn-primary" style="display: inline-flex;">เริ่มสร้างเนื้อหา</a>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($my_docs as $doc): ?>
                                <div class="card-knowledge" style="padding: 1.5rem;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                        <div style="display: flex; gap: 0.5rem;">
                                            <span class="tag-badge"
                                                style="background: hsl(var(--primary)/0.1); color: var(--teal-primary);"><?php echo $type_labels[$doc['type']] ?? 'เอกสาร'; ?></span>
                                            <span
                                                class="tag-badge"><?php echo htmlspecialchars($doc['category_name'] ?? 'ไม่ระบุ'); ?></span>
                                        </div>
                                        <span
                                            style="font-size: 0.75rem; color: hsl(var(--muted-foreground));"><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></span>
                                    </div>
                                    <h4 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.75rem;">
                                        <a href="view.php?id=<?php echo $doc['id']; ?>"
                                            style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($doc['title']); ?></a>
                                    </h4>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div
                                            style="display: flex; gap: 1rem; font-size: 0.8125rem; color: hsl(var(--muted-foreground));">
                                            <span><i data-lucide="eye"
                                                    style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i><?php echo $doc['views']; ?>
                                                views</span>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <a href="edit.php?id=<?php echo $doc['id']; ?>"
                                                style="padding: 0.375rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; font-size: 0.75rem; font-weight: 600; text-decoration: none; color: hsl(var(--foreground)); display: inline-flex; align-items: center; gap: 0.25rem;"><i
                                                    data-lucide="edit-2" style="width: 12px; height: 12px;"></i>แก้ไข</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>