<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id === 0) {
    header("Location: cop.php");
    exit;
}

// Fetch Community Details
$stmt = $pdo->prepare("SELECT c.*, cat.name as category_name, 
                       (SELECT COUNT(*) FROM community_members WHERE community_id = c.id) as member_count 
                       FROM communities c 
                       LEFT JOIN categories cat ON c.category_id = cat.id 
                       WHERE c.id = ?");
$stmt->execute([$id]);
$cop = $stmt->fetch();

if (!$cop) {
    header("Location: cop.php");
    exit;
}

// Check membership
$is_member = false;
$user_role = '';
if (is_logged_in()) {
    $stmt = $pdo->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $membership = $stmt->fetch();
    if ($membership) {
        $is_member = true;
        $user_role = $membership['role'];
    }
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'discussions';

// Time Ago Helper
function time_ago($timestamp)
{
    $time_ago = strtotime($timestamp);
    $cur_time = time();
    $time_elapsed = $cur_time - $time_ago;
    $seconds = $time_elapsed;
    $minutes = round($time_elapsed / 60);
    $hours = round($time_elapsed / 3600);
    $days = round($time_elapsed / 86400);
    $weeks = round($time_elapsed / 604800);
    $months = round($time_elapsed / 2600640);
    $years = round($time_elapsed / 31207680);

    if ($seconds <= 60)
        return "just now";
    elseif ($minutes <= 60)
        return ($minutes == 1) ? "one minute ago" : "$minutes minutes ago";
    elseif ($hours <= 24)
        return ($hours == 1) ? "an hour ago" : "$hours hours ago";
    elseif ($days <= 7)
        return ($days == 1) ? "yesterday" : "$days days ago";
    elseif ($weeks <= 4.3)
        return ($weeks == 1) ? "a week ago" : "$weeks weeks ago";
    elseif ($months <= 12)
        return ($months == 1) ? "a month ago" : "$months months ago";
    else
        return ($years == 1) ? "one year ago" : "$years years ago";
}

// Handle Post Actions (Join/Leave/Post/Upload/Invite)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $user_id = $_SESSION['user_id'];

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'join') {
            $stmt = $pdo->prepare("INSERT IGNORE INTO community_members (community_id, user_id, role) VALUES (?, ?, 'member')");
            $stmt->execute([$id, $user_id]);
            header("Location: cop_view.php?id=$id&status=joined");
            exit;
        } elseif ($_POST['action'] === 'leave') {
            $stmt = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            header("Location: cop_view.php?id=$id&status=left");
            exit;
        } elseif ($_POST['action'] === 'add_post') {
            $content = trim($_POST['content']);
            if (!empty($content)) {
                $stmt = $pdo->prepare("INSERT INTO community_posts (community_id, user_id, content) VALUES (?, ?, ?)");
                $stmt->execute([$id, $user_id, $content]);
                header("Location: cop_view.php?id=$id&tab=discussions&status=posted");
                exit;
            }
        } elseif ($_POST['action'] === 'upload_resource') {
            $title = trim($_POST['title']);
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/cop_resources/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0777, true);

                $file_name = $_FILES['file']['name'];
                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = uniqid() . '_' . $id . '.' . $file_ext;
                $dest_path = $uploadDir . $new_filename;

                if (move_uploaded_file($_FILES['file']['tmp_name'], $dest_path)) {
                    $stmt = $pdo->prepare("INSERT INTO community_resources (community_id, user_id, title, file_path, file_type) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $user_id, $title, $dest_path, $file_ext]);
                    header("Location: cop_view.php?id=$id&tab=resources&status=uploaded");
                    exit;
                }
            }
        } elseif ($_POST['action'] === 'invite_member') {
            $invite_user_id = (int) $_POST['user_id'];
            if ($user_role === 'leader') { // Only leaders can invite
                $stmt = $pdo->prepare("INSERT IGNORE INTO community_members (community_id, user_id, role) VALUES (?, ?, 'member')");
                $stmt->execute([$id, $invite_user_id]);
                header("Location: cop_view.php?id=$id&tab=members&status=invited");
                exit;
            }
        }
    }
}

