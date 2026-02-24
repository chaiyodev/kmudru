<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();

echo "Starting Wiki Enhancement Test...\n";

try {
    // 1. Create a Test User if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'testuser'");
    $stmt->execute();
    $user = $stmt->fetch();
    if (!$user) {
        $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES ('testuser', 'pass', 'Test User', 'test@example.com', 'contributor')")->execute();
        $user_id = $pdo->lastInsertId();
    } else {
        $user_id = $user['id'];
    }

    // 2. Simulate User Login
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = 'testuser';
    $_SESSION['role'] = 'contributor';
    $_SESSION['full_name'] = 'Test User';
    $_SESSION['csrf_token'] = 'test_token';

    echo "✔ Simulated login for user ID $user_id\n";

    // 3. Test Creation (wiki_create.php logic)
    $title = "Test Wiki Article " . time();
    $content = "Initial Content";
    $refs = "Source: Test";

    $stmt = $pdo->prepare("INSERT INTO documents (title, content, category_id, user_id, type, tags, doc_references) VALUES (?, ?, 1, ?, 'wiki', 'test', ?)");
    $stmt->execute([$title, $content, $user_id, $refs]);
    $doc_id = $pdo->lastInsertId();

    // Initial Version (5 placeholders)
    $v_stmt = $pdo->prepare("INSERT INTO document_versions (document_id, user_id, title_snapshot, content_snapshot, references_snapshot, edit_summary) VALUES (?, ?, ?, ?, ?, 'Initial Creation')");
    $v_stmt->execute([$doc_id, $user_id, $title, $content, $refs]);

    echo "✔ Created Wiki article ID $doc_id and initial version.\n";

    // 4. Test Editing (edit.php logic)
    $new_content = "Updated Content";
    $new_refs = "Source: Updated";
    $edit_summary = "First Update Test";

    // Update Main Doc
    $update_stmt = $pdo->prepare("UPDATE documents SET content = ?, doc_references = ?, last_editor_id = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$new_content, $new_refs, $user_id, $doc_id]);

    // Save Version (using separate prepare for 6 placeholders)
    $v2_stmt = $pdo->prepare("INSERT INTO document_versions (document_id, user_id, title_snapshot, content_snapshot, references_snapshot, edit_summary) VALUES (?, ?, ?, ?, ?, ?)");
    $v2_stmt->execute([$doc_id, $user_id, $title, $new_content, $new_refs, $edit_summary]);

    echo "✔ Updated Wiki article and saved new version.\n";

    // 5. Verify Database Records
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM document_versions WHERE document_id = ?");
    $check_stmt->execute([$doc_id]);
    $version_count = $check_stmt->fetchColumn();

    if ($version_count == 2) {
        echo "✔ VERIFIED: Found exactly 2 versions for document $doc_id\n";
    } else {
        echo "✖ FAILED: Expected 2 versions, found $version_count\n";
    }

    $doc_check = $pdo->prepare("SELECT doc_references FROM documents WHERE id = ?");
    $doc_check->execute([$doc_id]);
    $current_refs = $doc_check->fetchColumn();

    if ($current_refs === $new_refs) {
        echo "✔ VERIFIED: References updated correctly in documents table.\n";
    } else {
        echo "✖ FAILED: References mismatch. Expected '$new_refs', got '$current_refs'\n";
    }

    echo "\nTEST COMPLETED SUCCESSFULLY\n";

} catch (Exception $e) {
    echo "✖ TEST FAILED with error: " . $e->getMessage() . "\n";
}
?>