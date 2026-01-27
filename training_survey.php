<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$course_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($course_id === 0 || !is_logged_in()) {
    header("Location: training.php");
    exit;
}

// Check if certificate exists (Must pass quiz first)
$stmt = $pdo->prepare("SELECT * FROM certificates WHERE user_id = ? AND course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$cert = $stmt->fetch();

if (!$cert) {
    die("Access Denied: You must pass the exam first.");
}

// Handle Survey Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int) $_POST['rating'];
    $feedback = $_POST['feedback'];

    $stmt = $pdo->prepare("INSERT INTO survey_responses (user_id, course_id, rating, feedback) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $course_id, $rating, $feedback]);

    // Redirect to Certificate
    header("Location: certificate.php?id=$course_id");
    exit;
}

// Check if already surveyed
$stmt = $pdo->prepare("SELECT * FROM survey_responses WHERE user_id = ? AND course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]);
if ($stmt->fetch()) {
    header("Location: certificate.php?id=$course_id");
    exit;
}

$course = $pdo->query("SELECT title FROM trainings WHERE id = $course_id")->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบประเมินความพึงพอใจ | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .survey-container {
            max-width: 600px;
            margin: 4rem auto;
            padding: 3rem 2rem;
            background: white;
            border-radius: 1.5rem;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .star-rating {
            direction: rtl;
            display: inline-flex;
            justify-content: center;
            margin: 1.5rem 0;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            font-size: 2.5rem;
            color: #cbd5e1;
            cursor: pointer;
            transition: 0.2s;
            padding: 0 0.25rem;
        }

        .star-rating input:checked~label,
        .star-rating label:hover,
        .star-rating label:hover~label {
            color: #fbbf24;
        }
    </style>
</head>

<body style="background: #f8fafc;">

    <div class="survey-container">
        <div
            style="width: 80px; height: 80px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto;">
            <i data-lucide="check" style="width: 40px; height: 40px;"></i>
        </div>

        <h1 style="margin-bottom: 0.5rem; color: #0f172a;">ยินดีด้วย! คุณสอบผ่านแล้ว</h1>
        <p style="color: #64748b; margin-bottom: 2rem;">กรุณาทำแบบประเมินความพึงพอใจก่อนรับใบประกาศนียบัตร</p>

        <form method="POST">
            <label style="font-weight: 600;">ความพึงพอใจต่อหลักสูตรนี้</label>
            <div class="star-rating">
                <input type="radio" id="star5" name="rating" value="5" required /><label for="star5">★</label>
                <input type="radio" id="star4" name="rating" value="4" /><label for="star4">★</label>
                <input type="radio" id="star3" name="rating" value="3" /><label for="star3">★</label>
                <input type="radio" id="star2" name="rating" value="2" /><label for="star2">★</label>
                <input type="radio" id="star1" name="rating" value="1" /><label for="star1">★</label>
            </div>

            <div style="text-align: left; margin-bottom: 1.5rem;">
                <label class="form-label" style="margin-bottom: 0.5rem; display: block;">ข้อเสนอแนะเพิ่มเติม
                    (Optional)</label>
                <textarea name="feedback" class="form-input" rows="3"
                    placeholder="บอกให้เรารู้ว่าควรปรับปรุงตรงไหน..."></textarea>
            </div>

            <button type="submit" class="btn-primary"
                style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.1rem; border-radius: 3rem;">
                ส่งแบบประเมินและรับใบประกาศฯ
            </button>
        </form>
    </div>

    <script>lucide.createIcons();</script>
</body>

</html>