// Fetch Tab-specific Data
$posts = [];
$members = [];
$resources = [];

if ($tab === 'discussions') {
    $stmt = $pdo->prepare("SELECT p.*, u.username, u.full_name, m.role as member_role 
                           FROM community_posts p 
                           JOIN users u ON p.user_id = u.id 
                           LEFT JOIN community_members m ON (p.user_id = m.user_id AND p.community_id = m.community_id)
                           WHERE p.community_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$id]);
    $posts = $stmt->fetchAll();
} elseif ($tab === 'members') {
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, m.role, m.joined_at 
                           FROM community_members m 
                           JOIN users u ON m.user_id = u.id 
                           WHERE m.community_id = ? ORDER BY m.joined_at ASC");
    $stmt->execute([$id]);
    $members = $stmt->fetchAll();
} elseif ($tab === 'resources') {
    $stmt = $pdo->prepare("SELECT r.*, u.full_name 
                           FROM community_resources r 
                           JOIN users u ON r.user_id = u.id 
                           WHERE r.community_id = ? ORDER BY r.created_at DESC");
    $stmt->execute([$id]);
    $resources = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo e($cop['name']); ?> | UDRU Wisdom
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .cop-hero {
            position: relative;
            border-radius: 1.5rem;
            overflow: hidden;
            padding: 4rem 3rem;
            color: white;
            margin-bottom: 2rem;
            background:
                <?php echo $cop['color_theme']; ?>
            ;
            <?php if ($cop['cover_image']): ?>
                background-image: linear-gradient(to right, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.2)), url('<?php echo $cop['cover_image']; ?>');
                background-size: cover;
                background-position: center;
            <?php endif; ?>
        }

        .cop-hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
        }

        .glass-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cop-tabs {
            display: flex;
            gap: 1rem;
            background: white;
            padding: 0.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .ai-search-bar {
            background: linear-gradient(90deg, #f8fafc 0%, #ffffff 100%);
            border: 2px solid #e2e8f0;
            border-radius: 1.25rem;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
        }

        .ai-search-bar:focus-within {
            border-color: #6366f1;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.1);
        }

        .tab-btn {
            flex: 1;
            padding: 0.85rem;
            border-radius: 0.75rem;
            border: none;
            background: transparent;
            color: #64748b;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .tab-btn:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .tab-btn.active {
            background: var(--teal-primary);
            color: white;
            box-shadow: 0 10px 15px -3px rgba(20, 184, 166, 0.25);
        }

        .tab-btn.active span {
            background: rgba(255, 255, 255, 0.2) !important;
            color: white !important;
        }

        .form-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }

        .member-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn-action {
            padding: 0.65rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            backdrop-filter: blur(10px);
            transition: 0.2s;
            font-size: 0.875rem;
        }

        .btn-action:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .btn-action.primary {
            background: white;
            color: #0f172a;
            border: none;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .cop-hero-flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 2rem;
        }


        .quick-stat-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            padding: 1.25rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            min-width: 280px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-item .val {
            font-size: 1.25rem;
            font-weight: 800;
            color: white;
        }

        .stat-item .lbl {
            font-size: 0.75rem;
            opacity: 0.8;
            font-weight: 600;
        }

        /* Discussion Thread */
        .thread-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            transition: 0.2s;
            border-left: 4px solid transparent;
        }

        .thread-card:hover {
            border-color: #e2e8f0;
            border-left-color: var(--teal-primary);
            transform: translateX(4px);
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #64748b;
        }

        .post-tool-btn {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            color: #64748b;
            padding: 8px;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
        }

        .post-tool-btn:hover {
            background: #f1f5f9;
            color: var(--teal-primary);
            border-color: #e2e8f0;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-card {
            background: white;
            border-radius: 1.5rem;
            width: 100%;
            max-width: 500px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalEnter 0.3s ease-out;
        }

        @keyframes modalEnter {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <!-- AI Semantic Search Bar (Always Visible) -->
            <div class="ai-search-bar animate-fade-in">
                <i data-lucide="sparkles" style="color: #6366f1;"></i>
                <input type="text" placeholder="ค้นหาด้วย AI (Semantic Search) เช่น 'แนวทาง EdPEx หมวด 4'..."
                    style="border: none; background: transparent; flex: 1; font-size: 1rem; outline: none; font-weight: 500;">
                <span class="ai-badge">AI Powered</span>
                <button class="btn-primary"
                    style="padding: 0.5rem 1.5rem; border-radius: 0.75rem; background: #6366f1;">ค้นหาอัจฉริยะ</button>
            </div>

            <!-- Hero Banner Redesigned -->
            <div class="cop-hero">
                <div class="cop-hero-flex">
                    <div class="cop-hero-content">
                        <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem;">
                            <span class="glass-badge"><i data-lucide="tag" style="width: 12px;"></i>
                                <?php echo e($cop['category_name'] ?: 'General'); ?>
                            </span>
                            <span class="glass-badge"><i
                                    data-lucide="<?php echo $cop['is_public'] ? 'globe' : 'lock'; ?>"
                                    style="width: 12px;"></i>
                                <?php echo $cop['is_public'] ? 'สาธารณะ' : 'ส่วนตัว'; ?>
                            </span>
                        </div>

                        <h1 style="font-size: 3rem; font-weight: 900; margin-bottom: 0.5rem; letter-spacing: -1px;">
                            <?php echo e($cop['name']); ?>
                        </h1>
                        <p style="font-size: 1.1rem; opacity: 0.9; line-height: 1.6; margin-bottom: 1.5rem;">
                            <?php echo e($cop['description']); ?>
                        </p>

                        <div style="display: flex; gap: 2rem; align-items: center; opacity: 0.8; font-size: 0.8125rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="users"
                                    style="width: 16px;"></i>
                                <strong>
                                    <?php echo $cop['member_count']; ?>
                                </strong> สมาชิก
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="calendar"
                                    style="width: 16px;"></i>
                                สร้างเมื่อ
                                <?php echo date('j/n/Y', strtotime($cop['created_at'])); ?>
                            </div>
                        </div>

                        <div class="member-actions">
                            <?php if (!is_logged_in()): ?>
                                <button type="button" class="btn-action primary" onclick="showAuthAlert()">
                                    <i data-lucide="user-plus" style="width: 18px;"></i> เข้าร่วมชุมชน
                                </button>
                                <script>
                                    function showAuthAlert() {
                                        Swal.fire({
                                            title: 'กรุณาเข้าสู่ระบบ',
                                            text: 'คุณต้องเป็นสมาชิกของ UDRU Wisdom ก่อนจึงจะเข้าร่วมกลุ่ม CoP ได้ครับ',
                                            icon: 'info',
                                            showCancelButton: true,
                                            confirmButtonText: 'ไปหน้าสมัครสมาชิก',
                                            cancelButtonText: 'ไว้ทีหลัง',
                                            confirmButtonColor: 'var(--teal-primary)'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                window.location.href = 'register.php';
                                            }
                                        });
                                    }
                                </script>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <?php if ($is_member): ?>
                                        <input type="hidden" name="action" value="leave">
                                        <button type="submit" class="btn-action primary">
                                            <i data-lucide="check" style="width: 18px;"></i> เป็นสมาชิกแล้ว (Leave)
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="join">
                                        <button type="submit" class="btn-action primary">
                                            <i data-lucide="user-plus" style="width: 18px;"></i> เข้าร่วมชุมชน
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Side Widget in Banner (Fills empty space) -->
                    <div class="quick-stat-box animate-fade-in">
                        <div class="stat-item">
                            <span class="val"><?php echo count($posts); ?></span>
                            <span class="lbl">กระทู้ทั้งหมด</span>
                        </div>
                        <div class="stat-item">
                            <span class="val"><?php echo count($resources); ?></span>
                            <span class="lbl">การแชร์ทรัพยากร</span>
                        </div>
                        <div
                            style="grid-column: span 2; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem;">
                            <i data-lucide="shield-check" style="width: 14px; color: #4ade80;"></i> ชุมชนผ่านการรับรอง
                            KM UDRU
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation (Improved spacing and pills) -->
            <nav class="cop-tabs">
                <a href="?id=<?php echo $id; ?>&tab=discussions"
                    class="tab-btn <?php echo $tab == 'discussions' ? 'active' : ''; ?>">
                    <i data-lucide="message-square" style="width: 18px;"></i> การสนทนา
                    <span
                        style="font-size: 0.7rem; background: #f1f5f9; padding: 2px 6px; border-radius: 6px; margin-left: 0.5rem; color: #64748b;"><?php echo count($posts); ?></span>
                </a>
                <a href="?id=<?php echo $id; ?>&tab=resources"
                    class="tab-btn <?php echo $tab == 'resources' ? 'active' : ''; ?>">
                    <i data-lucide="library" style="width: 18px;"></i> ทรัพยากร
                    <span
                        style="font-size: 0.7rem; background: #f1f5f9; padding: 2px 6px; border-radius: 6px; margin-left: 0.5rem; color: #64748b;"><?php echo count($resources); ?></span>
                </a>
                <a href="?id=<?php echo $id; ?>&tab=members"
                    class="tab-btn <?php echo $tab == 'members' ? 'active' : ''; ?>">
                    <i data-lucide="users-2" style="width: 18px;"></i> สมาชิก
                    <span
                        style="font-size: 0.7rem; background: #f1f5f9; padding: 2px 6px; border-radius: 6px; margin-left: 0.5rem; color: #64748b;"><?php echo count($members); ?></span>
                </a>
            </nav>

            <!-- Tab Content -->
            <div class="tab-content">
                <?php if ($tab === 'discussions'): ?>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                        <div class="main-column">
                            <div class="form-card" style="padding: 1.5rem;">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                                    <h3 style="font-size: 1rem; font-weight: 700;">แบ่งปันความรู้</h3>
                                </div>

                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="add_post">
                                    <div
                                        style="display: flex; gap: 1rem; align-items: flex-start; background: white; padding: 1.25rem; border-radius: 1.25rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
                                        <div class="avatar-circle"
                                            style="width: 44px; height: 44px; font-size: 1rem; flex-shrink: 0; background: var(--teal-primary); color: white;">
                                            <?php echo isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'U'; ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <textarea name="content" class="form-input"
                                                style="background: transparent; border: none; padding: 0.5rem 0; box-shadow: none; resize: none; min-height: 50px; font-size: 1.05rem; font-weight: 500;"
                                                placeholder="แชร์ความรู้หรือตั้งคำถามกับเพื่อนร่วมชุมชน..."
                                                required></textarea>

                                            <div id="file-preview-area"
                                                style="display: none; padding: 0.5rem; background: #f8fafc; border-radius: 8px; margin-top: 0.5rem; font-size: 0.8rem; color: #64748b;">
                                                <i data-lucide="paperclip" style="width: 14px;"></i> <span
                                                    id="file-preview-name"></span>
                                            </div>

                                            <div
                                                style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                                                <div style="display: flex; gap: 0.75rem;">
                                                    <input type="file" id="composer-file" name="attachment"
                                                        style="display: none;" onchange="updateFilePreview(this)">
                                                    <input type="file" id="composer-image" name="image" accept="image/*"
                                                        style="display: none;" onchange="updateFilePreview(this)">

                                                    <button class="post-tool-btn" type="button"
                                                        onclick="document.getElementById('composer-file').click()"
                                                        title="แนบไฟล์">
                                                        <i data-lucide="paperclip" style="width: 18px;"></i>
                                                    </button>
                                                    <button class="post-tool-btn" type="button"
                                                        onclick="document.getElementById('composer-image').click()"
                                                        title="รูปภาพ">
                                                        <i data-lucide="image" style="width: 18px;"></i>
                                                    </button>
                                                    <button class="ai-btn-sparkle" type="button"
                                                        onclick="aiAssistant('writing')">
                                                        <i data-lucide="sparkles" style="width: 16px;"></i> ช่วยแต่งโพสต์
                                                    </button>
                                                </div>
                                                <button type="submit" class="btn-primary"
                                                    style="padding: 0.65rem 1.75rem; font-size: 0.9rem; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(20, 184, 166, 0.2);">ส่งโพสต์</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Real Discussion Items -->
                            <div class="thread-list">
                                <?php if (empty($posts)): ?>
                                    <div class="form-card" style="text-align: center; color: #64748b;">ยังไม่มีการสนทนา
                                        เริ่มต้นแบ่งปันคนแรกได้เลย!</div>
                                <?php else: ?>
                                    <?php foreach ($posts as $post): ?>
                                        <div class="thread-card">
                                            <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                                                <div class="avatar-circle">
                                                    <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 700;"><?php echo e($post['full_name']); ?> <span
                                                            style="font-weight: 500; font-size: 0.75rem; color: #94a3b8; margin-left: 0.5rem;"><?php echo time_ago($post['created_at']); ?></span>
                                                    </div>
                                                    <div
                                                        style="font-size: 0.75rem; color: var(--teal-primary); text-transform: capitalize;">
                                                        <?php echo e($post['member_role'] ?: 'Guest'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <p style="line-height: 1.6; color: #475569; margin-bottom: 1rem;">
                                                <?php echo nl2br(e($post['content'])); ?>
                                            </p>
                                            <div style="display: flex; gap: 1.5rem; font-size: 0.8125rem; color: #94a3b8;">
                                                <span onclick="toggleReply(<?php echo $post['id']; ?>)"
                                                    style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;"><i
                                                        data-lucide="message-circle" style="width: 14px;"></i> ตอบกลับ</span>
                                                <span onclick="toggleLike(this)"
                                                    style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer; transition: 0.2s;"><i
                                                        data-lucide="heart" style="width: 14px;"></i> ถูกใจ</span>
                                            </div>
                                            <div id="reply-box-<?php echo $post['id']; ?>"
                                                style="display: none; margin-top: 1rem; padding: 1rem; background: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9;">
                                                <textarea class="form-input" style="min-height: 40px; font-size: 0.875rem;"
                                                    placeholder="เขียนคำตอบ..."></textarea>
                                                <button class="btn-primary"
                                                    style="margin-top: 0.5rem; padding: 4px 12px; font-size: 0.75rem; border-radius: 6px;"
                                                    onclick="toggleReply(<?php echo $post['id']; ?>)">ส่งคำตอบ</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="side-column">
                            <!-- AI Recommendation Card -->
                            <div class="form-card"
                                style="background: linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%); border: 1px solid #ddd6fe;">
                                <h4
                                    style="font-size: 0.875rem; font-weight: 700; color: #5b21b6; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i data-lucide="zap" style="width: 16px;"></i> แนะนำโดย AI
                                </h4>
                                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <div onclick="recommendSummary()"
                                        style="font-size: 0.8125rem; padding: 0.75rem; background: white; border-radius: 10px; border: 1px solid #ede9fe; cursor: pointer; transition: 0.2s;"
                                        onmouseover="this.style.borderColor='#8b5cf6'"
                                        onmouseout="this.style.borderColor='#ede9fe'">
                                        <div style="font-weight: 700; color: #1e1b4b;">สรุปเกณฑ์ EdPEx 2024</div>
                                        <div style="font-size: 0.7rem; color: #6d28d9; margin-top: 2px;">
                                            อิงจากความสนใจของคุณ</div>
                                    </div>
                                    <div onclick="recommendExpert()"
                                        style="font-size: 0.8125rem; padding: 0.75rem; background: white; border-radius: 10px; border: 1px solid #ede9fe; cursor: pointer; transition: 0.2s;"
                                        onmouseover="this.style.borderColor='#8b5cf6'"
                                        onmouseout="this.style.borderColor='#ede9fe'">
                                        <div style="font-weight: 700; color: #1e1b4b;">Expert: ผศ.ดร. มานะ</div>
                                        <div style="font-size: 0.7rem; color: #6d28d9; margin-top: 2px;">
                                            เชี่ยวชาญด้านการประกันคุณภาพ</div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-card" style="padding: 1.5rem;">
                                <h4
                                    style="font-size: 0.875rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    ประกาศสำคัญ</h4>
                                <div
                                    style="padding: 1rem; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 0.75rem; color: #92400e; font-size: 0.875rem;">
                                    <div style="font-weight: 700; margin-bottom: 0.25rem;">ประชุมประจำเดือน</div>
                                    พบกันวันพุธที่พัดหน้า เวลา 13.00 น. ผ่าน MS Teams ครับ
                                </div>
                            </div>

                            <div class="form-card" style="padding: 1.5rem;">
                                <h4
                                    style="font-size: 0.875rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; text-transform: uppercase;">
                                    สมาชิกที่ออนไลน์</h4>
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <?php for ($i = 0; $i < 3; $i++): ?>
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div class="avatar-circle" style="width: 30px; height: 30px; position: relative;">
                                                <div
                                                    style="position: absolute; bottom: 0; right: 0; width: 8px; height: 8px; background: #22c55e; border: 2px solid white; border-radius: 50%;">
                                                </div>
                                                A
                                            </div>
                                            <span style="font-size: 0.875rem; font-weight: 600;">Sarabun Admin</span>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($tab === 'members'): ?>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                        <div class="main-column">
                            <div class="form-card">
                                <h3 style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
                                    <i data-lucide="users-2" style="color: var(--teal-primary);"></i> สมาชิกในชุมชน
                                </h3>
                                <div
                                    style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                                    <?php foreach ($members as $m): ?>
                                        <div
                                            style="display: flex; align-items: center; gap: 1rem; padding: 1.25rem; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 1.25rem; transition: 0.2s;">
                                            <div class="avatar-circle"
                                                style="width: 52px; height: 52px; background: white; border: 2px solid var(--teal-primary); font-size: 1.25rem; color: var(--teal-primary); flex-shrink: 0;">
                                                <?php echo strtoupper(substr($m['username'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div style="flex: 1; overflow: hidden;">
                                                <div
                                                    style="font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <?php echo e($m['full_name']); ?>
                                                </div>
                                                <div
                                                    style="font-size: 0.75rem; color: #64748b; text-transform: capitalize; margin-top: 0.15rem;">
                                                    <span
                                                        style="background: <?php echo $m['role'] == 'leader' ? '#fef3c7' : '#f1f5f9'; ?>; color: <?php echo $m['role'] == 'leader' ? '#92400e' : '#64748b'; ?>; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.65rem;">
                                                        <?php echo e($m['role']); ?>
                                                    </span>
                                                    <span style="margin-left: 0.5rem; font-size: 0.65rem;">• ร่วมเมื่อ
                                                        <?php echo date('M Y', strtotime($m['joined_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="side-column">
                            <?php if ($user_role === 'leader'): ?>
                                <div class="form-card" style="padding: 1.5rem;">
                                    <h4
                                        style="font-size: 0.875rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; text-transform: uppercase;">
                                        เชิญบุคลากรเข้ากลุ่ม</h4>
                                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                                        <input type="text" id="member_search" class="form-input" style="font-size: 0.875rem;"
                                            placeholder="ค้นหาชื่อบุคลากร...">
                                        <div id="search_results"
                                            style="max-height: 200px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem;">
                                            <div style="font-size: 0.75rem; color: #94a3b8; text-align: center; padding: 1rem;">
                                                พิมพ์เพื่อค้นหาสมาชิก...</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="form-card" style="padding: 1.5rem;">
                                <h4
                                    style="font-size: 0.875rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; text-transform: uppercase;">
                                    สมาชิกที่ออนไลน์</h4>
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div class="avatar-circle"
                                            style="width: 30px; height: 30px; position: relative; background: #e2e8f0;">
                                            <div
                                                style="position: absolute; bottom: 0; right: 0; width: 8px; height: 8px; background: #22c55e; border: 2px solid white; border-radius: 50%;">
                                            </div>
                                            A
                                        </div>
                                        <span style="font-size: 0.875rem; font-weight: 600;">Sarabun Admin</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($tab === 'resources'): ?>
                    <div class="form-card">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                            <h3 style="display: flex; align-items: center; gap: 0.75rem;">
                                <i data-lucide="library" style="color: var(--teal-primary);"></i> คลังความรู้และทรัพยากร
                            </h3>
                            <button onclick="toggleModal('uploadResourceModal', true)" class="btn-primary"
                                style="padding: 0.65rem 1.25rem; font-size: 0.9rem;"><i data-lucide="upload-cloud"
                                    style="width: 18px;"></i> อัปโหลดไฟล์</button>
                        </div>

                        <?php if (empty($resources)): ?>
                            <div
                                style="text-align: center; padding: 5rem 2rem; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 1.5rem; color: #64748b;">
                                <i data-lucide="folder-open"
                                    style="width: 64px; height: 64px; margin-bottom: 1.5rem; opacity: 0.2;"></i>
                                <h4 style="margin-bottom: 0.5rem; color: #0f172a;">ยังไม่มีทรัพยากรในขณะนี้</h4>
                                <p style="font-size: 0.875rem;">
                                    เริ่มต้นแชร์ไฟล์ความรู้แรกของคุณเพื่อช่วยส่งเสริมการเรียนรู้ในชุมชน</p>
                            </div>
                        <?php else: ?>
                            <div style="display: grid; gap: 1rem;">
                                <?php foreach ($resources as $res): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1.25rem; background: white; border: 1px solid #f1f5f9; border-radius: 1rem; transition: 0.2s;"
                                        class="resource-item">
                                        <div style="display: flex; align-items: center; gap: 1.25rem;">
                                            <div
                                                style="width: 48px; height: 48px; border-radius: 12px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: var(--teal-primary);">
                                                <i data-lucide="file-text" style="width: 24px;"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: #0f172a;"><?php echo e($res['title']); ?></div>
                                                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                                                    <span style="font-weight: 600; color: #64748b;">โดย
                                                        <?php echo e($res['full_name']); ?></span>
                                                    <span style="margin-left: 0.5rem;">• เมื่อ
                                                        <?php echo date('d M Y', strtotime($res['created_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <button class="ai-btn-sparkle"
                                                onclick="aiAssistant('summarize', '<?php echo $res['id']; ?>')"
                                                style="padding: 6px 10px;">
                                                <i data-lucide="wand-2" style="width: 14px;"></i> สรุป AI
                                            </button>
                                            <a href="<?php echo e($res['file_path']); ?>" class="post-tool-btn"
                                                style="padding: 10px 15px; text-decoration: none;" download>
                                                <i data-lucide="download" style="width: 18px;"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="form-card" style="text-align: center; padding: 5rem 2rem;">
                        <i data-lucide="alert-circle"
                            style="width: 48px; height: 48px; color: #94a3b8; margin-bottom: 1.5rem;"></i>
                        <h3>ไม่พบหน้าที่ต้องการ</h3>
                        <p style="color: #64748b;">กรุณาเลือกเมนูจากแถบด้านบนครับ</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Upload Modal -->
    <div id="uploadResourceModal" class="modal-overlay">
        <div class="modal-card">
            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="upload-cloud" style="color: var(--teal-primary);"></i> แชร์ทรัพยากรใหม่
            </h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="upload_resource">

                <div class="form-group margin-bottom-1">
                    <label class="form-label">ชื่อทรัพยากร</label>
                    <input type="text" name="title" class="form-input"
                        placeholder="เช่น คู่มือการใช้งาน, แบบฟอร์มขออนุมัติ" required>
                </div>

                <div class="form-group margin-bottom-2">
                    <label class="form-label">เลือกไฟล์</label>
                    <input type="file" name="file" class="form-input" style="padding: 0.5rem;" required>
                    <p style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.5rem;">รองรับ PDF, Docx, Image
                        และไฟล์สำนักงานทั่วไป</p>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn-primary" style="flex: 1;">ยืนยันการแชร์</button>
                    <button type="button" onclick="toggleModal('uploadResourceModal', false)" class="btn-primary"
                        style="background: white; border: 1px solid #e2e8f0; color: #64748b; width: auto;">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        lucide.createIcons();

        function toggleModal(id, show) {
            const modal = document.getElementById(id);
            if (modal) modal.style.display = show ? 'flex' : 'none';
        }

        // Search logic for invitations (Simplified mockup)
        const searchInput = document.getElementById('member_search');
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                const results = document.getElementById('search_results');
                if (e.target.value.length > 2) {
                    results.innerHTML = `
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: white; border: 1px solid #f1f5f9; border-radius: 0.75rem;">
                            <span style="font-size: 0.875rem; font-weight: 600;">ผลการค้นหา...</span>
                            <form method="POST">
                                <input type="hidden" name="action" value="invite_member">
                                <input type="hidden" name="user_id" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button class="btn-sm" style="background: var(--teal-primary); color: white; border: none; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem;">เชิญ</button>
                            </form>
                        </div>
                    `;
                } else {
                    results.innerHTML = '<div style="font-size: 0.75rem; color: #94a3b8; text-align: center; padding: 1rem;">พิมพ์เพื่อค้นหาสมาชิก...</div>';
                }
            });
        }

        function handleMembership(type) {
            Swal.fire({
                title: type === 'join' ? 'ต้องการเข้าร่วมชุมชน?' : 'ต้องการออกจากชุมชน?',
                text: 'คุณสามารถจัดการสถานะสมาชิกได้ตลอดเวลา',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--teal-primary)',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('สำเร็จ!', 'อัปเดตสถานะสมาชิกเรียบร้อยแล้ว', 'success');
                }
            });
        }

        // New Interactive Functions
        function updateFilePreview(input) {
            const area = document.getElementById('file-preview-area');
            const name = document.getElementById('file-preview-name');
            if (input.files && input.files[0]) {
                name.textContent = input.files[0].name;
                area.style.display = 'block';
            } else {
                area.style.display = 'none';
            }
        }

        function toggleLike(el) {
            const icon = el.querySelector('i');
            if (el.style.color === 'rgb(244, 63, 94)') { // #f43f5e
                el.style.color = '#94a3b8';
                icon.style.fill = 'none';
            } else {
                el.style.color = '#f43f5e';
                icon.style.fill = '#f43f5e';
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'ขอบคุณที่ถูกใจ!',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }

        function toggleReply(id) {
            const box = document.getElementById('reply-box-' + id);
            box.style.display = box.style.display === 'none' ? 'block' : 'none';
        }

        function recommendSummary() {
            Swal.fire({
                title: 'บทสรุปเกณฑ์ EdPEx 2024 (โดย AI)',
                html: `<div style="text-align: left; font-size: 0.9rem;">
                    <p><b>EdPEx 2024</b> มีการปรับปรุงที่น่าสนใจในหมวดที่ 2 เรื่องการวางแผนกลยุทธ์ และหมวดที่ 6 เรื่องการปฏิบัติการ เพื่อให้สอดรับกับความเปลี่ยนแปลงที่รวดเร็ว (Agility)...</p>
                    <ul style="margin-top: 1rem;">
                        <li>เน้นการบูรณาการระบบนวัตกรรม</li>
                        <li>เพิ่มความสำคัญของ Digital Transformation</li>
                    </ul>
                </div>`,
                icon: 'info',
                confirmButtonText: 'รับทราบความรู้'
            });
        }

        function recommendExpert() {
            Swal.fire({
                title: 'ติดต่อผู้เชี่ยวชาญ',
                text: 'คุณต้องการส่งข้อความสอบถาม ผศ.ดร. มานะ เกี่ยวกับงานประกันคุณภาพหรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ส่งข้อความ',
                cancelButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('สำเร็จ!', 'ระบบส่งข้อความให้ผู้เชี่ยวชาญเรียบร้อยแล้ว', 'success');
                }
            });
        }
    </script>
</body>

</html>