<?php
// ============================================================
// MARVELOUS HOTEL — Session & Auth Guard
// includes/session.php
// ============================================================

require_once __DIR__ . '/config.php';

// ---- Non-blocking session write for high-concurrency read pages ----
function releaseSessionLock(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
}

/**
 * Prepare CSRF token + consume any flash message into memory before releasing
 * the PHP session lock on GET requests so concurrent pages/assets never stall.
 */
function releaseReadOnlySession(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        generateCsrfToken();
        if (isset($_SESSION['flash']) && !isset($GLOBALS['__mh_preloaded_flash'])) {
            $GLOBALS['__mh_preloaded_flash'] = $_SESSION['flash'];
            unset($_SESSION['flash']);
        }
        @session_write_close();
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    session_name(SESSION_NAME);
    $defaultSavePath = session_save_path();
    if (empty($defaultSavePath) || !is_writable($defaultSavePath)) {
        $customSessPath = SITE_ROOT . '/storage/sessions';
        if (is_dir($customSessPath) && is_writable($customSessPath)) {
            session_save_path($customSessPath);
        }
    }

    $isHttpsConn = function_exists('isHttpsRequest')
        ? isHttpsRequest()
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false));

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $isHttpsConn,
        'httponly' => true,
        'samesite' => 'Lax', // 'Lax' allows shared links to preserve session across external clicks
    ]);
    @session_start();
}

// ---- Security Headers (bago mag-output ng HTML) ----
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ---- CSRF Token Generation & Validation ----
function generateCsrfToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) &&
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . generateCsrfToken() . '">';
}

// ---- Global CSRF Guard: lahat ng POST request ay dapat may valid na token ----
// Tumanggap ng token mula sa form field (csrf_token) o sa header (X-CSRF-Token para sa fetch/AJAX).
// Ang failure ay 403 — pinipigilan ang cross-site request forgery sa lahat ng page nang paisa-isa.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Exempt public read-only AI chatbot and Slim 4 REST API (which runs PSR-15 RoleAuthMiddleware + CsrfProtectionMiddleware with JSON responses)
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $isPublicChatbot = (strpos($uri, 'ai/chatbot.php') !== false);
    $isSlimApi = (strpos($uri, 'backend/api/') !== false);
    // Allow auth HTML pages to render their own styled in-page CSRF error message (they all verify CSRF before processing POST)
    $isAuthFormPage = (bool)preg_match('#/(?:auth/(?:login|register|forgot-password|reset-password|resend-verification|register-staff|setup-admin)|(?:staff|admin)/login)\.php(?:$|\?)#i', $uri);

    if (!$isPublicChatbot && !$isSlimApi && !$isAuthFormPage) {
        $csrfInput = $_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($csrfInput) && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach (['X-CSRF-Token', 'x-csrf-token', 'X-Csrf-Token', 'X-CSRF-TOKEN'] as $hdr) {
                if (!empty($headers[$hdr])) {
                    $csrfInput = $headers[$hdr];
                    break;
                }
            }
        }
        if (!is_string($csrfInput) || $csrfInput === '' || !verifyCsrfToken($csrfInput)) {
            http_response_code(403);
            error_log('CSRF rejected: ' . $uri . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
            die('403 — Illegal request (missing or invalid security token). Please refresh the page and try again.');
        }
    }
}

