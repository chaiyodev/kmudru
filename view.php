<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = is_logged_in() ? $_SESSION['user_id'] : 0;
$doc = null;

if ($id > 0 && $pdo) {
    // Handle POST Actions (CSRF Protected)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            verify_csrf_token($_POST['csrf_token'] ?? '');
            
            // Handle Like Toggle
            if (isset($_POST['action']) && $_POST['action'] === 'like' && $user_id > 0) {
        $check = $pdo->prepare("SELECT id FROM document_likes WHERE document_id = ? AND user_id = ?");
        $check->execute([$id, $user_id]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM document_likes WHERE document_id = ? AND user_id = ?")->execute([$id, $user_id]);
        } else {
            $pdo->prepare("INSERT INTO document_likes (document_id, user_id) VALUES (?, ?)")->execute([$id, $user_id]);
            $pdo->prepare("UPDATE users SET points = points + 5 WHERE id = (SELECT user_id FROM documents WHERE id = ?)")->execute([$id]);
            log_activity('document_like', 'document', "Doc ID: $id");
        }
    }

            // Handle Comment
            if (isset($_POST['comment']) && !empty(trim($_POST['comment'])) && $user_id > 0) {
                $stmt = $pdo->prepare("INSERT INTO comments (document_id, user_id, comment) VALUES (?, ?, ?)");
                $stmt->execute([$id, $user_id, trim($_POST['comment'])]);
                log_activity('comment_add', 'document', "Doc ID: $id");
            }

            // Handle Delete Document (Admin Only)
            if (isset($_POST['action']) && $_POST['action'] === 'delete_doc' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
                log_activity('document_delete', 'document', "Doc ID: $id");
                header("Location: browse.php?msg=deleted");
                exit;
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    // Increase view count
    $pdo->prepare("UPDATE documents SET views = views + 1 WHERE id = ?")->execute([$id]);

    // Fetch Document
    $stmt = $pdo->prepare("SELECT d.*, c.name as category_name, u.full_name as author_name, u.username as author_username, u.points as author_points, u.specialty as author_specialty
                           FROM documents d 
                           LEFT JOIN categories c ON d.category_id = c.id 
                           LEFT JOIN users u ON d.user_id = u.id 
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
    <link rel="stylesheet" href="assets/css/view.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <a href="browse.php" style="color: var(--teal-primary); text-decoration: none; font-weight: 600; font-size: 0.875rem;">คลังความรู้</a>
                        <i data-lucide="chevron-right" style="width: 14px; color: #94a3b8;"></i>
                        <span style="font-size: 0.875rem; color: #64748b;"><?php echo $type_labels[$doc['type']]; ?></span>
                    </div>
                    <h2>รายละเอียดความรู้</h2>
                </div>
                <div class="header-actions">
                    <button onclick="history.back()" class="btn-primary" style="background: white; color: #1e293b; border: 1px solid var(--border-color);">
                        <i data-lucide="arrow-left"></i> กลับ
                    </button>
                    <a href="<?php echo is_logged_in() ? 'create.php' : 'javascript:void(0)'; ?>" 
                       onclick="<?php echo is_logged_in() ? '' : "return requireLoginPrompt('สร้างบทความใหม่')"; ?>" 
                       class="btn-primary"><i data-lucide="plus"></i>สร้างใหม่</a>
                </div>
            </header>

            <div class="view-grid">
                <!-- Main Article -->
                <div>
                    <article class="article-card">
                        <?php 
                        $cover_image = null;
                        $album_images = [];
                        try {
                            $stmt = $pdo->prepare("SELECT file_path FROM document_images WHERE document_id = ? ORDER BY id ASC");
                            $stmt->execute([$doc['id']]);
                            $album_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        } catch (PDOException $e) {}

                        if (!empty($album_images)) {
                            $cover_image = $album_images[0];
                        } else {
                            if (preg_match('/<img[^>]+src="([^">]+)"/i', $doc['content'], $matches)) {
                                $cover_image = $matches[1];
                            } elseif (preg_match('/!\[.*?\]\((.*?)\)/i', $doc['content'], $matches)) {
                                $cover_image = $matches[1];
                            }
                        }
                        ?>
                        <?php if ($cover_image): ?>
                            <div class="article-cover-banner">
                                <div class="article-cover-overlay"></div>
                                <img src="<?php echo htmlspecialchars($cover_image); ?>" loading="lazy">
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
                            <span class="tag-badge" style="background: hsl(var(--primary)/0.1); color: var(--teal-primary);"><?php echo $type_labels[$doc['type']]; ?></span>
                            <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; background: #f8fafc; padding: 4px 12px; border-radius: 20px;">
                                ID: #<?php echo str_pad($doc['id'], 5, '0', STR_PAD_LEFT); ?>
                            </div>
                        </div>

                        <h1 class="article-title">
                            <?php echo htmlspecialchars($doc['title']); ?>
                        </h1>

                        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; color: #64748b; font-size: 0.875rem; padding-bottom: 2rem; margin-bottom: 3rem; border-bottom: 1px solid #f1f5f9;">
                            <div class="view-stat-item"><i data-lucide="user" style="width: 16px;"></i> <?php echo htmlspecialchars($doc['author_name']); ?></div>
                            <div class="view-stat-item"><i data-lucide="calendar" style="width: 16px;"></i> <?php echo date('d M Y', strtotime($doc['created_at'])); ?></div>
                            <div class="view-stat-item"><i data-lucide="folder" style="width: 16px;"></i> <?php echo htmlspecialchars($doc['category_name']); ?></div>
                            <div class="view-stat-item"><i data-lucide="eye" style="width: 16px;"></i> <?php echo number_format($doc['views']); ?> ครั้ง</div>
                        </div>

                        <div class="article-content" id="article-body">
                            <?php 
                            if ($doc['type'] === 'wiki') {
                                // Content will be rendered via JS marked.js
                                echo '<div id="markdown-content" style="display:none;">' . htmlspecialchars($doc['content']) . '</div>';
                                echo '<div id="rendered-content"></div>';
                            } else {
                                echo htmlspecialchars($doc['content']); 
                            }
                            ?>
                        </div>

                        <?php if ($doc['type'] === 'wiki'): ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const raw = document.getElementById('markdown-content').textContent;
                                document.getElementById('rendered-content').innerHTML = marked.parse(raw);
                                lucide.createIcons();
                            });
                        </script>
                        <?php endif; ?>

                        <?php if(!empty($album_images)): ?>
                        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                            <h3 style="font-size: 1.125rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <div style="width: 32px; height: 32px; background: rgba(20, 184, 166, 0.1); color: var(--teal-primary); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="image" style="width: 16px;"></i>
                                </div>
                                อัลบั้มภาพ
                            </h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                                <?php foreach($album_images as $img): ?>
                                    <div onclick="openImageModal('<?php echo htmlspecialchars($img); ?>')" style="display: block; aspect-ratio: 4/3; border-radius: 0.75rem; overflow: hidden; border: 1px solid var(--border-color); cursor: zoom-in; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                        <img src="<?php echo htmlspecialchars($img); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div
                            style="display: flex; gap: 0.75rem; margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                            <?php if (is_logged_in()): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="like">
                                    <button type="submit" class="btn-primary"
                                        style="<?php echo $user_liked ? 'background: hsl(339 90% 50%); color: white;' : 'background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));'; ?>">
                                        <i data-lucide="heart"></i><?php echo $user_liked ? 'คุณชอบแล้ว' : 'ถูกใจ'; ?>
                                        (<?php echo $total_likes; ?>)
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn-primary" onclick="requireLoginPrompt('ถูกใจบทความ')"
                                    style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">
                                    <i data-lucide="heart"></i>ถูกใจ (<?php echo $total_likes; ?>)
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn-primary"
                                style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));"
                                onclick="<?php echo is_logged_in() ? "document.getElementById('comment-box').focus()" : "requireLoginPrompt('แสดงความคิดเห็น')"; ?>">
                                <i data-lucide="message-square"></i>แสดงความเห็น
                            </button>
                            <button class="btn-primary"
                                style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));"><i
                                    data-lucide="share-2"></i>แชร์</button>

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
                        <?php else: ?>
                            <!-- Guest view: No comment box, alert triggered by action buttons -->
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
    <div id="image-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.9); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(8px); opacity: 0; transition: opacity 0.3s ease;">
        <button onclick="closeImageModal()" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; cursor: pointer; padding: 10px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
            <i data-lucide="x" style="width: 32px; height: 32px;"></i>
        </button>
        <img id="modal-image" src="" style="max-width: 90vw; max-height: 90vh; object-fit: contain; border-radius: 8px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
    </div>
    <script>
        lucide.createIcons();
        function openImageModal(src) {
            document.getElementById('modal-image').src = src;
            const modal = document.getElementById('image-modal');
            modal.style.display = 'flex';
            setTimeout(() => modal.style.opacity = '1', 10);
            document.body.style.overflow = 'hidden';
        }
        function closeImageModal() {
            const modal = document.getElementById('image-modal');
            modal.style.opacity = '0';
            setTimeout(() => { modal.style.display = 'none'; document.body.style.overflow = ''; }, 300);
        }
    </script>
</body>

</html>