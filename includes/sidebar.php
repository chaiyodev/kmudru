<?php
// includes/sidebar.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/notifications.php';

$sidebar_pdo = get_pdo();

// Consolidate counts to run only 1 query instead of many
$type_counts = $sidebar_pdo->query("SELECT type, COUNT(*) as count FROM documents GROUP BY type")->fetchAll(PDO::FETCH_KEY_PAIR);
$total_docs = array_sum($type_counts);
$current_page = basename($_SERVER['PHP_SELF']);
$type = $_GET['type'] ?? '';
$role = strtolower($_SESSION['role'] ?? '');

// Track page visit for analytics
track_visitor($current_page);

// Fetch current user data for profile card
$sidebar_user = null;
$unread_notifications = 0;
if (is_logged_in()) {
    $stmt = $sidebar_pdo->prepare("SELECT id, avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $sidebar_user = $stmt->fetch();

    if ($sidebar_user) {
        $unread_notifications = get_unread_count($sidebar_pdo, $sidebar_user['id']);
        // Sync session avatar with DB just in case
        if (!empty($sidebar_user['avatar'])) {
            $_SESSION['avatar'] = $sidebar_user['avatar'];
        }
    }
}
?>
<script>
    // Pre-paint state check to prevent layout shift
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.documentElement.classList.add('sidebar-collapsed-init');
    }
</script>

<aside
    class="sidebar <?php echo isset($_COOKIE['sidebarCollapsed']) && $_COOKIE['sidebarCollapsed'] == 'true' ? 'collapsed' : ''; ?>"
    id="main-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i data-lucide="book-open"></i></div>
        <div class="brand-info">
            <h1>UDRU Wisdom</h1>
            <span>Knowledge Center</span>
        </div>
    </div>

    <div class="sidebar-nav-container">
        <nav class="nav-group">
            <div class="nav-label">เมนูหลัก</div>
            <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i data-lucide="layout"></i>
                <span>หน้าหลัก</span>
            </a>
            <a href="browse.php" class="nav-link <?php echo $current_page == 'browse.php' ? 'active' : ''; ?>">
                <i data-lucide="search"></i>
                <span>คลังความรู้</span>
                <span class="nav-pill"><?php echo $type_counts['document'] ?? 0; ?></span>
            </a>
            <a href="chat.php" class="nav-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>">
                <i data-lucide="message-square"></i>
                <span>กล่องข้อความ</span>
            </a>
            <!-- Notification moved to top header -->
        </nav>

        <nav class="nav-group">
            <div class="nav-label">ขุมปัญญา UDRU</div>
            <a href="experts.php"
                class="nav-link <?php echo ($current_page == 'experts.php' || $current_page == 'experts_create.php') ? 'active' : ''; ?>">
                <i data-lucide="users"></i>
                <span>รายชื่อผู้เชี่ยวชาญ</span>
            </a>
            <a href="cop.php"
                class="nav-link <?php echo ($current_page == 'cop.php' || $current_page == 'cop_create.php' || $current_page == 'cop_view.php') ? 'active' : ''; ?>">
                <i data-lucide="share-2"></i>
                <span>เครือข่าย CoP</span>
            </a>
            <a href="categories.php"
                class="nav-link <?php echo ($current_page == 'categories.php' || $current_page == 'category_create.php') ? 'active' : ''; ?>">
                <i data-lucide="folder-tree"></i>
                <span>หมวดหมู่ความรู้</span>
            </a>
            <a href="training.php"
                class="nav-link <?php echo ($current_page == 'training.php' || $current_page == 'training_create.php') ? 'active' : ''; ?>">
                <i data-lucide="graduation-cap"></i>
                <span>คอร์สความรู้</span>
            </a>
        </nav>

        <nav class="nav-group">
            <div class="nav-label">เครื่องมือระบบ</div>
            <a href="analytics.php" class="nav-link <?php echo $current_page == 'analytics.php' ? 'active' : ''; ?>">
                <i data-lucide="pie-chart"></i>
                <span>การวิเคราะห์ข้อมูล</span>
            </a>
            <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <i data-lucide="settings"></i>
                <span>ตั้งค่าระบบ</span>
            </a>
            <?php if ($role === 'admin'): ?>
                <a href="admin_dashboard.php"
                    class="nav-link <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                    <i data-lucide="shield"></i>
                    <span>การจัดการระบบ</span>
                </a>
            <?php endif; ?>
        </nav>


    </div>

    <div class="sidebar-footer">
        <?php if (is_logged_in()): ?>
            <div class="user-profile-card">
                <div class="profile-main-info">
                    <?php
                    // Prefer session if sidebar_user fetch had any issue, but sidebar_user is fresher
                    $current_avatar = !empty($sidebar_user['avatar']) ? $sidebar_user['avatar'] : ($_SESSION['avatar'] ?? '');
                    $avatar_url = !empty($current_avatar) ? 'uploads/avatars/' . $current_avatar : '';
                    $has_avatar = !empty($current_avatar) && file_exists(__DIR__ . '/../' . $avatar_url);
                    $initials = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));
                    ?>
                    <div class="profile-avatar"
                        style="<?php echo $has_avatar ? "background-image: url('$avatar_url'); background-size: cover; background-position: center; width: 44px; height: 44px;" : "width: 44px; height: 44px;"; ?>">
                        <?php if (!$has_avatar): ?>
                            <span><?php echo $initials; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name">
                            <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                        </div>
                        <div class="role-badge">
                            <?php echo htmlspecialchars($_SESSION['role']); ?>
                        </div>
                    </div>
                </div>
                <div class="profile-actions">
                    <a href="profile.php" class="btn-sm">โปรไฟล์</a>
                    <a href="logout.php" class="btn-sm logout">ออกระบบ</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php" class="nav-link">
                <i data-lucide="log-in"></i>
                <span>เข้าสู่ระบบ</span>
            </a>
        <?php endif; ?>

        <button class="sidebar-toggle" id="main-toggle-btn" onclick="toggleSidebar()">
            <i data-lucide="chevrons-left" id="toggle-icon"></i>
            <span>ย่อเมนู</span>
        </button>

        <div class="system-credit"
            style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid rgba(255,255,255,0.05);">
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 12px; opacity: 1;">
                    <i data-lucide="copyright" class="system-credit-icon" style="color: white;"></i>
                    <span style="font-size: 0.85rem; color: white; font-weight: 600;">2026 <strong
                            style="color: var(--teal-primary);">phao2024</strong></span>
                </div>
                <div style="display: flex; align-items: center; gap: 12px; opacity: 1;">
                    <i data-lucide="shield-check" class="system-credit-icon" style="color: var(--teal-primary);"></i>
                    <span
                        style="font-size: 0.85rem; color: var(--teal-primary); font-weight: 800;">prapakorn1.1.0</span>
                </div>
            </div>
        </div>

    </div>

