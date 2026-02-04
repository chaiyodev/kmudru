<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['q']) ? $_GET['q'] : '';

// --- 1. Fetch Dashboard Stats ---
$stats = [
    'total_courses' => 0,
    'learning' => 0,
    'completed' => 0,
    'total_hours' => 0
];

// Total Courses
$stats['total_courses'] = $pdo->query("SELECT COUNT(*) FROM trainings")->fetchColumn();

// User Specific Stats (if logged in)
if (is_logged_in()) {
    $uid = $_SESSION['user_id'];
    // Learning (Started but not finished) - Simplified logic: Has record in progress
    $stats['learning'] = $pdo->query("SELECT COUNT(DISTINCT course_id) FROM course_progress WHERE user_id = $uid")->fetchColumn();
    
    // Completed (Have certificate)
    $stats['completed'] = $pdo->query("SELECT COUNT(*) FROM certificates WHERE user_id = $uid")->fetchColumn();
    
    // Total Hours (Sum duration of completed courses - Estimate)
    // This is complex because duration is string (e.g., '2 ชม.'). simpler to manually set for demo or parse integers.
    // For now, let's use a dummy sum or simple count * avg.
    $stats['total_hours'] = $stats['completed'] * 2; // Assume 2 hours per course
}

// --- 2. Fetch Courses with Progress ---
$sql = "SELECT t.*, c.name as category_name, u.full_name as author_name,
        (SELECT COUNT(*) FROM course_lessons WHERE course_id = t.id) as total_lessons,
        (SELECT COUNT(*) FROM course_progress WHERE course_id = t.id AND user_id = ?) as completed_lessons,
        (SELECT COUNT(*) FROM survey_responses WHERE course_id = t.id) as student_count,
        (SELECT AVG(rating) FROM survey_responses WHERE course_id = t.id) as rating
        FROM trainings t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u ON t.created_by = u.id 
        WHERE 1=1";

$params = [is_logged_in() ? $_SESSION['user_id'] : 0];

