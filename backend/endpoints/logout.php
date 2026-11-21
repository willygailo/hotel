<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

if (isLoggedIn()) {
    $uId = $_SESSION['user_id'] ?? null;
    auditLog('User Logged Out', 'Auth', "User ID {$uId} logged out.");
    if ($uId && function_exists('dbExec')) {
        dbExec("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = ?", [$uId]);
    }
}

// Clear Remember Me cookie
if (isset($_COOKIE['MH_REMEMBER_USER'])) {
    setcookie('MH_REMEMBER_USER', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Capture the role before the session is destroyed so we can send
// each role back to its own login portal (kanya-kanya server).
$role = $_SESSION['user_role'] ?? 'user';

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

$loginMap = [
    'admin' => ADMIN_SITE_URL . '/login.php',
    'staff' => STAFF_SITE_URL . '/login.php',
    'user'  => USER_SITE_URL . '/auth/login.php',
];
header("Location: " . ($loginMap[$role] ?? (USER_SITE_URL . '/auth/login.php')));
exit;
