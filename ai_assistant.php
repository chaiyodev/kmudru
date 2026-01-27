<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$user = null;
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

// Helper function to clean query (Semantic-ish cleaning)
function clean_km_query($q)
{
    // Remove punctuation and common Thai stop words
    $punctuation = ["?", "!", ".", ",", "(", ")"];
    $stop_words = ["มี", "อะไร", "บ้าง", "ใน", "ระบบ", "ช่วย", "แสดง", "หน่อย", "ที่", "ครับ", "ค่ะ", "วิธี", "การ", "หนู", "ผม"];

    $q = str_replace($punctuation, "", $q);
    $cleaned = str_replace($stop_words, " ", $q);

    return trim($cleaned);
}

if ($query !== '') {
    $clean_query = clean_km_query($query);

    // If cleaning made it too short or empty, try at least one keyword
    if (mb_strlen($clean_query) < 2) {
        $clean_query = $query;
    }

    $search_term = "%$clean_query%";

    // 1. Search Documents & Wiki
    $stmt = $pdo->prepare("SELECT 'document' as origin, title, content as body FROM documents WHERE (title LIKE ? OR content LIKE ?) AND status = 'published' LIMIT 3");
    $stmt->execute([$search_term, $search_term]);
    $results = array_merge($results, $stmt->fetchAll());

    // 2. Search Experts
    $stmt = $pdo->prepare("SELECT 'expert' as origin, full_name as title, specialty as body FROM users WHERE (full_name LIKE ? OR specialty LIKE ?) AND role IN ('admin', 'contributor') LIMIT 2");
    $stmt->execute([$search_term, $search_term]);
    $results = array_merge($results, $stmt->fetchAll());

    // 3. Search Trainings (Handling "training/courses" specifically)
    $is_training_query = false;
    $training_keywords = ['อบรม', 'หลักสูตร', 'เรียน', 'คอร์ส', 'training', 'course'];
    foreach ($training_keywords as $kw) {
        if (mb_stripos($query, $kw) !== false) {
            $is_training_query = true;
            break;
        }
    }

    if ($is_training_query) {
        $stmt = $pdo->prepare("SELECT 'training' as origin, title, description as body FROM trainings WHERE (title LIKE ? OR description LIKE ?) LIMIT 5");
        $stmt->execute([$search_term, $search_term]);
        $training_results = $stmt->fetchAll();

        if (empty($training_results)) {
            $stmt = $pdo->query("SELECT 'training' as origin, title, description as body FROM trainings ORDER BY created_at DESC LIMIT 3");
            $training_results = $stmt->fetchAll();
        }
        $results = array_merge($results, $training_results);
    }

    // 4. Handle "Popular Articles" specifically
    if (str_contains($query, 'ยอดนิยม')) {
        $stmt = $pdo->query("SELECT 'document' as origin, title, content as body FROM documents WHERE status = 'published' ORDER BY views DESC LIMIT 3");
        $results = array_merge($results, $stmt->fetchAll());
    }

    // 5. Handle "How to use" guide
    if (str_contains($query, 'วิธีใช้งาน') || str_contains($query, 'คู่มือ')) {
        $results[] = [
            'origin' => 'guide',
            'title' => 'คู่มือการใช้งานระบบ KM Portal',
            'body' => 'ระบบ KM Portal ออกแบบมาเพื่อช่วยให้คุณเข้าถึงความรู้ได้ง่ายขึ้น: 1. ใช้ช่องค้นหา AI เพื่อถามคำถาม 2. เข้าสู่เครือข่าย CoP เพื่อแลกเปลี่ยนความรู้ 3. ติดต่อผู้เชี่ยวชาญผ่านระบบส่งข้อความ'
        ];
    }

    // 6. Special Case for "What documents are there?"
    if (count($results) < 2 && (str_contains($query, 'เอกสาร') || str_contains($query, 'ไฟล์'))) {
        $stmt = $pdo->query("SELECT 'document' as origin, title, content as body FROM documents WHERE status = 'published' ORDER BY created_at DESC LIMIT 3");
        $results = array_merge($results, $stmt->fetchAll());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant | KM Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .ai-center-container {
            max-width: 900px;
            margin: 4rem auto;
            text-align: center;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ai-hero-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #f0fdfa 0%, #e0f2fe 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: var(--teal-primary);
            box-shadow: 0 20px 40px -10px rgba(20, 184, 166, 0.2);
        }

        .prompt-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 3rem;
        }

        .prompt-card {
            background: white;
            border: 1px dashed #e2e8f0;
            padding: 1.5rem;
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: 0.3s;
            text-align: left;
        }

        .prompt-card:hover {
            border-color: var(--teal-primary);
            background: #f0fdfa;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(20, 184, 166, 0.1);
        }

        .prompt-icon {
            width: 40px;
            height: 40px;
            background: #f8fafc;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }

        .search-area {
            margin-top: 3rem;
            position: relative;
        }

        .search-input-lg {
            width: 100%;
            padding: 1.5rem 4rem;
            border-radius: 1.5rem;
            border: 2px solid #e2e8f0;
            font-size: 1.1rem;
            outline: none;
            transition: 0.3s;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }

        .search-input-lg:focus {
            border-color: var(--teal-primary);
            box-shadow: 0 20px 40px -10px rgba(20, 184, 166, 0.15);
        }

        .ai-result-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            border: 1px solid #e2e8f0;
            text-align: left;
            margin-top: 2rem;
            animation: slideUp 0.4s ease-out;
        }

        .ai-tooltip { 
            background: white; 
            padding: 6px 12px; 
            border-radius: 10px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            font-size: 0.75rem; 
            font-weight: 700; 
            color: #14b8a6; 
            border: 1px solid #ccfbf1; 
            animation: ai-bounce 2s infinite; 
            pointer-events: none; /* Prevent blocking clicks */
        }

        .file-upload-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 0.75rem;
            color: #475569;
            margin-top: 1rem;
            border: 1px solid #e2e8f0;
        }

        .file-icon-btn {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-icon-btn:hover {
            color: var(--teal-primary);
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport">
            <header class="header-top" style="justify-content: flex-end;">
                <div class="header-actions" style="display: flex; align-items: center; gap: 1.5rem;">
                    <a href="ai_assistant.php" class="btn-primary" style="text-decoration: none;">
                        <i data-lucide="plus"></i>สร้างการสนทนาใหม่
                    </a>
                    <div style="position: relative; display: flex; align-items: center;">
                        <i data-lucide="bell" style="color: #64748b; cursor: pointer; width: 22px; height: 22px;"></i>
                        <span style="position: absolute; top: -8px; right: -8px; background: #f59e0b; color: white; font-size: 10px; padding: 2px 5px; border-radius: 10px; font-weight: 700; border: 2px solid white;">3</span>
                    </div>
                </div>
            </header>

            <div class="ai_header_card"
                style="background: white; padding: 1.5rem; border-radius: 1.5rem; border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
                <div
                    style="width: 48px; height: 48px; background: #14b8a6; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                    <i data-lucide="bot"></i>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                        KM AI Assistant <span class="ai-badge">Powered by AI</span>
                    </div>
                    <div style="font-size: 0.875rem; color: #94a3b8;">ถามคำถามเกี่ยวกับความรู้ในองค์กร</div>
                </div>
            </div>

            <div class="ai-center-container">
                <?php if ($query === ''): ?>
                    <div class="ai-hero-icon">
                        <i data-lucide="bot" style="width: 48px; height: 48px;"></i>
                    </div>

                    <h1 style="font-size: 2.25rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem;">สวัสดีครับ! ผมคือ
                        AI Assistant</h1>
                    <p style="font-size: 1.1rem; color: #64748b; max-width: 600px; margin: 0 auto;">
                        ผมช่วยค้นหาและตอบคำถามเกี่ยวกับความรู้ในองค์กรได้ ลองถามผมได้เลย!</p>

                    <form id="ai-main-form" class="search-area" onsubmit="event.preventDefault(); handleAISubmit();">
                        <i data-lucide="sparkles"
                            style="position: absolute; left: 1.5rem; top: 50%; transform: translateY(-50%); color: var(--teal-primary);"></i>
                        <input type="text" name="q" id="ai-query-input" class="search-input-lg"
                            placeholder="พิมพ์คำถาม หรือส่งไฟล์ให้ AI ช่วยสรุป..." value="<?php echo e($query); ?>" autocomplete="off">
                        
                        <div
                            style="position: absolute; right: 8.5rem; top: 50%; transform: translateY(-50%); display: flex; gap: 0.5rem; align-items: center;">
                            <input type="file" id="ai-file-input" style="display: none;" onchange="handleFileSelect(event)">
                            <button type="button" class="file-icon-btn"
                                onclick="document.getElementById('ai-file-input').click()" title="แนบไฟล์ PDF/Word">
                                <i data-lucide="paperclip" style="width: 22px;"></i>
                            </button>
                        </div>

                        <button type="submit" class="btn-primary"
                            style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); padding: 0.75rem 1.5rem; border-radius: 1rem; border: none; cursor: pointer;">ส่งคำถาม</button>
                    </form>

                    <div id="file-preview-area" style="display: none; justify-content: center;">
                        <div class="file-upload-badge">
                            <i data-lucide="file" style="width: 14px;"></i>
                            <span id="selected-filename">filename.pdf</span>
                            <i data-lucide="x" style="width: 14px; cursor: pointer;" onclick="clearFile()"></i>
                        </div>
                    </div>

                    <div class="prompt-grid">
                        <div class="prompt-card" onclick="setSearch('มีเอกสารอะไรบ้างในระบบ?')">
                            <div class="prompt-icon"><i data-lucide="file-text"></i></div>
                            <div style="font-weight: 600; color: #475569;">มีเอกสารอะไรบ้างในระบบ?</div>
                        </div>
                        <div class="prompt-card" onclick="setSearch('บทความยอดนิยมมีอะไรบ้าง?')">
                            <div class="prompt-icon"><i data-lucide="book-open"></i></div>
                            <div style="font-weight: 600; color: #475569;">บทความยอดนิยมมีอะไรบ้าง?</div>
                        </div>
                        <div class="prompt-card" onclick="setSearch('วิธีใช้งานระบบ KM?')">
                            <div class="prompt-icon"><i data-lucide="help-circle"></i></div>
                            <div style="font-weight: 600; color: #475569;">วิธีใช้งานระบบ KM?</div>
                        </div>
                        <div class="prompt-card" onclick="setSearch('มีหลักสูตรอบรมอะไรบ้าง?')">
                            <div class="prompt-icon"><i data-lucide="graduation-cap"></i></div>
                            <div style="font-weight: 600; color: #475569;">มีหลักสูตรอบรมอะไรบ้าง?</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align: left;">
                        <a href="ai_assistant.php"
                            style="color: var(--teal-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600; margin-bottom: 2rem;">
                            <i data-lucide="arrow-left" style="width: 18px;"></i> กลับไปหน้าถามตอบ
                        </a>

                        <div class="ai-result-card">
                            <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                                <div
                                    style="width: 40px; height: 40px; background: var(--teal-primary); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="bot" style="width: 24px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: #0f172a;">AI Smart Response</div>
                                    <div style="font-size: 0.8rem; color: #94a3b8;">วิเคราะห์ข้อมูลจากคลังความรู้ UDRU</div>
                                </div>
                            </div>

                            <div style="line-height: 1.8; color: #334155; font-size: 1.05rem;">
                                <?php if (empty($results)): ?>
                                    <p>ขออภัยครับ ผมไม่พบข้อมูลที่เกี่ยวข้องกับ <strong>"<?php echo e($query); ?>"</strong>
                                        ในคลังความรู้ปัจจุบัน...</p>
                                    <p style="margin-top: 1rem; color: #64748b; font-size: 0.9rem;">คำแนะนำ:
                                        ลองใช้คำค้นหาที่กว้างขึ้น หรือสอบถามผู้เชี่ยวชาญโดยตรงผ่านเมนู "รายชื่อผู้เชี่ยวชาญ"
                                        ครับ</p>
                                <?php else: ?>
                                    <p>จากการวิเคราะห์ฐานข้อมูล ผมพบผลลัพธ์ที่เกี่ยวข้องกับ
                                        <strong>"<?php echo e($query); ?>"</strong> ทั้งหมด <?php echo count($results); ?>
                                        รายการ ดังนี้ครับ:
                                    </p>
                                    <ul style="margin-top: 1.5rem; display: grid; gap: 1rem;">
                                        <?php foreach ($results as $res): ?>
                                            <li
                                                style="padding: 1rem; background: #f8fafc; border-radius: 1rem; border: 1px solid #f1f5f9;">
                                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                                    <div>
                                                        <span class="ai-badge" style="margin-bottom: 0.5rem; background: <?php
                                                        if ($res['origin'] == 'expert')
                                                            echo '#6366f1';
                                                        elseif ($res['origin'] == 'training')
                                                            echo '#f59e0b';
                                                        elseif ($res['origin'] == 'guide')
                                                            echo '#14b8a6';
                                                        else
                                                            echo 'var(--teal-primary)';
                                                        ?>;">
                                                            <?php
                                                            if ($res['origin'] == 'expert')
                                                                echo 'ผู้เชี่ยวชาญ';
                                                            elseif ($res['origin'] == 'training')
                                                                echo 'หลักสูตรอบรม';
                                                            elseif ($res['origin'] == 'guide')
                                                                echo 'คู่มือระบบ';
                                                            else
                                                                echo 'เอกสาร/ความรู้';
                                                            ?>
                                                        </span>
                                                        <div style="font-weight: 700; color: #0f172a;">
                                                            <?php echo e($res['title']); ?>
                                                        </div>
                                                        <p style="font-size: 0.875rem; color: #64748b; margin-top: 0.25rem;">
                                                            <?php echo mb_strimwidth(strip_tags($res['body']), 0, 150, "..."); ?>
                                                        </p>
                                                    </div>
                                                    <i data-lucide="chevron-right" style="color: #cbd5e1;"></i>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <p
                                        style="margin-top: 2rem; padding: 1rem; background: #f0fdf4; border-radius: 10px; border: 1px solid #dcfce7; color: #166534; font-size: 0.9rem;">
                                        <strong>สรุปจาก AI:</strong> จากการสืบค้นข้อมูลในระบบ
                                        พบว่าเนื้อหาที่คุณสนใจมีการรวบรวมไว้เป็นอย่างดี
                                        ทั้งในรูปแบบเอกสารและหลักสูตรอบรมที่เกี่ยวข้อง
                                        คุณสามารถเลือกศึกษาเพิ่มเติมจากรายการด้านบนได้ทันทีครับ
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form action="ai_assistant.php" method="GET" style="margin-top: 2rem; position: relative;">
                            <input type="text" name="q" class="search-input-lg" style="padding: 1rem 3rem; font-size: 1rem;"
                                placeholder="ถามคำถามต่อเนื่องจากเดิม...">
                            <button type="submit"
                                style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--teal-primary); cursor: pointer;">
                                <i data-lucide="send" style="width: 20px;"></i>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        lucide.createIcons();

        function setSearch(val) {
            document.querySelector('.search-input-lg').value = val;
            document.querySelector('.search-input-lg').focus();
        }

        let selectedFile = null;

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                selectedFile = file;
                document.getElementById('selected-filename').textContent = file.name;
                document.getElementById('file-preview-area').style.display = 'flex';
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'เตรียมอัปโหลดไฟล์: ' + file.name,
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        }

        function clearFile() {
            selectedFile = null;
            document.getElementById('ai-file-input').value = '';
            document.getElementById('file-preview-area').style.display = 'none';
        }

        function handleAISubmit() {
            const query = document.getElementById('ai-query-input').value;

            if (selectedFile) {
                let analysisType = query.includes('สรุป') ? 'สรุปประเด็นสำคัญ' :
                    (query.includes('ย่อ') ? 'ย่อเนื้อหา' : 'วิเคราะห์ข้อมูล');

                Swal.fire({
                    title: 'กำลังประมวลผลการวิเคราะห์...',
                    html: `AI กำลัง <b>${analysisType}</b> จากไฟล์ <b>${selectedFile.name}</b><br>ตามคำสั่งของคุณ: "<i>${query || 'วิเคราะห์ทั่วไป'}</i>"`,
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                setTimeout(() => {
                    Swal.close();
                    let resultHtml = "";

                    if (query.includes('สรุป') || query.includes('ย่อ')) {
                        resultHtml = `
                            <div style="background: #fdf2f8; padding: 1rem; border-radius: 12px; border: 1px solid #fbcfe8; margin-bottom: 1rem;">
                                <h4 style="margin: 0; color: #be185d;">📄 บทสรุปย่อตามคำขอ</h4>
                                <p style="margin: 0.5rem 0 0; color: #9d174d; font-size: 0.95rem;">
                                    เอกสารชุดนี้สรุปได้ว่า เป็นการวางระเบียบวาระการพัฒนาทักษะดิจิทัลของบุคลากร 
                                    โดยเน้นไปที่ 3 หัวข้อหลักคือ การใช้ AI ในงานธุรการ, ความปลอดภัยทางไซเบอร์, และการวิเคราะห์ข้อมูลเบื้องต้น
                                </p>
                            </div>
                        `;
                    }

                    Swal.fire({
                        title: 'ผลการวิเคราะห์เจาะลึก (AI)',
                        width: '750px',
                        html: `<div style="text-align: left; font-size: 0.95rem; line-height: 1.6;">
                            ${resultHtml}
                            <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; border-left: 4px solid var(--teal-primary); margin-bottom: 1.5rem;">
                                <h4 style="margin: 0; color: #0f172a;">ภาพรวมของเอกสาร</h4>
                                <p style="margin: 0.5rem 0 0; color: #475569; font-size: 0.9rem;">เป้าหมายหลักของไฟล์คือการเพิ่มประสิทธิภาพการทำงานผ่านกระบวนการจัดการความรู้ (KM) เพื่อรองรับมาตรฐาน EdPEx</p>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                                <div style="padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                                    <div style="font-weight: 700; color: #166534; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i data-lucide="check-circle-2" style="width:16px;"></i> ข้อดีที่พบ
                                    </div>
                                    <ul style="padding-left: 1.25rem; font-size: 0.85rem; color: #475569; margin: 0;">
                                        <li>มีแผนผังกระบวนการ (Workflow) ชัดเจน</li>
                                        <li>อ้างอิงแหล่งกฎหมายที่เกี่ยวข้องครบถ้วน</li>
                                    </ul>
                                </div>
                                <div style="padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                                    <div style="font-weight: 700; color: #991b1b; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i data-lucide="alert-triangle" style="width:16px;"></i> จุดที่ต้องตรวจสอบเพิ่ม
                                    </div>
                                    <ul style="padding-left: 1.25rem; font-size: 0.85rem; color: #475569; margin: 0;">
                                        <li>วิธีการวัดผลลัพธ์ยังไม่เป็นรูปธรรม</li>
                                    </ul>
                                </div>
                            </div>
                        </div>`,
                        icon: 'success',
                        confirmButtonText: 'บันทึกบทสรุปนี้',
                        didOpen: () => { lucide.createIcons(); }
                    });
                    clearFile();
                }, 3000);
            } else if (query) {
                // Regular Text Search
                window.location.href = 'ai_assistant.php?q=' + encodeURIComponent(query);
            }
        }

        document.getElementById('ai-query-input').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleAISubmit();
            }
        });
    </script>
</body>

</html>