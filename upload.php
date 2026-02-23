<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
require_login();
$categories = [];
if ($pdo) {
    $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $title = $_POST['title'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    $tags = $_POST['tags'];
    $user_id = $_SESSION['user_id'];

    // --- SECURITY: File Upload Validation ---
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg', 'gif'];
    $allowed_mimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'image/png',
        'image/jpeg',
        'image/gif'
    ];
    $max_file_size = 10 * 1024 * 1024; // 10 MB

    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $original_name = basename($_FILES['file']['name']);
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $file_mime = $_FILES['file']['type'];
        $file_size = $_FILES['file']['size'];

        // Validate Extension
        if (!in_array($file_ext, $allowed_extensions)) {
            $error = "ประเภทไฟล์ไม่อนุญาต (.{$file_ext}) — รองรับเฉพาะ: " . implode(', ', $allowed_extensions);
        }
        // Validate MIME type
        else if (!in_array($file_mime, $allowed_mimes)) {
            $error = "ประเภท MIME ของไฟล์ไม่ถูกต้อง ({$file_mime})";
        }
        // Validate File Size
        else if ($file_size > $max_file_size) {
            $error = "ขนาดไฟล์เกินกำหนด (" . round($file_size / 1024 / 1024, 1) . " MB) — จำกัดสูงสุด 10 MB";
        } else {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0755, true);

            // Secure filename: use unique hash + original extension only
            $file_name = bin2hex(random_bytes(16)) . '.' . $file_ext;
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
                try {
                    $pdo->beginTransaction();

                    // Insert Document
                    $stmt = $pdo->prepare("INSERT INTO documents (title, content, category_id, user_id, type, tags) VALUES (?, ?, ?, ?, 'document', ?)");
                    $stmt->execute([$title, $description, $category_id, $user_id, $tags]);
                    $doc_id = $pdo->lastInsertId();

                    // Insert Attachment
                    $stmt = $pdo->prepare("INSERT INTO attachments (document_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$doc_id, $original_name, $target_file, $file_mime, $file_size]);

                    $pdo->commit();
                    $message = "อัปโหลดเอกสารเรียบร้อยแล้ว!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
                }
            } else {
                $error = "ไม่สามารถย้ายไฟล์ที่อัปโหลดได้";
            }
        }
    } else {
        $error = "กรุณาเลือกไฟล์ที่ต้องการอัปโหลด";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปโหลดเอกสาร | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 1rem;
            padding: 3rem;
            text-align: center;
            background: hsl(var(--muted) / 0.5);
            transition: var(--transition-base);
            cursor: pointer;
            margin-bottom: 2rem;
        }

        .upload-zone:hover,
        .upload-zone.drag-active {
            border-color: var(--teal-primary);
            background: hsl(var(--primary) / 0.05);
        }

        .upload-icon {
            width: 48px;
            height: 48px;
            color: var(--teal-primary);
            margin: 0 auto 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: hsl(var(--foreground));
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            font-family: inherit;
            font-size: 0.875rem;
            outline: none;
            transition: var(--transition-base);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--teal-primary);
            box-shadow: 0 0 0 2px hsl(var(--primary) / 0.1);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .info-card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .info-item {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.8125rem;
            color: hsl(var(--muted-foreground));
        }

        .info-item i {
            color: var(--teal-primary);
            flex-shrink: 0;
            margin-top: 2px;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Viewport -->
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>อัปโหลดเอกสารใหม่</h2>
                    <p>แบ่งปันองค์ความรู้ของคุณให้กับบุคลากร UDRU</p>
                </div>
            </header>

            <?php if ($message): ?>
                <div
                    style="background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid hsl(142 76% 36% / 0.2);">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div
                    style="background: hsl(0 84% 60% / 0.1); color: hsl(0 84% 60%); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid hsl(0 84% 60% / 0.2);">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2.5rem;">
                <form action="upload.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div id="drop-zone" class="upload-zone">
                        <div class="upload-icon"><i data-lucide="upload-cloud"></i></div>
                        <h3 style="font-weight: 700; margin-bottom: 0.5rem;">คลิกเพื่อเลือกไฟล์ หรือลากมาวางที่นี่</h3>
                        <p style="font-size: 0.8125rem; color: hsl(var(--muted-foreground));">รองรับไฟล์ PDF, Word,
                            Excel, PPT และไฟล์รูปภาพ (สูงสุด 10MB)</p>
                        <input type="file" name="file" id="file-input" style="display: none;">
                    </div>

                    <div
                        style="background: white; border-radius: 0.75rem; border: 1px solid var(--border-color); padding: 2rem;">
                        <div class="form-group">
                            <label class="form-label">ชื่อเอกสาร</label>
                            <input type="text" name="title" class="form-input"
                                placeholder="ระบุชื่อหัวข้อเอกสารที่ชัดเจน" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">หมวดหมู่</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">เลือกหมวดหมู่...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">คำอธิบาย</label>
                            <textarea name="description" class="form-textarea"
                                placeholder="สรุปเนื้อหาสำคัญของเอกสารนี้..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">แท็ก (Tags)</label>
                            <input type="text" name="tags" class="form-input"
                                placeholder="เช่น คู่มือ, IT, วิจัย (คั่นด้วยคอมม่า)">
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn-primary"
                                style="flex: 1; justify-content: center;">ยืนยันการอัปโหลด</button>
                            <a href="index.php" class="btn-primary"
                                style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); flex: 1; justify-content: center;">ยกเลิก</a>
                        </div>
                    </div>
                </form>

                <aside>
                    <div class="info-card">
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1.5rem;">ข้อแนะนำการอัปโหลด</h3>
                        <div class="info-item">
                            <i data-lucide="check-circle-2"></i>
                            <p>ตั้งชื่อเอกสารให้สื่อถึงเนื้อหาภายในเพื่อให้อาจารย์และเจ้าหน้าที่ท่านอื่นค้นหาได้ง่าย</p>
                        </div>
                        <div class="info-item">
                            <i data-lucide="check-circle-2"></i>
                            <p>เลือกหมวดหมู่ให้ตรงกับเนื้อหามากที่สุดเพื่อให้ระบบคัดกรองข้อมูลได้อย่างถูกต้อง</p>
                        </div>
                        <div class="info-item">
                            <i data-lucide="check-circle-2"></i>
                            <p>การใส่แท็กที่หลากหลายจะช่วยเพิ่มโอกาสให้เอกสารของคุณถูกค้นพบมากขึ้น</p>
                        </div>
                        <div class="info-item">
                            <i data-lucide="shield-check"></i>
                            <p>ระบบมีการตรวจสอบความปลอดภัยของไฟล์ และเข้ารหัสก่อนการจัดเก็บในฐานข้อมูล</p>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem;" class="info-card">
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1rem;">ไฟล์ที่รองรับ</h3>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <span class="tag-badge">.pdf</span>
                            <span class="tag-badge">.docx</span>
                            <span class="tag-badge">.xlsx</span>
                            <span class="tag-badge">.pptx</span>
                            <span class="tag-badge">.jpg</span>
                        </div>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-active');
        });

        ['dragleave', 'drop'].forEach(event => {
            dropZone.addEventListener(event, () => dropZone.classList.remove('drag-active'));
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            fileInput.files = e.dataTransfer.files;
            handleFileSelect();
        });

        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            if (fileInput.files.length > 0) {
                const fileName = fileInput.files[0].name;
                dropZone.querySelector('h3').textContent = 'เลือกไฟล์แล้ว: ' + fileName;
                dropZone.querySelector('p').textContent = 'คลิกเพื่อเปลี่ยนไฟล์';
            }
        }
    </script>
</body>

</html>