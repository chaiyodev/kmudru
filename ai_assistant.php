<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/logger.php';

$pdo = get_pdo();
$user = null;
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (isset($_POST['ajax_chat'])) {
    ob_start(); // Buffer output to prevent warnings from breaking JSON
    header('Content-Type: application/json');
    $msg = trim($_POST['message'] ?? '');

    if (empty($msg)) {
        echo json_encode(['error' => 'Empty message']);
        exit;
    }

    // --- RATE LIMITING (30 requests per 5 minutes) ---
    if (!isset($_SESSION['ai_rate']))
        $_SESSION['ai_rate'] = ['count' => 0, 'start' => time()];
    if (time() - $_SESSION['ai_rate']['start'] > 300) {
        $_SESSION['ai_rate'] = ['count' => 0, 'start' => time()];
    }
    $_SESSION['ai_rate']['count']++;
    if ($_SESSION['ai_rate']['count'] > 30) {
        echo json_encode(['response' => '⏳ **คุณส่งข้อความเร็วเกินไปครับ** กรุณารอสักครู่แล้วลองใหม่นะครับ (จำกัด 30 ข้อความ / 5 นาที)']);
        exit;
    }

    // --- CONVERSATION MEMORY (Store last 10 messages) ---
    if (!isset($_SESSION['ai_history']))
        $_SESSION['ai_history'] = [];
    $_SESSION['ai_history'][] = ['role' => 'user', 'text' => $msg, 'time' => time()];
    // Keep only last 10 entries
    if (count($_SESSION['ai_history']) > 10) {
        $_SESSION['ai_history'] = array_slice($_SESSION['ai_history'], -10);
    }

    log_activity('ai_chat', 'question', $msg);

    // --- CONTEXT RESOLUTION (Resolve references like "นี้", "อันนี้", "เรื่องนี้") ---
    $msg_lower = mb_strtolower($msg);
    $resolved_context = '';

    if (preg_match('/(เรื่องนี้|อันนี้|บทความนี้|คอร์สนี้|คนนี้|ของนี้)/u', $msg_lower)) {
        // Look back in history for the last AI result that had a title/topic
        foreach (array_reverse($_SESSION['ai_history']) as $h) {
            if ($h['role'] === 'ai_result' && !empty($h['text'])) {
                $resolved_context = $h['text'];
                break;
            }
        }
    }

    // --- SMART INTELLIGENCE LOGIC (RAG-lite v3.0) ---

    // 1. Detect Intent
    $intent = 'search';

    if (preg_match('/(จำนวน|กี่|เท่าไหร่|สรุปสถิติ|สถิติ)/u', $msg_lower))
        $intent = 'stats';
    else if (preg_match('/(ยอดนิยม|ดัง|มีคนอ่านเยอะ|popular|อันดับ|top|rank)/u', $msg_lower))
        $intent = 'popular';
    else if (preg_match('/(ล่าสุด|ใหม่|เพิ่ง|recent)/u', $msg_lower))
        $intent = 'latest';
    else if (preg_match('/(อบรม|สัมมนา|เรียน|คอร์ส|training|course)/u', $msg_lower))
        $intent = 'training';
    else if (preg_match('/(คืออะไร|แปลว่า|หมายความว่า|คือ)/u', $msg_lower))
        $intent = 'define';
    else if (preg_match('/(ใคร|คนไหน|ผู้เชี่ยวชาญ|expert|คนเก่ง)/u', $msg_lower))
        $intent = 'expert';
    else if (preg_match('/(สรุป|ย่อ|ใจความ|สาระสำคัญ|summarize|summary)/u', $msg_lower))
        $intent = 'summarize';
    else if (preg_match('/(สวัสดี|หวัดดี|hi|hello|hey|ทักทาย|help|ช่วยด้วย)/u', $msg_lower))
        $intent = 'greeting';
    else if (preg_match('/(ฉลาดแค่ไหน|เก่งไหม|ระดับ|level|version|ศักยภาพ|ความสามารถ|ทำอะไรได้)/u', $msg_lower))
        $intent = 'capability';
    else if (preg_match('/(ประวัติ|คุยอะไรไป|ย้อนดู|chat history)/u', $msg_lower))
        $intent = 'history';

    $results = [];
    $ai_response = "";

    switch ($intent) {
        case 'capability':
            $ai_response = '
            <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                <div style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); padding: 1.5rem; color: white;">
                    <div style="font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">System Status</div>
                    <div style="font-size: 1.5rem; font-weight: 800; margin-top: 0.25rem;">UDRU Wisdom AI v3.0</div>
                    <div style="display: inline-block; background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; margin-top: 0.5rem;">
                        ⚡ Domain-Specific Intelligence
                    </div>
                </div>
                <div style="padding: 1.5rem;">
                    <div style="margin-bottom: 1.25rem;">
                        <h4 style="margin: 0 0 0.75rem 0; color: #1e293b; font-size: 1rem;">📊 ระดับความสามารถปัจจุบัน (Current Capabilities)</h4>
                        <div style="display: flex; gap: 0.75rem; flex-direction: column;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.95rem; color: #475569;">
                                <div style="width: 24px; height: 24px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center;">✓</div>
                                <span><b>Precision Search:</b> ค้นหาแม่นยำ 100% จากฐานข้อมูลจริง</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.95rem; color: #475569;">
                                <div style="width: 24px; height: 24px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center;">✓</div>
                                <span><b>Auto-Summarize:</b> สรุปใจความสำคัญอัตโนมัติ</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.95rem; color: #475569;">
                                <div style="width: 24px; height: 24px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center;">✓</div>
                                <span><b>Context Aware:</b> เข้าใจคำสั่งตัวเลขและบริบทง่ายๆ</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background: #f8fafc; border-radius: 12px; padding: 1rem; border: 1px solid #f1f5f9;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #334155; font-size: 0.9rem;">🤖 เทียบกับ External AI (เช่น ChatGPT)</h4>
                        <p style="margin: 0; font-size: 0.85rem; color: #64748b; line-height: 1.5;">
                            ระบบนี้เน้น <b>"ความถูกต้องของข้อมูลภายใน (Fact-based)"</b> และ <b>"ความปลอดภัย (Security)"</b> ซึ่งต่างจาก AI ทั่วไปที่เก่งรอบด้านแต่อาจให้ข้อมูลภายในองค์กรผิดพลาด (Hallucination) ครับ
                        </p>
                    </div>
                </div>
            </div>';
            break;

        case 'history':
            $history = $_SESSION['ai_history'] ?? [];
            $user_msgs = array_filter($history, fn($h) => $h['role'] === 'user');
            if (count($user_msgs) <= 1) {
                $ai_response = "📝 **ยังไม่มีประวัติการสนทนาก่อนหน้าครับ** นี่คือข้อความแรกของคุณเลย!\n\nลองถามอะไรมาได้เลยครับ ผมพร้อมช่วยเหลือ 😊";
            } else {
                $ai_response = "📝 **ประวัติการสนทนาล่าสุดของเรา (" . count($user_msgs) . " ข้อความ):**\n\n";
                $idx = 1;
                foreach ($user_msgs as $h) {
                    $time_ago = time() - $h['time'];
                    $time_str = $time_ago < 60 ? 'เมื่อสักครู่' : ($time_ago < 3600 ? round($time_ago / 60) . ' นาทีก่อน' : round($time_ago / 3600) . ' ชม. ก่อน');
                    $ai_response .= $idx . ". 💬 *\"{$h['text']}\"* — {$time_str}\n";
                    $idx++;
                }
                $ai_response .= "\n*ผมจำได้ทั้งหมดครับ! ถ้าอยากกลับไปเรื่องไหน บอกได้เลย* 🧠";
            }
            break;

        case 'greeting':
            // Context-aware greeting
            $total_history = count($_SESSION['ai_history'] ?? []);
            if ($total_history > 2) {
                $greetings = [
                    "สวัสดีอีกครั้งครับ! 👋 ยินดีที่ได้คุยกันต่อครับ มีอะไรให้ช่วยเพิ่มเติมไหมครับ?",
                    "กลับมาอีกแล้วนะครับ! 😊 วันนี้เราคุยกันมาแล้ว " . ($total_history - 1) . " ข้อความ ยังสนใจเรื่องอะไรอีกไหมครับ?",
                    "สวัสดีครับ! ผมจำได้ว่าเราเคยคุยกันก่อนหน้านี้ 🧠 ถามต่อเรื่องเดิมหรือเรื่องใหม่ก็ได้เลยครับ!"
                ];
            } else {
                $greetings = [
                    "สวัสดีครับ! 👋 มีอะไรให้ผมช่วยค้นหาในคลังปัญญา UDRU วันนี้ไหมครับ?",
                    "ยินดีที่ได้พบครับ! 😊 ท่านต้องการหาเอกสาร, ผู้เชี่ยวชาญ หรือคอร์สอบรมดีครับ?",
                    "สวัสดีครับ 🙏 ผมพร้อมเป็นผู้ช่วยอัจฉริยะของคุณแล้ว ถามมาได้เลยครับ!",
                    "ทักทายครับ! วันนี้สนใจรับข่าวสารหรือข้อมูลด้านไหนเป็นพิเศษไหมครับ?"
                ];
            }
            $ai_response = $greetings[array_rand($greetings)];
            break;

        case 'stats':
            $total_docs = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
            $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $total_cop = $pdo->query("SELECT COUNT(*) FROM communities")->fetchColumn();
            $ai_response = "📊 **สรุปภาพรวมฐานข้อมูลปัญญา UDRU ในขณะนี้ครับ**:\n\n" .
                "- 📄 **คลังความรู้สะสม:** " . number_format($total_docs) . " บทความ\n" .
                "- 👤 **นักจัดการความรู้:** " . number_format($total_users) . " ท่าน\n" .
                "- 🤝 **ชุมชนแนวปฏิบัติ (CoP):** " . number_format($total_cop) . " กลุ่ม\n\n" .
                "สถานะระบบ: **ยอดนิยมและอัปเดตต่อเนื่อง** ท่านต้องการเจาะลึกที่ส่วนไหนเป็นพิเศษไหมครับ?";
            break;

        case 'popular':
            // Smart Limit Detection
            $limit = 3;
            if (preg_match('/(อันดับ|top)\s*1/u', $msg_lower) || preg_match('/อันดับเดียว/u', $msg_lower)) {
                $limit = 1;
            }

            $stmt = $pdo->prepare("SELECT id, title, content as body, views FROM documents WHERE status = 'published' ORDER BY views DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($limit === 1 && !empty($results)) {
                $doc = $results[0];
                $ai_response = "🏆 **เอกสารที่ได้รับความนิยมเป็นอันดับ 1 ในขณะนี้คือ:**\n\n";
                $ai_response .= "**\"{$doc['title']}\"**\n";
                $ai_response .= "(ยอดเข้าชม: " . number_format($doc['views']) . " ครั้ง)\n\n";
                $ai_response .= "> " . mb_strimwidth(strip_tags($doc['body']), 0, 200, "...") . "\n\n";
                $ai_response .= "**ทำไมถึงเป็นอันดับ 1?**\n";
                $ai_response .= "เพราะเป็นเนื้อหาที่เกี่ยวข้องกับนโยบายหลักและมีการอัปเดตข้อมูลให้ทันสมัยอยู่เสมอครับ\n\n";
                $ai_response .= "[🌐 คลิกเพื่ออ่านฉบับเต็ม] (view.php?id={$doc['id']})\n";

                $results = []; // Clear results to prevent duplicate listing
            } else {
                $ai_response = "🔥 **นี่คือ " . count($results) . " สุดยอดเนื้อหาที่ได้รับความนิยมสูงสุด (Top Viewed) ในขณะนี้ครับ:**\n\n";
            }
            break;

        case 'latest':
            $limit = 3;
            if (preg_match('/([0-9]+)/', $msg, $matches) || preg_match('/(หนึ่ง|1|อันเดียว|เรื่องเดียว)/u', $msg_lower)) {
                $limit = (int) ($matches[1] ?? 1);
                if (preg_match('/(หนึ่ง|อันเดียว|เรื่องเดียว)/u', $msg_lower))
                    $limit = 1;
            }
            $limit = max(1, min($limit, 5)); // Clamp between 1-5

            $stmt = $pdo->prepare("SELECT id, title, content as body FROM documents WHERE status = 'published' ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($limit === 1 && !empty($results))
                $ai_response = "🆕 **นี่คือเอกสารที่อัปเดตเข้ามาล่าสุดครับ:**\n\n";
            else
                $ai_response = "🆕 **ผมรวบรวม $limit รายการข้อมูลที่เพิ่งอัปเดตใหม่ล่าสุดมาให้แล้วครับ:**\n\n";
            break;

        case 'training':
            $limit = 3;
            // Check for specific number request e.g., "ขอ 1 คอร์ส", "เลือกมา 1"
            if (preg_match('/([0-9]+)/', $msg, $matches) || preg_match('/(หนึ่ง|1|คอร์สเดียว|อันเดียว)/u', $msg_lower)) {
                $limit = (int) ($matches[1] ?? 1);
                if (preg_match('/(หนึ่ง|คอร์สเดียว|อันเดียว)/u', $msg_lower))
                    $limit = 1;
            }
            $limit = max(1, min($limit, 5));

            $stmt = $pdo->prepare("SELECT id, title, description as body FROM trainings ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as &$r) {
                $r['origin'] = 'training';
            }

            if ($limit === 1)
                $ai_response = "🎓 **นี่คือ 1 หลักสูตรอบรมที่ผมคัดมาแนะนำให้คุณโดยเฉพาะครับ:**\n\n";
            else
                $ai_response = "🎓 **หลักสูตรอบรมแนะนำ $limit รายการ เพื่อการพัฒนาทักษะ (Skill Up) ของคุณ:**\n\n";
            break;

        case 'expert':
            $clean_name = str_replace(['ใคร', 'คือ', 'คนไหน', 'ผู้เชี่ยวชาญ', 'หา', 'ต้องการ', 'ขอ', 'คน', 'ท่าน'], '', $msg);
            $limit = 3;
            if (preg_match('/([0-9]+)/', $msg, $matches)) {
                $limit = (int) $matches[1];
                $clean_name = str_replace($matches[1], '', $clean_name); // Remove number from name search
            }
            $limit = max(1, min($limit, 5));
            $clean_name = trim($clean_name);

            $stmt = $pdo->prepare("SELECT id, full_name as title, specialty as body FROM users WHERE (full_name LIKE ? OR specialty LIKE ?) AND role != 'reader' LIMIT ?");
            $stmt->bindValue(1, "%$clean_name%", PDO::PARAM_STR);
            $stmt->bindValue(2, "%$clean_name%", PDO::PARAM_STR);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as &$r) {
                $r['origin'] = 'expert';
            }
            $ai_response = "👤 **จากการสืบค้นรายชื่อผู้เชี่ยวชาญ ผมพบรายชื่อที่น่าจะช่วยเหลือคุณได้ดังนี้ครับ:**\n\n";
            break;

        case 'summarize':
            $clean_query = str_replace(['สรุป', 'ย่อ', 'ขอ', 'หน่อย', 'บทความ', 'เรื่อง', 'เนื้อหา', 'ใจความ', 'สำคัญ', 'ของ', 'ให้', 'ครับ', 'ค่ะ', 'ที', 'นี้', 'อันนี้', 'เรื่องนี้'], '', $msg);
            $clean_query = trim($clean_query);

            // Use conversation context if query is too short
            if (mb_strlen($clean_query) < 2 && !empty($resolved_context)) {
                $clean_query = $resolved_context;
            }

            if (mb_strlen($clean_query) < 2) {
                $ai_response = "🤔 **ขอทราบชื่อเรื่องที่ต้องการให้สรุปด้วยครับ** เช่น 'สรุปเรื่อง KM' หรือ 'ขอใจความสำคัญของ EdPEx'";
                break;
            }

            // Strategy 1: Exact Phrase Search
            $term = "%$clean_query%";
            $stmt = $pdo->prepare("SELECT id, title, content FROM documents WHERE (title LIKE ? OR content LIKE ?) AND status = 'published' ORDER BY views DESC LIMIT 1");
            $stmt->execute([$term, $term]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            // Strategy 2: Fallback to Keyword Search if Exact Phrase fails
            if (!$doc) {
                $keywords = explode(' ', $clean_query);
                $keywords = array_filter($keywords, function ($v) {
                    return mb_strlen($v) > 1;
                });

                if (!empty($keywords)) {
                    $like_clauses = [];
                    $params = [];
                    foreach ($keywords as $kw) {
                        $like_clauses[] = "(title LIKE ? OR content LIKE ?)";
                        $params[] = "%$kw%";
                        $params[] = "%$kw%";
                    }
                    $sql = "SELECT id, title, content FROM documents WHERE (" . implode(' OR ', $like_clauses) . ") AND status = 'published' ORDER BY views DESC LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($doc) {
                // Remove HTML and Markdown artifacts for clean text
                $raw_text = strip_tags($doc['content']);
                // Remove Markdown headers (#), bold (**), and other common symbols
                $clean_text = preg_replace('/[#*\[\]`]/', '', $raw_text);
                $clean_text = preg_replace('/\s+/', ' ', $clean_text);
                $clean_text = trim($clean_text);

                // 1. Better Intro
                $intro = mb_substr($clean_text, 0, 200) . "...";

                // 2. Intelligent Point Extraction
                $points = [];
                // Check for explicit list items in raw content (e.g., "1.", "2.", "-")
                if (preg_match_all('/(\d+\.|-|\u2022)\s+([^.\n]+)/u', $raw_text, $matches)) {
                    foreach ($matches[2] as $m) {
                        $p = trim($m);
                        if (mb_strlen($p) > 10 && mb_strlen($p) < 100) {
                            $points[] = $p;
                        }
                        if (count($points) >= 3)
                            break;
                    }
                }

                // Fallback: Split by major sentence endings or newlines if no list found
                if (empty($points)) {
                    $sentences = preg_split('/(\.|!|\?|\n)/u', $raw_text, -1, PREG_SPLIT_NO_EMPTY);
                    $count = 0;
                    foreach ($sentences as $s) {
                        $s = trim($s);
                        // Skip short titles or the intro we already showed
                        if (mb_strlen($s) > 20 && strpos($intro, mb_substr($s, 0, 20)) === false) {
                            $points[] = $s;
                            $count++;
                        }
                        if ($count >= 3)
                            break;
                    }
                }

                // If still empty, use heuristic
                if (empty($points)) {
                    $points = [
                        "เนื้อหาครอบคลุมประเด็นสำคัญของ {$doc['title']}",
                        "มีรายละเอียดขั้นตอนที่ชัดเจน",
                        "แนะนำให้ศึกษาฉบับเต็มเพื่อความครบถ้วน"
                    ];
                }

                // --- GENERATE PREMIUM HTML CARD ---
                // We use inline styles to guarantee the look regardless of Marked parser
                $ai_response = '
                <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-top: 0.5rem;">
                    <!-- Header -->
                    <div style="background: #f8fafc; padding: 1rem 1.25rem; border-bottom: 1px solid #edf2f7; display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 32px; height: 32px; background: #ccfbf1; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #0d9488;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">สรุปสาระสำคัญ</div>
                            <div style="font-size: 1rem; font-weight: 700; color: #0f172a; line-height: 1.2;">' . htmlspecialchars($doc['title']) . '</div>
                        </div>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 1.25rem;">
                        <div style="background: #fff; padding-left: 1rem; border-left: 4px solid #0d9488; margin-bottom: 1.25rem; font-style: italic; color: #475569;">
                            "' . $intro . '"
                        </div>
                        
                        <div style="margin-bottom: 1.25rem;">
                            <div style="font-weight: 600; color: #334155; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                ประเด็นสำคัญ
                            </div>
                            <ul style="margin: 0; padding: 0; list-style: none;">';
                foreach ($points as $p) {
                    $p = str_replace('✅', '', $p); // Clean old icons
                    $ai_response .= '<li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 0.75rem; color: #4b5563; font-size: 0.95rem;">
                                        <span style="display: block; width: 6px; height: 6px; background: #cbd5e1; border-radius: 50%; margin-top: 8px; flex-shrink: 0;"></span>
                                        <span style="flex: 1;">' . htmlspecialchars(trim($p)) . '</span>
                                    </li>';
                }
                $ai_response .= '</ul>
                        </div>

                        <!-- Action Button -->
                        <a href="view.php?id=' . $doc['id'] . '" target="_blank" style="display: flex; align-items: center; justify-content: center; width: 100%; padding: 0.75rem; background: #0f172a; color: white; text-decoration: none; border-radius: 10px; font-weight: 500; font-size: 0.95rem; transition: background 0.2s;">
                            อ่านบทความฉบับเต็ม
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 0.5rem;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </a>
                    </div>
                </div>
                <div style="text-align: right; font-size: 0.75rem; color: #94a3b8; margin-top: 0.5rem;">✨ สรุปโดย UDRU AI Wisdom</div>';

                $results = [];
            } else {
                $ai_response = "🤔 **ไม่พบบทความเรื่อง \"$clean_query\" เพื่อนำมาสรุปครับ**\n\nพยายามค้นหาด้วยคำที่กว้างขึ้น เช่นพิมพ์แค่ \"KM\" หรือ \"EdPEx\" สั้นๆ ดูนะครับ";
            }
            break;

        default:
            // 2. Advanced Keyword Extraction & Global Search
            $clean_query = str_replace(["?", "!", "ค่ะ", "ครับ", "ช่วย", "หน่อย", "คือ", "หา", "อยากรู้", "เกี่ยวกับ"], "", $msg);
            $clean_query = trim($clean_query);

            // If query is empty or too short, stop
            if (mb_strlen($clean_query) < 2) {
                $ai_response = "🤔 **คำค้นหาสั้นเกินไปครับ** รบกวนพิมพ์คำค้นหาที่ชัดเจนกว่านี้หน่อยนะครับ เช่น 'การประกันคุณภาพ' หรือ 'คู่มือพนักงาน'";
                break;
            }

            $keywords = explode(' ', $clean_query);
            $keywords = array_filter($keywords, function ($v) {
                return mb_strlen($v) > 1;
            });

            if (empty($keywords))
                $keywords = [$clean_query];

            // Strategy 1: Search Documents (Title & Content)
            foreach ($keywords as $kw) {
                $term = "%$kw%";
                $stmt = $pdo->prepare("SELECT id, 'document' as origin, title, content as body FROM documents WHERE (title LIKE ? OR content LIKE ?) AND status = 'published' ORDER BY views DESC LIMIT 3");
                $stmt->execute([$term, $term]);
                $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
            }

            // Strategy 2: Search Trainings if few results
            if (count($results) < 3) {
                foreach ($keywords as $kw) {
                    $term = "%$kw%";
                    $stmt = $pdo->prepare("SELECT id, 'training' as origin, title, description as body FROM trainings WHERE (title LIKE ? OR description LIKE ?) ORDER BY created_at DESC LIMIT 2");
                    $stmt->execute([$term, $term]);
                    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
                }
            }

            // Strategy 3: Search Experts if relevant
            if (count($results) < 5) {
                foreach ($keywords as $kw) {
                    $term = "%$kw%";
                    $stmt = $pdo->prepare("SELECT id, 'expert' as origin, full_name as title, specialty as body FROM users WHERE (full_name LIKE ? OR specialty LIKE ?) AND role != 'reader' LIMIT 2");
                    $stmt->execute([$term, $term]);
                    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
                }
            }

            // Deduplicate results based on Title to avoid repetition
            $temp = [];
            foreach ($results as $r) {
                $unique_key = $r['origin'] . '_' . $r['title'];
                if (!isset($temp[$unique_key])) {
                    $temp[$unique_key] = $r;
                }
            }
            $results = array_values($temp);

            // --- STRATEGY 4: Fuzzy Search (sub-keyword matching) ---
            if (empty($results) && mb_strlen($clean_query) >= 4) {
                // Split query into overlapping 3-char substrings for fuzzy matching
                $sub_keywords = [];
                $qlen = mb_strlen($clean_query);
                for ($i = 0; $i <= $qlen - 3; $i += 2) {
                    $sub_keywords[] = mb_substr($clean_query, $i, 3);
                }
                $sub_keywords = array_unique($sub_keywords);

                foreach ($sub_keywords as $sk) {
                    $term = "%$sk%";
                    $stmt = $pdo->prepare("SELECT id, 'document' as origin, title, content as body FROM documents WHERE (title LIKE ? OR tags LIKE ?) AND status = 'published' ORDER BY views DESC LIMIT 3");
                    $stmt->execute([$term, $term]);
                    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
                }
                // Deduplicate fuzzy results
                $temp = [];
                foreach ($results as $r) {
                    $unique_key = $r['origin'] . '_' . $r['title'];
                    if (!isset($temp[$unique_key]))
                        $temp[$unique_key] = $r;
                }
                $results = array_values($temp);
            }

            // Limit total results
            $results = array_slice($results, 0, 5);

            // Save first result title to conversation memory for context resolution
            if (!empty($results)) {
                $_SESSION['ai_history'][] = ['role' => 'ai_result', 'text' => $results[0]['title'], 'time' => time()];
            }

            if (empty($results)) {
                $ai_response = "🤔 **ผมพยายามค้นหาข้อมูลเกี่ยวกับ \"$msg\" ในทุกส่วนของระบบแล้ว แต่ไม่พบข้อมูลที่ตรงกันเลยครับ**\n\n" .
                    "**ข้อแนะนำเพิ่มเติม:**\n" .
                    "- ลองใช้คำค้นที่กว้างขึ้น หรือคำที่เกี่ยวข้อง\n" .
                    "- ลองใช้คำสำคัญเพียง 1-2 คำ เช่น 'การประกันคุณภาพ' แทน 'ขั้นตอนการทำงานประกันคุณภาพ'\n" .
                    "- หากเป็นเอกสารใหม่ อาจจะยังไม่อยู่ในระบบ\n" .
                    "- ลองพิมพ์คำว่า **'ช่วยเหลือ'** เพื่อดูวิธีใช้งานผมได้นะครับ";
            } else {
                $ai_response = "🧠 **ผมประมวลผลจากคลังความรู้ทั้งหมดและพบสิ่งที่น่าจะเกี่ยวข้องกับ \"$msg\" ดังนี้ครับ:**\n\n";
            }
            break;
    }

    // Build the Synthesis with Source Links (Standardized)
    if (!empty($results)) {
        // Save first result to conversation memory for context-aware follow-ups
        if (!empty($results[0]['title']) && $intent !== 'search') {
            $_SESSION['ai_history'][] = ['role' => 'ai_result', 'text' => $results[0]['title'], 'time' => time()];
        }
        foreach ($results as $idx => $res) {
            $origin = $res['origin'] ?? 'document';
            $icon = ($origin == 'expert') ? '👤' : (($origin == 'training') ? '🎓' : (($origin == 'wiki') ? '📘' : '📄'));

            // Set URL based on origin
            if ($origin == 'training') {
                $url = "training_view.php?id=" . $res['id'];
            } elseif ($origin == 'expert') {
                $url = "experts.php?q=" . urlencode($res['title']);
            } else {
                $url = "view.php?id=" . $res['id'];
            }

            $ai_response .= "### " . ($idx + 1) . ". $icon " . $res['title'] . "\n";

            // Smart Excerpt: If search terms exist, try to find them in body
            $body_text = strip_tags($res['body'] ?? '');
            $excerpt = mb_strimwidth($body_text, 0, 200, "...");

            $ai_response .= "> " . $excerpt . "\n\n";
            $ai_response .= "[🌐 คลิกเพื่ออ่านรายละเอียด]($url)\n\n";
        }
        $ai_response .= "---\n*💡 ข้อมูลนี้คัดกรองโดย AI Assistant จากฐานข้อมูล UDRU Wisdom*";
    }

    // --- SMART SUGGESTIONS (The Lovable Touch) ---
    $suggestions = [];

    switch ($intent) {
        case 'greeting':
            $suggestions = [
                '💡 ค้นหาเอกสารยอดนิยม',
                '🎓 แนะนำคอร์สอบรม',
                '👤 ค้นหาผู้เชี่ยวชาญ',
                '📊 สรุปสถิติระบบ'
            ];
            break;
        case 'popular':
            $suggestions = [
                '🥇 ขออันดับ 1 เพียงอันดับเดียว',
                '🆕 มีบทความใหม่อีกไหม',
                '❓ วิธีใช้งานระบบ'
            ];
            break;
        case 'summarize':
            $suggestions = [
                '👤 ใครคือผู้เชี่ยวชาญด้านนี้',
                '🎓 มีคอร์สอบรมเรื่องนี้ไหม',
                '🔎 ค้นหาเรื่องอื่นต่อ'
            ];
            break;
        case 'expert':
            $suggestions = [
                '📄 ขอบทความของคนนี้',
                '📞 ขอเบอร์ติดต่อ',
                '🔎 ค้นหาผู้เชี่ยวชาญท่านอื่น'
            ];
            break;
        default: // Search
            // Strategy 1: Search Documents (Title & Content)
            foreach ($keywords as $kw) {
                $term = "%$kw%";
                $stmt = $pdo->prepare("SELECT id, 'document' as origin, title, content as body FROM documents WHERE (title LIKE ? OR content LIKE ?) AND status = 'published' ORDER BY views DESC LIMIT 3");
                $stmt->execute([$term, $term]);
                $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
            }

            // Strategy 2: Search Trainings if few results
            if (count($results) < 3) {
                foreach ($keywords as $kw) {
                    $term = "%$kw%";
                    $stmt = $pdo->prepare("SELECT id, 'training' as origin, title, description as body FROM trainings WHERE (title LIKE ? OR description LIKE ?) ORDER BY created_at DESC LIMIT 2");
                    $stmt->execute([$term, $term]);
                    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
                }
            }

            // Strategy 3: Search Experts if relevant
            if (count($results) < 5) {
                foreach ($keywords as $kw) {
                    $term = "%$kw%";
                    $stmt = $pdo->prepare("SELECT id, 'expert' as origin, full_name as title, specialty as body FROM users WHERE (full_name LIKE ? OR specialty LIKE ?) AND role != 'reader' LIMIT 2");
                    $stmt->execute([$term, $term]);
                    $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
                }
            }

            // --- SMART FALLBACK: If no internal results, try Chit-Chat ---
            if (empty($results)) {
                $msg_chk = $msg_lower;

                // 1. Gratitude
                if (preg_match('/(ขอบคุณ|thank|thx|ใจมาก|ดีมาก|สุดยอด|เก่ง|good job)/u', $msg_chk)) {
                    $ai_response = "🙏 **ยินดีรับใช้ครับ!**\nหากมีเรื่องอื่นให้ช่วยบอกได้เลยนะครับ ผมพร้อมเสมอครับ 😊";
                    $suggestions = ['📝 กลับสู่เมนูหลัก', '❓ วิธีใช้งาน'];
                }
                // 2. Identity / Who are you
                else if (preg_match('/(คุณคือใคร|ชื่ออะไร|who are you|ทำอะไรได้บ้าง)/u', $msg_chk)) {
                    $ai_response = "🤖 **ผมคือ UDRU Wisdom AI ครับ!**\n\nหน้าที่ของผมคือช่วยคุณค้นหาความรู้ เอกสาร และผู้เชี่ยวชาญภายในองค์กรของเราครับ\nถึงผมจะยังไม่รูาทุกเรื่องในโลก แต่เรื่องใน UDRU ผมรู้ลึกรู้จริงแน่นอนครับ! 😎";
                    $suggestions = ['🔥 ค้นหาเรื่องยอดนิยม', '📊 ดูสถิติระบบ'];
                }
                // 3. Off-topic / General Knowledge (Polite Decline)
                else {
                    $ai_response = "🤔 **เรื่องนี้อยู่นอกเหนือฐานข้อมูลของผมครับ**\n\nเนื่องจากผมถูกออกแบบมาให้เป็น **\"ผู้เชี่ยวชาญเฉพาะด้าน Knowledge Management ภายใน UDRU\"** ครับ\n\nผมอาจจะตอบคำถามทั่วไปไม่ได้ แต่ถ้าเป็นเรื่องงาน เอกสาร หรือขั้นตอนต่างๆ ในองค์กร ถามมาได้เลยครับ!";
                    $suggestions = [
                        '🔥 ดูเอกสารยอดนิยมแทนไหม',
                        '👤 ค้นหาผู้เชี่ยวชาญ',
                        '❓ วิธีใช้งาน'
                    ];
                }
            } else {
                // Determine suggestions for Search Results
                $suggestions = [
                    '📝 สรุปเรื่องนี้ให้หน่อย',
                    '👤 หาผู้เชี่ยวชาญเรื่องนี้',
                    '🎓 มีอบรมเรื่องนี้ไหม'
                ];
            }
            break;
    }

    if (!empty($suggestions)) {
        $ai_response .= '<div class="suggestion-container">';
        foreach ($suggestions as $s) {
            // Encode the suggestion text to be safe for onclick
            $safe_s = htmlspecialchars($s, ENT_QUOTES);
            // We use a special onclick to populate the input and send immediately or just fill
            $ai_response .= '<div class="suggestion-chip" onclick="ask(\'' . $safe_s . '\')">';
            $ai_response .= '<span>' . $s . '</span>';
            $ai_response .= '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5"><path d="M5 12h14"></path><path d="M12 5l7 7-7 7"></path></svg>';
            $ai_response .= '</div>';
        }
        $ai_response .= '</div>';
    }

    ob_end_clean();
    echo json_encode(['response' => $ai_response]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDRU AI Workspace</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        :root {
            --chat-bg: #f8fafc;
            --sidebar-w: 300px;
            --accent: #14b8a6;
            --accent-glow: rgba(20, 184, 166, 0.15);
        }

        * {
            box-sizing: border-box;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            width: 100%;
            background-color: var(--chat-bg);
            font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif;
            overflow: hidden;
        }

        .app-container {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        .main-viewport {
            flex-grow: 1;
            height: 100vh;
            overflow: hidden;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        body.sidebar-collapsed .main-viewport {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        /* Use global sidebar logo and header styles */

        /* Removed local nav overrides to use global sidebar styles */

        /* --- Main Chat UI --- */
        .chat-area {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            position: relative;
            background: radial-gradient(circle at top right, #f0fdfa, transparent),
                radial-gradient(circle at bottom left, #eff6ff, transparent);
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 3rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 2rem;
            scroll-behavior: smooth;
        }

        .messages-inner {
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .message {
            display: flex;
            gap: 1.25rem;
            width: 100%;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .m-user {
            flex-direction: row-reverse;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .a-bot {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        .a-user {
            background: #0f172a;
            color: white;
        }

        .bubble {
            max-width: 80%;
            padding: 1rem 1.25rem;
            border-radius: 1.25rem;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .b-bot {
            background: white;
            border: 1px solid #e2e8f0;
            border-top-left-radius: 4px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        .b-user {
            background: #0f172a;
            color: white;
            border-top-right-radius: 4px;
        }

        /* --- Typo in Bubbles --- */
        .bubble h3 {
            margin: 1rem 0 0.5rem;
            color: var(--accent);
            font-size: 1.1rem;
        }

        .bubble blockquote {
            margin: 0.75rem 0;
            padding: 0.5rem 1rem;
            border-left: 3px solid #e2e8f0;
            color: #64748b;
            font-style: italic;
            background: #fbfcfd;
            border-radius: 4px;
        }

        .bubble a {
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        .bubble a:hover {
            text-decoration: underline;
        }

        /* --- Input Area --- */
        .input-sticky {
            padding: 2rem;
            padding-top: 0;
            background: linear-gradient(to top, var(--chat-bg) 70%, transparent);
        }

        .input-bar {
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1.5rem;
            padding: 0.75rem 1.25rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.1);
            transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .input-bar:focus-within {
            border-color: var(--accent);
            box-shadow: 0 20px 60px -15px var(--accent-glow);
            transform: translateY(-2px);
        }

        #chat-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 1rem;
            padding: 0.5rem 0;
            background: transparent;
            color: #0f172a;
        }

        .btn-circle {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            background: #f1f5f9;
            color: #64748b;
        }

        .suggestion-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .suggestion-chip {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .suggestion-chip:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: #f0fdfa;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(20, 184, 166, 0.15);
        }

        .btn-send {
            background: var(--accent) !important;
            color: white !important;
        }

        .btn-send:hover {
            transform: scale(1.05);
        }

        /* --- Hero States --- */
        .hero {
            text-align: center;
            margin: auto;
            max-width: 700px;
            padding: 2rem;
        }

        .hero h2 {
            font-size: 2.75rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 1rem;
            letter-spacing: -0.04em;
        }

        .hero p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .suggest-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2.5rem;
        }

        .suggest-card {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 1.25rem;
            border-radius: 1.25rem;
            cursor: pointer;
            transition: 0.2s;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .suggest-card:hover {
            border-color: var(--accent);
            background: #f0fdfa;
            transform: translateY(-2px);
        }

        /* --- Typing Dot Indicator --- */
        .typing {
            display: flex;
            gap: 4px;
            padding: 0.75rem 1rem;
            background: #f1f5f9;
            border-radius: 1rem;
            width: fit-content;
        }

        .dot {
            width: 6px;
            height: 6px;
            background: #94a3b8;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        .dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.3;
                transform: scale(0.8);
            }

            50% {
                opacity: 1;
                transform: scale(1.2);
            }
        }
    </style>
</head>

<body>

    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-viewport" style="display: flex; flex-direction: column; height: 100vh; padding: 0;">
            <div class="chat-area" id="chat-scroller" style="flex: 1; overflow-y: auto;">
                <div class="chat-messages">
                    <div class="messages-inner" id="message-container">
                        <div class="hero" id="welcome-state">
                            <div
                                style="width: 70px; height: 70px; background: white; border-radius: 20px; display: flex; align-items: center; justify-content: center; color: var(--accent); margin: 0 auto 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                                <i data-lucide="sparkles" style="width: 36px; height: 36px;"></i>
                            </div>
                            <h2>สวัสดีครับ! มีอะไรให้ผมช่วยสืบค้นไหมครับ?</h2>
                            <p>ผมคือผู้ช่วยอัจฉริยะ UDRU Wisdom ท่านสามารถสอบถามข้อมูลเอกสาร ผู้เชี่ยวชาญ
                                หรือคอร์สเรียนได้ทันที</p>

                            <div class="suggest-grid">
                                <div class="suggest-card" onclick="ask('สรุปผลกิจกรรมล่าสุดในระบบ')">
                                    <i data-lucide="trending-up" style="color: #f59e0b; width: 22px;"></i>
                                    <span>สรุปผลกิจกรรมล่าสุดในระบบ</span>
                                </div>
                                <div class="suggest-card" onclick="ask('มีเนื้อหายอดนิยมอะไรบ้าง')">
                                    <i data-lucide="zap" style="color: #6366f1; width: 22px;"></i>
                                    <span>มีเนื้อหายอดนิยมอะไรบ้าง</span>
                                </div>
                                <div class="suggest-card" onclick="ask('ใครคือผู้เชี่ยวชาญด้าน IT')">
                                    <i data-lucide="users" style="color: #10b981; width: 22px;"></i>
                                    <span>ใครคือผู้เชี่ยวชาญด้าน IT</span>
                                </div>
                                <div class="suggest-card" onclick="ask('มีอบรมอะไรใหม่บ้างสัปดาห์นี้')">
                                    <i data-lucide="book-open" style="color: #ec4899; width: 22px;"></i>
                                    <span>มีอบรมอะไรใหม่บ้างสัปดาห์นี้</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="input-sticky">
                    <div class="input-bar">
                        <button class="btn-circle"><i data-lucide="paperclip" style="width: 20px;"></i></button>
                        <input type="text" id="chat-input" placeholder="Ask anything about UDRU..." autocomplete="off">
                        <button class="btn-circle btn-send" id="send-trigger"><i data-lucide="send"
                                style="width: 20px;"></i></button>
                    </div>
                    <div
                        style="text-align: center; margin-top: 1rem; font-size: 0.7rem; color: #94a3b8; font-weight: 500;">
                        Powered by UDRU KM Intelligence &bull; AI can make mistakes
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        const container = document.getElementById('message-container');
        const input = document.getElementById('chat-input');
        const btn = document.getElementById('send-trigger');
        const welcome = document.getElementById('welcome-state');
        const scroller = document.getElementById('chat-scroller');

        function appendMessage(role, text) {
            if (welcome) welcome.style.display = 'none';

            const msgDiv = document.createElement('div');
            msgDiv.className = `message m-${role}`;

            const avatar = document.createElement('div');
            avatar.className = `avatar a-${role}`;
            avatar.innerHTML = role === 'bot' ? '<i data-lucide="bot" style="width:20px;"></i>' : '<i data-lucide="user" style="width:20px;"></i>';

            const bubble = document.createElement('div');
            bubble.className = `bubble b-${role}`;

            if (role === 'user') {
                bubble.textContent = text;
            } else {
                // Heuristic: If it starts with a <div tag (ignoring whitespace), assume it's our Pre-rendered HTML Card.
                // Otherwise bypass marked which might escape it or treat it as code block if indented.
                if (text.trim().startsWith('<div')) {
                    bubble.innerHTML = text;
                } else {
                    bubble.innerHTML = marked.parse(text);
                }
            }

            msgDiv.appendChild(role === 'user' ? avatar : avatar); // avatar is same logic but CSS handles position
            msgDiv.appendChild(bubble);

            // Correct order for user message
            if (role === 'user') {
                msgDiv.innerHTML = '';
                msgDiv.appendChild(bubble);
                msgDiv.appendChild(avatar);
            }

            container.appendChild(msgDiv);
            lucide.createIcons();

            // Auto Scroll to Bottom (Smooth)
            msgDiv.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }

        async function ask(text) {
            if (!text.trim()) return;

            const userText = text;
            input.value = '';
            appendMessage('user', userText);

            // Show Typing
            const typingDiv = document.createElement('div');
            typingDiv.className = 'message m-bot';
            typingDiv.id = 'typing-temp';
            typingDiv.innerHTML = `
            <div class="avatar a-bot"><i data-lucide="bot" style="width:20px;"></i></div>
            <div class="typing"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>
        `;
            container.appendChild(typingDiv);
            typingDiv.scrollIntoView({ behavior: 'smooth', block: 'end' });

            try {
                const formData = new FormData();
                formData.append('ajax_chat', '1');
                formData.append('message', userText);

                const response = await fetch('ai_assistant.php', { method: 'POST', body: formData });
                const data = await response.json();

                document.getElementById('typing-temp').remove();
                appendMessage('bot', data.response);
            } catch (e) {
                document.getElementById('typing-temp').remove();
                appendMessage('bot', '❌ ขออภัยครับ ระบบการสนทนาขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้ง');
            }
        }

        btn.addEventListener('click', () => ask(input.value));
        input.addEventListener('keypress', (e) => { if (e.key === 'Enter') ask(input.value); });

        window.onload = () => {
            const urlParams = new URLSearchParams(window.location.search);
            const q = urlParams.get('q');
            if (q) ask(q);
        };
    </script>

</body>

</html>