<?php
// ============================================================
// MARVELOUS HOTEL — Staff Operations AI Co-Pilot
// ai/staff-assistant.php
// ============================================================

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/nvidia-client.php';

if (!isLoggedIn() || (!isStaff() && !isAdmin())) {
    releaseSessionLock();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Release PHP session lock immediately so staff page transitions never block on AI latency
releaseSessionLock();

$forceRefresh = (($_GET['refresh'] ?? '') === '1');
if (!$forceRefresh) {
    $cached = getAiResponseCache('staff_assistant_' . date('Y-m-d'), 300);
    if ($cached !== null) {
        $cached['cached'] = true;
        echo json_encode($cached);
        exit;
    }
}

// 1. Gather live operational metrics
$today = date('Y-m-d');

$arrivals = dbRows("
    SELECT b.booking_code, b.num_guests, r.room_number, r.name as room_name, u.first_name, u.last_name, b.status
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON b.user_id = u.id
    WHERE b.check_in = ? AND b.status IN ('confirmed', 'checked_in')
", [$today]);

$departures = dbRows("
    SELECT b.booking_code, r.room_number, r.name as room_name, u.first_name, u.last_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON b.user_id = u.id
    WHERE b.check_out = ? AND b.status = 'checked_in'
", [$today]);

$dirtyRooms = dbRows("
    SELECT room_number, name, floor, status 
    FROM rooms 
    WHERE status IN ('dirty', 'maintenance')
    ORDER BY floor ASC, room_number ASC
");

$pendingBookingsCount = dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'pending'")['cnt'] ?? 0;
$unpaidArrivalsCount = dbRow("
    SELECT COUNT(*) as cnt
    FROM bookings b
    WHERE b.check_in = ?
      AND b.status = 'confirmed'
      AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.id AND p.status = 'verified')
", [$today])['cnt'] ?? 0;

$arrivalsCount = count($arrivals);
$departuresCount = count($departures);
$dirtyCount = count($dirtyRooms);

$arrivalRoomList = !empty($arrivals)
    ? implode(', ', array_map(fn($a) => "Room {$a['room_number']} (#{$a['booking_code']})", $arrivals))
    : 'None';
$dirtyRoomList = !empty($dirtyRooms)
    ? implode(', ', array_map(fn($r) => "Room {$r['room_number']} ({$r['status']})", $dirtyRooms))
    : 'None';

// 2. Format a smart briefing prompt for NVIDIA NIM AI
$prompt = "Create a warm, organized, and actionable Shift Operational Briefing in professional Taglish for the frontdesk staff on duty today ($today).\n"
    . "Operational Data:\n"
    . "- Expected Arrivals Today: $arrivalsCount guests ($arrivalRoomList).\n"
    . "- Expected Departures (Check-outs) Today: $departuresCount guests.\n"
    . "- Rooms needing cleaning / Housekeeping or Maintenance priority: $dirtyCount rooms ($dirtyRoomList).\n"
    . "- Bookings pending frontdesk review: $pendingBookingsCount bookings.\n"
    . "- Arriving bookings without verified payment yet: $unpaidArrivalsCount.\n\n"
    . "Provide 3 concise bulleted sections: 1. Highlights for Today, 2. Housekeeping Priority, 3. Frontdesk Reminder. "
    . "Keep it brief, practical, and inspiring.";

$aiText = null;
if (getSetting('ai_enabled', '1')) {
    $aiReply = queryNvidiaAI($prompt, ['role' => 'staff']);
    if (!empty($aiReply)) {
        $aiText = sanitizeAiHtml($aiReply);
    }
}

// 3. Fallback: Dynamic programmatic briefing if AI offline
if (empty($aiText)) {
    $aiText = "<strong>📋 Frontdesk Shift Operational Briefing (" . date('F d, Y') . ")</strong><br><br>";
    $aiText .= "<strong>1. Mga Inaasahang Bisita (Arrivals):</strong> May <strong>{$arrivalsCount}</strong> reservation na nakatakdang mag-check in simula 2:00 PM" . ($arrivalsCount > 0 ? " (" . h($arrivalRoomList) . ")" : "") . ".<br>";
    $aiText .= "<strong>2. Mga Check-out Ngayong Araw:</strong> May <strong>{$departuresCount}</strong> kwarto na nakatakdang mag-check out bago mag-12:00 NN.<br>";
    $aiText .= "<strong>3. Housekeeping & Rooms:</strong> May <strong>{$dirtyCount}</strong> kwartong kailangang linisin o i-check" . ($dirtyCount > 0 ? " (" . h($dirtyRoomList) . ")" : "") . ".<br>";
    if ($pendingBookingsCount > 0 || $unpaidArrivalsCount > 0) {
        $aiText .= "<strong>4. Pending Queue:</strong> May <strong>{$pendingBookingsCount}</strong> pending booking at <strong>{$unpaidArrivalsCount}</strong> arriving stay na walang verified payment.<br>";
    }
    $aiText .= "<br><em>Paalala: Siguraduhing na-verify ang valid ID at resibo ng guest bago ibigay ang room keycard. Magandang araw at masayang paglilingkod!</em>";
}

$responsePayload = [
    'success' => true,
    'briefing' => $aiText,
    'metrics' => [
        'arrivals' => $arrivalsCount,
        'departures' => $departuresCount,
        'dirty' => $dirtyCount,
        'pending' => $pendingBookingsCount,
        'unpaid_arrivals' => $unpaidArrivalsCount
    ],
    'details' => [
        'arrivals' => $arrivals,
        'departures' => $departures,
        'dirty_rooms' => $dirtyRooms
    ]
];
setAiResponseCache('staff_assistant_' . $today, $responsePayload);
echo json_encode($responsePayload);
