<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$source = realpath(dirname(__DIR__) . '/dist');
$targetInput = trim((string) ($argv[1] ?? ''));
if (!$source || !is_file($source . '/.release.json') || $targetInput === '') {
    fwrite(STDERR, "Usage: php scripts/deploy-release.php /absolute/path/to/public_html/optional-folder\n");
    exit(1);
}

if (!is_dir($targetInput) && !mkdir($targetInput, 0755, true) && !is_dir($targetInput)) {
    fwrite(STDERR, "Could not create deployment target: {$targetInput}\n");
    exit(1);
}
$target = realpath($targetInput);
$filesystemRoot = realpath(DIRECTORY_SEPARATOR);
if (!$target || $target === $filesystemRoot || $target === $source) {
    fwrite(STDERR, "Refusing unsafe deployment target.\n");
    exit(1);
}

function deployDirectory(string $source, string $target, int &$files): void
{
    $iterator = new DirectoryIterator($source);
    foreach ($iterator as $item) {
        if ($item->isDot()) continue;
        $sourcePath = $item->getPathname();
        $targetPath = $target . DIRECTORY_SEPARATOR . $item->getFilename();
        if ($item->isDir()) {
            if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
                throw new RuntimeException("Could not create directory: {$targetPath}");
            }
            deployDirectory($sourcePath, $targetPath, $files);
            continue;
        }
        $temporary = $targetPath . '.emc-new-' . bin2hex(random_bytes(4));
        if (!copy($sourcePath, $temporary) || !rename($temporary, $targetPath)) {
            @unlink($temporary);
            throw new RuntimeException("Could not deploy file: {$targetPath}");
        }
        @chmod($targetPath, 0644);
        $files++;
    }
}

try {
    $files = 0;
    deployDirectory($source, $target, $files);
    $photoDirectory = $target . '/storage/order-photos';
    if (!is_dir($photoDirectory) && !mkdir($photoDirectory, 0750, true) && !is_dir($photoDirectory)) {
        throw new RuntimeException('Could not create the private photo directory.');
    }
    fwrite(STDOUT, "Deployed {$files} release files to {$target}\n");
    fwrite(STDOUT, "Preserved any existing api/config.local.php and storage/order-photos files.\n");
    fwrite(STDOUT, "Next: php {$target}/api/cli/migrate.php --status\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Deployment failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
