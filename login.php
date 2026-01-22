<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        session_regenerate_id(true); // Security: Prevent session fixation
        header("Location: index.php");
        exit;
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | KM Portal UDRU</title>
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
        }

        .login-card {
            width: 100%;
            max-width: 420px;
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
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 0.5rem;
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
    </style>
</head>

<body>
    <div class="login-card glass animate-fade-in">
        <div class="logo-box"><i data-lucide="book-open" style="width: 32px; height: 32px;"></i></div>
        <h2 style="color: white; font-weight: 800; margin-bottom: 0.5rem;">KM Portal</h2>
        <p style="color: #94a3b8; margin-bottom: 2.5rem; font-size: 0.9rem;">UDRU Knowledge Hub Access</p>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" class="form-control" placeholder="ระบุชื่อผู้ใช้งาน" required
                    autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">รหัสผ่าน (Password)</label>
                <input type="password" name="password" class="form-control" placeholder="ระบุรหัสผ่าน" required>
            </div>
            <button type="submit" class="btn-search"
                style="width: 100%; padding: 1rem; margin-top: 1rem;">เข้าสู่ระบบสมาชิก</button>
        </form>

        <p style="margin-top: 2rem; color: #94a3b8; font-size: 0.9rem;">
            ยังไม่มีบัญชีผู้ใช้? <a href="register.php"
                style="color: var(--primary); text-decoration: none; font-weight: 700;">ลงทะเบียนที่นี่</a>
        </p>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>