</aside>

<style>
    /* Premium Sidebar Footer Styles */
    .sidebar.collapsed .footer-text {
        display: none;
    }

    .sidebar.collapsed .system-credit {
        padding: 1.25rem 0 !important;
        display: flex;
        justify-content: center;
    }

    .sidebar.collapsed .credit-content div {
        justify-content: center;
    }

    /* System credit icons — matching 22px baseline */
    .system-credit-icon,
    .system-credit-icon svg {
        width: 22px !important;
        height: 22px !important;
        stroke-width: 2 !important;
        flex-shrink: 0;
    }

    .ai-drawer {
        position: fixed;
        right: -400px;
        top: 0;
        width: 380px;
        height: 100vh;
        background: white;
        box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
    }

    .ai-drawer.open {
        right: 0;
    }

    .ai-drawer-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, var(--teal-primary) 0%, #0ea5e9 100%);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ai-avatar-m {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .ai-drawer-body {
        flex: 1;
        padding: 1.5rem;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        background: #f8fafc;
    }

    .ai-msg {
        max-width: 85%;
        padding: 0.75rem 1rem;
        border-radius: 1rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .ai-msg-bot {
        background: white;
        color: #1e293b;
        align-self: flex-start;
        border-bottom-left-radius: 0.25rem;
        border: 1px solid #e2e8f0;
    }

    .ai-msg-user {
        background: var(--teal-primary);
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 0.25rem;
    }

    .ai-drawer-footer {
        padding: 1rem;
        border-top: 1px solid #e2e8f0;
    }

    .ai-drawer-footer form {
        display: flex;
        gap: 0.5rem;
    }

    .ai-drawer-footer input {
        flex: 1;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 100px;
        outline: none;
    }

    .ai-drawer-footer button {
        width: 44px;
        height: 44px;
        background: var(--teal-primary);
        color: white;
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
</style>

<!-- Mobile Top Bar (Global) -->
<div class="mobile-top-bar">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div class="mobile-logo-box">
            <i data-lucide="book-open" style="width: 18px;"></i>
        </div>
        <span class="mobile-brand-name">UDRU Wisdom</span>
    </div>
    <button onclick="toggleMobileMenu()" class="mobile-menu-btn">
        <i data-lucide="menu"></i>
    </button>
</div>

<!-- Global AI Assistant UI -->
<div class="ai-float-btn-container">
    <button class="ai-float-btn" onclick="aiAssistant('chat')">
        <i data-lucide="bot-message-square" style="width: 28px; height: 28px;"></i>
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Theme Manager Script
    (function () {
        const savedTheme = localStorage.getItem('theme-primary');
        const isDarkMode = localStorage.getItem('theme-dark-mode') === 'true';

        if (savedTheme) {
            document.documentElement.style.setProperty('--primary', savedTheme);
            document.documentElement.style.setProperty('--teal-primary', `hsl(${savedTheme})`);
        }

        if (isDarkMode) {
            document.documentElement.classList.add('dark-mode');
            // Basic dark mode overrides if not in CSS
            document.documentElement.style.setProperty('--background', '222 47% 11%');
            document.documentElement.style.setProperty('--foreground', '210 40% 98%');
            document.documentElement.style.setProperty('--card', '217 33% 17%');
            document.documentElement.style.setProperty('--border', '217 33% 25%');
            document.documentElement.style.setProperty('--muted', '217 33% 20%');
        }
    })();

    function toggleSidebar() {
        const sidebar = document.getElementById('main-sidebar');
        const body = document.body;
        const icon = document.getElementById('toggle-icon');

        sidebar.classList.toggle('collapsed');
        body.classList.toggle('sidebar-collapsed');

        // Remove initial class if present
        document.documentElement.classList.remove('sidebar-collapsed-init');

        const isCollapsed = sidebar.classList.contains('collapsed');

        // Update icon
        if (isCollapsed) {
            icon.setAttribute('data-lucide', 'chevrons-right');
            localStorage.setItem('sidebarCollapsed', 'true');
            document.cookie = "sidebarCollapsed=true; path=/; max-age=31536000";
        } else {
            icon.setAttribute('data-lucide', 'chevrons-left');
            localStorage.setItem('sidebarCollapsed', 'false');
            document.cookie = "sidebarCollapsed=false; path=/; max-age=31536000";
        }
        lucide.createIcons();
    }

    // Restore sidebar state on page load
    document.addEventListener('DOMContentLoaded', function () {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true' ||
            document.cookie.match(/sidebarCollapsed=true/);

        if (isCollapsed) {
            document.getElementById('main-sidebar').classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
            document.getElementById('toggle-icon').setAttribute('data-lucide', 'chevrons-right');
            lucide.createIcons();
        }
    });

    function aiAssistant(mode) {
        if (mode === 'chat') {
            const drawer = document.getElementById('ai-chat-drawer');
            drawer.classList.add('open');
        }
    }

    function closeAIDrawer() {
        document.getElementById('ai-chat-drawer').classList.remove('open');
    }

    function toggleMobileMenu() {
        const sidebar = document.getElementById('main-sidebar');
        sidebar.classList.toggle('mobile-open');
    }

    function toggleNotifications(event) {
        if (event) event.stopPropagation();
        const dropdown = document.getElementById('notif-dropdown');
        if (dropdown) dropdown.classList.toggle('show');

        // Close dropdown when clicking outside
        if (dropdown && dropdown.classList.contains('show')) {
            const closeHandler = (e) => {
                if (!dropdown.contains(e.target) && !e.target.closest('.notification-bell-btn')) {
                    dropdown.classList.remove('show');
                    document.removeEventListener('click', closeHandler);
                }
            };
            setTimeout(() => {
                document.addEventListener('click', closeHandler);
            }, 10);
        }
    }
    // Global Status Toast Handler
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            const status = urlParams.get('status');
            if (status === 'success' || status === 'created') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'ดำเนินการสำเร็จ',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.onmouseenter = Swal.stopTimer;
                        toast.onmouseleave = Swal.resumeTimer;
                    }
                });
            }
        }
    });
