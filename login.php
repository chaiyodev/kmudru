<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    try {
        verify_csrf_token($token);
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $login_result = login($username, $password);
        if ($login_result === true) {
            session_regenerate_id(true); // Security: Prevent session fixation
            header("Location: index.php");
            exit;
        } else {
            $error = is_string($login_result) ? $login_result : 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
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

        <a href="auth_google.php" class="btn-sso-google"
            style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; width: 100%; padding: 0.85rem; background: white; border: 1px solid #e2e8f0; border-radius: 12px; color: #1e293b; text-decoration: none; font-weight: 600; font-size: 0.95rem; margin-bottom: 1.5rem; transition: all 0.2s;">
            <svg width="20" height="20" viewBox="0 0 48 48">
                <path fill="#EA4335"
                    d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z">
                </path>
                <path fill="#4285F4"
                    d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z">
                </path>
                <path fill="#FBBC05"
                    d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z">
                </path>
                <path fill="#34A853"
                    d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z">
                </path>
            </svg>
            ใช้งานผ่าน UDRU Account / Google
        </a>

        <div
            style="display: flex; align-items: center; gap: 1rem; color: #64748b; margin-bottom: 1.5rem; font-size: 0.85rem;">
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
            <span>หรือเข้าใช้งานด้วยรหัสผ่าน</span>
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
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