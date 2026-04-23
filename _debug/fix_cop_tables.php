<?php
require_once 'includes/db.php';
$pdo = get_pdo();

echo "<h1>Updating Database for CoP Interactions...</h1>";

try {
    // 1. Add image columns to community_posts if not exists
    $cols = $pdo->query("DESCRIBE community_posts")->fetchAll(PDO::FETCH_COLUMN);
    $post_updates = [
        'image1' => "ALTER TABLE community_posts ADD COLUMN image1 VARCHAR(255)",
        'image2' => "ALTER TABLE community_posts ADD COLUMN image2 VARCHAR(255)",
        'image3' => "ALTER TABLE community_posts ADD COLUMN image3 VARCHAR(255)"
    ];
    foreach ($post_updates as $col => $sql) {
        if (!in_array($col, $cols)) { $pdo->exec($sql); echo "Added $col.<br>"; }
    }

    // 2. Create Likes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_post_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(post_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Likes table ready.<br>";

    // 3. Create Comments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_post_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Comments table ready.<br>";
    
    // 4. Update community_resources table if exists or create if not
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        community_id INT NOT NULL,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Check missing columns in community_resources
    $res_cols = $pdo->query("DESCRIBE community_resources")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('file_type', $res_cols)) {
        $pdo->exec("ALTER TABLE community_resources ADD COLUMN file_type VARCHAR(50) AFTER file_path");
        echo "Added file_type to community_resources.<br>";
    }
    echo "Community Resources table ready.<br>";

    echo "<h1>Database Synced!</h1><a href='cop_view.php?id=" . ($_GET['id'] ?? 1) . "'>Back to CoP</a>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
