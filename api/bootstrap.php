<?php
declare(strict_types=1);

const EMC_API_VERSION = '5.0.0';

$config = require __DIR__ . '/config.php';

if (($config['app']['env'] ?? 'production') === 'production'
    && ($config['app']['key'] === 'emc-development-key-change-before-production' || strlen((string) $config['app']['key']) < 32)) {
    throw new RuntimeException('EMC_APP_KEY must be set to a unique value of at least 32 characters in production.');
}

require_once __DIR__ . '/lib/Http.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Security.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/AdminAuth.php';
require_once __DIR__ . '/lib/Orders.php';

configureHttp($config);

set_exception_handler(static function (Throwable $exception) use ($config): void {
    if ($exception instanceof ApiException) {
        jsonError($exception->errorCode, $exception->getMessage(), $exception->status, $exception->fields);
    }

    error_log(sprintf('[EMC API] %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine()));
    $message = ($config['app']['env'] ?? 'production') === 'development'
        ? $exception->getMessage()
        : 'Something went wrong. Please try again.';
    jsonError('server_error', $message, 500);
});
