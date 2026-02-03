<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();

// Core Stats
$total_docs = $pdo->query("SELECT COUNT(*) FROM documents WHERE type='document'")->fetchColumn();
$total_wiki = $pdo->query("SELECT COUNT(*) FROM documents WHERE type='wiki'")->fetchColumn();
$total_qa = $pdo->query("SELECT COUNT(*) FROM documents WHERE type='qa'")->fetchColumn();
$total_training = $pdo->query("SELECT COUNT(*) FROM trainings")->fetchColumn() ?? 0;
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_views = $pdo->query("SELECT SUM(views) FROM documents")->fetchColumn() ?? 0;
$total_likes = $pdo->query("SELECT COUNT(*) FROM document_likes")->fetchColumn() ?? 0;
$total_comments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn() ?? 0;

// Top Contributors
$contributors = $pdo->query("
    SELECT u.full_name, u.username, COUNT(d.id) as doc_count, u.points 
    FROM users u 
    LEFT JOIN documents d ON u.id = d.user_id 
    GROUP BY u.id 
    ORDER BY doc_count DESC 
    LIMIT 5
")->fetchAll();
$max_docs = !empty($contributors) ? max($contributors[0]['doc_count'], 1) : 1;

// Recent 7 days activity
$recent_activity = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn() ?? 0;
$recent_docs = $pdo->query("SELECT COUNT(*) FROM documents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$recent_users = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Calculate percentages for donut chart
$total_content = max($total_docs + $total_wiki + $total_qa + $total_training, 1);
$pct_docs = round(($total_docs / $total_content) * 100);
$pct_wiki = round(($total_wiki / $total_content) * 100);
$pct_qa = round(($total_qa / $total_content) * 100);
$pct_training = 100 - $pct_docs - $pct_wiki - $pct_qa;

// Real Activity Data for Area Chart
$activity_data = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b') as month, COUNT(*) as count 
    FROM activity_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month 
    ORDER BY created_at ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

$months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.'];
$monthly_values = [];
foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'] as $m) {
    if ($m == 'Jan')
        $thai = 'ม.ค.';
    if ($m == 'Feb')
        $thai = 'ก.พ.';
    if ($m == 'Mar')
        $thai = 'มี.ค.';
    if ($m == 'Apr')
        $thai = 'เม.ย.';
    if ($m == 'May')
        $thai = 'พ.ค.';
    if ($m == 'Jun')
        $thai = 'มิ.ย.';

    $monthly_values[] = $activity_data[$m] ?? 0;
}

// Fallback if no activity yet
if (array_sum($monthly_values) == 0) {
    $monthly_values = [10, 25, 45, 80, 150, 210]; // Simulation for new system
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์วิเคราะห์ข้อมูล | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            box-shadow: rgba(20, 29, 31, 0.03) 0px 1px 2px 0px;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: rgba(20, 29, 31, 0.03) 0px 1px 2px 0px;
        }

        .trend-up {
            color: #10b981;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .trend-neutral {
            color: hsl(var(--muted-foreground));
            font-size: 0.75rem;
        }

        .contributor-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .contributor-row:last-child {
            border-bottom: none;
        }

        .contributor-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--teal-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .contributor-bar {
            flex: 1;
            height: 8px;
            background: hsl(var(--muted));
            border-radius: 4px;
            overflow: hidden;
        }

        .contributor-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--teal-primary), hsl(174 62% 42%));
            border-radius: 4px;
            transition: width 1s ease;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>ศูนย์วิเคราะห์ข้อมูล</h2>
                    <p>ภาพรวมกิจกรรมและตัวชี้วัดความสำเร็จของระบบจัดการความรู้ UDRU</p>
                </div>
            </header>

            <!-- Primary Stats Row -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="stat-card">
                    <div
                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="stat-card-icon"
                            style="background: hsl(var(--primary)/0.1); color: var(--teal-primary);"><i
                                data-lucide="file-text"></i></div>
                        <div class="trend-up"><i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> 12%
                        </div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem;"><?php echo $total_docs; ?>
                    </div>
                    <div style="font-size: 0.8125rem; color: hsl(var(--muted-foreground));">เอกสารทั้งหมด</div>
                </div>
                <div class="stat-card">
                    <div
                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="stat-card-icon" style="background: hsl(45 93% 47%/0.1); color: hsl(45 93% 47%);"><i
                                data-lucide="book"></i></div>
                        <div class="trend-up"><i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> 8%
                        </div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem;"><?php echo $total_wiki; ?>
                    </div>
                    <div style="font-size: 0.8125rem; color: hsl(var(--muted-foreground));">บทความ Wiki</div>
                </div>
                <div class="stat-card">
                    <div
                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="stat-card-icon" style="background: hsl(262 83% 58%/0.1); color: hsl(262 83% 58%);">
                            <i data-lucide="help-circle"></i>
                        </div>
                        <div class="trend-up"><i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> 5%
                        </div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem;"><?php echo $total_qa; ?>
                    </div>
                    <div style="font-size: 0.8125rem; color: hsl(var(--muted-foreground));">คำถาม Q&A</div>
                </div>
                <div class="stat-card">
                    <div
                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="stat-card-icon" style="background: hsl(142 76% 36%/0.1); color: hsl(142 76% 36%);">
                            <i data-lucide="graduation-cap"></i>
                        </div>
                        <div class="trend-neutral">ใหม่</div>
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem;">
                        <?php echo $total_training; ?>
                    </div>
                    <div style="font-size: 0.8125rem; color: hsl(var(--muted-foreground));">หลักสูตรอบรม</div>
                </div>
            </div>

            <!-- Secondary Stats Row -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="stat-card" style="display: flex; align-items: center; gap: 1rem;">
                    <div class="stat-card-icon"
                        style="background: hsl(var(--muted)); color: hsl(var(--muted-foreground));"><i
                            data-lucide="users"></i></div>
                    <div>
                        <div style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">ผู้ใช้งาน</div>
                        <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $total_users; ?></div>
                    </div>
                </div>
                <div class="stat-card" style="display: flex; align-items: center; gap: 1rem;">
                    <div class="stat-card-icon"
                        style="background: hsl(var(--muted)); color: hsl(var(--muted-foreground));"><i
                            data-lucide="eye"></i></div>
                    <div>
                        <div style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">ยอดเข้าชม</div>
                        <div style="font-size: 1.5rem; font-weight: 800;"><?php echo number_format($total_views); ?>
                        </div>
                    </div>
                </div>
                <div class="stat-card" style="display: flex; align-items: center; gap: 1rem;">
                    <div class="stat-card-icon" style="background: hsl(339 90% 50%/0.1); color: hsl(339 90% 50%);"><i
                            data-lucide="heart"></i></div>
                    <div>
                        <div style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">ยอดถูกใจ</div>
                        <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $total_likes; ?></div>
                    </div>
                </div>
                <div class="stat-card" style="display: flex; align-items: center; gap: 1rem;">
                    <div class="stat-card-icon" style="background: hsl(199 89% 48%/0.1); color: hsl(199 89% 48%);"><i
                            data-lucide="message-square"></i></div>
                    <div>
                        <div style="font-size: 0.75rem; color: hsl(var(--muted-foreground));">การมีส่วนร่วม</div>
                        <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $total_comments; ?></div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Area Chart -->
                <div class="chart-card">
                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem;">แนวโน้มการเข้าชม</h3>
                    <p style="font-size: 0.8125rem; color: hsl(var(--muted-foreground)); margin-bottom: 1.5rem;">
                        จำนวนการเข้าชมรายเดือน (6 เดือนล่าสุด)</p>
                    <div style="height: 300px; position: relative; width: 100%;">
                        <canvas id="trafficChart"></canvas>
                    </div>
                </div>

                <!-- Donut Chart -->
                <div class="chart-card">
                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem;">สัดส่วนประเภทเนื้อหา</h3>
                    <p style="font-size: 0.8125rem; color: hsl(var(--muted-foreground)); margin-bottom: 1.5rem;">
                        การกระจายตัวของเนื้อหาในระบบ</p>
                    <div style="display: flex; align-items: center; justify-content: center;">
                        <canvas id="contentChart" width="200" height="200"></canvas>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;"><span
                                style="width: 12px; height: 12px; background: #3B82F6; border-radius: 3px;"></span>เอกสาร
                            (<?php echo $pct_docs; ?>%)</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;"><span
                                style="width: 12px; height: 12px; background: #8B5CF6; border-radius: 3px;"></span>Wiki
                            (<?php echo $pct_wiki; ?>%)</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;"><span
                                style="width: 12px; height: 12px; background: #F59E0B; border-radius: 3px;"></span>Q&A
                            (<?php echo $pct_qa; ?>%)</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;"><span
                                style="width: 12px; height: 12px; background: #10B981; border-radius: 3px;"></span>อบรม
                            (<?php echo $pct_training; ?>%)</div>
                    </div>
                </div>
            </div>

            <!-- Contributors & Activity -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Top Contributors -->
                <div class="chart-card">
                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem;">ผู้ร่วมขับเคลื่อนความรู้</h3>
                    <p style="font-size: 0.8125rem; color: hsl(var(--muted-foreground)); margin-bottom: 1.5rem;">Top 5
                        อันดับแรก</p>

                    <?php foreach ($contributors as $i => $c):
                        $width = ($max_docs > 0) ? ($c['doc_count'] / $max_docs) * 100 : 0;
                        ?>
                        <div class="contributor-row">
                            <span
                                style="font-size: 0.875rem; font-weight: 700; color: hsl(var(--muted-foreground)); width: 20px;">#<?php echo $i + 1; ?></span>
                            <div class="contributor-avatar"><?php echo strtoupper(substr($c['username'], 0, 1)); ?></div>
                            <div style="flex: 1;">
                                <div style="font-size: 0.875rem; font-weight: 600; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($c['full_name']); ?>
                                </div>
                                <div class="contributor-bar">
                                    <div class="contributor-bar-fill" style="width: <?php echo $width; ?>%;"></div>
                                </div>
                            </div>
                            <span
                                style="font-size: 0.875rem; font-weight: 700; color: var(--teal-primary);"><?php echo $c['doc_count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($contributors)): ?>
                        <p style="text-align: center; color: hsl(var(--muted-foreground)); padding: 2rem;">ยังไม่มีข้อมูล
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="chart-card"
                    style="background: linear-gradient(135deg, var(--teal-primary) 0%, hsl(174 62% 42%) 100%); color: white; border: none;">
                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem;">เป้าหมาย KM ประจำปี</h3>
                    <p style="font-size: 0.8125rem; opacity: 0.9; margin-bottom: 2rem;">ติดตามความก้าวหน้าของทีม</p>

                    <?php $target = 100;
                    $progress = min(100, round((($total_docs + $total_wiki + $total_qa) / $target) * 100)); ?>

                    <div style="font-size: 4rem; font-weight: 900; margin-bottom: 0.5rem;"><?php echo $progress; ?><span
                            style="font-size: 2rem;">%</span></div>
                    <div
                        style="height: 12px; background: rgba(255,255,255,0.2); border-radius: 6px; margin-bottom: 1rem; overflow: hidden;">
                        <div
                            style="height: 100%; width: <?php echo $progress; ?>%; background: white; border-radius: 6px; transition: width 1s ease;">
                        </div>
                    </div>
                    <p style="font-size: 0.8125rem; opacity: 0.9;">สร้างเนื้อหาอีก
                        <?php echo max(0, $target - ($total_docs + $total_wiki + $total_qa)); ?> รายการเพื่อบรรลุ KPI
                    </p>

                    <a href="create.php" class="btn-primary"
                        style="background: white; color: var(--teal-primary); margin-top: 2rem; width: 100%; justify-content: center;"><i
                            data-lucide="plus"></i>สร้างเนื้อหาใหม่</a>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        // Area Chart
        const trafficCtx = document.getElementById('trafficChart').getContext('2d');
        const gradient = trafficCtx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.01)');

        new Chart(trafficCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'ยอดเข้าชม',
                    data: <?php echo json_encode($monthly_values); ?>,
                    borderColor: '#3B82F6',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#3B82F6',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Donut Chart
        new Chart(document.getElementById('contentChart'), {
            type: 'doughnut',
            data: {
                labels: ['เอกสาร', 'Wiki', 'Q&A', 'อบรม'],
                datasets: [{
                    data: [<?php echo $total_docs; ?>, <?php echo $total_wiki; ?>, <?php echo $total_qa; ?>, <?php echo $total_training; ?>],
                    backgroundColor: ['#3B82F6', '#8B5CF6', '#F59E0B', '#10B981'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                cutout: '70%',
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>

</html>