<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check Admin Access
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$pdo = get_pdo();

// Admin Stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_docs = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$total_comments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผู้ดูแลระบบ | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
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
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>แผงควบคุมผู้ดูแลระบบ</h2>
                    <p>จัดการสมาชิกและตรวจสอบความเรียบร้อยของระบบ</p>
                </div>
            </header>

            <!-- Stats Overview -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="admin-card">
                    <div class="admin-icon"><i data-lucide="users"></i></div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;">
                            <?php echo $total_users; ?>
                        </div>
                        <div style="color: hsl(var(--muted-foreground));">สมาชิกทั้งหมด</div>
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-icon" style="background: hsl(45 93% 47% / 0.1); color: hsl(45 93% 47%);"><i
                            data-lucide="file-text"></i></div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;">
                            <?php echo $total_docs; ?>
                        </div>
                        <div style="color: hsl(var(--muted-foreground));">บทความในระบบ</div>
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

            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">การจัดการ (Quick Actions)</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 3rem;">
                <a href="admin_users.php" class="action-card">
                    <i data-lucide="user-plus" style="width: 32px; height: 32px;"></i>
                    <span style="font-weight: 600;">จัดการสมาชิก</span>
                </a>
                <a href="browse.php" class="action-card">
                    <i data-lucide="file-x" style="width: 32px; height: 32px;"></i>
                    <span style="font-weight: 600;">ลบบทความ</span>
                </a>
                <a href="#" class="action-card" onclick="alert('Coming Soon: System Logs')">
                    <i data-lucide="shield-check" style="width: 32px; height: 32px;"></i>
                    <span style="font-weight: 600;">ตรวจสอบระบบ</span>
                </a>
                <a href="category_create.php" class="action-card">
                    <i data-lucide="folder-plus" style="width: 32px; height: 32px;"></i>
                    <span style="font-weight: 600;">จัดการหมวดหมู่</span>
                </a>
            </div>

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
    <script>lucide.createIcons();</script>
</body>

</html>