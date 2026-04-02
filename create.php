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
    <title>สร้างสรรค์ผลงานใหม่ | UDRU Wisdom</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .hub-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2.5rem;
        }

        .hub-card {
            background: white;
            border-radius: 2rem;
            border: 1px solid var(--border-color);
            padding: 3rem 2rem;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .hub-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            border-color: var(--teal-primary);
        }

        .hub-icon {
            width: 72px;
            height: 72px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            font-size: 1.75rem;
            transition: transform 0.3s ease;
        }

        .hub-card:hover .hub-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .hub-card h3 {
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            color: #0f172a;
        }

        .hub-card p {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
        }

        @media (max-width: 640px) {
            .hub-grid {
                grid-template-columns: 1fr;
            }
            .hub-card {
                padding: 2.5rem 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        <!-- Main Viewport -->
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>เลือกรูปแบบการสร้างเนื้อหา</h2>
                    <p>คุณต้องการแบ่งปันอะไรให้กับชุมชนการเรียนรู้ UDRU ในวันนี้?</p>
                </div>
            </header>

            <div class="hub-grid">
                <a href="upload.php" class="hub-card">
                    <div class="hub-icon" style="background: hsl(var(--primary) / 0.1); color: var(--teal-primary);">
                        <i data-lucide="upload-cloud"></i>
                    </div>
                    <h3>อัปโหลดเอกสาร</h3>
                    <p>แชร์ไฟล์คู่มือ, วิจัย, หรือเอกสารราชการที่น่าสนใจในรูปแบบ PDF, Word, Excel</p>
                </a>

                <a href="wiki_create.php" class="hub-card">
                    <div class="hub-icon" style="background: hsl(45 93% 47% / 0.1); color: hsl(45 93% 47%);">
                        <i data-lucide="book"></i>
                    </div>
                    <h3>เขียนบทความ Wiki</h3>
                    <p>เขียนองค์ความรู้เชิงลึกในรูปแบบสารานุกรมดิจิทัล รองรับการจัดรูปแบบด้วย Markdown</p>
                </a>

                <a href="qa_ask.php" class="hub-card">
                    <div class="hub-icon" style="background: hsl(262 83% 58% / 0.1); color: hsl(262 83% 58%);">
                        <i data-lucide="help-circle"></i>
                    </div>
                    <h3>ตั้งคำถาม (Q&A)</h3>
                    <p>มีข้อสงสัยหรือต้องการขอคำปรึกษา? ตั้งคำถามเพื่อขอความช่วยเหลือจากผู้เชี่ยวชาญ</p>
                </a>

                <a href="cop_create.php" class="hub-card">
                    <div class="hub-icon" style="background: hsl(142 76% 36% / 0.1); color: hsl(142 76% 36%);">
                        <i data-lucide="users"></i>
                    </div>
                    <h3>สร้างกลุ่ม CoP</h3>
                    <p>ตั้งกลุ่มเครือข่ายความร่วมมือทางวิชาชีพเพื่อแลกเปลี่ยนเรียนรู้เฉพาะเรื่อง</p>
                </a>

                <a href="training_create.php" class="hub-card">
                    <div class="hub-icon" style="background: hsl(199 89% 48% / 0.1); color: hsl(199 89% 48%);">
                        <i data-lucide="graduation-cap"></i>
                    </div>
                    <h3>เพิ่มหลักสูตรอบรม</h3>
                    <p>แนะนำคอร์สเรียนหรือหลักสูตรที่น่าสนใจให้กับเพื่อนบุคลากร</p>
                </a>

                <a href="experts_create.php" class="hub-card">
                    <div class="hub-icon" style="background: hsl(339 90% 50% / 0.1); color: hsl(339 90% 50%);">
                        <i data-lucide="user-plus"></i>
                    </div>
                    <h3>ลงทะเบียนผู้เชี่ยวชาญ</h3>
                    <p>ระบุความถนัดของคุณเพื่อให้ระบบช่วยเหลือในการแนะนำองค์ความรู้ที่ถูกต้อง</p>
                </a>
            </div>

            <div
                style="margin-top: 3rem; text-align: center; background: hsl(var(--muted) / 0.3); padding: 2.5rem; border-radius: 1.5rem; border: 1px dashed var(--border-color);">
                <i data-lucide="info" style="color: var(--teal-primary); margin-bottom: 1rem;"></i>
                <h4 style="font-weight: 700; margin-bottom: 0.5rem;">ทุกผลงานมีค่า</h4>
                <p style="font-size: 0.875rem; color: hsl(var(--muted-foreground)); max-width: 500px; margin: 0 auto;">
                    การแบ่งปันความรู้ของคุณจะช่วยยกระดับศักยภาพของบุคลากร UDRU และสร้างสังคมแห่งการเรียนรู้ที่ยั่งยืน
                </p>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>