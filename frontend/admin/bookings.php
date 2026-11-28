<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('admin');
releaseReadOnlySession();

throttleStaleBookingSweep(24, 15);

$adminId = currentUserId();
$tab = $_GET['tab'] ?? 'all';

$buildBookingsQs = function () use ($tab): string {
    $redir = [];
    if ($tab !== 'all') $redir['tab'] = $tab;
    if (!empty($_GET['q'])) $redir['q'] = trim((string)$_GET['q']);
    if (!empty($_GET['page']) && (int)$_GET['page'] > 1) $redir['page'] = (int)$_GET['page'];
    return !empty($redir) ? ('?' . http_build_query($redir)) : '';
};

// Handle direct payment receipt verify / reject from Inspector modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_payment'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $payId = (int)($_POST['payment_id'] ?? 0);
        $act = $_POST['action_type'] ?? ''; // 'verify' or 'reject'
        $res = processPaymentDecision($payId, $act, $adminId, 'Admin');
        setFlash($res['flash_type'], $res['message']);
    }
    header("Location: bookings.php" . $buildBookingsQs());
    exit;
}

// Admin status override with comprehensive timestamp audit tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['override_status'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $bId = (int)$_POST['booking_id'];
        $newStat = $_POST['status'];
        $reason = trim($_POST['status_reason'] ?? '');

        if (in_array($newStat, ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'rejected'], true)) {
            $bk = dbRow("
                SELECT b.*, r.status AS room_status, r.room_number, r.is_dorm, rt.name AS room_type_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN room_types rt ON r.room_type_id = rt.id
                WHERE b.id = ?
            ", [$bId]);
            if ($bk) {
                $wasCheckedIn = ($bk['status'] === 'checked_in');
                if ($newStat === 'confirmed') {
                    $conflict = findConflictingBooking((int)$bk['room_id'], $bk['check_in'], $bk['check_out'], $bId);
                    if ($conflict) {
                        setFlash('error', "Cannot confirm Booking #{$bk['booking_code']}: overlaps with Booking #{$conflict['booking_code']} (" . formatDate($conflict['check_in'], 'M d') . " - " . formatDate($conflict['check_out'], 'M d') . ").");
                    } else {
                        dbExec("UPDATE bookings SET status = 'confirmed', accepted_by = ?, accepted_at = NOW(), cancelled_reason = NULL WHERE id = ?", [$adminId, $bId]);
                        if ($wasCheckedIn) {
                            releaseRoomOccupancyIfIdle((int)$bk['room_id'], $bId);
                        }
                        // Auto-sync pending payment receipt to verified so it does not stay stuck in 'verification pending'
                        $autoVerifiedPay = dbExec("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE booking_id = ? AND status = 'pending'", [$adminId, $bId]);
                        if ($autoVerifiedPay > 0) {
                            auditLog('Admin Auto-Verified Payment', 'Admin Module', "Auto-verified {$autoVerifiedPay} pending payment(s) during Booking #{$bk['booking_code']} approval by Admin #$adminId");
                        }
                        auditLog('Admin Approved Booking', 'Admin Module', "Booking #{$bk['booking_code']} approved by Admin #$adminId");
                        createNotification($bk['user_id'], 'Reservation Confirmed', "Your booking (#{$bk['booking_code']}) has been officially confirmed by Administration.", 'booking', 'my-bookings.php');
                        setFlash('success', "Booking #{$bk['booking_code']} has been approved and confirmed.");
                    }
                } elseif (in_array($newStat, ['cancelled', 'rejected'], true)) {
                    if (empty($reason)) {
                        $reason = ($newStat === 'rejected') ? 'Denied by Admin' : 'Cancelled by Admin';
                    }
                    dbExec("UPDATE bookings SET status = ?, cancelled_at = NOW(), cancelled_reason = ? WHERE id = ?", [$newStat, $reason, $bId]);
                    rejectPendingPaymentsForBooking($bId, "Auto-rejected: booking {$newStat} by Admin");
                    if ($wasCheckedIn) {
                        releaseRoomOccupancyIfIdle((int)$bk['room_id'], $bId);
                    }
                    auditLog('Admin Denied/Cancelled Booking', 'Admin Module', "Booking #{$bk['booking_code']} marked $newStat. Reason: $reason");
                    createNotification($bk['user_id'], 'Booking Status Update', "Your reservation #{$bk['booking_code']} was marked $newStat: $reason", 'alert', 'my-bookings.php');
                    setFlash('warning', "Booking #{$bk['booking_code']} marked as " . strtoupper($newStat) . ".");
                } elseif ($newStat === 'checked_in') {
                    $isDormRoom = (!empty($bk['is_dorm']) || strcasecmp((string)($bk['room_type_name'] ?? ''), 'Dormitory') === 0);
                    $inHouseGuest = !$isDormRoom
                        ? dbRow("SELECT booking_code FROM bookings WHERE room_id = ? AND status = 'checked_in' AND id != ? LIMIT 1", [(int)$bk['room_id'], $bId])
                        : null;
                    $conflict = findConflictingBooking((int)$bk['room_id'], $bk['check_in'], $bk['check_out'], $bId);
                    if (($bk['room_status'] ?? '') === 'maintenance') {
                        setFlash('error', "Cannot check in Booking #{$bk['booking_code']}: Room #{$bk['room_number']} is currently under Maintenance. Update room status first.");
                    } elseif ($inHouseGuest) {
                        setFlash('error', "Cannot check in Booking #{$bk['booking_code']}: Room #{$bk['room_number']} still has Booking #{$inHouseGuest['booking_code']} checked in. Check out the previous guest first.");
                    } elseif ($conflict) {
                        setFlash('error', "Cannot check in Booking #{$bk['booking_code']}: overlaps with Booking #{$conflict['booking_code']} (" . formatDate($conflict['check_in'], 'M d') . " - " . formatDate($conflict['check_out'], 'M d') . ").");
                    } else {
                        dbExec("UPDATE bookings SET status = 'checked_in', checked_in_at = NOW(), accepted_by = COALESCE(accepted_by, ?), accepted_at = COALESCE(accepted_at, NOW()), cancelled_reason = NULL WHERE id = ?", [$adminId, $bId]);
                        dbExec("UPDATE rooms SET status = 'occupied' WHERE id = ?", [$bk['room_id']]);
                        auditLog('Admin Checked In Guest', 'Admin Module', "Booking #{$bk['booking_code']} marked checked-in");
                        createNotification((int)$bk['user_id'], 'Checked In — Welcome!', "You are now checked in to Room #{$bk['room_number']} for Booking #{$bk['booking_code']}. Enjoy your stay at Marvelous Hotel!", 'booking', 'my-bookings.php');
                        setFlash('success', "Customer for booking #{$bk['booking_code']} checked in successfully.");
                    }
                } elseif ($newStat === 'checked_out') {
                    dbExec("UPDATE bookings SET status = 'checked_out', checked_out_at = NOW() WHERE id = ?", [$bId]);
                    $becameIdle = releaseRoomOccupancyIfIdle((int)$bk['room_id'], $bId, 'dirty');
                    createNotification((int)$bk['user_id'], 'Checked Out — Thank You!', "You have checked out from Booking #{$bk['booking_code']}. We hope you enjoyed your stay!", 'booking', 'reviews.php');
                    if (!$becameIdle) {
                        auditLog('Admin Checked Out Guest', 'Admin Module', "Booking #{$bk['booking_code']} marked checked-out (other dorm guest still in-house).");
                        setFlash('info', "Customer for booking #{$bk['booking_code']} checked out. Room stays occupied by remaining dorm guest.");
                    } else {
                        auditLog('Admin Checked Out Guest', 'Admin Module', "Booking #{$bk['booking_code']} marked checked-out. Room marked for cleaning.");
                        setFlash('info', "Customer for booking #{$bk['booking_code']} checked out. Room marked for housekeeping.");
                    }
                } else { // pending
                    dbExec("UPDATE bookings SET status = 'pending', accepted_at = NULL, cancelled_at = NULL, cancelled_reason = NULL WHERE id = ?", [$bId]);
                    if ($wasCheckedIn) {
                        releaseRoomOccupancyIfIdle((int)$bk['room_id'], $bId);
                    }
                    auditLog('Admin Reset Booking Status', 'Admin Module', "Booking #{$bk['booking_code']} reset to pending");
                    setFlash('info', "Booking #{$bk['booking_code']} reset to pending state.");
                }
            }
        }
    }
    header("Location: bookings.php" . $buildBookingsQs());
    exit;
}

