<?php
/**
 * UDRU Wisdom - Ultimate Debugger
 * This tool traps ALL ERRORS including syntax errors in included files.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<div style='font-family: Arial, sans-serif; padding: 30px; background: #f0f4f8; min-height: 100vh;'>";
echo "<h1 style='color: #1e293b;'>UDRU Wisdom - Ultimate Debugger</h1>";

// --- Helper: Error display ---
function show_error($type, $msg, $file, $line, $color = "#b91c1c", $bg = "#fee") {
    echo "<div style='color:$color; border-left: 5px solid $color; padding: 15px; margin: 15px 0; background: $bg; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>";
    echo "<h3 style='margin-top:0;'>$type</h3>";
    echo "<strong>Message:</strong> $msg<br>";
    echo "<strong>File:</strong> $file<br>";
    echo "<strong>Line:</strong> $line";
    echo "</div>";
}

// 1. Error Handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    show_error("PHP Error [$errno]", $errstr, $errfile, $errline);
    return true;
});

// 2. Exception Handler
set_exception_handler(function($e) {
    show_error("Uncaught Exception", $e->getMessage(), $e->getFile(), $e->getLine(), "#7c2d12", "#fff7ed");
});

// 3. Shutdown function for Fatal Errors (Syntax errors, etc.)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL) {
        $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (in_array($error['type'], $fatal_types)) {
            show_error("CRITICAL FATAL ERROR", $error['message'], $error['file'], $error['line'], "white", "#7f1d1d");
        }
    }
    echo "<hr><p style='color: #64748b;'>Check Completed. If you see no red boxes above, the 500 error might be caused by an .htaccess file or Server Configuration (Web Server Level).</p>";
    echo "</div>";
});

echo "<p><strong>Phase 1: Testing file existence & readability...</strong></p>";
$important_files = [
    'includes/config.php',
    'includes/db.php',
    'includes/security.php',
    'includes/auth.php',
    'includes/head.php',
    'includes/footer.php',
    'login.php'
];

echo "<ul>";
foreach ($important_files as $f) {
    $exists = file_exists(__DIR__ . '/' . $f);
    $readable = $exists ? is_readable(__DIR__ . '/' . $f) : false;
    $status = $exists ? ($readable ? "<span style='color:green;'>Found & Readable</span>" : "<span style='color:orange;'>Found but NOT READABLE</span>") : "<span style='color:red;'>MISSING</span>";
    echo "<li>$f: $status</li>";
}
echo "</ul>";

echo "<p><strong>Phase 2: Attempting to run login.php logic...</strong></p>";
echo "<div style='background: white; padding: 10px; border: 1px solid #ddd; height: 100px; overflow: auto; font-size: 12px; color: #999;'>";
// We use output buffering to capture any output before error
ob_start();
include 'login.php';
$output = ob_get_clean();
echo "Execution finished. (Output size: " . strlen($output) . " bytes)</div>";

?>
