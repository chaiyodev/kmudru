<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check Admin Access
require_admin();

$pdo = get_pdo();

// Admin Stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_docs = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$total_comments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$total_communities = $pdo->query("SELECT COUNT(*) FROM communities")->fetchColumn();
$total_trainings = $pdo->query("SELECT COUNT(*) FROM trainings")->fetchColumn();

// AI Search stats (with details column now available)
$total_ai_searches = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'ai_search'")->fetchColumn();

// Content this week
$docs_this_week = $pdo->query("SELECT COUNT(*) FROM documents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$users_this_week = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Top searched terms
$top_searches = $pdo->query("SELECT details, COUNT(*) as cnt FROM activity_logs WHERE action = 'ai_search' AND details IS NOT NULL GROUP BY details ORDER BY cnt DESC LIMIT 5")->fetchAll();

// Visitor Statistics
$total_visits = $pdo->query("SELECT COUNT(*) FROM visitor_stats")->fetchColumn();
$internal_visits = $pdo->query("SELECT COUNT(*) FROM visitor_stats WHERE is_internal = 1")->fetchColumn();
$external_visits = $pdo->query("SELECT COUNT(*) FROM visitor_stats WHERE is_internal = 0")->fetchColumn();
$visits_today = $pdo->query("SELECT COUNT(*) FROM visitor_stats WHERE visit_date = CURDATE()")->fetchColumn();
$visits_this_week = $pdo->query("SELECT COUNT(*) FROM visitor_stats WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

// Most visited pages
$top_pages = $pdo->query("SELECT page_visited, COUNT(*) as cnt FROM visitor_stats GROUP BY page_visited ORDER BY cnt DESC LIMIT 5")->fetchAll();

$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<?php
$page_title = 'ผู้ดูแลระบบ | UDRU Wisdom';
$extra_css = <<<'HTML'
    <style>
        .admin-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .admin-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: hsl(var(--primary) / 0.1);
            color: var(--teal-primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-card {
            background: white;
            border: 1px dashed var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition-base);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            color: hsl(var(--muted-foreground));
            text-decoration: none;
        }

        .action-card:hover {
            background: hsl(var(--primary) / 0.05);
            border-color: var(--teal-primary);
            color: var(--teal-primary);
        }
    </style>
HTML;
require_once 'includes/head.php';
?>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>แผงควบคุมผู้ดูแลระบบ</h2>
                    <p>จัดการสมาชิกและตรวจสอบความเรียบร้อยของระบบ</p>
                </div>
            </header>

            <!-- Stats Overview - Row 1 -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="admin-card">
                    <div class="admin-icon"><i data-lucide="users"></i></div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;">
                            <?php echo $total_users; ?>
                        </div>
                        <div style="color: hsl(var(--muted-foreground));">สมาชิกทั้งหมด <span
                                style="color: var(--teal-primary); font-size: 0.75rem;">(+<?php echo $users_this_week; ?>
                                สัปดาห์นี้)</span></div>
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-icon" style="background: hsl(45 93% 47% / 0.1); color: hsl(45 93% 47%);"><i
                            data-lucide="file-text"></i></div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;">
                            <?php echo $total_docs; ?>
                        </div>
                        <div style="color: hsl(var(--muted-foreground));">บทความในระบบ <span
                                style="color: var(--teal-primary); font-size: 0.75rem;">(+<?php echo $docs_this_week; ?>
                                สัปดาห์นี้)</span></div>
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-icon" style="background: hsl(339 90% 50% / 0.1); color: hsl(339 90% 50%);"><i
                            data-lucide="message-square"></i></div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;">
                            <?php echo $total_comments; ?>
                        </div>
                        <div style="color: hsl(var(--muted-foreground));">ความคิดเห็น</div>
                    </div>
                </div>
            </div>

            <!-- Stats Overview - Row 2 -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="admin-card">
                    <div class="admin-icon" style="background: hsl(250 90% 60% / 0.1); color: hsl(250 90% 60%);"><i
                            data-lucide="share-2"></i></div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;">
                            <?php echo $total_communities; ?>
                        </div>
                        <div style="color: hsl(var(--muted-foreground));">เครือข่าย CoP</div>
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-icon" style="background: hsl(160 90% 40% / 0.1); color: hsl(160 90% 40%);"><i
                            data-lucide="graduation-cap"></i></div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;">
                            <?php echo $total_trainings; ?>
                        </div>
                        <div style="color: hsl(var(--muted-foreground));">หลักสูตรอบรม</div>
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-icon" style="background: hsl(200 90% 50% / 0.1); color: hsl(200 90% 50%);"><i
                            data-lucide="bot"></i></div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;">
                            <?php echo $total_ai_searches; ?>
                        </div>
                        <div style="color: hsl(var(--muted-foreground));">AI Searches</div>
                    </div>
                </div>
            </div>

            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">การจัดการ (Quick Actions)</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <a href="admin_users.php" class="action-card">
                    <i data-lucide="user-plus" style="width: 32px; height: 32px;"></i>
                    <span style="font-weight: 600;">จัดการสมาชิก</span>
                </a>
                <a href="browse.php" class="action-card">
                    <i data-lucide="file-x" style="width: 32px; height: 32px;"></i>
                    <span style="font-weight: 600;">ลบบทความ</span>
                </a>
                <a href="cop.php" class="action-card">
                    <i data-lucide="network" style="width: 32px; height: 32px;"></i>
                    <span style="font-weight: 600;">จัดการ CoP</span>
                </a>
                <a href="category_create.php" class="action-card">
                    <i data-lucide="folder-plus" style="width: 32px; height: 32px;"></i>
                    <span style="font-weight: 600;">จัดการหมวดหมู่</span>
                </a>
                <a href="admin_logs.php" class="action-card">
                    <i data-lucide="history" style="width: 32px; height: 32px;"></i>
                    <span style="font-weight: 600;">System Logs</span>
                </a>
            </div>

            <!-- Visitor Statistics -->
            <div
                style="background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%); border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; color: white;">
                <h3
                    style="font-size: 1rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="activity" style="width: 18px;"></i>
                    สถิติการเข้าเว็บไซต์
                </h3>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div
                        style="background: rgba(255,255,255,0.15); padding: 1rem; border-radius: 0.75rem; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 800;"><?php echo number_format($total_visits); ?>
                        </div>
                        <div style="font-size: 0.75rem; opacity: 0.9;">การเข้าชมทั้งหมด</div>
                    </div>
                    <div
                        style="background: rgba(255,255,255,0.15); padding: 1rem; border-radius: 0.75rem; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 800;"><?php echo number_format($visits_today); ?>
                        </div>
                        <div style="font-size: 0.75rem; opacity: 0.9;">วันนี้</div>
                    </div>
                    <div
                        style="background: rgba(20,184,166,0.3); padding: 1rem; border-radius: 0.75rem; text-align: center; border: 1px solid rgba(255,255,255,0.3);">
                        <div style="font-size: 2rem; font-weight: 800;"><?php echo number_format($internal_visits); ?>
                        </div>
                        <div style="font-size: 0.75rem; opacity: 0.9;">🏠 ภายใน (Login)</div>
                    </div>
                    <div
                        style="background: rgba(244,63,94,0.3); padding: 1rem; border-radius: 0.75rem; text-align: center; border: 1px solid rgba(255,255,255,0.3);">
                        <div style="font-size: 2rem; font-weight: 800;"><?php echo number_format($external_visits); ?>
                        </div>
                        <div style="font-size: 0.75rem; opacity: 0.9;">🌐 ภายนอก (Guest)</div>
                    </div>
                </div>
                <?php if (!empty($top_pages)): ?>
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.2);">
                        <div style="font-size: 0.8rem; opacity: 0.8; margin-bottom: 0.5rem;">หน้าที่เข้าชมมากที่สุด:</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php foreach ($top_pages as $pg): ?>
                                <span
                                    style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 100px; font-size: 0.75rem;">
                                    <?php echo htmlspecialchars($pg['page_visited']); ?> (<?php echo $pg['cnt']; ?>)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top AI Searches -->
            <?php if (!empty($top_searches)): ?>
                <div
                    style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 2rem;">
                    <h3
                        style="font-size: 1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="trending-up" style="width: 18px; color: var(--teal-primary);"></i>
                        คำค้นหา AI ยอดนิยม
                    </h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php foreach ($top_searches as $search): ?>
                            <span
                                style="background: hsl(var(--primary) / 0.1); color: var(--teal-primary); padding: 0.5rem 1rem; border-radius: 100px; font-size: 0.875rem; font-weight: 500;">
                                <?php echo htmlspecialchars($search['details']); ?> <span
                                    style="opacity: 0.6;">(<?php echo $search['cnt']; ?>)</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-top: 1rem; font-size: 0.8rem; color: hsl(var(--muted-foreground));">
                        ข้อมูลนี้ช่วยระบุช่องว่างความรู้ที่ควรเติมเต็มในระบบ</p>
                </div>
            <?php endif; ?>

            <!-- Recent Users -->
            <div
                style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
                <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1rem;">สมาชิกใหม่ล่าสุด</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 0.75rem; font-size: 0.875rem; color: hsl(var(--muted-foreground));">
                                ชื่อผู้ใช้</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; color: hsl(var(--muted-foreground));">
                                ชื่อ-นามสกุล</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; color: hsl(var(--muted-foreground));">
                                สถานะ</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; color: hsl(var(--muted-foreground));">
                                วันที่สมัคร</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div class="avatar-sm" style="width: 24px; height: 24px; font-size: 10px;">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </td>
                                <td style="padding: 0.75rem;"><span class="badge"
                                        style="background: <?php echo $user['role'] === 'admin' ? 'var(--teal-primary)' : 'hsl(var(--muted))'; ?>; color: <?php echo $user['role'] === 'admin' ? 'white' : 'inherit'; ?>;">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span></td>
                                <td style="padding: 0.75rem; color: hsl(var(--muted-foreground));">
                                    <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top: 1rem; text-align: center;">
                    <a href="admin_users.php" class="btn-text">ดูสมาชิกทั้งหมด</a>
                </div>
            </div>

        </main>
    </div>
<?php require_once 'includes/footer.php'; ?>