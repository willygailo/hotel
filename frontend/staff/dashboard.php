<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('staff');
releaseReadOnlySession();

// Throttled periodic housekeeping: cancel stale unpaid bookings older than 24 hours
throttleStaleBookingSweep(24, 300);

$staffId = currentUserId();
ensureTempCredentialSchema();
$staff = dbRow("SELECT * FROM users WHERE id = ?", [$staffId]);

$ppage = max(1, (int)($_GET['ppage'] ?? 1));
$bpage = max(1, (int)($_GET['bpage'] ?? 1));
$payPerPage = 10;
$bkPerPage  = 15;

$buildDashQs = function () use ($ppage, $bpage): string {
    $p = [];
    if ($ppage > 1) $p['ppage'] = $ppage;
    if ($bpage > 1) $p['bpage'] = $bpage;
    return !empty($p) ? ('?' . http_build_query($p)) : '';
};

// Action: Accept / Decline pending transactions (Atomic updates + Guest Notifications)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_booking'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $action = $_POST['action_type'] ?? ''; // 'accept' or 'decline'

        $bk = dbRow("
            SELECT b.*, v.first_name, v.last_name, v.role
            FROM bookings b
            LEFT JOIN users v ON b.accepted_by = v.id
            WHERE b.id = ?
        ", [$bookingId]);

        if (!$bk) {
            setFlash('error', 'Booking record not found.');
        } else {
            $vName = !empty($bk['first_name']) ? trim($bk['first_name'] . ' ' . ($bk['last_name'] ?? '')) : 'Staff/Admin';
            $vRole = (($bk['role'] ?? '') === 'admin') ? 'Super Admin' : 'Frontdesk Staff';

            if ($bk['status'] === 'confirmed') {
                setFlash('info', "Already Confirmed: Booking #{$bk['booking_code']} was already approved by $vRole ($vName). Auto-synchronized!");
            } elseif ($bk['status'] !== 'pending') {
                setFlash('warning', "Booking #{$bk['booking_code']} cannot be modified because it is already " . strtoupper($bk['status']) . '.');
            } elseif ($action === 'accept') {
                $conflict = findConflictingBooking((int)$bk['room_id'], $bk['check_in'], $bk['check_out'], $bookingId);
                if ($conflict) {
                    setFlash('error', "Cannot confirm Booking #{$bk['booking_code']}: overlaps with Booking #{$conflict['booking_code']} (" . formatDate($conflict['check_in'], 'M d') . " - " . formatDate($conflict['check_out'], 'M d') . ").");
                } else {
                    $updated = dbExec(
                        "UPDATE bookings SET status = 'confirmed', accepted_by = ?, accepted_at = NOW(), cancelled_reason = NULL WHERE id = ? AND status = 'pending'",
                        [$staffId, $bookingId]
                    );
                    if ($updated > 0) {
                        auditLog('Staff Accepted Booking', 'Staff Module', "Booking #{$bk['booking_code']} approved by Staff ID $staffId");
                        createNotification(
                            (int)$bk['user_id'],
                            'Reservation Confirmed',
                            "Your booking (#{$bk['booking_code']}) has been approved and confirmed by the Frontdesk team.",
                            'booking',
                            'my-bookings.php'
                        );
                        setFlash('success', "Booking #{$bk['booking_code']} confirmed successfully.");
                    } else {
                        setFlash('info', "Booking #{$bk['booking_code']} was already processed by another team member.");
                    }
                }
            } elseif ($action === 'decline') {
                $declineReason = trim($_POST['decline_reason'] ?? '');
                if ($declineReason === '') {
                    $declineReason = 'Denied by Frontdesk Staff';
                }
                $updated = dbExec(
                    "UPDATE bookings SET status = 'rejected', cancelled_at = NOW(), cancelled_reason = ? WHERE id = ? AND status = 'pending'",
                    [$declineReason, $bookingId]
                );
                if ($updated > 0) {
                    auditLog('Staff Rejected Booking', 'Staff Module', "Booking #{$bk['booking_code']} rejected by Staff ID $staffId. Reason: {$declineReason}");
                    createNotification(
                        (int)$bk['user_id'],
                        'Booking Declined',
                        "Your reservation #{$bk['booking_code']} was declined: {$declineReason}",
                        'alert',
                        'my-bookings.php'
                    );
                    setFlash('warning', "Booking #{$bk['booking_code']} has been declined.");
                } else {
                    setFlash('info', "Booking #{$bk['booking_code']} was already processed by another team member.");
                }
            }
        }
    }
    header("Location: dashboard.php" . $buildDashQs());
    exit;
}

