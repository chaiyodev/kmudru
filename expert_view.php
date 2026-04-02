<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/logger.php';

$pdo = get_pdo();
$expert_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$expert_id) {
    header("Location: experts.php");
    exit;
}

// Fetch Expert Data
$stmt = $pdo->prepare("SELECT u.*, 
    (SELECT COUNT(*) FROM documents WHERE user_id = u.id AND type='document') as doc_count,
    (SELECT COUNT(*) FROM documents WHERE user_id = u.id AND type='wiki') as wiki_count
    FROM users u WHERE u.id = ?");
$stmt->execute([$expert_id]);
$expert = $stmt->fetch();

if (!$expert) {
    header("Location: experts.php");
    exit;
}

$is_owner = (is_logged_in() && $_SESSION['user_id'] == $expert_id);
$is_admin = (is_logged_in() && $_SESSION['role'] === 'admin');
$can_edit = $is_owner || $is_admin;

$message = '';
$error = '';

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $full_name = trim($_POST['full_name']);
    $specialty = trim($_POST['specialty']);
    $bio = trim($_POST['bio']);
    $portfolio = trim($_POST['portfolio']);
    $phone = trim($_POST['phone']);

    // Handle Image Upload
    $avatar = $expert['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);

        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $new_name = uniqid('profile_') . '.' . $ext;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_name)) {
            $avatar = $new_name;
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, specialty = ?, bio = ?, portfolio = ?, phone = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$full_name, $specialty, $bio, $portfolio, $phone, $avatar, $expert_id]);
        $message = "อัปเดตข้อมูลผู้เชี่ยวชาญเรียบร้อยแล้ว!";
        log_activity("Update Expert Profile", "user", "User ID: $expert_id");

        // Refresh local data
        $expert['full_name'] = $full_name;
        $expert['specialty'] = $specialty;
        $expert['bio'] = $bio;
        $expert['portfolio'] = $portfolio;
        $expert['phone'] = $phone;
        $expert['avatar'] = $avatar;
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// Fetch Expert's Latest Documents
$docs = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? AND status = 'published' ORDER BY created_at DESC LIMIT 5");
$docs->execute([$expert_id]);
$latest_docs = $docs->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($expert['full_name']); ?> | UDRU Wisdom
    </title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/expert.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <a href="experts.php"
                            style="color: var(--teal-primary); text-decoration: none; font-weight: 600; font-size: 0.875rem;">ผู้เชี่ยวชาญ</a>
                        <i data-lucide="chevron-right" style="width: 14px; color: #94a3b8;"></i>
                        <span style="font-size: 0.875rem; color: #64748b;">ข้อมูลโปรไฟล์</span>
                    </div>
                    <h2>รายละเอียดผู้เชี่ยวชาญ</h2>
                </div>
                <?php if ($can_edit): ?>
                    <button onclick="document.getElementById('editModal').style.display='flex'" class="btn-primary">
                        <i data-lucide="edit-3"></i> แก้ไขข้อมูล
                    </button>
                <?php endif; ?>
            </header>

            <?php if ($message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'อัปเดตสำเร็จ!',
                            text: '<?php echo addslashes($message); ?>',
                            confirmButtonColor: 'var(--teal-primary)'
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: '<?php echo addslashes($error); ?>',
                            confirmButtonColor: 'var(--teal-primary)'
                        });
                    });
                </script>
            <?php endif; ?>

            <div class="profile-header-premium">
                <div class="profile-image-container">
                    <div class="profile-image-large"
                        style="<?php if(!empty($expert['avatar']) && file_exists('uploads/avatars/'.$expert['avatar'])) echo "background-image: url('uploads/avatars/".htmlspecialchars($expert['avatar'])."'); background-size: cover; background-position: center; color: transparent;"; ?>">
                        <?php if(empty($expert['avatar']) || !file_exists('uploads/avatars/'.$expert['avatar'])) echo mb_strtoupper(mb_substr($expert['username'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                    </div>
                </div>
                <div class="profile-header-info-main">
                    <div class="profile-info-stack">
                        <h1 class="profile-fullname">
                            <?php echo htmlspecialchars($expert['full_name']); ?>
                        </h1>
                        <p class="profile-specialty">
                            <?php echo htmlspecialchars($expert['specialty'] ?? 'ผู้เชี่ยวชาญทั่วไป'); ?>
                        </p>
                        <div class="profile-points-status">
                            <span class="points-number"><?php echo $expert['points']; ?></span>
                            <span class="points-text">Points Earned</span>
                        </div>
                    </div>

                    <div class="expert-stats-pill">
                        <div class="stat-badge">
                            <i data-lucide="file-text"></i>
                            <span><?php echo $expert['doc_count']; ?> Documents</span>
                        </div>
                        <div class="stat-badge">
                            <i data-lucide="edit-3"></i>
                            <span><?php echo $expert['wiki_count']; ?> Wiki Contributions</span>
                        </div>
                        <?php if ($expert['phone']): ?>
                            <div class="stat-badge">
                                <i data-lucide="phone"></i>
                                <span><?php echo htmlspecialchars($expert['phone']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="stat-badge">
                            <i data-lucide="mail"></i>
                            <span><?php echo htmlspecialchars($expert['email']); ?></span>
                        </div>
                    </div>
                </div>
            </div>


            <div class="content-section">
                <div>
                    <div class="info-card">
                        <span class="info-label">ประวัติและข้อมูลส่วนตัว (Bio)</span>
                        <div style="line-height: 1.8; color: #475569;">
                            <?php echo nl2br(htmlspecialchars($expert['bio'] ?? 'ยังไม่มีข้อมูลประวัติ')); ?>
                        </div>
                    </div>

                    <div class="info-card">
                        <span class="info-label">ผลงานและทักษะความชำนาญ (Portfolio)</span>
                        <div style="line-height: 1.8; color: #475569;">
                            <?php echo nl2br(htmlspecialchars($expert['portfolio'] ?? 'ยังไม่มีข้อมูลผลงาน')); ?>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 style="font-size: 1.125rem; font-weight: 800; margin-bottom: 1.5rem;">คลังความรู้ล่าสุด</h3>
                    <?php foreach ($latest_docs as $doc): ?>
                        <a href="view.php?id=<?php echo $doc['id']; ?>" class="info-card"
                            style="display: block; text-decoration: none; padding: 1.25rem; transition: var(--transition-base);">
                            <h4 style="font-weight: 700; margin-bottom: 0.5rem; color: #1e293b;">
                                <?php echo htmlspecialchars($doc['title']); ?>
                            </h4>
                            <div style="font-size: 0.75rem; color: #94a3b8;">
                                <i data-lucide="calendar" style="width: 12px; vertical-align: middle;"></i>
                                <?php echo date('d M Y', strtotime($doc['created_at'])); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($latest_docs)): ?>
                        <p style="color: #94a3b8; font-size: 0.875rem; font-style: italic;">ยังไม่ได้สร้างเนื้อหา</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <?php if ($can_edit): ?>
        <div id="editModal" class="modal-edit">
            <div class="modal-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 800;">แก้ไขข้อมูลผู้เชี่ยวชาญ</h3>
                    <button onclick="document.getElementById('editModal').style.display='none'"
                        style="background:none; border:none; cursor:pointer;"><i data-lucide="x"></i></button>
                </div>

                <form action="expert_view.php?id=<?php echo $expert_id; ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">รูปประจำตัว</label>
                        <input type="file" name="avatar" class="form-input">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" name="full_name" class="form-input"
                            value="<?php echo htmlspecialchars($expert['full_name']); ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">ความเชี่ยวชาญ / ตำแหน่ง</label>
                        <input type="text" name="specialty" class="form-input"
                            value="<?php echo htmlspecialchars($expert['specialty'] ?? ''); ?>"
                            placeholder="เช่น ผู้เชี่ยวชาญด้าน EdPEx, นักพัฒนาซอฟต์แวร์">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">เบอร์โทรศัพท์ติดต่อ</label>
                        <input type="text" name="phone" class="form-input"
                            value="<?php echo htmlspecialchars($expert['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">ประวัติย่อ (Bio)</label>
                        <textarea name="bio" class="form-input"
                            rows="4"><?php echo htmlspecialchars($expert['bio'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label class="form-label">ผลงานและทักษะที่เกี่ยวข้อง (Portfolio)</label>
                        <textarea name="portfolio" class="form-input"
                            rows="4"><?php echo htmlspecialchars($expert['portfolio'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 1rem;">
                        บันทึกการเปลี่ยนแปลง
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>lucide.createIcons();</script>
</body>

</html>