</script>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleMobileMenu()"></div>

<!-- AI Chat Drawer -->
<div id="ai-chat-drawer" class="ai-drawer">
    <div class="ai-drawer-header">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="ai-avatar-m"><i data-lucide="bot"></i></div>
            <div>
                <div style="font-weight: 700; font-size: 1rem;">UDRU AI Chatbot</div>
                <div style="font-size: 0.75rem; color: #10b981;">● Online</div>
            </div>
        </div>
        <button onclick="closeAIDrawer()" style="background:none; border:none; color: white; cursor:pointer;">
            <i data-lucide="x"></i>
        </button>
    </div>
    <div class="ai-drawer-body" id="ai-chat-messages">
        <div class="ai-msg ai-msg-bot">สวัสดีครับ! ผมเป็นผู้ช่วยอัจฉริยะ UDRU วันนี้มีอะไรให้ผมช่วยสืบค้น
            หรือสงสัยเรื่องไหนสอบถามได้ทันทีครับ</div>
    </div>
    <div class="ai-drawer-footer">
        <form onsubmit="sendDrawerMessage(event)">
            <input type="text" id="ai-drawer-input" placeholder="พิมพ์คำถามของคุณ..." autocomplete="off">
            <button type="submit"><i data-lucide="send"></i></button>
        </form>
    </div>
