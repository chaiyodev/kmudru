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

$page_title = 'เข้าสู่ระบบ | UDRU Wisdom';
require_once __DIR__ . '/includes/head.php';
?>
    <style>
        :root {
            --glass-bg: rgba(15, 23, 42, 0.75);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }

        @keyframes pulse-glow {
            0% { box-shadow: 0 0 20px rgba(20, 184, 166, 0.2); }
            50% { box-shadow: 0 0 40px rgba(20, 184, 166, 0.4); }
            100% { box-shadow: 0 0 20px rgba(20, 184, 166, 0.2); }
        }

        body {
            background-color: #020617;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(20, 184, 166, 0.1), transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(99, 102, 241, 0.1), transparent 40%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 40px 20px;
            font-family: 'Inter', 'Sarabun', sans-serif;
            overflow: hidden; /* Hide scrollbars for the extra effect layer */
            position: relative;
        }

        /* Mouse Follower Glow */
        #cursor-glow {
            position: fixed;
            top: 0;
            left: 0;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(20, 184, 166, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 1;
            transform: translate(-50%, -50%);
            transition: transform 0.1s ease-out;
            will-change: transform;
        }

        .bg-blobs {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            overflow: hidden;
            pointer-events: none;
        }

        .blob {
            position: absolute;
            background: var(--teal-primary);
            filter: blur(100px);
            border-radius: 50%;
            opacity: 0.15;
            transition: transform 0.2s ease-out;
            will-change: transform;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 3.5rem 2.5rem;
            border-radius: 36px;
            text-align: center;
            background: var(--glass-bg);
            backdrop-filter: blur(32px);
            -webkit-backdrop-filter: blur(32px);
            border: 1px solid var(--glass-border);
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                0 0 40px rgba(20, 184, 166, 0.05);
            position: relative;
            z-index: 10;
            margin: auto;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .logo-box {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--teal-primary), #0ea5e9);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white !important; /* Force white icon */
            box-shadow: 0 12px 30px rgba(20, 184, 166, 0.4);
            transform: rotate(-3deg);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            animation: pulse-glow 4s infinite ease-in-out;
            position: relative;
            z-index: 5;
        }

        .logo-box svg {
            color: white !important;
            filter: drop-shadow(0 0 8px rgba(255,255,255,0.5));
        }

        .logo-box:hover {
            transform: rotate(0deg) scale(1.08);
        }

        .form-group {
            text-align: left;
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 0.5rem;
            margin-left: 0.5rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.25rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            color: white;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Prevent auto-fill from breaking styles */
        .form-control:-webkit-autofill,
        .form-control:-webkit-autofill:hover,
        .form-control:-webkit-autofill:focus {
            -webkit-text-fill-color: white !important;
            -webkit-box-shadow: 0 0 0px 1000px rgba(15, 23, 42, 1) inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--teal-primary);
            background: rgba(15, 23, 42, 0.9);
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.2);
            transform: translateY(-2px);
        }

        .btn-login {
            width: 100%;
            padding: 1.1rem;
            background: linear-gradient(135deg, var(--teal-primary) 0%, #0d9488 100%) !important;
            color: white !important;
            border: none;
            border-radius: 18px;
            font-weight: 800;
            font-size: 1.05rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
            box-shadow: 0 10px 25px rgba(20, 184, 166, 0.3) !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            z-index: 5;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(20, 184, 166, 0.5) !important;
            filter: brightness(1.1);
        }

        .btn-sso-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            width: 100%;
            padding: 1rem;
            background: white !important;
            border: none;
            border-radius: 18px;
            color: #1e293b !important;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 2rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .btn-sso-google:hover {
            background: #f1f5f9 !important;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        @media (max-width: 480px) {
            body { padding: 20px 15px; overflow: auto; }
            .login-card {
                padding: 2.5rem 1.5rem;
                border-radius: 32px;
            }
            .logo-box {
                width: 70px;
                height: 70px;
                margin-bottom: 1rem;
            }
            #cursor-glow { display: none; }
        }
    </style>

    <div id="cursor-glow"></div>
    <div class="bg-blobs">
        <div class="blob" id="blob-1" style="width: 500px; height: 500px; top: -150px; left: -150px; background: #14b8a6; opacity: 0.2;"></div>
        <div class="blob" id="blob-2" style="width: 600px; height: 600px; bottom: -200px; right: -200px; background: #6366f1; opacity: 0.15;"></div>
        <div class="blob" id="blob-3" style="width: 400px; height: 400px; top: 40%; left: 60%; background: #3b82f6; opacity: 0.1;"></div>
    </div>

    <div class="login-card animate-fade-in" id="login-card">
        <div class="logo-box">
            <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
        </div>
        <h2 style="color: white; font-weight: 900; margin-bottom: 0.5rem; font-size: 2.25rem; letter-spacing: -1.5px;">UDRU Wisdom</h2>
        <p style="color: #94a3b8; margin-bottom: 2.5rem; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; font-size: 0.75rem;">Knowledge Center Access</p>

        <script>
            <?php if ($error): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: '<?php echo htmlspecialchars(str_replace("'", "\\'", $error)); ?>',
                    confirmButtonColor: 'var(--teal-primary)',
                    background: '#1e293b',
                    color: '#f8fafc',
                    customClass: {
                        popup: 'animated tada'
                    }
                });
            <?php endif; ?>
        </script>

        <a href="auth_google.php" class="btn-sso-google">
            <svg width="24" height="24" viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6 c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
            </svg>
            เข้าใช้งานด้วย UDRU Account
        </a>

        <div style="display: flex; align-items: center; gap: 1rem; color: #475569; margin-bottom: 2rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
            <span>หรือระบุรหัสประจำตัว</span>
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="form-group">
                <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" class="form-control" placeholder="ชื่อผู้ใช้หรืออีเมล" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">รหัสผ่าน (Password)</label>
                <input type="password" name="password" class="form-control" placeholder="รหัสผ่านเข้าใช้ระบบ" required>
            </div>
            <button type="submit" class="btn-login">เข้าสู่ระบบ Wisdom</button>
        </form>

        <p style="margin-top: 3rem; color: #94a3b8; font-size: 0.875rem;">
            ยังไม่มีบัญชีผู้ใช้? <a href="register.php" style="color: var(--teal-primary); text-decoration: none; font-weight: 800; border-bottom: 2px solid rgba(20, 184, 166, 0.4);">ลงทะเบียนที่นี่</a>
        </p>
    </div>

    <script>
        // Antigravity Mouse Effects
        const glow = document.getElementById('cursor-glow');
        const b1 = document.getElementById('blob-1');
        const b2 = document.getElementById('blob-2');
        const b3 = document.getElementById('blob-3');
        const card = document.getElementById('login-card');

        document.addEventListener('mousemove', (e) => {
            const x = e.clientX;
            const y = e.clientY;
            
            // Intensified glow
            glow.style.transform = `translate(${x - 300}px, ${y - 300}px)`;
            glow.style.background = `radial-gradient(circle, rgba(20, 184, 166, 0.15) 0%, transparent 70%)`;

            // Active Parallax Blobs
            b1.style.transform = `translate(${x * 0.03}px, ${y * 0.03}px)`;
            b2.style.transform = `translate(${-x * 0.04}px, ${-y * 0.04}px)`;
            b3.style.transform = `translate(${x * -0.02}px, ${y * 0.02}px)`;

            // Subtle Card Tilt
            const centerX = window.innerWidth / 2;
            const centerY = window.innerHeight / 2;
            const tiltX = (y - centerY) * 0.015;
            const tiltY = (x - centerX) * -0.015;
            
            if (window.innerWidth > 768) {
                card.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`;
            }
        });
        
        // Ensure Lucide icons load if using the data-lucide attribute elsewhere
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>