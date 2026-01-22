<?php
// includes/sidebar.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
$sidebar_pdo = get_pdo();
$doc_count = $sidebar_pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="main-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i data-lucide="book-open"></i></div>
        <div class="brand-info">
            <h1>KM Portal</h1>
            <span>UDRU HUB</span>
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
    </nav>

    <nav class="nav-group">
        <div class="nav-label">ขุมปัญญา UDRU</div>
        <a href="experts.php"
            class="nav-link <?php echo ($current_page == 'experts.php' || $current_page == 'experts_create.php') ? 'active' : ''; ?>">
            <i data-lucide="users"></i>
            <span>รายชื่อผู้เชี่ยวชาญ</span>
        </a>
        <a href="cop.php"
            class="nav-link <?php echo ($current_page == 'cop.php' || $current_page == 'cop_create.php') ? 'active' : ''; ?>">
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
    </nav>

    <div style="margin-top: auto; display: flex; flex-direction: column; gap: 0.5rem;">
        <?php if (is_logged_in()): ?>
            <a href="profile.php" class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <i data-lucide="user"></i>
                <span>โปรไฟล์ของฉัน</span>
            </a>
            <a href="logout.php" class="nav-link">
                <i data-lucide="log-out"></i>
                <span>ออกจากระบบ</span>
            </a>
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

<script>
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
</script>