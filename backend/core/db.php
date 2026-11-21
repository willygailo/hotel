<?php
// ============================================================
// MARVELOUS HOTEL — Database Connection (PDO)
// includes/db.php
// ============================================================

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::ATTR_TIMEOUT            => 3,
            ]);
        } catch (PDOException $e) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
                throw $e;
            } elseif (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                die('<div style="font-family:monospace;background:#1a1a2e;color:#e94560;padding:20px;border-radius:8px;">
                    <h3>⚠️ Database Connection Failed</h3>
                    <p>' . htmlspecialchars($e->getMessage()) . '</p>
                    <small>Make sure XAMPP MySQL is running and database "marvelous_hotel" exists.</small>
                </div>');
            } else {
                die('Service temporarily unavailable. Please try again later.');
            }
        }
    }
    return $pdo;
}

// ---- Helper: fetch single row ----
function dbRow(string $sql, array $params = []): ?array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ---- Helper: fetch multiple rows ----
function dbRows(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ---- Helper: execute INSERT/UPDATE/DELETE ----
function dbExec(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->rowCount();
}

// ---- Helper: insert and get last ID ----
function dbInsert(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return (int) getDB()->lastInsertId();
}

// ---- Helper: check if database connection can be established ----
function isDbAvailable(): bool {
    static $available = null;
    if ($available !== null) return $available;
    try {
        getDB();
        $available = true;
    } catch (\Throwable $e) {
        $available = false;
    }
    return $available;
}

// ---- Helper: clear per-request settings cache ----
function clearSettingsCache(): void {
    $GLOBALS['__mh_settings_cache'] = null;
}

// ---- Helper: get setting value (per-request cached) ----
function getSetting(string $key, string $default = ''): string {
    if (!isDbAvailable()) {
        return $default;
    }
    try {
        if (!isset($GLOBALS['__mh_settings_cache']) || !is_array($GLOBALS['__mh_settings_cache'])) {
            $rows = dbRows("SELECT `key`, `value` FROM settings");
            $map = [];
            foreach ($rows as $r) {
                $map[(string)$r['key']] = (string)$r['value'];
            }
            $GLOBALS['__mh_settings_cache'] = $map;
        }
        return $GLOBALS['__mh_settings_cache'][$key] ?? $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

