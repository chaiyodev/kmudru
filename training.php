<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pdo = get_pdo();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คอร์สการเรียนรู้ | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
    <div class="app-container">
        <!-- Standardized Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>UDRU Academy</h2>
                    <p>ระบบจัดการเรียนรู้เพื่อยกระดับทักษะบุคลากรในยุคดิจิทัล</p>
                </div>
            </header>
            <div
                style="text-align: center; padding: 10rem 0; background: white; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: rgba(20, 29, 31, 0.05) 0px 1px 2px 0px;">
                <div
                    style="width: 80px; height: 80px; background: hsl(var(--primary) / 0.1); color: var(--teal-primary); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem;">
                    <i data-lucide="construction" style="width: 40px; height: 40px;"></i>
                </div>
                <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.75rem;">ขออภัย!
                    ระบบคอร์สเรียนกำลังอยู่ระหว่างการพัฒนา</h3>
                <p style="color: hsl(var(--muted-foreground)); max-width: 440px; margin: 0 auto 2.5rem;">
                    เจ้าหน้าที่กำลังเร่งดำเนินการเพื่อนำเสนอการอบรมที่ดีที่สุดให้กับคุณ
                    โปรดรอติดตามข่าวสารจากทางมหาวิทยาลัยในเร็วๆ นี้</p>
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <a href="index.php" class="btn-primary"
                        style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));">กลับหน้าหลัก</a>
                    <a href="browse.php" class="btn-primary">ไปที่คลังความรู้</a>
                </div>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>