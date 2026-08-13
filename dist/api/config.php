<?php
declare(strict_types=1);

function emc_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function emc_default_cookie_path(): string
{
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $applicationRoot = realpath(dirname(__DIR__));
    if ($documentRoot && $applicationRoot) {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $applicationRoot = str_replace('\\', '/', $applicationRoot);
        if ($applicationRoot === $documentRoot) {
            return '/';
        }
        if (str_starts_with($applicationRoot, $documentRoot . '/')) {
            return '/' . trim(substr($applicationRoot, strlen($documentRoot)), '/') . '/';
        }
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.*?)/api(?:/index\.php)?$#', $scriptName, $matches)) {
        return '/' . trim($matches[1], '/') . (trim($matches[1], '/') === '' ? '' : '/');
    }
    return '/';
}

$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

$config = array_replace_recursive([
    'app' => [
        'env' => emc_env('EMC_APP_ENV', 'development'),
        'key' => emc_env('EMC_APP_KEY', 'emc-development-key-change-before-production'),
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', emc_env('EMC_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173') ?? '')
        ))),
        'cookie_name' => emc_env('EMC_COOKIE_NAME', 'emc_session'),
        'cookie_path' => emc_env('EMC_COOKIE_PATH'),
        'session_days' => max(1, (int) emc_env('EMC_SESSION_DAYS', '30')),
        'admin_cookie_name' => emc_env('EMC_ADMIN_COOKIE_NAME', 'emc_admin_session'),
        'upload_max_bytes' => max(1048576, (int) emc_env('EMC_UPLOAD_MAX_BYTES', '5242880')),
        'order_photo_retention_days' => max(0, (int) emc_env('EMC_ORDER_PHOTO_RETENTION_DAYS', '0')),
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

if (!is_string($config['app']['cookie_path'] ?? null) || $config['app']['cookie_path'] === '') {
    $config['app']['cookie_path'] = ($config['app']['env'] ?? 'development') === 'production'
        ? emc_default_cookie_path()
        : '/';
}

return $config;
