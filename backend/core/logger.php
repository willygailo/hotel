<?php
// ============================================================
// MARVELOUS HOTEL — Structured Logger
// includes/logger.php
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Log levels (prefixed to avoid conflict with PHP syslog constants)
if (!defined('MH_LOG_EMERGENCY')) define('MH_LOG_EMERGENCY', 0);
if (!defined('MH_LOG_ALERT'))     define('MH_LOG_ALERT', 1);
if (!defined('MH_LOG_CRITICAL'))  define('MH_LOG_CRITICAL', 2);
if (!defined('MH_LOG_ERROR'))     define('MH_LOG_ERROR', 3);
if (!defined('MH_LOG_WARNING'))   define('MH_LOG_WARNING', 4);
if (!defined('MH_LOG_NOTICE'))    define('MH_LOG_NOTICE', 5);
if (!defined('MH_LOG_INFO'))      define('MH_LOG_INFO', 6);
if (!defined('MH_LOG_DEBUG'))     define('MH_LOG_DEBUG', 7);

$LOG_LEVEL_NAMES = [
    MH_LOG_EMERGENCY => 'EMERGENCY',
    MH_LOG_ALERT     => 'ALERT',
    MH_LOG_CRITICAL  => 'CRITICAL',
    MH_LOG_ERROR     => 'ERROR',
    MH_LOG_WARNING   => 'WARNING',
    MH_LOG_NOTICE    => 'NOTICE',
    MH_LOG_INFO      => 'INFO',
    MH_LOG_DEBUG     => 'DEBUG',
];

// Minimum log level to record (based on environment)
$minLogLevel = ENVIRONMENT === 'production' ? MH_LOG_NOTICE : MH_LOG_DEBUG;

/**
 * Write a structured log entry
 * 
 * @param int $level Log level constant
 * @param string $message Log message
 * @param array $context Additional context data
 * @param string $channel Log channel (module name)
 */
