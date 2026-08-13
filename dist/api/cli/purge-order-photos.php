<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command can only run from the command line.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/Database.php';

$options = getopt('', ['days::', 'execute', 'help']);
if (isset($options['help'])) {
    echo "Usage: php api/cli/purge-order-photos.php [--days=180] [--execute]\n";
    echo "Without --execute this command only reports what would be removed.\n";
    exit(0);
}

$days = isset($options['days'])
    ? filter_var($options['days'], FILTER_VALIDATE_INT)
    : (int) ($config['app']['order_photo_retention_days'] ?? 0);
if ($days === false || $days < 30) {
    fwrite(STDERR, "Choose an approved retention period of at least 30 days with --days or EMC_ORDER_PHOTO_RETENTION_DAYS.\n");
    exit(2);
}

$execute = isset($options['execute']);
$root = realpath((string) $config['app']['order_photo_path']);
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "The private order-photo directory is unavailable.\n");
    exit(3);
}
$rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$pdo = database($config);
$statement = $pdo->prepare(
    "SELECT p.id, p.size_bytes, p.storage_name, o.id AS order_id, o.order_number, o.storage_key
     FROM order_photos p
     INNER JOIN orders o ON o.id = p.order_id
     WHERE o.status IN ('done', 'cancelled')
       AND o.updated_at < (UTC_TIMESTAMP() - INTERVAL ? DAY)
     ORDER BY o.id, p.id"
);
$statement->execute([$days]);
$photos = $statement->fetchAll();
$summary = [
    'mode' => $execute ? 'execute' : 'dry-run',
    'retentionDays' => $days,
    'orders' => count(array_unique(array_column($photos, 'order_id'))),
    'photos' => count($photos),
    'bytes' => array_sum(array_map(static fn(array $photo): int => (int) $photo['size_bytes'], $photos)),
    'deleted' => 0,
    'missingFiles' => 0,
    'failures' => 0,
];

if (!$execute || $photos === []) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$deleteMetadata = $pdo->prepare('DELETE FROM order_photos WHERE id = ?');
$touchedDirectories = [];
foreach ($photos as $photo) {
    if (!preg_match('/^[a-f0-9]{32}$/', (string) $photo['storage_key'])
        || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', (string) $photo['storage_name'])) {
        $summary['failures']++;
        continue;
    }
    $directory = $rootPrefix . $photo['storage_key'];
    $path = $directory . DIRECTORY_SEPARATOR . $photo['storage_name'];
    $resolvedDirectory = realpath($directory);
    if ($resolvedDirectory !== false
        && !str_starts_with(rtrim($resolvedDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $rootPrefix)) {
        $summary['failures']++;
        continue;
    }
    if (is_file($path)) {
        if (!unlink($path)) {
            $summary['failures']++;
            continue;
        }
    } else {
        $summary['missingFiles']++;
    }
    $deleteMetadata->execute([(int) $photo['id']]);
    $summary['deleted']++;
    $touchedDirectories[$directory] = true;
}

foreach (array_keys($touchedDirectories) as $directory) {
    if (is_dir($directory) && count(scandir($directory) ?: []) === 2) {
        rmdir($directory);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($summary['failures'] > 0 ? 4 : 0);
