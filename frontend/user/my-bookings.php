<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('user');
releaseReadOnlySession();

$userId = currentUserId();

// Handle cancellation (pending bookings only; confirmed bookings require Frontdesk/Admin review)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $bookingId = (int)$_POST['booking_id'];
        $bk = dbRow("SELECT * FROM bookings WHERE id = ? AND user_id = ?", [$bookingId, $userId]);
        if ($bk) {
            if ($bk['status'] === 'pending') {
                dbExec("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), cancelled_reason = 'Cancelled by User' WHERE id = ? AND status = 'pending'", [$bookingId]);
                rejectPendingPaymentsForBooking($bookingId, 'Auto-rejected: booking cancelled by guest');
                auditLog('Cancelled Booking', 'User Module', "Booking #{$bk['booking_code']} cancelled by user");
                setFlash('warning', 'Your pending booking has been cancelled.');
            } elseif ($bk['status'] === 'confirmed') {
                setFlash('info', 'Confirmed bookings cannot be self-cancelled online. Please contact Frontdesk or Administration for cancellation & refund assistance.');
            }
        }
    }
    header("Location: my-bookings.php");
    exit;
}

$bookings = dbRows("
    SELECT b.*, r.name as room_name, r.room_number, r.thumbnail, r.price_per_night, rt.name as type_name,
           lp.status as latest_payment_status
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN room_types rt ON r.room_type_id = rt.id 
    LEFT JOIN (
        SELECT p1.booking_id, p1.status
        FROM payments p1
        INNER JOIN (
            SELECT booking_id, MAX(id) AS max_id
            FROM payments
            GROUP BY booking_id
        ) pm ON p1.id = pm.max_id
    ) lp ON lp.booking_id = b.id
    WHERE b.user_id = ? 
    ORDER BY b.id DESC
", [$userId]);
$defaultRoomImg = ASSETS_URL . '/images/rooms/default-room.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Bookings — Marvelous Hotel</title>
  <?php require_once __DIR__ . '/../components/page-head.php'; renderPerformanceHeadLinks(true); ?>
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/user.css')) ?>">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('user'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="user-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Manage Your Stays</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">All My Bookings</h1>
          <div class="page-header-sub">Review your past and upcoming hotel reservations.</div>
        </div>
        <a href="../rooms.php" class="btn btn-gold btn-sm"><i class="fa-solid fa-door-open"></i> Browse Rooms</a>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <!-- Desktop Table (Screen >= 768px) -->
          <div class="table-wrap desktop-bookings-table">
            <table class="table">
              <thead>
                <tr>
                  <th class="col-nowrap">Booking ID</th>
                  <th>Room / Type</th>
                  <th class="col-nowrap">Check-In & Check-Out</th>
                  <th class="col-nowrap">Nights</th>
                  <th class="col-amount">Total Payment</th>
                  <th class="col-nowrap">Status</th>
                  <th class="col-nowrap">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($bookings)): ?>
                  <tr>
                    <td colspan="7" class="text-center" style="padding:30px;color:var(--text-muted);">No bookings found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($bookings as $b): ?>
                    <tr>
                      <td class="col-nowrap"><strong><?= h($b['booking_code']) ?></strong></td>
                      <td>
                        <div class="booking-room-cell">
                          <img src="<?= h(roomThumbUrl($b['thumbnail'] ?? null)) ?>"
                               alt="<?= h($b['room_name']) ?>"
                               class="booking-thumb-sm"
                               loading="lazy"
                               decoding="async"
                               onerror="this.src='<?= h($defaultRoomImg) ?>';this.onerror=null;">
                          <div>
                            <div style="font-weight:600;color:#fff;"><?= h($b['room_name']) ?></div>
                            <div style="display:flex;align-items:center;gap:6px;margin-top:2px;">
                              <span class="badge badge-gold" style="font-size:0.65rem;"><?= h($b['type_name']) ?></span>
                              <?php if (!empty($b['room_number'])): ?>
                                <span style="font-size:0.72rem;color:var(--text-muted);">#<?= h($b['room_number']) ?></span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td class="col-nowrap" style="white-space:nowrap;"><?= formatDate($b['check_in']) ?> &rarr; <?= formatDate($b['check_out']) ?></td>
                      <td class="col-nowrap"><?= $b['nights'] ?> nights</td>
                      <td class="col-amount">
                        <div style="color:var(--gold);font-weight:700;white-space:nowrap;"><?= formatCurrency($b['total_amount']) ?></div>
                        <div style="margin-top:3px;">
                          <?php if (($b['payment_method'] ?? '') === 'gcash'): ?>
                            <span class="badge" style="background:rgba(41,121,255,0.15);color:#64b5f6;border:1px solid rgba(41,121,255,0.3);font-size:0.68rem;padding:2px 6px;">
                              <i class="fa-solid fa-mobile-screen-button"></i> GCash
                            </span>
                          <?php else: ?>
                            <span class="badge" style="background:rgba(0,210,106,0.15);color:#00e074;border:1px solid rgba(0,210,106,0.3);font-size:0.68rem;padding:2px 6px;">
                              <i class="fa-solid fa-qrcode"></i> Maya
                            </span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="col-nowrap"><?= statusBadge($b['status']) ?></td>
                      <td class="col-nowrap">
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                          <a href="booking-detail.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm" title="View Booking Voucher">
                            <i class="fa-solid fa-file-invoice"></i> Voucher
                          </a>
                          <?php if (in_array($b['status'], ['confirmed', 'checked_in', 'checked_out'])): ?>
                            <a href="reviews.php?booking_id=<?= $b['id'] ?>" class="btn btn-gold btn-sm" title="Rate this Stay">
                              <i class="fa-solid fa-star"></i> Rate Stay
                            </a>
                          <?php endif; ?>
                          <?php if ($b['status'] === 'pending'): ?>
                            <?php if (in_array($b['latest_payment_status'] ?? '', ['pending', 'verified'], true)): ?>
                              <a href="payment-history.php" class="btn btn-ghost btn-sm" title="View Submitted Payment Receipt" style="border:1px solid rgba(234,179,8,0.35);color:#fde047;">
                                <i class="fa-solid fa-hourglass-half"></i> Payment Pending
                              </a>
                            <?php else: ?>
                              <a href="payment.php?booking_id=<?= $b['id'] ?>" class="btn btn-gold btn-sm">
                                <i class="fa-solid fa-credit-card"></i> Pay Now
                              </a>
                            <?php endif; ?>
                          <?php endif; ?>
                          <?php if ($b['status'] === 'pending'): ?>
                            <form action="" method="POST" onsubmit="return confirm('Cancel this pending booking?')">
                              <?= csrfField() ?>
                              <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                              <input type="hidden" name="cancel_booking" value="1">
                              <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Mobile Cards (Screen < 768px) -->
          <div class="mobile-bookings-list">
            <?php if (empty($bookings)): ?>
              <div class="text-center" style="padding:30px 15px;color:var(--text-muted);">
                <i class="fa-solid fa-calendar-xmark" style="font-size:2.5rem;opacity:0.4;margin-bottom:10px;display:block;"></i>
                No bookings found.
              </div>
            <?php else: ?>
              <?php foreach ($bookings as $b): ?>
                <div class="booking-mobile-card">
                  <div class="bmc-header">
                    <div>
                      <div class="bmc-code"><?= h($b['booking_code']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                        <?= $b['nights'] ?> night(s) stay
                      </div>
                    </div>
                    <div>
                      <?= statusBadge($b['status']) ?>
                    </div>
                  </div>

                  <div class="bmc-body">
                    <div class="bmc-room-strip">
                      <img src="<?= h(roomThumbUrl($b['thumbnail'] ?? null)) ?>"
                           alt="<?= h($b['room_name']) ?>"
                           loading="lazy"
                           decoding="async"
                           onerror="this.src='<?= h($defaultRoomImg) ?>';this.onerror=null;">
                      <div style="min-width:0;">
                        <div style="font-weight:700;color:#fff;font-size:0.9rem;line-height:1.25;"><?= h($b['room_name']) ?></div>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:3px;flex-wrap:wrap;">
                          <span class="badge badge-gold" style="font-size:0.65rem;"><?= h($b['type_name']) ?></span>
                          <?php if (!empty($b['room_number'])): ?>
                            <span style="font-size:0.72rem;color:var(--text-muted);">Room #<?= h($b['room_number']) ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>

                    <div class="bmc-row">
                      <span class="bmc-label"><i class="fa-solid fa-calendar-day"></i> Stay Dates:</span>
                      <span class="bmc-value" style="font-size:0.82rem;">
                        <?= formatDate($b['check_in']) ?> &rarr; <?= formatDate($b['check_out']) ?>
                      </span>
                    </div>

                    <div class="bmc-row" style="align-items:center;">
                      <span class="bmc-label"><i class="fa-solid fa-wallet"></i> Total Payment:</span>
                      <div style="text-align:right;">
                        <span class="bmc-price"><?= formatCurrency($b['total_amount']) ?></span>
                        <div style="margin-top:2px;">
                          <?php if (($b['payment_method'] ?? '') === 'gcash'): ?>
                            <span class="badge" style="background:rgba(41,121,255,0.15);color:#64b5f6;border:1px solid rgba(41,121,255,0.3);font-size:0.68rem;padding:2px 6px;">
                              <i class="fa-solid fa-mobile-screen-button"></i> GCash
                            </span>
                          <?php else: ?>
                            <span class="badge" style="background:rgba(0,210,106,0.15);color:#00e074;border:1px solid rgba(0,210,106,0.3);font-size:0.68rem;padding:2px 6px;">
                              <i class="fa-solid fa-qrcode"></i> Maya
                            </span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="bmc-actions">
                    <a href="booking-detail.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm">
                      <i class="fa-solid fa-file-invoice"></i> Voucher
                    </a>

                    <?php if (in_array($b['status'], ['confirmed', 'checked_in', 'checked_out'])): ?>
                      <a href="reviews.php?booking_id=<?= $b['id'] ?>" class="btn btn-gold btn-sm">
                        <i class="fa-solid fa-star"></i> Rate Stay
                      </a>
                    <?php endif; ?>

                    <?php if ($b['status'] === 'pending'): ?>
                      <?php if (in_array($b['latest_payment_status'] ?? '', ['pending', 'verified'], true)): ?>
                        <a href="payment-history.php" class="btn btn-ghost btn-sm" style="border:1px solid rgba(234,179,8,0.35);color:#fde047;">
                          <i class="fa-solid fa-hourglass-half"></i> Payment Pending
                        </a>
                      <?php else: ?>
                        <a href="payment.php?booking_id=<?= $b['id'] ?>" class="btn btn-gold btn-sm">
                          <i class="fa-solid fa-credit-card"></i> Pay Now
                        </a>
                      <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($b['status'] === 'pending'): ?>
                      <form action="" method="POST" onsubmit="return confirm('Cancel this pending booking?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <input type="hidden" name="cancel_booking" value="1">
                        <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                          <i class="fa-solid fa-xmark"></i> Cancel
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script defer src="<?= h(assetUrl('js/user.js')) ?>"></script>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
</body>
</html>
