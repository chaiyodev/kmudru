<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = is_logged_in() ? $_SESSION['user_id'] : 0;
$doc = null;

if ($id > 0 && $pdo) {
    // Security: Verify CSRF token for all POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_token($_POST['csrf_token'] ?? '');
    }

    // Handle Like Toggle
    if (isset($_POST['action']) && $_POST['action'] === 'like' && $user_id > 0) {
        $check = $pdo->prepare("SELECT id FROM document_likes WHERE document_id = ? AND user_id = ?");
        $check->execute([$id, $user_id]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM document_likes WHERE document_id = ? AND user_id = ?")->execute([$id, $user_id]);
        } else {
            $pdo->prepare("INSERT INTO document_likes (document_id, user_id) VALUES (?, ?)")->execute([$id, $user_id]);
            $pdo->prepare("UPDATE users SET points = points + 5 WHERE id = (SELECT user_id FROM documents WHERE id = ?)")->execute([$id]);
        }
    }

    // Handle Comment
    if (isset($_POST['comment']) && !empty(trim($_POST['comment'])) && $user_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO comments (document_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$id, $user_id, trim($_POST['comment'])]);
    }

    // Handle Delete Document (Admin Only)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_doc' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
        header("Location: browse.php?msg=deleted");
        exit;
    }

    // Increase view count
    $pdo->prepare("UPDATE documents SET views = views + 1 WHERE id = ?")->execute([$id]);

    // Fetch Document
    $stmt = $pdo->prepare("SELECT d.*, c.name as category_name, 
                                  u.full_name as author_name, u.username as author_username, u.points as author_points, u.specialty as author_specialty,
                                  le.full_name as last_editor_name
                           FROM documents d 
                           LEFT JOIN categories c ON d.category_id = c.id 
                           LEFT JOIN users u ON d.user_id = u.id 
                           LEFT JOIN users le ON d.last_editor_id = le.id
                           WHERE d.id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    $like_count = $pdo->prepare("SELECT COUNT(*) FROM document_likes WHERE document_id = ?");
    $like_count->execute([$id]);
    $total_likes = $like_count->fetchColumn();

    $user_liked = false;
    if ($user_id > 0) {
        $liked_stmt = $pdo->prepare("SELECT id FROM document_likes WHERE document_id = ? AND user_id = ?");
        $liked_stmt->execute([$id, $user_id]);
        $user_liked = (bool) $liked_stmt->fetch();
    }

    $comments_stmt = $pdo->prepare("SELECT c.*, u.full_name, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.document_id = ? ORDER BY c.created_at DESC");
    $comments_stmt->execute([$id]);
    $comments = $comments_stmt->fetchAll();

    // Fetch Attachments
    $attachments_stmt = $pdo->prepare("SELECT * FROM attachments WHERE document_id = ?");
    $attachments_stmt->execute([$id]);
    $attachments = $attachments_stmt->fetchAll();
}

if (!$doc) {
    header("Location: browse.php");
    exit;
}