// Counts for filter pills
$counts = dbRow("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cnt,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_cnt,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in_cnt,
        SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as checked_out_cnt,
        SUM(CASE WHEN status IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as cancelled_cnt
    FROM bookings
");

$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$conditions = ["1=1"];
$params = [];
if ($tab === 'pending') {
    $conditions[] = "b.status = 'pending'";
} elseif ($tab === 'confirmed') {
    $conditions[] = "b.status = 'confirmed'";
} elseif ($tab === 'checked_in') {
    $conditions[] = "b.status = 'checked_in'";
} elseif ($tab === 'checked_out') {
    $conditions[] = "b.status = 'checked_out'";
} elseif ($tab === 'cancelled') {
    $conditions[] = "b.status IN ('cancelled', 'rejected')";
}
if ($search !== '') {
    $conditions[] = "(b.booking_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR r.name LIKE ? OR r.room_number LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
$whereClause = implode(' AND ', $conditions);

$totalFiltered = (int)(dbRow("
    SELECT COUNT(*) as c
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    WHERE $whereClause
", $params)['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$allBookings = dbRows("
    SELECT b.*, 
           u.first_name, u.middle_name, u.last_name, u.suffix, u.email, u.phone, 
           r.name as room_name, r.room_number,
           ac.first_name as acceptor_fn, ac.last_name as acceptor_ln, ac.role as acceptor_role,
           lp.id as payment_id,
           lp.status as payment_status,
           COALESCE(lp.method, b.payment_method, 'maya') as payment_method,
           lp.proof_image as proof_image,
           lp.reference_number as reference_number,
           lp.notes as payment_notes,
           lp.amount as paid_amount,
           lp.created_at as payment_created_at
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    LEFT JOIN users ac ON b.accepted_by = ac.id
    LEFT JOIN (
        SELECT p1.*
        FROM payments p1
        INNER JOIN (
            SELECT booking_id, MAX(id) AS max_id
            FROM payments
            GROUP BY booking_id
        ) pm ON p1.id = pm.max_id
    ) lp ON lp.booking_id = b.id
    WHERE $whereClause
    ORDER BY b.id DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Master Bookings Registry &amp; Lifecycle Audit — Admin Portal</title>
  <?php require_once __DIR__ . '/../components/page-head.php'; renderPerformanceHeadLinks(false); ?>
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/admin.css')) ?>">
  <style>
    .booking-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      padding-bottom: 12px;
      flex-wrap: wrap;
    }
    .booking-tab {
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--text-muted);
      background: rgba(255,255,255,0.02);
      border: 1px solid rgba(255,255,255,0.06);
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .booking-tab:hover {
      background: rgba(201,168,76,0.1);
      color: var(--gold);
    }
    .booking-tab.active {
      background: var(--gold);
      color: #0b1120;
      font-weight: 700;
      border-color: var(--gold);
    }
    .tab-counter {
      background: rgba(0,0,0,0.25);
      color: inherit;
      padding: 2px 7px;
      border-radius: 12px;
      font-size: 0.72rem;
    }
    .booking-tab.active .tab-counter {
      background: #0b1120;
      color: #fff;
    }
    .timeline-modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(4, 7, 20, 0.85);
      backdrop-filter: blur(5px);
      z-index: 9999;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .timeline-box {
      background: var(--navy-card);
      border: 1px solid var(--border);
      border-radius: 14px;
      max-width: 620px;
      width: 100%;
      padding: 26px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.6);
      max-height: 90vh;
      overflow-y: auto;
    }
    .timeline-track {
      position: relative;
      margin-top: 20px;
      padding-left: 32px;
    }
    .timeline-track::before {
      content: '';
      position: absolute;
      left: 11px;
      top: 8px;
      bottom: 8px;
      width: 2px;
      background: rgba(255,255,255,0.1);
    }
    .timeline-node {
      position: relative;
      margin-bottom: 22px;
    }
    .timeline-node:last-child {
      margin-bottom: 0;
    }
    .timeline-icon-dot {
      position: absolute;
      left: -32px;
      top: 0;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      border: 2px solid var(--navy-card);
    }
  </style>
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('admin'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button type="button" class="sidebar-collapse-btn" data-sidebar-collapse title="Toggle Compact Sidebar" aria-label="Toggle Compact Sidebar" aria-expanded="true"><i class="fa-solid fa-bars-staggered"></i></button>
        <div class="topbar-title">Master Bookings Registry</div>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-gold"><i class="fa-solid fa-shield-halved"></i> Audit &amp; Timestamps</span>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
          <h1 class="page-header-title">Master Reservation Records</h1>
          <div class="page-header-sub">Full audit trail of bookings with exact decision timestamps for approvals, denials, and customer check-ins.</div>
        </div>
        <form action="bookings.php" method="GET" style="display:flex;gap:8px;align-items:center;min-width:290px;">
          <input type="hidden" name="tab" value="<?= h($tab) ?>">
          <input type="text" id="bookingSearchInput" name="q" value="<?= h($search) ?>" data-table-search data-table-target="#bookingsTable" class="form-control" placeholder="Search code, customer, room, or email..." style="background:rgba(255,255,255,0.05);font-size:0.85rem;">
          <button type="submit" class="btn btn-gold btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
          <?php if ($search !== ''): ?>
            <a href="bookings.php?tab=<?= urlencode($tab) ?>" class="btn btn-ghost btn-sm" title="Clear"><i class="fa-solid fa-xmark"></i></a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Quick Filter Tabs (Hindi Magulo at Hindi Watak-watak) -->
      <div class="booking-tabs">
        <a href="bookings.php?tab=all<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="booking-tab <?= $tab === 'all' ? 'active' : '' ?>">
          <i class="fa-solid fa-list-ul"></i> All Records <span class="tab-counter"><?= (int)($counts['total'] ?? 0) ?></span>
        </a>
        <a href="bookings.php?tab=pending<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="booking-tab <?= $tab === 'pending' ? 'active' : '' ?>">
          <i class="fa-solid fa-clock" style="color:#eab308;"></i> Pending Approval <span class="tab-counter"><?= (int)($counts['pending_cnt'] ?? 0) ?></span>
        </a>
        <a href="bookings.php?tab=confirmed<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="booking-tab <?= $tab === 'confirmed' ? 'active' : '' ?>">
          <i class="fa-solid fa-circle-check" style="color:#22c55e;"></i> Approved / Confirmed <span class="tab-counter"><?= (int)($counts['confirmed_cnt'] ?? 0) ?></span>
        </a>
        <a href="bookings.php?tab=checked_in<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="booking-tab <?= $tab === 'checked_in' ? 'active' : '' ?>">
          <i class="fa-solid fa-key" style="color:#60a5fa;"></i> In-House Occupants <span class="tab-counter"><?= (int)($counts['checked_in_cnt'] ?? 0) ?></span>
        </a>
        <a href="bookings.php?tab=checked_out<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="booking-tab <?= $tab === 'checked_out' ? 'active' : '' ?>">
          <i class="fa-solid fa-door-closed" style="color:#94a3b8;"></i> Checked Out <span class="tab-counter"><?= (int)($counts['checked_out_cnt'] ?? 0) ?></span>
        </a>
        <a href="bookings.php?tab=cancelled<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="booking-tab <?= $tab === 'cancelled' ? 'active' : '' ?>">
          <i class="fa-solid fa-ban" style="color:#ef4444;"></i> Denied / Cancelled <span class="tab-counter"><?= (int)($counts['cancelled_cnt'] ?? 0) ?></span>
        </a>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table" id="bookingsTable">
              <thead>
                <tr>
                  <th class="col-nowrap">Booking ID &amp; Created</th>
                  <th>Customer &amp; Contact</th>
                  <th class="col-nowrap">Room Assigned</th>
                  <th class="col-nowrap">Stay Dates</th>
                  <th class="col-amount">Amount &amp; Payment</th>
                  <th class="col-nowrap">Status &amp; Timestamps</th>
                  <th class="col-nowrap">Admin Override &amp; Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($allBookings)): ?>
                  <tr data-empty-row><td colspan="7" class="text-center" style="padding:32px;color:var(--text-muted);">No reservation records found for this category.</td></tr>
                <?php else: ?>
                  <?php foreach ($allBookings as $bk): 
                    $acceptorName = trim(($bk['acceptor_fn'] ?? '') . ' ' . ($bk['acceptor_ln'] ?? ''));
                  ?>
                    <tr>
                      <!-- 1. Booking ID & Submission Time -->
                      <td class="col-nowrap">
                        <strong style="color:var(--gold);font-size:0.95rem;">#<?= h($bk['booking_code']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                          <i class="fa-solid fa-calendar-plus"></i> <?= formatDate($bk['created_at'], 'M d, Y') ?>
                        </div>
                        <div style="font-size:0.72rem;color:var(--text-muted);">
                          <i class="fa-solid fa-clock"></i> <?= formatDate($bk['created_at'], 'h:i:s A') ?>
                        </div>
                      </td>

                      <!-- 2. Guest -->
                      <td>
                        <strong><?= h(formatFullName($bk)) ?></strong>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                          <span><i class="fa-solid fa-envelope"></i> <?= h($bk['email']) ?></span>
                          <?php if (!empty($bk['phone'])): ?>
                            <span>&bull; <i class="fa-solid fa-phone"></i> <?= h($bk['phone']) ?></span>
                          <?php endif; ?>
                        </div>
                      </td>

                      <!-- 3. Room -->
                      <td class="col-nowrap">
                        <div style="font-weight:600;color:#fff;">Room <?= h($bk['room_number']) ?></div>
                        <div style="font-size:0.75rem;color:var(--text-muted);"><?= h($bk['room_name']) ?></div>
                      </td>

                      <!-- 4. Stay Dates -->
                      <td class="col-nowrap">
                        <div style="font-size:0.85rem;color:#fff;white-space:nowrap;">
                          <?= formatDate($bk['check_in'], 'M d, Y') ?> &rarr; <?= formatDate($bk['check_out'], 'M d, Y') ?>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                          <?= $bk['nights'] ?> Night(s) &bull; <?= $bk['num_guests'] ?> Pax
                        </div>
                      </td>

                      <!-- 5. Amount & Payment -->
                      <td class="col-amount">
                        <div style="color:var(--gold);font-weight:700;font-size:1.05rem;white-space:nowrap;"><?= formatCurrency($bk['total_amount']) ?></div>
                        <div style="margin-top:2px;display:flex;flex-direction:column;gap:3px;align-items:flex-start;">
                          <?php if ($bk['payment_status'] === 'verified'): ?>
                            <span class="badge" style="background:#22c55e;color:#fff;font-size:0.68rem;padding:2px 6px;">
                              <i class="fa-solid fa-check"></i> Paid
                            </span>
                          <?php elseif ($bk['payment_status'] === 'pending'): ?>
                            <span class="badge" style="background:#eab308;color:#000;font-size:0.68rem;padding:2px 6px;">
                              <i class="fa-solid fa-clock"></i> Verification Pending
                            </span>
                          <?php else: ?>
                            <span class="badge" style="background:rgba(239,68,68,0.2);color:#ef4444;font-size:0.68rem;padding:2px 6px;">
                              <i class="fa-solid fa-circle-exclamation"></i> Unpaid
                            </span>
                          <?php endif; ?>

                          <?php if (($bk['payment_method'] ?? '') === 'gcash'): ?>
                            <span class="badge" style="background:rgba(41,121,255,0.15);color:#64b5f6;border:1px solid rgba(41,121,255,0.3);font-size:0.65rem;padding:1px 5px;">
                              <i class="fa-solid fa-mobile-screen-button"></i> GCash
                            </span>
                          <?php else: ?>
                            <span class="badge" style="background:rgba(0,210,106,0.15);color:#00e074;border:1px solid rgba(0,210,106,0.3);font-size:0.65rem;padding:1px 5px;">
                              <i class="fa-solid fa-qrcode"></i> Maya
                            </span>
                          <?php endif; ?>

                          <?php if (!empty($bk['proof_image'])): ?>
                            <button type="button" class="btn btn-gold btn-sm" onclick='openReceiptInspector(<?= json_encode([
                              "booking_id" => $bk["id"],
                              "booking_code" => $bk["booking_code"],
                              "guest" => formatFullName($bk),
                              "email" => $bk["email"],
                              "phone" => $bk["phone"],
                              "room" => $bk["room_name"] . " (" . $bk["room_number"] . ")",
                              "amount" => formatCurrency($bk["paid_amount"] ?: $bk["total_amount"]),
                              "method" => strtoupper($bk["payment_method"]),
                              "ref_num" => $bk["reference_number"] ?: "No Reference Number",
                              "proof_url" => getUploadUrl($bk["proof_image"]),
                              "proof_raw" => $bk["proof_image"],
                              "payment_status" => $bk["payment_status"] ?? "pending",
                              "payment_id" => $bk["payment_id"],
                              "notes" => $bk["payment_notes"] ?: "No notes provided.",
                              "paid_at" => !empty($bk["payment_created_at"]) ? formatDate($bk["payment_created_at"], "M d, Y \a\t h:i:s A") : "N/A"
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' style="padding:3px 8px;font-size:0.72rem;margin-top:4px;display:inline-flex;align-items:center;gap:5px;background:linear-gradient(135deg,var(--gold),#eab308);color:#000;font-weight:700;border:none;box-shadow:0 2px 8px rgba(201,168,76,0.25);">
                              <i class="fa-solid fa-receipt"></i> View Receipt
                            </button>
                          <?php endif; ?>
                        </div>
                      </td>

                      <!-- 6. Status & Decision Timestamps -->
                      <td class="col-nowrap">
                        <div style="margin-bottom:4px;"><?= statusBadge($bk['status']) ?></div>

                        <?php if (!empty($bk['accepted_at']) && in_array($bk['status'], ['confirmed', 'checked_in', 'checked_out'])): ?>
                          <div style="font-size:0.72rem;color:#4ade80;line-height:1.4;margin-top:3px;">
                            <i class="fa-solid fa-circle-check"></i> <strong>Approved:</strong> <?= formatDate($bk['accepted_at'], 'M d, Y') ?><br>
                            <span style="color:#86efac;margin-left:14px;"><?= formatDate($bk['accepted_at'], 'h:i A') ?></span>
                            <?php if ($acceptorName !== ''): ?>
                              <span style="color:var(--text-muted);">by <?= h($acceptorName) ?> (<?= ucfirst($bk['acceptor_role'] ?: 'Staff') ?>)</span>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>

                        <?php if (in_array($bk['status'], ['cancelled', 'rejected'])): ?>
                          <div style="font-size:0.72rem;color:#f87171;line-height:1.4;margin-top:3px;">
                            <i class="fa-solid fa-ban"></i> <strong><?= $bk['status'] === 'rejected' ? 'Denied' : 'Cancelled' ?>:</strong> <?= !empty($bk['cancelled_at']) ? formatDate($bk['cancelled_at'], 'M d, Y h:i A') : 'Recorded' ?><br>
                            <?php if (!empty($bk['cancelled_reason'])): ?>
                              <span style="color:#fca5a5;margin-left:14px;" title="<?= h($bk['cancelled_reason']) ?>">
                                Reason: <?= h(truncateText($bk['cancelled_reason'], 26)) ?>
                              </span>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>

                        <?php if (!empty($bk['checked_in_at'])): ?>
                          <div style="font-size:0.72rem;color:#60a5fa;line-height:1.4;margin-top:2px;">
                            <i class="fa-solid fa-key"></i> <strong>Checked In:</strong> <?= formatDate($bk['checked_in_at'], 'M d, Y h:i A') ?>
                          </div>
                        <?php endif; ?>

                        <?php if (!empty($bk['checked_out_at'])): ?>
                          <div style="font-size:0.72rem;color:#94a3b8;line-height:1.4;margin-top:2px;">
                            <i class="fa-solid fa-door-closed"></i> <strong>Checked Out:</strong> <?= formatDate($bk['checked_out_at'], 'M d, Y h:i A') ?>
                          </div>
                        <?php endif; ?>
                      </td>

                      <!-- 7. Actions & Override -->
                      <td class="col-nowrap">
                        <div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">
                          <button type="button" class="btn btn-ghost btn-sm" onclick='openBookingTimeline(<?= json_encode([
                            "booking_code" => $bk["booking_code"],
                            "guest" => formatFullName($bk),
                            "email" => $bk["email"],
                            "room" => $bk["room_name"] . " (Room #" . $bk["room_number"] . ")",
                            "status" => $bk["status"],
                            "created_at" => formatDate($bk["created_at"], "M d, Y \a\t h:i:s A"),
                            "accepted_at" => !empty($bk["accepted_at"]) ? formatDate($bk["accepted_at"], "M d, Y \a\t h:i:s A") : null,
                            "acceptor" => $acceptorName ?: "Administration",
                            "cancelled_at" => !empty($bk["cancelled_at"]) ? formatDate($bk["cancelled_at"], "M d, Y \a\t h:i:s A") : null,
                            "cancelled_reason" => $bk["cancelled_reason"] ?? null,
                            "checked_in_at" => !empty($bk["checked_in_at"]) ? formatDate($bk["checked_in_at"], "M d, Y \a\t h:i:s A") : null,
                            "checked_out_at" => !empty($bk["checked_out_at"]) ? formatDate($bk["checked_out_at"], "M d, Y \a\t h:i:s A") : null,
                            "payment_status" => $bk["payment_status"] ?? "unpaid",
                            "payment_method" => strtoupper($bk["payment_method"] ?? "N/A"),
                            "payment_created_at" => !empty($bk["payment_created_at"]) ? formatDate($bk["payment_created_at"], "M d, Y \a\t h:i:s A") : null,
                            "proof_url" => !empty($bk["proof_image"]) ? getUploadUrl($bk["proof_image"]) : null,
                            "reference_number" => $bk["reference_number"] ?? null,
                            "total_amount" => formatCurrency($bk["total_amount"])
                          ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Complete Audit Timeline" style="padding:4px 8px;font-size:0.75rem;">
                            <i class="fa-solid fa-timeline"></i> Timeline
                          </button>
                        </div>

                        <form action="bookings.php?tab=<?= urlencode($tab) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?><?= $page > 1 ? '&page=' . $page : '' ?>" method="POST" style="display:flex;gap:4px;align-items:center;" onsubmit="return handleBookingOverrideSubmit(this)">
                          <?= csrfField() ?>
                          <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
                          <input type="hidden" name="override_status" value="1">
                          <input type="hidden" name="status_reason" value="">
                          <select name="status" class="form-control" style="font-size:0.75rem;padding:4px 8px;min-width:115px;">
                            <option value="pending" <?= $bk['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $bk['status'] === 'confirmed' ? 'selected' : '' ?>>Approved</option>
                            <option value="checked_in" <?= $bk['status'] === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                            <option value="checked_out" <?= $bk['status'] === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                            <option value="rejected" <?= $bk['status'] === 'rejected' ? 'selected' : '' ?>>Denied</option>
                            <option value="cancelled" <?= $bk['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                          </select>
                          <button type="submit" class="btn btn-gold btn-sm" style="padding:5px 8px;" title="Save Status Override">
                            <i class="fa-solid fa-save"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1 || $totalFiltered > 0): ?>
            <div class="admin-pagination">
              <span>Showing page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalFiltered ?> booking record(s))</span>
              <?php if ($totalPages > 1): ?>
                <div class="admin-pagination-links">
                  <?php if ($page > 1): ?>
                    <a href="bookings.php?tab=<?= urlencode($tab) ?>&q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm">&laquo; Prev</a>
                  <?php endif; ?>
                  <?php if ($page < $totalPages): ?>
                    <a href="bookings.php?tab=<?= urlencode($tab) ?>&q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm">Next &raquo;</a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Booking Lifecycle Audit Timeline Modal -->
<div class="timeline-modal" id="bookingTimelineModal" onclick="closeBookingTimeline(event)">
  <div class="timeline-box" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:18px;">
      <div>
        <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.35rem;margin:0;">
          <i class="fa-solid fa-clock-rotate-left"></i> Booking Lifecycle Audit
        </h3>
        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;">Complete sequence of events and decisions for <strong id="modalBookingCode" style="color:#fff;"></strong>.</div>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeBookingTimeline()" style="font-size:1.3rem;padding:4px 8px;color:#94a3b8;">&times;</button>
    </div>

    <!-- Overview Card -->
    <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;font-size:0.85rem;">
      <div>
        <div style="color:var(--text-muted);font-size:0.75rem;">Customer &amp; Room:</div>
        <div style="font-weight:700;color:#fff;" id="modalGuestInfo">-</div>
      </div>
      <div style="text-align:right;">
        <div style="color:var(--text-muted);font-size:0.75rem;">Total Amount:</div>
        <div style="font-weight:700;color:var(--gold);font-size:1.05rem;" id="modalAmount">-</div>
      </div>
    </div>

    <!-- Timeline Steps -->
    <div class="timeline-track" id="modalTimelineTrack">
      <!-- Populated via JS -->
    </div>

    <div style="margin-top:24px;text-align:right;">
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeBookingTimeline()">Close Window</button>
    </div>
  </div>
</div>

<!-- Receipt Quick Inspector Modal for Bookings Registry -->
<div id="receiptInspectorModal" class="admin-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(5,10,24,0.85);backdrop-filter:blur(8px);z-index:9999;align-items:center;justify-content:center;padding:20px;" onclick="closeReceiptInspector()">
  <div class="glass-card" style="max-width:850px;width:100%;max-height:90vh;overflow-y:auto;padding:28px;background:var(--navy);border:1px solid rgba(201,168,76,0.3);border-radius:16px;box-shadow:0 25px 60px rgba(0,0,0,0.7);" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:20px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="width:36px;height:36px;border-radius:10px;background:rgba(201,168,76,0.15);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:1.1rem;">
          <i class="fa-solid fa-receipt"></i>
        </span>
        <div>
          <h3 style="color:#fff;font-size:1.2rem;margin:0;">Payment Verification &amp; Receipt Inspector</h3>
          <div style="font-size:0.78rem;color:var(--text-muted);">Inspect uploaded payment screenshot before approving reservation</div>
        </div>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeReceiptInspector()" style="font-size:1.2rem;padding:4px 8px;color:#94a3b8;">&times;</button>
    </div>

    <div class="admin-modal-grid-2" id="inspectorGrid">
      <!-- Screenshot Preview Column -->
      <div style="background:rgba(0,0,0,0.4);border:1px solid var(--border);border-radius:12px;padding:12px;text-align:center;">
        <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;font-weight:700;">Uploaded Proof Receipt</div>
        <img id="inspectorReceiptImg" src="#" alt="Proof Receipt" style="max-width:100%;max-height:380px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.5);display:block;margin:0 auto;object-fit:contain;">
        <div id="inspectorReceiptFallback" style="display:none;padding:28px 16px;color:var(--text-muted);font-size:0.85rem;">
          <i class="fa-solid fa-file-pdf" style="font-size:2.4rem;color:var(--gold);margin-bottom:8px;display:block;"></i>
          <span>Preview unavailable inline. Open file in a new tab below.</span>
        </div>
        <div style="margin-top:10px;">
          <a href="#" id="inspectorReceiptLink" target="_blank" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full File
          </a>
        </div>
      </div>

      <!-- Transaction Comparison Column -->
      <div>
        <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:18px;">
          <div style="font-size:0.75rem;text-transform:uppercase;color:var(--gold);font-weight:700;margin-bottom:12px;">Transaction Details</div>
          
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Customer Name:</span>
            <strong style="color:#fff;" id="inspectorGuestName">-</strong>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Booking Code:</span>
            <span style="color:#60a5fa;font-weight:600;" id="inspectorBookingCode">-</span>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Room Assigned:</span>
            <span style="color:#fff;" id="inspectorRoomName">-</span>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Payment Channel:</span>
            <span style="color:#00e074;font-weight:700;" id="inspectorMethod">-</span>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Amount:</span>
            <strong style="color:var(--gold);font-size:1.1rem;" id="inspectorAmount">-</strong>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;">
            <span style="color:var(--text-muted);">Timestamp Paid:</span>
            <span style="color:#fde047;font-weight:600;" id="inspectorPaidAt">-</span>
          </div>

          <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:0.85rem;">
            <span style="color:var(--text-muted);">Reference Number:</span>
            <code style="color:#facc15;font-weight:700;" id="inspectorRefNum">-</code>
          </div>
        </div>

        <!-- Notes -->
        <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:18px;font-size:0.8rem;color:#cbd5e1;">
          <div style="color:var(--text-muted);font-size:0.75rem;margin-bottom:4px;">Customer Notes:</div>
          <div id="inspectorNotes">-</div>
        </div>

        <!-- Direct Actions -->
        <div id="inspectorActionArea">
          <form id="inspectorActionForm" action="bookings.php?tab=<?= urlencode($tab) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?><?= $page > 1 ? '&page=' . $page : '' ?>" method="POST" style="display:flex;gap:10px;">
            <?= csrfField() ?>
            <input type="hidden" name="payment_id" id="inspectorPaymentId" value="">
            <input type="hidden" name="action_payment" value="1">
            <button type="submit" name="action_type" value="verify" class="btn btn-success" style="flex:1;">
              <i class="fa-solid fa-check"></i> Approve &amp; Confirm Booking
            </button>
            <button type="submit" name="action_type" value="reject" class="btn btn-danger" style="flex:1;">
              <i class="fa-solid fa-xmark"></i> Reject Receipt
            </button>
          </form>
          <div id="inspectorStatusNotice" style="display:none;background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.3);color:#4ade80;padding:12px;border-radius:8px;font-weight:700;text-align:center;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script defer src="<?= h(assetUrl('js/admin.js')) ?>"></script>
<script>
function handleBookingOverrideSubmit(form) {
  const sel = form.querySelector('select[name="status"]');
  const reasonInp = form.querySelector('input[name="status_reason"]');
  if (sel && reasonInp && (sel.value === 'rejected' || sel.value === 'cancelled')) {
    const label = sel.value === 'rejected' ? 'Denial' : 'Cancellation';
    const r = prompt(`Enter reason for ${label} (optional):`, '');
    if (r === null) return false;
    reasonInp.value = r.trim();
  }
  return true;
}

function openBookingTimeline(data) {
  const safeEsc = (v) => (typeof escapeHtml === 'function')
    ? escapeHtml(v)
    : String(v ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
  document.getElementById('modalBookingCode').textContent = '#' + data.booking_code;
  document.getElementById('modalGuestInfo').textContent = data.guest + ' • ' + data.room;
  document.getElementById('modalAmount').textContent = data.total_amount;

  const track = document.getElementById('modalTimelineTrack');
  track.innerHTML = '';

  // Step 1: Created / Booked
  track.innerHTML += `
    <div class="timeline-node">
      <div class="timeline-icon-dot" style="background:#3b82f6;color:#fff;"><i class="fa-solid fa-calendar-plus"></i></div>
      <div style="font-weight:700;color:#fff;font-size:0.9rem;">Reservation Submitted by User</div>
      <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
        <i class="fa-solid fa-clock" style="color:var(--gold);"></i> ${safeEsc(data.created_at)}
      </div>
      <div style="font-size:0.78rem;color:#94a3b8;margin-top:2px;">Account: ${safeEsc(data.guest)} (${safeEsc(data.email)})</div>
    </div>
  `;

  // Step 2: Payment Receipt (if any)
  if (data.payment_created_at) {
    let proofHtml = '';
    if (data.proof_url) {
      const safeProofUrl = safeEsc(data.proof_url);
      proofHtml = `
        <div style="margin-top:10px;background:rgba(0,0,0,0.3);border:1px solid rgba(201,168,76,0.25);border-radius:10px;padding:10px;display:inline-block;">
          <a href="${safeProofUrl}" target="_blank" style="display:block;border-radius:6px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);">
            <img src="${safeProofUrl}" alt="Receipt Screenshot" style="max-height:140px;max-width:200px;display:block;object-fit:cover;border-radius:4px;">
          </a>
          <div style="margin-top:6px;display:flex;gap:8px;align-items:center;">
            <a href="${safeProofUrl}" target="_blank" class="btn btn-gold btn-sm" style="font-size:0.72rem;padding:3px 8px;">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> Open High-Res
            </a>
          </div>
        </div>
      `;
    }
    track.innerHTML += `
      <div class="timeline-node">
        <div class="timeline-icon-dot" style="background:#eab308;color:#000;"><i class="fa-solid fa-receipt"></i></div>
        <div style="font-weight:700;color:#fff;font-size:0.9rem;">Payment Receipt Uploaded (${safeEsc(data.payment_method)})</div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
          <i class="fa-solid fa-clock" style="color:var(--gold);"></i> ${safeEsc(data.payment_created_at)}
        </div>
        ${data.reference_number ? `<div style="font-size:0.78rem;color:#facc15;margin-top:2px;">Reference #: <code>${safeEsc(data.reference_number)}</code></div>` : ''}
        <div style="font-size:0.78rem;color:#cbd5e1;margin-top:2px;">Status: <span style="text-transform:uppercase;">${safeEsc(data.payment_status)}</span></div>
        ${proofHtml}
      </div>
    `;
  }

  // Step 3: Decision (Approved vs Denied/Cancelled)
  if (data.accepted_at) {
    track.innerHTML += `
      <div class="timeline-node">
        <div class="timeline-icon-dot" style="background:#22c55e;color:#fff;"><i class="fa-solid fa-check"></i></div>
        <div style="font-weight:700;color:#4ade80;font-size:0.9rem;">Reservation Officially Approved &amp; Confirmed</div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
          <i class="fa-solid fa-clock" style="color:var(--gold);"></i> ${safeEsc(data.accepted_at)}
        </div>
        <div style="font-size:0.78rem;color:#cbd5e1;margin-top:2px;">Decision made by: <strong>${safeEsc(data.acceptor)}</strong></div>
      </div>
    `;
  } else if (data.cancelled_at) {
    const isDenied = data.status === 'rejected';
    track.innerHTML += `
      <div class="timeline-node">
        <div class="timeline-icon-dot" style="background:#ef4444;color:#fff;"><i class="fa-solid fa-ban"></i></div>
        <div style="font-weight:700;color:#f87171;font-size:0.9rem;">${isDenied ? 'Reservation Denied' : 'Reservation Cancelled'}</div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
          <i class="fa-solid fa-clock" style="color:var(--gold);"></i> ${safeEsc(data.cancelled_at)}
        </div>
        <div style="font-size:0.78rem;color:#fca5a5;margin-top:2px;">Reason: ${safeEsc(data.cancelled_reason || 'No specific reason recorded.')}</div>
      </div>
    `;
  } else {
    track.innerHTML += `
      <div class="timeline-node">
        <div class="timeline-icon-dot" style="background:#64748b;color:#fff;"><i class="fa-solid fa-hourglass-half"></i></div>
        <div style="font-weight:700;color:#cbd5e1;font-size:0.9rem;">Awaiting Administrative Review</div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">Pending decision by Hotel Admin or Frontdesk Staff.</div>
      </div>
    `;
  }

  // Step 4: Checked In
  if (data.checked_in_at) {
    track.innerHTML += `
      <div class="timeline-node">
        <div class="timeline-icon-dot" style="background:#3b82f6;color:#fff;"><i class="fa-solid fa-key"></i></div>
        <div style="font-weight:700;color:#60a5fa;font-size:0.9rem;">Customer Checked In at Frontdesk</div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
          <i class="fa-solid fa-clock" style="color:var(--gold);"></i> ${safeEsc(data.checked_in_at)}
        </div>
        <div style="font-size:0.78rem;color:#94a3b8;margin-top:2px;">Room assigned and room key issued.</div>
      </div>
    `;
  }

  // Step 5: Checked Out
  if (data.checked_out_at) {
    track.innerHTML += `
      <div class="timeline-node">
        <div class="timeline-icon-dot" style="background:#64748b;color:#fff;"><i class="fa-solid fa-door-closed"></i></div>
        <div style="font-weight:700;color:#e2e8f0;font-size:0.9rem;">Customer Checked Out &amp; Folio Settled</div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
          <i class="fa-solid fa-clock" style="color:var(--gold);"></i> ${safeEsc(data.checked_out_at)}
        </div>
        <div style="font-size:0.78rem;color:#94a3b8;margin-top:2px;">Room scheduled for housekeeping &amp; cleaning.</div>
      </div>
    `;
  }

  document.getElementById('bookingTimelineModal').style.display = 'flex';
}

function closeBookingTimeline(e) {
  document.getElementById('bookingTimelineModal').style.display = 'none';
}

function openReceiptInspector(data) {
  document.getElementById('inspectorPaymentId').value = data.payment_id || '';
  document.getElementById('inspectorGuestName').textContent = data.guest + (data.email ? ' (' + data.email + ')' : '');
  document.getElementById('inspectorBookingCode').textContent = '#' + data.booking_code;
  document.getElementById('inspectorRoomName').textContent = data.room;
  document.getElementById('inspectorMethod').textContent = data.method;
  document.getElementById('inspectorAmount').textContent = data.amount;
  document.getElementById('inspectorPaidAt').textContent = data.paid_at;
  document.getElementById('inspectorRefNum').textContent = data.ref_num;
  document.getElementById('inspectorNotes').textContent = data.notes || 'No customer notes.';

  const imgEl = document.getElementById('inspectorReceiptImg');
  const fallbackEl = document.getElementById('inspectorReceiptFallback');
  const linkEl = document.getElementById('inspectorReceiptLink');
  const rawName = (data.proof_raw || data.proof_url || '').toLowerCase();
  const isPdf = rawName.endsWith('.pdf');

  if (data.proof_url && !isPdf) {
    if (fallbackEl) fallbackEl.style.display = 'none';
    imgEl.onerror = function () {
      imgEl.style.display = 'none';
      if (fallbackEl) fallbackEl.style.display = 'block';
    };
    imgEl.src = data.proof_url;
    linkEl.href = data.proof_url;
    imgEl.style.display = 'block';
    linkEl.style.display = 'inline-flex';
  } else if (data.proof_url) {
    imgEl.style.display = 'none';
    if (fallbackEl) fallbackEl.style.display = 'block';
    linkEl.href = data.proof_url;
    linkEl.style.display = 'inline-flex';
  } else {
    imgEl.style.display = 'none';
    if (fallbackEl) fallbackEl.style.display = 'none';
    linkEl.href = '#';
    linkEl.style.display = 'none';
  }

  // Toggle action form vs status notice without destroying the form DOM
  const formEl = document.getElementById('inspectorActionForm');
  const noticeEl = document.getElementById('inspectorStatusNotice');
  if (data.payment_status === 'verified') {
    noticeEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> This payment is already verified and confirmed.';
    noticeEl.style.display = 'block';
    formEl.style.display = 'none';
  } else if (!data.payment_id) {
    noticeEl.innerHTML = '<i class="fa-solid fa-circle-info"></i> No payment receipt submitted for this booking yet.';
    noticeEl.style.display = 'block';
    formEl.style.display = 'none';
  } else {
    noticeEl.style.display = 'none';
    noticeEl.innerHTML = '';
    formEl.style.display = 'flex';
  }

  document.getElementById('receiptInspectorModal').style.display = 'flex';
}

function closeReceiptInspector() {
  document.getElementById('receiptInspectorModal').style.display = 'none';
}
</script>
</body>
</html>
