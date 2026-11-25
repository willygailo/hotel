<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('staff');
releaseReadOnlySession();

throttleStaleBookingSweep(24, 15);

$staffId = currentUserId();
$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all', 'arrivals', 'inhouse'], true)) {
    $tab = 'all';
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

// Build clean query string helper for preserving tab & page on redirects
$buildQs = function () use ($tab, $page): string {
    $p = [];
    if ($tab !== 'all') $p['tab'] = $tab;
    if ($page > 1) $p['page'] = $page;
    return !empty($p) ? ('?' . http_build_query($p)) : '';
};

// Handle Check-in / Check-out actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_guest'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $act = $_POST['action_type'] ?? ''; // 'check_in' or 'check_out'
        $overrideUnpaid = !empty($_POST['confirm_unpaid_checkin']);

        $bk = dbRow("
            SELECT b.*, u.verification_status, u.profile_verified,
                   r.status AS room_status, r.room_number, r.name AS room_name, r.is_dorm
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            WHERE b.id = ?
        ", [$bookingId]);

        if (!$bk) {
            setFlash('error', 'Booking record not found.');
        } elseif ($act === 'check_in') {
            if ($bk['status'] !== 'confirmed') {
                setFlash('warning', "Booking #{$bk['booking_code']} cannot be checked in from status '" . strtoupper($bk['status']) . "'.");
            } elseif (($bk['room_status'] ?? '') === 'maintenance') {
                setFlash('error', "Cannot check in Booking #{$bk['booking_code']}: Room #{$bk['room_number']} is currently under Maintenance. Update Room Status first.");
            } else {
                // Guard non-dorm rooms against still-in-house previous guest
                $inHouseGuest = empty($bk['is_dorm'])
                    ? dbRow("SELECT booking_code FROM bookings WHERE room_id = ? AND status = 'checked_in' AND id != ? LIMIT 1", [(int)$bk['room_id'], $bookingId])
                    : null;

                $conflict = findConflictingBooking((int)$bk['room_id'], $bk['check_in'], $bk['check_out'], $bookingId);
                if ($inHouseGuest) {
                    setFlash('error', "Cannot check in Booking #{$bk['booking_code']}: Room #{$bk['room_number']} still has Booking #{$inHouseGuest['booking_code']} checked in. Check out the previous guest first.");
                } elseif ($conflict && ($conflict['status'] ?? '') !== 'checked_out') {
                    setFlash('error', "Cannot check in Booking #{$bk['booking_code']}: overlaps with Booking #{$conflict['booking_code']} (" . formatDate($conflict['check_in'], 'M d') . " - " . formatDate($conflict['check_out'], 'M d') . ").");
                } else {
                    $verifiedPay = dbRow("SELECT id FROM payments WHERE booking_id = ? AND status = 'verified' LIMIT 1", [$bookingId]);
                    if (!$verifiedPay && !$overrideUnpaid) {
                        setFlash('warning', "Payment for Booking #{$bk['booking_code']} is not yet verified. Verify payment first or check the Desk Override box to proceed.");
                    } else {
                        $kycState = $bk['verification_status'] ?? ($bk['profile_verified'] ? 'verified' : 'unverified');
                        $updated = dbExec(
                            "UPDATE bookings SET status = 'checked_in', checked_in_at = NOW(), accepted_by = COALESCE(accepted_by, ?), accepted_at = COALESCE(accepted_at, NOW()) WHERE id = ? AND status = 'confirmed'",
                            [$staffId, $bookingId]
                        );
                        if ($updated > 0) {
                            dbExec("UPDATE rooms SET status = 'occupied' WHERE id = ?", [(int)$bk['room_id']]);
                            $note = $verifiedPay ? 'Verified payment' : 'Frontdesk unpaid override';
                            $wasDirty = ($bk['room_status'] === 'dirty');
                            auditLog('Customer Checked In', 'Staff Module', "Booking #{$bk['booking_code']} checked in by Staff #$staffId ({$note}; KYC: {$kycState}" . ($wasDirty ? '; room was dirty' : '') . ")");
                            createNotification(
                                (int)$bk['user_id'],
                                'Checked In — Welcome!',
                                "You are now checked in to Room #{$bk['room_number']} for Booking #{$bk['booking_code']}. Enjoy your stay at Marvelous Hotel!",
                                'booking',
                                'my-bookings.php'
                            );
                            if ($wasDirty) {
                                setFlash('warning', "Customer for Booking #{$bk['booking_code']} checked in. Note: Room #{$bk['room_number']} was flagged Dirty — please alert Housekeeping.");
                            } else {
                                setFlash('success', "Customer for Booking #{$bk['booking_code']} checked in successfully.");
                            }
                        } else {
                            setFlash('info', "Booking #{$bk['booking_code']} was already updated. Auto-synchronized.");
                        }
                    }
                }
            }
        } elseif ($act === 'check_out') {
            if ($bk['status'] !== 'checked_in') {
                setFlash('warning', "Booking #{$bk['booking_code']} is not currently checked in.");
            } else {
                $updated = dbExec(
                    "UPDATE bookings SET status = 'checked_out', checked_out_at = NOW() WHERE id = ? AND status = 'checked_in'",
                    [$bookingId]
                );
                if ($updated > 0) {
                    $becameIdle = releaseRoomOccupancyIfIdle((int)$bk['room_id'], $bookingId, 'dirty');
                    createNotification(
                        (int)$bk['user_id'],
                        'Checked Out — Thank You!',
                        "You have checked out from Booking #{$bk['booking_code']}. We hope you enjoyed your stay!",
                        'booking',
                        'reviews.php'
                    );
                    if ($becameIdle) {
                        auditLog('Customer Checked Out', 'Staff Module', "Booking #{$bk['booking_code']} marked checked out by Staff #$staffId. Room #{$bk['room_number']} set to Dirty (Needs Cleaning).");
                        setFlash('info', "Customer checked out cleanly. Room #{$bk['room_number']} set to Housekeeping (Needs Cleaning).");
                    } else {
                        auditLog('Customer Checked Out', 'Staff Module', "Booking #{$bk['booking_code']} marked checked out by Staff #$staffId. Room #{$bk['room_number']} stays Occupied (remaining dorm guest).");
                        setFlash('info', "Customer checked out. Room #{$bk['room_number']} remains Occupied by another checked-in guest.");
                    }
                } else {
                    setFlash('info', "Booking #{$bk['booking_code']} was already checked out.");
                }
            }
        }
    }
    header("Location: check-in.php" . $buildQs());
    exit;
}

