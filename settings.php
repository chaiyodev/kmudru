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
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dept = trim($_POST['department'] ?? '');

    if (empty($full_name) || empty($email)) {
        $message = "กรุณากรอกชื่อ-นามสกุลและอีเมล";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, department = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $dept, $user_id]);
            $message = "บันทึกการตั้งค่าเรียบร้อยแล้ว!";
        } catch (PDOException $e) {
            error_log("Settings update error: " . $e->getMessage());
            $message = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
        }
    }
}

// Ensure user_preferences table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_preferences (
        user_id INT PRIMARY KEY,
        email_notifications TINYINT(1) DEFAULT 1,
        system_alerts TINYINT(1) DEFAULT 1,
        two_factor_auth TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log("user_preferences table error: " . $e->getMessage());
}

// Handle AJAX preference save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_pref'])) {
    header('Content-Type: application/json');
    $pref_key = $_POST['pref_key'] ?? '';
    $pref_val = (int)($_POST['pref_val'] ?? 0);
    $allowed_keys = ['email_notifications', 'system_alerts', 'two_factor_auth'];
    if (in_array($pref_key, $allowed_keys)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, $pref_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE $pref_key = ?");
            $stmt->execute([$user_id, $pref_val, $pref_val]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            error_log("Pref save error: " . $e->getMessage());
            echo json_encode(['status' => 'error']);
        }
    } else {
        echo json_encode(['status' => 'invalid_key']);
    }
    exit;
}

