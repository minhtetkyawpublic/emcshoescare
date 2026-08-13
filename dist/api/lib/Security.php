<?php
declare(strict_types=1);

function normalizePhone(mixed $phone): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
    if (str_starts_with($digits, '959')) {
        $digits = '0' . substr($digits, 2);
    } elseif (str_starts_with($digits, '9')) {
        $digits = '0' . $digits;
    }
    return $digits;
}

function validateAccountInput(array $data, bool $registration): array
{
    $phone = normalizePhone($data['phone'] ?? '');
    $password = (string) ($data['password'] ?? '');
    $name = trim((string) ($data['fullName'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $fields = [];

    if (!preg_match('/^09\d{7,9}$/', $phone)) {
        $fields['phone'] = 'Enter a valid Myanmar phone number.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $fields['password'] = 'Password must be between 8 and 72 characters.';
    }
    if ($registration) {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $fields['fullName'] = 'Name must be between 2 and 120 characters.';
        }
        if (mb_strlen($address) > 500) {
            $fields['address'] = 'Address cannot be longer than 500 characters.';
        }
    }

    if ($fields !== []) {
        throw new ApiException('validation_failed', 'Please check the highlighted fields.', 422, $fields);
    }

    return compact('phone', 'password', 'name', 'address');
}

function validateProfileInput(array $data): array
{
    $name = trim((string) ($data['fullName'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $fields = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $fields['fullName'] = 'Name must be between 2 and 120 characters.';
    }
    if (mb_strlen($address) > 500) {
        $fields['address'] = 'Address cannot be longer than 500 characters.';
    }
    if ($fields !== []) {
        throw new ApiException('validation_failed', 'Please check the highlighted fields.', 422, $fields);
    }
    return compact('name', 'address');
}

function clientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

function rateLimitKey(array $config, string $action, string $phone): string
{
    return hash_hmac('sha256', $action . '|' . clientIp() . '|' . $phone, $config['app']['key'], true);
}

function consumeRateLimit(PDO $pdo, array $config, string $action, string $phone, int $maxAttempts = 8): void
{
    $key = rateLimitKey($config, $action, $phone);
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM rate_limit_attempts WHERE bucket_key = ? AND action = ? AND attempted_at >= (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
    );
    $statement->execute([$key, $action]);
    if ((int) $statement->fetchColumn() >= $maxAttempts) {
        throw new ApiException('too_many_attempts', 'Too many attempts. Please wait 15 minutes and try again.', 429);
    }

    $insert = $pdo->prepare('INSERT INTO rate_limit_attempts (bucket_key, action, attempted_at) VALUES (?, ?, UTC_TIMESTAMP())');
    $insert->execute([$key, $action]);

    if (random_int(1, 100) === 1) {
        $pdo->exec('DELETE FROM rate_limit_attempts WHERE attempted_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)');
    }
}

function randomToken(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function tokenHash(string $token): string
{
    return hash('sha256', $token, true);
}
