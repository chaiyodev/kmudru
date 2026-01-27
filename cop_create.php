<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';

$pdo = get_pdo();
$categories = [];
if ($pdo) {
    $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $name = $_POST['name'];
    $description = $_POST['description'];
    $category_id = (int) $_POST['category_id'];
    $color_theme = $_POST['color_theme'];
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $user_id = $_SESSION['user_id'];

    // Handle Cover Image upload
    $cover_image = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/cop/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);
        $filename = uniqid() . '_' . basename($_FILES['cover_image']['name']);
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDir . $filename)) {
            $cover_image = $uploadDir . $filename;
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO communities (name, description, category_id, color_theme, cover_image, is_public) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $category_id, $color_theme, $cover_image, $is_public]);
        $community_id = $pdo->lastInsertId();

        // Add creator as leader
        $stmt = $pdo->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'leader')");
        $stmt->execute([$community_id, $user_id]);

        $pdo->commit();
        $message = "สร้างชุมชน CoP ใหม่เรียบร้อยแล้ว!";
        header("Location: cop.php?status=success");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างชุมชนใหม่ | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .creator-split {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 2.5rem;
        }

        .creation-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .preview-sticky {
            position: sticky;
            top: 2rem;
        }

        .preview-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        }

        .preview-header {
            height: 100px;
            background: linear-gradient(135deg, #14b8a6, #0d9488);
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            color: white;
        }

        .preview-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
        }

        .color-picker {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .color-option {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid transparent;
            transition: 0.2s;
            position: relative;
        }

        .color-option.active {
            border-color: #0f172a;
            transform: scale(1.1);
        }

        .color-option input {
            opacity: 0;
            position: absolute;
            inset: 0;
            cursor: pointer;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
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
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 34px;
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

        .feature-tag {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }

        .feature-tag i {
            color: var(--teal-primary);
            width: 18px;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <a href="cop.php" class="back-nav"
                    style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; color: #64748b; margin-bottom: 0.5rem; font-size: 0.875rem;">
                    <i data-lucide="arrow-left" style="width: 16px;"></i> กลับหน้าชุมชน
                </a>
                <div class="page-title">
                    <h2>สร้างพื้นที่แห่งการเรียนรู้ (CoP)</h2>
                    <p>เพราะความรู้ยิ่งแชร์ยิ่งงอกงาม เริ่มต้นสร้างชุมชนของคุณได้ง่ายๆ ที่นี่</p>
                </div>
            </header>

            <div class="creator-split">
                <!-- Form Side -->
                <div class="form-side">
                    <form action="cop_create.php" method="POST" enctype="multipart/form-data" class="creation-card">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 2rem;">
                            <div
                                style="width: 48px; height: 48px; background: #f5f3ff; color: #8b5cf6; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="users"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 1.125rem; font-weight: 700;">ข้อมูลพื้นฐาน</h3>
                                <p style="font-size: 0.875rem; color: #64748b;">ระบุชื่อและวัตถุประสงค์ของชุมชน</p>
                            </div>
                        </div>

                        <div class="form-group margin-bottom-2">
                            <label class="form-label">ชื่อชุมชน *</label>
                            <input type="text" name="name" id="cop_name" class="form-input"
                                placeholder="ระบุชื่อชุมชนที่เข้าใจง่าย เช่น ชมรมพัฒนา Software" required>
                        </div>

                        <div class="form-group margin-bottom-2">
                            <label class="form-label">คำอธิบาย</label>
                            <textarea name="description" id="cop_desc" class="form-input" rows="4"
                                placeholder="อธิบายวัตถุประสงค์และกิจกรรมของชุมชนเพื่อให้เพื่อนสมาชิกตัดสินใจเข้าร่วม"></textarea>
                        </div>

                        <div class="form-group margin-bottom-2">
                            <label class="form-label">หมวดหมู่</label>
                            <select name="category_id" class="form-input">
                                <option value="">เลือกหมวดหมู่...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group margin-bottom-2">
                            <label class="form-label">สีธีมชุมชน</label>
                            <div class="color-picker">
                                <?php
                                $colors = ['#14b8a6', '#8b5cf6', '#f59e0b', '#10b981', '#a855f7', '#ec4899'];
                                foreach ($colors as $idx => $color): ?>
                                    <label class="color-option <?php echo $idx == 0 ? 'active' : ''; ?>"
                                        style="background: <?php echo $color; ?>">
                                        <input type="radio" name="color_theme" value="<?php echo $color; ?>" <?php echo $idx == 0 ? 'checked' : ''; ?> onchange="updateTheme(this)">
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group margin-bottom-2">
                            <label class="form-label">รูปหน้าปก (Cover Image)</label>
                            <input type="file" name="cover_image" class="form-input" accept="image/*"
                                onchange="previewCover(this)">
                        </div>

                        <div class="toggle-switch margin-bottom-2">
                            <div style="flex: 1;">
                                <div
                                    style="font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i data-lucide="globe" style="width: 16px;"></i> ชุมชนสาธารณะ
                                </div>
                                <p style="font-size: 0.75rem; color: #64748b;">ทุกคนสามารถค้นหา ดูเนื้อหา
                                    และขอเข้าร่วมได้</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="is_public" checked>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div style="display: flex; gap: 1rem; padding-top: 1rem;">
                            <button type="submit" class="btn-primary" style="padding: 1rem 3rem;">
                                <i data-lucide="save"></i> สร้างชุมชน
                            </button>
                            <a href="cop.php" class="btn-primary"
                                style="background: white; border: 1px solid #e2e8f0; color: #64748b; width: auto;">ยกเลิก</a>
                        </div>
                    </form>
                </div>

                <!-- Preview Side -->
                <div class="preview-side">
                    <div class="preview-sticky">
                        <p
                            style="font-size: 0.875rem; font-weight: 700; color: #64748b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="eye" style="width: 16px;"></i> ตัวอย่างชุมชน
                        </p>
                        <div class="preview-card">
                            <div id="prev_banner" class="preview-header">
                                <div class="preview-icon">🤝</div>
                                <div>
                                    <h4 id="prev_name" style="font-weight: 800; font-size: 1.1rem; line-height: 1;">
                                        ชื่อชุมชน</h4>
                                    <span
                                        style="font-size: 0.7rem; opacity: 0.8; display: flex; align-items: center; gap: 0.25rem; margin-top: 0.25rem;">
                                        <i data-lucide="globe" style="width: 10px;"></i> สาธารณะ
                                    </span>
                                </div>
                            </div>
                            <p id="prev_desc" style="font-size: 0.875rem; color: #64748b; line-height: 1.6;">
                                คำอธิบายชุมชนจะปรากฏที่นี่...</p>
                        </div>

                        <div style="margin-top: 2rem;">
                            <p style="font-size: 0.875rem; font-weight: 700; color: #64748b; margin-bottom: 1rem;">✨
                                ฟีเจอร์ที่จะได้รับ</p>
                            <div class="feature-tag"><i data-lucide="message-square"></i>
                                <div><strong>กระทู้สนทนา</strong><br><span
                                        style="font-size: 0.75rem;">เปิดให้สมาชิกสร้างกระทู้และตอบกลับ</span></div>
                            </div>
                            <div class="feature-tag"><i data-lucide="calendar"></i>
                                <div><strong>กิจกรรม</strong><br><span
                                        style="font-size: 0.75rem;">สร้างและจัดการกิจกรรมร่วมของชุมชน</span></div>
                            </div>
                            <div class="feature-tag"><i data-lucide="file-text"></i>
                                <div><strong>แหล่งข้อมูล</strong><br><span
                                        style="font-size: 0.75rem;">แชร์เอกสารและลิงก์ที่เป็นประโยชน์</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        // Live Preview Logic
        const nameInput = document.getElementById('cop_name');
        const descInput = document.getElementById('cop_desc');
        const prevName = document.getElementById('prev_name');
        const prevDesc = document.getElementById('prev_desc');
        const prevBanner = document.getElementById('prev_banner');

        nameInput.addEventListener('input', (e) => {
            prevName.innerText = e.target.value || 'ชื่อชุมชน';
        });

        descInput.addEventListener('input', (e) => {
            prevDesc.innerText = e.target.value || 'คำอธิบายชุมชนจะปรากฏที่นี่...';
        });

        function updateTheme(radio) {
            document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('active'));
            radio.parentElement.classList.add('active');
            prevBanner.style.background = radio.value;
        }

        function previewCover(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    prevBanner.style.backgroundImage = `url(${e.target.result})`;
                    prevBanner.style.backgroundSize = 'cover';
                    prevBanner.style.backgroundPosition = 'center';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>