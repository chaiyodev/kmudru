<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

// --- ป้อนฟังก์ชันที่จำเป็นเพื่อป้องกัน Error (กรณีไม่มี functions.php) ---
if (!function_exists('e')) {
    function e($text) { return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('time_ago')) {
    function time_ago($timestamp) {
        $time_ago = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
        $cur_time = time();
        $time_elapsed = $cur_time - $time_ago;
        if ($time_elapsed < 60) return "เมื่อครู่นี้";
        if ($time_elapsed < 3600) return round($time_elapsed/60)." นาทีที่แล้ว";
        if ($time_elapsed < 86400) return round($time_elapsed/3600)." ชม. ที่แล้ว";
        return date('j M Y', $time_ago);
    }
}

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$tab = $_GET['tab'] ?? 'discussions';

if ($id === 0) { header("Location: cop.php"); exit; }

// ดึงข้อมูลกลุ่ม และ สถิติจริงของแต่ละ Tab เพื่อให้ตัวเลข (0) เปลี่ยนเป็นตัวเลขจริง
$stmt = $pdo->prepare("SELECT c.*, cat.name as category_name, 
                       (SELECT COUNT(*) FROM community_members WHERE community_id = c.id) as member_count,
                       (SELECT COUNT(*) FROM community_posts WHERE community_id = c.id) as posts_count,
                       (SELECT COUNT(*) FROM community_resources WHERE community_id = c.id) as resources_count,
                       (SELECT COUNT(*) FROM community_announcements WHERE community_id = c.id) as announcements_count
                       FROM communities c 
                       LEFT JOIN categories cat ON c.category_id = cat.id 
                       WHERE c.id = ?");
$stmt->execute([$id]);
$cop = $stmt->fetch();

if (!$cop) { header("Location: cop.php"); exit; }

$user_id = $_SESSION['user_id'] ?? null;
$is_member = false; $user_role = '';
if ($user_id) {
    $stmt = $pdo->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $membership = $stmt->fetch();
    if ($membership) { $is_member = true; $user_role = $membership['role']; }
}

// Handle สลับ Tab
$posts = []; $members = []; $resources = []; $announcements = [];

if ($tab === 'discussions') {
    $stmt = $pdo->prepare("SELECT p.*, u.full_name, u.avatar FROM community_posts p JOIN users u ON p.user_id = u.id WHERE p.community_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$id]);
    $posts = $stmt->fetchAll();
} elseif ($tab === 'announcements') {
    $stmt = $pdo->prepare("SELECT a.*, u.full_name FROM community_announcements a JOIN users u ON a.user_id = u.id WHERE a.community_id = ? ORDER BY a.created_at DESC");
    $stmt->execute([$id]);
    $announcements = $stmt->fetchAll();
} elseif ($tab === 'members') {
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, m.role, m.joined_at FROM community_members m JOIN users u ON m.user_id = u.id WHERE m.community_id = ? ORDER BY m.joined_at ASC");
    $stmt->execute([$id]);
    $members = $stmt->fetchAll();
} elseif ($tab === 'resources') {
    $stmt = $pdo->prepare("SELECT r.*, u.full_name FROM community_resources r JOIN users u ON r.user_id = u.id WHERE r.community_id = ? ORDER BY r.created_at DESC");
    $stmt->execute([$id]);
    $resources = $stmt->fetchAll();
}

// Handle Form Posts (Join/Add Post... etc)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'join') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO community_members (community_id, user_id, role) VALUES (?, ?, 'member')");
        $stmt->execute([$id, $user_id]);
        header("Location: cop_view.php?id=$id&status=joined"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo e($cop['name']); ?> | UDRU Wisdom
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/cop.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .cop-hero {
            background: <?php echo $cop['color_theme']; ?>;
            <?php if ($cop['cover_image']): ?>
                background-image: linear-gradient(to right, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.2)), url('<?php echo $cop['cover_image']; ?>');
                background-size: cover;
                background-position: center;
            <?php endif; ?>
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <!-- AI Semantic Search Bar (Always Visible) -->
            <div class="ai-search-bar animate-fade-in">
                <i data-lucide="sparkles" style="color: #6366f1;"></i>
                <input type="text" placeholder="ค้นหาด้วย AI (Semantic Search) เช่น 'แนวทาง EdPEx หมวด 4'..."
                    style="border: none; background: transparent; flex: 1; font-size: 1rem; outline: none; font-weight: 500;">
                <span class="ai-badge">AI Powered</span>
                <button class="btn-primary"
                    style="padding: 0.5rem 1.5rem; border-radius: 0.75rem; background: #6366f1;">ค้นหาอัจฉริยะ</button>
            </div>

            <!-- Hero Banner Redesigned -->
            <div class="cop-hero">
                <div class="cop-hero-flex">
                    <div class="cop-hero-content">
                        <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem;">
                            <span class="glass-badge"><i data-lucide="tag" style="width: 12px;"></i>
                                <?php echo e($cop['category_name'] ?: 'General'); ?>
                            </span>
                            <span class="glass-badge"><i
                                    data-lucide="<?php echo $cop['is_public'] ? 'globe' : 'lock'; ?>"
                                    style="width: 12px;"></i>
                                <?php echo $cop['is_public'] ? 'สาธารณะ' : 'ส่วนตัว'; ?>
                            </span>
                        </div>

                        <h1 style="font-size: 3rem; font-weight: 900; margin-bottom: 0.5rem; letter-spacing: -1px;">
                            <?php echo e($cop['name']); ?>
                        </h1>
                        <p style="font-size: 1.1rem; opacity: 0.9; line-height: 1.6; margin-bottom: 1.5rem;">
                            <?php echo e($cop['description']); ?>
                        </p>

                        <div style="display: flex; gap: 2rem; align-items: center; opacity: 0.8; font-size: 0.8125rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="users"
                                    style="width: 16px;"></i>
                                <strong>
                                    <?php echo $cop['member_count']; ?>
                                </strong> สมาชิก
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="calendar"
                                    style="width: 16px;"></i>
                                สร้างเมื่อ
                                <?php echo date('j/n/Y', strtotime($cop['created_at'])); ?>
                            </div>
                        </div>

                        <div class="member-actions">
                            <?php if (!is_logged_in()): ?>
                                <button type="button" class="btn-action primary" onclick="requireLoginPrompt('เข้าร่วมชุมชน CoP')">
                                    <i data-lucide="user-plus" style="width: 18px;"></i> เข้าร่วมชุมชน
                                </button>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <?php if ($is_member): ?>
                                        <input type="hidden" name="action" value="leave">
                                        <button type="submit" class="btn-action primary">
                                            <i data-lucide="check" style="width: 18px;"></i> เป็นสมาชิกแล้ว (Leave)
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="join">
                                        <button type="submit" class="btn-action primary">
                                            <i data-lucide="user-plus" style="width: 18px;"></i> เข้าร่วมชุมชน
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Side Widget in Banner (Fills empty space) -->
                    <div class="quick-stat-box animate-fade-in">
                        <div class="stat-item">
                            <span class="val"><?php echo $cop['posts_count']; ?></span>
                            <span class="lbl">กระทู้ทั้งหมด</span>
                        </div>
                        <div class="stat-item">
                            <span class="val"><?php echo $cop['resources_count']; ?></span>
                            <span class="lbl">การแชร์ทรัพยากร</span>
                        </div>
                        <div
                            style="grid-column: span 2; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem;">
                            <i data-lucide="shield-check" style="width: 14px; color: #4ade80;"></i> ชุมชนผ่านการรับรอง
                            KM UDRU
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation (Improved spacing and pills) -->
            <nav class="cop-tabs">
                <a href="?id=<?php echo $id; ?>&tab=discussions"
                    class="tab-btn <?php echo $tab == 'discussions' ? 'active' : ''; ?>">
                    <i data-lucide="message-square" style="width: 18px;"></i> การสนทนา
                    <span
                        style="font-size: 0.7rem; background: #f1f5f9; padding: 2px 6px; border-radius: 6px; margin-left: 0.5rem; color: #64748b;"><?php echo $cop['posts_count']; ?></span>
                </a>
                <a href="?id=<?php echo $id; ?>&tab=announcements"
                    class="tab-btn <?php echo $tab == 'announcements' ? 'active' : ''; ?>">
                    <i data-lucide="megaphone" style="width: 18px;"></i> ประกาศ
                    <span
                        style="font-size: 0.7rem; background: #f1f5f9; padding: 2px 6px; border-radius: 6px; margin-left: 0.5rem; color: #64748b;"><?php echo $cop['announcements_count']; ?></span>
                </a>
                <a href="?id=<?php echo $id; ?>&tab=resources"
                    class="tab-btn <?php echo $tab == 'resources' ? 'active' : ''; ?>">
                    <i data-lucide="library" style="width: 18px;"></i> ทรัพยากร
                    <span
                        style="font-size: 0.7rem; background: #f1f5f9; padding: 2px 6px; border-radius: 6px; margin-left: 0.5rem; color: #64748b;"><?php echo $cop['resources_count']; ?></span>
                </a>
                <a href="?id=<?php echo $id; ?>&tab=members"
                    class="tab-btn <?php echo $tab == 'members' ? 'active' : ''; ?>">
                    <i data-lucide="users-2" style="width: 18px;"></i> สมาชิก
                    <span
                        style="font-size: 0.7rem; background: #f1f5f9; padding: 2px 6px; border-radius: 6px; margin-left: 0.5rem; color: #64748b;"><?php echo $cop['member_count']; ?></span>
                </a>
            </nav>

            <!-- Tab Content -->
            <div class="tab-content">
                <?php if ($tab === 'discussions'): ?>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                        <div class="main-column">
                            <!-- โพสต์สนทนา -->
                            <div class="form-card" style="padding: 1.5rem;">
                                <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1rem;">แบ่งปันความรู้</h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_post">
                                    <textarea name="content" class="form-input" style="min-height: 80px; margin-bottom: 1rem;" placeholder="ร่วมแลกเปลี่ยนความรู้ในชุมชน..." required></textarea>
                                    <button type="submit" class="btn-primary" style="background:#0d9488;">ส่งโพสต์</button>
                                </form>
                            </div>
                            <div class="thread-list">
                                <?php foreach ($posts as $p): ?>
                                    <div class="card" style="background:white; padding:20px; border-radius:15px; margin-bottom:15px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                                        <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                                            <div style="width:36px; height:36px; background:#0d9488; color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;"><?php echo strtoupper(substr($p['full_name'],0,1)); ?></div>
                                            <div>
                                                <div style="font-weight:600; font-size:0.95rem;"><?php echo e($p['full_name']); ?></div>
                                                <div style="font-size:0.75rem; color:#94a3b8;"><?php echo time_ago($p['created_at']); ?></div>
                                            </div>
                                        </div>
                                        <p style="color:#475569; line-height:1.6;"><?php echo nl2br(e($p['content'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Sidebar กลับมาตรงนี้แล้วครับ (แบบเดิมที่สวยกว่า) -->
                        <div class="side-column">
                            <div class="form-card" style="background: linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%); border: 1px solid #ddd6fe;">
                                <h4 style="font-size: 0.875rem; font-weight: 700; color: #5b21b6; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i data-lucide="zap" style="width: 16px;"></i> แนะนำโดย AI
                                </h4>
                                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <div style="font-size: 0.8125rem; padding: 0.75rem; background: white; border-radius: 10px; border: 1px solid #ede9fe; cursor: pointer;">
                                        <div style="font-weight: 700; color: #1e1b4b;">สรุปเกณฑ์ EdPEx 2024</div>
                                        <div style="font-size: 0.7rem; color: #6d28d9; margin-top: 2px;">อิงจากความสนใจของคุณ</div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-card" style="padding: 1.5rem;">
                                <h4 style="font-size: 0.875rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; text-transform: uppercase;">ประกาศสำคัญ</h4>
                                <div style="padding: 1rem; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 0.75rem; color: #92400e; font-size: 0.875rem;">
                                    <div style="font-weight: 700; margin-bottom: 0.25rem;">ประชุมประจำเดือน</div>
                                    พบกันวันพุธหน้า เวลา 13.00 น. ผ่าน MS Teams ครับ
                                </div>
                            </div>
                            
                            <div class="form-card" style="padding: 1.5rem;">
                                <h4 style="font-size: 0.875rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; text-transform: uppercase;">สมาชิกที่ออนไลน์</h4>
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <?php 
                                    $online = get_online_members($id);
                                    foreach ($online as $om): ?>
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div class="avatar-circle" style="width: 30px; height: 30px; position: relative; background: #0d9488; color: white;">
                                                <div style="position: absolute; bottom: 0; right: 0; width: 8px; height: 8px; background: #22c55e; border: 2px solid white; border-radius: 50%;"></div>
                                                <?php echo strtoupper(substr($om['username'],0,1)); ?>
                                            </div>
                                            <span style="font-size: 0.875rem; font-weight: 600;"><?php echo e($om['full_name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($tab === 'announcements'): ?>
                    <!-- Tab ประกาศแบบใหม่ แต่ใช้ดีไซน์สีเหลืองที่สวยงามตามที่คุณชอบ -->
                    <div class="main-column" style="max-width: 900px; margin: 0 auto;">
                        <h3 style="margin-bottom: 1.5rem; color: #0d9488;">ประกาศและข่าวสารจากผู้ดูแล</h3>
                        <?php if (empty($announcements)): ?>
                            <div style="text-align: center; padding: 4rem; background: #f8fafc; border-radius: 20px;">ยังไม่มีประกาศในขณะนี้</div>
                        <?php else: ?>
                            <?php foreach ($announcements as $a): ?>
                                <div style="padding: 1.5rem; background: #fffbeb; border-left: 5px solid #f59e0b; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                                    <h4 style="color: #92400e; font-weight: 700; margin-bottom: 0.5rem;"><?php echo e($a['title']); ?></h4>
                                    <p style="color: #475569; line-height: 1.6;"><?php echo e($a['content']); ?></p>
                                    <div style="margin-top: 1rem; font-size: 0.8rem; color: #94a3b8;">
                                        โดย <?php echo e($a['full_name']); ?> • <?php echo time_ago($a['created_at']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                <?php elseif ($tab === 'members'): ?>
                    <div class="main-column">
                        <div class="form-card" style="padding: 2rem;">
                            <h3 style="margin-bottom: 1.5rem;"><i data-lucide="users" style="color:#0d9488;"></i> รายชื่อสมาชิกในกลุ่ม</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem;">
                                <?php foreach ($members as $m): ?>
                                    <div style="display:flex; align-items:center; gap:10px; padding:15px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                                        <div style="width:40px; height:40px; background:white; border:2px solid #0d9488; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#0d9488; font-weight:bold;">
                                            <?php echo strtoupper(substr($m['username']??'U',0,1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:600; font-size:0.9rem;"><?php echo e($m['full_name']); ?></div>
                                            <div style="font-size:0.7rem; color:#64748b;"><?php echo e($m['role']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($tab === 'resources'): ?>
                    <div class="form-card" style="padding: 2rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="color:#0d9488;">คลังทรัพยากร</h3>
                            <button class="btn-primary">+ อัปโหลด</button>
                        </div>
                        <?php if (empty($resources)): ?>
                            <div style="text-align: center; padding: 3rem; color: #64748b;">ยังไม่มีทรัพยากร</div>
                        <?php else: ?>
                            <div style="display: grid; gap: 1rem;">
                                <?php foreach ($resources as $r): ?>
                                    <div style="padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong><?php echo e($r['title']); ?></strong>
                                            <div style="font-size: 0.8rem; color: #94a3b8;">แชร์โดย <?php echo e($r['full_name']); ?></div>
                                        </div>
                                        <i data-lucide="download" style="cursor: pointer; color: #0d9488;"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Upload Modal -->
    <div id="uploadResourceModal" class="modal-overlay">
        <div class="modal-card">
            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="upload-cloud" style="color: var(--teal-primary);"></i> แชร์ทรัพยากรใหม่
            </h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="upload_resource">

                <div class="form-group margin-bottom-1">
                    <label class="form-label">ชื่อทรัพยากร</label>
                    <input type="text" name="title" class="form-input"
                        placeholder="เช่น คู่มือการใช้งาน, แบบฟอร์มขออนุมัติ" required>
                </div>

                <div class="form-group margin-bottom-2">
                    <label class="form-label">เลือกไฟล์</label>
                    <input type="file" name="file" class="form-input" style="padding: 0.5rem;" required>
                    <p style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.5rem;">รองรับ PDF, Docx, Image
                        และไฟล์สำนักงานทั่วไป</p>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn-primary" style="flex: 1;">ยืนยันการแชร์</button>
                    <button type="button" onclick="toggleModal('uploadResourceModal', false)" class="btn-primary"
                        style="background: white; border: 1px solid #e2e8f0; color: #64748b; width: auto;">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        lucide.createIcons();

        function toggleModal(id, show) {
            const modal = document.getElementById(id);
            if (modal) modal.style.display = show ? 'flex' : 'none';
        }

        // Search logic for invitations (Simplified mockup)
        const searchInput = document.getElementById('member_search');
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                const results = document.getElementById('search_results');
                if (e.target.value.length > 2) {
                    results.innerHTML = `
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: white; border: 1px solid #f1f5f9; border-radius: 0.75rem;">
                            <span style="font-size: 0.875rem; font-weight: 600;">ผลการค้นหา...</span>
                            <form method="POST">
                                <input type="hidden" name="action" value="invite_member">
                                <input type="hidden" name="user_id" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button class="btn-sm" style="background: var(--teal-primary); color: white; border: none; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem;">เชิญ</button>
                            </form>
                        </div>
                    `;
                } else {
                    results.innerHTML = '<div style="font-size: 0.75rem; color: #94a3b8; text-align: center; padding: 1rem;">พิมพ์เพื่อค้นหาสมาชิก...</div>';
                }
            });
        }

        function handleMembership(type) {
            Swal.fire({
                title: type === 'join' ? 'ต้องการเข้าร่วมชุมชน?' : 'ต้องการออกจากชุมชน?',
                text: 'คุณสามารถจัดการสถานะสมาชิกได้ตลอดเวลา',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--teal-primary)',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('สำเร็จ!', 'อัปเดตสถานะสมาชิกเรียบร้อยแล้ว', 'success');
                }
            });
        }

        // New Interactive Functions
        function updateFilePreview(input) {
            const area = document.getElementById('file-preview-area');
            const name = document.getElementById('file-preview-name');
            if (input.files && input.files[0]) {
                name.textContent = input.files[0].name;
                area.style.display = 'block';
            } else {
                area.style.display = 'none';
            }
        }

        function toggleLike(el) {
            const icon = el.querySelector('i');
            if (el.style.color === 'rgb(244, 63, 94)') { // #f43f5e
                el.style.color = '#94a3b8';
                icon.style.fill = 'none';
            } else {
                el.style.color = '#f43f5e';
                icon.style.fill = '#f43f5e';
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'ขอบคุณที่ถูกใจ!',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }

        function toggleReply(id) {
            const box = document.getElementById('reply-box-' + id);
            box.style.display = box.style.display === 'none' ? 'block' : 'none';
        }

        function recommendSummary() {
            Swal.fire({
                title: 'บทสรุปเกณฑ์ EdPEx 2024 (โดย AI)',
                html: `<div style="text-align: left; font-size: 0.9rem;">
                    <p><b>EdPEx 2024</b> มีการปรับปรุงที่น่าสนใจในหมวดที่ 2 เรื่องการวางแผนกลยุทธ์ และหมวดที่ 6 เรื่องการปฏิบัติการ เพื่อให้สอดรับกับความเปลี่ยนแปลงที่รวดเร็ว (Agility)...</p>
                    <ul style="margin-top: 1rem;">
                        <li>เน้นการบูรณาการระบบนวัตกรรม</li>
                        <li>เพิ่มความสำคัญของ Digital Transformation</li>
                    </ul>
                </div>`,
                icon: 'info',
                confirmButtonText: 'รับทราบความรู้'
            });
        }

        function recommendExpert() {
            Swal.fire({
                title: 'ติดต่อผู้เชี่ยวชาญ',
                text: 'คุณต้องการส่งข้อความสอบถาม ผศ.ดร. มานะ เกี่ยวกับงานประกันคุณภาพหรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ส่งข้อความ',
                cancelButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('สำเร็จ!', 'ระบบส่งข้อความให้ผู้เชี่ยวชาญเรียบร้อยแล้ว', 'success');
                }
            });
        }
    </script>
</body>

</html>