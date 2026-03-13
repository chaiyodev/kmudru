<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id === 0) {
    header("Location: training.php");
    exit;
}

// Fetch Course Details
$stmt = $pdo->prepare("SELECT t.*, c.name as category_name, u.full_name as author_name, u.username 
                       FROM trainings t 
                       LEFT JOIN categories c ON t.category_id = c.id 
                       LEFT JOIN users u ON t.created_by = u.id 
                       WHERE t.id = ?");
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course) {
    die("Course not found.");
}

// Fetch Lessons
$stmt = $pdo->prepare("SELECT * FROM course_lessons WHERE course_id = ? ORDER BY order_index ASC");
$stmt->execute([$id]);
$lessons = $stmt->fetchAll();

// Handle Completion Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_lesson'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
    $lesson_to_complete = (int) $_POST['complete_lesson'];
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO course_progress (user_id, lesson_id, course_id) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $lesson_to_complete, $id]);
        log_activity('lesson_complete', 'training', "Course ID: $id | Lesson ID: $lesson_to_complete");

        // Find next lesson
        $next_lesson_id = 0;
        foreach ($lessons as $index => $l) {
            if ($l['id'] == $lesson_to_complete && isset($lessons[$index + 1])) {
                $next_lesson_id = $lessons[$index + 1]['id'];
                break;
            }
        }

        // Redirect to next lesson or stay
        if ($next_lesson_id) {
            header("Location: training_view.php?id=$id&lesson=$next_lesson_id");
        } else {
            header("Location: training_view.php?id=$id&lesson=$lesson_to_complete");
        }
        exit;
    } catch (PDOException $e) { /* Error handling could go here */
    }
}

// Determine Active Lesson
$active_lesson = null;
$lesson_id = isset($_GET['lesson']) ? (int) $_GET['lesson'] : 0;

if ($lesson_id > 0) {
    foreach ($lessons as $l) {
        if ($l['id'] == $lesson_id) {
            $active_lesson = $l;
            break;
        }
    }
} else if (!empty($lessons)) {
    $active_lesson = $lessons[0];
}

