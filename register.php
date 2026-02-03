<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';

    $pdo = get_pdo();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $full_name, $email]);
                $success = "สร้างบัญชีสำเร็จ! คุณสามารถ <a href='login.php' style='color: var(--primary); font-weight: 700; text-decoration: none;'>เข้าสู่ระบบ</a> ได้ทันที";
            }
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนบุคลากร | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            background-color: var(--sidebar-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 2rem 0;
        }

        .login-card {
            width: 100%;
            max-width: 480px;
            padding: 3rem;
            border-radius: 24px;
            text-align: center;
        }

        .logo-box {
            width: 64px;
            height: 64px;
            background: var(--primary);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            box-shadow: 0 10px 20px rgba(20, 184, 166, 0.3);
        }

        .form-group {
            text-align: left;
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 0.4rem;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            padding: 0.85rem;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .success-msg {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            padding: 0.85rem;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
    </style>
</head>

<body>
    <div class="login-card glass animate-fade-in">
        <div class="logo-box"><i data-lucide="user-plus" style="width: 32px; height: 32px;"></i></div>
        <h2 style="color: white; font-weight: 800; margin-bottom: 0.5rem;">Create Account</h2>
        <p style="color: #94a3b8; margin-bottom: 2rem; font-size: 0.9rem;">ร่วมเป็นส่วนหนึ่งของเครือข่ายความรู้ UDRU</p>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-msg"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">ชื่อ-นามสกุล</label>
                <input type="text" name="full_name" class="form-control" placeholder="เช่น นายสมชาย ใจดี" required>
            </div>
            <div class="form-group">
                <label class="form-label">อีเมลมหาวิทยาลัย</label>
                <input type="email" name="email" class="form-control" placeholder="example@udru.ac.th" required>
            </div>
            <div class="form-group">
                <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" class="form-control" placeholder="ภาษาอังกฤษหรือตัวเลข" required>
            </div>
            <div class="form-group">
                <label class="form-label">รหัสผ่าน (Password)</label>
                <input type="password" name="password" class="form-control" placeholder="กำหนดรหัสผ่านของคุณ" required>
            </div>
            <button type="submit" class="btn-search"
                style="width: 100%; padding: 1rem; margin-top: 1rem;">ยืนยันการลงทะเบียน</button>
        </form>

        <p style="margin-top: 2rem; color: #94a3b8; font-size: 0.9rem;">
            มีบัญชีผู้ใช้อยู่แล้ว? <a href="login.php"
                style="color: var(--primary); text-decoration: none; font-weight: 700;">เข้าสู่ระบบ</a>
        </p>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>