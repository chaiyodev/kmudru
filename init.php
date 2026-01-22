<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents(__DIR__ . '/setup.sql');
    
    // Split SQL by semicolon, but be careful with LONGTEXT if it contains semicolons (basic split for now)
    // A better way is to execute the whole thing if the driver supports it, or use multi_query
    $pdo->exec($sql);

    echo "Database and tables created successfully.<br>";

    // Create necessary directories
    $dirs = [
        'assets/css',
        'assets/js',
        'assets/img',
        'uploads',
        'includes',
        'api'
    ];

    foreach ($dirs as $dir) {
        if (!is_dir(__DIR__ . '/' . $dir)) {
            mkdir(__DIR__ . '/' . $dir, 0777, true);
            echo "Created directory: $dir<br>";
        }
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