// ---- Check if user is logged in (with Remember Me support strictly scoped to User portal) ----
function isLoggedIn(): bool {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
        return true;
    }

    // Attempt auto-login via persistent Remember Me cookie ONLY on the User/Main portal
    // (never leak guest sessions into Admin or Staff portals)
    $rememberCookie = $_COOKIE['MH_REMEMBER_USER'] ?? '';
    if (ACTIVE_PORTAL === 'user' && !empty($rememberCookie) && function_exists('dbRow')) {
        $parts = explode(':', $rememberCookie, 2);
        if (count($parts) === 2) {
            $uId = (int)$parts[0];
            $token = $parts[1];
            $u = dbRow("SELECT * FROM users WHERE id = ? AND status = 'active' AND role = 'user' AND remember_expires > NOW()", [$uId]);
            if ($u && !empty($u['remember_token']) && hash_equals($u['remember_token'], hash('sha256', $token))) {
                // Regenerate session for persistent user
                session_regenerate_id(true);
                $_SESSION['user_id']     = $u['id'];
                $_SESSION['user_name']   = function_exists('formatFullName') ? formatFullName($u) : ($u['first_name'] . ' ' . $u['last_name']);
                $_SESSION['user_email']  = $u['email'];
                $_SESSION['user_role']   = 'user';
                $_SESSION['user_avatar'] = $u['avatar'] ?: 'default-avatar.png';
                $_SESSION['_status_checked_at'] = time();
                return true;
            }
        }
    }

    return false;
}

// ---- Get current user info ----
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    $avatar = $_SESSION['user_avatar'] ?? null;
    if ($avatar === null || $avatar === '') {
        if (function_exists('dbRow')) {
            $u = dbRow("SELECT avatar FROM users WHERE id = ?", [$_SESSION['user_id']]);
            $avatar = (!empty($u['avatar'])) ? $u['avatar'] : 'default-avatar.png';
            $_SESSION['user_avatar'] = $avatar;
        } else {
            $avatar = 'default-avatar.png';
        }
    }
    return [
        'id'         => $_SESSION['user_id'],
        'name'       => $_SESSION['user_name'] ?? '',
        'email'      => $_SESSION['user_email'] ?? '',
        'role'       => $_SESSION['user_role'],
        'avatar'     => $avatar,
    ];
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function currentRole(): string {
    return $_SESSION['user_role'] ?? '';
}

function isAdmin(): bool   { return currentRole() === 'admin'; }
function isStaff(): bool   { return currentRole() === 'staff'; }
function isStaffOrAdmin(): bool { return in_array(currentRole(), ['staff','admin']); }
function isUser(): bool    { return currentRole() === 'user'; }

// ---- Require login (redirect if not logged in) ----
function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        $back = $redirect ?: $_SERVER['REQUEST_URI'];
        setFlash('info', 'Please log in to your account first. Direct dashboard access is restricted.');
        header('Location: ' . SITE_URL . '/auth/login.php?redirect=' . urlencode($back));
        exit;
    }
}

// ---- Get dashboard URL per role/server ----
function getDashboardUrl(?string $role = null): string {
    $role = $role ?: currentRole();
    if ($role === 'admin') {
        return ADMIN_SITE_URL . '/dashboard.php';
    }
    if ($role === 'staff') {
        return STAFF_SITE_URL . '/dashboard.php';
    }
    if ($role === 'user') {
        return USER_SITE_URL . '/user/dashboard.php';
    }
    return USER_SITE_URL . '/index.php';
}

