<?php
// ============================================================
// MARVELOUS HOTEL — Configuration File
// includes/config.php
// ============================================================

// Environment: 'development' or 'production'
// I-set ang MH_ENV=development (hal. via Apache SetEnv) kapag nagde-debug.
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', (getenv('MH_ENV') ?: ($_SERVER['MH_ENV'] ?? 'production')));
}

// ---- Database Config (Supports Local XAMPP and Cloud Environments like Render/Railway) ----
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1'));
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306'));
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'marvelous_hotel'));
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root'));
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : ''));
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ---- Site Config ----
if (!function_exists('isHttpsRequest')) {
    function isHttpsRequest(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_CF_VISITOR']) && strpos((string)$_SERVER['HTTP_CF_VISITOR'], 'https') !== false);
    }
}

if (!defined('SITE_NAME')) define('SITE_NAME', 'Marvelous Hotel');
if (!defined('SITE_URL')) {
    $isHttps = isHttpsRequest();
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/hotel') === 0) {
        define('SITE_URL', $protocol . $host . '/hotel');
    } else {
        define('SITE_URL', $protocol . $host);
    }
}
if (!defined('SITE_ROOT')) define('SITE_ROOT', dirname(__DIR__, 2));

// ---- 3-Tier Path Shortcuts ----
if (!defined('FRONTEND_PATH')) define('FRONTEND_PATH', SITE_ROOT . '/frontend/');
if (!defined('BACKEND_PATH')) define('BACKEND_PATH', SITE_ROOT . '/backend/');
if (!defined('DATABASE_PATH')) define('DATABASE_PATH', SITE_ROOT . '/database/');
if (!defined('INCLUDES_PATH')) define('INCLUDES_PATH', SITE_ROOT . '/backend/core/');
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', SITE_ROOT . '/uploads/');
if (!defined('SECURE_UPLOADS_PATH')) define('SECURE_UPLOADS_PATH', SITE_ROOT . '/uploads/');
if (!defined('ASSETS_URL')) define('ASSETS_URL', SITE_URL . '/frontend/assets');
if (!defined('API_URL')) define('API_URL', SITE_URL . '/backend/api/v1');

// ---- Multi-Server Portal & Session Isolation ----
if (!function_exists('getActivePortal')) {
    /**
     * Detects active server portal: 'admin', 'staff', or 'user'
     * Supports:
     * - Apache SetEnv MH_PORTAL (from VirtualHosts)
     * - Multi-port servers (8080: User, 8081: Admin, 8082: Staff)
     * - Subdomains (admin.hotel.local, staff.hotel.local, hotel.local)
     * - Script / URI path routing (/admin/, /staff/, /hotel/...)
     */
    function getActivePortal(): string {
        // 1. Explicit VirtualHost environment flag
        $env = getenv('MH_PORTAL') ?: ($_SERVER['MH_PORTAL'] ?? '');
        if (in_array(strtolower($env), ['admin', 'staff', 'user'], true)) {
            return strtolower($env);
        }

        // 2. Port-based detection (Multi-port servers)
        $port = (int)($_SERVER['SERVER_PORT'] ?? 0);
        if ($port === 8081) return 'admin';
        if ($port === 8082) return 'staff';
        if ($port === 8080) return 'user';

        // 3. Subdomain-based detection
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (stripos($host, 'admin.') === 0) return 'admin';
        if (stripos($host, 'staff.') === 0) return 'staff';

        // 4. Script / Request URI Path detection
        $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        $target = $script . ' ' . $uri;

        if (preg_match('#[\\/](admin)([\\/]|$|\.php|\?)#i', $target) || stripos($target, 'admin-insights.php') !== false) {
            return 'admin';
        }
        if (preg_match('#[\\/](staff)([\\/]|$|\.php|\?)#i', $target) || stripos($target, 'staff-assistant.php') !== false) {
            return 'staff';
        }

        // 4b. Explicit portal query parameter for shared logout endpoint (survives stripped Referer headers)
        if (stripos($target, 'logout.php') !== false) {
            $portalQuery = strtolower(trim((string)($_GET['portal'] ?? '')));
            if (in_array($portalQuery, ['admin', 'staff', 'user'], true)) {
                return $portalQuery;
            }
        }

        // 4c. Explicit X-Portal header hint for shared REST API endpoints (/backend/api/*)
        $portalHeader = strtolower(trim((string)($_SERVER['HTTP_X_PORTAL'] ?? '')));
        if (in_array($portalHeader, ['admin', 'staff', 'user'], true)) {
            return $portalHeader;
        }

        // 5. Referer-based fallback for shared endpoints (e.g., serve-file.php, backend/api/*)
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($referer)) {
            if (preg_match('#[\\/](admin)([\\/]|$|\.php|\?)#i', $referer) || strpos($referer, ':8081') !== false) {
                return 'admin';
            }
            if (preg_match('#[\\/](staff)([\\/]|$|\.php|\?)#i', $referer) || strpos($referer, ':8082') !== false) {
                return 'staff';
            }
        }

        // 6. Cookie-based fallback for shared endpoints when opened directly or in new tab
        if (stripos($target, 'serve-file.php') !== false) {
            if (!empty($_COOKIE['MH_ADMIN_SESS'])) return 'admin';
            if (!empty($_COOKIE['MH_STAFF_SESS'])) return 'staff';
            if (!empty($_COOKIE['MH_USER_SESS']))  return 'user';
        }

        return 'user';
    }
}

