<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$communities = [];
$stats = ['total' => 0, 'members' => 0, 'joined' => 0];

if ($pdo) {
    // Fetch communities
    $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM community_members WHERE community_id = c.id) as member_count FROM communities c");
    $communities = $stmt->fetchAll();

    $stats['total'] = count($communities);
    $stmt = $pdo->query("SELECT COUNT(*) FROM community_members");
    $stats['members'] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เครือข่าย CoP | KM Portal</title>
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
                    <h2>เครือข่าย CoP (Community of Practice)</h2>
                    <p>พื้นที่แลกเปลี่ยนเรียนรู้และสร้างความร่วมมือเชิงลึกในแต่ละสาขา</p>
                </div>
                <div class="header-actions">
                    <a href="cop_create.php" class="btn-primary" style="background:#8b5cf6; text-decoration: none;">
                        <i data-lucide="plus"></i>สร้างชุมชน
                    </a>
                </div>
            </header>

            <!-- Stats Page Header -->
            <div class="grid-stats">
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: hsl(262 83% 58% / 0.1); color: hsl(262 83% 58%);"><i
                                data-lucide="layers"></i></div>
                        <span class="stat-label">ชุมชนทั้งหมด</span>
                    </div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: hsl(174 62% 32% / 0.1); color: var(--teal-primary);">
                            <i data-lucide="user-check"></i>
                        </div>
                        <span class="stat-label">เข้าร่วมแล้ว</span>
                    </div>
                    <div class="stat-value">0</div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: hsl(38 92% 50% / 0.1); color: hsl(38 92% 50%);"><i
                                data-lucide="users"></i></div>
                        <span class="stat-label">สมาชิกทั้งหมด</span>
                    </div>
                    <div class="stat-value"><?php echo $stats['members']; ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: hsl(0 84% 60% / 0.1); color: hsl(0 84% 60%);"><i
                                data-lucide="trending-up"></i></div>
                        <span class="stat-label">กิจกรรมวันนี้</span>
                    </div>
                    <div class="stat-value">12</div>
                </div>
            </div>

            <!-- Community Grid -->
            <div class="knowledge-grid">
                <?php foreach ($communities as $cop): ?>
                    <div class="card-knowledge">
                        <div style="display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1.25rem;">
                            <div
                                style="width: 48px; height: 48px; border-radius: 12px; background: hsl(var(--muted)); display: flex; align-items: center; justify-content: center; color: var(--teal-primary);">
                                <i data-lucide="<?php echo htmlspecialchars($cop['icon'] ?? 'users'); ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <h3 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($cop['name']); ?></h3>
                                <span
                                    style="font-size: 0.75rem; color: hsl(var(--muted-foreground));"><?php echo $cop['member_count']; ?>
                                    สมาชิก</span>
                            </div>
                        </div>
                        <p class="card-excerpt"><?php echo htmlspecialchars($cop['description']); ?></p>

                        <div class="card-footer" style="border: none; padding-top: 0;">
                            <button class="btn-primary" style="width: 100%; justify-content: center;"
                                onclick="joinGroup('<?php echo htmlspecialchars($cop['name']); ?>')">เข้าร่วมกลุ่ม</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        lucide.createIcons();

        function joinGroup(groupName) {
            Swal.fire({
                title: 'ยินดีต้อนรับ!',
                text: 'คุณได้เข้าร่วมกลุ่ม ' + groupName + ' เรียบร้อยแล้ว',
                icon: 'success',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: 'var(--teal-primary)',
                background: 'white',
                color: 'hsl(var(--foreground))'
            });
        }
    </script>
</body>

</html>