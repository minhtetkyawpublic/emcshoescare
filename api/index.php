<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = requestPath();
$pdo = database($config);

if ($method === 'GET' && $path === '/health') {
    $pdo->query('SELECT 1');
    jsonSuccess(['service' => 'EMC API', 'version' => EMC_API_VERSION]);
}

if ($method === 'POST' && $path === '/auth/register') {
    assertTrustedBrowserRequest($config);
    $body = jsonBody();
    $input = validateAccountInput($body, true);
    consumeRateLimit($pdo, $config, 'register', $input['phone'], 5);

    $existing = $pdo->prepare('SELECT id FROM customers WHERE phone = ? LIMIT 1');
    $existing->execute([$input['phone']]);
    if ($existing->fetch()) {
        throw new ApiException('phone_in_use', 'An account already exists for this phone number.', 409, ['phone' => 'Phone number already registered.']);
    }

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT INTO customers (phone, password_hash, full_name, address, last_login_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $insert->execute([
            $input['phone'],
            password_hash($input['password'], PASSWORD_DEFAULT),
            $input['name'],
            $input['address'],
        ]);
        $customerId = (int) $pdo->lastInsertId();
        $session = createSession($pdo, $config, $customerId, (bool) ($body['remember'] ?? true));
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    jsonSuccess([
        'customer' => ['id' => $customerId, 'phone' => $input['phone'], 'fullName' => $input['name'], 'address' => $input['address']],
        ...$session,
    ], 201);
}

if ($method === 'POST' && $path === '/auth/login') {
    assertTrustedBrowserRequest($config);
    $body = jsonBody();
    $input = validateAccountInput($body, false);
    consumeRateLimit($pdo, $config, 'login', $input['phone']);

    $statement = $pdo->prepare('SELECT * FROM customers WHERE phone = ? LIMIT 1');
    $statement->execute([$input['phone']]);
    $customer = $statement->fetch();
    if (!$customer || !(bool) $customer['is_active'] || !password_verify($input['password'], $customer['password_hash'])) {
        throw new ApiException('invalid_credentials', 'Phone number or password is incorrect.', 401);
    }

    if (password_needs_rehash($customer['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = $pdo->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
        $rehash->execute([password_hash($input['password'], PASSWORD_DEFAULT), $customer['id']]);
    }
    $pdo->prepare('UPDATE customers SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$customer['id']]);
    $session = createSession($pdo, $config, (int) $customer['id'], (bool) ($body['remember'] ?? true));
    jsonSuccess(['customer' => publicCustomer($customer), ...$session]);
}

if ($method === 'GET' && $path === '/auth/session') {
    $session = currentSession($pdo, $config, false);
    if ($session === null) {
        jsonSuccess(['authenticated' => false]);
    }
    jsonSuccess([
        'authenticated' => true,
        'customer' => publicCustomer($session),
        'csrfToken' => refreshCsrf($pdo, $session['id']),
        'expiresAt' => (new DateTimeImmutable((string) $session['expires_at'], new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
    ]);
}

if ($method === 'POST' && $path === '/auth/logout') {
    assertTrustedBrowserRequest($config);
    $session = currentSession($pdo, $config);
    assertCsrf($session);
    $pdo->prepare('DELETE FROM auth_sessions WHERE id = ?')->execute([$session['id']]);
    clearSessionCookie($config);
    jsonSuccess(['loggedOut' => true]);
}

if ($method === 'GET' && $path === '/profile') {
    $session = currentSession($pdo, $config);
    jsonSuccess(['customer' => publicCustomer($session), 'csrfToken' => refreshCsrf($pdo, $session['id'])]);
}

if ($method === 'PUT' && $path === '/profile') {
    assertTrustedBrowserRequest($config);
    $session = currentSession($pdo, $config);
    assertCsrf($session);
    $input = validateProfileInput(jsonBody());
    $statement = $pdo->prepare('UPDATE customers SET full_name = ?, address = ? WHERE id = ?');
    $statement->execute([$input['name'], $input['address'], $session['customer_id']]);
    jsonSuccess([
        'customer' => [
            'id' => (int) $session['customer_id'],
            'phone' => $session['phone'],
            'fullName' => $input['name'],
            'address' => $input['address'],
        ],
        'csrfToken' => refreshCsrf($pdo, $session['id']),
    ]);
}

throw new ApiException('not_found', 'API route not found.', 404);
