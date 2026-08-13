<?php
declare(strict_types=1);

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: {$message}; expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

putenv('EMC_APP_ENV=production');
putenv('EMC_COOKIE_PATH');
$_SERVER['DOCUMENT_ROOT'] = '/unrelated/document-root';
$_SERVER['SCRIPT_NAME'] = '/clients/alpha/emc/api/index.php';
$config = require dirname(__DIR__) . '/api/config.php';
assertSameValue('/clients/alpha/emc/', $config['app']['cookie_path'], 'production cookie path follows the installation folder');

require_once dirname(__DIR__) . '/api/lib/Http.php';

$_SERVER['REQUEST_URI'] = '/clients/alpha/emc/api/orders/42?view=full';
$_SERVER['SCRIPT_NAME'] = '/clients/alpha/emc/api/index.php';
assertSameValue('/orders/42', requestPath(), 'nested API path is stripped exactly');

$_SERVER['REQUEST_URI'] = '/clients/api-project/emc/api/orders';
$_SERVER['SCRIPT_NAME'] = '/clients/api-project/emc/api/index.php';
assertSameValue('/orders', requestPath(), 'an installation folder containing api does not confuse routing');

$_SERVER['REQUEST_URI'] = '/api/health';
$_SERVER['SCRIPT_NAME'] = '/api/index.php';
assertSameValue('/health', requestPath(), 'document-root API routing works');

fwrite(STDOUT, "PASS arbitrary-subfolder cookie and API routing\n");