$type_labels = ['document' => 'เอกสาร', 'wiki' => 'Wiki', 'qa' => 'Q&A'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($doc['title']); ?> | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .article-content {
            line-height: 1.9;
            font-size: 1.0625rem;
            white-space: pre-wrap;
            color: hsl(var(--foreground));
        }

        .comment-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .author-card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid var(--border-color);
            padding: 2rem;
            text-align: center;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: hsl(var(--muted)/0.3);
            border: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            margin-bottom: 0.75rem;
            transition: var(--transition-base);
        }

        .attachment-item:hover {
            background: hsl(var(--primary)/0.05);
            border-color: var(--teal-primary);
        }

        .file-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--teal-primary);
            flex-shrink: 0;
        }
    </style>
    <script>
        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const MAP = {
                'pdf': 'file-text',
                'doc': 'file-code',
                'docx': 'file-code',
                'xls': 'file-spreadsheet',
                'xlsx': 'file-spreadsheet',
                'ppt': 'presentation',
                'pptx': 'presentation',
                'jpg': 'image',
                'jpeg': 'image',
                'png': 'image',
                'zip': 'archive'
            };
            return MAP[ext] || 'file';
        }
    </script>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>รายละเอียดความรู้</h2>
                    <p><?php echo $type_labels[$doc['type']]; ?> •
                        <?php echo htmlspecialchars($doc['category_name']); ?>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="browse.php" class="btn-primary"
                        style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));"><i
                            data-lucide="arrow-left"></i>กลับ</a>
                    <a href="create.php" class="btn-primary"><i data-lucide="plus"></i>สร้างใหม่</a>
                </div>
            </header>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <!-- Main Article -->
                <div>
                    <article
                        style="background: white; border-radius: 1.5rem; border: 1px solid var(--border-color); padding: 3rem; margin-bottom: 2rem;">
                        <span class="tag-badge"
                            style="background: hsl(var(--primary)/0.1); color: var(--teal-primary); margin-bottom: 1.5rem; display: inline-block;"><?php echo $type_labels[$doc['type']]; ?></span>

                        <h1 style="font-size: 2rem; font-weight: 800; line-height: 1.3; margin-bottom: 1.5rem;">
                            <?php echo htmlspecialchars($doc['title']); ?>
                        </h1>

                        <div
                            style="display: flex; gap: 1.5rem; color: hsl(var(--muted-foreground)); font-size: 0.875rem; padding-bottom: 2rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color);">
                            <span><i data-lucide="user"
                                    style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i><?php echo htmlspecialchars($doc['author_name']); ?></span>
                            <span><i data-lucide="calendar"
                                    style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i><?php echo date('d M Y', strtotime($doc['created_at'])); ?></span>
                            <span><i data-lucide="eye"
                                    style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i><?php echo $doc['views']; ?></span>
                            <span><i data-lucide="heart"
                                    style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i><?php echo $total_likes; ?></span>
                            <?php if (!empty($doc['last_editor_name'])): ?>
                                <span style="color: var(--teal-primary); font-weight: 600;">
                                    <i data-lucide="edit-3"
                                        style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i>
                                    แก้ไขล่าสุดโดย <?php echo htmlspecialchars($doc['last_editor_name']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($doc['type'] === 'wiki'): ?>
                                <a href="wiki_history.php?id=<?php echo $id; ?>"
                                    style="color: hsl(var(--muted-foreground)); text-decoration: none; display: flex; align-items: center; gap: 4px; border: 1px solid var(--border-color); padding: 2px 8px; border-radius: 4px; background: white;">
                                    <i data-lucide="history" style="width: 14px; height: 14px;"></i>
                                    ประวัติการแก้ไข
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="article-content">
                            <?php echo ($doc['type'] === 'wiki') ? nl2br(htmlspecialchars($doc['content'])) : nl2br(htmlspecialchars($doc['content'])); ?>
                        </div>

                        <?php if (!empty($doc['doc_references'])): ?>
                            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                                <h3
                                    style="font-size: 1.125rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                                    <i data-lucide="book" style="color: var(--teal-primary);"></i> แหล่งอ้างอิง (References)
                                </h3>
                                <div
                                    style="font-size: 0.9375rem; color: #475569; padding: 1.5rem; background: #f8fafc; border-radius: 1rem; line-height: 1.7;">
                                    <?php echo nl2br(htmlspecialchars($doc['doc_references'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($attachments)): ?>
                            <div style="margin-top: 3rem;">
                                <h3
                                    style="font-size: 1.125rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                                    <i data-lucide="paperclip" style="color: var(--teal-primary);"></i> เอกสารแนบ
                                    (<?php echo count($attachments); ?>)
                                </h3>
                                <div class="attachments-list">
                                    <?php foreach ($attachments as $file): ?>
                                        <?php
                                        $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                        $icon = 'file';
                                        if (in_array($ext, ['pdf']))
                                            $icon = 'file-text';
                                        if (in_array($ext, ['doc', 'docx']))
                                            $icon = 'file-box';
                                        if (in_array($ext, ['xls', 'xlsx']))
                                            $icon = 'table-2';
                                        if (in_array($ext, ['ppt', 'pptx']))
                                            $icon = 'presentation';
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']))
                                            $icon = 'image';
                                        ?>
                                        <div class="attachment-item">
                                            <div style="display: flex; align-items: center; gap: 1rem; overflow: hidden;">
                                                <div class="file-icon">
                                                    <i data-lucide="<?php echo $icon; ?>"></i>
                                                </div>
                                                <div style="overflow: hidden;">
                                                    <div style="font-weight: 700; font-size: 0.9375rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                                        title="<?php echo htmlspecialchars($file['file_name']); ?>">
                                                        <?php echo htmlspecialchars($file['file_name']); ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">
                                                        <?php echo strtoupper($ext); ?> •
                                                        <?php echo round($file['file_size'] / 1024, 1); ?> KB
                                                    </div>
                                                </div>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>"
                                                download="<?php echo htmlspecialchars($file['file_name']); ?>"
                                                class="btn-primary"
                                                style="padding: 0.5rem 1rem; font-size: 0.8125rem; border-radius: 0.75rem;">
                                                <i data-lucide="download" style="width: 14px; height: 14px;"></i> ดาวน์โหลด
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div
                            style="display: flex; gap: 0.75rem; margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="like">
                                <button type="submit" class="btn-primary"
                                    style="<?php echo $user_liked ? 'background: hsl(339 90% 50%); color: white;' : 'background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));'; ?>">
                                    <i data-lucide="heart"></i><?php echo $user_liked ? 'คุณชอบแล้ว' : 'ถูกใจ'; ?>
                                    (<?php echo $total_likes; ?>)
                                </button>
                            </form>
                            <button class="btn-primary"
                                style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));"
                                onclick="document.getElementById('comment-box').focus()"><i
                                    data-lucide="message-square"></i>แสดงความเห็น</button>
                            <button class="btn-primary"
                                style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));"><i
                                    data-lucide="share-2"></i>แชร์</button>

                            <?php
                            $can_edit = is_logged_in() && ($user_id == $doc['user_id'] || $_SESSION['role'] === 'admin');
                            if ($doc['type'] === 'wiki' && is_logged_in() && ($_SESSION['role'] === 'contributor' || $_SESSION['role'] === 'admin')) {
                                $can_edit = true;
                            }
                            ?>
                            <?php if ($can_edit): ?>
                                <a href="edit.php?id=<?php echo $doc['id']; ?>" class="btn-primary"
                                    style="background: white; color: hsl(var(--foreground)); border: 1px solid var(--border-color);">
                                    <i data-lucide="edit"></i>แก้ไขบทความ
                                </a>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <form method="POST"
                                    onsubmit="return confirm('คุณต้องการลบบทความนี้ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้');"
                                    style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="delete_doc">
                                    <button type="submit" class="btn-primary"
                                        style="background: hsl(0 84% 60% / 0.1); color: hsl(0 84% 60%); border: 1px solid hsl(0 84% 60% / 0.2);">
                                        <i data-lucide="trash-2"></i>ลบบทความ
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>

                    <!-- Comments Section -->
                    <section
                        style="background: white; border-radius: 1.5rem; border: 1px solid var(--border-color); padding: 2rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 800; margin-bottom: 2rem;"><i
                                data-lucide="message-circle"
                                style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px; color: var(--teal-primary);"></i>ความคิดเห็น
                            (<?php echo count($comments); ?>)</h3>

                        <?php if (is_logged_in()): ?>
                            <form method="POST" style="margin-bottom: 2rem;">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <textarea id="comment-box" name="comment" placeholder="แบ่งปันมุมมองของคุณ..."
                                    style="width: 100%; padding: 1rem; border: 1px solid var(--border-color); border-radius: 0.75rem; min-height: 100px; font-family: inherit; resize: vertical; outline: none; transition: var(--transition-base);"
                                    onfocus="this.style.borderColor='var(--teal-primary)';this.style.boxShadow='0 0 0 3px hsl(var(--primary)/0.1)'"
                                    onblur="this.style.borderColor='var(--border-color)';this.style.boxShadow='none'"></textarea>
                                <div style="text-align: right; margin-top: 1rem;"><button type="submit"
                                        class="btn-primary">ส่งความคิดเห็น</button></div>
                            </form>
                        <?php endif; ?>

                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($comments as $c): ?>
                                <div class="comment-card">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div
                                                style="width: 32px; height: 32px; border-radius: 10px; background: var(--teal-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem;">
                                                <?php echo strtoupper(substr($c['username'], 0, 1)); ?>
                                            </div>
                                            <span
                                                style="font-weight: 700;"><?php echo htmlspecialchars($c['full_name']); ?></span>
                                        </div>
                                        <span
                                            style="font-size: 0.75rem; color: hsl(var(--muted-foreground));"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></span>
                                    </div>
                                    <p style="color: hsl(var(--foreground)); line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($c['comment'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($comments)): ?>
                                <p style="text-align: center; color: hsl(var(--muted-foreground)); padding: 2rem;">
                                    ยังไม่มีความคิดเห็น เป็นคนแรกที่แสดงความคิดเห็นเกี่ยวกับเนื้อหานี้!</p>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <!-- Author Sidebar -->
                <aside>
                    <div class="author-card">
                        <div
                            style="width: 80px; height: 80px; border-radius: 24px; background: var(--teal-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; margin: 0 auto 1.5rem; box-shadow: 0 0 0 6px hsl(var(--primary)/0.1);">
                            <?php echo strtoupper(substr($doc['author_username'] ?? 'KM', 0, 2)); ?>
                        </div>
                        <h4 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.25rem;">
                            <?php echo htmlspecialchars($doc['author_name']); ?>
                        </h4>
                        <p style="color: hsl(var(--muted-foreground)); font-size: 0.875rem; margin-bottom: 1.5rem;">
                            <?php echo htmlspecialchars($doc['author_specialty'] ?? 'ผู้เชี่ยวชาญประจำระบบ'); ?>
                        </p>

                        <div
                            style="display: flex; justify-content: space-around; background: hsl(var(--muted)/0.3); padding: 1rem; border-radius: 1rem;">
                            <div>
                                <div style="font-weight: 800; color: var(--teal-primary); font-size: 1.25rem;">
                                    <?php echo $doc['author_points'] ?? 0; ?>
                                </div>
                                <div
                                    style="font-size: 0.6875rem; color: hsl(var(--muted-foreground)); text-transform: uppercase; font-weight: 600;">
                                    XP</div>
                            </div>
                            <div style="width: 1px; background: var(--border-color);"></div>
                            <div>
                                <div style="font-weight: 800; color: hsl(var(--foreground)); font-size: 1.25rem;">Expert
                                </div>
                                <div
                                    style="font-size: 0.6875rem; color: hsl(var(--muted-foreground)); text-transform: uppercase; font-weight: 600;">
                                    ระดับ</div>
                            </div>
                        </div>
                        <a href="experts.php" class="btn-primary"
                            style="width: 100%; justify-content: center; margin-top: 1.5rem; background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">ดูโปรไฟล์ทั้งหมด</a>
                    </div>
                </aside>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>