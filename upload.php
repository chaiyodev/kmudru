<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

require_login();

$pdo = get_pdo();
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

    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);

        $file_name = time() . '_' . basename($_FILES['file']['name']);
        $target_file = $upload_dir . $file_name;

        // Strict file type validation
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];
        $file_ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['file']['tmp_name']);
        finfo_close($finfo);

        $allowed_mime_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png'
        ];

        $max_file_size = 50 * 1024 * 1024; // 50 MB

        if ($_FILES['file']['size'] > $max_file_size) {
            $error = "ขนาดไฟล์เกินที่กำหนดไว้ (สูงสุด 50MB)";
        } else if (!in_array($file_ext, $allowed_extensions) || !in_array($mime_type, $allowed_mime_types)) {
            $error = "ไฟล์ที่อัปโหลดไม่ได้รับอนุญาตให้ใช้งาน (รองรับเฉพาะ PDF, Word, Excel, PPT, JPG, PNG)";
        } else if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
            try {
                $pdo->beginTransaction();

                // Insert Document
                $stmt = $pdo->prepare("INSERT INTO documents (title, content, category_id, user_id, type, tags, status) VALUES (?, ?, ?, ?, 'document', ?, 'published')");
                $stmt->execute([$title, $description, $category_id, $user_id, $tags]);
                $doc_id = $pdo->lastInsertId();

                // Insert Attachment
                $stmt = $pdo->prepare("INSERT INTO attachments (document_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$doc_id, $_FILES['file']['name'], $target_file, $_FILES['file']['type'], $_FILES['file']['size']]);

                $pdo->commit();
                log_activity('document_upload', 'document', "Title: $title | File: " . $_FILES['file']['name']);
                $message = "อัปโหลดเอกสารเรียบร้อยแล้ว!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        } else {
            $error = "ไม่สามารถย้ายไฟล์ที่อัปโหลดได้";
        }
    } else {
        $error = "กรุณาเลือกไฟล์ที่ต้องการอัปโหลด";
    }
}
?>
<?php
$page_title = 'อัปโหลดเอกสาร | UDRU Wisdom';
$extra_css = '<link rel="stylesheet" href="assets/css/upload.css">';
require_once 'includes/head.php';
?>
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
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ!',
                            text: '<?php echo addslashes($message); ?>',
                            confirmButtonColor: 'var(--teal-primary)'
                        }).then(() => {
                            window.location.href = 'browse.php';
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'ขออภัย...',
                            text: '<?php echo addslashes($error); ?>',
                            confirmButtonColor: 'var(--teal-primary)'
                        });
                    });
                </script>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2.5rem;">
                <form action="upload.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div id="drop-zone" class="upload-zone">
                        <div class="upload-icon"><i data-lucide="upload-cloud"></i></div>
                        <h3 style="font-weight: 700; margin-bottom: 0.5rem;">คลิกเพื่อเลือกไฟล์ หรือลากมาวางที่นี่</h3>
                        <p style="font-size: 0.8125rem; color: hsl(var(--muted-foreground));">รองรับไฟล์ PDF, Word,
                            Excel, PPT และไฟล์รูปภาพ (สูงสุด 20MB)</p>
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

<?php
$extra_js = <<<'HTML'
    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');

        if (dropZone && fileInput) {
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
        }
    </script>
HTML;
require_once 'includes/footer.php';
?>