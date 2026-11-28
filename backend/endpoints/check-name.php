<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'available' => false, 'message' => 'Method not allowed.']);
    exit;
}

$fn = trim((string)($_REQUEST['first_name'] ?? ''));
$mn = trim((string)($_REQUEST['middle_name'] ?? ''));
$ln = trim((string)($_REQUEST['last_name'] ?? ''));
$suf = trim((string)($_REQUEST['suffix'] ?? ''));
$excludeId = isset($_REQUEST['exclude_id']) ? (int)$_REQUEST['exclude_id'] : null;

if ($fn === '' || $ln === '') {
    echo json_encode([
        'status'    => 'incomplete',
        'available' => false,
        'message'   => 'Please enter both First Name and Last Name.'
    ]);
    exit;
}

$err = validatePersonName($fn, $ln, $mn, $suf, $excludeId);

if ($err !== null) {
    echo json_encode([
        'status'    => 'invalid',
        'available' => false,
        'message'   => $err
    ]);
} else {
    echo json_encode([
        'status'    => 'available',
        'available' => true,
        'message'   => 'This name is available!'
    ]);
}
