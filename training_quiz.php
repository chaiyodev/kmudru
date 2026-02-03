<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$course_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($course_id === 0 || !is_logged_in()) {
    header("Location: training.php");
    exit;
}

// Check Previous Result
$stmt = $pdo->prepare("SELECT * FROM user_quiz_results WHERE user_id = ? AND course_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$last_result = $stmt->fetch();

// Determine Action
$show_quiz = true;
$result_mode = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process Submission
    $score = 0;
    $total = 0;

    // Fetch Correct Answers
    $questions = $pdo->prepare("SELECT id, correct_answer FROM quizzes WHERE course_id = ?");
    $questions->execute([$course_id]);
    $answers = $questions->fetchAll(PDO::FETCH_KEY_PAIR); // [id => correct_answer]

    foreach ($answers as $q_id => $correct) {
        $total++;
        if (isset($_POST["q_$q_id"]) && $_POST["q_$q_id"] === $correct) {
            $score++;
        }
    }

    $percent = ($total > 0) ? ($score / $total) * 100 : 0;
    $passed = ($percent >= 80);

    // Save Result
    $stmt = $pdo->prepare("INSERT INTO user_quiz_results (user_id, course_id, score, total_questions, passed) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $course_id, $score, $total, $passed ? 1 : 0]);

    if ($passed) {
        // Issue Certificate if not exists
        $stmt = $pdo->prepare("INSERT IGNORE INTO certificates (user_id, course_id, certificate_code) VALUES (?, ?, UUID())");
        $stmt->execute([$_SESSION['user_id'], $course_id]);
        header("Location: training_survey.php?id=$course_id&passed=1"); // Go to survey first
        exit;
    } else {
        $result_mode = true;
    }
} else if ($last_result && $last_result['passed']) {
    // Already Passed
    header("Location: training_view.php?id=$course_id"); // Or certificate page
    exit;
}

// Fetch Questions
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? ORDER BY RAND()");
$stmt->execute([$course_id]);
$quiz_questions = $stmt->fetchAll();

if (count($quiz_questions) == 0)
    die("ยังไม่มีแบบทดสอบสำหรับคอร์สนี้");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบทดสอบหลังเรียน | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        .quiz-container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 2rem;
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
        }

        .question-item {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .option-label {
            display: block;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .option-label:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        input:checked+.option-text {
            font-weight: 700;
            color: var(--teal-primary);
        }

        input[type="radio"] {
            margin-right: 0.5rem;
        }
    </style>
</head>

<body style="background: #f1f5f9;">

    <div class="quiz-container">
        <?php if ($result_mode): ?>
            <div style="text-align: center; padding: 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">😢</div>
                <h1 style="color: #ef4444; margin-bottom: 1rem;">เสียใจด้วย คุณสอบไม่ผ่าน</h1>
                <p style="font-size: 1.2rem; margin-bottom: 2rem;">คุณทำได้
                    <?php echo $score; ?> /
                    <?php echo $total; ?> คะแนน (
                    <?php echo round($percent); ?>%)<br>ต้องได้ 80% ขึ้นไปถึงจะผ่าน
                </p>
                <a href="training_quiz.php?id=<?php echo $course_id; ?>" class="btn-primary"
                    style="justify-content: center;">ทำแบบทดสอบใหม่</a>
            </div>
        <?php else: ?>
            <div style="text-align: center; margin-bottom: 3rem;">
                <h1 style="margin-bottom: 0.5rem;">แบบทดสอบหลังเรียน</h1>
                <p style="color: #64748b;">ตอบคำถามให้ถูกต้องที่สุด (เกณฑ์ผ่าน 80%)</p>
            </div>

            <form method="POST">
                <?php foreach ($quiz_questions as $index => $q):
                    $opts = json_decode($q['options'], true);
                    ?>
                    <div class="question-item">
                        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">
                            <?php echo ($index + 1) . '. ' . htmlspecialchars($q['question']); ?>
                        </h3>

                        <label class="option-label">
                            <input type="radio" name="q_<?php echo $q['id']; ?>" value="A" required>
                            <span class="option-text">A)
                                <?php echo htmlspecialchars($opts['A']); ?>
                            </span>
                        </label>
                        <label class="option-label">
                            <input type="radio" name="q_<?php echo $q['id']; ?>" value="B" required>
                            <span class="option-text">B)
                                <?php echo htmlspecialchars($opts['B']); ?>
                            </span>
                        </label>
                        <label class="option-label">
                            <input type="radio" name="q_<?php echo $q['id']; ?>" value="C" required>
                            <span class="option-text">C)
                                <?php echo htmlspecialchars($opts['C']); ?>
                            </span>
                        </label>
                        <label class="option-label">
                            <input type="radio" name="q_<?php echo $q['id']; ?>" value="D" required>
                            <span class="option-text">D)
                                <?php echo htmlspecialchars($opts['D']); ?>
                            </span>
                        </label>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-primary"
                    style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.2rem;">ส่งคำตอบ (Submit
                    Exam)</button>
            </form>
        <?php endif; ?>
    </div>

</body>

</html>