// Action: Quick Payment Approval / Rejection (Delegated Frontdesk Power)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_payment'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $payId = (int)($_POST['payment_id'] ?? 0);
        $act = $_POST['action_type'] ?? ''; // 'verify' or 'reject'
        $res = processPaymentDecision($payId, $act, $staffId, 'Staff');
        setFlash($res['flash_type'], $res['message']);
    }
    header("Location: dashboard.php" . $buildDashQs());
    exit;
}

// Stats (exposed to $GLOBALS for shared sidebar badge rendering)
$pendingBookings = (int)(dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'pending'")['cnt'] ?? 0);
$pendingPayments = (int)(dbRow("SELECT COUNT(*) as cnt FROM payments WHERE status = 'pending'")['cnt'] ?? 0);
$todayCheckIns   = (int)(dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE check_in = CURDATE() AND status = 'confirmed'")['cnt'] ?? 0);
$occupiedRooms   = (int)(dbRow("SELECT COUNT(*) as cnt FROM rooms WHERE status = 'occupied'")['cnt'] ?? 0);

$GLOBALS['pendingBookings'] = $pendingBookings;
$GLOBALS['pendingPayments'] = $pendingPayments;

// Paginated Pending Payments queue (Frontdesk Approval)
$totalPayPages = max(1, (int)ceil($pendingPayments / $payPerPage));
if ($ppage > $totalPayPages) $ppage = $totalPayPages;
$payOffset = ($ppage - 1) * $payPerPage;

$pendingPaymentsList = dbRows("
    SELECT p.*, b.booking_code, u.first_name, u.last_name, u.phone, r.name as room_name, r.room_number
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON p.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    WHERE p.status = 'pending'
    ORDER BY p.id ASC
    LIMIT {$payPerPage} OFFSET {$payOffset}
");

// Paginated Pending Bookings queue (removes hidden LIMIT 10 ceiling)
$totalBkPages = max(1, (int)ceil($pendingBookings / $bkPerPage));
if ($bpage > $totalBkPages) $bpage = $totalBkPages;
$bkOffset = ($bpage - 1) * $bkPerPage;

$pendingList = dbRows("
    SELECT b.*, u.first_name, u.last_name, u.phone, r.name as room_name, r.room_number
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    WHERE b.status = 'pending'
    ORDER BY b.id ASC
    LIMIT {$bkPerPage} OFFSET {$bkOffset}
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard — Marvelous Hotel</title>
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
        <div class="topbar-title">Frontdesk Staff Operations</div>
      </div>
      <div class="topbar-actions">
        <a href="account.php" class="badge badge-info" style="text-decoration:none;"><i class="fa-solid fa-shield"></i> Staff Account</a>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <?php if (!empty($staff['password_is_temp'])): ?>
        <div class="flash-message flash-warning" style="margin-bottom:18px;">
          <i class="fa-solid fa-key"></i>
          <span>Kasalukuyang <strong>temporary password</strong> ang gamit mo. Pumunta sa <a href="account.php">Account &amp; Password</a> upang mag-set ng sariling password.</span>
        </div>
      <?php endif; ?>

      <div class="desktop-workstation-note">
        <i class="fa-solid fa-desktop"></i>
        <span><strong>Workstation Mode:</strong> Ang Staff Frontdesk Portal ay dinisenyo para sa Laptop at Desktop PC screens (Chrome, Edge, Firefox) para sa mabilisang arrivals, check-in, at payment verification.</span>
      </div>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Frontdesk Operations Desk</h1>
          <div class="page-header-sub">Confirm incoming bookings and verify guest payments while Admin is away.</div>
        </div>
      </div>

      <!-- Quick Metrics -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:28px;">
        <div class="stat-card">
          <div class="stat-icon red"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $pendingBookings ?></div>
            <div class="stat-label">Pending Bookings</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(234,179,8,0.15);color:#eab308;border:1px solid rgba(234,179,8,0.3);"><i class="fa-solid fa-receipt"></i></div>
          <div class="stat-info">
            <div class="stat-value" style="color:#eab308;"><?= $pendingPayments ?></div>
            <div class="stat-label">Payments Awaiting Verification</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fa-solid fa-person-walking-luggage"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $todayCheckIns ?></div>
            <div class="stat-label">Expected Check-ins Today</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon gold"><i class="fa-solid fa-key"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $occupiedRooms ?></div>
            <div class="stat-label">Currently Occupied Rooms</div>
          </div>
        </div>
      </div>

      <!-- Marvi Staff AI Co-Pilot Shift Briefing -->
      <div class="glass-card" style="padding:22px;margin-bottom:28px;border:1px solid rgba(78,154,241,0.3);background:linear-gradient(135deg, rgba(8,13,36,0.95), rgba(16,26,60,0.9));">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:12px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;">
              <i class="fa-solid fa-robot"></i>
            </div>
            <div>
              <div style="font-weight:700;color:#fff;font-size:1.05rem;">Marvi AI — Frontdesk Operations Co-Pilot</div>
              <div style="font-size:0.75rem;color:var(--accent);">Automated Shift Operations &amp; Room Turnover Intelligence</div>
            </div>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" id="btnRefreshAiShift" onclick="loadStaffAiBriefing(true)" style="font-size:0.78rem;">
            <i class="fa-solid fa-arrows-rotate"></i> Refresh Shift Briefing
          </button>
        </div>

        <div id="staffAiLoading" style="padding:20px;text-align:center;color:var(--text-muted);font-size:0.88rem;">
          <i class="fa-solid fa-circle-notch fa-spin" style="color:var(--accent);margin-right:8px;"></i> Pinoproseso ang live shift briefing mula sa AI engine...
        </div>

        <div id="staffAiContent" style="display:none;font-size:0.88rem;line-height:1.7;color:#e2e8f0;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:10px;padding:16px;">
        </div>
      </div>

      <!-- Actionable Pending Payments (Frontdesk Delegation when Admin is away) -->
      <div class="card" id="pending-payments" style="margin-bottom:28px;border:1px solid rgba(234,179,8,0.3);">
        <div class="card-header" style="background:rgba(234,179,8,0.06);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
          <h2 class="card-title" style="margin:0;"><i class="fa-solid fa-receipt" style="color:#eab308;margin-right:8px;"></i> Payments Awaiting Frontdesk Verification (<?= $pendingPayments ?>)</h2>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="text" data-table-search data-table-target="#dashPendingPaymentsTable" class="form-control" placeholder="Filter payments..." style="width:200px;padding:6px 12px;font-size:0.8rem;background:rgba(255,255,255,0.05);">
            <a href="payments.php?status=pending" class="btn btn-ghost btn-sm" style="color:var(--gold);">Open Full Payments &rarr;</a>
          </div>
        </div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table" id="dashPendingPaymentsTable">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Customer &amp; Contact</th>
                  <th>Room</th>
                  <th>Channel</th>
                  <th>Amount</th>
                  <th>Proof / Ref #</th>
                  <th>Date &amp; Time Paid</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pendingPaymentsList)): ?>
                  <tr data-empty-row>
                    <td colspan="8" class="text-center" style="padding:28px;color:var(--text-muted);">
                      <i class="fa-solid fa-circle-check" style="color:#4ade80;margin-right:6px;"></i> No pending payment receipts right now. All settled!
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($pendingPaymentsList as $pay): ?>
                    <tr>
                      <td><strong>#<?= h($pay['booking_code']) ?></strong></td>
                      <td>
                        <strong><?= h(formatFullName($pay)) ?></strong><br>
                        <small style="color:var(--text-muted);"><i class="fa-solid fa-phone"></i> <?= h($pay['phone'] ?: 'N/A') ?></small>
                      </td>
                      <td>Room <?= h($pay['room_number']) ?></td>
                      <td><span style="text-transform:uppercase;font-weight:600;color:#60a5fa;"><?= h($pay['method']) ?></span></td>
                      <td style="color:var(--gold);font-weight:700;"><?= formatCurrency($pay['amount']) ?></td>
                      <td>
                        <div><code><?= h($pay['reference_number'] ?: 'No Ref') ?></code></div>
                        <?php if ($pay['proof_image']): ?>
                          <a href="<?= h(getUploadUrl($pay['proof_image'])) ?>" target="_blank" style="color:var(--accent);font-size:0.75rem;"><i class="fa-solid fa-image"></i> View Receipt</a>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="font-weight:600;color:#fff;"><?= formatDate($pay['created_at'], 'M d, Y') ?></div>
                        <div style="font-size:0.75rem;color:var(--gold);margin-top:2px;">
                          <i class="fa-solid fa-clock"></i> <?= formatDate($pay['created_at'], 'h:i:s A') ?>
                        </div>
                      </td>
                      <td>
                        <form action="dashboard.php<?= h($buildDashQs()) ?>" method="POST" style="display:inline-flex;gap:6px;">
                          <?= csrfField() ?>
                          <input type="hidden" name="payment_id" value="<?= (int)$pay['id'] ?>">
                          <input type="hidden" name="action_payment" value="1">
                          <button type="submit" name="action_type" value="verify" class="btn btn-success btn-sm" onclick="return confirm('Confirm and approve this payment receipt?')">
                            <i class="fa-solid fa-check"></i> Approve
                          </button>
                          <button type="submit" name="action_type" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Decline this payment receipt?')">
                            <i class="fa-solid fa-xmark"></i> Decline
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr data-no-match-row style="display:none;"><td colspan="8" class="text-center" style="padding:24px;color:var(--text-muted);">No payments match your filter.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPayPages > 1): ?>
            <div class="staff-pagination">
              <span>Payments Page <strong><?= $ppage ?></strong> of <strong><?= $totalPayPages ?></strong></span>
              <div class="staff-pagination-links">
                <?php if ($ppage > 1): ?>
                  <a href="dashboard.php?<?= http_build_query(array_filter(['ppage' => $ppage - 1, 'bpage' => $bpage > 1 ? $bpage : null])) ?>#pending-payments" class="btn btn-ghost btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <?php if ($ppage < $totalPayPages): ?>
                  <a href="dashboard.php?<?= http_build_query(array_filter(['ppage' => $ppage + 1, 'bpage' => $bpage > 1 ? $bpage : null])) ?>#pending-payments" class="btn btn-ghost btn-sm">Next &raquo;</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Actionable Pending Transactions -->
      <div class="card" id="pending-bookings">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
          <h2 class="card-title" style="margin:0;"><i class="fa-solid fa-bell" style="color:var(--gold);margin-right:8px;"></i> New Bookings Awaiting Approval (<?= $pendingBookings ?>)</h2>
          <input type="text" data-table-search data-table-target="#dashPendingBookingsTable" class="form-control" placeholder="Filter pending bookings..." style="width:220px;padding:6px 12px;font-size:0.8rem;background:rgba(255,255,255,0.05);">
        </div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table" id="dashPendingBookingsTable">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Customer</th>
                  <th>Contact</th>
                  <th>Room</th>
                  <th>Dates</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pendingList)): ?>
                  <tr data-empty-row>
                    <td colspan="7" class="text-center" style="padding:30px;color:var(--text-muted);">
                      No pending bookings right now. Everything has already been processed!
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($pendingList as $p): ?>
                    <tr>
                      <td><strong>#<?= h($p['booking_code']) ?></strong></td>
                      <td><?= h($p['first_name'] . ' ' . $p['last_name']) ?></td>
                      <td><?= h($p['phone'] ?: 'N/A') ?></td>
                      <td><?= h($p['room_name']) ?> <small style="color:var(--text-muted);">(#<?= h($p['room_number']) ?>)</small></td>
                      <td><?= formatDate($p['check_in']) ?> - <?= formatDate($p['check_out']) ?></td>
                      <td style="color:var(--gold);font-weight:600;"><?= formatCurrency($p['total_amount']) ?></td>
                      <td>
                        <div style="display:inline-flex;gap:6px;">
                          <form action="dashboard.php<?= h($buildDashQs()) ?>" method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="booking_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="action_booking" value="1">
                            <button type="submit" name="action_type" value="accept" class="btn btn-success btn-sm" onclick="return confirm('Confirm Booking #<?= h($p['booking_code']) ?>?')">
                              <i class="fa-solid fa-check"></i> Accept
                            </button>
                          </form>
                          <button type="button" class="btn btn-danger btn-sm" onclick="openStaffDeclineModal(<?= (int)$p['id'] ?>, <?= json_encode($p['booking_code'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)">
                            <i class="fa-solid fa-xmark"></i> Decline
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr data-no-match-row style="display:none;"><td colspan="7" class="text-center" style="padding:24px;color:var(--text-muted);">No bookings match your filter.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalBkPages > 1): ?>
            <div class="staff-pagination">
              <span>Bookings Page <strong><?= $bpage ?></strong> of <strong><?= $totalBkPages ?></strong></span>
              <div class="staff-pagination-links">
                <?php if ($bpage > 1): ?>
                  <a href="dashboard.php?<?= http_build_query(array_filter(['ppage' => $ppage > 1 ? $ppage : null, 'bpage' => $bpage - 1])) ?>#pending-bookings" class="btn btn-ghost btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <?php if ($bpage < $totalBkPages): ?>
                  <a href="dashboard.php?<?= http_build_query(array_filter(['ppage' => $ppage > 1 ? $ppage : null, 'bpage' => $bpage + 1])) ?>#pending-bookings" class="btn btn-ghost btn-sm">Next &raquo;</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Staff Decline Reason Modal -->
