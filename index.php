<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

$pdo = get_pdo();
$user = null;
if (is_logged_in() && $pdo) {
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
                   u.username as author_username, u.full_name as author_full_name, u.avatar as author_avatar,
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
                   'System' as author_username, 'UDRU Training' as author_full_name, NULL as author_avatar,
                   0 as like_count, 0 as comment_count,
                   'training_view.php?id=' as base_url
            FROM trainings t
            LEFT JOIN categories c ON t.category_id = c.id)
            ORDER BY created_at DESC 
            LIMIT 6";
        $latest_docs = $pdo->query($doc_query)->fetchAll();

        // Update type labels for display
        $type_labels = [
            'document' => 'เอกสาร',
            'wiki' => 'Wiki',
            'qa' => 'Q&A',
            'training' => 'การฝึกอบรม'
        ];

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
            LIMIT 5";
        $recent_activity = $pdo->query($activity_query)->fetchAll();

        // Fetch personalized AI insight (count of new docs in user's most viewed category)
        $ai_insight_count = 0;
        $ai_insight_category = 'ประกันคุณภาพ'; // Default
        if ($user) {
            // Find most frequent category in user's history or just most popular one
            $stmt = $pdo->query("SELECT name FROM categories ORDER BY id ASC LIMIT 1");
            $ai_insight_category = $stmt->fetchColumn() ?: 'ประกันคุณภาพ';

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $ai_insight_count = $stmt->fetchColumn();
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) FROM documents WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $ai_insight_count = $stmt->fetchColumn();
        }

    } catch (PDOException $e) {
    }
}

