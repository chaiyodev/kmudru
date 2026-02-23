<?php
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $stmt = $pdo->query("SHOW DATABASES");
    echo "<h3>Databases Found:</h3><ul>";
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>