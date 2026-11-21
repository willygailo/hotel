<?php
// ============================================================
// MARVELOUS HOTEL — Admin Executive AI Insights Engine
// ai/admin-insights.php
// ============================================================

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/nvidia-client.php';

if (!isLoggedIn() || !isAdmin()) {
    releaseSessionLock();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin authorization required.']);
    exit;
}

// Release PHP session lock immediately so navigating to other admin pages never stalls during AI calls
releaseSessionLock();

$forceRefresh = (($_GET['refresh'] ?? '') === '1');
if (!$forceRefresh) {
    $cached = getAiResponseCache('admin_insights', 300);
    if ($cached !== null) {
        $cached['cached'] = true;
        echo json_encode($cached);
        exit;
    }
}

// 1. Compute financial and operational analytics
$totalRevenue = dbRow("SELECT SUM(amount) as total FROM payments WHERE status = 'verified'")['total'] ?? 0;
$monthRevenue = dbRow("SELECT SUM(amount) as total FROM payments WHERE status = 'verified' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")['total'] ?? 0;

$mayaRevenue = dbRow("SELECT SUM(amount) as total FROM payments WHERE status = 'verified' AND method = 'maya'")['total'] ?? 0;
$gcashRevenue = dbRow("SELECT SUM(amount) as total FROM payments WHERE status = 'verified' AND method = 'gcash'")['total'] ?? 0;

$totalRooms = dbRow("SELECT COUNT(*) as cnt FROM rooms")['cnt'] ?? 1;
$occupiedRooms = dbRow("SELECT COUNT(*) as cnt FROM rooms WHERE status = 'occupied'")['cnt'] ?? 0;
$occupancyRate = round(($occupiedRooms / max(1, $totalRooms)) * 100, 1);

$totalBookings = dbRow("SELECT COUNT(*) as cnt FROM bookings")['cnt'] ?? 0;
$confirmedBookings = dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'confirmed'")['cnt'] ?? 0;
$cancelledBookings = dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE status IN ('cancelled', 'rejected')")['cnt'] ?? 0;
$pendingKyc = dbRow("SELECT COUNT(*) as cnt FROM users WHERE role = 'user' AND verification_status = 'pending'")['cnt'] ?? 0;
$pendingPayments = dbRow("SELECT COUNT(*) as cnt FROM payments WHERE status = 'pending'")['cnt'] ?? 0;

// 2. Query NVIDIA NIM Live AI Engine with dedicated Admin role prompt
$prompt = "Provide a concise, high-level Strategic Executive Digest in professional Taglish for the Hotel Administrator based on the following live hotel metrics:\n"
    . "- Total All-Time Verified Revenue: PHP " . number_format($totalRevenue, 2) . "\n"
    . "- This Month Revenue: PHP " . number_format($monthRevenue, 2) . "\n"
    . "- PayMaya (Maya) Channel Share: PHP " . number_format($mayaRevenue, 2) . "\n"
    . "- GCash Channel Share: PHP " . number_format($gcashRevenue, 2) . "\n"
    . "- Current Room Occupancy Rate: {$occupancyRate}% ({$occupiedRooms} of {$totalRooms} rooms occupied)\n"
    . "- Bookings Summary: {$confirmedBookings} confirmed, {$cancelledBookings} cancelled/rejected out of {$totalBookings} total\n"
    . "- Operational Backlog: {$pendingPayments} pending payments, {$pendingKyc} pending KYC verifications\n\n"
    . "Structure into 3 brief sections: 1. Financial Performance & Payment Channels, 2. Occupancy & Operations, 3. Strategic Action Item (promo, pricing, or backlog action). "
    . "Keep it executive, data-driven, and actionable.";

$aiReport = null;
if (getSetting('ai_enabled', '1')) {
    $aiReply = queryNvidiaAI($prompt, ['role' => 'admin']);
    if (!empty($aiReply)) {
        $aiReport = sanitizeAiHtml($aiReply);
    }
}

// 3. Programmatic Fallback
if (empty($aiReport)) {
    $aiReport = "<strong>📈 Executive AI Performance Digest (" . date('F Y') . ")</strong><br><br>";
    $aiReport .= "<strong>1. Pananalapi & Payment Channels:</strong> Ang kabuuang naipong kita ay umabot sa <strong>PHP " . number_format($totalRevenue, 2) . "</strong> (ngayong buwan: <strong>PHP " . number_format($monthRevenue, 2) . "</strong>). Maya: <strong>PHP " . number_format($mayaRevenue, 2) . "</strong> | GCash: <strong>PHP " . number_format($gcashRevenue, 2) . "</strong>.<br>";
    $aiReport .= "<strong>2. Room Occupancy & Backlog:</strong> Kasalukuyang nasa <strong>{$occupancyRate}%</strong> ang occupancy ({$occupiedRooms} sa {$totalRooms} kwarto). May <strong>{$pendingPayments}</strong> pending payment at <strong>{$pendingKyc}</strong> pending KYC review.<br>";
    $aiReport .= "<strong>3. Strategic Recommendation:</strong> ";
    if ($occupancyRate < 50) {
        $aiReport .= "Medyo mababa ang occupancy ngayong araw. Inirerekomenda ang promo para sa dormitory at standard rooms at agarang pag-verify ng pending payments.";
    } else {
        $aiReport .= "Maganda ang takbo ng bookings at mataas ang occupancy. Panatilihing mabilis ang room turnover at KYC/payment verification.";
    }
}

$responsePayload = [
    'success' => true,
    'report' => $aiReport,
    'metrics' => [
        'total_revenue' => $totalRevenue,
        'month_revenue' => $monthRevenue,
        'occupancy_rate' => $occupancyRate,
        'maya_revenue' => $mayaRevenue,
        'gcash_revenue' => $gcashRevenue,
        'pending_payments' => $pendingPayments,
        'pending_kyc' => $pendingKyc
    ]
];
setAiResponseCache('admin_insights', $responsePayload);
echo json_encode($responsePayload);
