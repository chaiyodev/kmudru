<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'lessons'; // Default to lessons for flow

if ($id === 0 || !is_logged_in()) {
    header("Location: training.php");
    exit;
}

// Fetch Course
$stmt = $pdo->prepare("SELECT * FROM trainings WHERE id = ?");
$stmt->execute([$id]);
$course = $stmt->fetch();

// Security Check
if (!$course || ($course['created_by'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin')) {
    header("Location: training.php");
    exit;
}

// --- HANDLE ACTIONS ---

// 1. Add Lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lesson'])) {
    $title = $_POST['lesson_title'];
    $video_url = $_POST['video_url'];
    $duration = $_POST['duration'];
    $content = $_POST['content'];

    // Get next order index
    $order = $pdo->query("SELECT MAX(order_index) FROM course_lessons WHERE course_id = $id")->fetchColumn() + 1;

    $stmt = $pdo->prepare("INSERT INTO course_lessons (course_id, title, video_url, duration, content, order_index) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id, $title, $video_url, $duration, $content, $order]);
    header("Location: course_editor.php?id=$id&tab=lessons");
    exit;
}

// 2. Add Quiz Question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
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
    header("Location: course_editor.php?id=$id&tab=quiz");
    exit;
}

// 3. Update Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $level = $_POST['level'];
    $objs = $_POST['objectives'];

    // Handle File Upload
    $thumbnail = $course['thumbnail'];
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/courses/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);
        $filename = uniqid() . '_' . basename($_FILES['thumbnail']['name']);
        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $filename)) {
            $thumbnail = $uploadDir . $filename;
        }
    }

    $stmt = $pdo->prepare("UPDATE trainings SET title=?, description=?, thumbnail=?, level=?, objectives=? WHERE id=?");
    $stmt->execute([$title, $desc, $thumbnail, $level, $objs, $id]);
    header("Location: course_editor.php?id=$id&tab=settings");
    exit;
}

// Delete Actions
if (isset($_GET['delete_lesson'])) {
    $l_id = (int) $_GET['delete_lesson'];
    $pdo->exec("DELETE FROM course_lessons WHERE id = $l_id AND course_id = $id");
    header("Location: course_editor.php?id=$id&tab=lessons");
    exit;
}
if (isset($_GET['delete_quiz'])) {
    $q_id = (int) $_GET['delete_quiz'];
    $pdo->exec("DELETE FROM quizzes WHERE id = $q_id AND course_id = $id");
    header("Location: course_editor.php?id=$id&tab=quiz");
    exit;
}

// Fetch Data
$lessons = $pdo->prepare("SELECT * FROM course_lessons WHERE course_id = ? ORDER BY order_index ASC");
$lessons->execute([$id]);
$all_lessons = $lessons->fetchAll();

