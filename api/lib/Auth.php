<?php
declare(strict_types=1);

function publicCustomer(array $customer): array
{
    return [
        'id' => (int) $customer['id'],
        'phone' => $customer['phone'],
        'fullName' => $customer['full_name'],
        'address' => $customer['address'],
    ];
}

function cookieIsSecure(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function issueSessionCookie(array $config, string $token, bool $remember, DateTimeImmutable $expires): void
{
    setcookie($config['app']['cookie_name'], $token, [
        'expires' => $remember ? $expires->getTimestamp() : 0,
        'path' => $config['app']['cookie_path'],
        'secure' => cookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearSessionCookie(array $config): void
{
    setcookie($config['app']['cookie_name'], '', [
        'expires' => time() - 3600,
        'path' => $config['app']['cookie_path'],
        'secure' => cookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function createSession(PDO $pdo, array $config, int $customerId, bool $remember): array
{
    $token = randomToken();
    $csrf = randomToken();
    $days = $remember ? (int) $config['app']['session_days'] : 1;
    $expires = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expires = $expires->modify('+' . $days . ' days');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipBinary = @inet_pton(clientIp()) ?: null;

    $statement = $pdo->prepare(
        'INSERT INTO auth_sessions
            (customer_id, token_hash, csrf_token_hash, user_agent_hash, ip_address, remember_me, expires_at, last_rotated_at, last_used_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $statement->execute([
        $customerId,
        tokenHash($token),
        tokenHash($csrf),
        $userAgent === '' ? null : hash('sha256', $userAgent, true),
        $ipBinary,
        $remember ? 1 : 0,
        $expires->format('Y-m-d H:i:s'),
    ]);

    issueSessionCookie($config, $token, $remember, $expires);
    return ['csrfToken' => $csrf, 'expiresAt' => $expires->format(DateTimeInterface::ATOM)];
}

function currentSession(PDO $pdo, array $config, bool $required = true): ?array
{
    $token = $_COOKIE[$config['app']['cookie_name']] ?? '';
    if (!is_string($token) || strlen($token) < 40) {
        if ($required) {
            throw new ApiException('authentication_required', 'Please sign in to continue.', 401);
        }
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT s.*, c.phone, c.full_name, c.address, c.is_active
         FROM auth_sessions s
         INNER JOIN customers c ON c.id = s.customer_id
         WHERE s.token_hash = ? AND s.expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    $statement->execute([tokenHash($token)]);
    $session = $statement->fetch();
    if (!$session || !(bool) $session['is_active']) {
        clearSessionCookie($config);
        if ($required) {
            throw new ApiException('authentication_required', 'Your session has expired. Please sign in again.', 401);
        }
        return null;
    }

    $rotate = strtotime((string) $session['last_rotated_at']) < time() - 86400;
    $newToken = $rotate ? randomToken() : $token;
    $update = $pdo->prepare(
        'UPDATE auth_sessions
         SET token_hash = ?, last_rotated_at = IF(?, UTC_TIMESTAMP(), last_rotated_at), last_used_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $update->execute([tokenHash($newToken), $rotate ? 1 : 0, $session['id']]);

    if ($rotate) {
        $expires = new DateTimeImmutable((string) $session['expires_at'], new DateTimeZone('UTC'));
        issueSessionCookie($config, $newToken, (bool) $session['remember_me'], $expires);
    }

    return $session;
}

function refreshCsrf(PDO $pdo, int|string $sessionId): string
{
    $csrf = randomToken();
    $statement = $pdo->prepare('UPDATE auth_sessions SET csrf_token_hash = ? WHERE id = ?');
    $statement->execute([tokenHash($csrf), $sessionId]);
    return $csrf;
}

function assertCsrf(array $session): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || $provided === '' || !hash_equals($session['csrf_token_hash'], tokenHash($provided))) {
        throw new ApiException('csrf_failed', 'Your secure session changed. Refresh the page and try again.', 403);
    }
}