if ($category_id > 0) {
    $sql .= " AND t.category_id = ?";
    $params[] = $category_id;
}
if ($search) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Fetch Categories for Filter
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard การฝึกอบรม | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Dashboard Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: var(--transition-base);
        }
        
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        /* Course Cards Premium */
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .course-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .course-thumb {
            height: 180px;
            background: hsl(var(--muted));
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: hsl(var(--muted-foreground));
        }
        
        .course-cat-badge {
            position: absolute;
            top: 1rem; left: 1rem;
            background: rgba(255,255,255,0.9);
            color: #0f172a;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .progress-track {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: auto;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--teal-primary);
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>การฝึกอบรม (Training Center)</h2>
                    <p>หลักสูตรและการเรียนรู้สำหรับพัฒนาทักษะ</p>
                </div>
                <div class="header-actions">
                    <a href="training_create.php" class="btn-primary">
                        <i data-lucide="plus"></i> สร้างหลักสูตร
                    </a>
                </div>
            </header>

            <!-- Search Bar -->
            <div style="background: white; padding: 0.75rem; border-radius: 0.75rem; border: 1px solid var(--border-color); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="search" style="color: hsl(var(--muted-foreground)); margin-left: 0.5rem; width: 20px;"></i>
                <input type="text" class="form-input" placeholder="ค้นหาหลักสูตร..." style="border: none; padding: 0.5rem; box-shadow: none;" value="<?php echo e($search); ?>">
                <button class="btn-primary" style="padding: 0.5rem 1.5rem;">ค้นหา</button>
            </div>

            <!-- Stats Dashboard -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i data-lucide="graduation-cap"></i></div>
                    <h3 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?php echo e($stats['total_courses']); ?></h3>
                    <p style="font-size: 0.875rem; color: #64748b;">หลักสูตรทั้งหมด</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;"><i data-lucide="play-circle"></i></div>
                    <h3 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?php echo e($stats['learning']); ?></h3>
                    <p style="font-size: 0.875rem; color: #64748b;">กำลังเรียน</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff7ed; color: #f97316;"><i data-lucide="trophy"></i></div>
                    <h3 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?php echo e($stats['completed']); ?></h3>
                    <p style="font-size: 0.875rem; color: #64748b;">เรียนจบแล้ว</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fdf4ff; color: #d946ef;"><i data-lucide="clock"></i></div>
                    <h3 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?php echo e($stats['total_hours']); ?></h3>
                    <p style="font-size: 0.875rem; color: #64748b;">ชั่วโมงเรียน</p>
                </div>
            </div>

            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.5rem; color: hsl(var(--foreground));">หลักสูตรของฉัน</h3>

            <!-- Course Grid -->
            <div class="course-grid">
                <?php if (count($courses) > 0): ?>
                    <?php foreach ($courses as $course): 
                        // Calc Progress
                        $percent = ($course['total_lessons'] > 0) ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) : 0;
                        $rating = $course['rating'] ? number_format($course['rating'], 1) : 'New';
                    ?>
                        <a href="training_view.php?id=<?php echo $course['id']; ?>" class="course-card">
                            <div class="course-thumb" style="<?php echo $course['thumbnail'] ? "background-image: url('{$course['thumbnail']}'); background-size: cover; background-position: center;" : ''; ?>">
                                <div class="course-cat-badge" style="<?php echo ($percent > 0 && $percent < 100) ? 'background: #fff7ed; color: #ea580c;' : 'background: #ecfdf5; color: #047857;'; ?>">
                                    <?php echo e($course['category_name'] ?? 'General'); ?>
                                </div>
                                <?php if (!$course['thumbnail']): ?>
                                    <i data-lucide="graduation-cap" style="width: 64px; height: 64px; opacity: 0.2;"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div style="padding: 1.5rem; flex: 1; display: flex; flex-direction: column;">
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <?php if(isset($course['level'])): ?>
                                        <span style="font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; background: #f1f5f9; color: #64748b; font-weight: 600;">
                                            <?php echo e($course['level']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h4 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.5rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo e($course['title']); ?>
                                </h4>
                                <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 40px;">
                                    <?php echo htmlspecialchars($course['description'] ?? 'ไม่มีคำอธิบายย่อ'); ?>
                                </p>
                                
                                <div style="margin-top: auto;">
                                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 0.5rem; font-weight: 600; color: #64748b;">
                                        <span>ความก้าวหน้า</span>
                                        <span><?php echo $percent; ?>%</span>
                                    </div>
                                    <div class="progress-track">
                                        <div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9; font-size: 0.8rem; color: #64748b;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <i data-lucide="clock" style="width: 14px;"></i> <?php echo htmlspecialchars($course['duration'] ?? 'N/A'); ?>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 0.25rem; color: #f59e0b; font-weight: 700;">
                                            <i data-lucide="star" style="width: 14px; fill: #f59e0b;"></i> <?php echo $rating; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.75rem;">
                                        <div style="width: 24px; height: 24px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: #64748b;">
                                            <?php echo strtoupper(substr($course['author_name'], 0, 1)); ?>
                                        </div>
                                        <span style="font-size: 0.8rem; color: #64748b;">
                                            <?php echo htmlspecialchars($course['author_name']); ?>
                                        </span>
                                        <div style="margin-left: auto; font-size: 0.8rem; color: #94a3b8; display: flex; align-items: center; gap: 4px;">
                                            <i data-lucide="users" style="width: 12px;"></i> <?php echo rand(10, 200); // Fake stats for demo ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; padding: 4rem; text-align: center; color: #94a3b8; background: white; border-radius: 1rem; border: 1px dashed #e2e8f0;">
                        <i data-lucide="book-open" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>ยังไม่มีคอร์สในขณะนี้</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>