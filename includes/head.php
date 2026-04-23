<?php
// includes/head.php
// Standardized Header section to be used across the application.
// 
// Expected variables from the parent file:
// $page_title - String for the page title
// $extra_css  - String for any additional <link> or <style> tags
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'UDRU Wisdom | UDRU Knowledge Hub'); ?></title>
    
    <!-- Core CSS -->
    <?php 
    $css_path = __DIR__ . '/../assets/css/style.css';
    $version = file_exists($css_path) ? filemtime($css_path) : time();
    ?>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $version; ?>">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- External Libraries -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <script>
        // Apply Global Theme Settings immediately
        (function () {
            const savedTheme = localStorage.getItem('theme-primary');
            if (savedTheme) {
                document.documentElement.style.setProperty('--primary', savedTheme);
                document.documentElement.style.setProperty('--teal-primary', `hsl(${savedTheme})`);
            }
        })();
    </script>
