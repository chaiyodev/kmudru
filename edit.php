<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id === 0 || !is_logged_in()) {
    header("Location: index.php");
    exit;
}

// Fetch Document
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    die("Document not found.");
}

// Check if user is owner or admin
if ($doc['user_id'] !== $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
    die("Access Denied: You do not have permission to edit this document.");
}

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$message = '';
$error = '';
$is_wiki = ($doc['type'] === 'wiki');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $title = trim($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $content = trim($_POST['content']);
    $tags = trim($_POST['tags']);

    try {
        $stmt = $pdo->prepare("UPDATE documents SET title = ?, content = ?, category_id = ?, tags = ? WHERE id = ?");
        $stmt->execute([$title, $content, $category_id, $tags, $id]);
        $message = "บันทึกการเปลี่ยนแปลงเรียบร้อยแล้ว!";
        
        // Update local doc variable to reflect changes immediately
        $doc['title'] = $title;
        $doc['content'] = $content;
        $doc['category_id'] = $category_id;
        $doc['tags'] = $tags;
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไข<?php echo $is_wiki ? 'บทความ Wiki' : 'เอกสาร'; ?> | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php if ($is_wiki): ?>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <?php endif; ?>
    <style>
        .edit-form-container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            border-radius: 1.5rem;
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: hsl(var(--foreground));
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            font-family: inherit;
            font-size: 0.875rem;
            outline: none;
            transition: var(--transition-base);
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--teal-primary);
            box-shadow: 0 0 0 3px hsl(var(--primary)/0.1);
        }
        .form-textarea {
            min-height: 250px;
            resize: vertical;
        }

        /* Wiki Editor Styles */
        .editor-container {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
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
        .editor-textarea {
            width: 100%;
            min-height: 450px;
            padding: 1.5rem;
            border: none;
            outline: none;
            font-family: 'Sarabun', monospace;
            font-size: 1rem;
            line-height: 1.8;
            resize: vertical;
            display: block;
        }
        .editor-preview {
            padding: 2.5rem;
            display: none;
            min-height: 450px;
            overflow-y: auto;
            background: white;
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

        /* Preview content styles */
        .wiki-preview-content { font-family: 'Sarabun', sans-serif; line-height: 1.8; color: #334155; }
        .wiki-preview-content h1 { font-size: 2rem; font-weight: 800; margin-bottom: 1rem; color: #0f172a; }
        .wiki-preview-content h2 { font-size: 1.5rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem; border-left: 4px solid var(--teal-primary); padding-left: 0.875rem; color: #0f172a; }
        .wiki-preview-content h3 { font-size: 1.25rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: 0.5rem; color: #1e293b; }
        .wiki-preview-content p { line-height: 1.8; margin-bottom: 1rem; }
        .wiki-preview-content ul, .wiki-preview-content ol { margin-bottom: 1rem; padding-left: 1.5rem; }
        .wiki-preview-content li { margin-bottom: 0.4rem; }
        .wiki-preview-content strong { color: #0f172a; font-weight: 700; }
        .wiki-preview-content blockquote { border-left: 4px solid var(--teal-primary); padding: 1rem 1.5rem; background: hsl(var(--primary) / 0.05); border-radius: 0 12px 12px 0; margin: 1.5rem 0; }
        .wiki-preview-content code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; color: #e11d48; }
        .wiki-preview-content pre { background: #1e293b; color: #f8fafc; padding: 1.25rem; border-radius: 10px; overflow-x: auto; margin: 1.5rem 0; }
        .wiki-preview-content pre code { background: none; color: inherit; padding: 0; }
        .wiki-preview-content table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
        .wiki-preview-content th, .wiki-preview-content td { border: 1px solid var(--border-color); padding: 0.75rem 1rem; text-align: left; }
        .wiki-preview-content th { background: hsl(var(--primary) / 0.1); font-weight: 700; }
        .wiki-preview-content hr { border: none; border-top: 2px solid var(--border-color); margin: 2rem 0; }

        @media (max-width: 768px) {
            .edit-form-container {
                padding: 1.5rem;
                margin: 1rem;
                border-radius: 1rem;
            }
            .form-grid-stack {
                grid-template-columns: 1fr !important;
                gap: 0 !important;
            }
            .form-actions-edit {
                flex-direction: column-reverse;
                align-items: stretch;
            }
            .form-actions-edit > * {
                width: 100%;
                justify-content: center;
            }
            .editor-tab {
                padding: 0.75rem 1rem;
            }
            .editor-textarea, .editor-preview {
                padding: 1rem;
            }
        }
    </style>

</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <a href="view.php?id=<?php echo $id; ?>" style="color: var(--teal-primary); text-decoration: none; font-weight: 600; font-size: 0.875rem;">ย้อนกลับไปหน้าเนื้อหา</a>
                        <i data-lucide="chevron-right" style="width: 14px; color: #94a3b8;"></i>
                        <span style="font-size: 0.875rem; color: #64748b;">แก้ไข</span>
                    </div>
                    <h2>แก้ไข<?php echo $is_wiki ? 'บทความ Wiki' : 'เอกสาร/บทความ'; ?></h2>
                </div>
                <div class="header-actions">
                    <a href="view.php?id=<?php echo $id; ?>" class="btn-primary" style="background: white; color: #1e293b; border: 1px solid var(--border-color);">
                        <i data-lucide="eye"></i>ดูตัวอย่าง
                    </a>
                </div>
            </header>

            <?php if ($message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'บันทึกสำเร็จ!',
                                text: '<?php echo addslashes($message); ?>',
                                confirmButtonColor: 'var(--teal-primary)'
                            }).then(() => {
                                window.location.href = 'view.php?id=<?php echo $id; ?>';
                            });
                        } else {
                            alert('<?php echo addslashes($message); ?>');
                            window.location.href = 'view.php?id=<?php echo $id; ?>';
                        }
                    });
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: hsl(0 84% 60% / 0.1); color: hsl(0 84% 60%); padding: 1rem; border-radius: 0.75rem; margin-bottom: 2rem; border: 1px solid hsl(0 84% 60% / 0.2); max-width: 900px; margin-left: auto; margin-right: auto;">
                    <i data-lucide="alert-circle" style="width: 16px; vertical-align: middle; margin-right: 0.5rem;"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="edit-form-container">
                <form action="edit.php?id=<?php echo $id; ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">หัวข้อ (Title)</label>
                        <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($doc['title']); ?>" required 
                            style="font-size: 1.25rem; font-weight: 700; padding: 1rem;">
                    </div>

                    <div class="form-grid-stack" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">หมวดหมู่</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">เลือกหมวดหมู่...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $doc['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">แท็ก (Tags)</label>
                            <input type="text" name="tags" class="form-input" value="<?php echo htmlspecialchars($doc['tags'] ?? ''); ?>" placeholder="คั่นด้วยคอมม่า เช่น AI, การสอน">
                        </div>
                    </div>

                    <?php if ($is_wiki): ?>
                    <!-- Wiki Markdown Editor -->
                    <div class="form-group">
                        <label class="form-label">เนื้อหา (Markdown)</label>
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
                                <div class="tool-btn" onclick="insertMD('`', '`')" title="โค้ด"><i data-lucide="code"></i></div>
                                <div class="tool-btn" onclick="insertTable()" title="ตาราง"><i data-lucide="table-2"></i></div>
                                <div class="tool-btn" onclick="insertMD('\n---\n', '')" title="เส้นคั่น"><i data-lucide="minus"></i></div>
                            </div>
                            <textarea name="content" id="editor-input" class="editor-textarea" oninput="updateWordCount()"><?php echo htmlspecialchars($doc['content']); ?></textarea>
                            <div id="editor-preview" class="editor-preview wiki-preview-content"></div>
                            <div class="editor-status-bar">
                                <span id="word-count">0 ตัวอักษร · 0 คำ</span>
                                <span>รองรับ Markdown</span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Regular Textarea for non-wiki -->
                    <div class="form-group">
                        <label class="form-label">เนื้อหา / รายละเอียด (Content)</label>
                        <textarea name="content" class="form-textarea" required><?php echo htmlspecialchars($doc['content']); ?></textarea>
                    </div>
                    <?php endif; ?>

                    <div class="form-actions-edit">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn-primary" style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">ยกเลิก</a>
                        <button type="submit" class="btn-primary">
                            <i data-lucide="save" style="width: 16px;"></i>บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        lucide.createIcons();

        <?php if ($is_wiki): ?>
        // Configure marked
        marked.setOptions({ breaks: true, gfm: true });

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
            previewDiv.innerHTML = marked.parse(editorTextarea.value || '*ไม่มีเนื้อหาให้แสดงตัวอย่าง*');
        });

        function updateWordCount() {
            const text = editorTextarea.value;
            const chars = text.length;
            const words = text.trim() === '' ? 0 : text.trim().split(/\s+/).length;
            document.getElementById('word-count').textContent = `${chars} ตัวอักษร · ${words} คำ`;
        }
        updateWordCount();

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

        function insertTable() {
            const table = '\n| หัวข้อ 1 | หัวข้อ 2 | หัวข้อ 3 |\n|---------|---------|----------|\n| ข้อมูล 1 | ข้อมูล 2 | ข้อมูล 3 |\n';
            insertMD(table, '');
        }

        editorTextarea.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 'b') { e.preventDefault(); insertMD('**', '**'); }
                if (e.key === 'i') { e.preventDefault(); insertMD('*', '*'); }
                if (e.key === 'k') { e.preventDefault(); insertMD('[', '](url)'); }
            }
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = this.selectionStart;
                this.value = this.value.substring(0, start) + '    ' + this.value.substring(this.selectionEnd);
                this.selectionStart = this.selectionEnd = start + 4;
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