$page_title = 'UDRU Wisdom | UDRU Knowledge Hub';
$extra_css = '<link rel="stylesheet" href="assets/css/index.css">';
require_once 'includes/head.php';
?>
    <div class="app-container">
        <!-- Standardized Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Viewport -->
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <?php if ($user): ?>
                            <h2>ยินดีต้อนรับ, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?> 👋</h2>
                        <?php else: ?>
                            <h2>ยินดีต้อนรับสู่ UDRU Wisdom 👋</h2>
                        <?php endif; ?>

                        <?php if (function_exists('render_notification_component')) {
                            render_notification_component($unread_notifications ?? 0);
                        } ?>
                    </div>
                    <p>สืบค้นและแบ่งปันองค์ความรู้เพื่อสังคมแห่งการเรียนรู้ UDRU</p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo is_logged_in() ? 'create.php' : 'javascript:void(0)'; ?>" 
                       onclick="<?php echo is_logged_in() ? '' : "return requireLoginPrompt('สร้างองค์ความรู้')"; ?>" 
                       class="btn-primary"><i data-lucide="plus"></i>สร้างองค์ความรู้</a>
                </div>
            </header>

            <!-- Premium Hero Section -->
            <section class="hero-premium animate-fade-in">
                <div class="hero-mask"></div>
                <div class="hero-content">
                    <span class="hero-tag">มหาวิทยาลัยราชภัฏอุดรธานี</span>
                    <h1>ขุมปัญญาอัจฉริยะ <span>UDRU Wisdom</span></h1>
                    <p>ศูนย์กลางการรวบรวมเทคนิค วิจัย และแนวทางการทำงานที่ดีที่สุด เพื่อสร้างสังคมแห่งการเรียนรู้ที่ยั่งยืน</p>

                    <form action="browse.php" method="GET" class="hero-search-box">
                        <div class="search-input-group">
                            <i data-lucide="search" class="search-lead-icon"></i>
                            <input type="text" name="q" placeholder="ค้นหาบทความ, งานวิจัย หรือชุดความรู้..." autocomplete="off">
                            <button type="submit" class="hero-search-btn">
                                <span>ค้นหาข้อมูล</span>
                                <i data-lucide="arrow-right"></i>
                            </button>
                        </div>
                        <div class="hero-quick-tags">
                            <span>ยอดนิยม:</span>
                            <a href="browse.php?q=AI">#AI</a>
                            <a href="browse.php?q=วิจัย">#วิจัย</a>
                            <a href="browse.php?q=KM">#KM</a>
                        </div>
                    </form>
                </div>
                <div class="hero-visual" id="heroVisual">
                    <div class="v-blob v-1"></div>
                    <div class="v-blob v-2"></div>
                    
                    <!-- Premium Wisdom Aura -->
                    <div class="hero-feather-wrapper" id="featherParallax">
                        <div class="feather-glow"></div>
                        <div class="wisdom-sparkle sparkle-1"></div>
                        <div class="wisdom-sparkle sparkle-2"></div>
                        <div class="wisdom-sparkle sparkle-3"></div>
                        <div class="wisdom-sparkle sparkle-4"></div>
                    </div>
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
                                
                                <?php 
                                $cover_image = null;
                                if (preg_match('/<img[^>]+src="([^">]+)"/i', $doc['content'], $matches)) {
                                    $cover_image = $matches[1];
                                } elseif (preg_match('/!\[.*?\]\((.*?)\)/i', $doc['content'], $matches)) {
                                    $cover_image = $matches[1];
                                }
                                ?>
                                <?php if ($cover_image): ?>
                                    <div style="width: calc(100% + 40px); height: 180px; margin: 1rem -20px 1.5rem -20px; border-radius: 8px; overflow: hidden; position: relative;">
                                        <div style="position: absolute; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.03);"></div>
                                        <img src="<?php echo htmlspecialchars($cover_image); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;" loading="lazy">
                                    </div>
                                <?php endif; ?>

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
                                        <div class="author-sm-avatar" <?php if(!empty($doc['author_avatar']) && file_exists('uploads/avatars/' . $doc['author_avatar'])) echo 'style="background-image: url(\'uploads/avatars/' . htmlspecialchars($doc['author_avatar']) . '\'); background-size: cover; background-position: center; color: transparent; border: 1px solid var(--border-color);"'; ?>>
                                            <?php if(empty($doc['author_avatar']) || !file_exists('uploads/avatars/' . $doc['author_avatar'])) echo mb_strtoupper(mb_substr($doc['author_username'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8'); ?>
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

                <aside style="display: flex; flex-direction: column; position: sticky; top: 2rem; align-self: start;">
                    <!-- AI Smart Hub Widget -->
                    <div class="ai-smart-card"
                        style="background: linear-gradient(135deg, #14b8a6 0%, #0ea5e9 100%); padding: 1.5rem; border-radius: 1rem; color: white; margin-bottom: 2rem; position: relative; overflow: hidden; box-shadow: 0 10px 25px rgba(20, 184, 166, 0.3);">
                        <i data-lucide="sparkles"
                            style="position: absolute; right: -10px; top: -10px; width: 60px; height: 60px; opacity: 0.2;"></i>
                        <h4
                            style="font-size: 1rem; font-weight: 800; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="bot" style="width: 18px;"></i> AI Smart Insight
                        </h4>
                        <p style="font-size: 0.8rem; opacity: 0.9; line-height: 1.5;">
                            วันนี้มีเนื้อหาใหม่ <?php echo $ai_insight_count; ?> รายการในหัวข้อ
                            "<?php echo $ai_insight_category; ?>" ที่คุณอาจสนใจครับ
                        </p>
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

<?php 
$extra_js = <<<'HTML'
    <script>
        // AI Toggle Logic
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

        // Premium Feather Parallax Logic
        const hero = document.querySelector('.hero-premium');
        const feather = document.getElementById('featherParallax');
        
        if (hero && feather) {
            hero.addEventListener('mousemove', (e) => {
                const rect = hero.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) - 0.5;
                const y = ((e.clientY - rect.top) / rect.height) - 0.5;
                
                requestAnimationFrame(() => {
                    // Subtle movement and rotation based on mouse position
                    feather.style.transform = `translate(${x * 40}px, ${y * 40}px) rotate(${x * 10}deg)`;
                });
            });
            
            // Return to center when mouse leaves
            hero.addEventListener('mouseleave', () => {
                requestAnimationFrame(() => {
                    feather.style.transform = `translate(0, 0) rotate(0deg)`;
                });
            });
        }
    </script>
HTML;
require_once 'includes/footer.php';
?>