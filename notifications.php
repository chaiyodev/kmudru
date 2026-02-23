<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

$pdo = get_pdo();
$user_id = is_logged_in() ? $_SESSION['user_id'] : 0;

if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Mark all as read when viewing this page
mark_all_read($pdo, $user_id);

$notifications = get_recent_notifications($pdo, $user_id, 20);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การแจ้งเตือน | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .notif-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .notif-item {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 1.25rem;
            text-decoration: none;
            color: inherit;
            transition: background 0.2s;
        }

        .notif-item:last-child {
            border-bottom: none;
        }

        .notif-item:hover {
            background: #f8fafc;
        }

        .notif-item.unread {
            background: hsl(var(--primary) / 0.03);
            border-left: 4px solid var(--teal-primary);
        }

        .notif-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: hsl(var(--primary) / 0.1);
            color: var(--teal-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notif-content {
            flex: 1;
        }

        .notif-msg {
            font-size: 0.9375rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }

        .notif-time {
            font-size: 0.75rem;
            color: #64748b;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>การแจ้งเตือน (Notifications)</h2>
                    <p>ติดตามความเคลื่อนไหวล่าสุดในระบบ KM ของคุณ</p>
                </div>
            </header>

            <div class="notif-card">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i data-lucide="bell-off" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>ยังไม่มีการแจ้งเตือนในขณะนี้</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <?php
                        $icon = 'bell';
                        if ($n['type'] == 'comment')
                            $icon = 'message-circle';
                        if ($n['type'] == 'new_content')
                            $icon = 'file-plus';
                        if ($n['type'] == 'system')
                            $icon = 'shield-info';
                        ?>
                        <a href="<?php echo htmlspecialchars($n['link']); ?>"
                            class="notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?>">
                            <div class="notif-icon"><i data-lucide="<?php echo $icon; ?>"></i></div>
                            <div class="notif-content">
                                <div class="notif-msg">
                                    <?php echo htmlspecialchars($n['message']); ?>
                                </div>
                                <div class="notif-time">
                                    <?php echo date('d M Y, H:i', strtotime($n['created_at'])); ?> น.
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>