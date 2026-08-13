<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/Database.php';

$username = trim((string) ($argv[1] ?? getenv('EMC_ADMIN_USER') ?: ''));
$password = (string) ($argv[2] ?? getenv('EMC_ADMIN_PASSWORD') ?: '');
$displayName = trim((string) ($argv[3] ?? getenv('EMC_ADMIN_NAME') ?: 'EMC Administrator'));

if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || strlen($password) < 10 || strlen($password) > 72 || mb_strlen($displayName) < 2) {
    fwrite(STDERR, "Usage: php create-admin.php <username> <password-min-10-chars> [display-name]\n");
    exit(1);
}

$pdo = database($config);
$statement = $pdo->prepare(
    'INSERT INTO admins (username, password_hash, display_name, is_active)
     VALUES (?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name), is_active = 1'
);
$statement->execute([$username, password_hash($password, PASSWORD_DEFAULT), $displayName]);
fwrite(STDOUT, "Administrator account created or updated: {$username}\n");
