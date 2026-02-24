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
        } elseif ($_POST['action'] === 'update_role') {
            $user_id = $_POST['user_id'];
            $new_role = $_POST['role'];
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                $message = "อัปเดตบทบาทเรียบร้อยแล้ว";
            } catch (PDOException $e) {
                $error = "เกิดข้อผิดพลาดในการอัปเดตบทบาท";
            }
        } elseif ($_POST['action'] === 'toggle_status') {
            $user_id = $_POST['user_id'];
            $new_status = $_POST['status'];
            if ($user_id != $_SESSION['user_id']) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    $message = $new_status === 'suspended' ? "ระงับการใช้งานสมาชิกเรียบร้อยแล้ว" : "คืนสิทธิ์การใช้งานสมาชิกเรียบร้อยแล้ว";
                } catch (PDOException $e) {
                    $error = "เกิดข้อผิดพลาดในการเปลี่ยนสถานะ";
                }
            } else {
                $error = "คุณไม่สามารถระงับบัญชีตัวเองได้";
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
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
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
                <div
                    style="background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div
                    style="background: hsl(0 84% 60% / 0.1); color: hsl(0 84% 60%); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                    <?php echo $error; ?>
                </div>
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
                            <th style="padding: 1rem;">สถานะ</th>
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
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="role" onchange="this.form.submit()" class="form-select"
                                            style="padding: 4px 8px; font-size: 0.8125rem; width: auto; min-width: 120px;">
                                            <option value="reader" <?php echo $user['role'] === 'reader' ? 'selected' : ''; ?>>Reader</option>
                                            <option value="contributor" <?php echo $user['role'] === 'contributor' ? 'selected' : ''; ?>>Contributor</option>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <option value="admin" selected>Admin</option>
                                            <?php endif; ?>
                                        </select>
                                    </form>
                                </td>
                                <td style="padding: 1rem;">
                                    <span class="badge"
                                        style="background: <?php echo $user['status'] === 'active' ? '#10b981' : '#ef4444'; ?>; color: white;">
                                        <?php echo $user['status'] === 'suspended' ? 'ระงับการใช้งาน' : 'ปกติ'; ?>
                                    </span>
                                </td>
                                <td
                                    style="padding: 1rem; text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                    <a href="admin_logs.php?user_id=<?php echo $user['id']; ?>" class="btn-icon"
                                        title="View Logs" style="color: #64748b;">
                                        <i data-lucide="history" style="width: 18px;"></i>
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="status"
                                                value="<?php echo $user['status'] === 'active' ? 'suspended' : 'active'; ?>">
                                            <button type="submit" class="btn-icon"
                                                style="color: <?php echo $user['status'] === 'active' ? '#f59e0b' : '#10b981'; ?>;"
                                                title="<?php echo $user['status'] === 'active' ? 'ระงับการใช้งาน' : 'คืนสิทธิ์การใช้งาน'; ?>">
                                                <i data-lucide="<?php echo $user['status'] === 'active' ? 'user-x' : 'user-check'; ?>"
                                                    style="width: 18px;"></i>
                                            </button>
                                        </form>

                                        <form method="POST" onsubmit="return confirm('ยืนยันการลบสมาชิกนี้?');"
                                            style="display: inline;">
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