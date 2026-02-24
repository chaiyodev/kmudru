<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Simulate login as contributor (id 6: chai.awi)
$_SESSION['user_id'] = 6;
$_SESSION['username'] = 'chai.awi';
$_SESSION['role'] = 'contributor';
$_SESSION['csrf_token'] = 'test_token';

$id = 9; // Wiki doc owned by admin (id 1)
$pdo = get_pdo();

// Prepare POST data
$_POST['title'] = "Test Edit by Contributor";
$_POST['content'] = "New content for testing collaboration tracking.";
$_POST['category_id'] = 3;
$_POST['csrf_token'] = 'test_token';

// Execute the logic from edit.php
require 'edit.php'; // This might cause issues with headers/die, but let's try a direct update first

echo "SUCCESS if update reached.";
?>