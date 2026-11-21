<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json; charset=utf-8');

$fn = trim($_REQUEST['first_name'] ?? '');
$mn = trim($_REQUEST['middle_name'] ?? '');
$ln = trim($_REQUEST['last_name'] ?? '');
$suf = trim($_REQUEST['suffix'] ?? '');
$excludeId = isset($_REQUEST['exclude_id']) ? (int)$_REQUEST['exclude_id'] : null;

if ($fn === '' || $ln === '') {
    echo json_encode([
        'status'    => 'incomplete',
        'available' => false,
        'message'   => 'Maglagay ng First Name at Last Name.'
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
        'message'   => 'Available ang pangalan na ito! (Unique Name)'
    ]);
}
