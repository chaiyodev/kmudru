<?php
/**
 * UDRU Wisdom - Sample Configuration File
 * Copy this file to config.php and fill in your actual credentials.
 */

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'kmud_db',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'google' => [
        'client_id' => 'PASTE_HERE',
        'client_secret' => 'PASTE_HERE',
        'redirect_uri' => 'http://localhost/kmudru/login.php',
    ],
    'app' => [
        'name' => 'UDRU Wisdom',
        'url' => 'http://localhost/kmudru',
    ]
];