<div class="staff-modal-overlay" id="staffDeclineModal">
  <div class="staff-modal-box" style="max-width:500px;" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;margin-bottom:16px;">
      <h3 style="margin:0;color:#fff;font-size:1.1rem;"><i class="fa-solid fa-ban" style="color:var(--red);margin-right:8px;"></i> Decline Booking <span id="declineBookingCode" style="color:var(--gold);"></span></h3>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeStaffDeclineModal()" style="font-size:1.2rem;padding:4px 8px;">&times;</button>
    </div>

    <form action="dashboard.php<?= h($buildDashQs()) ?>" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action_booking" value="1">
      <input type="hidden" name="action_type" value="decline">
      <input type="hidden" name="booking_id" id="declineBookingId" value="">

      <div style="margin-bottom:10px;font-size:0.78rem;color:var(--text-muted);">Quick reason presets (optional):</div>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
        <button type="button" class="btn btn-ghost btn-sm" onclick="setDeclinePreset('Room unavailable due to urgent maintenance.')">Room Maintenance</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="setDeclinePreset('Schedule overlap with another confirmed stay.')">Schedule Overlap</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="setDeclinePreset('No valid payment or guest confirmation received.')">No Payment / Unverified</button>
      </div>

      <div class="form-group">
        <label class="form-label">Reason Sent to Guest (leave blank for default)</label>
        <textarea name="decline_reason" id="declineReasonInput" rows="3" class="form-control" placeholder="e.g. Room temporarily unavailable on requested dates..."></textarea>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost" onclick="closeStaffDeclineModal()">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Confirm Decline</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/staff.js')) ?>"></script>
<script>
function loadStaffAiBriefing(forceRefresh = false) {
  const loading = document.getElementById('staffAiLoading');
  const content = document.getElementById('staffAiContent');
  const btn = document.getElementById('btnRefreshAiShift');

  loading.style.display = 'block';
  content.style.display = 'none';
  if (btn) btn.disabled = true;

  fetch('../ai/staff-assistant.php' + (forceRefresh ? '?refresh=1' : ''))
    .then(res => res.json())
    .then(data => {
      loading.style.display = 'none';
      if (data.success && data.briefing) {
        content.innerHTML = data.briefing;
        content.style.display = 'block';
      } else {
        content.innerHTML = '<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Hindi ma-load ang briefing sa ngayon.</span>';
        content.style.display = 'block';
      }
    })
    .catch(() => {
      loading.style.display = 'none';
      content.innerHTML = '<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> May error sa pag-konekta sa AI server.</span>';
      content.style.display = 'block';
    })
    .finally(() => {
      if (btn) btn.disabled = false;
    });
}

document.addEventListener('DOMContentLoaded', () => {
  loadStaffAiBriefing();
});
</script>
</body>
</html>
