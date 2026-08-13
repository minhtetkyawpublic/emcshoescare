<?php
declare(strict_types=1);

const EMC_API_VERSION = '5.0.0';

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/lib/Http.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Security.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/AdminAuth.php';
require_once __DIR__ . '/lib/Orders.php';

configureHttp($config);

set_exception_handler(static function (Throwable $exception) use ($config): void {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
    if ($exception instanceof ApiException) {
        jsonError($exception->errorCode, $exception->getMessage(), $exception->status, $exception->fields);
    }

    error_log(sprintf('[EMC API] %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine()));
    $message = ($config['app']['env'] ?? 'production') === 'development'
        ? $exception->getMessage()
        : 'Something went wrong. Please try again.';
    jsonError('server_error', $message, 500);
});

function emcConfigurationFailure(string $message): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    error_log('[EMC API] Configuration error: ' . $message);
    jsonError('server_configuration_error', 'The service is not configured for production.', 500);
}

if (($config['app']['env'] ?? 'production') === 'production') {
    $appKey = (string) ($config['app']['key'] ?? '');
    if (strlen($appKey) < 32 || in_array($appKey, [
        'emc-development-key-change-before-production',
        'replace-with-a-unique-random-string-of-at-least-32-characters',
    ], true)) {
        emcConfigurationFailure('EMC_APP_KEY must be set to a unique value of at least 32 characters in production.');
    }
    $databaseName = (string) ($config['database']['name'] ?? '');
    $databaseUser = (string) ($config['database']['user'] ?? '');
    $databasePassword = (string) ($config['database']['pass'] ?? '');
    if ($databaseUser === 'root' || $databasePassword === ''
        || $databaseName === 'hostinger_database_name'
        || $databaseUser === 'hostinger_database_user'
        || $databasePassword === 'replace-with-database-password') {
        emcConfigurationFailure('Production must use a password-protected, non-root MySQL account.');
    }
    $allowedOrigins = $config['app']['allowed_origins'] ?? [];
    if ($allowedOrigins === [] || in_array('https://example.com', $allowedOrigins, true)) {
        emcConfigurationFailure('Production must define the final HTTPS allowed origin.');
    }
    foreach ($allowedOrigins as $allowedOrigin) {
        if (!str_starts_with(strtolower((string) $allowedOrigin), 'https://')) {
            emcConfigurationFailure('Every production EMC_ALLOWED_ORIGINS entry must use HTTPS.');
        }
    }
    if (PHP_SAPI !== 'cli' && !cookieIsSecure()) {
        jsonError('https_required', 'HTTPS is required.', 426);
    }
}