if (!defined('ACTIVE_PORTAL')) {
    define('ACTIVE_PORTAL', getActivePortal());
}

// Multi-Server Portal URLs
if (!defined('USER_SITE_URL')) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);
    $protocol = $isHttps ? 'https://' : 'http://';
    $rawHost  = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $port     = (int)($_SERVER['SERVER_PORT'] ?? 0);
    $isMultiPort = in_array($port, [8080, 8081, 8082], true);
    $isSubdomain = (stripos($_SERVER['HTTP_HOST'] ?? '', 'admin.') === 0 || stripos($_SERVER['HTTP_HOST'] ?? '', 'staff.') === 0);

    if ($isMultiPort) {
        define('USER_SITE_URL',  $protocol . $rawHost . ':8080');
        define('ADMIN_SITE_URL', $protocol . $rawHost . ':8081');
        define('STAFF_SITE_URL', $protocol . $rawHost . ':8082');
    } elseif ($isSubdomain) {
        define('USER_SITE_URL',  $protocol . 'hotel.local');
        define('ADMIN_SITE_URL', $protocol . 'admin.hotel.local');
        define('STAFF_SITE_URL', $protocol . 'staff.hotel.local');
    } else {
        // Standard folder-based paths
        define('USER_SITE_URL',  SITE_URL);
        define('ADMIN_SITE_URL', SITE_URL . '/admin');
        define('STAFF_SITE_URL', SITE_URL . '/staff');
    }
}

// ---- Session Config (Strictly Isolated per Server / Portal) ----
// 30 days (2,592,000 seconds) for User Portal so guests stay seamlessly logged in next day
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', (ACTIVE_PORTAL === 'user') ? 2592000 : 28800); // 30 days for guests, 8 hours for staff/admin
}
// Each portal uses its own unique session name to completely prevent session overwrites/collisions:
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'MH_' . strtoupper(ACTIVE_PORTAL) . '_SESS');
}

// ---- Security ----
if (!defined('HASH_COST')) define('HASH_COST', 12);
if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');

// ---- Upload Limits ----
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 15 * 1024 * 1024); // 15MB
if (!defined('ALLOWED_IMAGE_TYPES')) define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg', 'image/pjpeg', 'image/jpg',
    'image/png', 'image/x-png',
    'image/webp', 'image/gif'
]);

// ---- Pagination ----
if (!defined('ITEMS_PER_PAGE')) define('ITEMS_PER_PAGE', 10);

// ---- Error Display ----
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Production: huwag ipakita sa browser, pero i-log pa rin.
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    if (is_dir(SITE_ROOT . '/logs')) {
        ini_set('error_log', SITE_ROOT . '/logs/php-errors.log');
    }
}

// ---- Timezone ----
date_default_timezone_set('Asia/Manila');
