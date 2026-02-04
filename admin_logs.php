<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/logger.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$pdo = get_pdo();

// Filters from GET
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filter_action = isset($_GET['action']) ? $_GET['action'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base Query
$sql = "SELECT l.*, u.username, u.full_name, u.avatar 
        FROM activity_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        WHERE 1=1";

$params = [];

if ($filter_user) {
    $sql .= " AND l.user_id = ?";
    $params[] = $filter_user;
}

if ($filter_action) {
    $sql .= " AND l.action = ?";
    $params[] = $filter_action;
}

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR l.action LIKE ? OR l.details LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

$sql .= " ORDER BY l.created_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch unique actions for filter
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Fetch users for filter
$users_list = $pdo->query("SELECT id, full_name, username FROM users ORDER BY full_name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Activity Audit | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .audit-container {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .filter-bar {
            padding: 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
        }

        .log-table th {
            padding: 1rem 1.5rem;
            text-align: left;
            background: white;
            font-size: 0.75rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }

        .log-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
            transition: var(--transition-base);
        }

        .user-cell:hover {
            color: var(--teal-primary);
        }

        .avatar-box {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #f1f5f9;
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-weight: 700;
            font-size: 0.75rem;
        }

        .action-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .ip-text {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .timestamp {
            color: #64748b;
            font-size: 0.8125rem;
        }

        .details-box {
            font-size: 0.8125rem;
            color: #64748b;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .empty-state {
            padding: 4rem;
            text-align: center;
            color: #94a3b8;
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
                        <span style="font-size: 0.875rem; font-weight: 600; color: var(--teal-primary);">Admin Tools</span>
                        <i data-lucide="chevron-right" style="width: 14px; color: #94a3b8;"></i>
                        <span style="font-size: 0.875rem; color: #64748b;">Activity Audit</span>
                    </div>
                    <h2>ระบบบันทึกเหตุการณ์ (Activity Logs)</h2>
                </div>
                <a href="admin_dashboard.php" class="btn-primary" style="background: white; color: #1e293b; border: 1px solid var(--border-color);">
                    <i data-lucide="arrow-left"></i> กลับแผงควบคุม
                </a>
            </header>

            <div class="audit-container">
                <div class="filter-bar">
                    <form method="GET" style="display: flex; gap: 0.75rem; flex: 1;">
                        <div style="position: relative; flex: 1;">
                            <i data-lucide="search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); width: 16px; color: #94a3b8;"></i>
                            <input type="text" name="search" class="form-input" placeholder="ค้นหาเหตุการณ์..." style="padding-left: 2.5rem;" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <select name="user_id" class="form-input" style="width: 200px;">
                            <option value="">ผู้ใช้งานทั้งหมด</option>
                            <?php foreach ($users_list as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="action" class="form-input" style="width: 180px;">
                            <option value="">เหตุการณ์ทั้งหมด</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?php echo $act; ?>" <?php echo $filter_action == $act ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($act); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn-primary" style="padding: 0.5rem 1.25rem;">
                            กรองข้อมูล
                        </button>
                        
                        <?php if ($filter_user || $filter_action || $search): ?>
                            <a href="admin_logs.php" class="btn-primary" style="background: #f1f5f9; color: #64748b;">
                                <i data-lucide="x"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div style="overflow-x: auto;">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>User / Subject</th>
                                <th>Action Performed</th>
                                <th>Relevant Details</th>
                                <th>IP & Location</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <a href="admin_logs.php?user_id=<?php echo $log['user_id']; ?>" class="user-cell">
                                            <div class="avatar-box" style="background-image: url('uploads/avatars/<?php echo $log['avatar'] ?? 'default.png'; ?>');">
                                                <?php if (!$log['avatar'] || $log['avatar'] == 'default.png') echo strtoupper(substr($log['username'] ?? 'G', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 700;"><?php echo htmlspecialchars($log['full_name'] ?? 'Guest User'); ?></div>
                                                <div style="font-size: 0.7rem; color: #94a3b8;">@<?php echo htmlspecialchars($log['username'] ?? 'anonymous'); ?></div>
                                            </div>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="action-badge"><?php echo htmlspecialchars($log['action']); ?></span>
                                    </td>
                                    <td>
                                        <div class="details-box" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
                                        </div>
                                    </td>
                                    <td class="ip-text">
                                        <i data-lucide="map-pin" style="width: 12px; vertical-align: middle; opacity: 0.5;"></i>
                                        <?php echo htmlspecialchars($log['ip_address']); ?>
                                    </td>
                                    <td class="timestamp">
                                        <?php echo date('M j, Y • H:i', strtotime($log['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i data-lucide="info" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.2;"></i>
                                        <p>ไม่พบข้อมูลบันทึกเหตุการณ์ที่ตรงตามเงื่อนไข</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <p style="margin-top: 2rem; font-size: 0.8125rem; color: #94a3b8; text-align: center;">
                <i data-lucide="shield-check" style="width: 14px; vertical-align: middle;"></i> 
                Data integrity confirmed. Logging active since deployment.
            </p>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>