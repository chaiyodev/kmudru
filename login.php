<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

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
            log_activity('login_success', 'user', "User: $username");
            header("Location: index.php");
            exit;
        } else {
            $error = is_string($login_result) ? $login_result : 'ชื่อผู้เข้าใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาอัปเดตเบราว์เซอร์ให้เป็นรุ่นล่าสุดหรือลองใช้โหมดไม่ระบุตัวตน (Incognito) ครับ';
            log_activity('login_failure', 'user', "User: $username | Error: $error");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Specific hint for CSRF/Session errors common on mobile
        if (strpos($error, 'Session/CSRF Expired') !== false) {
            $error .= " (แนะนำ: ลองปิด 'บล็อกคุกกี้ทั้งหมด' ในการตั้งค่าเบราว์เซอร์มือถือของคุณ)";
        }
    }
}
?>
<?php
$page_title = 'เข้าสู่ระบบ | UDRU Wisdom';
$extra_css = '
    <style>
        body {
            background-color: #0f172a;
            background-image: radial-gradient(circle at top right, rgba(20, 184, 166, 0.1), transparent), radial-gradient(circle at bottom left, rgba(99, 102, 241, 0.1), transparent);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: \'Inter\', \'Sarabun\', sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 3.5rem 3rem;
            border-radius: 32px;
            text-align: center;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .logo-box {
            width: 72px;
            height: 72px;
            background: var(--teal-primary);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            box-shadow: 0 10px 30px rgba(20, 184, 166, 0.3);
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
            margin-bottom: 0.5rem;
            margin-left: 0.25rem;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1.25rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--teal-primary);
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
            transform: translateY(-2px);
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: var(--teal-primary);
            color: white;
            border: none;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
            box-shadow: 0 10px 20px rgba(20, 184, 166, 0.2);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(20, 184, 166, 0.3);
            filter: brightness(1.1);
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            padding: 1rem;
            border-radius: 16px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
            text-align: left;
        }
    </style>';
require_once __DIR__ . '/includes/head.php';
?>

    <div class="login-card animate-fade-in">
        <div class="logo-box"><i data-lucide="book-open" style="width: 36px; height: 36px;"></i></div>
        <h2 style="color: white; font-weight: 900; margin-bottom: 0.5rem; font-size: 2rem; letter-spacing: -1px;">UDRU
            Wisdom</h2>
        <p
            style="color: #64748b; margin-bottom: 2.5rem; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; font-size: 0.75rem;">
            Knowledge Center Access</p>

        <script>
            <?php if ($error): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: '<?php echo htmlspecialchars(str_replace('\'', '\\\'', $error)); ?>',
                    confirmButtonColor: 'var(--teal-primary)',
                    background: '#1e293b',
                    color: '#f8fafc',
                    customClass: {
                        popup: 'animated tada'
                    }
                });
            <?php endif; ?>
        </script>

        <a href="auth_google.php" class="btn-sso-google"
            style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; width: 100%; padding: 1rem; background: white; border: none; border-radius: 16px; color: #1e293b; text-decoration: none; font-weight: 700; font-size: 1rem; margin-bottom: 2rem; transition: all 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <svg width="24" height="24" viewBox="0 0 48 48">
                <path fill="#EA4335"
                    d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z">
                </path>
                <path fill="#4285F4"
                    d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6 c4.51-4.18 7.09-10.36 7.09-17.65z">
                </path>
                <path fill="#FBBC05"
                    d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z">
                </path>
                <path fill="#34A853"
                    d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z">
                </path>
            </svg>
            เข้าใช้งานด้วย UDRU Account
        </a>

        <div
            style="display: flex; align-items: center; gap: 1rem; color: #475569; margin-bottom: 2rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.05);"></div>
            <span>หรือระบุรหัสประจำตัว</span>
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.05);"></div>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="form-group">
                <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" class="form-control" placeholder="ชื่อผู้ใช้หรืออีเมล" required
                    autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">รหัสผ่าน (Password)</label>
                <input type="password" name="password" class="form-control" placeholder="รหัสผ่านเข้าเล่นระบบ" required>
            </div>
            <button type="submit" class="btn-login">
                เข้าสู่ระบบ Wisdom
            </button>
        </form>

        <p style="margin-top: 2.5rem; color: #64748b; font-size: 0.875rem;">
            ยังไม่มีบัญชีผู้ใช้? <a href="register.php"
                style="color: var(--teal-primary); text-decoration: none; font-weight: 800; border-bottom: 2px solid rgba(20, 184, 166, 0.2);">ลงทะเบียนที่นี่</a>
        </p>
    </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>