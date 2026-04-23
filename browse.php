<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$cat_id = isset($_GET['cat_id']) ? (int) $_GET['cat_id'] : 0;
$docs = [];
$stats = ['document' => 0, 'wiki' => 0, 'qa' => 0, 'total' => 0];

$cat_name = '';
if ($cat_id > 0) {
    $c_stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $c_stmt->execute([$cat_id]);
    $cat_name = $c_stmt->fetchColumn();
    // If search is empty, we can show category name in search box for clarity
    if (empty($search)) {
        // Option 1: Set search to cat_name to mimic previous behavior but with correct filtering
        // $search = $cat_name; 
    }
}

if ($pdo) {
    // Dynamic counts for tabs based on current filters (except type itself)
    $count_query = "SELECT type, COUNT(*) as count FROM documents d WHERE 1=1";
    $count_params = [];
    if (!empty($search)) {
        $count_query .= " AND (title LIKE ? OR content LIKE ?)";
        $count_params[] = "%$search%";
        $count_params[] = "%$search%";
    }
    if ($cat_id > 0) {
        $count_query .= " AND category_id = ?";
        $count_params[] = $cat_id;
    }
    $count_query .= " GROUP BY type";
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    while ($row = $count_stmt->fetch()) {
        $stats[$row['type']] = $row['count'];
        $stats['total'] += $row['count'];
    }

    $query = "SELECT d.*, c.name as category_name, u.username as author_username, u.full_name as author_name, u.avatar as author_avatar,
              (SELECT COUNT(*) FROM document_likes WHERE document_id = d.id) as like_count,
              (SELECT COUNT(*) FROM comments WHERE document_id = d.id) as comment_count
              FROM documents d 
              LEFT JOIN categories c ON d.category_id = c.id 
              LEFT JOIN users u ON d.user_id = u.id 
              WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (d.title LIKE ? OR d.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($type)) {
        $query .= " AND d.type = ?";
        $params[] = $type;
    }

    if ($cat_id > 0) {
        $query .= " AND d.category_id = ?";
        $params[] = $cat_id;
    }

    $query .= " ORDER BY d.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $docs = $stmt->fetchAll();
}