$quizzes = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ?");
$quizzes->execute([$id]);
$all_quizzes = $quizzes->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Editor | <?php echo htmlspecialchars($course['title']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .editor-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        .editor-sidebar {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            border: 1px solid var(--border-color);
            height: fit-content;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
            margin-bottom: 0.25rem;
        }

        .nav-item:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .nav-item.active {
            background: #e0f2fe;
            color: var(--teal-primary);
            font-weight: 600;
        }

        .content-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2rem;
        }

        .item-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            background: #f8fafc;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <div
                        style="font-size: 0.875rem; color: var(--teal-primary); font-weight: 600; margin-bottom: 0.25rem;">
                        COURSE EDITOR</div>
                    <h2><?php echo htmlspecialchars($course['title']); ?></h2>
                </div>
                <div class="header-actions">
                    <a href="training_view.php?id=<?php echo $id; ?>" class="btn-primary" target="_blank">
                        <i data-lucide="eye"></i> ดูตัวอย่าง (Preview)
                    </a>
                </div>
            </header>

            <div class="editor-container">
                <!-- Sidebar Nav -->
                <aside class="editor-sidebar">
                    <a href="?id=<?php echo $id; ?>&tab=settings"
                        class="nav-item <?php echo $tab == 'settings' ? 'active' : ''; ?>">
                        <i data-lucide="settings"></i> ตั้งค่า (Settings)
                    </a>
                    <a href="?id=<?php echo $id; ?>&tab=lessons"
                        class="nav-item <?php echo $tab == 'lessons' ? 'active' : ''; ?>">
                        <i data-lucide="youtube"></i> บทเรียน (Lessons)
                    </a>
                    <a href="?id=<?php echo $id; ?>&tab=quiz" class="nav-item <?php echo $tab == 'quiz' ? 'active' : ''; ?>">
                        <i data-lucide="help-circle"></i> แบบทดสอบ (Quiz)
                    </a>
                </aside>

                <!-- Content Area -->
                <div class="editor-content">

                    <!-- TAB: LESSONS -->
                    <?php if ($tab === 'lessons'): ?>
                        <div class="content-card">
                            <h3 style="margin-bottom: 1.5rem;">เนื้อหาบทเรียน</h3>

                            <form method="POST"
                                style="background: #f8fafc; padding: 1.5rem; border-radius: 0.75rem; border: 1px dashed #cbd5e1; margin-bottom: 2rem;">
                                <input type="hidden" name="add_lesson" value="1">
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">หัวข้อบทเรียน</label>
                                    <input type="text" name="lesson_title" class="form-input" required>
                                </div>
                                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                    <div>
                                        <label class="form-label">YouTube URL</label>
                                        <input type="url" name="video_url" class="form-input"
                                            placeholder="https://youtube.com/watch?v=..." required>
                                    </div>
                                    <div>
                                        <label class="form-label">ความยาว (เช่น 5:00)</label>
                                        <input type="text" name="duration" class="form-input">
                                    </div>
                                </div>
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">รายละเอียดเพิ่มเติม</label>
                                    <textarea name="content" class="form-input" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn-primary"><i data-lucide="plus"></i> เพิ่มบทเรียน</button>
                            </form>

                            <div class="item-list">
                                <?php foreach ($all_lessons as $idx => $l): ?>
                                    <div class="list-item">
                                        <div style="display: flex; gap: 1rem; align-items: center;">
                                            <span style="font-weight: 700; color: #cbd5e1;"><?php echo $idx + 1; ?></span>
                                            <div>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($l['title']); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #64748b;">
                                                    <?php echo htmlspecialchars($l['duration']); ?></div>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $id; ?>&tab=lessons&delete_lesson=<?php echo $l['id']; ?>"
                                            class="action-btn delete" onclick="return confirm('ลบ?');"><i
                                                data-lucide="trash-2"></i></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- TAB: QUIZ -->
                    <?php if ($tab === 'quiz'): ?>
                        <div class="content-card">
                            <h3 style="margin-bottom: 1.5rem;">จัดการแบบทดสอบ</h3>

                            <form method="POST"
                                style="background: #f8fafc; padding: 1.5rem; border-radius: 0.75rem; border: 1px dashed #cbd5e1; margin-bottom: 2rem;">
                                <input type="hidden" name="add_question" value="1">
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">คำถาม</label>
                                    <input type="text" name="question" class="form-input" required>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                    <input type="text" name="option_a" class="form-input" placeholder="ตัวเลือก A" required>
                                    <input type="text" name="option_b" class="form-input" placeholder="ตัวเลือก B" required>
                                    <input type="text" name="option_c" class="form-input" placeholder="ตัวเลือก C" required>
                                    <input type="text" name="option_d" class="form-input" placeholder="ตัวเลือก D" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">ข้อที่ถูก</label>
                                    <select name="correct" class="form-input" style="width: 200px;">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn-primary" style="margin-top: 1rem;"><i
                                        data-lucide="plus"></i> เพิ่มคำถาม</button>
                            </form>

                            <div class="item-list">
                                <?php foreach ($all_quizzes as $idx => $q): ?>
                                    <div class="list-item">
                                        <div style="display: flex; gap: 1rem; align-items: center;">
                                            <span style="font-weight: 700; color: #cbd5e1;">Q<?php echo $idx + 1; ?></span>
                                            <div>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($q['question']); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #10b981;">Correct:
                                                    <?php echo $q['correct_answer']; ?></div>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $id; ?>&tab=quiz&delete_quiz=<?php echo $q['id']; ?>"
                                            class="action-btn delete" onclick="return confirm('ลบ?');"><i
                                                data-lucide="trash-2"></i></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- TAB: SETTINGS -->
                    <?php if ($tab === 'settings'): ?>
                        <div class="content-card">
                            <h3 style="margin-bottom: 1.5rem;">ตั้งค่าหลักสูตร</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="update_settings" value="1">
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">ชื่อคอร์ส</label>
                                    <input type="text" name="title" class="form-input"
                                        value="<?php echo htmlspecialchars($course['title']); ?>">
                                </div>
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">คำอธิบาย</label>
                                    <textarea name="description" class="form-input"
                                        rows="4"><?php echo htmlspecialchars($course['description']); ?></textarea>
                                </div>
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">สิ่งที่ได้รับ (Objectives)</label>
                                    <textarea name="objectives" class="form-input"
                                        rows="3"><?php echo htmlspecialchars($course['objectives']); ?></textarea>
                                </div>
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">ระดับความยาก</label>
                                    <select name="level" class="form-input">
                                        <option value="Beginner" <?php echo $course['level'] == 'Beginner' ? 'selected' : ''; ?>>
                                            Beginner</option>
                                        <option value="Intermediate" <?php echo $course['level'] == 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                        <option value="Advanced" <?php echo $course['level'] == 'Advanced' ? 'selected' : ''; ?>>
                                            Advanced</option>
                                    </select>
                                </div>
                                <div class="form-group margin-bottom-2">
                                    <label class="form-label">อัปโหลดปกใหม่</label>
                                    <input type="file" name="thumbnail" class="form-input">
                                </div>
                                <button type="submit" class="btn-primary">บันทึกการเปลี่ยนแปลง</button>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>