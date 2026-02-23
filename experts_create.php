<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
require_login();
$user_id = $_SESSION['user_id'];
$user_data = [];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $specialty = $_POST['specialty'];
    $bio = $_POST['bio'];
    $department = $_POST['department'];

    try {
        $stmt = $pdo->prepare("UPDATE users SET specialty = ?, bio = ?, department = ?, role = 'contributor' WHERE id = ?");
        $stmt->execute([$specialty, $bio, $department, $user_id]);
        $message = "ลงทะเบียนข้อมูลผู้เชี่ยวชาญเรียบร้อยแล้ว! ตอนนี้คุณสามารถแบ่งปันความรู้ได้เต็มรูปแบบ";
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
                <div
                    style="max-width: 800px; margin: 0 auto 2rem; background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); padding: 1.25rem; border-radius: 1rem; text-align: center; border: 1px solid hsl(142 76% 36% / 0.2);">
                    <?php echo $message; ?>
                    <div style="margin-top: 1rem;"><a href="experts.php" class="btn-primary"
                            style="display:inline-flex;">ดูทำเนียบผู้เชี่ยวชาญ</a></div>
                </div>
            <?php endif; ?>

            <div class="experts-form">
                <form action="experts_create.php" method="POST">
                    <div
                        style="display: flex; gap: 2rem; align-items: center; margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 1px solid var(--border-color);">
                        <div
                            style="width: 100px; height: 100px; border-radius: 30px; background: var(--teal-primary); color: white; display:flex; align-items:center; justify-content:center; font-size: 2.5rem; font-weight: 800;">
                            <?php echo strtoupper(substr($user_data['username'], 0, 1)); ?>
                        </div>
                        <div>
                            <h3 style="font-size: 1.5rem; font-weight: 800;">
                                <?php echo htmlspecialchars($user_data['full_name']); ?>
                            </h3>
                            <p style="color: hsl(var(--muted-foreground));">
                                <?php echo htmlspecialchars($user_data['email']); ?>
                            </p>
                        </div>
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
    <script>lucide.createIcons();</script>
</body>

</html>