<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$pdo = get_pdo();
$user_id = $_SESSION['user_id'];
$user_info = null;
$my_docs = [];
$stats = ['total' => 0, 'views' => 0, 'likes' => 0];

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch();

        // Use SQL for stats instead of fetching all documents
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(views), 0) as total_views FROM documents WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $doc_stats = $stmt->fetch();
        $stats['total'] = (int)$doc_stats['total'];
        $stats['views'] = (int)$doc_stats['total_views'];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_likes dl JOIN documents d ON dl.document_id = d.id WHERE d.user_id = ?");
        $stmt->execute([$user_id]);
        $stats['likes'] = $stmt->fetchColumn();

        // Paginated documents (10 per page)
        $per_page = 10;
        $current_page_num = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($current_page_num - 1) * $per_page;
        $total_pages = max(1, ceil($stats['total'] / $per_page));

        $stmt = $pdo->prepare("SELECT d.*, c.name as category_name FROM documents d LEFT JOIN categories c ON d.category_id = c.id WHERE d.user_id = ? ORDER BY d.created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$user_id, $per_page, $offset]);
        $my_docs = $stmt->fetchAll();

        // Fetch Certificates
        $stmt = $pdo->prepare("SELECT c.*, t.title as course_title FROM certificates c JOIN trainings t ON c.course_id = t.id WHERE c.user_id = ? ORDER BY c.issued_at DESC");
        $stmt->execute([$user_id]);
        $certificates = $stmt->fetchAll();

        // Handle Avatar Upload (if posted from this page or shared with experts_create)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            verify_csrf_token($_POST['csrf_token'] ?? '');
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            // Security: Validate extension and MIME type
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $fileTmpPath);
            finfo_close($finfo);

            $max_avatar_size = 5 * 1024 * 1024; // 5 MB

            if (!in_array($fileExtension, $allowed_extensions) || !in_array($mime_type, $allowed_mime_types)) {
                error_log("Avatar upload rejected: invalid type ($fileExtension / $mime_type) by user $user_id");
            } elseif ($_FILES['avatar']['size'] > $max_avatar_size) {
                error_log("Avatar upload rejected: file too large by user $user_id");
            } else {
                $newFileName = 'profile_' . uniqid() . '.' . $fileExtension;
                $uploadFileDir = './uploads/avatars/';

                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                $dest_path = $uploadFileDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$newFileName, $user_id]);
                    log_activity('avatar_upload', 'user', "New avatar: $newFileName");
                    $_SESSION['avatar'] = $newFileName;

                    // Refresh $user_info
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_info = $stmt->fetch();
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Profile page error: " . $e->getMessage());
    }
}

if (!$user_info) {
    header("Location: index.php");
    exit;
}

$type_labels = ['document' => 'เอกสาร', 'wiki' => 'Wiki', 'qa' => 'Q&A'];
?>
<?php
$page_title = 'โปรไฟล์ของฉัน | UDRU Wisdom';
$extra_css = <<<'HTML'
    <style>
        .profile-card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            text-align: center;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 30px;
            background: var(--teal-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 auto 1.5rem;
            box-shadow: 0 0 0 6px hsl(var(--primary) / 0.1);
        }

        .xp-card {
            background: hsl(var(--primary) / 0.1);
            color: var(--teal-primary);
            padding: 1.5rem;
            border-radius: 1rem;
            text-align: left;
            margin: 2rem 0;
        }

        /* Responsive Layouts and Premium Touches */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .profile-stat-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .profile-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            border-color: var(--teal-primary);
        }

        .profile-main-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2.5rem;
        }

        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        @media (max-width: 1024px) {
            .profile-main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin-bottom: 2rem;
            }
            .profile-card {
                padding: 2rem 1.5rem;
            }
            .avatar-edit-container {
                width: 100px;
                height: 100px;
            }
            .avatar-preview-wrapper {
                font-size: 2.5rem;
                border-radius: 28px;
            }
        }
    </style>
