<?php
declare(strict_types=1);

// Copy this file to config.local.php and edit it on the hosting server.
// config.local.php is ignored by Git and must never be committed.
return [
    'app' => [
        'env' => 'production',
        'key' => 'replace-with-a-unique-random-string-of-at-least-32-characters',
        'allowed_origins' => ['https://example.com'],
        // Omit cookie_path to detect the installation folder automatically.
        'session_days' => 30,
        'upload_max_bytes' => 5 * 1024 * 1024,
        // Keep disabled until EMC approves the retention period.
        'order_photo_retention_days' => 0,
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'hostinger_database_name',
        'user' => 'hostinger_database_user',
        'pass' => 'replace-with-database-password',
    ],
];
