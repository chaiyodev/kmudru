<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$tab = isset($_GET['tab']) ? $_GET['tab'] : ($id > 0 ? 'lessons' : 'basic');

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$categories = [];
if ($pdo) {
    $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
}

$message = '';
$error = '';

// --- 1. HANDLING COURSE CREATION / UPDATE (BASIC INFO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_course'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $title = $_POST['title'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    $duration = $_POST['duration'];
    $link = $_POST['link'];
    $level = $_POST['level'];
    $objectives = $_POST['objectives'];

    // Handle File Upload
    $thumbnail = null;
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT thumbnail FROM trainings WHERE id = ?");
        $stmt->execute([$id]);
        $thumbnail = $stmt->fetchColumn();
    }

    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/courses/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        $filename = uniqid() . '_' . basename($_FILES['thumbnail']['name']);
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $targetPath)) {
            $thumbnail = $targetPath;
        }
    }

    try {
        if ($id > 0) {
            // Update
            $stmt = $pdo->prepare("UPDATE trainings SET title=?, description=?, duration=?, category_id=?, link=?, thumbnail=?, level=?, objectives=? WHERE id=?");
            $stmt->execute([$title, $description, $duration, $category_id, $link, $thumbnail, $level, $objectives, $id]);
            $message = "อัปเดตข้อมูลหลักสูตรเรียบร้อยแล้ว!";
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO trainings (title, description, duration, category_id, link, created_by, thumbnail, level, objectives) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $duration, $category_id, $link, $_SESSION['user_id'], $thumbnail, $level, $objectives]);
            $id = $pdo->lastInsertId();
            header("Location: training_create.php?id=$id&tab=lessons&status=created");
            exit;
        }
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// --- 2. HANDLING LESSONS ---
if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lesson'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $l_title = $_POST['lesson_title'];
    $video_url = $_POST['video_url'];
    $l_duration = $_POST['l_duration'];
    $content = $_POST['content'];

    $order = $pdo->query("SELECT IFNULL(MAX(order_index), 0) FROM course_lessons WHERE course_id = $id")->fetchColumn() + 1;

    $stmt = $pdo->prepare("INSERT INTO course_lessons (course_id, title, video_url, duration, content, order_index) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id, $l_title, $video_url, $l_duration, $content, $order]);
    header("Location: training_create.php?id=$id&tab=lessons");
    exit;
}

// --- 3. HANDLING QUIZ ---
if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $question = $_POST['question'];
    $options = json_encode([
        'A' => $_POST['option_a'],
        'B' => $_POST['option_b'],
        'C' => $_POST['option_c'],
        'D' => $_POST['option_d']
    ], JSON_UNESCAPED_UNICODE);
    $correct = $_POST['correct'];

    $stmt = $pdo->prepare("INSERT INTO quizzes (course_id, question, options, correct_answer) VALUES (?, ?, ?, ?)");
    $stmt->execute([$id, $question, $options, $correct]);
    header("Location: training_create.php?id=$id&tab=quiz");
    exit;
}

// --- DELETE ACTIONS ---
if (isset($_GET['delete_lesson']) && $id > 0) {
    $l_id = (int) $_GET['delete_lesson'];
    $pdo->exec("DELETE FROM course_lessons WHERE id = $l_id AND course_id = $id");
    header("Location: training_create.php?id=$id&tab=lessons");
    exit;
}
if (isset($_GET['delete_quiz']) && $id > 0) {
    $q_id = (int) $_GET['delete_quiz'];
    $pdo->exec("DELETE FROM quizzes WHERE id = $q_id AND course_id = $id");
    header("Location: training_create.php?id=$id&tab=quiz");
    exit;
}

