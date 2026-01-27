<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$course_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($course_id === 0 || !is_logged_in()) {
    header("Location: training.php");
    exit;
}

// Fetch Certificate & Instructor Info
$stmt = $pdo->prepare("SELECT c.*, 
                              u_student.full_name as student_name, 
                              t.title as course_title, 
                              t.duration,
                              u_instructor.full_name as instructor_name,
                              u_instructor.department as instructor_dept
                       FROM certificates c 
                       JOIN users u_student ON c.user_id = u_student.id 
                       JOIN trainings t ON c.course_id = t.id 
                       LEFT JOIN users u_instructor ON t.created_by = u_instructor.id
                       WHERE c.user_id = ? AND c.course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$cert = $stmt->fetch();

if (!$cert) {
    die("Certificate not found.");
}

$signer_name = $cert['instructor_name'] ?? 'Academic Director';
$signer_title = $cert['instructor_dept'] ? 'Expert in ' . $cert['instructor_dept'] : 'Course Instructor';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDRU - Digital Certificate</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Cinzel:wght@700&family=Sarabun:wght@400;500;700&family=Cormorant+Garamond:ital,wght@0,700;1,700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --cert-navy: #0f172a;
            --cert-gold: #c29c53;
            --cert-gold-light: #eaddca;
            --cert-border: rgba(194, 156, 83, 0.3);
        }

        @page {
            size: landscape;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: #1a1a1a;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .certificate-wrapper {
            position: relative;
            width: 1123px;
            height: 794px;
            background: white;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            overflow: hidden;
            display: flex;
            color: var(--cert-navy);
        }

        /* Abstract Pattern Background */
        .bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            opacity: 0.05;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%230f172a' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3Csvg%3E");
        }

        /* Side Geometric Element */
        .side-accent {
            width: 80px;
            height: 100%;
            background: var(--cert-navy);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 200px;
        }

        .side-accent::after {
            content: '';
            position: absolute;
            left: 80px;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, transparent, var(--cert-gold), transparent);
        }

        .vertical-text {
            transform: rotate(-90deg);
            color: var(--cert-gold);
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 5px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* Main Content */
        .cert-main {
            flex: 1;
            padding: 60px 80px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 50px;
        }

        .udru-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-box {
            width: 50px;
            height: 50px;
            background: var(--cert-navy);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 900;
            font-size: 1.2rem;
        }

        .logo-text {
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            letter-spacing: 2px;
            color: var(--cert-navy);
        }

        .cert-title-area {
            text-align: right;
        }

        .cert-id-tag {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .main-heading {
            font-family: 'Cormorant Garamond', serif;
            font-size: 4.5rem;
            margin: 30px 0 10px;
            font-weight: 700;
            line-height: 1;
            color: var(--cert-navy);
        }

        .sub-heading {
            font-size: 1.25rem;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 60px;
            letter-spacing: 1px;
        }

        .student-name {
            font-size: 4.5rem;
            font-weight: 800;
            color: var(--cert-gold);
            margin: 20px 0;
            font-family: 'Sarabun', sans-serif;
            position: relative;
            display: inline-block;
        }

        .course-info {
            font-size: 1.15rem;
            color: #475569;
            margin-top: 40px;
            line-height: 1.8;
            max-width: 700px;
        }

        .course-name {
            font-weight: 700;
            color: var(--cert-navy);
            font-size: 1.5rem;
            border-bottom: 2px solid var(--cert-gold);
            padding-bottom: 4px;
        }

        .footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .signature-group {
            display: flex;
            gap: 80px;
        }

        .sig-block {
            text-align: center;
        }

        .sig-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sig-title {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sig-line {
            width: 200px;
            height: 1px;
            background: #e2e8f0;
            margin: 15px 0 10px;
        }

        /* Gold Seal */
        .seal-modern {
            width: 130px;
            height: 130px;
            background: linear-gradient(135deg, #d4af37 0%, #f1d382 50%, #d4af37 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(212, 175, 55, 0.3);
            position: relative;
        }

        .seal-inner {
            width: 110px;
            height: 110px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .seal-text {
            font-size: 0.6rem;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .seal-year {
            font-size: 1rem;
            font-weight: 800;
            margin-top: 2px;
        }

        /* Verification QR */
        .verification {
            position: absolute;
            bottom: 60px;
            right: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .qr-placeholder {
            width: 80px;
            height: 80px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 5px;
            border-radius: 8px;
        }

        .qr-code {
            width: 100%;
            height: 100%;
            background: #0f172a;
            mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='3' width='7' height='7'%3E%3C/rect%3E%3Crect x='14' y='3' width='7' height='7'%3E%3C/rect%3E%3Crect x='14' y='14' width='7' height='7'%3E%3C/rect%3E%3Crect x='3' y='14' width='7' height='7'%3E%3C/rect%3E%3C/svg%3E");
            mask-repeat: no-repeat;
            mask-position: center;
        }

        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--cert-navy);
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 100;
        }

        @media print {
            .print-btn {
                display: none;
            }

            body {
                background: white;
            }

            .certificate-wrapper {
                box-shadow: none;
            }
        }
    </style>
</head>

<body>

    <div class="certificate-wrapper">
        <div class="bg-pattern"></div>

        <div class="side-accent">
            <div class="vertical-text">ESTABLISHED 1923</div>
            <div class="vertical-text">KM UDRU ACADEMY</div>
            <div class="vertical-text">VERIFIED RECORD</div>
        </div>

        <div class="cert-main">
            <div class="header">
                <div class="udru-logo">
                    <div class="logo-box">KM</div>
                    <div class="logo-text">UDRU TRAINING CENTER</div>
                </div>
                <div class="cert-title-area">
                    <div class="cert-id-tag">CERTIFICATE ID: <?php echo htmlspecialchars($cert['certificate_code']); ?>
                    </div>
                    <div style="font-weight: 800; font-size: 0.8rem; letter-spacing: 2px;">OFFICIAL DOCUMENT</div>
                </div>
            </div>

            <div class="main-heading">Certificate of Achievement</div>
            <div class="sub-heading">มอบคุณสมบัติและประกาศนียบัตรฉบับนี้แก่</div>

            <div class="student-name">
                <?php echo htmlspecialchars($cert['student_name']); ?>
                <div
                    style="position: absolute; bottom: -10px; left: 0; width: 100%; height: 4px; background: var(--cert-gold); opacity: 0.3;">
                </div>
            </div>

            <div class="course-info">
                ได้รับวุฒิบัตรจากการผ่านการฝึกอบรมและทดสอบเกณฑ์มาตรฐานในหลักสูตร
                <br>
                <span class="course-name">"<?php echo htmlspecialchars($cert['course_title']); ?>"</span>
                <br>
                <div style="margin-top: 15px; font-size: 0.95rem;">
                    โดยได้คะแนนผ่านเกณฑ์ตามที่มหาวิทยาลัยกำหนด ออกให้ ณ วันที่
                    <?php echo date('d F Y', strtotime($cert['issued_at'])); ?>
                </div>
            </div>

            <div class="footer">
                <div class="signature-group">
                    <div class="sig-block">
                        <div class="sig-name"><?php echo htmlspecialchars($signer_name); ?></div>
                        <div class="sig-line"></div>
                        <div class="sig-title"><?php echo htmlspecialchars($signer_title); ?></div>
                    </div>
                </div>

                <div class="seal-modern">
                    <div class="seal-inner">
                        <div class="seal-text">Official</div>
                        <div class="seal-year"><?php echo date('Y'); ?></div>
                        <div class="seal-text">Certified</div>
                    </div>
                </div>
            </div>

            <div class="verification">
                <div class="qr-placeholder">
                    <div class="qr-code"></div>
                </div>
                <div style="font-size: 0.6rem; font-weight: 700; color: #94a3b8;">SCAN TO VERIFY</div>
            </div>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">🖨️ พิมพ์ใบประกาศ</button>

</body>

</html>