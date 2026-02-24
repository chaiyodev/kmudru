<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

require_login();
$user_id = $_SESSION['user_id'];

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check if document exists and join with users to get author name
$stmt = $pdo->prepare("SELECT d.*, u.full_name as author_name FROM documents d LEFT JOIN users u ON d.user_id = u.id WHERE d.id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    die("ไม่พบเอกสารที่ต้องการแก้ไข");
}

// Authorization Check
$can_edit = ($doc['user_id'] == $user_id || $_SESSION['role'] === 'admin');
if ($doc['type'] === 'wiki') {
    // For Wiki, any contributor or higher can edit. 
    // If we want total open, just: $can_edit = true;
    if ($_SESSION['role'] === 'contributor' || $_SESSION['role'] === 'admin') {
        $can_edit = true;
    }
}

if (!$can_edit) {
    die("คุณไม่มีสิทธิ์แก้ไขเอกสารนี้");
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $title = trim($_POST['title']);
    $content = $_POST['content']; // HTML/Markdown content
    $category_id = (int) $_POST['category_id'];
    $status = $_POST['status'] ?? 'published';
    $tags = $_POST['tags'] ?? '';
    $doc_references = $_POST['doc_references'] ?? '';
    $edit_summary = trim($_POST['edit_summary'] ?? '');

    if (empty($title)) {
        $error = "กรุณาระบุหัวข้อ";
    } else {
        try {
            $update_sql = "UPDATE documents SET title = ?, content = ?, category_id = ?, status = ?, tags = ?, doc_references = ?, last_editor_id = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$title, $content, $category_id, $status, $tags, $doc_references, $user_id, $id]);

            // Save Snapshot to history
            $version_sql = "INSERT INTO document_versions (document_id, user_id, title_snapshot, content_snapshot, references_snapshot, edit_summary) VALUES (?, ?, ?, ?, ?, ?)";
            $version_stmt = $pdo->prepare($version_sql);
            $version_stmt->execute([$id, $user_id, $title, $content, $doc_references, $edit_summary]);

            $message = "บันทึกการแก้ไขและสำรองประวัติเรียบร้อยแล้ว";
            // Refresh Data
            $stmt->execute([$id]);
            $doc = $stmt->fetch();
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Fetch Categories
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

$page_title = "แก้ไขเนื้อหา: " . htmlspecialchars($doc['title']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขเนื้อหา | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .edit-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .editor-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-badge.published {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.draft {
            background: #f1f5f9;
            color: #475569;
        }

        /* Editor Styles (Reused) */
        .simple-editor textarea {
            width: 100%;
            min-height: 400px;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-family: inherit;
            resize: vertical;
            line-height: 1.6;
        }

        .simple-editor textarea:focus {
            outline: none;
            border-color: var(--teal-primary);
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>แก้ไขเนื้อหา</h2>
                    <p>ปรับปรุงข้อมูลความรู้ให้ทันสมัยอยู่เสมอ</p>
                </div>
                <div class="header-actions">
                    <a href="profile.php" class="btn-primary"
                        style="background: white; color: hsl(var(--foreground)); border: 1px solid var(--border-color);">
                        ยกเลิก
                    </a>
                    <a href="view.php?id=<?php echo $id; ?>" class="btn-primary" target="_blank">
                        <i data-lucide="external-link"></i> ดูหน้าเว็บจริง
                    </a>
                </div>
            </header>

            <?php if ($doc['type'] === 'wiki' && $doc['user_id'] != $user_id): ?>
                <div
                    style="background: hsl(var(--primary)/0.05); border: 1px dashed var(--teal-primary); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: start; gap: 0.75rem;">
                    <i data-lucide="info"
                        style="color: var(--teal-primary); width: 20px; flex-shrink: 0; margin-top: 2px;"></i>
                    <div style="font-size: 0.875rem;">
                        <strong style="display: block; margin-bottom: 0.25rem;">พื้นที่ความร่วมมือ (Collaboration
                            Space)</strong>
                        คุณกำลังร่วมแก้ไขบทความวิกิของ
                        <strong><?php echo htmlspecialchars($doc['author_name'] ?? 'เพื่อนร่วมงาน'); ?></strong>
                        การบันทึกของคุณจะถูกบันทึกประวัติผู้แก้ไขล่าสุดในระบบครับ
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div
                    style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="check-circle" style="width: 20px;"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="alert-circle" style="width: 20px;"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="edit-layout">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <!-- Main Content Area -->
                <div>
                    <div class="editor-card">
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label"
                                style="font-weight: 700; margin-bottom: 0.5rem; display: block;">หัวข้อเรื่อง
                                (Title)</label>
                            <input type="text" name="title" value="<?php echo htmlspecialchars($doc['title']); ?>"
                                class="form-input" style="font-size: 1.25rem; font-weight: 600; padding: 0.75rem;"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700; margin-bottom: 0.5rem; display: block;">
                                เนื้อหา (Content)
                                <?php if ($doc['type'] == 'wiki'): ?>
                                    <span style="font-size: 0.75rem; color: #64748b; font-weight: 400;">(รองรับ
                                        Markdown)</span>
                                <?php endif; ?>
                            </label>
                            <div class="simple-editor">
                                <textarea name="content"><?php echo htmlspecialchars($doc['content']); ?></textarea>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 1.5rem;">
                            <label class="form-label" style="font-weight: 700; margin-bottom: 0.5rem; display: block;">
                                แหล่งอ้างอิง (References)
                            </label>
                            <textarea name="doc_references" class="form-input" style="height: 100px; resize: vertical;"
                                placeholder="ใส่แหล่งอ้างอิงข้อมูลของคุณที่นี่..."><?php echo htmlspecialchars($doc['doc_references'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="editor-card">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700; margin-bottom: 0.5rem; display: block;">
                                สรุปการแก้ไข (Edit Summary)
                            </label>
                            <input type="text" name="edit_summary" class="form-input"
                                placeholder="เช่น 'แก้ไขคำผิด', 'เพิ่มเนื้อหาบทที่ 2'..." required>
                            <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">อธิบายสั้นๆ
                                ว่าคุณแก้ไขอะไรในรอบนี้</p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Settings -->
                <aside>
                    <div class="editor-card">
                        <h3
                            style="font-size: 1rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="settings" style="width: 18px;"></i> ตั้งค่าบทความ
                        </h3>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label">สถานะ (Status)</label>
                            <select name="status" class="form-select">
                                <option value="published" <?php echo $doc['status'] == 'published' ? 'selected' : ''; ?>>
                                    เผยแพร่ (Published)</option>
                                <option value="draft" <?php echo $doc['status'] == 'draft' ? 'selected' : ''; ?>>ฉบับร่าง
                                    (Draft)</option>
                                <option value="archived" <?php echo $doc['status'] == 'archived' ? 'selected' : ''; ?>>
                                    เก็บเข้าคลัง (Archived)</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label">หมวดหมู่ (Category)</label>
                            <select name="category_id" class="form-select">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $doc['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label">ป้ายกำกับ (Tags)</label>
                            <input type="text" name="tags" value="<?php echo htmlspecialchars($doc['tags'] ?? ''); ?>"
                                class="form-input" placeholder="เช่น km, วิจัย, เทคนิค">
                            <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                                คั่นด้วยเครื่องหมายจุลภาค (,)</p>
                        </div>

                        <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">
                            <i data-lucide="save"></i> บันทึกการเปลี่ยนแปลง
                        </button>
                    </div>

                    <div class="editor-card" style="background: #f8fafc; border: none;">
                        <div style="font-size: 0.875rem; color: #64748b;">
                            <div style="margin-bottom: 0.5rem;">สร้างเมื่อ:
                                <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                            </div>
                            <div>แก้ไขล่าสุด:
                                <?php echo date('d/m/Y H:i', strtotime($doc['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                </aside>
            </form>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>