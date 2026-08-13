<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/Database.php';

$mode = $argv[1] ?? 'migrate';
if (!in_array($mode, ['migrate', '--status', '--dry-run'], true)) {
    fwrite(STDERR, "Usage: php migrate.php [--status|--dry-run]\n");
    exit(1);
}

function migrationStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);

    for ($index = 0; $index < $length; $index++) {
        $character = $sql[$index];
        $next = $index + 1 < $length ? $sql[$index + 1] : '';

        if ($quote !== null) {
            $buffer .= $character;
            if ($character === '\\' && $next !== '') {
                $buffer .= $next;
                $index++;
            } elseif ($character === $quote) {
                if ($next === $quote) {
                    $buffer .= $next;
                    $index++;
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if (in_array($character, ["'", '"', '`'], true)) {
            $quote = $character;
            $buffer .= $character;
            continue;
        }
        if ($character === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
            while ($index < $length && $sql[$index] !== "\n") $index++;
            $buffer .= "\n";
            continue;
        }
        if ($character === '#') {
            while ($index < $length && $sql[$index] !== "\n") $index++;
            $buffer .= "\n";
            continue;
        }
        if ($character === '/' && $next === '*') {
            $index += 2;
            while ($index + 1 < $length && !($sql[$index] === '*' && $sql[$index + 1] === '/')) $index++;
            $index++;
            $buffer .= ' ';
            continue;
        }
        if ($character === ';') {
            $statement = trim($buffer);
            if ($statement !== '') $statements[] = $statement;
            $buffer = '';
            continue;
        }
        $buffer .= $character;
    }

    if ($quote !== null) throw new RuntimeException('A migration contains an unterminated quoted value.');
    $statement = trim($buffer);
    if ($statement !== '') $statements[] = $statement;
    return $statements;
}

$migrationDirectory = dirname(__DIR__, 2) . '/database/migrations';
if (!is_dir($migrationDirectory)) {
    $migrationDirectory = __DIR__ . '/migrations';
}
$files = glob($migrationDirectory . '/*.sql') ?: [];
sort($files, SORT_NATURAL);
if ($files === []) {
    fwrite(STDERR, "No migration files were found in {$migrationDirectory}.\n");
    exit(1);
}

try {
    $pdo = database($config);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
          version VARCHAR(50) NOT NULL PRIMARY KEY,
          applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $lockName = 'emc_migrate_' . substr(hash('sha256', (string) $config['database']['name']), 0, 32);
    $lockStatement = $pdo->prepare('SELECT GET_LOCK(?, 30)');
    $lockStatement->execute([$lockName]);
    if ((int) $lockStatement->fetchColumn() !== 1) throw new RuntimeException('Could not acquire the migration lock.');

    try {
        $applied = array_fill_keys($pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN), true);
        $pending = [];
        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            if (!preg_match('/^\d{3}_[a-z0-9_]+$/', $version)) {
                throw new RuntimeException('Invalid migration filename: ' . basename($file));
            }
            if (!isset($applied[$version])) $pending[$version] = $file;
        }

        if ($mode === '--status') {
            foreach ($files as $file) {
                $version = pathinfo($file, PATHINFO_FILENAME);
                fwrite(STDOUT, (isset($applied[$version]) ? '[applied] ' : '[pending] ') . $version . "\n");
            }
            exit(0);
        }
        if ($pending === []) {
            fwrite(STDOUT, "Database is up to date.\n");
            exit(0);
        }
        if ($mode === '--dry-run') {
            foreach (array_keys($pending) as $version) fwrite(STDOUT, "[would apply] {$version}\n");
            exit(0);
        }

        $record = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        foreach ($pending as $version => $file) {
            $sql = file_get_contents($file);
            if ($sql === false) throw new RuntimeException("Could not read migration {$version}.");
            foreach (migrationStatements($sql) as $statement) $pdo->exec($statement);
            $record->execute([$version]);
            fwrite(STDOUT, "[applied] {$version}\n");
        }
        fwrite(STDOUT, "Database migration complete.\n");
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
