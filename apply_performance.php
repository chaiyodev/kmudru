<?php
/**
 * Optimized Performance Update Script
 * This script applies the approved Point 1 & 2 fixes across the codebase.
 */
$dir = __DIR__;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$phpFiles = [];

foreach ($files as $file) {
    if (pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'php') {
        $phpFiles[] = $file->getRealPath();
    }
}

$updatedCount = 0;

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    $original = $content;
    
    // 1. Lucide -> 0.475.0 and defer
    // Match common variations of the script tag
    $content = preg_replace('/<script src="https:\/\/unpkg\.com\/lucide(@latest)?"><\/script>/', '<script src="https://unpkg.com/lucide@0.475.0" defer></script>', $content);
    
    // 2. Remove from sidebar.php explicitly if it exists
    if (basename($file) == 'sidebar.php') {
        $content = preg_replace('/<script src="https:\/\/unpkg\.com\/lucide(@latest|@0\.475\.0)?"( defer)?><\/script>\s*/', '', $content);
    }
    
    // 3. Fonts - Add preconnect and display=swap
    if (strpos($content, 'fonts.googleapis.com') !== false) {
        // Add preconnect if missing
        if (strpos($content, '<link rel="preconnect"') === false) {
             $content = preg_replace(
                '/(<link[^>]*href="https:\/\/fonts\.googleapis\.com[^>]*>)/', 
                "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n    $1", 
                $content
            );
        }
        
        // Add display=swap if missing
        if (strpos($content, 'display=swap') === false) {
            $content = preg_replace('/(href="https:\/\/fonts\.googleapis\.com\/css2\?[^"]+)"/', '$1&display=swap"', $content);
        }
        
        // Optimize weights
        $content = str_replace('Inter:wght@400;500;600;700;800', 'Inter:wght@400;600;700;800', $content);
        $content = str_replace('Sarabun:wght@300;400;500;600;700', 'Sarabun:wght@400;500;700', $content);
        $content = str_replace('Sarabun:wght@400;500;600;700', 'Sarabun:wght@400;500;700', $content);
    }
    
    // 4. Update Viewport for iOS if missing viewport-fit=cover
    if (strpos($content, 'name="viewport"') !== false && strpos($content, 'viewport-fit=cover') === false) {
        $content = preg_replace('/(content="width=device-width, initial-scale=1\.0)"/', '$1, viewport-fit=cover"', $content);
    }

    if ($content !== $original) {
        file_put_contents($file, $content);
        $updatedCount++;
    }
}

echo "SUCCESS: Updated $updatedCount PHP files for performance.\n";
