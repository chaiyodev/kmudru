<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

require_admin();

$pdo = get_pdo();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? 'create';
    
    if ($action === 'create') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = $_POST['icon'] ?? 'folder';

        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)");
                $stmt->execute([$name, $description, $icon]);
                $message = "สร้างหมวดหมู่ใหม่เรียบร้อยแล้ว!";
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        } else {
            $error = "กรุณาระบุชื่อหมวดหมู่";
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = $_POST['icon'] ?? 'folder';
        
        if ($id > 0 && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, icon = ? WHERE id = ?");
                $stmt->execute([$name, $description, $icon, $id]);
                $message = "อัปเดตหมวดหมู่เรียบร้อยแล้ว!";
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['category_id'];
        if ($id > 0) {
            try {
                // Check if category has documents
                $check = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE category_id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() > 0) {
                    $error = "ไม่สามารถลบได้เนื่องจากมีเอกสารอยู่ในหมวดหมู่นี้";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "ลบหมวดหมู่เรียบร้อยแล้ว!";
                }
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
}

// Fetch existing categories
$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM documents WHERE category_id = c.id) as doc_count FROM categories c ORDER BY name")->fetchAll();

$page_title = 'จัดการหมวดหมู่ | UDRU Wisdom';
$extra_css = <<<'HTML'
    <style>
        .category-form {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .category-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .category-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition-base);
        }

        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: rgba(245, 159, 10, 0.3) 0px 0px 0px 2px;
        }

        .category-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: hsl(var(--primary)/0.1);
            color: var(--teal-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .icon-option {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid transparent;
            background: hsl(var(--muted));
            color: hsl(var(--muted-foreground));
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-base);
        }

        .icon-option:hover,
        .icon-option.selected {
            border-color: var(--teal-primary);
            color: var(--teal-primary);
            background: hsl(var(--primary)/0.1);
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
                    <h2>จัดการหมวดหมู่ความรู้</h2>
                    <p>สร้าง ลบ และแก้ไขหมวดหมู่เพื่อจัดระเบียบองค์ความรู้ในระบบ (เฉพาะผู้ดูแลระบบ)</p>
                </div>
                <div class="header-actions">
                    <a href="categories.php" class="btn-primary"
                        style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));"><i
                            data-lucide="arrow-left"></i>คลังหมวดหมู่ทั่วไป</a>
                </div>
            </header>

            <?php if ($message): ?>
                <div
                    style="background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); padding: 1rem 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; border: 1px solid hsl(142 76% 36% / 0.2); display: flex; align-items: center; gap: 0.75rem;">
                    <i data-lucide="check-circle"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div
                    style="background: hsl(0 84% 60% / 0.1); color: hsl(0 84% 60%); padding: 1rem 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; border: 1px solid hsl(0 84% 60% / 0.2); display: flex; align-items: center; gap: 0.75rem;">
                    <i data-lucide="alert-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="category-form">
                <h3 id="form-title" style="font-size: 1.125rem; font-weight: 800; margin-bottom: 1.5rem;"><i data-lucide="plus-circle"
                        style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px; color: var(--teal-primary);"></i>แบบฟอร์มเพิ่ม/แก้ไข หมวดหมู่
                </h3>

                <form action="category_create.php" method="POST" id="category-crud-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="form-action" value="create">
                    <input type="hidden" name="category_id" id="form-category-id" value="">
                    
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">ชื่อหมวดหมู่</label>
                            <input type="text" name="name" id="form-name" class="form-input"
                                placeholder="เช่น นวัตกรรมการสอน, งานวิจัย" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">คำอธิบายโดยย่อ</label>
                            <input type="text" name="description" id="form-description" class="form-input"
                                placeholder="อธิบายว่าหมวดหมู่นี้ครอบคลุมเนื้อหาประเภทใด">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">เลือกไอคอน</label>
                        <div class="icon-grid">
                            <label class="icon-option selected" onclick="selectIcon(this, 'folder')">
                                <input type="radio" name="icon" value="folder" checked style="display:none;">
                                <i data-lucide="folder"></i>
                            </label>
                            <label class="icon-option" onclick="selectIcon(this, 'book')">
                                <input type="radio" name="icon" value="book" style="display:none;">
                                <i data-lucide="book"></i>
                            </label>
                            <label class="icon-option" onclick="selectIcon(this, 'file-text')">
                                <input type="radio" name="icon" value="file-text" style="display:none;">
                                <i data-lucide="file-text"></i>
                            </label>
                            <label class="icon-option" onclick="selectIcon(this, 'lightbulb')">
                                <input type="radio" name="icon" value="lightbulb" style="display:none;">
                                <i data-lucide="lightbulb"></i>
                            </label>
                            <label class="icon-option" onclick="selectIcon(this, 'briefcase')">
                                <input type="radio" name="icon" value="briefcase" style="display:none;">
                                <i data-lucide="briefcase"></i>
                            </label>
                            <label class="icon-option" onclick="selectIcon(this, 'code')">
                                <input type="radio" name="icon" value="code" style="display:none;">
                                <i data-lucide="code"></i>
                            </label>
                            <label class="icon-option" onclick="selectIcon(this, 'heart')">
                                <input type="radio" name="icon" value="heart" style="display:none;">
                                <i data-lucide="heart"></i>
                            </label>
                            <label class="icon-option" onclick="selectIcon(this, 'star')">
                                <input type="radio" name="icon" value="star" style="display:none;">
                                <i data-lucide="star"></i>
                            </label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" id="form-submit-btn" class="btn-primary" style="padding: 0.75rem 2rem;">บันทึกหมวดหมู่</button>
                        <button type="button" onclick="resetForm()" class="btn-primary" style="background: hsl(var(--muted)); color: hsl(var(--muted-foreground)); padding: 0.75rem 1.5rem;">ล้างข้อมูล</button>
                    </div>
                </form>
            </div>

            <h3 style="font-size: 1.125rem; font-weight: 800; margin-bottom: 1.5rem;">หมวดหมู่ที่มีอยู่และจัดการได้ (<?php echo count($categories); ?>)</h3>

            <div class="category-list">
                <?php foreach ($categories as $cat): ?>
                    <div class="category-card">
                        <div class="category-icon">
                            <i data-lucide="<?php echo htmlspecialchars($cat['icon'] ?? 'folder'); ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-weight: 700; margin-bottom: 0.25rem;">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </h4>
                            <p style="font-size: 0.8125rem; color: hsl(var(--muted-foreground));">
                                <?php echo $cat['doc_count']; ?> เอกสาร
                            </p>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                           <button type="button" class="btn-primary" style="padding: 0.5rem; background: hsl(var(--muted)); color: hsl(var(--foreground));" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)"><i data-lucide="edit" style="width: 16px; height: 16px;"></i></button>
                           <form method="POST" onsubmit="return confirm('ยืนยันการลบหมวดหมู่นี้? (หากมีบทความอยู่ภายในจะไม่สามารถลบได้)');" style="margin: 0; padding: 0;">
                               <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                               <input type="hidden" name="action" value="delete">
                               <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                               <button type="submit" class="btn-primary" style="padding: 0.5rem; background: hsl(0 84% 60% / 0.1); color: hsl(0 84% 60%);"><i data-lucide="trash-2" style="width: 16px; height: 16px;"></i></button>
                           </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <script>
        function selectIcon(el, value) {
            document.querySelectorAll('.icon-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            el.querySelector('input').checked = true;
        }

        function editCategory(cat) {
            document.getElementById('form-action').value = 'edit';
            document.getElementById('form-category-id').value = cat.id;
            document.getElementById('form-name').value = cat.name;
            document.getElementById('form-description').value = cat.description;
            document.getElementById('form-title').innerHTML = '<i data-lucide="edit" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px; color: var(--teal-primary);"></i>แก้ไขหมวดหมู่: ' + cat.name;
            document.getElementById('form-submit-btn').textContent = 'บันทึกการเปลี่ยนแปลง';
            
            // Set Icon
            document.querySelectorAll('.icon-option').forEach(o => o.classList.remove('selected'));
            let iconLabel = document.querySelector(`.icon-option input[value="${cat.icon}"]`);
            if(iconLabel) {
                let parent = iconLabel.parentElement;
                parent.classList.add('selected');
                iconLabel.checked = true;
            }
            lucide.createIcons();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('category-crud-form').reset();
            document.getElementById('form-action').value = 'create';
            document.getElementById('form-category-id').value = '';
            document.getElementById('form-title').innerHTML = '<i data-lucide="plus-circle" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px; color: var(--teal-primary);"></i>แบบฟอร์มเพิ่ม/แก้ไข หมวดหมู่';
            document.getElementById('form-submit-btn').textContent = 'บันทึกหมวดหมู่';
            
            // Reset to folder
            document.querySelectorAll('.icon-option').forEach(o => o.classList.remove('selected'));
            let iconLabel = document.querySelector(`.icon-option input[value="folder"]`);
            if(iconLabel) {
                let parent = iconLabel.parentElement;
                parent.classList.add('selected');
                iconLabel.checked = true;
            }
            lucide.createIcons();
        }
    </script>
<?php require_once 'includes/footer.php'; ?>