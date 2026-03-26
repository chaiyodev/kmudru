<?php
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
    $content = preg_replace('/<script src="https:\/\/unpkg\.com\/lucide@latest"><\/script>/', '<script src="https://unpkg.com/lucide@0.475.0" defer></script>', $content);
    
    // Remove if it's sidebar
    if (basename($file) == 'sidebar.php') {
        $content = str_replace('<script src="https://unpkg.com/lucide@latest"></script>', '', $content);
        $content = str_replace('<script src="https://unpkg.com/lucide@0.475.0" defer></script>', '', $content);
        // Also remove if there's trailing newlines that were left
        $content = preg_replace("/\n\s*$/", "", str_replace('<script src="https://unpkg.com/lucide@0.475.0" defer></script>'."\n", '', $content));
    }
    
    // 2. Fonts
    // Add preconnect if not exists
    if (strpos($content, 'fonts.googleapis.com') !== false && strpos($content, '<link rel="preconnect"') === false) {
        $content = preg_replace(
            '/(<link[^>]*href="https:\/\/fonts\.googleapis\.com[^>]*>)/', 
            "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n    $1", 
            $content
        );
    }
    
    // Adjust weights for Inter
    $content = str_replace('Inter:wght@400;500;600;700;800', 'Inter:wght@400;600;700;800', $content);
    
    // Adjust weights for Sarabun
    $content = str_replace('Sarabun:wght@300;400;500;600;700', 'Sarabun:wght@400;500;700', $content);
    $content = str_replace('Sarabun:wght@400;500;600;700', 'Sarabun:wght@400;500;700', $content);
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        $updatedCount++;
        echo "Updated: " . basename($file) . "\n";
    }
}

// Update style.css
$cssFile = $dir . '/assets/css/style.css';
if (file_exists($cssFile)) {
    $content = file_get_contents($cssFile);
    $original = $content;
    
    $content = preg_replace('/\.main-viewport\s*\{\s*margin-left:\s*0\s*!important;\s*padding:\s*1\.5rem\s*1rem\s*!important;\s*\}/s', '', $content);
    
    if ($content !== $original) {
        file_put_contents($cssFile, $content);
        echo "Updated: style.css\n";
    }
}

echo "Done. Total PHP files updated: $updatedCount\n";
