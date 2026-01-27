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
    <title>ชุมชน CoP | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .cop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }

        .cop-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .cop-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            border-color: var(--teal-primary);
        }

        .cop-card-banner {
            height: 140px;
            position: relative;
            background: #14b8a6;
            overflow: hidden;
        }

        .cop-card-avatar {
            width: 70px;
            height: 70px;
            border-radius: 1.25rem;
            background: white;
            border: 4px solid white;
            position: absolute;
            left: 1.5rem;
            bottom: -35px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            z-index: 2;
        }

        .cop-status-tag {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 4px 10px;
            border-radius: 100px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .category-tag {
            padding: 4px 10px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-join {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.75rem;
            border: none;
            font-weight: 700;
            background: #f1f5f9;
            color: #475569;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .cop-card:hover .btn-join {
            background: var(--teal-primary);
            color: white;
        }
    </style>
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
                    <a href="cop_create.php" class="btn-primary"
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
                                    <!-- Simple Avatar Stack Mockup -->
                                    <div
                                        style="width: 24px; height: 24px; border-radius: 50%; border: 2px solid white; background: #e2e8f0; margin-left: -8px;">
                                    </div>
                                    <div
                                        style="width: 24px; height: 24px; border-radius: 50%; border: 2px solid white; background: #cbd5e1; margin-left: -8px;">
                                    </div>
                                    <div
                                        style="width: 24px; height: 24px; border-radius: 50%; border: 2px solid white; background: #94a3b8; margin-left: -8px;">
                                    </div>
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