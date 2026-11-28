<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

$role = $_SESSION['user_role'] ?? ACTIVE_PORTAL;
if (!in_array($role, ['admin', 'staff', 'user'], true)) {
    $role = 'user';
}

if (isLoggedIn()) {
    $uId = $_SESSION['user_id'] ?? null;
    $module = $role === 'admin' ? 'Admin Auth' : ($role === 'staff' ? 'Staff Auth' : 'User Auth');
    auditLog(ucfirst($role) . ' Logged Out', $module, ucfirst($role) . " ID {$uId} logged out.");
    if ($uId && $role === 'user' && function_exists('dbExec')) {
        dbExec("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = ?", [$uId]);
    }
}

// Clear Remember Me cookie ONLY when logging out of the User portal (never wipe guest cookie during Admin/Staff logout)
if ($role === 'user' && isset($_COOKIE['MH_REMEMBER_USER'])) {
    setcookie('MH_REMEMBER_USER', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => function_exists('isHttpsRequest') ? isHttpsRequest() : false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Destroy active portal session cleanly
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
    setFlash('success', 'You have been signed out safely.');
}

$loginMap = [
    'admin' => ADMIN_SITE_URL . '/login.php',
    'staff' => STAFF_SITE_URL . '/login.php',
    'user'  => USER_SITE_URL . '/auth/login.php',
];
header("Location: " . ($loginMap[$role] ?? (USER_SITE_URL . '/auth/login.php')));
exit;
