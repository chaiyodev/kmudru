<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$user_id = is_logged_in() ? $_SESSION['user_id'] : 0;

if (!$user_id) {
    header("Location: login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $dept = $_POST['department'];

    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, department = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $dept, $user_id]);
        $message = "บันทึกการตั้งค่าเรียบร้อยแล้ว!";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่า | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .settings-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 2rem;
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: rgba(20, 29, 31, 0.05) 0px 1px 2px 0px;
        }

        .settings-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .settings-nav-link {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: hsl(var(--muted-foreground));
            text-decoration: none;
            transition: var(--transition-base);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .settings-nav-link:hover,
        .settings-nav-link.active {
            background: hsl(var(--muted));
            color: var(--teal-primary);
        }

        .settings-nav-link.active {
            background: hsl(var(--primary) / 0.1);
        }

        .settings-content {
            padding-left: 2rem;
            border-left: 1px solid var(--border-color);
        }

        .settings-section-title {
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .toggle-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .toggle-info h4 {
            font-size: 0.9375rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .toggle-info p {
            font-size: 0.8125rem;
            color: hsl(var(--muted-foreground));
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: hsl(var(--muted));
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--teal-primary);
        }

        input:checked+.slider:before {
            transform: translateX(20px);
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>การตั้งค่าบัญชี (Settings)</h2>
                    <p>จัดการข้อมูลส่วนตัว ความเป็นส่วนตัว และตั้งค่าการใช้งานต่างๆ</p>
                </div>
            </header>

            <?php if ($message): ?>
                <div
                    style="background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid hsl(142 76% 36% / 0.2);">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="settings-layout">
                <div class="settings-nav">
                    <a href="#" class="settings-nav-link active"><i data-lucide="user"></i>ข้อมูลบัญชี</a>
                    <a href="#" class="settings-nav-link"><i data-lucide="bell"></i>การแจ้งเตือน</a>
                    <a href="#" class="settings-nav-link"><i data-lucide="shield"></i>ความปลอดภัย</a>
                    <a href="#" class="settings-nav-link"><i data-lucide="monitor"></i>การแสดงผล</a>
                    <div
                        style="margin-top: 2rem; padding: 1rem; background: hsl(var(--primary)/0.05); border-radius: 0.75rem; font-size: 0.75rem; color: var(--teal-primary);">
                        <i data-lucide="info"
                            style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i>
                        เวอร์ชันระบบ 1.0.0 (Phase 3)
                    </div>
                </div>

                <div class="settings-content">
                    <section>
                        <h3 class="settings-section-title">ข้อมูลส่วนตัว</h3>
                        <form action="settings.php" method="POST">
                            <div class="form-group">
                                <label class="form-label">ชื่อ-นามสกุล</label>
                                <input type="text" name="full_name" class="form-input"
                                    value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                            </div>
                            <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div>
                                    <label class="form-label">อีเมลติดต่อ</label>
                                    <input type="email" name="email" class="form-input"
                                        value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">หน่วยงาน</label>
                                    <input type="text" name="department" class="form-input"
                                        value="<?php echo htmlspecialchars($user_data['department'] ?? ''); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn-primary"
                                style="margin-top: 1rem;">บันทึกการเปลี่ยนแปลง</button>
                        </form>
                    </section>

                    <section style="margin-top: 4rem;">
                        <h3 class="settings-section-title">ตั้งค่าการแจ้งเตือน</h3>
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <h4>อีเมลแจ้งเตือน (Email Notifications)</h4>
                                <p>รับอีเมลเมื่อมีบทความใหม่หรือคำถามที่ตรงกับความเชี่ยวชาญของคุณ</p>
                            </div>
                            <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                        </div>
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <h4>การแจ้งเตือนในระบบ (System Alerts)</h4>
                                <p>แสดงจุดแจ้งเตือนเมื่อมีคนมาคอมเมนต์หรือกดไลก์ผลงานของคุณ</p>
                            </div>
                            <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
                        </div>
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <h4>สรุปรายสัปดาห์ (Weekly Digest)</h4>
                                <p>ส่งสรุปกิจกรรมและความรู้น่าสนใจประจำสัปดาห์</p>
                            </div>
                            <label class="switch"><input type="checkbox"><span class="slider"></span></label>
                        </div>
                    </section>

                    <section style="margin-top: 4rem;">
                        <h3 class="settings-section-title">การแสดงผล</h3>
                        <div class="toggle-group" style="border-bottom: none;">
                            <div class="toggle-info">
                                <h4>โหมดมืด (Dark Mode)</h4>
                                <p>ปรับเปลี่ยนธีมของแอปพลิเคชันให้เป็นสีเข้มเพื่อถนอมสายตา</p>
                            </div>
                            <label class="switch"><input type="checkbox"><span class="slider"></span></label>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>