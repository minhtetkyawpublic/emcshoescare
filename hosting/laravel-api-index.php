<?php
declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$runtimeFile = __DIR__.'/runtime.php';
if (!is_file($runtimeFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => ['code' => 'runtime_not_configured', 'message' => 'The Laravel runtime has not been deployed.']]);
    exit;
}

$basePath = require $runtimeFile;
if (!is_string($basePath) || !is_file($basePath.'/vendor/autoload.php') || !is_file($basePath.'/bootstrap/app.php')) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => ['code' => 'runtime_unavailable', 'message' => 'The Laravel runtime is unavailable.']]);
    exit;
}

$scriptDirectory = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'))), '/');
$cookiePath = preg_replace('#/api$#', '/', $scriptDirectory) ?: '/';
putenv('SESSION_PATH='.$cookiePath);
$_ENV['SESSION_PATH'] = $cookiePath;
$_SERVER['SESSION_PATH'] = $cookiePath;

require $basePath.'/vendor/autoload.php';
/** @var Application $app */
$app = require $basePath.'/bootstrap/app.php';
$app->handleRequest(Request::capture());
