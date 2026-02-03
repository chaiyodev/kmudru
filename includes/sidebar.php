<?php
// includes/sidebar.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

$sidebar_pdo = get_pdo();
$doc_count = $sidebar_pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$current_page = basename($_SERVER['PHP_SELF']);

// Track page visit for analytics
track_visitor($current_page);
?>
<aside class="sidebar" id="main-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i data-lucide="book-open"></i></div>
        <div class="brand-info">
            <h1>UDRU Wisdom</h1>
            <span>Knowledge Center</span>
        </div>
    </div>

    <nav class="nav-group">
        <div class="nav-label">เมนูหลัก</div>
        <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <i data-lucide="layout"></i>
            <span>หน้าหลัก</span>
        </a>
        <a href="browse.php" class="nav-link <?php echo $current_page == 'browse.php' ? 'active' : ''; ?>">
            <i data-lucide="search"></i>
            <span>คลังความรู้</span>
            <span
                class="nav-pill"><?php echo $sidebar_pdo->query("SELECT COUNT(*) FROM documents WHERE type='document'")->fetchColumn(); ?></span>
        </a>
        <a href="chat.php" class="nav-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>">
            <i data-lucide="message-square"></i>
            <span>กล่องข้อความ</span>
        </a>
        <a href="ai_assistant.php" class="nav-link <?php echo $current_page == 'ai_assistant.php' ? 'active' : ''; ?>">
            <i data-lucide="bot"></i>
            <span>AI Assistant</span>
        </a>
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
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin_dashboard.php"
                class="nav-link <?php echo ($current_page == 'admin_dashboard.php' || $current_page == 'admin_users.php') ? 'active' : ''; ?>">
                <i data-lucide="shield"></i>
                <span>ผู้ดูแลระบบ</span>
            </a>
        <?php endif; ?>
    </nav>

    <div style="margin-top: auto; display: flex; flex-direction: column; gap: 0.5rem; width: 100%;">
        <?php if (is_logged_in()): ?>
            <div class="user-profile-card">
                <div class="profile-main-info"
                    style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                    <div class="profile-avatar"
                        style="width: 32px; height: 32px; background: var(--teal-primary); border-radius: 8px; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; flex-shrink: 0;">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="profile-info" style="flex: 1; overflow: hidden;">
                        <div
                            style="font-size: 0.875rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: hsl(var(--muted-foreground)); text-transform: capitalize;">
                            <?php echo htmlspecialchars($_SESSION['role']); ?>
                        </div>
                    </div>
                </div>
                <div class="profile-actions" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <a href="profile.php" class="btn-sm"
                        style="text-align: center; background: white; border: 1px solid var(--border-color); border-radius: 6px; padding: 0.25rem; font-size: 0.75rem; color: hsl(var(--foreground)); text-decoration: none;">โปรไฟล์</a>
                    <a href="logout.php" class="btn-sm"
                        style="text-align: center; background: white; border: 1px solid var(--border-color); border-radius: 6px; padding: 0.25rem; font-size: 0.75rem; color: hsl(var(--foreground)); text-decoration: none;">ออก</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php" class="nav-link">
                <i data-lucide="log-in"></i>
                <span>เข้าสู่ระบบ</span>
            </a>
        <?php endif; ?>

        <!-- Sidebar Toggle Button -->
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i data-lucide="chevrons-left" id="toggle-icon"></i>
            <span>ย่อเมนู</span>
        </button>
    </div>
</aside>

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

        // Update icon
        if (sidebar.classList.contains('collapsed')) {
            icon.setAttribute('data-lucide', 'chevrons-right');
            localStorage.setItem('sidebarCollapsed', 'true');
        } else {
            icon.setAttribute('data-lucide', 'chevrons-left');
            localStorage.setItem('sidebarCollapsed', 'false');
        }
        lucide.createIcons();
    }

    // Restore sidebar state on page load
    document.addEventListener('DOMContentLoaded', function () {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
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
</script>

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

<style>
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