// Tab counters
$stayCounts = dbRow("
    SELECT
        COUNT(*) AS all_cnt,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS arrivals_cnt,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) AS inhouse_cnt
    FROM bookings
    WHERE status IN ('confirmed', 'checked_in')
") ?: ['all_cnt' => 0, 'arrivals_cnt' => 0, 'inhouse_cnt' => 0];

$statusWhere = "b.status IN ('confirmed', 'checked_in')";
$totalForTab = (int)($stayCounts['all_cnt'] ?? 0);
if ($tab === 'arrivals') {
    $statusWhere = "b.status = 'confirmed'";
    $totalForTab = (int)($stayCounts['arrivals_cnt'] ?? 0);
} elseif ($tab === 'inhouse') {
    $statusWhere = "b.status = 'checked_in'";
    $totalForTab = (int)($stayCounts['inhouse_cnt'] ?? 0);
}

$totalPages = max(1, (int)ceil($totalForTab / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$activeStays = dbRows("
    SELECT b.*,
           u.first_name, u.middle_name, u.last_name, u.suffix, u.phone, u.profile_verified, u.verification_status, u.id_type,
           r.name AS room_name, r.room_number, r.status AS room_status, r.is_dorm,
           lp.status AS payment_status,
           lp.created_at AS payment_created_at,
           lp.verified_at AS payment_verified_at,
           COALESCE(lp.method, b.payment_method, 'maya') AS payment_method,
           pv.role AS payment_verifier_role,
           CONCAT(pv.first_name, ' ', pv.last_name) AS payment_verifier_name,
           COALESCE(ps.total_paid, 0) AS total_paid
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN (
        SELECT p1.*
        FROM payments p1
        INNER JOIN (
            SELECT booking_id, MAX(id) AS max_id
            FROM payments
            GROUP BY booking_id
        ) pm ON p1.id = pm.max_id
    ) lp ON lp.booking_id = b.id
    LEFT JOIN users pv ON lp.verified_by = pv.id
    LEFT JOIN (
        SELECT booking_id, SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) AS total_paid
        FROM payments
        GROUP BY booking_id
    ) ps ON ps.booking_id = b.id
    WHERE {$statusWhere}
    ORDER BY (b.status = 'confirmed') DESC, b.check_in ASC
    LIMIT {$perPage} OFFSET {$offset}
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Check-in &amp; Check-out — Staff Desk</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/staff.css')) ?>">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('staff'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="staff-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Arrivals and Departures</div>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-info"><i class="fa-solid fa-key"></i> <?= (int)$stayCounts['inhouse_cnt'] ?> Checked In</span>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
          <h1 class="page-header-title">Frontdesk Check-in &amp; Check-out</h1>
          <div class="page-header-sub">Manage arriving and departing customers with room readiness and verified identity checks.</div>
        </div>
      </div>

      <div class="staff-toolbar">
        <div class="staff-filter-pills">
          <a href="check-in.php?tab=all" class="staff-filter-pill<?= $tab === 'all' ? ' active' : '' ?>">
            All Active <span class="pill-count"><?= (int)$stayCounts['all_cnt'] ?></span>
          </a>
          <a href="check-in.php?tab=arrivals" class="staff-filter-pill<?= $tab === 'arrivals' ? ' active' : '' ?>">
            Ready for Check-in <span class="pill-count"><?= (int)$stayCounts['arrivals_cnt'] ?></span>
          </a>
          <a href="check-in.php?tab=inhouse" class="staff-filter-pill<?= $tab === 'inhouse' ? ' active' : '' ?>">
            Currently Checked-in <span class="pill-count"><?= (int)$stayCounts['inhouse_cnt'] ?></span>
          </a>
        </div>

        <div style="min-width:260px;">
          <input type="text" id="checkInSearchInput" data-table-search data-table-target="#checkInTable" class="form-control" placeholder="Search by code, customer, or room..." style="background:rgba(255,255,255,0.05);font-size:0.85rem;">
        </div>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table" id="checkInTable">
              <thead>
                <tr>
                  <th class="col-nowrap">Booking ID</th>
                  <th>Customer / Contact</th>
                  <th class="col-nowrap">ID / KYC Status</th>
                  <th class="col-nowrap">Room</th>
                  <th class="col-nowrap">Stay Dates</th>
                  <th class="col-nowrap">Payment &amp; Balance</th>
                  <th class="col-nowrap">Status</th>
                  <th class="col-nowrap">Frontdesk Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($activeStays)): ?>
                  <tr data-empty-row><td colspan="8" class="text-center" style="padding:30px;color:var(--text-muted);">No active bookings match this view right now.</td></tr>
                <?php else: ?>
                  <?php foreach ($activeStays as $st):
                    $kycStatus = $st['verification_status'] ?? ($st['profile_verified'] ? 'verified' : 'unverified');
                    $totalAmount = (float)$st['total_amount'];
                    $totalPaid   = (float)$st['total_paid'];
                    $balanceDue  = max(0, $totalAmount - $totalPaid);
                    $roomDirty   = ($st['room_status'] === 'dirty');
                    $roomMaint   = ($st['room_status'] === 'maintenance');
                  ?>
                    <tr>
                      <td class="col-nowrap"><strong>#<?= h($st['booking_code']) ?></strong></td>
                      <td>
                        <strong><?= h(formatFullName($st)) ?></strong>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                          <i class="fa-solid fa-phone"></i> <?= h($st['phone'] ?: 'N/A') ?>
                        </div>
                      </td>
                      <td class="col-nowrap">
                        <?php if ($kycStatus === 'verified'): ?>
                          <span class="badge" style="background:#22c55e;color:#fff;font-size:0.75rem;"><i class="fa-solid fa-shield-check"></i> Verified ID</span>
                          <?php if (!empty($st['id_type'])): ?>
                            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;"><?= h($st['id_type']) ?></div>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="badge" style="background:#eab308;color:#000;font-size:0.75rem;"><i class="fa-solid fa-triangle-exclamation"></i> Check Physical ID</span>
                          <div style="font-size:0.72rem;color:#fde047;margin-top:2px;">Request card upon arrival</div>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap">
                        <div>Room <?= h($st['room_number']) ?> (<?= h($st['room_name']) ?>)</div>
                        <?php if ($roomMaint): ?>
                          <div style="font-size:0.72rem;color:#f87171;margin-top:2px;"><i class="fa-solid fa-wrench"></i> Room Under Maintenance</div>
                        <?php elseif ($roomDirty && $st['status'] === 'confirmed'): ?>
                          <div style="font-size:0.72rem;color:#facc15;margin-top:2px;"><i class="fa-solid fa-broom"></i> Needs Cleaning</div>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap"><?= formatDate($st['check_in']) ?> &rarr; <?= formatDate($st['check_out']) ?></td>
                      <td class="col-nowrap">
                        <?php if ($st['payment_status'] === 'verified'): ?>
                          <span class="badge" style="background:#22c55e;color:#fff;font-size:0.75rem;"><i class="fa-solid fa-circle-check"></i> Paid (<?= h(strtoupper($st['payment_method'] ?? 'ONLINE')) ?>)</span>
                          <?php if ($balanceDue > 0.01): ?>
                            <div style="font-size:0.72rem;color:#facc15;margin-top:3px;">Balance: <?= formatCurrency($balanceDue) ?> (of <?= formatCurrency($totalAmount) ?>)</div>
                          <?php endif; ?>
                          <?php if (!empty($st['payment_verifier_role'])): ?>
                            <div style="font-size:0.72rem;margin-top:4px;">
                              <?php if ($st['payment_verifier_role'] === 'admin'): ?>
                                <span style="color:#fde047;font-weight:600;"><i class="fa-solid fa-crown" style="color:var(--gold);"></i> Admin Verified</span>
                              <?php else: ?>
                                <span style="color:#93c5fd;font-weight:600;"><i class="fa-solid fa-user-shield"></i> Staff Verified</span>
                              <?php endif; ?>
                              <?php if (!empty($st['payment_verifier_name']) && trim($st['payment_verifier_name']) !== ''): ?>
                                <span style="color:var(--text-muted);">(<?= h(trim($st['payment_verifier_name'])) ?>)</span>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;">
                            <i class="fa-solid fa-clock"></i> <?= formatDate($st['payment_verified_at'] ?? $st['payment_created_at'], 'M d, Y h:i A') ?>
                          </div>
                        <?php elseif ($st['payment_status'] === 'pending'): ?>
                          <span class="badge" style="background:#eab308;color:#000;font-size:0.75rem;"><i class="fa-solid fa-hourglass-half"></i> Pending Verification</span>
                          <div style="font-size:0.72rem;color:#fde047;margin-top:3px;">
                            <i class="fa-solid fa-clock"></i> <?= formatDate($st['payment_created_at'], 'M d, Y h:i A') ?>
                          </div>
                        <?php else: ?>
                          <span class="badge" style="background:rgba(239,68,68,0.2);color:#ef4444;font-size:0.75rem;"><i class="fa-solid fa-circle-exclamation"></i> Unpaid (<?= formatCurrency($totalAmount) ?>)</span>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap"><?= statusBadge($st['status']) ?></td>
                      <td class="col-nowrap">
                        <form action="check-in.php<?= h($buildQs()) ?>" method="POST"
                              class="staff-check-form"
                              data-dirty-room="<?= $roomDirty ? '1' : '0' ?>"
                              data-room-label="#<?= h($st['room_number']) ?>"
                              data-booking-code="<?= h($st['booking_code']) ?>"
                              style="display:inline-flex;flex-direction:column;gap:6px;align-items:flex-start;">
                          <?= csrfField() ?>
                          <input type="hidden" name="booking_id" value="<?= (int)$st['id'] ?>">
                          <input type="hidden" name="action_guest" value="1">
                          <?php if ($st['status'] === 'confirmed'): ?>
                            <?php if ($roomMaint): ?>
                              <span class="badge badge-danger" title="Update Room Status before checking in"><i class="fa-solid fa-ban"></i> Room Blocked (Maint.)</span>
                            <?php else: ?>
                              <?php if ($st['payment_status'] !== 'verified'): ?>
                                <label style="font-size:0.72rem;color:#fde047;display:flex;align-items:center;gap:4px;cursor:pointer;">
                                  <input type="checkbox" name="confirm_unpaid_checkin" value="1"> Desk Override (Unpaid)
                                </label>
                              <?php endif; ?>
                              <button type="submit" name="action_type" value="check_in" class="btn btn-primary btn-sm"><i class="fa-solid fa-key"></i> Check In</button>
                            <?php endif; ?>
                          <?php elseif ($st['status'] === 'checked_in'): ?>
                            <button type="submit" name="action_type" value="check_out" class="btn btn-warning btn-sm"><i class="fa-solid fa-door-closed"></i> Check Out</button>
                          <?php endif; ?>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr data-no-match-row style="display:none;"><td colspan="8" class="text-center" style="padding:28px;color:var(--text-muted);">No rows match your search filter.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($totalPages > 1): ?>
            <div class="staff-pagination">
              <span>Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalForTab ?> booking(s))</span>
              <div class="staff-pagination-links">
                <?php if ($page > 1): ?>
                  <a href="check-in.php?<?= http_build_query(array_filter(['tab' => $tab !== 'all' ? $tab : null, 'page' => $page - 1])) ?>" class="btn btn-ghost btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                  <a href="check-in.php?<?= http_build_query(array_filter(['tab' => $tab !== 'all' ? $tab : null, 'page' => $page + 1])) ?>" class="btn btn-ghost btn-sm">Next &raquo;</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/staff.js')) ?>"></script>
</body>
</html>
