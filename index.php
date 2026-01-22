<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$user = null;
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

$categories = [];
$stats = ['document' => 0, 'wiki' => 0, 'qa' => 0, 'experts' => 0, 'total' => 0];
$latest_docs = [];
$trending_topics = [];
$recent_activity = [];

if ($pdo) {
    try {
        // Fetch categories
        $stmt = $pdo->query("SELECT * FROM categories");
        $categories = $stmt->fetchAll();

        // Fetch document counts by type
        $stmt = $pdo->query("SELECT type, COUNT(*) as count FROM documents GROUP BY type");
        while ($row = $stmt->fetch()) {
            $stats[$row['type']] = $row['count'];
            $stats['total'] += $row['count'];
        }

        // Fetch expert count
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin', 'contributor')");
        $stats['experts'] = $stmt->fetchColumn();

        // Fetch latest recommended docs
        $stmt = $pdo->query("SELECT d.*, c.name as category_name, u.username as author_username FROM documents d LEFT JOIN categories c ON d.category_id = c.id LEFT JOIN users u ON d.user_id = u.id WHERE d.status = 'published' ORDER BY d.created_at DESC LIMIT 3");
        $latest_docs = $stmt->fetchAll();

        // Fetch trending topics
        $stmt = $pdo->query("SELECT c.name, COUNT(d.id) as doc_count FROM categories c LEFT JOIN documents d ON c.id = d.category_id GROUP BY c.id ORDER BY doc_count DESC LIMIT 8");
        $trending_topics = $stmt->fetchAll();

        // Activity Feed
        $activity_query = "
            (SELECT 'document' as type, title as content, created_at, u.username, u.full_name 
             FROM documents d JOIN users u ON d.user_id = u.id)
            UNION
            (SELECT 'comment' as type, comment as content, created_at, u.username, u.full_name 
             FROM comments c JOIN users u ON c.user_id = u.id)
            ORDER BY created_at DESC LIMIT 5";
        $recent_activity = $pdo->query($activity_query)->fetchAll();

    } catch (PDOException $e) {
    }
}

