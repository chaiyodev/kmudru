<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

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
    $content = $_POST['content'];
    $tags = $_POST['tags'];
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO documents (title, content, category_id, user_id, type, tags) VALUES (?, ?, ?, ?, 'wiki', ?)");
        $stmt->execute([$title, $content, $category_id, $user_id, $tags]);
        log_activity('wiki_create', 'document', "Title: $title");
        $message = "สร้างบทความ Wiki เรียบร้อยแล้ว!";
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างสรรค์ Wiki | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .editor-container {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .editor-tabs {
            display: flex;
            background: hsl(var(--muted) / 0.5);
            border-bottom: 1px solid var(--border-color);
        }

        .editor-tab {
            padding: 0.75rem 1.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            color: hsl(var(--muted-foreground));
            transition: var(--transition-base);
            border-bottom: 2px solid transparent;
        }

        .editor-tab.active {
            background: white;
            color: var(--teal-primary);
            border-bottom-color: var(--teal-primary);
        }

        .editor-content {
            padding: 0;
            min-height: 400px;
        }

        .editor-textarea {
            width: 100%;
            height: 400px;
            padding: 1.5rem;
            border: none;
            outline: none;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 0.9375rem;
            line-height: 1.6;
            resize: none;
            display: block;
        }

        .editor-preview {
            padding: 2rem;
            display: none;
            min-height: 400px;
            overflow-y: auto;
            background: white;
        }

        .editor-toolbar {
            padding: 0.5rem 1rem;
            display: flex;
            gap: 0.5rem;
            background: white;
            border-bottom: 1px solid var(--border-color);
        }

        .tool-btn {
            pading: 6px;
            border-radius: 4px;
            cursor: pointer;
            color: hsl(var(--muted-foreground));
            transition: var(--transition-base);
        }

        .tool-btn:hover {
            background: hsl(var(--muted));
            color: var(--teal-primary);
        }

        .sidebar-card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .wiki-preview-content {
            font-family: inherit;
        }

        .wiki-preview-content h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .wiki-preview-content h2 {
            font-size: 1.5rem;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
        }

        .wiki-preview-content p {
            line-height: 1.7;
            margin-bottom: 1rem;
            color: #333;
        }

        .wiki-preview-content code {
            background: #f0f0f0;
            padding: 2px 4px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .wiki-preview-content pre {
            background: #1e293b;
            color: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Viewport -->
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>สร้างบทความวิชาการ (Wiki)</h2>
                    <p>เขียนและรวบรวมองค์ความรู้เชิงลึกในรูปแบบสารานุกรมดิจิทัล</p>
                </div>
            </header>

            <?php if ($message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'สร้างสำเร็จ!',
                            text: '<?php echo addslashes($message); ?>',
                            confirmButtonColor: 'var(--teal-primary)'
                        }).then(() => {
                            window.location.href = 'browse.php?type=wiki';
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: '<?php echo addslashes($error); ?>',
                            confirmButtonColor: 'var(--teal-primary)'
                        });
                    });
                </script>
            <?php endif; ?>

            <form action="wiki_create.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2.5rem;">
                    <div>
                        <div
                            style="background: white; border-radius: 0.75rem; border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 1.5rem;">
                            <input type="text" name="title" placeholder="พิมพ์หัวข้อบทความ..."
                                style="font-size: 2rem; font-weight: 800; border: none; outline: none; width: 100%; border-bottom: 2px solid transparent; transition: 0.2s;"
                                onfocus="this.style.borderBottomColor='var(--teal-primary)'" required>
                        </div>

                        <div class="editor-container">
                            <div class="editor-tabs">
                                <div class="editor-tab active" id="tab-write">เขียน (Markdown)</div>
                                <div class="editor-tab" id="tab-preview">ดูตัวอย่าง</div>
                            </div>
                            <div class="editor-toolbar">
                                <div class="tool-btn" title="Bold"><i data-lucide="bold"></i></div>
                                <div class="tool-btn" title="Italic"><i data-lucide="italic"></i></div>
                                <div class="tool-btn" title="Heading 1"><i data-lucide="heading-1"></i></div>
                                <div class="tool-btn" title="Heading 2"><i data-lucide="heading-2"></i></div>
                                <div class="tool-btn" title="List"><i data-lucide="list"></i></div>
                                <div class="tool-btn" title="Link"><i data-lucide="link"></i></div>
                                <div class="tool-btn" title="Code"><i data-lucide="code"></i></div>
                            </div>
                            <div class="editor-content">
                                <textarea name="content" id="editor-input" class="editor-textarea"
                                    placeholder="เริ่มเขียนบทความของคุณที่นี่... (รองรับ Markdown)"></textarea>
                                <div id="editor-preview" class="editor-preview wiki-preview-content"></div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn-primary"
                                style="padding: 0.75rem 2rem;">เผยแพร่บทความ</button>
                            <button type="button" class="btn-primary"
                                style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">บันทึกร่าง</button>
                        </div>
                    </div>

                    <aside>
                        <div class="sidebar-card">
                            <h3
                                style="font-size: 0.875rem; font-weight: 700; margin-bottom: 1.25rem; color: hsl(var(--muted-foreground)); letter-spacing: 0.05em; text-transform: uppercase;">
                                ข้อมูลบทความ</h3>

                            <div class="form-group">
                                <label class="form-label">หมวดหมู่</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">เลือกหมวดหมู่...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo e($cat['id']); ?>">
                                            <?php echo e($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">แท็ก (Tags)</label>
                                <input type="text" name="tags" class="form-input"
                                    placeholder="ใส่แท็กคั่นด้วยคอมม่า...">
                            </div>
                        </div>

                        <div class="sidebar-card">
                            <h3
                                style="font-size: 0.875rem; font-weight: 700; margin-bottom: 1.25rem; color: hsl(var(--muted-foreground)); letter-spacing: 0.05em; text-transform: uppercase;">
                                คู่มือ Markdown</h3>
                            <div style="font-size: 0.8125rem; color: hsl(var(--muted-foreground)); line-height: 1.8;">
                                <div
                                    style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color);">
                                    <span>**ข้อความหนา**</span>
                                    <span style="font-weight: 700;">หนา</span>
                                </div>
                                <div
                                    style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color);">
                                    <span>*ข้อความเอียง*</span>
                                    <span style="font-style: italic;">เอียง</span>
                                </div>
                                <div
                                    style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color);">
                                    <span># หัวข้อ</span>
                                    <span>H1</span>
                                </div>
                                <div
                                    style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color);">
                                    <span>[ชื่อลิงก์](url)</span>
                                    <span>ลิงก์</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>- รายการ</span>
                                    <span>Bullet</span>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </main>
    </div>

    <script>
        lucide.createIcons();

        const btnWrite = document.getElementById('tab-write');
        const btnPreview = document.getElementById('tab-preview');
        const editorTextarea = document.getElementById('editor-input');
        const previewDiv = document.getElementById('editor-preview');

        btnWrite.addEventListener('click', () => {
            btnWrite.classList.add('active');
            btnPreview.classList.remove('active');
            editorTextarea.style.display = 'block';
            previewDiv.style.display = 'none';
        });

        btnPreview.addEventListener('click', () => {
            btnPreview.classList.add('active');
            btnWrite.classList.remove('active');
            editorTextarea.style.display = 'none';
            previewDiv.style.display = 'block';

            // Render Markdown
            previewDiv.innerHTML = marked.parse(editorTextarea.value || '*ไม่มีเนื้อหาให้แสดงตัวอย่าง*');
        });
    </script>
</body>

</html>