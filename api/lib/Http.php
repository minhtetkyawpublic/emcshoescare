<?php
declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $fields = []
    ) {
        parent::__construct($message);
    }
}

function configureHttp(array $config): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: geolocation=(), microphone=()');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, private');

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, $config['app']['allowed_origins'], true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
        http_response_code(204);
        exit;
    }
}

function jsonSuccess(array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $code, string $message, int $status = 400, array $fields = []): never
{
    http_response_code($status);
    $error = ['code' => $code, 'message' => $message];
    if ($fields !== []) {
        $error['fields'] = $fields;
    }
    echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonBody(): array
{
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (!str_starts_with($contentType, 'application/json')) {
        throw new ApiException('invalid_content_type', 'Please send JSON data.', 415);
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 65536) {
        throw new ApiException('invalid_request', 'The request is too large.', 413);
    }

    try {
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ApiException('invalid_json', 'The request contains invalid JSON.', 400);
    }

    if (!is_array($data)) {
        throw new ApiException('invalid_json', 'The request must contain a JSON object.', 400);
    }
    return $data;
}

function requestPath(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $apiPosition = strpos($path, '/api');
    if ($apiPosition !== false) {
        $path = substr($path, $apiPosition + 4);
    }
    $path = '/' . trim($path, '/');
    return $path === '//' ? '/' : $path;
}

function assertTrustedBrowserRequest(array $config): void
{
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        throw new ApiException('untrusted_origin', 'This request was blocked for your security.', 403);
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && !in_array($origin, $config['app']['allowed_origins'], true)) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $originHost = parse_url($origin, PHP_URL_HOST) ?: '';
        $requestHost = explode(':', $host)[0];
        if ($originHost === '' || !hash_equals(strtolower($requestHost), strtolower($originHost))) {
            throw new ApiException('untrusted_origin', 'This request was blocked for your security.', 403);
        }
    }
}
