<?php
/**
 * UDRU Wisdom - BOM Checker & Fixer
 * Developed in PHP as requested.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

function check_and_fix_bom($directory, $fix = false) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    $bom = pack("CCC", 0xef, 0xbb, 0xbf);
    $found = [];

    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        
        $path = $file->getPathname();
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        
        if (in_array($ext, ['php', 'css', 'js', 'html'])) {
            $content = file_get_contents($path);
            if (substr($content, 0, 3) === $bom) {
                $found[] = $path;
                if ($fix) {
                    $new_content = substr($content, 3);
                    file_put_contents($path, $new_content);
                    echo "Fixed BOM in: $path\n";
                } else {
                    echo "BOM found in: $path\n";
                }
            }
        }
    }
    return $found;
}

$dir = __DIR__;
echo "Scanning directory: $dir\n";
echo "---------------------------------\n";
$results = check_and_fix_bom($dir, isset($_GET['fix']) || (php_sapi_name() === 'cli' && in_array('--fix', $argv)));

if (empty($results)) {
    echo "No BOM found in any PHP/CSS/JS files.\n";
} else {
    echo "---------------------------------\n";
    echo "Total files with BOM: " . count($results) . "\n";
    if (!isset($_GET['fix']) && !(php_sapi_name() === 'cli' && in_array('--fix', $argv))) {
        echo "Run with ?fix=1 in browser or --fix in CLI to remove them.\n";
    }
}
