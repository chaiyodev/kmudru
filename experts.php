<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pdo = get_pdo();
$experts = [];
$search = isset($_GET['q']) ? $_GET['q'] : '';
$specialty = isset($_GET['s']) ? $_GET['s'] : '';

if ($pdo) {
    $sql = "SELECT u.*, (SELECT COUNT(*) FROM documents WHERE user_id = u.id) as doc_count 
            FROM users u 
            WHERE u.role != 'reader'";

    $params = [];
    if ($search) {
        $sql .= " AND (u.full_name LIKE ? OR u.specialty LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($specialty) {
        $sql .= " AND u.specialty = ?";
        $params[] = $specialty;
    }

    $sql .= " ORDER BY u.points DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $experts = $stmt->fetchAll();

    $specialties = $pdo->query("SELECT DISTINCT specialty FROM users WHERE specialty IS NOT NULL AND specialty != ''")->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายชื่อผู้เชี่ยวชาญ | UDRU Wisdom</title>
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
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>ทำความรู้จักผู้เชี่ยวชาญ UDRU</h2>
                    <p>รวบรวมบุคลากรที่มีความรู้ความสามารถโดดเด่นในแต่ละสาขา</p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo is_logged_in() ? 'experts_create.php' : 'javascript:void(0)'; ?>" 
                       onclick="<?php echo is_logged_in() ? '' : "return requireLoginPrompt('ลงทะเบียนเป็นผู้เชี่ยวชาญ')"; ?>" 
                       class="btn-primary">
                        <i data-lucide="user-plus"></i> ลงทะเบียนผู้เชี่ยวชาญ
                    </a>
                </div>
            </header>

            <!-- Search & Filters -->
            <div class="search-container-expert"
                style="background: white; padding: 1.5rem; border-radius: 1rem; border: 1px solid var(--border-color); margin-bottom: 2rem;">

                <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 300px; position: relative;">
                        <i data-lucide="search"
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px;"></i>
                        <input type="text" name="q" class="form-input" placeholder="ค้นหาชื่อผู้เชี่ยวชาญ หรือทักษะ..."
                            style="padding-left: 3rem;" value="<?php echo e($search); ?>">
                    </div>
                    <select name="s" class="form-input" style="width: 200px;">
                        <option value="">ทุกความเชี่ยวชาญ</option>
                        <?php foreach ($specialties as $s): ?>
                            <option value="<?php echo e($s); ?>" <?php echo $specialty == $s ? 'selected' : ''; ?>>
                                <?php echo e($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary" style="padding: 0.5rem 2rem;">ค้นหา</button>
                    <?php if ($search || $specialty): ?>
                        <a href="experts.php" class="btn-primary"
                            style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">ล้างค่า</a>
                    <?php endif; ?>
                </form>
            </div>

            <style>
                .expert-card {
                    text-decoration: none;
                    color: inherit;
                    flex-direction: row;
                    align-items: center;
                    gap: 2rem;
                    transition: var(--transition-base);
                    cursor: pointer;
                    display: flex;
                    padding: 1.5rem;
                    background: white;
                    border-radius: 1rem;
                    border: 1px solid var(--border-color);
                }
                @media (max-width: 640px) {
                    .expert-card {
                        flex-direction: column;
                        text-align: center;
                        gap: 1.25rem;
                    }
                    .expert-info {
                        text-align: center;
                    }
                    .expert-stats {
                        justify-content: center;
                    }
                    .search-container-expert form > * {
                        width: 100% !important;
                        flex: none !important;
                    }
                }
            </style>

            <div class="knowledge-grid" style="grid-template-columns: 1fr;">
                <?php if (empty($experts)): ?>
                    <div
                        style="padding: 4rem; text-align: center; background: white; border-radius: 1rem; border: 1px dashed var(--border-color);">
                        <i data-lucide="user-plus"
                            style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>ไม่พบผู้เชี่ยวชาญที่ตรงกับเงื่อนไข</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($experts as $expert): ?>
                    <a href="expert_view.php?id=<?php echo $expert['id']; ?>" class="expert-card">
                        <div class="profile-avatar-large"

                            style="width: 80px; height: 80px; border-radius: 20px; background: var(--teal-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; flex-shrink: 0; <?php if(!empty($expert['avatar']) && file_exists('uploads/avatars/'.$expert['avatar'])) echo "background-image: url('uploads/avatars/".htmlspecialchars($expert['avatar'])."'); background-size: cover; background-position: center; color: transparent;"; ?> overflow: hidden;">
                            <?php if(empty($expert['avatar']) || !file_exists('uploads/avatars/'.$expert['avatar'])) echo mb_strtoupper(mb_substr($expert['username'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                        </div>
                        <div class="expert-info" style="flex: 1;">
                            <h3 style="margin-bottom: 0.25rem; font-weight: 700;">
                                <?php echo htmlspecialchars($expert['full_name']); ?>
                            </h3>
                            <p
                                style="font-size: 0.875rem; color: var(--teal-primary); font-weight: 600; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($expert['specialty'] ?? 'ผู้เชี่ยวชาญทั่วไป'); ?>
                            </p>
                            <div class="expert-stats"
                                style="display: flex; gap: 1.5rem; color: hsl(var(--muted-foreground)); font-size: 0.8125rem;">
                                <span style="display: flex; align-items: center; gap: 4px;"><i data-lucide="award"
                                         style="width: 14px;"></i>
                                     <?php echo $expert['points']; ?> แต้มปัญญา</span>
                                <span style="display: flex; align-items: center; gap: 4px;"><i data-lucide="file-text"
                                         style="width: 14px;"></i>
                                     <?php echo $expert['doc_count']; ?> บทความ</span>
                            </div>
                        </div>

                        <div style="font-size: 1.5rem; font-weight: 800; color: hsl(var(--muted)); opacity: 0.5;">
                            #<?php echo array_search($expert, $experts) + 1; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>