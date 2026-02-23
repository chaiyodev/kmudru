<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

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

        // Fetch latest recommended docs (Content Spotlight - Mixed Types)
        $doc_query = "
            (SELECT d.id, d.title, d.content, d.type, d.category_id, d.user_id, d.views, d.created_at, d.tags,
                   c.name as category_name, c.icon as category_icon, 
                   u.username as author_username, u.full_name as author_full_name,
                   (SELECT COUNT(*) FROM document_likes WHERE document_id = d.id) as like_count,
                   (SELECT COUNT(*) FROM comments WHERE document_id = d.id) as comment_count,
                   'view.php?id=' as base_url
            FROM documents d 
            LEFT JOIN categories c ON d.category_id = c.id 
            LEFT JOIN users u ON d.user_id = u.id 
            WHERE d.status = 'published')
            UNION ALL
            (SELECT t.id, t.title, t.description as content, 'training' as type, t.category_id, NULL as user_id, 0 as views, t.created_at, '' as tags,
                   c.name as category_name, 'graduation-cap' as category_icon,
                   'System' as author_username, 'UDRU Training' as author_full_name,
                   0 as like_count, 0 as comment_count,
                   'training_view.php?id=' as base_url
            FROM trainings t
            LEFT JOIN categories c ON t.category_id = c.id)
            ORDER BY created_at DESC 
            LIMIT 6";
        $latest_docs = $pdo->query($doc_query)->fetchAll();

        // Update type labels for display
        $type_labels['training'] = 'การฝึกอบรม';
        $type_labels['document'] = 'เอกสาร';
        $type_labels['wiki'] = 'Wiki';
        $type_labels['qa'] = 'Q&A';

        // Fetch trending topics
        $stmt = $pdo->query("SELECT c.id, c.name, COUNT(d.id) as doc_count FROM categories c LEFT JOIN documents d ON c.id = d.category_id GROUP BY c.id ORDER BY doc_count DESC LIMIT 8");
        $trending_topics = $stmt->fetchAll();

        // Content Feed (Latest Documents/Articles)
        $activity_query = "
            SELECT d.*, u.username, u.full_name, c.name as category_name, c.icon as category_icon
            FROM documents d 
            LEFT JOIN users u ON d.user_id = u.id 
            LEFT JOIN categories c ON d.category_id = c.id
            WHERE d.status = 'published'
            ORDER BY d.created_at DESC 
            LIMIT 8";
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
    <title>UDRU Wisdom | UDRU Knowledge Hub</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .knowledge-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .premium-card {
            background: white;
            border: 1px solid #eef2f6;
            border-radius: 1.25rem;
            padding: 1.5rem;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            overflow: hidden;
        }

        .premium-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.05);
            border-color: var(--teal-primary);
        }

        .premium-card.is-recommended {
            border: 2px solid #fdba74;
            /* orange-300 */
        }

        .recommend-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #f97316;
            color: white;
            padding: 4px 16px;
            font-size: 0.7rem;
            font-weight: 800;
            border-bottom-left-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .card-top {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .type-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #64748b;
        }

        .type-tag {
            padding: 4px 12px;
            background: #f1f5f9;
            color: #64748b;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 800;
            line-height: 1.4;
            color: #1e293b;
            margin-bottom: 0.75rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-desc {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            margin-top: auto;
        }

        .tag-pill {
            padding: 4px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
            border-radius: 100px;
            font-size: 0.6875rem;
            font-weight: 600;
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }

        .footer-author {
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }

        .author-sm-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: 700;
            color: var(--teal-primary);
        }

        .author-sm-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
        }

        .footer-stats {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .stat-item-sm {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        @media (max-width: 768px) {
            .knowledge-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }

            .premium-card {
                padding: 1.25rem;
            }

            .grid-stats {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 1rem !important;
            }

            .hero-centered {
                padding: 2.5rem 1rem 3.5rem !important;
            }

            .hero-centered h1 {
                font-size: 1.75rem !important;
            }

            .main-viewport {
                padding: 1.25rem !important;
            }

            .main-content-grid {
                grid-template-columns: 1fr !important;
                gap: 1.5rem !important;
            }

            .ai-smart-card {
                padding: 1.25rem !important;
            }

            .ai-smart-card h4 {
                font-size: 0.95rem !important;
            }

            .ai-smart-card p {
                font-size: 0.75rem !important;
                line-height: 1.4 !important;
            }

            /* Fix tag-badge wrapping */
            .tag-badge {
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                display: inline-block;
            }
        }
    </style>
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
                        <h2>ยินดีต้อนรับสู่ UDRU Wisdom 👋</h2>
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
                    <form action="ai_assistant.php" method="GET" class="search-inner" id="main-search-form">
                        <i data-lucide="search" id="search-icon" style="color: hsl(var(--muted-foreground));"></i>
                        <input type="text" name="q" id="search-input" placeholder="พิมพ์สิ่งที่คุณต้องการค้นหา..."
                            autofocus>
                        <button type="button" onclick="toggleAI()" id="ai-toggle-btn" class="ai-badge"
                            style="cursor: pointer; border: none; background: #f1f5f9; color: #64748b; font-size: 0.65rem; padding: 4px 8px; border-radius: 6px;">
                            <i data-lucide="sparkles" style="width: 12px; height: 12px; margin-right: 4px;"></i> AI
                            Mode: OFF
                        </button>
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
                    <div class="stat-value"><?php echo e($stats['document']); ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon"><i data-lucide="pen-tool"></i></div>
                        <span class="stat-label">บทความ Wiki</span>
                    </div>
                    <div class="stat-value"><?php echo e($stats['wiki']); ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon"><i data-lucide="message-circle"></i></div>
                        <span class="stat-label">ถาม-ตอบ (Q&A)</span>
                    </div>
                    <div class="stat-value"><?php echo e($stats['qa']); ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon"><i data-lucide="award"></i></div>
                        <span class="stat-label">ผู้เชี่ยวชาญ</span>
                    </div>
                    <div class="stat-value"><?php echo e($stats['experts']); ?></div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="main-content-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2.5rem;">
                <section>
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h3
                            style="font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem; color: #0f172a;">
                            <div
                                style="width: 32px; height: 32px; background: rgba(20, 184, 166, 0.1); color: var(--teal-primary); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="trending-up" style="width: 18px;"></i>
                            </div>
                            เนื้อหาแนะนำ
                        </h3>
                        <a href="browse.php"
                            style="color: var(--teal-primary); font-size: 0.875rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                            ดูทั้งหมด <i data-lucide="arrow-right" style="width: 16px;"></i>
                        </a>
                    </div>

                    <div class="knowledge-grid">
                        <?php
                        $idx = 0;
                        foreach ($latest_docs as $doc):
                            $idx++;
                            $type_icon = 'file-text';
                            $brand_color = '#3b82f6'; // blue
                            if ($doc['type'] == 'wiki') {
                                $type_icon = 'book-open';
                                $brand_color = '#a855f7'; // purple
                            } elseif ($doc['type'] == 'qa') {
                                $type_icon = 'help-circle';
                                $brand_color = '#f59e0b'; // orange
                            } elseif ($doc['type'] == 'training') {
                                $type_icon = 'graduation-cap';
                                $brand_color = '#10b981'; // teal/green
                            }

                            $is_recommended = ($idx <= 2);
                            ?>
                            <div class="premium-card <?php echo $is_recommended ? 'is-recommended' : ''; ?>"
                                onclick="location.href='<?php echo $doc['base_url'] . $doc['id']; ?>'">

                                <?php if ($is_recommended): ?>
                                    <div class="recommend-badge">แนะนำ</div>
                                <?php endif; ?>

                                <div class="card-top">
                                    <div class="type-icon-box"
                                        style="background: <?php echo $brand_color; ?>15; color: <?php echo $brand_color; ?>;">
                                        <i data-lucide="<?php echo $type_icon; ?>" style="width: 18px;"></i>
                                    </div>
                                    <span class="type-tag"><?php echo $type_labels[$doc['type']] ?? 'เนื้อหา'; ?></span>
                                </div>

                                <h3 class="card-title"><?php echo e($doc['title']); ?></h3>
                                <p class="card-desc">
                                    <?php echo mb_strimwidth(strip_tags($doc['content']), 0, 150, "..."); ?>
                                </p>

                                <div class="card-tags">
                                    <?php
                                    $tags = !empty($doc['tags']) ? explode(',', $doc['tags']) : [];
                                    if (empty($tags))
                                        $tags = [$doc['category_name']];
                                    foreach (array_slice($tags, 0, 3) as $tag):
                                        ?>
                                        <span class="tag-pill"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                    <?php endforeach; ?>
                                </div>

                                <div class="card-footer">
                                    <div class="footer-author">
                                        <div class="author-sm-avatar">
                                            <?php echo strtoupper(substr($doc['author_username'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <span
                                            class="author-sm-name"><?php echo e($doc['author_full_name'] ?? $doc['author_username'] ?? 'Anonymous'); ?></span>
                                    </div>

                                    <div class="footer-stats">
                                        <div class="stat-item-sm">
                                            <i data-lucide="eye" style="width: 12px;"></i>
                                            <?php echo e($doc['views']); ?>
                                        </div>
                                        <div class="stat-item-sm">
                                            <i data-lucide="heart" style="width: 12px;"></i>
                                            <?php echo e($doc['like_count']); ?>
                                        </div>
                                        <div class="stat-item-sm">
                                            <i data-lucide="message-circle" style="width: 12px;"></i>
                                            <?php echo e($doc['comment_count']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <aside>
                    <!-- AI Smart Hub Widget -->
                    <div class="ai-smart-card"
                        style="background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%); padding: 1.5rem; border-radius: 1rem; color: white; margin-bottom: 2rem; position: relative; overflow: hidden; box-shadow: 0 10px 25px rgba(20, 184, 166, 0.3);">
                        <i data-lucide="sparkles"
                            style="position: absolute; right: -10px; top: -10px; width: 60px; height: 60px; opacity: 0.2;"></i>
                        <h4
                            style="font-size: 1rem; font-weight: 800; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="bot" style="width: 18px;"></i> AI Smart Insight
                        </h4>
                        <p style="font-size: 0.8rem; opacity: 0.9; line-height: 1.5;">วันนี้มีเอกสาร 5
                            รายการใหม่ที่ตรงกับทักษะ "ประกันคุณภาพ" ของคุณครับ</p>
                        <a href="ai_assistant.php"
                            style="display: block; width: 100%; padding: 0.65rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); border-radius: 10px; color: white; text-align: center; text-decoration: none; margin-top: 1rem; font-size: 0.8125rem; font-weight: 700;">ดูข้อมูลเจาะลึก</a>
                    </div>

                    <div
                        style="background: white; border-radius: 0.75rem; border: 1px solid var(--border-color); padding: 1.5rem;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1.5rem;">หัวข้อยอดนิยม</h3>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php foreach ($trending_topics as $topic): ?>
                                <a href="browse.php?cat_id=<?php echo $topic['id']; ?>" class="tag-badge"
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
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1.5rem;">เนื้อหาอัปเดตล่าสุด</h3>
                        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                            <?php foreach ($recent_activity as $act): ?>
                                <div style="display: flex; gap: 0.75rem;">
                                    <div
                                        style="width: 32px; height: 32px; background: rgba(20, 184, 166, 0.1); color: var(--teal-primary); border-radius: 8px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                        <i data-lucide="<?php echo $act['category_icon'] ?? 'file-text'; ?>"
                                            style="width: 16px;"></i>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <a href="view.php?id=<?php echo $act['id']; ?>"
                                            style="text-decoration: none; color: #1e293b; font-size: 0.8125rem; font-weight: 700; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;">
                                            <?php echo htmlspecialchars($act['title']); ?>
                                        </a>
                                        <div
                                            style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.6875rem; color: #64748b;">
                                            <span
                                                style="color: var(--teal-primary); font-weight: 600;"><?php echo htmlspecialchars($act['category_name'] ?? 'ทั่วไป'); ?></span>
                                            <span>&bull;</span>
                                            <span><?php echo htmlspecialchars($act['full_name'] ?? 'System'); ?></span>
                                        </div>
                                        <span style="font-size: 0.625rem; color: #94a3b8; display: block; margin-top: 2px;">
                                            <?php echo date('d M Y', strtotime($act['created_at'])); ?>
                                        </span>
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

        let aiMode = false;
        function toggleAI() {
            aiMode = !aiMode;
            const btn = document.getElementById('ai-toggle-btn');
            const icon = document.getElementById('search-icon');
            const form = document.getElementById('main-search-form');
            const input = document.getElementById('search-input');

            if (aiMode) {
                btn.innerHTML = `<i data-lucide="sparkles" style="width: 12px; height: 12px; margin-right: 4px;"></i> AI Mode: ON`;
                btn.style.background = 'linear-gradient(135deg, #6366f1 0%, #a855f7 100%)';
                btn.style.color = 'white';
                icon.style.color = '#6366f1';
                form.action = 'ai_assistant.php';
                input.placeholder = "ถามคำถามกับ AI อัจฉริยะ...";
            } else {
                btn.innerHTML = `<i data-lucide="sparkles" style="width: 12px; height: 12px; margin-right: 4px;"></i> AI Mode: OFF`;
                btn.style.background = '#f1f5f9';
                btn.style.color = '#64748b';
                icon.style.color = 'hsl(var(--muted-foreground))';
                form.action = 'browse.php';
                input.placeholder = "พิมพ์สิ่งที่คุณต้องการค้นหา...";
            }
            lucide.createIcons();
        }
    </script>
</body>

</html>