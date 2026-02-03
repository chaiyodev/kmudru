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
    header('Content-Type: application/json');
    $msg = trim($_POST['message'] ?? '');

    if (empty($msg)) {
        echo json_encode(['error' => 'Empty message']);
        exit;
    }

    log_activity('ai_chat', 'question', $msg);

    // --- SMART INTELLIGENCE LOGIC (RAG-lite) ---

    // 1. Detect Intent
    $msg_lower = mb_strtolower($msg);
    $intent = 'search';

    if (preg_match('/(จำนวน|กี่|เท่าไหร่|สรุปสถิติ|สถิติ)/u', $msg_lower))
        $intent = 'stats';
    else if (preg_match('/(ยอดนิยม|ดัง|มีคนอ่านเยอะ|popular)/u', $msg_lower))
        $intent = 'popular';
    else if (preg_match('/(ล่าสุด|ใหม่|เพิ่ง|recent)/u', $msg_lower))
        $intent = 'latest';
    else if (preg_match('/(อบรม|สัมมนา|เรียน|คอร์ส|training|course)/u', $msg_lower))
        $intent = 'training';
    else if (preg_match('/(คืออะไร|แปลว่า|หมายความว่า|คือ)/u', $msg_lower))
        $intent = 'define';
    else if (preg_match('/(ใคร|คนไหน|ผู้เชี่ยวชาญ|expert|คนเก่ง)/u', $msg_lower))
        $intent = 'expert';

    $results = [];
    $ai_response = "";

    switch ($intent) {
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
            $stmt = $pdo->query("SELECT id, title, content as body FROM documents WHERE status = 'published' ORDER BY views DESC LIMIT 3");
            $results = $stmt->fetchAll();
            $ai_response = "🔥 **นี่คือ 3 สุดยอดเนื้อหาที่ได้รับความนิยมสูงสุด (Top Viewed) ในขณะนี้ครับ:**\n\n";
            break;

        case 'latest':
            $stmt = $pdo->query("SELECT id, title, content as body FROM documents WHERE status = 'published' ORDER BY created_at DESC LIMIT 3");
            $results = $stmt->fetchAll();
            $ai_response = "🆕 **ผมรวบรวมข้อมูลที่เพิ่งอัปเดตใหม่ล่าสุดเข้ามาในระบบมาให้แล้วครับ:**\n\n";
            break;

        case 'training':
            $stmt = $pdo->query("SELECT id, title, description as body FROM trainings ORDER BY created_at DESC LIMIT 3");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$r) {
                $r['origin'] = 'training';
            }
            $ai_response = "🎓 **หลักสูตรอบรมแนะนำเพื่อการพัฒนาทักษะ (Skill Up) ของคุณ:**\n\n";
            break;

        case 'expert':
            $clean_name = str_replace(['ใคร', 'คือ', 'คนไหน', 'ผู้เชี่ยวชาญ', 'หา'], '', $msg);
            $stmt = $pdo->prepare("SELECT id, full_name as title, specialty as body FROM users WHERE (full_name LIKE ? OR specialty LIKE ?) AND role != 'reader' LIMIT 3");
            $stmt->execute(["%$clean_name%", "%$clean_name%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$r) {
                $r['origin'] = 'expert';
            }
            $ai_response = "👤 **จากการสืบค้นรายชื่อผู้เชี่ยวชาญ ผมพบรายชื่อที่น่าจะช่วยเหลือคุณได้ดังนี้ครับ:**\n\n";
            break;

        default:
            // Advanced Keyword Extraction
            $clean_query = str_replace(["?", "!", "ค่ะ", "ครับ", "ช่วย", "หน่อย", "คือ", "หา"], "", $msg);
            $keywords = explode(' ', $clean_query);
            $keywords = array_filter($keywords, function ($v) {
                return mb_strlen($v) > 1; });
            if (empty($keywords))
                $keywords = [$clean_query];

            foreach ($keywords as $kw) {
                $term = "%$kw%";
                // Search Documents
                $stmt = $pdo->prepare("SELECT id, 'document' as origin, title, content as body FROM documents WHERE (title LIKE ? OR content LIKE ?) AND status = 'published' ORDER BY views DESC LIMIT 2");
                $stmt->execute([$term, $term]);
                $results = array_merge($results, $stmt->fetchAll());
                if (count($results) > 4)
                    break;
            }

            // Deduplicate
            $temp = [];
            foreach ($results as $r) {
                $temp[$r['title']] = $r;
            }
            $results = array_values($temp);

            if (empty($results)) {
                $ai_response = "🤔 **ผมลองพยายามสืบค้นเกี่ยวกับ \"$msg\" แล้ว แต่ยังไม่เจอข้อมูลที่ตรงกันเป๊ะๆ เลยครับ**\n\n" .
                    "**คำแนะนำ:**\n" .
                    "- ลองเปลี่ยนมาใช้คำสั้นๆ เช่น \"EdPEx\", \"KPI\" หรือ \"งบประมาณ\"\n" .
                    "- สอบถามข้อมูลตามหน่วยงาน เช่น \"ฝ่ายบุคคล\", \"วิทยบริการ\"\n" .
                    "- หรือลองถามผมว่า \"มีบทความใหม่ล่าสุดอะไรบ้าง\" ดูนะครับ";
            } else {
                $ai_response = "💡 **จากการสืบค้นคลังปัญญา UDRU ผมสรุปข้อมูลที่เกี่ยวข้องกับข้อซักถามของคุณได้ดังนี้ครับ:**\n\n";
            }
            break;
    }

    // Build the Synthesis with Source Links
    if (!empty($results)) {
        foreach ($results as $idx => $res) {
            $origin = $res['origin'] ?? 'document';
            $icon = ($origin == 'expert') ? '👤' : (($origin == 'training') ? '🎓' : '📄');
            $url = ($origin == 'training') ? "training_view.php?id=" . $res['id'] : (($origin == 'expert') ? "experts.php?q=" . urlencode($res['title']) : "view.php?id=" . $res['id']);

            $ai_response .= "### " . ($idx + 1) . ". $icon " . $res['title'] . "\n";
            $ai_response .= "> " . mb_strimwidth(strip_tags($res['body'] ?? ''), 0, 150, "...") . "\n\n";
            $ai_response .= "[🌐 อ่านรายละเอียดเพิ่มเติม]($url)\n\n";
        }
        $ai_response .= "---\n*ข้อมูลสรุปโดยระบบ AI Assistant ท่านสามารถถามเจาะลึกในแต่ละหัวข้อได้ทันทีครับ*";
    }

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
    <link rel="stylesheet" href="assets/css/style.css">
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
            background: var(--chat-bg);
            font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif;
            overflow: hidden;
        }

        .workspace-container {
            display: grid;
            grid-template-columns: var(--sidebar-w) 1fr;
            height: 100vh;
            width: 100%;
        }

        /* --- Sidebar UI --- */
        .workspace-sidebar {
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            z-index: 50;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .logo-box {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), #0ea5e9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        .nav-group {
            margin-bottom: 2rem;
        }

        .nav-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }

        .nav-item {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            color: #475569;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: 0.2s;
        }

        .nav-item:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .nav-item.active {
            background: #f0fdfa;
            color: var(--accent);
        }

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

    <div class="workspace-container">
        <aside class="workspace-sidebar">
            <div class="sidebar-logo">
                <div class="logo-box"><i data-lucide="bot"></i></div>
                <div style="font-weight: 800; font-size: 1.2rem; display: flex; flex-direction: column;">
                    UDRU AI
                    <span
                        style="font-size: 0.6rem; color: #94a3b8; font-weight: 800; text-transform: uppercase;">Workspace
                        v2.0</span>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-label">General</div>
                <a href="javascript:void(0)" onclick="location.reload()" class="nav-item active">
                    <i data-lucide="message-square" style="width: 18px;"></i> New Conversation
                </a>
                <a href="index.php" class="nav-item">
                    <i data-lucide="home" style="width: 18px;"></i> Homepage
                </a>
            </div>

            <div class="nav-group" style="flex: 1;">
                <div class="nav-label">Historical Activity</div>
                <div
                    style="text-align: center; color: #cbd5e1; font-size: 0.8rem; padding: 2rem 0; font-style: italic;">
                    History is cleared on reload
                </div>
            </div>

            <div class="sidebar-footer" style="padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div
                        style="width: 32px; height: 32px; border-radius: 8px; background: #0f172a; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                        <?php echo isset($user['username']) ? strtoupper(substr($user['username'], 0, 2)) : 'G'; ?>
                    </div>
                    <div style="overflow: hidden;">
                        <div
                            style="font-weight: 700; font-size: 0.85rem; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo isset($user['full_name']) ? e($user['full_name']) : 'Guest User'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="chat-area">
            <div class="chat-messages" id="chat-scroller">
                <div class="messages-inner" id="message-container">
                    <div class="hero" id="welcome-state">
                        <div
                            style="width: 70px; height: 70px; background: white; border-radius: 20px; display: flex; align-items: center; justify-content: center; color: var(--accent); margin: 0 auto 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                            <i data-lucide="sparkles" style="width: 36px; height: 36px;"></i>
                        </div>
                        <h2>How can I assist you?</h2>
                        <p>I'm your UDRU Wisdom Assistant. Search for documents, experts, or training courses with
                            natural language.</p>

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
                <div style="text-align: center; margin-top: 1rem; font-size: 0.7rem; color: #94a3b8; font-weight: 500;">
                    Powered by UDRU KM Intelligence &bull; AI can make mistakes
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
                bubble.innerHTML = marked.parse(text);
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
            scroller.scrollTop = scroller.scrollHeight;
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
            scroller.scrollTop = scroller.scrollHeight;

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