// Fetch Progress (User Specific)
$completed_lessons = [];
if (is_logged_in()) {
    $stmt = $pdo->prepare("SELECT lesson_id FROM course_progress WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$_SESSION['user_id'], $id]);
    $completed_lessons = $stmt->fetchAll(PDO::FETCH_COLUMN); // Returns array of lesson_ids
}

$total_lessons = count($lessons);
$completed_count = count($completed_lessons);
$progress_percent = $total_lessons > 0 ? round(($completed_count / $total_lessons) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($active_lesson ? $active_lesson['title'] : $course['title']); ?> | Course Player
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Zen Mode Layout */
        body {
            background: #f8fafc;
        }

        .player-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 0;
            height: 100vh;
            overflow: hidden;
        }

        .main-stage {
            padding: 2rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .playlist-sidebar {
            background: white;
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .video-container {
            width: 100%;
            aspect-ratio: 16/9;
            background: black;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 2rem;
        }

        .progress-container {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .progress-bar-bg {
            height: 8px;
            background: hsl(var(--muted));
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar-fill {
            height: 100%;
            background: var(--teal-primary);
            width:
                <?php echo $progress_percent; ?>
                %;
            transition: width 0.3s ease;
        }

        .lesson-item {
            display: flex;
            padding: 1rem 1.5rem;
            gap: 1rem;
            border-bottom: 1px solid hsl(var(--border) / 0.5);
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            align-items: flex-start;
        }

        .lesson-item:hover {
            background: #f1f5f9;
        }

        .lesson-item.active {
            background: #e0f2fe;
            /* Light blueish tint */
            border-left: 4px solid var(--teal-primary);
        }

        .lesson-item.completed .lesson-title {
            color: #15803d;
            /* Green */
        }

        .check-circle {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .check-circle.checked {
            background: #22c55e;
            border-color: #22c55e;
            color: white;
        }

        .back-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            color: hsl(var(--muted-foreground));
            text-decoration: none;
            font-weight: 500;
        }

        .back-nav:hover {
            color: var(--teal-primary);
        }

        /* Hide standard Sidebar/Header for Zen Mode */
        /* Note: We are creating a custom layout, so we simply won't include sidebar.php */
    </style>
</head>

<body>
    <div class="player-grid">
        <!-- Main Stage (Video & Content) -->
        <div class="main-stage">
            <div style="max-width: 900px; margin: 0 auto; width: 100%;">

                <a href="training.php" class="back-nav">
                    <i data-lucide="arrow-left" style="width: 18px;"></i> ออกจากห้องเรียน (Back to Courses)
                </a>

                <?php
                // YouTube ID Extraction
                $video_id = '';
                if ($active_lesson) {
                    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $active_lesson['video_url'], $matches);
                    $video_id = $matches[1] ?? '';
                }
                ?>

                <div class="video-container">
                    <?php if ($video_id): ?>
                        <iframe width="100%" height="100%"
                            src="https://www.youtube.com/embed/<?php echo $video_id; ?>?modestbranding=1&rel=0"
                            frameborder="0" allowfullscreen></iframe>
                    <?php else: ?>
                        <div
                            style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: white;">
                            <i data-lucide="play" style="width: 64px; height: 64px; opacity: 0.5;"></i>
                            <p style="margin-top: 1rem; opacity: 0.8;">เลือกบทเรียนเพื่อเริ่ม</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div
                    style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
                    <div>
                        <h1 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">
                            <?php echo e($active_lesson ? $active_lesson['title'] : $course['title']); ?>
                        </h1>
                        <p style="color: hsl(var(--muted-foreground));">
                            <?php echo e($course['title']); ?> • บทที่
                            <?php echo e($active_lesson['order_index'] ?? '-'); ?>
                        </p>
                    </div>

                    <?php if ($active_lesson && !in_array($active_lesson['id'], $completed_lessons)): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="complete_lesson" value="<?php echo e($active_lesson['id']); ?>">
                            <button type="submit" class="btn-primary"
                                style="background: var(--teal-primary); border: none; padding: 0.75rem 1.5rem;">
                                <i data-lucide="check-circle-2"></i> เรียนจบแล้ว (Mark Complete)
                            </button>
                        </form>
                    <?php elseif ($active_lesson): ?>
                        <div class="btn-primary"
                            style="background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); border: 1px solid hsl(142 76% 36% / 0.2); cursor: default;">
                            <i data-lucide="check"></i> เรียนจบแล้ว
                        </div>
                    <?php endif; ?>
                </div>

                <div
                    style="background: white; padding: 2rem; border-radius: 1rem; border: 1px solid var(--border-color);">
                    <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">คำอธิบายบทเรียน</h3>
                    <div style="line-height: 1.7; color: hsl(var(--foreground));">
                        <?php echo nl2br(e($active_lesson['content'] ?? $course['description'])); ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Sidebar Playlist -->
        <aside class="playlist-sidebar">
            <div class="progress-container">
                <h4
                    style="font-size: 0.875rem; font-weight: 700; color: hsl(var(--muted-foreground)); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                    ความคืบหน้าของคุณ
                </h4>
                <div
                    style="display: flex; justify-content: space-between; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">
                    <span><?php echo $progress_percent; ?>% เสร็จสิ้น</span>
                    <span><?php echo $completed_count; ?>/<?php echo $total_lessons; ?> บท</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill"></div>
                </div>

                <!-- Exam / Certificate Actions -->
                <?php
                // Check if passed
                $is_passed = false;
                if (is_logged_in()) {
                    $chk = $pdo->prepare("SELECT passed FROM user_quiz_results WHERE user_id = ? AND course_id = ? AND passed = 1");
                    $chk->execute([$_SESSION['user_id'], $id]);
                    $is_passed = $chk->fetch();
                }

                if ($is_passed): ?>
                    <a href="certificate.php?id=<?php echo $id; ?>" target="_blank" class="btn-primary"
                        style="margin-top: 1rem; justify-content: center; background: #fbbf24; border: none; color: #78350f;">
                        <i data-lucide="award"></i> ดาวน์โหลดใบประกาศฯ
                    </a>
                <?php elseif ($progress_percent >= 100): ?>
                    <a href="training_quiz.php?id=<?php echo $id; ?>" class="btn-primary"
                        style="margin-top: 1rem; justify-content: center; background: #0f172a; border: none;">
                        <i data-lucide="pen-tool"></i> ทำแบบทดสอบ (Final Exam)
                    </a>
                <?php endif; ?>

                <?php if (is_logged_in() && ($_SESSION['role'] == 'admin' || $_SESSION['user_id'] == $course['created_by'])): ?>
                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                        <a href="training_create.php?id=<?php echo $id; ?>&tab=lessons"
                            style="flex: 1; text-align: center; font-size: 0.75rem; color: var(--teal-primary); border: 1px dashed var(--teal-primary); border-radius: 0.5rem; padding: 0.5rem; text-decoration: none;">
                            แก้ไขเนื้อหา
                        </a>
                        <a href="training_create.php?id=<?php echo $id; ?>&tab=quiz"
                            style="flex: 1; text-align: center; font-size: 0.75rem; color: #f59e0b; border: 1px dashed #f59e0b; border-radius: 0.5rem; padding: 0.5rem; text-decoration: none;">
                            แก้ไขข้อสอบ
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div style="flex: 1; overflow-y: auto;">
                <?php if (empty($lessons)): ?>
                    <div style="padding: 2rem; text-align: center; color: hsl(var(--muted-foreground));">
                        ยังไม่มีบทเรียน
                    </div>
                <?php else: ?>
                    <?php foreach ($lessons as $l):
                        $is_completed = in_array($l['id'], $completed_lessons);
                        $is_active = ($active_lesson && $active_lesson['id'] == $l['id']);
                        ?>
                        <a href="training_view.php?id=<?php echo $id; ?>&lesson=<?php echo $l['id']; ?>"
                            class="lesson-item <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>">

                            <div class="check-circle <?php echo $is_completed ? 'checked' : ''; ?>">
                                <?php if ($is_completed): ?>
                                    <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                                <?php elseif ($is_active): ?>
                                    <i data-lucide="play"
                                        style="width: 10px; height: 10px; fill: #94a3b8; color: #94a3b8; margin-left: 2px;"></i>
                                <?php else: ?>
                                    <span
                                        style="font-size: 10px; font-weight: 600; color: #cbd5e1;"><?php echo $l['order_index']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div>
                                <div class="lesson-title" style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($l['title']); ?>
                                </div>
                                <div
                                    style="font-size: 0.8rem; color: hsl(var(--muted-foreground)); display: flex; align-items: center; gap: 4px;">
                                    <i data-lucide="clock" style="width: 12px;"></i>
                                    <?php echo htmlspecialchars($l['duration']); ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>