// ---- Require specific role (with live account-status enforcement) ----
function requireRole(string ...$roles): void {
    if (!isLoggedIn()) {
        if (in_array('admin', $roles, true)) {
            setFlash('error', 'Administrator login required. Direct dashboard access is not allowed.');
            header('Location: ' . ADMIN_SITE_URL . '/login.php');
            exit;
        } elseif (in_array('staff', $roles, true)) {
            setFlash('error', 'Staff login required. Direct dashboard access is not allowed.');
            header('Location: ' . STAFF_SITE_URL . '/login.php');
            exit;
        } else {
            setFlash('info', 'Please log in first to access your dashboard. Direct access is restricted.');
            header('Location: ' . USER_SITE_URL . '/auth/login.php');
            exit;
        }
    }

    // Enforce live account status so banned guests or deactivated staff are kicked immediately
    $uId = (int)($_SESSION['user_id'] ?? 0);
    $isWriteReq = (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET');
    $lastChecked = (int)($_SESSION['_status_checked_at'] ?? 0);
    if ($uId > 0 && function_exists('dbRow') && ($isWriteReq || (time() - $lastChecked) >= 30)) {
        try {
            $acct = dbRow("SELECT status, role FROM users WHERE id = ? LIMIT 1", [$uId]);
            if (!$acct || ($acct['status'] ?? '') !== 'active' || ($acct['role'] ?? '') !== currentRole()) {
                $portal = ACTIVE_PORTAL;
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                if ($portal === 'user' && isset($_COOKIE['MH_REMEMBER_USER'])) {
                    setcookie('MH_REMEMBER_USER', '', [
                        'expires'  => time() - 3600,
                        'path'     => '/',
                        'secure'   => function_exists('isHttpsRequest') ? isHttpsRequest() : false,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }
                setFlash('error', 'Your account session is no longer active. Please contact administration.');
                if ($portal === 'admin') {
                    header('Location: ' . ADMIN_SITE_URL . '/login.php');
                } elseif ($portal === 'staff') {
                    header('Location: ' . STAFF_SITE_URL . '/login.php');
                } else {
                    header('Location: ' . USER_SITE_URL . '/auth/login.php');
                }
                exit;
            }
            $_SESSION['_status_checked_at'] = time();
        } catch (\Throwable $e) {
            // Preserve existing session if DB is temporarily unreachable
        }
    }

    if (!in_array(currentRole(), $roles, true)) {
        // Redirect to their own dashboard
        header('Location: ' . getDashboardUrl(currentRole()));
        exit;
    }
}

// ---- Redirect if already logged in ----
function redirectIfLoggedIn(): void {
    if (!isLoggedIn()) return;
    header('Location: ' . getDashboardUrl(currentRole()));
    exit;
}

// ---- Flash Messages ----
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($GLOBALS['__mh_preloaded_flash'])) {
        $flash = $GLOBALS['__mh_preloaded_flash'];
        unset($GLOBALS['__mh_preloaded_flash']);
        return $flash;
    }
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function sanitizeFlashHtml(string $message): string {
    if (!preg_match('/<\/?(strong|b|em|i|br|small|code|a)\b/i', $message)) {
        return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }

    $clean = strip_tags($message, '<strong><b><em><i><br><small><code><a>');
    $clean = preg_replace('/<(strong|b|em|i|small|code)\b[^>]*>/i', '<$1>', $clean);
    $clean = preg_replace('/<br\s*\/?>/i', '<br>', $clean);
    $clean = preg_replace_callback('/<a\b([^>]*)>/i', function (array $matches): string {
        $attrs = $matches[1] ?? '';
        $href = '#';
        if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $m)) {
            $candidate = html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8');
            if (preg_match('/^(https?:\/\/|\/|[a-z0-9_.\-\/]+\.php)/i', $candidate)) {
                $href = htmlspecialchars($candidate, ENT_QUOTES, 'UTF-8');
            }
        }
        $targetBlank = preg_match('/\btarget\s*=\s*(["\'])_blank\1/i', $attrs);
        return '<a href="' . $href . '"' . ($targetBlank ? ' target="_blank" rel="noopener noreferrer"' : '') . '>';
    }, $clean);

    return $clean;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $icons = [
        'success' => 'fa-circle-check',
        'error'   => 'fa-circle-xmark',
        'warning' => 'fa-triangle-exclamation',
        'info'    => 'fa-circle-info',
    ];
    $type = preg_replace('/[^a-z]/i', '', (string)($flash['type'] ?? 'info')) ?: 'info';
    $icon = $icons[$type] ?? 'fa-bell';
    return '<div class="flash-message flash-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" id="flashMsg">
        <i class="fa-solid ' . $icon . '"></i>
        <span>' . sanitizeFlashHtml((string)($flash['message'] ?? '')) . '</span>
        <button type="button" onclick="this.parentElement.remove()" class="flash-close"><i class="fa-solid fa-xmark"></i></button>
    </div>';
}
