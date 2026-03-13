<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$user_id = is_logged_in() ? $_SESSION['user_id'] : 0;
$user_data = [];

if ($user_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
} else {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $specialty = $_POST['specialty'];
    $bio = $_POST['bio'];
    $department = $_POST['department'];

    try {
        $stmt = $pdo->prepare("UPDATE users SET specialty = ?, bio = ?, department = ?, role = 'contributor' WHERE id = ?");
        $stmt->execute([$specialty, $bio, $department, $user_id]);
        log_activity('profile_update', 'user', "Specialty: $specialty | Dept: $department");
        $message = "ลงทะเบียนข้อมูลผู้เชี่ยวชาญเรียบร้อยแล้ว! ตอนนี้คุณสามารถแบ่งปันความรู้ได้เต็มรูปแบบ";

        // Handle Avatar Upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            $newFileName = 'expert_' . uniqid() . '.' . $fileExtension;
            $uploadFileDir = './uploads/avatars/';

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }

            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$newFileName, $user_id]);
                log_activity('avatar_upload', 'user', "New avatar: $newFileName");
                $_SESSION['avatar'] = $newFileName;
            }
        }

        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();

    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนผู้เชี่ยวชาญ | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .experts-form {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 3rem;
            box-shadow: rgba(20, 29, 31, 0.05) 0px 1px 2px 0px;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>ลงทะเบียนผู้เชี่ยวชาญ (Expert Enrollment)</h2>
                    <p>ร่วมเป็นส่วนหนึ่งของการสร้างสังคมแห่งการเรียนรู้โดยการระบุความเชี่ยวชาญของคุณ</p>
                </div>
            </header>

            <?php if ($message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'ลงทะเบียนสำเร็จ!',
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

            <div class="experts-form">
                <form action="experts_create.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div
                        style="text-align: center; margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid var(--border-color);">
                        <div class="avatar-edit-container">
                            <?php
                            $avatar_url = !empty($user_data['avatar']) ? 'uploads/avatars/' . $user_data['avatar'] : '';
                            $has_avatar = !empty($avatar_url) && file_exists(__DIR__ . '/' . $avatar_url);
                            $initials = strtoupper(substr($user_data['username'], 0, 1));
                            ?>
                            <div class="avatar-preview-wrapper" id="avatar-preview"
                                style="<?php echo $has_avatar ? "background-image: url('$avatar_url');" : ""; ?>">
                                <?php if (!$has_avatar): ?>
                                    <span id="avatar-initials"><?php echo $initials; ?></span>
                                <?php endif; ?>
                            </div>
                            <label for="avatar-input" class="avatar-edit-btn">
                                <i data-lucide="camera" style="width: 20px; height: 20px;"></i>
                            </label>
                            <input type="file" name="avatar" id="avatar-input" class="hidden-file-input"
                                accept="image/*" onchange="previewAvatar(this)">
                        </div>
                        <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem;">
                            <?php echo htmlspecialchars($user_data['full_name']); ?>
                        </h3>
                        <p style="color: hsl(var(--muted-foreground)); font-size: 0.875rem;">
                            <?php echo htmlspecialchars($user_data['email']); ?>
                        </p>
                    </div>


                    <div class="form-group">
                        <label class="form-label">สาขาความเชี่ยวชาญ (Specialty)</label>
                        <input type="text" name="specialty" class="form-input"
                            placeholder="เช่น วิทยาการข้อมูล, การจัดการทรัพยากรมนุษย์, กฎหมายมหาชน"
                            value="<?php echo htmlspecialchars($user_data['specialty'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">หน่วยงาน/คณะ (Department)</label>
                        <input type="text" name="department" class="form-input"
                            placeholder="เช่น คณะวิทยาศาสตร์, สำนักวิทยบริการ"
                            value="<?php echo htmlspecialchars($user_data['department'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ประวัติและผลงานโดยย่อ (Short Bio)</label>
                        <textarea name="bio" class="form-textarea"
                            placeholder="ระบุประสบการณ์หรือผลงานเด่นที่ต้องการเผยแพร่..."><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem; padding-top: 1rem;">
                        <button type="submit" class="btn-primary"
                            style="flex: 1; justify-content: center; padding: 1rem;">บันทึกข้อมูลหน้าข้อมูล</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        lucide.createIcons();

        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById('avatar-preview');
                    const initials = document.getElementById('avatar-initials');
                    preview.style.backgroundImage = `url(${e.target.result})`;
                    if (initials) initials.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

</body>

</html>