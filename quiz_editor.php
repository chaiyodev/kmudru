<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$course_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($course_id === 0 || !is_logged_in()) {
    header("Location: training.php");
    exit;
}

// Handle POST: Add Question
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question'])) {
    $question = $_POST['question'];
    $options = json_encode([
        'A' => $_POST['option_a'],
        'B' => $_POST['option_b'],
        'C' => $_POST['option_c'],
        'D' => $_POST['option_d']
    ], JSON_UNESCAPED_UNICODE);
    $correct = $_POST['correct'];

    $stmt = $pdo->prepare("INSERT INTO quizzes (course_id, question, options, correct_answer) VALUES (?, ?, ?, ?)");
    $stmt->execute([$course_id, $question, $options, $correct]);
    $message = "เพิ่มคำถามเรียบร้อยแล้ว!";
}

// Handle Delete
if (isset($_GET['delete'])) {
    $q_id = (int) $_GET['delete'];
    $pdo->exec("DELETE FROM quizzes WHERE id = $q_id AND course_id = $course_id");
    header("Location: quiz_editor.php?id=$course_id");
    exit;
}

// Fetch Existing Questions
$questions = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ?");
$questions->execute([$course_id]);
$quiz_list = $questions->fetchAll();

// Fetch Course Info
$course = $pdo->query("SELECT title FROM trainings WHERE id = $course_id")->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแบบทดสอบ | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .split-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>สร้างแบบทดสอบ:
                        <?php echo htmlspecialchars($course['title']); ?>
                    </h2>
                    <p>เพิ่มข้อสอบสำหรับวัดผลผู้เรียน (ผ่านเกณฑ์ 80%)</p>
                </div>
                <div>
                    <a href="training_view.php?id=<?php echo $course_id; ?>" class="btn-primary"
                        style="background: white; color: var(--teal-primary); border: 1px solid var(--border-color);">
                        <i data-lucide="eye"></i> กลับไปหน้าคอร์ส
                    </a>
                </div>
            </header>

            <div class="split-layout">
                <!-- Form -->
                <div
                    style="background: white; padding: 2rem; border-radius: 1rem; border: 1px solid var(--border-color);">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">เพิ่มคำถามใหม่</h3>
                    <?php if ($message): ?>
                        <div
                            style="background: #ecfdf5; color: #059669; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group margin-bottom: 1rem;">
                            <label class="form-label">คำถาม</label>
                            <textarea name="question" class="form-input" rows="3" required></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">ตัวเลือก ก (A)</label>
                                <input type="text" name="option_a" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">ตัวเลือก ข (B)</label>
                                <input type="text" name="option_b" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">ตัวเลือก ค (C)</label>
                                <input type="text" name="option_c" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">ตัวเลือก ง (D)</label>
                                <input type="text" name="option_d" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 1rem;">
                            <label class="form-label">เฉลยข้อที่ถูก</label>
                            <select name="correct" class="form-input" required>
                                <option value="A">ตัวเลือก ก (A)</option>
                                <option value="B">ตัวเลือก ข (B)</option>
                                <option value="C">ตัวเลือก ค (C)</option>
                                <option value="D">ตัวเลือก ง (D)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary"
                            style="width: 100%; justify-content: center; margin-top: 1.5rem;">บันทึกคำถาม</button>
                    </form>
                </div>

                <!-- Preview list -->
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">รายการคำถาม (
                        <?php echo count($quiz_list); ?> ข้อ)
                    </h3>
                    <?php foreach ($quiz_list as $index => $q):
                        $opts = json_decode($q['options'], true);
                        ?>
                        <div
                            style="background: white; padding: 1.5rem; border-radius: 1rem; border: 1px solid var(--border-color); margin-bottom: 1rem; position: relative;">
                            <span
                                style="position: absolute; top: 1rem; right: 1rem; font-weight: 700; color: #10b981;">เฉลย:
                                <?php echo $q['correct_answer']; ?>
                            </span>
                            <h4 style="font-weight: 700; margin-bottom: 0.5rem;">
                                <?php echo ($index + 1) . '. ' . htmlspecialchars($q['question']); ?>
                            </h4>
                            <ul style="list-style: none; padding: 0; font-size: 0.9rem; color: #64748b;">
                                <li>A)
                                    <?php echo htmlspecialchars($opts['A']); ?>
                                </li>
                                <li>B)
                                    <?php echo htmlspecialchars($opts['B']); ?>
                                </li>
                                <li>C)
                                    <?php echo htmlspecialchars($opts['C']); ?>
                                </li>
                                <li>D)
                                    <?php echo htmlspecialchars($opts['D']); ?>
                                </li>
                            </ul>
                            <a href="quiz_editor.php?id=<?php echo $course_id; ?>&delete=<?php echo $q['id']; ?>"
                                onclick="return confirm('ลบข้อนี้?');"
                                style="color: #ef4444; font-size: 0.8rem; text-decoration: none;">[ลบคำถาม]</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>