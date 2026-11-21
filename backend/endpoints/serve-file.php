<?php
// ============================================================
// MARVELOUS HOTEL — Secure File Serve
// serve-file.php
// Serves files from outside webroot with access control,
// non-blocking session locking, ETag caching, and avatar thumbnails
// ============================================================

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

$fileParam = $_GET['file'] ?? '';

if (!$fileParam) {
    releaseSessionLock();
    http_response_code(400);
    die('Missing file parameter');
}

// Sanitize path - prevent directory traversal
$fileParam = ltrim($fileParam, '/');
$fileParam = str_replace('..', '', $fileParam);

$isProof  = (strpos($fileParam, 'proofs/') === 0);
$isDoc    = (strpos($fileParam, 'documents/') === 0);
$isAvatar = (strpos($fileParam, 'avatars/') === 0);

// Public files (e.g., avatars) don't need session state at all — release lock immediately!
if (!$isProof && !$isDoc) {
    releaseSessionLock();
}

// Validate file exists
$securePath = SECURE_UPLOADS_PATH;
$fullPath = $securePath . $fileParam;

if (!file_exists($fullPath) || !is_file($fullPath)) {
    releaseSessionLock();
    http_response_code(404);
    die('File not found');
}

// Verify the path is within secure uploads directory
$realSecure = realpath($securePath);
$realFile = realpath($fullPath);

if (!$realFile || strpos($realFile, $realSecure) !== 0) {
    releaseSessionLock();
    http_response_code(403);
    die('Access denied');
}

// ---- Access control: payment proofs & identity documents (owner / admin / staff only) ----
if ($isProof || $isDoc) {
    // If not logged in in current active portal session, check other portal session cookies (admin/staff/user)
    if (empty($_SESSION['user_id'])) {
        $otherPortals = ['MH_ADMIN_SESS', 'MH_STAFF_SESS', 'MH_USER_SESS'];
        $currentName = session_name();
        foreach ($otherPortals as $pName) {
            if ($pName !== $currentName && !empty($_COOKIE[$pName])) {
                @session_write_close();
                session_name($pName);
                @session_start();
                if (!empty($_SESSION['user_id'])) {
                    break;
                }
            }
        }
    }

    $currentUserId = $_SESSION['user_id'] ?? null;
    $currentUserRole = $_SESSION['user_role'] ?? null;

    // Done reading session — release lock before DB queries and file streaming!
    releaseSessionLock();

    if (!$currentUserId) {
        http_response_code(401);
        die('Login required');
    }

    if ($isProof && !in_array($currentUserRole, ['admin', 'staff'], true)) {
        $owner = dbRow("SELECT id FROM payments WHERE proof_image = ? AND user_id = ?", [$fileParam, $currentUserId]);
        if (!$owner) {
            http_response_code(403);
            die('Access denied');
        }
    }

    if ($isDoc && !in_array($currentUserRole, ['admin', 'staff'], true)) {
        $owner = dbRow("SELECT id FROM users WHERE id_document = ? AND id = ?", [$fileParam, $currentUserId]);
        if (!$owner) {
            http_response_code(403);
            die('Access denied');
        }
    }
}

// Check if file is an image for inline display
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $realFile);
finfo_close($finfo);

// Serve lightweight cached thumbnail for oversized avatars unless ?size=full is requested
$servePath = $realFile;
$origSize  = @filesize($realFile) ?: 0;
if ($isAvatar && ($_GET['size'] ?? '') !== 'full' && $origSize > 65536 && str_starts_with((string)$mime, 'image/') && $mime !== 'image/gif') {
    $cacheDir  = SITE_ROOT . '/storage/cache/avatars';
    $cacheFile = $cacheDir . '/' . md5($realFile . '|' . (@filemtime($realFile) ?: 0)) . '.jpg';
    $thumbMime = 'image/jpeg';

    // Preserve PNG transparency only when source is PNG and quantized/smaller, else JPEG thumbnail
    if (in_array($mime, ['image/png', 'image/x-png'], true)) {
        $cacheFile = $cacheDir . '/' . md5($realFile . '|' . (@filemtime($realFile) ?: 0)) . '.png';
        $thumbMime = 'image/png';
    }

    if (is_file($cacheFile) && is_readable($cacheFile)) {
        $servePath = $cacheFile;
        $mime = $thumbMime;
    } elseif (optimizeImageFileGd($realFile, $cacheFile, $mime, 320, 82)) {
        $thumbSize = @filesize($cacheFile) ?: PHP_INT_MAX;
        if ($thumbSize > 0 && $thumbSize < $origSize) {
            $servePath = $cacheFile;
            $mime = $thumbMime;
        } else {
            @unlink($cacheFile);
        }
    }
}

// Determine if we should force download or display inline
$forceDownload = isset($_GET['download']) || !str_starts_with((string)$mime, 'image/');

$fileSize = filesize($servePath);
$mtime    = @filemtime($servePath) ?: time();
$etag     = '"' . md5($servePath . '|' . $mtime . '|' . $fileSize) . '"';

// Browser cache headers & 304 Not Modified fast path
$cacheControl = ($isProof || $isDoc)
    ? 'private, max-age=86400'
    : 'public, max-age=604800';

header('Cache-Control: ' . $cacheControl);
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('X-Content-Type-Options: nosniff');

$ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$ifModSince  = trim((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
if (($ifNoneMatch !== '' && $ifNoneMatch === $etag)
    || ($ifModSince !== '' && @strtotime($ifModSince) >= $mtime)) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . $fileSize);

if ($forceDownload) {
    $originalName = basename($fileParam);
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
} else {
    header('Content-Disposition: inline');
}

while (ob_get_level() > 0) {
    @ob_end_clean();
}

// Stream file
readfile($servePath);
exit;