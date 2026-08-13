<?php
declare(strict_types=1);

function emc_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

return array_replace_recursive([
    'app' => [
        'env' => emc_env('EMC_APP_ENV', 'development'),
        'key' => emc_env('EMC_APP_KEY', 'emc-development-key-change-before-production'),
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', emc_env('EMC_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173') ?? '')
        ))),
        'cookie_name' => emc_env('EMC_COOKIE_NAME', 'emc_session'),
        'cookie_path' => emc_env('EMC_COOKIE_PATH', '/'),
        'session_days' => max(1, (int) emc_env('EMC_SESSION_DAYS', '30')),
        'admin_cookie_name' => emc_env('EMC_ADMIN_COOKIE_NAME', 'emc_admin_session'),
        'upload_max_bytes' => max(1048576, (int) emc_env('EMC_UPLOAD_MAX_BYTES', '5242880')),
        'order_photo_path' => dirname(__DIR__) . '/storage/order-photos',
    ],
    'database' => [
        'host' => emc_env('EMC_DB_HOST', '127.0.0.1'),
        'port' => (int) emc_env('EMC_DB_PORT', '3306'),
        'name' => emc_env('EMC_DB_NAME', 'emc_shoes_care'),
        'user' => emc_env('EMC_DB_USER', 'root'),
        'pass' => emc_env('EMC_DB_PASS', ''),
    ],
], $localConfig);
