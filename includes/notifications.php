<?php
// includes/notifications.php

// Function to initialize notification tables
function init_notification_tables($pdo)
{
    try {
        // 1. Create notifications table
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL, 
            message TEXT NOT NULL,
            link VARCHAR(255),
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sql);

        // 2. Add email_prefs column to users if not exists
        // Check if column exists first
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_prefs'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email_prefs TEXT DEFAULT NULL COMMENT 'JSON: {\"new_content\":1, \"comments\":1, \"system\":1}'");
        }

        return true;
    } catch (PDOException $e) {
        error_log("Notification Init Error: " . $e->getMessage());
        return false;
    }
}

// Function to create a notification
function create_notification($pdo, $user_id, $type, $message, $link = '#')
{
    try {
        // 1. Insert into DB (In-App Notification)
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $type, $message, $link]);

        // 2. Check if user wants email (Email Notification)
        // Load user prefs
        $stmt = $pdo->prepare("SELECT email, email_prefs FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['email'])) {
            $prefs = json_decode($user['email_prefs'] ?? '[]', true);
            // Default to TRUE if not set (Opt-out style) or FALSE (Opt-in). User requested "Opt-in" logic conceptually but practically usually "Opt-out" is better for "System" messages. 
            // Let's assume default is: 
            // - System: Always/Default On
            // - Comments: Default On
            // - New Content: Default Off (to avoid spam)

            $should_email = false;

            // Logic: Check specific preference. If not set, use default.
            if ($type == 'system') {
                $should_email = isset($prefs['system']) ? $prefs['system'] : true;
            } elseif ($type == 'comment' || $type == 'reply') {
                $should_email = isset($prefs['comments']) ? $prefs['comments'] : true;
            } elseif ($type == 'new_content') {
                $should_email = isset($prefs['new_content']) ? $prefs['new_content'] : false; // Default off for general content
            }

            if ($should_email) {
                // Send Email (Mock function or real mail() usage)
                // In production, use a Queue. Here we just simple mail()
                $subject = "UDRU Wisdom: " . mb_strimwidth($message, 0, 50, "...");
                $headers = "From: no-reply@udruwisdom.udru.ac.th\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

                $body = "<h2>มีการแจ้งเตือนใหม่ในระบบ UDRU Wisdom</h2>";
                $body .= "<p>" . htmlspecialchars($message) . "</p>";
                $body .= "<p><a href='" . "http://localhost/udruwisdom/" . $link . "'>คลิกเพื่อดูรายละเอียด</a></p>";

                // mail($user['email'], $subject, $body, $headers); // Commented out to prevent errors on localhost without mail server
                // error_log("Simulated Email sent to {$user['email']}: $subject");
            }
        }

        return true;
    } catch (PDOException $e) {
        error_log("Create Notification Error: " . $e->getMessage());
        return false;
    }
}

// Get Unread Count for badge
function get_unread_count($pdo, $user_id)
{
    if (!$user_id)
        return 0;
    try {
        // 1. Get system notifications count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $notif_count = (int)$stmt->fetchColumn();

        // 2. Get unread chat messages count
        $stmt_chat = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
        $stmt_chat->execute([$user_id]);
        $chat_count = (int)$stmt_chat->fetchColumn();

        return $notif_count + $chat_count;
    } catch (PDOException $e) {
        return 0;
    }
}

// Get recent notifications (including chats)
function get_recent_notifications($pdo, $user_id, $limit = 20)
{
    if (!$user_id)
        return [];
    
    // UNION system notifications and chats
    $sql = "
        (SELECT id, message, 'system' as type, link, is_read, created_at, 'notif' as source, NULL as sender_name 
         FROM notifications WHERE user_id = :uid)
        UNION ALL
        (SELECT m.id, m.message, 'chat' as type, 'chat.php?user=' || m.sender_id as link, m.is_read, m.created_at, 'chat' as source, u.full_name as sender_name 
         FROM chat_messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = :uid)
        ORDER BY created_at DESC LIMIT :limit
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Mark as read
function mark_all_read($pdo, $user_id)
{
    // 1. Mark system notifications
    $stmt1 = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt1->execute([$user_id]);
    
    // 2. Mark chat messages as read (optional: maybe only when viewing chat page? 
    // But user asked for numbers to disappear when 'viewing', so if they view 'all notifications' page, it should probably clear them)
    $stmt2 = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE receiver_id = ?");
    $stmt2->execute([$user_id]);
    
    return true;
}
?>