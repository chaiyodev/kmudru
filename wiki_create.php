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
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $pdo->prepare("INSERT INTO documents (title, content, category_id, user_id, type, tags) VALUES (?, ?, ?, ?, 'wiki', ?)");
        $stmt->execute([$title, $content, $category_id, $user_id, $tags]);
        $doc_id = $pdo->lastInsertId();

        if (isset($_FILES['images'])) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $total_files = count($_FILES['images']['name']);
            for($i = 0; $i < min($total_files, 3); $i++) {
                if($_FILES['images']['error'][$i] === 0) {
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                        $file_name = time() . '_' . $i . '_' . basename($_FILES['images']['name'][$i]);
                        $target_file = $upload_dir . $file_name;
                        if(move_uploaded_file($_FILES['images']['tmp_name'][$i], $target_file)) {
                            $img_stmt = $pdo->prepare("INSERT INTO document_images (document_id, file_path) VALUES (?, ?)");
                            $img_stmt->execute([$doc_id, $target_file]);
                        }
                    }
                }
            }
        }

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
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
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
            min-height: 450px;
            padding: 1.5rem;
            border: none;
            outline: none;
            font-family: 'Sarabun', 'JetBrains Mono', monospace;
            font-size: 1rem;
            line-height: 1.8;
            resize: vertical;
            display: block;
            tab-size: 4;
        }

        .editor-preview {
            padding: 2.5rem;
            display: none;
            min-height: 450px;
            overflow-y: auto;
            background: white;
        }

        .editor-toolbar {
            padding: 0.5rem 1rem;
            display: flex;
            gap: 0.25rem;
            background: white;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            align-items: center;
        }

        .tool-btn {
            padding: 6px 8px;
            border-radius: 6px;
            cursor: pointer;
            color: hsl(var(--muted-foreground));
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tool-btn:hover {
            background: hsl(var(--primary) / 0.1);
            color: var(--teal-primary);
        }

        .toolbar-divider {
            width: 1px;
            height: 20px;
            background: var(--border-color);
            margin: 0 0.25rem;
        }

        .editor-status-bar {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 1rem;
            background: hsl(var(--muted) / 0.3);
            border-top: 1px solid var(--border-color);
            font-size: 0.75rem;
            color: hsl(var(--muted-foreground));
        }

        .sidebar-card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .wiki-preview-content {
            font-family: 'Sarabun', sans-serif;
            line-height: 1.8;
            color: #334155;
        }

        .wiki-preview-content h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: #0f172a;
        }

        .wiki-preview-content h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid var(--teal-primary);
            padding-left: 0.875rem;
            color: #0f172a;
        }

        .wiki-preview-content h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .wiki-preview-content p {
            line-height: 1.8;
            margin-bottom: 1rem;
            color: #334155;
        }

        .wiki-preview-content ul, .wiki-preview-content ol {
            margin-bottom: 1rem;
            padding-left: 1.5rem;
        }

        .wiki-preview-content li {
            margin-bottom: 0.4rem;
        }

        .wiki-preview-content strong {
            color: #0f172a;
            font-weight: 700;
        }

        .wiki-preview-content blockquote {
            border-left: 4px solid var(--teal-primary);
            padding: 1rem 1.5rem;
            background: hsl(var(--primary) / 0.05);
            border-radius: 0 12px 12px 0;
            margin: 1.5rem 0;
            color: #475569;
        }

        .wiki-preview-content code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
            color: #e11d48;
        }

        .wiki-preview-content pre {
            background: #1e293b;
            color: #f8fafc;
            padding: 1.25rem;
            border-radius: 10px;
            overflow-x: auto;
            margin: 1.5rem 0;
            line-height: 1.5;
        }

        .wiki-preview-content pre code {
            background: none;
            color: inherit;
            padding: 0;
        }

        .wiki-preview-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            border-radius: 8px;
            overflow: hidden;
        }

        .wiki-preview-content th, .wiki-preview-content td {
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            text-align: left;
        }

        .wiki-preview-content th {
            background: hsl(var(--primary) / 0.1);
            font-weight: 700;
        }

        .wiki-preview-content hr {
            border: none;
            border-top: 2px solid var(--border-color);
            margin: 2rem 0;
        }

        .wiki-preview-content img {
            max-width: 100%;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .wiki-create-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2.5rem;
        }

        .form-actions-wiki {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        @media (max-width: 1024px) {
            .wiki-create-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .form-actions-wiki {
                flex-direction: column;
            }
            .editor-tabs {
                overflow-x: auto;
                white-space: nowrap;
            }
            .editor-tab {
                padding: 0.75rem 1rem;
                flex: none;
            }
            .editor-textarea, .editor-preview {
                padding: 1.25rem;
                min-height: 350px;
            }
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

            <form action="wiki_create.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="wiki-create-grid">
                    <div>
                        <div
                            style="background: white; border-radius: 0.75rem; border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 1.5rem;">
                            <input type="text" name="title" placeholder="พิมพ์หัวข้อบทความ..."
                                style="font-family: inherit; font-size: clamp(1.25rem, 3vw, 1.75rem); font-weight: 800; border: none; outline: none; width: 100%; border-bottom: 2px solid transparent; transition: 0.2s;"
                                onfocus="this.style.borderBottomColor='var(--teal-primary)'" required>
                        </div>

                        <div class="editor-container">
                            <div class="editor-tabs">
                                <div class="editor-tab active" id="tab-write">เขียน (Markdown)</div>
                                <div class="editor-tab" id="tab-preview">ดูตัวอย่าง</div>
                            </div>
                            <div class="editor-toolbar">
                                <div class="tool-btn" onclick="insertMD('**', '**')" title="ตัวหนา (Ctrl+B)"><i data-lucide="bold"></i></div>
                                <div class="tool-btn" onclick="insertMD('*', '*')" title="ตัวเอียง (Ctrl+I)"><i data-lucide="italic"></i></div>
                                <div class="toolbar-divider"></div>
                                <div class="tool-btn" onclick="insertMD('# ', '')" title="หัวข้อ 1"><i data-lucide="heading-1"></i></div>
                                <div class="tool-btn" onclick="insertMD('## ', '')" title="หัวข้อ 2"><i data-lucide="heading-2"></i></div>
                                <div class="tool-btn" onclick="insertMD('### ', '')" title="หัวข้อ 3"><i data-lucide="heading-3"></i></div>
                                <div class="toolbar-divider"></div>
                                <div class="tool-btn" onclick="insertMD('- ', '')" title="รายการ"><i data-lucide="list"></i></div>
                                <div class="tool-btn" onclick="insertMD('1. ', '')" title="รายการเรียงลำดับ"><i data-lucide="list-ordered"></i></div>
                                <div class="tool-btn" onclick="insertMD('> ', '')" title="อ้างอิง"><i data-lucide="quote"></i></div>
                                <div class="toolbar-divider"></div>
                                <div class="tool-btn" onclick="insertMD('[', '](url)')" title="ลิงก์"><i data-lucide="link"></i></div>
                                <div class="tool-btn" onclick="insertMD('![alt](', ')')" title="รูปภาพ"><i data-lucide="image"></i></div>
                                <div class="tool-btn" onclick="insertMD('`', '`')" title="โค้ดอินไลน์"><i data-lucide="code"></i></div>
                                <div class="tool-btn" onclick="insertTable()" title="ตาราง"><i data-lucide="table-2"></i></div>
                                <div class="tool-btn" onclick="insertMD('\n---\n', '')" title="เส้นคั่น"><i data-lucide="minus"></i></div>
                            </div>
                            <div class="editor-content">
                                <textarea name="content" id="editor-input" class="editor-textarea"
                                    placeholder="เริ่มเขียนบทความของคุณที่นี่... (รองรับ Markdown)

ตัวอย่าง:
# หัวข้อหลัก
## หัวข้อรอง

- รายการที่ 1
- รายการที่ 2

**ตัวหนา** และ *ตัวเอียง*" oninput="updateWordCount()"></textarea>
                                <div id="editor-preview" class="editor-preview wiki-preview-content"></div>
                            </div>
                            <div class="editor-status-bar">
                                <span id="word-count">0 ตัวอักษร · 0 คำ</span>
                                <span>รองรับ Markdown</span>
                            </div>
                        </div>

                        <div class="form-actions-wiki">
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
                            <h3 style="font-size: 0.875rem; font-weight: 700; margin-bottom: 1.25rem; color: hsl(var(--muted-foreground)); letter-spacing: 0.05em; text-transform: uppercase;">
                                <i data-lucide="image" style="width: 16px; margin-right: 4px; vertical-align: middle;"></i> อัลบั้มรูปภาพ
                            </h3>
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="file" name="images[]" multiple accept="image/jpeg, image/png, image/webp" class="form-input" id="image-upload" onchange="previewImages()" style="padding: 0.5rem; font-size: 0.8125rem;">
                                <small style="color: hsl(var(--muted-foreground)); display: block; margin-top: 0.5rem; line-height: 1.5;">อัปโหลดภาพได้สูงสุด 3 รูป (JPG, PNG) ระบบจะใช้อัตโนมัติเป็นหน้าปกและการนำเสนออัลบั้มที่สวยงาม</small>
                            </div>
                            <div id="image-preview-container" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 1rem;"></div>
                        </div>
                    </aside>
                </div>
            </form>
        </main>
    </div>

    <script>
        lucide.createIcons();

        // Configure marked for proper rendering
        marked.setOptions({
            breaks: true,
            gfm: true
        });

        const btnWrite = document.getElementById('tab-write');
        const btnPreview = document.getElementById('tab-preview');
        const editorTextarea = document.getElementById('editor-input');
        const previewDiv = document.getElementById('editor-preview');

        btnWrite.addEventListener('click', () => {
            btnWrite.classList.add('active');
            btnPreview.classList.remove('active');
            editorTextarea.style.display = 'block';
            previewDiv.style.display = 'none';
            document.querySelector('.editor-toolbar').style.display = 'flex';
            document.querySelector('.editor-status-bar').style.display = 'flex';
        });

        btnPreview.addEventListener('click', () => {
            btnPreview.classList.add('active');
            btnWrite.classList.remove('active');
            editorTextarea.style.display = 'none';
            previewDiv.style.display = 'block';
            document.querySelector('.editor-toolbar').style.display = 'none';
            document.querySelector('.editor-status-bar').style.display = 'none';

            // Render Markdown with proper options
            const content = editorTextarea.value || '*ไม่มีเนื้อหาให้แสดงตัวอย่าง*';
            previewDiv.innerHTML = marked.parse(content);
        });

        // Word/character counter
        function updateWordCount() {
            const text = editorTextarea.value;
            const chars = text.length;
            const words = text.trim() === '' ? 0 : text.trim().split(/\s+/).length;
            document.getElementById('word-count').textContent = `${chars} ตัวอักษร · ${words} คำ`;
        }

        // Image preview
        function previewImages() {
            const container = document.getElementById('image-preview-container');
            container.innerHTML = '';
            const files = document.getElementById('image-upload').files;
            const maxFiles = Math.min(files.length, 3);
            for(let i=0; i<maxFiles; i++) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.style.aspectRatio = '1/1';
                    div.style.borderRadius = '8px';
                    div.style.overflow = 'hidden';
                    div.style.background = '#f1f5f9';
                    div.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
                    container.appendChild(div);
                }
                reader.readAsDataURL(files[i]);
            }
        }

        // Insert markdown syntax
        function insertMD(prefix, suffix) {
            const textarea = document.getElementById('editor-input');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const selectedText = text.substring(start, end);
            
            const defaultText = selectedText || 'ข้อความ';
            const replacement = prefix + defaultText + suffix;
            
            textarea.value = text.substring(0, start) + replacement + text.substring(end);
            
            textarea.focus();
            if (selectedText.length === 0) {
                textarea.selectionStart = start + prefix.length;
                textarea.selectionEnd = start + prefix.length + defaultText.length;
            } else {
                textarea.selectionStart = start;
                textarea.selectionEnd = start + replacement.length;
            }
            updateWordCount();
        }

        // Insert table template
        function insertTable() {
            const table = '\n| หัวข้อ 1 | หัวข้อ 2 | หัวข้อ 3 |\n|---------|---------|---------|\n| ข้อมูล 1 | ข้อมูล 2 | ข้อมูล 3 |\n| ข้อมูล 4 | ข้อมูล 5 | ข้อมูล 6 |\n';
            insertMD(table, '');
        }

        // Keyboard shortcuts
        editorTextarea.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 'b') { e.preventDefault(); insertMD('**', '**'); }
                if (e.key === 'i') { e.preventDefault(); insertMD('*', '*'); }
                if (e.key === 'k') { e.preventDefault(); insertMD('[', '](url)'); }
            }
            // Tab key inserts spaces instead of changing focus
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = this.selectionStart;
                this.value = this.value.substring(0, start) + '    ' + this.value.substring(this.selectionEnd);
                this.selectionStart = this.selectionEnd = start + 4;
            }
        });
    </script>
</body>

</html>