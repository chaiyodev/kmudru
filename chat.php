<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$user_id = is_logged_in() ? $_SESSION['user_id'] : 0;

if (!$user_id) {
    header("Location: login.php");
    exit();
}

$target_user_id = isset($_GET['user']) ? (int) $_GET['user'] : 0;
$users = $pdo->query("SELECT id, username, full_name FROM users WHERE id != $user_id")->fetchAll();

$messages = [];
if ($target_user_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $stmt->execute([$user_id, $target_user_id, $target_user_id, $user_id]);
    $messages = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $target_user_id > 0) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $message = $_POST['message'];
    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $target_user_id, $message]);
    header("Location: chat.php?user=$target_user_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กล่องข้อความ | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .chat-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            height: calc(100vh - 120px);
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .chat-sidebar {
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            background: hsl(var(--muted) / 0.2);
        }

        .chat-list {
            overflow-y: auto;
            flex: 1;
        }

        .chat-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 1.5rem;
            align-items: center;
            cursor: pointer;
            transition: var(--transition-base);
            border-bottom: 1px solid var(--border-color);
            text-decoration: none;
            color: inherit;
        }

        .chat-item:hover,
        .chat-item.active {
            background: white;
        }

        .chat-item.active {
            border-right: 3px solid var(--teal-primary);
        }

        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--teal-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .chat-main {
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .message-area {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: hsl(var(--muted) / 0.1);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1.25rem;
            border-radius: 1.25rem;
            font-size: 0.9375rem;
            line-height: 1.5;
        }

        .message-sent {
            background: var(--teal-primary);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 0.25rem;
        }

        .message-received {
            background: white;
            color: hsl(var(--foreground));
            align-self: flex-start;
            border-bottom-left-radius: 0.25rem;
            border: 1px solid var(--border-color);
        }

        .chat-input-area {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>การสนทนาและการทำงานร่วมกัน</h2>
                    <p>แลกเปลี่ยนความคิดเห็นกับเพื่อนบุคลากร UDRU แบบเรียลไทม์</p>
                </div>
            </header>

            <div class="chat-layout">
                <div class="chat-sidebar">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                        <input type="text" class="form-input" placeholder="ค้นหาเพื่อนร่วมงาน...">
                    </div>
                    <div class="chat-list">
                        <?php foreach ($users as $u): ?>
                            <a href="chat.php?user=<?php echo $u['id']; ?>"
                                class="chat-item <?php echo $target_user_id == $u['id'] ? 'active' : ''; ?>">
                                <div class="chat-avatar">
                                    <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                </div>
                                <div style="flex: 1; overflow: hidden;">
                                    <div style="font-weight: 700;">
                                        <?php echo e($u['full_name']); ?>
                                    </div>
                                    <div
                                        style="font-size: 0.75rem; color: hsl(var(--muted-foreground)); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        คลิกเพื่อเริ่มบทสนทนา</div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="chat-main">
                    <?php if ($target_user_id > 0):
                        $target_user = $pdo->query("SELECT * FROM users WHERE id = $target_user_id")->fetch();
                        ?>
                        <div class="chat-header">
                            <div style="display: flex; gap: 1rem; align-items: center;">
                                <div class="chat-avatar" style="width: 32px; height: 32px; font-size: 0.875rem;">
                                    <?php echo strtoupper(substr($target_user['username'], 0, 1)); ?>
                                </div>
                                <div style="font-weight: 700;">
                                    <?php echo e($target_user['full_name']); ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn-primary"
                                    style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); padding: 0.5rem;"><i
                                        data-lucide="phone" style="width: 18px; height: 18px;"></i></button>
                                <button class="btn-primary"
                                    style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); padding: 0.5rem;"><i
                                        data-lucide="video" style="width: 18px; height: 18px;"></i></button>
                            </div>
                        </div>

                        <div class="message-area" id="message-container">
                            <?php if (empty($messages)): ?>
                                <div style="text-align: center; margin-top: 5rem; color: hsl(var(--muted-foreground));">
                                    <i data-lucide="message-square" style="width: 48px; height: 48px; margin-bottom: 1rem;"></i>
                                    <p>ยังไม่มีข้อความ เริ่มต้นทักทายคุณ
                                        <?php echo e($target_user['full_name']); ?> ได้เลย!
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $m): ?>
                                    <div
                                        class="message-bubble <?php echo $m['sender_id'] == $user_id ? 'message-sent' : 'message-received'; ?>">
                                        <?php echo e($m['message']); ?>
                                        <div style="font-size: 0.625rem; margin-top: 4px; opacity: 0.8; text-align: right;">
                                            <?php echo date('H:i', strtotime($m['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <form action="chat.php?user=<?php echo e($target_user_id); ?>" method="POST"
                            class="chat-input-area">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="button" class="btn-primary"
                                style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); padding: 1rem;"><i
                                    data-lucide="plus"></i></button>
                            <input type="text" name="message" class="form-input" style="flex: 1;"
                                placeholder="พิมพ์ข้อความของคุณที่นี่..." required autocomplete="off">
                            <button type="submit" class="btn-primary"><i data-lucide="send"></i></button>
                        </form>
                    <?php else: ?>
                        <div
                            style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; background: hsl(var(--muted) / 0.05);">
                            <div
                                style="width: 120px; height: 120px; background: hsl(var(--primary) / 0.1); border-radius: 40px; display: flex; align-items: center; justify-content: center; color: var(--teal-primary); margin-bottom: 2rem;">
                                <i data-lucide="messages-square" style="width: 60px; height: 60px;"></i>
                            </div>
                            <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">
                                เลือกผู้สนทนาเพื่อเริ่มต้น</h3>
                            <p style="color: hsl(var(--muted-foreground));">
                                ติดต่อสื่อสารกับผู้เชี่ยวชาญหรือบุคลากรท่านอื่นที่นี่</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        lucide.createIcons();
        const container = document.getElementById('message-container');
        if (container) container.scrollTop = container.scrollHeight;
    </script>
</body>

</html>