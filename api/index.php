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

if ($method === 'GET' && $path === '/packages') {
    $statement = $pdo->query(
        'SELECT id, slug, name_en, name_mm, description_en, description_mm, price_ks, sort_order
         FROM packages WHERE is_active = 1 ORDER BY sort_order, id'
    );
    $packages = array_map(static fn(array $package): array => [
        'id' => (int) $package['id'],
        'slug' => $package['slug'],
        'nameEn' => $package['name_en'],
        'nameMm' => $package['name_mm'],
        'descriptionEn' => $package['description_en'],
        'descriptionMm' => $package['description_mm'],
        'priceKs' => (int) $package['price_ks'],
        'sortOrder' => (int) $package['sort_order'],
    ], $statement->fetchAll());
    jsonSuccess(['packages' => $packages]);
}

if ($method === 'GET' && $path === '/settings') {
    $statement = $pdo->query("SELECT setting_value FROM shop_settings WHERE setting_key = 'pickup_fee_ks'");
    jsonSuccess(['pickupFeeKs' => max(0, (int) ($statement->fetchColumn() ?: 0))]);
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
    jsonSuccess(['customer' => publicCustomer($session)]);
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

if ($method === 'POST' && $path === '/orders') {
    assertTrustedBrowserRequest($config);
    $session = currentSession($pdo, $config);
    assertCsrf($session);
    $input = validateOrderInput($pdo, $config);
    $orderNumber = orderNumber($pdo);
    $storageKey = bin2hex(random_bytes(16));
    $storageDirectory = $config['app']['order_photo_path'] . '/' . $storageKey;
    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException('Could not create the private order photo directory.');
    }
    $createdFiles = [];
    $pdo->beginTransaction();
    try {
        $package = $input['package'];
        $total = (int) $package['price_ks'] + $input['pickupFee'];
        $insert = $pdo->prepare(
            'INSERT INTO orders
              (order_number, storage_key, customer_id, package_id, package_name_en, package_name_mm,
               package_price_ks, pickup_fee_ks, total_price_ks, fulfillment_method, customer_name,
               customer_phone, customer_address, customer_notes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $orderNumber,
            $storageKey,
            $session['customer_id'],
            $package['id'],
            $package['name_en'],
            $package['name_mm'],
            $package['price_ks'],
            $input['pickupFee'],
            $total,
            $input['fulfillment'],
            $input['name'],
            $session['phone'],
            $input['address'],
            $input['notes'],
            'submitted',
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $photoInsert = $pdo->prepare(
            'INSERT INTO order_photos
              (order_id, storage_name, original_name, mime_type, size_bytes, width_px, height_px, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($input['validatedPhotos'] as $photo) {
            $storageName = bin2hex(random_bytes(16)) . '.' . $photo['extension'];
            $destination = $storageDirectory . '/' . $storageName;
            if (!move_uploaded_file($photo['tmp_name'], $destination)) {
                throw new RuntimeException('A validated order photo could not be stored.');
            }
            $createdFiles[] = $destination;
            $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', basename((string) $photo['name'])) ?: 'shoe-photo';
            $photoInsert->execute([
                $orderId,
                $storageName,
                mb_substr($originalName, 0, 255),
                $photo['mime'],
                $photo['size'],
                $photo['width'],
                $photo['height'],
                $photo['sortOrder'],
            ]);
        }
        $pdo->prepare('UPDATE customers SET full_name = ?, address = ? WHERE id = ?')
            ->execute([$input['name'], $input['address'], $session['customer_id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($createdFiles as $createdFile) {
            if (is_file($createdFile)) unlink($createdFile);
        }
        if (is_dir($storageDirectory)) rmdir($storageDirectory);
        throw $exception;
    }
    $order = fetchOrder($pdo, $orderId);
    jsonSuccess([
        'order' => orderPayload($order, fetchOrderPhotos($pdo, $orderId)),
        'customer' => [
            'id' => (int) $session['customer_id'],
            'phone' => $session['phone'],
            'fullName' => $input['name'],
            'address' => $input['address'],
        ],
        'csrfToken' => refreshCsrf($pdo, $session['id']),
    ], 201);
}

if ($method === 'GET' && $path === '/orders') {
    $session = currentSession($pdo, $config);
    $statement = $pdo->prepare(
        'SELECT o.*, COUNT(p.id) AS photo_count
         FROM orders o LEFT JOIN order_photos p ON p.order_id = o.id
         WHERE o.customer_id = ? GROUP BY o.id ORDER BY o.created_at DESC, o.id DESC'
    );
    $statement->execute([$session['customer_id']]);
    jsonSuccess([
        'orders' => array_map(static fn(array $order): array => orderPayload($order), $statement->fetchAll()),
    ]);
}

if ($method === 'GET' && preg_match('#^/orders/(\d+)$#', $path, $matches)) {
    $session = currentSession($pdo, $config);
    $order = fetchOrder($pdo, (int) $matches[1]);
    if (!$order || (int) $order['customer_id'] !== (int) $session['customer_id']) {
        throw new ApiException('order_not_found', 'Order not found.', 404);
    }
    jsonSuccess([
        'order' => orderPayload($order, fetchOrderPhotos($pdo, (int) $order['id'])),
    ]);
}

if ($method === 'GET' && preg_match('#^/orders/(\d+)/photos/(\d+)$#', $path, $matches)) {
    sendOrderPhoto($pdo, $config, (int) $matches[1], (int) $matches[2]);
}

if ($method === 'POST' && $path === '/admin/auth/login') {
    assertTrustedBrowserRequest($config);
    $body = jsonBody();
    $username = strtolower(trim((string) ($body['username'] ?? '')));
    $password = (string) ($body['password'] ?? '');
    if (!preg_match('/^[a-z0-9_.-]{3,50}$/', $username) || strlen($password) < 10 || strlen($password) > 72) {
        throw new ApiException('invalid_admin_credentials', 'Username or password is incorrect.', 401);
    }
    consumeRateLimit($pdo, $config, 'admin_login', $username, 6);
    $statement = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $statement->execute([$username]);
    $admin = $statement->fetch();
    if (!$admin || !(bool) $admin['is_active'] || !password_verify($password, $admin['password_hash'])) {
        throw new ApiException('invalid_admin_credentials', 'Username or password is incorrect.', 401);
    }
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
    }
    $pdo->prepare('UPDATE admins SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$admin['id']]);
    $adminSession = createAdminSession($pdo, $config, (int) $admin['id']);
    jsonSuccess(['admin' => publicAdmin($admin), ...$adminSession]);
}

if ($method === 'GET' && $path === '/admin/auth/session') {
    $session = currentAdminSession($pdo, $config, false);
    if ($session === null) jsonSuccess(['authenticated' => false]);
    jsonSuccess([
        'authenticated' => true,
        'admin' => publicAdmin($session),
        'csrfToken' => refreshAdminCsrf($pdo, $session['id']),
    ]);
}

if ($method === 'POST' && $path === '/admin/auth/logout') {
    assertTrustedBrowserRequest($config);
    $session = currentAdminSession($pdo, $config);
    assertAdminCsrf($session);
    $pdo->prepare('DELETE FROM admin_sessions WHERE id = ?')->execute([$session['id']]);
    clearAdminCookie($config);
    jsonSuccess(['loggedOut' => true]);
}

if ($method === 'GET' && $path === '/admin/packages') {
    $session = currentAdminSession($pdo, $config);
    $statement = $pdo->query('SELECT * FROM packages ORDER BY sort_order, id');
    $packages = array_map(static fn(array $package): array => [
        'id' => (int) $package['id'], 'slug' => $package['slug'],
        'nameEn' => $package['name_en'], 'nameMm' => $package['name_mm'],
        'descriptionEn' => $package['description_en'], 'descriptionMm' => $package['description_mm'],
        'priceKs' => (int) $package['price_ks'], 'active' => (bool) $package['is_active'],
        'sortOrder' => (int) $package['sort_order'],
    ], $statement->fetchAll());
    jsonSuccess(['packages' => $packages]);
}

if ($method === 'POST' && $path === '/admin/packages') {
    assertTrustedBrowserRequest($config);
    $session = currentAdminSession($pdo, $config);
    assertAdminCsrf($session);
    $body = jsonBody();
    $nameEn = trim((string) ($body['nameEn'] ?? ''));
    $nameMm = trim((string) ($body['nameMm'] ?? ''));
    $descriptionEn = trim((string) ($body['descriptionEn'] ?? ''));
    $descriptionMm = trim((string) ($body['descriptionMm'] ?? ''));
    $price = filter_var($body['priceKs'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000000]]);
    $sortOrder = max(0, min(10000, (int) ($body['sortOrder'] ?? 0)));
    if (mb_strlen($nameEn) < 2 || mb_strlen($nameEn) > 120 || mb_strlen($nameMm) < 2 || mb_strlen($nameMm) > 180 || mb_strlen($descriptionEn) > 500 || mb_strlen($descriptionMm) > 800 || $price === false) {
        throw new ApiException('validation_failed', 'Check the package names, descriptions, and price.', 422);
    }
    $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $nameEn) ?? '', '-')) ?: 'package';
    $slug = substr($baseSlug, 0, 64) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    $statement = $pdo->prepare(
        'INSERT INTO packages (slug, name_en, name_mm, description_en, description_mm, price_ks, is_active, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([$slug, $nameEn, $nameMm, $descriptionEn, $descriptionMm, $price, !empty($body['active']) ? 1 : 0, $sortOrder]);
    jsonSuccess(['id' => (int) $pdo->lastInsertId(), 'csrfToken' => refreshAdminCsrf($pdo, $session['id'])], 201);
}

if ($method === 'PUT' && preg_match('#^/admin/packages/(\d+)$#', $path, $matches)) {
    assertTrustedBrowserRequest($config);
    $session = currentAdminSession($pdo, $config);
    assertAdminCsrf($session);
    $body = jsonBody();
    $nameEn = trim((string) ($body['nameEn'] ?? ''));
    $nameMm = trim((string) ($body['nameMm'] ?? ''));
    $descriptionEn = trim((string) ($body['descriptionEn'] ?? ''));
    $descriptionMm = trim((string) ($body['descriptionMm'] ?? ''));
    $price = filter_var($body['priceKs'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000000]]);
    $sortOrder = max(0, min(10000, (int) ($body['sortOrder'] ?? 0)));
    if (mb_strlen($nameEn) < 2 || mb_strlen($nameEn) > 120 || mb_strlen($nameMm) < 2 || mb_strlen($nameMm) > 180 || mb_strlen($descriptionEn) > 500 || mb_strlen($descriptionMm) > 800 || $price === false) {
        throw new ApiException('validation_failed', 'Check the package names, descriptions, and price.', 422);
    }
    $statement = $pdo->prepare(
        'UPDATE packages SET name_en = ?, name_mm = ?, description_en = ?, description_mm = ?, price_ks = ?, is_active = ?, sort_order = ? WHERE id = ?'
    );
    $statement->execute([$nameEn, $nameMm, $descriptionEn, $descriptionMm, $price, !empty($body['active']) ? 1 : 0, $sortOrder, (int) $matches[1]]);
    if ($statement->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM packages WHERE id = ?');
        $check->execute([(int) $matches[1]]);
        if (!$check->fetch()) throw new ApiException('package_not_found', 'Package not found.', 404);
    }
    jsonSuccess(['updated' => true, 'csrfToken' => refreshAdminCsrf($pdo, $session['id'])]);
}

if ($method === 'DELETE' && preg_match('#^/admin/packages/(\d+)$#', $path, $matches)) {
    assertTrustedBrowserRequest($config);
    $session = currentAdminSession($pdo, $config);
    assertAdminCsrf($session);
    $statement = $pdo->prepare('UPDATE packages SET is_active = 0 WHERE id = ?');
    $statement->execute([(int) $matches[1]]);
    jsonSuccess(['archived' => true, 'csrfToken' => refreshAdminCsrf($pdo, $session['id'])]);
}

if ($method === 'GET' && $path === '/admin/settings') {
    $session = currentAdminSession($pdo, $config);
    $statement = $pdo->query("SELECT setting_value FROM shop_settings WHERE setting_key = 'pickup_fee_ks'");
    jsonSuccess(['pickupFeeKs' => max(0, (int) ($statement->fetchColumn() ?: 0))]);
}

if ($method === 'PUT' && $path === '/admin/settings') {
    assertTrustedBrowserRequest($config);
    $session = currentAdminSession($pdo, $config);
    assertAdminCsrf($session);
    $body = jsonBody();
    $fee = filter_var($body['pickupFeeKs'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000000]]);
    if ($fee === false) throw new ApiException('validation_failed', 'Enter a valid pickup fee.', 422);
    $statement = $pdo->prepare(
        "INSERT INTO shop_settings (setting_key, setting_value) VALUES ('pickup_fee_ks', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $statement->execute([(string) $fee]);
    jsonSuccess(['pickupFeeKs' => $fee, 'csrfToken' => refreshAdminCsrf($pdo, $session['id'])]);
}

if ($method === 'GET' && $path === '/admin/orders') {
    $session = currentAdminSession($pdo, $config);
    $statement = $pdo->query(
        'SELECT o.*, COUNT(p.id) AS photo_count
         FROM orders o LEFT JOIN order_photos p ON p.order_id = o.id
         GROUP BY o.id ORDER BY o.created_at DESC, o.id DESC LIMIT 250'
    );
    jsonSuccess([
        'orders' => array_map(static fn(array $order): array => orderPayload($order), $statement->fetchAll()),
    ]);
}

if ($method === 'GET' && preg_match('#^/admin/orders/(\d+)$#', $path, $matches)) {
    $session = currentAdminSession($pdo, $config);
    $order = fetchOrder($pdo, (int) $matches[1]);
    if (!$order) throw new ApiException('order_not_found', 'Order not found.', 404);
    jsonSuccess([
        'order' => orderPayload($order, fetchOrderPhotos($pdo, (int) $order['id'])),
    ]);
}

throw new ApiException('not_found', 'API route not found.', 404);
