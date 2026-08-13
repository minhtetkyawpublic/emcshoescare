<?php
declare(strict_types=1);

function publicAdmin(array $admin): array
{
    return [
        'id' => (int) ($admin['admin_id'] ?? $admin['id']),
        'username' => $admin['username'],
        'displayName' => $admin['display_name'],
    ];
}

function issueAdminCookie(array $config, string $token, DateTimeImmutable $expires): void
{
    setcookie($config['app']['admin_cookie_name'], $token, [
        'expires' => $expires->getTimestamp(),
        'path' => $config['app']['cookie_path'],
        'secure' => cookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function clearAdminCookie(array $config): void
{
    setcookie($config['app']['admin_cookie_name'], '', [
        'expires' => time() - 3600,
        'path' => $config['app']['cookie_path'],
        'secure' => cookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function createAdminSession(PDO $pdo, array $config, int $adminId): array
{
    $token = randomToken();
    $csrf = randomToken();
    $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+12 hours');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipBinary = @inet_pton(clientIp()) ?: null;
    $statement = $pdo->prepare(
        'INSERT INTO admin_sessions
            (admin_id, token_hash, csrf_token_hash, user_agent_hash, ip_address, expires_at, last_rotated_at, last_used_at)
         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $statement->execute([
        $adminId,
        tokenHash($token),
        tokenHash($csrf),
        $userAgent === '' ? null : hash('sha256', $userAgent, true),
        $ipBinary,
        $expires->format('Y-m-d H:i:s'),
    ]);
    issueAdminCookie($config, $token, $expires);
    return ['csrfToken' => $csrf, 'expiresAt' => $expires->format(DateTimeInterface::ATOM)];
}

function currentAdminSession(PDO $pdo, array $config, bool $required = true): ?array
{
    $token = $_COOKIE[$config['app']['admin_cookie_name']] ?? '';
    if (!is_string($token) || strlen($token) < 40) {
        if ($required) {
            throw new ApiException('admin_authentication_required', 'Administrator sign-in is required.', 401);
        }
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT s.*, a.username, a.display_name, a.is_active
         FROM admin_sessions s
         INNER JOIN admins a ON a.id = s.admin_id
         WHERE s.token_hash = ? AND s.expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    $statement->execute([tokenHash($token)]);
    $session = $statement->fetch();
    if (!$session || !(bool) $session['is_active']) {
        clearAdminCookie($config);
        if ($required) {
            throw new ApiException('admin_authentication_required', 'The administrator session has expired.', 401);
        }
        return null;
    }

    $rotate = strtotime((string) $session['last_rotated_at']) < time() - 3600;
    $newToken = $rotate ? randomToken() : $token;
    $update = $pdo->prepare(
        'UPDATE admin_sessions
         SET token_hash = ?, last_rotated_at = IF(?, UTC_TIMESTAMP(), last_rotated_at), last_used_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $update->execute([tokenHash($newToken), $rotate ? 1 : 0, $session['id']]);
    if ($rotate) {
        $expires = new DateTimeImmutable((string) $session['expires_at'], new DateTimeZone('UTC'));
        issueAdminCookie($config, $newToken, $expires);
    }
    return $session;
}

function refreshAdminCsrf(PDO $pdo, int|string $sessionId): string
{
    $csrf = randomToken();
    $statement = $pdo->prepare('UPDATE admin_sessions SET csrf_token_hash = ? WHERE id = ?');
    $statement->execute([tokenHash($csrf), $sessionId]);
    return $csrf;
}

function assertAdminCsrf(array $session): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || $provided === '' || !hash_equals($session['csrf_token_hash'], tokenHash($provided))) {
        throw new ApiException('csrf_failed', 'The secure administrator session changed. Refresh and try again.', 403);
    }
}
