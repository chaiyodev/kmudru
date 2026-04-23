<?php
/**
 * Database Performance Indexes
 * Run this once to add indexes for frequently queried columns.
 * Safe to run multiple times (uses IF NOT EXISTS equivalent).
 */
require_once __DIR__ . '/includes/db.php';

$pdo = get_pdo();
if (!$pdo) {
    die("Database connection failed.");
}

$indexes = [
    // Documents table — most queried table
    "ALTER TABLE documents ADD INDEX idx_status_created (status, created_at DESC)",
    "ALTER TABLE documents ADD INDEX idx_user_id (user_id)",
    "ALTER TABLE documents ADD INDEX idx_category_id (category_id)",
    "ALTER TABLE documents ADD INDEX idx_type (type)",
    "ALTER TABLE documents ADD INDEX idx_created_at (created_at)",

    // Document likes — used for COUNT queries
    "ALTER TABLE document_likes ADD INDEX idx_doc_id (document_id)",
    "ALTER TABLE document_likes ADD INDEX idx_user_doc (user_id, document_id)",

    // Comments — used for COUNT and listing
    "ALTER TABLE comments ADD INDEX idx_doc_id (document_id)",
    "ALTER TABLE comments ADD INDEX idx_user_id (user_id)",

    // Activity logs — used for AI search stats
    "ALTER TABLE activity_logs ADD INDEX idx_action (action)",
    "ALTER TABLE activity_logs ADD INDEX idx_action_details (action, details(100))",
    "ALTER TABLE activity_logs ADD INDEX idx_user_action (user_id, action)",

    // Chat messages — used for notification counts
    "ALTER TABLE chat_messages ADD INDEX idx_receiver_read (receiver_id, is_read)",
    "ALTER TABLE chat_messages ADD INDEX idx_sender_receiver (sender_id, receiver_id)",

    // Notifications — used for unread counts
    "ALTER TABLE notifications ADD INDEX idx_user_read (user_id, is_read)",

    // Users — used for login and search
    "ALTER TABLE users ADD INDEX idx_username (username)",
    "ALTER TABLE users ADD INDEX idx_last_activity (last_activity)",

    // Visitor stats — used in admin dashboard
    "ALTER TABLE visitor_stats ADD INDEX idx_visit_date (visit_date)",
    "ALTER TABLE visitor_stats ADD INDEX idx_is_internal (is_internal)",
    "ALTER TABLE visitor_stats ADD INDEX idx_page_visited (page_visited(100))",
];

$success = 0;
$skipped = 0;
$errors = 0;

echo "<h2>🔧 Adding Performance Indexes</h2><pre>";

foreach ($indexes as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ $sql\n";
        $success++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            // Index already exists
            $idx_name = '';
            preg_match('/ADD INDEX (\w+)/', $sql, $m);
            $idx_name = $m[1] ?? '';
            echo "⏭️  Index '$idx_name' already exists — skipped\n";
            $skipped++;
        } elseif (strpos($e->getMessage(), "doesn't exist") !== false) {
            // Table doesn't exist yet
            echo "⚠️  Table not found — skipped: $sql\n";
            $skipped++;
        } else {
            echo "❌ Error: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

echo "\n--- Summary ---\n";
echo "✅ Added: $success\n";
echo "⏭️  Skipped: $skipped\n";
echo "❌ Errors: $errors\n";
echo "</pre>";