// Load saved preferences
$user_prefs = ['email_notifications' => 1, 'system_alerts' => 1, 'two_factor_auth' => 0];
try {
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $saved_prefs = $stmt->fetch();
    if ($saved_prefs) {
        $user_prefs['email_notifications'] = (int)$saved_prefs['email_notifications'];
        $user_prefs['system_alerts'] = (int)$saved_prefs['system_alerts'];
        $user_prefs['two_factor_auth'] = (int)$saved_prefs['two_factor_auth'];
    }
} catch (PDOException $e) {
    error_log("Pref load error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่า | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
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
            border: none;
            background: transparent;
            cursor: pointer;
            text-align: left;
            width: 100%;
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
            background-color: #e2e8f0;
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

        /* Theme Presets */
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .theme-box {
            height: 60px;
            border-radius: 12px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: 0.2s;
            position: relative;
        }

        .theme-box:hover {
            transform: scale(1.05);
        }

        .theme-box.active {
            border-color: #0f172a;
        }

        .theme-box::after {
            content: attr(data-name);
            position: absolute;
            bottom: -20px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .settings-layout {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1.5rem;
            }
            .settings-content {
                padding-left: 0;
                border-left: none;
                border-top: 1px solid var(--border-color);
                padding-top: 1.5rem;
            }
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
            .settings-nav {
                flex-direction: row;
                overflow-x: auto;
                padding-bottom: 0.5rem;
            }
            .settings-nav-link {
                white-space: nowrap;
                width: auto;
                padding: 0.5rem 1rem;
            }
            /* Hide the version text on mobile so nav fits better */
            .settings-version-text {
                display: none;
            }
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

            <div class="settings-layout">
                <aside class="settings-nav">
                    <button onclick="showSection('profile')" class="settings-nav-link active" id="nav-profile"><i
                            data-lucide="user"></i>ข้อมูลบัญชี</button>
                    <button onclick="showSection('notifications')" class="settings-nav-link" id="nav-notifications"><i
                            data-lucide="bell"></i>การแจ้งเตือน</button>
                    <button onclick="showSection('security')" class="settings-nav-link" id="nav-security"><i
                            data-lucide="shield"></i>ความปลอดภัย</button>
                    <button onclick="showSection('display')" class="settings-nav-link" id="nav-display"><i
                            data-lucide="monitor"></i>การแสดงผล</button>
                    <div class="settings-version-text"
                        style="margin-top: auto; padding: 1rem; background: hsl(var(--primary)/0.05); border-radius: 0.75rem; font-size: 0.75rem; color: var(--teal-primary);">
                        <i data-lucide="info"
                            style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i>
                        <?php echo APP_VERSION; ?>
                    </div>
                </aside>

                <div class="settings-content">
                    <!-- Profile Section -->
                    <section id="section-profile">
                        <h3 class="settings-section-title"><i data-lucide="user"></i> ข้อมูลส่วนตัว</h3>
                        <form id="profile-form" action="settings.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <div class="form-group">
                                <label class="form-label">ชื่อ-นามสกุล</label>
                                <input type="text" name="full_name" class="form-input"
                                    value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                            </div>
                            <div class="form-group form-grid-2">
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

                    <!-- Notifications Section -->
                    <section id="section-notifications" style="display: none;">
                        <h3 class="settings-section-title"><i data-lucide="bell"></i> ตั้งค่าการแจ้งเตือน</h3>
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <h4>อีเมลแจ้งเตือน (Email Notifications)</h4>
                                <p>รับอีเมลเมื่อมีบทความใหม่หรือคำถามที่ตรงกับความเชี่ยวชาญ</p>
                            </div>
                            <label class="switch"><input type="checkbox" <?php echo $user_prefs['email_notifications'] ? 'checked' : ''; ?>
                                    onchange="savePref('email_notifications', this.checked, 'Email Notifications')"><span class="slider"></span></label>
                        </div>
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <h4>การแจ้งเตือนในระบบ (System Alerts)</h4>
                                <p>แสดงจุดแจ้งเตือนเมื่อมีคนมาคอมเมนต์หรือกดไลก์</p>
                            </div>
                            <label class="switch"><input type="checkbox" <?php echo $user_prefs['system_alerts'] ? 'checked' : ''; ?>
                                    onchange="savePref('system_alerts', this.checked, 'System Alerts')"><span class="slider"></span></label>
                        </div>
                    </section>

                    <!-- Security Section -->
                    <section id="section-security" style="display: none;">
                        <h3 class="settings-section-title"><i data-lucide="shield"></i> ความปลอดภัย</h3>
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <h4>การยืนยันตัวตนสองชั้น (2FA)</h4>
                                <p>เพิ่มความปลอดภัยพิเศษให้กับบัญชีของคุณ</p>
                            </div>
                            <label class="switch"><input type="checkbox" <?php echo $user_prefs['two_factor_auth'] ? 'checked' : ''; ?>
                                    onchange="savePref('two_factor_auth', this.checked, '2FA Authenticator')"><span class="slider"></span></label>
                        </div>
                        <button class="btn-primary"
                            style="margin-top: 2rem; background: #64748b;">เปลี่ยนรหัสผ่านใหม่</button>
                    </section>

                    <!-- Display Section -->
                    <section id="section-display" style="display: none;">
                        <h3 class="settings-section-title"><i data-lucide="palette"></i> การแสดงผลและธีม (WOW Theme)
                        </h3>

                        <div class="toggle-group" style="border-bottom: none; margin-bottom: 2rem;">
                            <div class="toggle-info">
                                <h4>โหมดมืด (Dark Mode)</h4>
                                <p>ปรับธีมของแอปพลิเคชันให้เป็นสีเข้มเพื่อถนอมสายตา</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="dark-mode-toggle" onchange="toggleDarkMode(this.checked)">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <h4 style="font-size: 0.9375rem; font-weight: 700; margin-bottom: 1rem;">เลือกสีประจำตัวคุณ
                            (Personalized Theme)</h4>
                        <div class="theme-grid">
                            <div class="theme-box" data-name="Teal" data-hsl="174 62% 32%"
                                style="background: hsl(174 62% 32%)" onclick="setTheme('174 62% 32%', this)"></div>
                            <div class="theme-box" data-name="Ocean" data-hsl="199 89% 48%"
                                style="background: hsl(199 89% 48%)" onclick="setTheme('199 89% 48%', this)"></div>
                            <div class="theme-box" data-name="Indigo" data-hsl="239 84% 67%"
                                style="background: hsl(239 84% 67%)" onclick="setTheme('239 84% 67%', this)"></div>
                            <div class="theme-box" data-name="Rose" data-hsl="350 89% 60%"
                                style="background: hsl(350 89% 60%)" onclick="setTheme('350 89% 60%', this)"></div>
                            <div class="theme-box" data-name="Amber" data-hsl="38 92% 50%"
                                style="background: hsl(38 92% 50%)" onclick="setTheme('38 92% 50%', this)"></div>
                            <div class="theme-box" data-name="Vibrant" data-hsl="282 91% 62%"
                                style="background: hsl(282 91% 62%)" onclick="setTheme('282 91% 62%', this)"></div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        lucide.createIcons();

        function showSection(sectionId) {
            // Hide all sections
            const sections = ['profile', 'notifications', 'security', 'display'];
            sections.forEach(s => {
                document.getElementById('section-' + s).style.display = 'none';
                document.getElementById('nav-' + s).classList.remove('active');
            });

            // Show target section
            document.getElementById('section-' + sectionId).style.display = 'block';
            document.getElementById('nav-' + sectionId).classList.add('active');
        }

        function notifyChange(settingName) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `อัปเดต ${settingName} เรียบร้อยแล้ว`,
                showConfirmButton: false,
                timer: 2000
            });
        }

        async function savePref(key, value, label) {
            try {
                const formData = new FormData();
                formData.append('ajax_pref', '1');
                formData.append('pref_key', key);
                formData.append('pref_val', value ? 1 : 0);
                const res = await fetch('settings.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.status === 'success') {
                    notifyChange(label);
                }
            } catch (err) {
                console.error('Pref save error:', err);
            }
        }

        function setTheme(hsl, el) {
            // Update UI
            document.querySelectorAll('.theme-box').forEach(box => box.classList.remove('active'));
            el.classList.add('active');

            // Apply Theme
            document.documentElement.style.setProperty('--primary', hsl);
            document.documentElement.style.setProperty('--teal-primary', `hsl(${hsl})`);

            // Persist
            localStorage.setItem('theme-primary', hsl);

            Swal.fire({
                icon: 'success',
                title: 'ธีมเปลี่ยนไปแล้ว!',
                text: 'สีประจำตัวคุณถูกนำไปใช้ทั่วทั้งเว็บไซต์แล้วครับ 😍',
                timer: 2000,
                showConfirmButton: false
            });
        }

        function toggleDarkMode(isDark) {
            localStorage.setItem('theme-dark-mode', isDark);

            if (isDark) {
                document.documentElement.classList.add('dark-mode');
                document.documentElement.style.setProperty('--background', '222 47% 11%');
                document.documentElement.style.setProperty('--foreground', '210 40% 98%');
                document.documentElement.style.setProperty('--card', '217 33% 17%');
                document.documentElement.style.setProperty('--border', '217 33% 25%');
                document.documentElement.style.setProperty('--muted', '217 33% 20%');
            } else {
                document.documentElement.classList.remove('dark-mode');
                // Reset to defaults (based on style.css)
                document.documentElement.style.setProperty('--background', '180 20% 99%');
                document.documentElement.style.setProperty('--foreground', '192 80% 10%');
                document.documentElement.style.setProperty('--card', '0 0% 100%');
                document.documentElement.style.setProperty('--border', '200 20% 90%');
                document.documentElement.style.setProperty('--muted', '200 20% 96%');
            }

            notifyChange('Dark Mode');
        }

        // Initialize Settings
        document.addEventListener('DOMContentLoaded', () => {
            const savedPrimary = localStorage.getItem('theme-primary');
            if (savedPrimary) {
                const activeBox = document.querySelector(`.theme-box[data-hsl="${savedPrimary}"]`);
                if (activeBox) activeBox.classList.add('active');
            }

            const isDark = localStorage.getItem('theme-dark-mode') === 'true';
            document.getElementById('dark-mode-toggle').checked = isDark;
        });
    </script>
</body>

</html>