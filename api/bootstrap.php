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

if (($config['app']['env'] ?? 'production') === 'production'
    && ($config['app']['key'] === 'emc-development-key-change-before-production' || strlen((string) $config['app']['key']) < 32)) {
    emcConfigurationFailure('EMC_APP_KEY must be set to a unique value of at least 32 characters in production.');
}

if (($config['app']['env'] ?? 'production') === 'production') {
    if (($config['database']['user'] ?? '') === 'root' || ($config['database']['pass'] ?? '') === '') {
        emcConfigurationFailure('Production must use a password-protected, non-root MySQL account.');
    }
    foreach ($config['app']['allowed_origins'] ?? [] as $allowedOrigin) {
        if (!str_starts_with(strtolower((string) $allowedOrigin), 'https://')) {
            emcConfigurationFailure('Every production EMC_ALLOWED_ORIGINS entry must use HTTPS.');
        }
    }
    if (PHP_SAPI !== 'cli' && !cookieIsSecure()) {
        jsonError('https_required', 'HTTPS is required.', 426);
    }
}