$type_labels = ['document' => 'เอกสาร', 'wiki' => 'Wiki', 'qa' => 'Q&A'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คลังความรู้ | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
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
                    <h2>สำรวจคลังความรู้</h2>
                    <p>ค้นหาและกรององค์ความรู้ตามความสนใจของคุณ</p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo is_logged_in() ? 'create.php' : 'javascript:void(0)'; ?>" 
                       onclick="<?php echo is_logged_in() ? '' : "return requireLoginPrompt('สร้างเนื้อหาใหม่')"; ?>" 
                       class="btn-primary"><i data-lucide="plus"></i>สร้างเนื้อหา</a>
                </div>
            </header>

            <!-- Refined Search & Filters -->
            <div class="search-container-center" style="margin-bottom: 2rem; max-width: 100%;">
                <form action="browse.php" method="GET" class="search-inner">
                    <i data-lucide="search" style="color: hsl(var(--muted-foreground));"></i>
                    <input type="text" name="search" placeholder="พิมพ์สิ่งที่คุณต้องการค้นหา..."
                        value="<?php echo htmlspecialchars(!empty($search) ? $search : $cat_name); ?>">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                    <?php if ($cat_id > 0): ?>
                        <input type="hidden" name="cat_id" value="<?php echo $cat_id; ?>">
                    <?php endif; ?>
                    <kbd
                        style="font-size: 0.75rem; background: hsl(var(--muted)); padding: 4px 8px; border-radius: 6px; font-weight: 700; color: hsl(var(--muted-foreground));">⌘
                        K</kbd>
                    <button type="submit" class="btn-search-main">สืบค้น</button>
                    <!-- Clear Category filter if present -->
                    <?php if ($cat_id > 0): ?>
                        <a href="browse.php?search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>" 
                           class="btn-search-main" style="background: #f1f5f9; color: #64748b; margin-left: 8px;">ล้างหมวดหมู่</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="filter-tabs">
                <a href="browse.php?search=<?php echo urlencode($search); ?>&cat_id=<?php echo $cat_id; ?>"
                    class="tab-pill <?php echo empty($type) ? 'active' : ''; ?>">ทั้งหมด</a>
                <?php foreach ($type_labels as $val => $label): ?>
                    <a href="browse.php?type=<?php echo $val; ?>&search=<?php echo urlencode($search); ?>&cat_id=<?php echo $cat_id; ?>"
                        class="tab-pill <?php echo $type == $val ? 'active' : ''; ?>">
                        <?php echo $label; ?>
                        <span style="opacity: 0.5; margin-left: 4px; font-size: 0.7rem;"><?php echo $stats[$val]; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Knowledge Grid -->
            <?php if (empty($docs)): ?>
                <div
                    style="text-align: center; padding: 5rem 0; background: white; border-radius: 0.75rem; border: 1px dashed var(--border-color);">
                    <i data-lucide="search-x"
                        style="width: 48px; height: 48px; color: hsl(var(--muted-foreground)); margin-bottom: 1rem;"></i>
                    <h3 style="font-weight: 700;">ไม่พบข้อมูลที่ต้องการ</h3>
                    <p style="color: hsl(var(--muted-foreground));">ลองเปลี่ยนคำค้นหาหรือเลือกประเภทอื่นดูสิ</p>
                </div>
            <?php else: ?>
                <div class="knowledge-grid">
                    <?php foreach ($docs as $doc): ?>
                        <div class="card-knowledge" onclick="location.href='view.php?id=<?php echo $doc['id']; ?>'"
                            style="cursor: pointer;">
                            
                            <?php 
                            $cover_image = null;
                            if (preg_match('/<img[^>]+src="([^">]+)"/i', $doc['content'], $matches)) {
                                $cover_image = $matches[1];
                            } elseif (preg_match('/!\[.*?\]\((.*?)\)/i', $doc['content'], $matches)) {
                                $cover_image = $matches[1];
                            }
                            ?>
                            <?php if ($cover_image): ?>
                                <div style="width: calc(100% + 3rem); height: 160px; margin: -1.5rem -1.5rem 1.5rem -1.5rem; border-top-left-radius: inherit; border-top-right-radius: inherit; overflow: hidden; position: relative;">
                                    <div style="position: absolute; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.03);"></div>
                                    <img src="<?php echo htmlspecialchars($cover_image); ?>" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                                </div>
                            <?php endif; ?>

                            <div class="card-tags">
                                <span class="tag-badge"
                                    style="background: hsl(var(--primary) / 0.1); color: var(--teal-primary);"><?php echo $type_labels[$doc['type']]; ?></span>
                                <span class="tag-badge"><?php echo htmlspecialchars($doc['category_name']); ?></span>
                            </div>
                            <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
                            <p class="card-excerpt">
                                <?php echo mb_strimwidth(strip_tags($doc['content']), 0, 140, "..."); ?>
                            </p>
                            <div class="card-footer">
                                <div class="card-author">
                                    <div class="author-avatar" <?php if(!empty($doc['author_avatar']) && file_exists('uploads/avatars/' . $doc['author_avatar'])) echo 'style="background-image: url(\'uploads/avatars/' . htmlspecialchars($doc['author_avatar']) . '\'); background-size: cover; background-position: center; color: transparent; border: 1px solid var(--border-color);"'; ?>>
                                        <?php if(empty($doc['author_avatar']) || !file_exists('uploads/avatars/' . $doc['author_avatar'])) echo mb_strtoupper(mb_substr($doc['author_username'] ?? 'U', 0, 2, 'UTF-8'), 'UTF-8'); ?>
                                    </div>
                                    <span
                                        class="author-name"><?php echo htmlspecialchars($doc['author_name'] ?? 'UDRU User'); ?></span>
                                </div>
                                <div class="card-metrics">
                                    <div class="metric-item"><i data-lucide="eye"></i> <?php echo $doc['views']; ?></div>
                                    <div class="metric-item"><i data-lucide="thumbs-up"></i> <?php echo $doc['like_count']; ?>
                                    </div>
                                    <div class="metric-item"><i data-lucide="message-square"></i>
                                        <?php echo $doc['comment_count']; ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>