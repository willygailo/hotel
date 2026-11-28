<?php
// ============================================================
// MARVELOUS HOTEL — Canonical CLI Migration Runner
// database/migrate.php
// Gamit:
//   php database/migrate.php            -> ilapat ang mga bagong migration
//   php database/migrate.php --status   -> i-list ang applied/pending
//   (Pwede ring gamitin ang root wrapper: php migrate.php)
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only: php database/migrate.php' . "\n");
}

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1'));
}

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/db.php';

dbExec("CREATE TABLE IF NOT EXISTS schema_migrations (
    migration  VARCHAR(191) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$applied = array_column(dbRows("SELECT migration FROM schema_migrations"), 'migration');
$files = glob(__DIR__ . '/migrations/*.sql') ?: [];

// Also pick up any legacy root migrations/ if present
$legacyFiles = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
if (!empty($legacyFiles)) {
    $seen = array_flip(array_map('basename', $files));
    foreach ($legacyFiles as $lf) {
        if (!isset($seen[basename($lf)])) {
            $files[] = $lf;
        }
    }
}
usort($files, fn($a, $b) => strcmp(basename($a), basename($b)));

$onlyStatus = in_array('--status', $argv ?? [], true);
$pending = 0;

foreach ($files as $file) {
    $name = basename($file);
    $done = in_array($name, $applied, true);

    if ($onlyStatus) {
        echo ($done ? '[applied]  ' : '[pending]  ') . $name . "\n";
        if (!$done) $pending++;
        continue;
    }

    if ($done) {
        echo "[skip]     $name\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "[error]    Cannot read: $name\n");
        exit(1);
    }

    $lines = array_filter(explode("\n", $sql), function ($line) {
        return !preg_match('/^\s*--/', $line);
    });
    $statements = array_filter(array_map('trim', explode(';', implode("\n", $lines))), function ($s) {
        return $s !== '';
    });

    foreach ($statements as $statement) {
        try {
            dbExec($statement);
        } catch (Throwable $e) {
            $mysqlCode = ($e instanceof PDOException && isset($e->errorInfo[1])) ? (int)$e->errorInfo[1] : 0;
            if (in_array($mysqlCode, [1050, 1060, 1061, 1091], true)) {
                echo "[sync]     $name (already up to date: code $mysqlCode)\n";
                continue;
            }
            fwrite(STDERR, "[error]    $name: " . $e->getMessage() . "\n");
            exit(1);
        }
    }

    dbExec("INSERT INTO schema_migrations (migration) VALUES (?)", [$name]);
    echo "[applied]  $name\n";
}

if ($onlyStatus) {
    echo $pending === 0 ? "Lahat ay applied na.\n" : "$pending pending migration(s).\n";
} else {
    echo "Migration run tapos na.\n";
}