function logEntry(int $level, string $message, array $context = [], string $channel = 'app'): void {
    global $minLogLevel, $LOG_LEVEL_NAMES;

    if ($level > $minLogLevel) return;

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'cli';
    $url = $_SERVER['REQUEST_URI'] ?? 'cli';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

    $levelName = $LOG_LEVEL_NAMES[$level] ?? 'UNKNOWN';

    // Build structured log data
    $logData = [
        'timestamp'  => date('c'), // ISO 8601
        'level'      => $levelName,
        'level_num'  => $level,
        'channel'    => $channel,
        'message'    => $message,
        'context'    => $context,
        'user_id'    => $userId,
        'ip'         => $ip,
        'user_agent' => $ua,
        'url'        => $url,
        'method'     => $method,
        'trace_id'   => $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8)),
    ];

    // Write to database (audit_logs table for important events)
    if (in_array($level, [MH_LOG_EMERGENCY, MH_LOG_ALERT, MH_LOG_CRITICAL, MH_LOG_ERROR, MH_LOG_WARNING])) {
        $details = json_encode(['message' => $message, 'context' => $context, 'channel' => $channel], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        dbExec(
            "INSERT INTO audit_logs (user_id, action, module, details, ip_address, user_agent) VALUES (?,?,?,?,?,?)",
            [$userId, $levelName, $channel, $details, $ip, $ua]
        );
    }

    // Also write to file for persistence and external log aggregation
    $logLine = json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    $logFile = SITE_ROOT . '/storage/logs/app-' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// Convenience methods
function logEmergency(string $message, array $context = [], string $channel = 'app'): void { logEntry(MH_LOG_EMERGENCY, $message, $context, $channel); }
function logAlert(string $message, array $context = [], string $channel = 'app'): void     { logEntry(MH_LOG_ALERT, $message, $context, $channel); }
function logCritical(string $message, array $context = [], string $channel = 'app'): void  { logEntry(MH_LOG_CRITICAL, $message, $context, $channel); }
function logError(string $message, array $context = [], string $channel = 'app'): void     { logEntry(MH_LOG_ERROR, $message, $context, $channel); }
function logWarning(string $message, array $context = [], string $channel = 'app'): void   { logEntry(MH_LOG_WARNING, $message, $context, $channel); }
function logNotice(string $message, array $context = [], string $channel = 'app'): void    { logEntry(MH_LOG_NOTICE, $message, $context, $channel); }
function logInfo(string $message, array $context = [], string $channel = 'app'): void      { logEntry(MH_LOG_INFO, $message, $context, $channel); }
function logDebug(string $message, array $context = [], string $channel = 'app'): void     { logEntry(MH_LOG_DEBUG, $message, $context, $channel); }

/**
 * Log authentication events
 */
function logAuth(string $event, string $email, bool $success, array $context = []): void {
    $level = $success ? MH_LOG_NOTICE : MH_LOG_WARNING;
    $message = "Auth {$event}: " . ($success ? 'success' : 'failed') . " for {$email}";
    logEntry($level, $message, array_merge($context, ['email' => $email, 'success' => $success]), 'auth');
}

/**
 * Log security events
 */
function logSecurity(string $event, array $context = []): void {
    logEntry(MH_LOG_WARNING, "Security event: {$event}", $context, 'security');
}

/**
 * Log AI/chatbot events
 */
function logAI(string $event, array $context = []): void {
    logEntry(MH_LOG_INFO, "AI event: {$event}", $context, 'ai');
}

/**
 * Legacy auditLog function - kept for backward compatibility
 * Now uses structured logger internally
 */
function auditLog(string $action, string $module, string $details = ''): void {
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Write to database (original behavior)
    dbExec(
        "INSERT INTO audit_logs (user_id, action, module, details, ip_address, user_agent) VALUES (?,?,?,?,?,?)",
        [$userId, $action, $module, $details, $ip, $ua]
    );
    
    // Also log via structured logger
    logNotice($action, ['module' => $module, 'details' => $details], $module);
}

/**
 * Get recent logs from file (for admin dashboard)
 */
function getRecentLogs(int $hours = 24, int $limit = 100): array {
    $logFile = SITE_ROOT . '/storage/logs/app-' . date('Y-m-d') . '.log';
    if (!file_exists($logFile)) return [];
    
    $lines = file($logFile);
    $logs = [];
    $cutoff = time() - ($hours * 3600);
    
    foreach (array_reverse($lines) as $line) {
        $entry = json_decode(trim($line), true);
        if ($entry && isset($entry['timestamp'])) {
            $ts = strtotime($entry['timestamp']);
            if ($ts >= $cutoff) {
                $logs[] = $entry;
                if (count($logs) >= $limit) break;
            }
        }
    }
    
    return $logs;
}

/**
 * Initialize error/exception handlers for automatic logging
 */
function initErrorLogging(): void {
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) return false; // Respect error_reporting level
        
        logError("PHP Error: {$message}", [
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
        ], 'php');
        
        return false; // Let PHP continue with default handler
    });

    set_exception_handler(function (Throwable $e): void {
        logCritical("Uncaught Exception: {$e->getMessage()}", [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'code' => $e->getCode(),
        ], 'exception');
        
        if (ENVIRONMENT === 'development') {
            // Show error in development
            echo "<pre style='background:#1a1a2e;color:#e94560;padding:20px;border-radius:8px;font-family:monospace;'>";
            echo "Uncaught Exception: " . htmlspecialchars($e->getMessage()) . "\n";
            echo "File: " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "\n";
            echo "Trace:\n" . htmlspecialchars($e->getTraceAsString());
            echo "</pre>";
        } else {
            // Generic error page in production
            http_response_code(500);
            echo "<div style='font-family:sans-serif;text-align:center;padding:50px;background:#0f172a;color:#fff;'>
                <h1>Something went wrong</h1>
                <p>We've been notified and will fix this shortly.</p>
            </div>";
        }
    });

    register_shutdown_function(function (): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            logCritical("Fatal Error: {$error['message']}", [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type'],
            ], 'fatal');
        }
    });
}

// Auto-initialize if not in CLI
if (php_sapi_name() !== 'cli') {
    initErrorLogging();
}