HTML;
require_once 'includes/head.php';
?>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>โปรไฟล์ของฉัน</h2>
                    <p>จัดการข้อมูลส่วนตัวและดูผลงานที่คุณได้แบ่งปันให้กับชุมชน</p>
                </div>
                <div class="header-actions">
                    <a href="create.php" class="btn-primary"><i data-lucide="plus"></i>สร้างเนื้อหาใหม่</a>
                </div>
            </header>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="profile-stat-card">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span
                            style="font-size: 0.8125rem; font-weight: 600; color: hsl(var(--muted-foreground));">ผลงานของฉัน</span>
                        <div style="color: var(--teal-primary); opacity: 0.8;"><i data-lucide="file-text"></i></div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800;"><?php echo $stats['total']; ?></div>
                </div>
                <div class="profile-stat-card">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span
                            style="font-size: 0.8125rem; font-weight: 600; color: hsl(var(--muted-foreground));">ยอดเข้าชมรวม</span>
                        <div style="color: var(--teal-primary); opacity: 0.8;"><i data-lucide="eye"></i></div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800;"><?php echo $stats['views']; ?></div>
                </div>
                <div class="profile-stat-card">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span
                            style="font-size: 0.8125rem; font-weight: 600; color: hsl(var(--muted-foreground));">ยอดถูกใจ</span>
                        <div style="color: hsl(339 90% 50%); opacity: 0.8;"><i data-lucide="heart"></i></div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800;"><?php echo $stats['likes']; ?></div>
                </div>
            </div>

            <div class="profile-main-grid">
                <!-- Profile Sidebar -->
                <div class="profile-card">
                    <form action="profile.php" method="POST" enctype="multipart/form-data" id="avatar-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="avatar-edit-container">
                            <?php
                            $avatar_url = !empty($user_info['avatar']) ? 'uploads/avatars/' . $user_info['avatar'] : '';
                            $has_avatar = !empty($avatar_url) && file_exists(__DIR__ . '/' . $avatar_url);
                            $initials = mb_strtoupper(mb_substr($user_info['username'], 0, 1, 'UTF-8'), 'UTF-8');
                            ?>
                            <div class="avatar-preview-wrapper" id="avatar-preview"
                                style="<?php echo $has_avatar ? "background-image: url('$avatar_url');" : ""; ?>">
                                <?php if (!$has_avatar): ?>
                                    <span id="avatar-initials"><?php echo $initials; ?></span>
                                <?php endif; ?>
                            </div>
                            <label for="avatar-input" class="avatar-edit-btn">
                                <i data-lucide="camera" style="width: 20px; height: 20px;"></i>
                            </label>
                            <input type="file" name="avatar" id="avatar-input" class="hidden-file-input"
                                accept="image/*" onchange="submitAvatar()">
                        </div>
                    </form>

                    <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem;">
                        <?php echo htmlspecialchars($user_info['full_name']); ?>
                    </h3>
                    <p style="color: hsl(var(--muted-foreground)); font-size: 0.9375rem; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($user_info['specialty'] ?? 'ผู้ใช้งานทั่วไป'); ?>
                    </p>
                    <p style="color: hsl(var(--muted-foreground)); font-size: 0.8125rem;">
                        <?php echo htmlspecialchars($user_info['department'] ?? 'ยังไม่ระบุหน่วยงาน'); ?>
                    </p>

                    <div class="xp-card">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <i data-lucide="award" style="width: 20px;"></i>
                            <span style="font-weight: 700;">คะแนนสะสม (XP)</span>
                        </div>
                        <div style="font-size: 2.5rem; font-weight: 900;"><?php echo $user_info['points'] ?? 0; ?></div>
                        <p style="font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.8;">
                            สร้างผลงานเพื่อรับคะแนนเพิ่มเติม</p>
                    </div>

                    <div style="text-align: left; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                        <h5 style="font-weight: 700; font-size: 0.875rem; margin-bottom: 0.75rem;">เกี่ยวกับฉัน</h5>
                        <p style="font-size: 0.875rem; color: hsl(var(--muted-foreground)); line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($user_info['bio'] ?? 'ยังไม่มีคำแนะนำตัว คลิกปุ่มด้านล่างเพื่อเพิ่มข้อมูล...')); ?>
                        </p>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <a href="experts_create.php" class="btn-primary"
                            style="justify-content: center;">แก้ไขโปรไฟล์</a>
                        <a href="settings.php" class="btn-primary"
                            style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); justify-content: center;">ตั้งค่าบัญชี</a>
                    </div>
                </div>

                <!-- My Works Section -->
                <div>
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 800;">ผลงานของฉัน (<?php echo $stats['total']; ?>)
                        </h3>
                        <a href="create.php"
                            style="font-size: 0.875rem; color: var(--teal-primary); font-weight: 600; text-decoration: none;">+
                            สร้างใหม่</a>
                    </div>

                    <?php if (empty($my_docs)): ?>
                        <div
                            style="background: white; border-radius: 1.5rem; border: 1px solid var(--border-color); text-align: center; padding: 5rem 2rem;">
                            <div
                                style="width: 80px; height: 80px; background: hsl(var(--muted)/0.5); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: hsl(var(--muted-foreground));">
                                <i data-lucide="file-plus" style="width: 40px; height: 40px;"></i>
                            </div>
                            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem;">
                                คุณยังไม่ได้สร้างเนื้อหาใดๆ</h3>
                            <p
                                style="color: hsl(var(--muted-foreground)); margin-bottom: 2rem; max-width: 400px; margin-left: auto; margin-right: auto;">
                                เริ่มต้นแบ่งปันความรู้แรกของคุณวันนี้เพื่อรับแต้ม XP และช่วยเหลือเพื่อนบุคลากร!</p>
                            <a href="create.php" class="btn-primary" style="display: inline-flex;">เริ่มสร้างเนื้อหา</a>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($my_docs as $doc): ?>
                                <div class="card-knowledge" style="padding: 1.5rem;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                        <div style="display: flex; gap: 0.5rem;">
                                            <span class="tag-badge"
                                                style="background: hsl(var(--primary)/0.1); color: var(--teal-primary);"><?php echo $type_labels[$doc['type']] ?? 'เอกสาร'; ?></span>
                                            <span
                                                class="tag-badge"><?php echo htmlspecialchars($doc['category_name'] ?? 'ไม่ระบุ'); ?></span>
                                        </div>
                                        <span
                                            style="font-size: 0.75rem; color: hsl(var(--muted-foreground));"><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></span>
                                    </div>
                                    <h4 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.75rem;">
                                        <a href="view.php?id=<?php echo $doc['id']; ?>"
                                            style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($doc['title']); ?></a>
                                    </h4>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div
                                            style="display: flex; gap: 1rem; font-size: 0.8125rem; color: hsl(var(--muted-foreground));">
                                            <span><i data-lucide="eye"
                                                    style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i><?php echo $doc['views']; ?>
                                                views</span>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <a href="edit.php?id=<?php echo $doc['id']; ?>"
                                                style="padding: 0.375rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; font-size: 0.75rem; font-weight: 600; text-decoration: none; color: hsl(var(--foreground)); display: inline-flex; align-items: center; gap: 0.25rem;"><i
                                                    data-lucide="edit-2" style="width: 12px; height: 12px;"></i>แก้ไข</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <!-- Pagination -->
                        <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem;">
                            <?php if ($current_page_num > 1): ?>
                                <a href="?page=<?php echo $current_page_num - 1; ?>" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 0.5rem; text-decoration: none; color: hsl(var(--foreground)); font-size: 0.875rem; font-weight: 600;">← ก่อนหน้า</a>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <?php if ($p == $current_page_num): ?>
                                    <span style="padding: 0.5rem 0.875rem; background: var(--teal-primary); color: white; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 700;"><?php echo $p; ?></span>
                                <?php elseif ($p <= 2 || $p >= $total_pages - 1 || abs($p - $current_page_num) <= 1): ?>
                                    <a href="?page=<?php echo $p; ?>" style="padding: 0.5rem 0.875rem; border: 1px solid var(--border-color); border-radius: 0.5rem; text-decoration: none; color: hsl(var(--foreground)); font-size: 0.875rem;"><?php echo $p; ?></a>
                                <?php elseif ($p == 3 || $p == $total_pages - 2): ?>
                                    <span style="color: hsl(var(--muted-foreground));">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($current_page_num < $total_pages): ?>
                                <a href="?page=<?php echo $current_page_num + 1; ?>" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 0.5rem; text-decoration: none; color: hsl(var(--foreground)); font-size: 0.875rem; font-weight: 600;">ถัดไป →</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Certificates Section -->
                    <div style="margin-top: 3rem; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 800;">ใบประกาศนียบัตรของฉัน
                            (<?php echo count($certificates); ?>)</h3>
                    </div>

                    <?php if (empty($certificates)): ?>
                        <div
                            style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 2rem; text-align: center; color: hsl(var(--muted-foreground));">
                            คุณยังไม่มีใบประกาศนียบัตร เรียนหลักสูตรและสอบให้ผ่านเพื่อรับใบประกาศ!
                        </div>
                    <?php else: ?>
                        <div class="certificates-grid">
                            <?php foreach ($certificates as $cert): ?>
                                <div
                                    style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
                                    <div
                                        style="width: 48px; height: 48px; background: hsl(45 93% 47% / 0.1); color: hsl(45 93% 47%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i data-lucide="award"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 700; font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($cert['course_title']); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: hsl(var(--muted-foreground)); margin-top: 2px;">
                                            รหัส: <?php echo $cert['certificate_code']; ?></div>
                                    </div>
                                    <a href="certificate.php?id=<?php echo $cert['id']; ?>" class="btn-sm"
                                        style="padding: 0.25rem 0.5rem; text-decoration: none; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.75rem;">ดูไฟล์</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
<?php
$extra_js = <<<'HTML'
    <script>
        function submitAvatar() {
            const input = document.getElementById('avatar-input');
            if (input.files && input.files[0]) {
                // Show preview immediately for better UX
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('avatar-preview').style.backgroundImage = `url(${e.target.result})`;
                    const initials = document.getElementById('avatar-initials');
                    if (initials) initials.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);

                // Submit form
                setTimeout(() => {
                    document.getElementById('avatar-form').submit();
                }, 500);
            }
        }
    </script>
HTML;
require_once 'includes/footer.php';
?>