</div>

<script>
    function sendDrawerMessage(e) {
        e.preventDefault();
        const input = document.getElementById('ai-drawer-input');
        const text = input.value.trim();
        if (!text) return;

        const body = document.getElementById('ai-chat-messages');

        // Add User Message
        const userDiv = document.createElement('div');
        userDiv.className = 'ai-msg ai-msg-user';
        userDiv.textContent = text;
        body.appendChild(userDiv);

        input.value = '';
        body.scrollTop = body.scrollHeight;

        // Redirect to full AI Assistant after a small delay to feel like a "handoff"
        setTimeout(() => {
            window.location.href = 'ai_assistant.php?q=' + encodeURIComponent(text);
        }, 1000);
    }
</script>

<!-- Notification Dropdown Component -->
<?php
function render_notification_component($unread_count = 0)
{
    global $sidebar_pdo;
    $previews = [];
    if (is_logged_in()) {
        $stmt = $sidebar_pdo->prepare("SELECT message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$_SESSION['user_id']]);
        $previews = $stmt->fetchAll();
    }
    ?>
    <div class="notification-bell-container">
        <div class="notification-bell-btn" onclick="toggleNotifications(event)">
            <i data-lucide="bell" style="width: 22px; height: 22px;"></i>
            <?php if ($unread_count > 0): ?>
                <span class="bell-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </div>

        <div class="notification-dropdown" id="notif-dropdown">
            <div class="notif-header">
                <h3>การแจ้งเตือน</h3>
                <?php if ($unread_count > 0): ?>
                    <span
                        style="font-size: 0.7rem; background: rgba(20, 184, 166, 0.1); color: var(--teal-primary); padding: 2px 8px; border-radius: 100px; font-weight: 700;">ใหม่
                        <?php echo $unread_count; ?></span>
                <?php endif; ?>
            </div>
            <div class="notif-body">
                <?php if (empty($previews)): ?>
                    <div class="notif-empty">
                        <i data-lucide="bell-off" style="width: 40px; height: 40px; margin-bottom: 0.75rem; opacity: 0.5;"></i>
                        <p style="font-size: 0.8125rem;">ไม่มีการแจ้งเตือนใหม่</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($previews as $n): ?>
                        <a href="notifications.php" class="notif-item">
                            <div class="notif-icon"><i data-lucide="message-circle" style="width: 16px;"></i></div>
                            <div class="notif-content">
                                <div class="notif-text"><?php echo htmlspecialchars($n['message']); ?></div>
                                <div class="notif-time"><?php echo date('d M H:i', strtotime($n['created_at'])); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="notif-footer">
                <a href="notifications.php">ดูการแจ้งเตือนทั้งหมด</a>
            </div>
        </div>
    </div>
    <?php
}

?>