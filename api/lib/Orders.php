<?php
declare(strict_types=1);

function orderNumber(PDO $pdo): string
{
    do {
        $number = 'EMC-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $statement = $pdo->prepare('SELECT id FROM orders WHERE order_number = ? LIMIT 1');
        $statement->execute([$number]);
    } while ($statement->fetch());
    return $number;
}

function normalizeUploadList(string $field): array
{
    if (!isset($_FILES[$field])) {
        return [];
    }
    $source = $_FILES[$field];
    if (!is_array($source['name'])) {
        return [$source];
    }
    $files = [];
    foreach ($source['name'] as $index => $name) {
        $files[] = [
            'name' => $name,
            'type' => $source['type'][$index] ?? '',
            'tmp_name' => $source['tmp_name'][$index] ?? '',
            'error' => $source['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $source['size'][$index] ?? 0,
        ];
    }
    return $files;
}

function validateOrderInput(PDO $pdo, array $config): array
{
    $name = trim((string) ($_POST['fullName'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $fulfillment = (string) ($_POST['handover'] ?? 'dropoff');
    $packageId = filter_var($_POST['packageId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $fields = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $fields['fullName'] = 'Enter a valid name.';
    if (mb_strlen($address) < 3 || mb_strlen($address) > 500) $fields['address'] = 'Enter a valid address.';
    if (mb_strlen($notes) > 2000) $fields['notes'] = 'Notes cannot exceed 2,000 characters.';
    if (!in_array($fulfillment, ['dropoff', 'pickup'], true)) $fields['handover'] = 'Choose a valid handover method.';
    if ($packageId === false) $fields['packageId'] = 'Choose a valid package.';
    if ($fields !== []) throw new ApiException('validation_failed', 'Please check the order details.', 422, $fields);

    $packageStatement = $pdo->prepare('SELECT * FROM packages WHERE id = ? AND is_active = 1 LIMIT 1');
    $packageStatement->execute([$packageId]);
    $package = $packageStatement->fetch();
    if (!$package) throw new ApiException('package_unavailable', 'The selected package is no longer available.', 409);

    $photos = normalizeUploadList('photos');
    if (count($photos) < 1 || count($photos) > 10) {
        throw new ApiException('photo_count_invalid', 'Add between 1 and 10 shoe photos.', 422);
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $validatedPhotos = [];
    foreach ($photos as $index => $photo) {
        if ((int) $photo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($photo['tmp_name'])) {
            throw new ApiException('photo_upload_failed', 'One of the photos could not be uploaded.', 422);
        }
        if ((int) $photo['size'] < 1 || (int) $photo['size'] > $config['app']['upload_max_bytes']) {
            throw new ApiException('photo_size_invalid', 'Each photo must be 5 MB or smaller.', 422);
        }
        $mime = $finfo->file($photo['tmp_name']) ?: '';
        if (!isset($allowed[$mime])) {
            throw new ApiException('photo_type_invalid', 'Only JPG, PNG, and WebP photos are accepted.', 422);
        }
        $dimensions = @getimagesize($photo['tmp_name']);
        if (!$dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || ($dimensions[0] * $dimensions[1]) > 30000000) {
            throw new ApiException('photo_invalid', 'One of the image files is invalid or too large.', 422);
        }
        $validatedPhotos[] = [
            ...$photo,
            'mime' => $mime,
            'extension' => $allowed[$mime],
            'width' => (int) $dimensions[0],
            'height' => (int) $dimensions[1],
            'sortOrder' => $index,
        ];
    }

    $pickupFee = 0;
    if ($fulfillment === 'pickup') {
        $setting = $pdo->prepare("SELECT setting_value FROM shop_settings WHERE setting_key = 'pickup_fee_ks'");
        $setting->execute();
        $pickupFee = max(0, (int) ($setting->fetchColumn() ?: 0));
    }
    return compact('name', 'address', 'notes', 'fulfillment', 'package', 'validatedPhotos', 'pickupFee');
}

function orderPayload(array $order, array $photos = []): array
{
    return [
        'id' => (int) $order['id'],
        'orderNumber' => $order['order_number'],
        'package' => [
            'id' => (int) $order['package_id'],
            'nameEn' => $order['package_name_en'],
            'nameMm' => $order['package_name_mm'],
            'priceKs' => (int) $order['package_price_ks'],
        ],
        'pickupFeeKs' => (int) $order['pickup_fee_ks'],
        'totalPriceKs' => (int) $order['total_price_ks'],
        'handover' => $order['fulfillment_method'],
        'customer' => [
            'name' => $order['customer_name'],
            'phone' => $order['customer_phone'],
            'address' => $order['customer_address'],
        ],
        'notes' => $order['customer_notes'],
        'status' => $order['status'],
        'photoCount' => isset($order['photo_count']) ? (int) $order['photo_count'] : count($photos),
        'photos' => array_map(static fn(array $photo): array => [
            'id' => (int) $photo['id'],
            'url' => '/orders/' . (int) $order['id'] . '/photos/' . (int) $photo['id'],
            'width' => (int) $photo['width_px'],
            'height' => (int) $photo['height_px'],
        ], $photos),
        'createdAt' => (new DateTimeImmutable($order['created_at'], new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
        'updatedAt' => (new DateTimeImmutable($order['updated_at'], new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
    ];
}

function fetchOrder(PDO $pdo, int $orderId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $statement->execute([$orderId]);
    return $statement->fetch() ?: null;
}

function fetchOrderPhotos(PDO $pdo, int $orderId): array
{
    $statement = $pdo->prepare('SELECT * FROM order_photos WHERE order_id = ? ORDER BY sort_order, id');
    $statement->execute([$orderId]);
    return $statement->fetchAll();
}

function sendOrderPhoto(PDO $pdo, array $config, int $orderId, int $photoId): never
{
    $order = fetchOrder($pdo, $orderId);
    if (!$order) throw new ApiException('order_not_found', 'Order not found.', 404);

    $customerSession = currentSession($pdo, $config, false);
    $authorized = $customerSession && (int) $customerSession['customer_id'] === (int) $order['customer_id'];
    if (!$authorized) {
        $authorized = currentAdminSession($pdo, $config, false) !== null;
    }
    if (!$authorized) throw new ApiException('order_not_found', 'Order not found.', 404);

    $statement = $pdo->prepare('SELECT * FROM order_photos WHERE id = ? AND order_id = ? LIMIT 1');
    $statement->execute([$photoId, $orderId]);
    $photo = $statement->fetch();
    if (!$photo || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $photo['storage_name'])) {
        throw new ApiException('photo_not_found', 'Photo not found.', 404);
    }
    $base = realpath($config['app']['order_photo_path']);
    $path = realpath($config['app']['order_photo_path'] . '/' . $order['storage_key'] . '/' . $photo['storage_name']);
    if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
        throw new ApiException('photo_not_found', 'Photo not found.', 404);
    }
    header('Content-Type: ' . $photo['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="emc-order-photo.' . pathinfo($photo['storage_name'], PATHINFO_EXTENSION) . '"');
    header('Cache-Control: private, max-age=300');
    readfile($path);
    exit;
}
