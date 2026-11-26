<?php
// ============================================================
// MARVELOUS HOTEL — Live Password Reset Status Endpoint
// auth/check-reset-status.php
// ============================================================

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// POST: Acknowledge one-time popup view or cancel current watch (CSRF enforced globally by session.php)
if ($method === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'acknowledge') {
        consumePasswordResetWatch();
        echo json_encode(['ok' => true, 'status' => 'acknowledged']);
        exit;
    }

    if ($action === 'cancel') {
        unset($_SESSION['password_reset_watch']);
        echo json_encode(['ok' => true, 'status' => 'none']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
    exit;
}

// GET: Read-only live status poll scoped strictly to this browser's session watch
$watch = $_SESSION['password_reset_watch'] ?? null;
releaseSessionLock();

if (!is_array($watch) || empty($watch['token'])) {
    echo json_encode(['status' => 'none']);
    exit;
}

$requestId = (int)($watch['request_id'] ?? 0);
if ($requestId <= 0) {
    echo json_encode(buildResetStatusPayload(null, $watch));
    exit;
}

try {
    ensurePasswordResetSchema();
    $row = dbRow(
        "SELECT pr.id, pr.user_id, pr.email, pr.status, pr.dispatch_type,
                pr.new_password_assigned, pr.requested_at, pr.resolved_at,
                pr.user_notified_at, pr.request_token,
                u.reset_token, u.reset_expires
         FROM password_reset_requests pr
         LEFT JOIN users u ON pr.user_id = u.id
         WHERE pr.id = ?
         LIMIT 1",
        [$requestId]
    );
} catch (\Throwable $e) {
    error_log('check-reset-status query failed: ' . $e->getMessage());
    echo json_encode(['status' => 'pending', 'email' => (string)($watch['email'] ?? '')]);
    exit;
}

$payload = buildResetStatusPayload($row, $watch);
echo json_encode($payload);
