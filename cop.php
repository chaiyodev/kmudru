<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

$pdo = get_pdo();
$communities = [];
$stats = ['total' => 0, 'members' => 0, 'active' => 0];

if ($pdo) {
    // Fetch communities with details
    $stmt = $pdo->query("SELECT c.*, cat.name as category_name,
                        (SELECT COUNT(*) FROM community_members WHERE community_id = c.id) as member_count 
                        FROM communities c
                        LEFT JOIN categories cat ON c.category_id = cat.id
                        ORDER BY c.created_at DESC");
    $communities = $stmt->fetchAll();

    $stats['total'] = count($communities);
    $stats['members'] = $pdo->query("SELECT COUNT(*) FROM community_members")->fetchColumn();
    $stats['active'] = $pdo->query("SELECT COUNT(DISTINCT community_id) FROM community_members WHERE joined_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชุมชน CoP | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/cop.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>ชุมชนแห่งการเรียนรู้ (CoP)</h2>
                    <p>เครือข่ายความร่วมมือของบุคลากร UDRU เพื่อความเป็นเลิศทางวิชาการและการจัดการ</p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo is_logged_in() ? 'cop_create.php' : 'javascript:void(0)'; ?>" 
                       onclick="<?php echo is_logged_in() ? '' : "return requireLoginPrompt('สร้างชุมชนใหม่')"; ?>" 
                       class="btn-primary"
                        style="background: #8b5cf6; box-shadow: 0 4px 14px 0 rgba(139, 92, 246, 0.39);">
                        <i data-lucide="plus-circle"></i> สร้างชุมชนใหม่
                    </a>
                </div>
            </header>

            <!-- Stats Bar -->
            <div class="grid-stats" style="margin-bottom: 3rem;">

                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #f5f3ff; color: #8b5cf6;"><i data-lucide="layers"></i>
                        </div>
                        <span class="stat-label">ชุมชนทั้งหมด</span>
                    </div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i data-lucide="users"></i>
                        </div>
                        <span class="stat-label">สมาชิกเครือข่าย</span>
                    </div>
                    <div class="stat-value"><?php echo $stats['members']; ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #fff7ed; color: #f59e0b;"><i data-lucide="zap"></i>
                        </div>
                        <span class="stat-label">ความเคลื่อนไหว (7 วัน)</span>
                    </div>
                    <div class="stat-value"><?php echo $stats['active']; ?></div>
                </div>
            </div>

            <!-- Content -->
            <div class="cop-grid">
                <?php foreach ($communities as $cop): ?>
                    <div class="cop-card" onclick="location.href='cop_view.php?id=<?php echo $cop['id']; ?>'">
                        <div class="cop-card-banner"
                            style="background: <?php echo $cop['color_theme']; ?>; <?php echo $cop['cover_image'] ? "background-image: url('{$cop['cover_image']}'); background-size: cover; background-position: center;" : ""; ?>">
                            <div class="cop-status-tag">
                                <i data-lucide="<?php echo $cop['is_public'] ? 'globe' : 'lock'; ?>"
                                    style="width: 10px; display: inline-block; vertical-align: middle;"></i>
                                <?php echo $cop['is_public'] ? 'Public' : 'Private'; ?>
                            </div>
                            <div class="cop-card-avatar" style="color: <?php echo $cop['color_theme']; ?>">
                                <?php echo $cop['icon'] ?: '🤝'; ?>
                            </div>
                        </div>

                        <div style="padding: 2.5rem 1.5rem 1.5rem; flex: 1; display: flex; flex-direction: column;">
                            <div style="margin-bottom: 1rem;">
                                <span class="category-tag"><?php echo e($cop['category_name'] ?: 'ทั่วไป'); ?></span>
                            </div>

                            <h3
                                style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; color: #0f172a;">
                                <?php echo e($cop['name']); ?>
                            </h3>

                            <p
                                style="font-size: 0.875rem; color: #64748b; line-height: 1.6; margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1;">
                                <?php echo e($cop['description']); ?>
                            </p>

                            <div
                                style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid #f1f5f9; margin-bottom: 1rem;">
                                <div
                                    style="display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; font-size: 0.75rem; font-weight: 600;">
                                    <i data-lucide="users" style="width: 14px;"></i> <?php echo $cop['member_count']; ?>
                                    สมาชิก
                                </div>
                                <div style="display: flex; -webkit-rtl-ordering: visual; direction: rtl;">
                                    <?php
                                    $stmt_m = $pdo->prepare("SELECT u.avatar FROM community_members m JOIN users u ON m.user_id = u.id WHERE community_id = ? LIMIT 3");
                                    $stmt_m->execute([$cop['id']]);
                                    $top_members = $stmt_m->fetchAll();
                                    foreach($top_members as $tm): ?>
                                        <div style="width: 24px; height: 24px; border-radius: 50%; border: 2px solid white; background: #e2e8f0; margin-left: -8px; background-image: url('uploads/avatars/<?php echo $tm['avatar'] ?: 'default.png'; ?>'); background-size: cover; background-position: center;"></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <button class="btn-join">
                                ดูรายละเอียดชุมชน <i data-lucide="arrow-right" style="width: 16px;"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>