$type_labels = ['document' => 'เอกสาร', 'wiki' => 'Wiki', 'qa' => 'Q&A'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KM Portal | UDRU Knowledge Hub</title>
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

        <!-- Main Viewport -->
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <?php if ($user): ?>
                        <h2>ยินดีต้อนรับ, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?> 👋</h2>
                    <?php else: ?>
                        <h2>ยินดีต้อนรับสู่ KM Portal 👋</h2>
                    <?php endif; ?>
                    <p>สืบค้นและแบ่งปันองค์ความรู้เพื่อสังคมแห่งการเรียนรู้ UDRU</p>
                </div>
                <div class="header-actions">
                    <a href="create.php" class="btn-primary"><i data-lucide="plus"></i>สร้างองค์ความรู้</a>
                </div>
            </header>

            <!-- Hero Section -->
            <section class="hero-centered animate-slide-down">
                <h1>สืบค้นองค์ความรู้ของมหาวิทยาลัย</h1>
                <p>แหล่งรวบรวมเทคนิค วิจัย และแนวทางการทำงานที่ดีที่สุดของบุคลากร UDRU</p>

                <div class="search-container-center">
                    <form action="browse.php" method="GET" class="search-inner">
                        <i data-lucide="search" style="color: hsl(var(--muted-foreground));"></i>
                        <input type="text" name="search" placeholder="พิมพ์สิ่งที่คุณต้องการค้นหา..." autofocus>
                        <kbd
                            style="font-size: 0.75rem; background: hsl(var(--muted)); padding: 4px 8px; border-radius: 6px; font-weight: 700; color: hsl(var(--muted-foreground));">⌘
                            K</kbd>
                        <button type="submit" class="btn-search-main">สืบค้น</button>
                    </form>
                </div>
            </section>

            <!-- Stats Grid -->
            <div class="grid-stats">
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon"><i data-lucide="file-text"></i></div>
                        <span class="stat-label">คลังเอกสาร</span>
                    </div>
                    <div class="stat-value"><?php echo $stats['document']; ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon"><i data-lucide="pen-tool"></i></div>
                        <span class="stat-label">บทความ Wiki</span>
                    </div>
                    <div class="stat-value"><?php echo $stats['wiki']; ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon"><i data-lucide="message-circle"></i></div>
                        <span class="stat-label">ถาม-ตอบ (Q&A)</span>
                    </div>
                    <div class="stat-value"><?php echo $stats['qa']; ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon"><i data-lucide="award"></i></div>
                        <span class="stat-label">ผู้เชี่ยวชาญ</span>
                    </div>
                    <div class="stat-value"><?php echo $stats['experts']; ?></div>
                </div>
            </div>

            <!-- Content Area -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2.5rem;">
                <section>
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h3 style="font-size: 1.25rem; font-weight: 700;">องค์ความรู้แนะนำ</h3>
                        <a href="browse.php"
                            style="color: var(--teal-primary); font-size: 0.875rem; font-weight: 600; text-decoration: none;">ดูทั้งหมด</a>
                    </div>
                    <div class="knowledge-grid" style="grid-template-columns: 1fr;">
                        <?php foreach ($latest_docs as $doc): ?>
                            <div class="card_knowledge" onclick="location.href='view.php?id=<?php echo $doc['id']; ?>'"
                                style="cursor: pointer; margin-bottom: 1.5rem; background: white; border-radius: 0.75rem; border: 1px solid var(--border-color); padding: 1.5rem; transition: var(--transition-base); box-shadow: rgba(20, 29, 31, 0.05) 0px 1px 2px 0px;">
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                                    <span class="tag-badge"
                                        style="background: hsl(var(--primary) / 0.1); color: var(--teal-primary);"><?php echo $type_labels[$doc['type']]; ?></span>
                                    <span class="tag-badge"><?php echo htmlspecialchars($doc['category_name']); ?></span>
                                </div>
                                <h3
                                    style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; color: hsl(var(--foreground));">
                                    <?php echo htmlspecialchars($doc['title']); ?>
                                </h3>
                                <p
                                    style="font-size: 0.9375rem; color: hsl(var(--muted-foreground)); line-height: 1.6; margin-bottom: 1.5rem;">
                                    <?php echo mb_strimwidth(strip_tags($doc['content']), 0, 160, "..."); ?>
                                </p>
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div class="author-avatar">
                                            <?php echo strtoupper(substr($doc['author_username'] ?? 'U', 0, 2)); ?>
                                        </div>
                                        <span
                                            style="font-size: 0.875rem; font-weight: 600;"><?php echo htmlspecialchars($doc['author_username'] ?? 'Anonymous'); ?></span>
                                    </div>
                                    <div
                                        style="display: flex; gap: 1rem; color: hsl(var(--muted-foreground)); font-size: 0.8125rem;">
                                        <span><i data-lucide="eye" style="width: 14px; vertical-align: middle;"></i>
                                            <?php echo $doc['views']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <aside>
                    <div
                        style="background: white; border-radius: 0.75rem; border: 1px solid var(--border-color); padding: 1.5rem;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1.5rem;">หัวข้อยอดนิยม</h3>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php foreach ($trending_topics as $topic): ?>
                                <a href="browse.php?search=<?php echo urlencode($topic['name']); ?>" class="tag-badge"
                                    style="text-decoration: none; padding: 6px 12px; font-size: 0.8125rem;">
                                    <?php echo htmlspecialchars($topic['name']); ?>
                                    <span
                                        style="opacity: 0.5; margin-left: 4px;">(<?php echo $topic['doc_count']; ?>)</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div
                        style="margin-top: 2rem; background: white; border-radius: 0.75rem; border: 1px solid var(--border-color); padding: 1.5rem;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1.5rem;">ความเคลื่อนไหวล่าสุด</h3>
                        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                            <?php foreach ($recent_activity as $act): ?>
                                <div style="display: flex; gap: 0.75rem;">
                                    <div
                                        style="width: 32px; height: 32px; background: hsl(var(--muted)); border-radius: 50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700;">
                                        <?php echo strtoupper(substr($act['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p style="font-size: 0.8125rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($act['full_name']); ?>
                                            <span style="font-weight: 400; color: hsl(var(--muted-foreground));">
                                                <?php echo $act['type'] == 'document' ? 'สร้างบทความใหม่' : 'แสดงความคิดเห็น'; ?>
                                            </span>
                                        </p>
                                        <p
                                            style="font-size: 0.75rem; color: var(--teal-primary); font-weight: 500; margin-top: 2px;">
                                            <?php echo mb_strimwidth($act['content'], 0, 40, "..."); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>