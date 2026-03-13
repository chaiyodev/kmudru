<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check Admin Access
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$pdo = get_pdo();
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            // Add User Logic
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $full_name = $_POST['full_name'];
            $email = $_POST['email'];
            $role = $_POST['role'];

            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password, $full_name, $email, $role]);
                $message = "เพิ่มสมาชิกเรียบร้อยแล้ว";
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'delete_user') {
            // Delete User Logic
            $user_id = $_POST['user_id'];
            if ($user_id != $_SESSION['user_id']) { // Prevent self-delete
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $message = "ลบสมาชิกเรียบร้อยแล้ว";
                } catch (PDOException $e) {
                    $error = "เกิดข้อผิดพลาดในการลบ";
                }
            } else {
                $error = "คุณไม่สามารถลบบัญชีตัวเองได้";
            }
        }
    }
}

// Fetch Users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสมาชิก | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            width: 100%;
            max-width: 500px;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>จัดการสมาชิก</h2>
                    <p>เพิ่ม ลบ และกำหนดสิทธิ์การใช้งานของสมาชิก</p>
                </div>
                <div class="header-actions">
                    <button onclick="document.getElementById('ddUserModal').classList.add('active')"
                        class="btn-primary">
                        <i data-lucide="plus"></i> เพิ่มสมาชิก
                    </button>
                </div>
            </header>

            <?php if ($message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'ดำเนินการสำเร็จ',
                            text: '<?php echo addslashes($message); ?>',
                            confirmButtonColor: 'var(--teal-primary)'
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'ขออภัย...',
                            text: '<?php echo addslashes($error); ?>',
                            confirmButtonColor: 'var(--teal-primary)'
                        });
                    });
                </script>
            <?php endif; ?>

            <div
                style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; background: hsl(var(--muted) / 0.3);">
                            <th style="padding: 1rem;">User ID</th>
                            <th style="padding: 1rem;">ชื่อผู้ใช้</th>
                            <th style="padding: 1rem;">ชื่อ-นามสกุล</th>
                            <th style="padding: 1rem;">อีเมล</th>
                            <th style="padding: 1rem;">บทบาท (Role)</th>
                            <th style="padding: 1rem; text-align: right;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1rem; color: hsl(var(--muted-foreground));">#
                                    <?php echo $user['id']; ?>
                                </td>
                                <td style="padding: 1rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </td>
                                <td style="padding: 1rem; color: hsl(var(--muted-foreground));">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span class="badge" style="background: <?php
                                    echo match ($user['role']) {
                                        'admin' => 'var(--teal-primary)',
                                        'contributor' => '#3B82F6',
                                        default => 'hsl(var(--muted))'
                                    };
                                    ?>; color: <?php echo $user['role'] === 'reader' ? 'inherit' : 'white'; ?>;">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td
                                    style="padding: 1rem; text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                    <a href="admin_logs.php?user_id=<?php echo $user['id']; ?>" class="btn-icon"
                                        title="View Logs" style="color: #64748b;">
                                        <i data-lucide="history" style="width: 18px;"></i>
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" onsubmit="return confirm('ยืนยันการลบสมาชิกนี้?');"
                                            style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn-icon" style="color: #ef4444;"><i
                                                    data-lucide="trash-2" style="width: 18px;"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add User Modal -->
            <div id="ddUserModal" class="modal">
                <div class="modal-content">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.25rem; font-weight: 700;">เพิ่มสมาชิกใหม่</h3>
                        <button onclick="document.getElementById('ddUserModal').classList.remove('active')"
                            style="background: none; border: none; cursor: pointer;"><i data-lucide="x"></i></button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="add_user">
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="reader">Reader (อ่านอย่างเดียว)</option>
                                <option value="contributor">Contributor (เขียนได้)</option>
                                <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary"
                            style="width: 100%; justify-content: center;">บันทึกสมาชิก</button>
                    </form>
                </div>
            </div>

        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>