// --- FETCH DATA FOR DISPLAY ---
$course = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM trainings WHERE id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch();

    if (!$course || ($course['created_by'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin')) {
        header("Location: training.php");
        exit;
    }
}

$all_lessons = [];
$all_quizzes = [];
if ($id > 0) {
    $all_lessons = $pdo->query("SELECT * FROM course_lessons WHERE course_id = $id ORDER BY order_index ASC")->fetchAll();
    $all_quizzes = $pdo->query("SELECT * FROM quizzes WHERE course_id = $id")->fetchAll();
}

if (isset($_GET['status']) && $_GET['status'] == 'created') {
    $message = "สร้างหลักสูตรเรียบร้อยแล้ว! ตอนนี้คุณสามารถเพิ่มบทเรียนและข้อสอบด้านล่างได้เลย";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id > 0 ? 'แก้ไขหลักสูตร' : 'สร้างหลักสูตรใหม่'; ?> | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .creator-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .creation-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .step-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 1rem;
        }

        .step-tab {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            color: #64748b;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 2px solid transparent;
            transition: 0.2s;
        }

        .step-tab.active {
            background: #e0f2fe;
            color: var(--teal-primary);
            border-color: #7dd3fc;
        }

        .step-tab.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #f8fafc;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            background: #f8fafc;
        }

        .item-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            background: #fff;
        }

        .level-options {
            display: flex;
            gap: 1rem;
        }

        .level-radio {
            display: none;
        }

        .level-label {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
            font-size: 0.9rem;
        }

        .level-radio:checked+.level-label {
            background: #ecfdf5;
            border-color: #10b981;
            color: #047857;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2><?php echo $id > 0 ? 'จัดการเนื้อหาหลักสูตร' : 'สร้างหลักสูตรใหม่'; ?></h2>
                    <p>รวบรวมเนื้อหาและแบบทดสอบในที่เดียว</p>
                </div>
                <?php if ($id > 0): ?>
                    <div class="header-actions">
                        <a href="training_view.php?id=<?php echo $id; ?>" class="btn-primary" target="_blank">
                            <i data-lucide="eye"></i> ดูตัวอย่างผลลัพธ์
                        </a>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($message): ?>
                <div
                    style="background: #ecfdf5; color: #047857; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid #10b98133;">
                    <i data-lucide="check-circle" style="width: 18px; display: inline; vertical-align: middle;"></i>
                    <?php echo e($message); ?>
                </div>
            <?php endif; ?>

            <div class="creator-container">
                <!-- Step Tabs -->
                <nav class="step-tabs">
                    <a href="?id=<?php echo $id; ?>&tab=basic"
                        class="step-tab <?php echo $tab == 'basic' ? 'active' : ''; ?>">
                        <i data-lucide="settings"></i> 1. ตั้งค่าพื้นฐาน
                    </a>
                    <a href="<?php echo $id > 0 ? "?id=$id&tab=lessons" : "#"; ?>"
                        class="step-tab <?php echo $tab == 'lessons' ? 'active' : ''; ?> <?php echo $id == 0 ? 'disabled' : ''; ?>">
                        <i data-lucide="youtube"></i> 2. เพิ่มบทเรียน (Video)
                    </a>
                    <a href="<?php echo $id > 0 ? "?id=$id&tab=quiz" : "#"; ?>"
                        class="step-tab <?php echo $tab == 'quiz' ? 'active' : ''; ?> <?php echo $id == 0 ? 'disabled' : ''; ?>">
                        <i data-lucide="help-circle"></i> 3. สร้างข้อสอบ (Quiz)
                    </a>
                </nav>

                <div class="creation-card">
                    <!-- TAB 1: BASIC INFO -->
                    <?php if ($tab === 'basic'): ?>
                        <form action="training_create.php<?php echo $id > 0 ? "?id=$id" : ""; ?>" method="POST"
                            enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="save_course" value="1">

                            <div class="form-section">
                                <h3 class="section-title"><i data-lucide="info"></i> ข้อมูลหลักสูตร</h3>
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">ชื่อหลักสูตร</label>
                                    <input type="text" name="title" class="form-input"
                                        value="<?php echo htmlspecialchars($course['title'] ?? ''); ?>"
                                        placeholder="ชื่อวิชาหรือหัวข้อการเรียนรู้" required>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label class="form-label">หมวดหมู่</label>
                                        <select name="category_id" class="form-input" required>
                                            <option value="">เลือกหมวดหมู่...</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($course['category_id']) && $course['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">ระยะเวลาทั้งหมด</label>
                                        <input type="text" name="duration" class="form-input"
                                            value="<?php echo htmlspecialchars($course['duration'] ?? ''); ?>"
                                            placeholder="เช่น 3 ชั่วโมง 15 นาที">
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="section-title"><i data-lucide="image"></i> รูปปกและระดับความยาก</h3>
                                <div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem;">
                                    <div class="upload-area" onclick="document.getElementById('thumb-input').click()">
                                        <input type="file" name="thumbnail" id="thumb-input" accept="image/*"
                                            style="display: none;" onchange="previewImage(this)">
                                        <div id="preview-area">
                                            <?php if (isset($course['thumbnail']) && $course['thumbnail']): ?>
                                                <img src="<?php echo $course['thumbnail']; ?>"
                                                    style="max-height: 120px; border-radius: 0.5rem;">
                                            <?php else: ?>
                                                <i data-lucide="image"
                                                    style="width: 40px; color: #94a3b8; margin-bottom: 0.5rem;"></i>
                                                <p style="font-size: 0.8rem; color: #64748b;">คลิกอัปโหลดปก (PNG, JPG)</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">ระดับความยาก</label>
                                        <div class="level-options">
                                            <label><input type="radio" name="level" value="Beginner" class="level-radio"
                                                    <?php echo (!isset($course['level']) || $course['level'] == 'Beginner') ? 'checked' : ''; ?>>
                                                <div class="level-label">🟢 พื้นฐาน</div>
                                            </label>
                                            <label><input type="radio" name="level" value="Intermediate" class="level-radio"
                                                    <?php echo (isset($course['level']) && $course['level'] == 'Intermediate') ? 'checked' : ''; ?>>
                                                <div class="level-label">🟡 ปานกลาง</div>
                                            </label>
                                            <label><input type="radio" name="level" value="Advanced" class="level-radio"
                                                    <?php echo (isset($course['level']) && $course['level'] == 'Advanced') ? 'checked' : ''; ?>>
                                                <div class="level-label">🔴 ขั้นสูง</div>
                                            </label>
                                        </div>
                                        <div class="form-group" style="margin-top: 1.5rem;">
                                            <label class="form-label">ลิงก์ภายนอกเสริม (ถ้ามี)</label>
                                            <input type="url" name="link" class="form-input"
                                                value="<?php echo htmlspecialchars($course['link'] ?? ''); ?>"
                                                placeholder="https://youtube.com/playlist...">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3 class="section-title"><i data-lucide="align-left"></i> รายละเอียดคอร์ส</h3>
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">คำอธิบาย</label>
                                    <textarea name="description" class="form-input"
                                        rows="3"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">สิ่งที่จะได้เรียนรู้ (Objectives)</label>
                                    <textarea name="objectives" class="form-input" rows="3"
                                        placeholder="- การติดตั้งโปรแกรม...&#10;- พื้นฐานการใช้งาน..."><?php echo htmlspecialchars($course['objectives'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div style="display: flex; gap: 1rem;">
                                <button type="submit" class="btn-primary" style="padding: 1rem 3rem;">
                                    <?php echo $id > 0 ? 'บันทึกการแก้ไข' : 'บันทึกและไปขั้นตอนต่อไป'; ?>
                                </button>
                                <?php if ($id == 0): ?>
                                    <a href="training.php" class="btn-primary"
                                        style="background: white; border: 1px solid #e2e8f0; color: #64748b;">ยกเลิก</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>

                    <!-- TAB 2: LESSONS -->
                    <?php if ($tab === 'lessons' && $id > 0): ?>
                        <div class="form-section">
                            <h3 class="section-title"><i data-lucide="plus-circle"></i> เพิ่มบทเรียนใหม่</h3>
                            <form method="POST"
                                style="background: #f8fafc; padding: 2rem; border-radius: 1rem; border: 1px solid #e2e8f0;">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="add_lesson" value="1">
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">หัวข้อบทเรียน</label>
                                    <input type="text" name="lesson_title" class="form-input" placeholder="เช่น บทนำกราฟิก"
                                        required>
                                </div>
                                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                    <div>
                                        <label class="form-label">YouTube URL</label>
                                        <input type="url" name="video_url" class="form-input"
                                            placeholder="https://www.youtube.com/watch?v=..." required>
                                    </div>
                                    <div>
                                        <label class="form-label">เวลา (เช่น 10:30)</label>
                                        <input type="text" name="l_duration" class="form-input">
                                    </div>
                                </div>
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">สรุปเนื้อหาเบื้องต้น</label>
                                    <textarea name="content" class="form-input" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn-primary"><i data-lucide="save"></i>
                                    เพิ่มบทเรียนนี้</button>
                            </form>
                        </div>

                        <div class="form-section" style="border-bottom: none;">
                            <h3 class="section-title"><i data-lucide="list"></i> รายการบทเรียนทั้งหมด
                                (<?php echo count($all_lessons); ?>)</h3>
                            <div class="item-list">
                                <?php foreach ($all_lessons as $idx => $l): ?>
                                    <div class="list-item">
                                        <div style="display: flex; gap: 1rem; align-items: center;">
                                            <div
                                                style="width: 32px; height: 32px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #64748b;">
                                                <?php echo $idx + 1; ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: #0f172a;">
                                                    <?php echo htmlspecialchars($l['title']); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #64748b;">
                                                    <?php echo htmlspecialchars($l['duration']); ?> •
                                                    <?php echo htmlspecialchars($l['video_url']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $id; ?>&tab=lessons&delete_lesson=<?php echo $l['id']; ?>"
                                            onclick="return confirm('ลบบทเรียนนี้?');" style="color: #ef4444;"><i
                                                data-lucide="trash-2" style="width: 18px;"></i></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($all_lessons) > 0): ?>
                                <a href="?id=<?php echo $id; ?>&tab=quiz" class="btn-primary"
                                    style="margin-top: 2rem; width: 100%; justify-content: center; border-radius: 0.5rem; background: #0f172a;">เรียบร้อยแล้ว
                                    ไปสร้างข้อสอบต่อ <i data-lucide="arrow-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- TAB 3: QUIZ -->
                    <?php if ($tab === 'quiz' && $id > 0): ?>
                        <div class="form-section">
                            <h3 class="section-title"><i data-lucide="plus-square"></i> เพิ่มคำถามข้อสอบ</h3>
                            <form method="POST"
                                style="background: #f8fafc; padding: 2rem; border-radius: 1rem; border: 1px solid #e2e8f0;">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="add_question" value="1">
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">คำถาม</label>
                                    <textarea name="question" class="form-input" rows="2" required></textarea>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                    <input type="text" name="option_a" class="form-input" placeholder="ก (Option A)"
                                        required>
                                    <input type="text" name="option_b" class="form-input" placeholder="ข (Option B)"
                                        required>
                                    <input type="text" name="option_c" class="form-input" placeholder="ค (Option C)"
                                        required>
                                    <input type="text" name="option_d" class="form-input" placeholder="ง (Option D)"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">เฉลยข้อที่ถูกต้อง</label>
                                    <select name="correct" class="form-input" style="max-width: 250px;">
                                        <option value="A">ตัวเลือก ก (A)</option>
                                        <option value="B">ตัวเลือก ข (B)</option>
                                        <option value="C">ตัวเลือก ค (C)</option>
                                        <option value="D">ตัวเลือก ง (D)</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn-primary" style="margin-top: 1rem;"><i
                                        data-lucide="save"></i> เพิ่มคำถาม</button>
                            </form>
                        </div>

                        <div class="form-section" style="border-bottom: none;">
                            <h3 class="section-title"><i data-lucide="check-square"></i> ข้อสอบปัจจุบัน
                                (<?php echo count($all_quizzes); ?> ข้อ)</h3>
                            <div class="item-list">
                                <?php foreach ($all_quizzes as $idx => $q): ?>
                                    <div class="list-item">
                                        <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                            <div style="font-weight: 700; color: #10b981; padding-top: 2px;">
                                                Q<?php echo $idx + 1; ?></div>
                                            <div>
                                                <div style="font-weight: 700; margin-bottom: 0.25rem;">
                                                    <?php echo htmlspecialchars($q['question']); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #10b981; font-weight: 600;">เฉลย: ข้อ
                                                    <?php echo $q['correct_answer']; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $id; ?>&tab=quiz&delete_quiz=<?php echo $q['id']; ?>"
                                            onclick="return confirm('ลบคำถามข้อนี้?');" style="color: #ef4444;"><i
                                                data-lucide="trash-2" style="width: 18px;"></i></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($all_quizzes) > 0): ?>
                                <a href="training.php" class="btn-primary"
                                    style="margin-top: 2rem; width: 100%; justify-content: center; border-radius: 0.5rem;">เสร็จสิ้นการสร้างหลักสูตร
                                    <i data-lucide="check"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('preview-area').innerHTML = '<img src="' + e.target.result + '" style="max-height: 120px; border-radius: 0.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>