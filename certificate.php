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

// Default to "Academic Director" if no instructor found
$signer_name = $cert['instructor_name'] ?? 'Academic Director';
$signer_title = $cert['instructor_dept'] ? 'Instructor of ' . $cert['instructor_dept'] : 'Course Instructor';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Achievement</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Great+Vibes&family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Sarabun:wght@300;400;500;700&display=swap"
        rel="stylesheet">
    <style>
        @page {
            size: landscape;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Cormorant Garamond', serif;
            background: #222;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .certificate-container {
            width: 1123px;
            height: 794px;
            /* A4 Landscape */
            background: #fffdf5;
            /* Cream/Ivory */
            position: relative;
            color: #1e293b;
            text-align: center;
            box-sizing: border-box;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        /* Border Design */
        .border-outer {
            width: 100%;
            height: 100%;
            border: 4px solid #b45309;
            /* Bronze/Gold */
            position: relative;
            box-sizing: border-box;
            background: #fff;
        }

        .border-inner {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 2px solid #0f172a;
            /* Navy */
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9IiNlN2U1ZTQiLz48L3N2Zz4=');
            /* Subtle dots */
        }

        .corner-deco {
            position: absolute;
            width: 60px;
            height: 60px;
            border-style: double;
            border-color: #b45309;
        }

        .top-left {
            top: 15px;
            left: 15px;
            border-width: 4px 0 0 4px;
        }

        .top-right {
            top: 15px;
            right: 15px;
            border-width: 4px 4px 0 0;
        }

        .bottom-left {
            bottom: 15px;
            left: 15px;
            border-width: 0 0 4px 4px;
        }

        .bottom-right {
            bottom: 15px;
            right: 15px;
            border-width: 0 4px 4px 0;
        }

        /* Content */
        .logo {
            font-family: 'Cinzel', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #b45309;
            letter-spacing: 4px;
            margin-bottom: 2rem;
            text-transform: uppercase;
        }

        h1 {
            font-family: 'Cinzel', serif;
            font-size: 3.5rem;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
            line-height: 1;
        }

        .subtitle {
            font-size: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #64748b;
            margin-top: 1rem;
            margin-bottom: 3rem;
        }

        .content-body {
            margin: 1.5rem 0;
        }

        .presented-text {
            font-style: italic;
            font-size: 1.25rem;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .student-name {
            font-family: 'Great Vibes', cursive;
            font-size: 3.5rem;
            /* Reduced from 5rem */
            color: #b45309;
            line-height: 1.2;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 0.5rem;
        }

        .course-text {
            font-size: 1.2rem;
            color: #334155;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .course-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 2rem;
            /* Adjusted for balance */
            display: block;
            margin-top: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.5rem;
            display: inline-block;
        }

        .footer {
            display: flex;
            justify-content: center;
            gap: 150px;
            margin-top: 3rem;
            align-items: flex-end;
            position: relative;
            z-index: 2;
        }

        .signature-block {
            text-align: center;
        }

        .sign {
            font-family: 'Great Vibes', cursive;
            font-size: 2rem;
            /* Reduced from 2.5rem */
            font-weight: 400;
            /* Lighter weight */
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .line {
            width: 250px;
            height: 1px;
            background: #94a3b8;
            margin: 0 auto 0.5rem auto;
        }

        .title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            font-weight: 500;
            white-space: nowrap;
        }

        /* Seal */
        .seal {
            position: absolute;
            bottom: 60px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 120px;
            border: 4px double #b45309;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b45309;
            font-weight: 700;
            font-family: 'Cinzel', serif;
            font-size: 0.8rem;
            text-align: center;
            opacity: 0.2;
            pointer-events: none;
        }

        .cert-id {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 0.8rem;
            font-family: sans-serif;
            color: #cbd5e1;
        }

        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 2rem;
            background: #0f172a;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: 0.3s;
            font-family: 'Sarabun', sans-serif;
        }

        .print-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
        }

        @media print {
            body {
                background: white;
            }

            .certificate-container {
                box-shadow: none;
                margin: 0;
            }

            .print-btn {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="certificate-container">
        <div class="border-outer">
            <div class="border-inner">
                <!-- Decorations -->
                <div class="corner-deco top-left"></div>
                <div class="corner-deco top-right"></div>
                <div class="corner-deco bottom-left"></div>
                <div class="corner-deco bottom-right"></div>

                <div class="logo">KM UDRU Training Center</div>

                <h1>Certificate of Completion</h1>
                <div class="subtitle">มอบประกาศนียบัตรฉบับนี้ไว้เพื่อแสดงว่า</div>

                <div class="content-body">
                    <div class="student-name"><?php echo htmlspecialchars($cert['student_name']); ?></div>

                    <div class="course-text">
                        ได้ผ่านการฝึกอบรมและทดสอบความรู้ในหลักสูตร
                        <br>
                        <span class="course-title">"<?php echo htmlspecialchars($cert['course_title']); ?>"</span>
                    </div>
                </div>

                <div class="seal">OFFICIAL<br>CERTIFIED</div>

                <div class="footer">
                    <div class="signature-block">
                        <div class="sign"><?php echo date('d F Y', strtotime($cert['issued_at'])); ?></div>
                        <div class="line"></div>
                        <div class="title">Date of Completion (วันที่สำเร็จ)</div>
                    </div>

                    <div class="signature-block">
                        <div class="sign"><?php echo htmlspecialchars($signer_name); ?></div>
                        <div class="line"></div>
                        <div class="title"><?php echo htmlspecialchars($signer_title); ?></div>
                    </div>
                </div>

                <div class="cert-id">Verification Code: <?php echo htmlspecialchars($cert['certificate_code']); ?></div>
            </div>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">🖨️ พิมพ์ใบประกาศ</button>

</body>

</html>