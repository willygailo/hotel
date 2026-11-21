<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('user');

$userId = currentUserId();
$bId = (int)($_GET['id'] ?? 0);

$booking = dbRow("
    SELECT b.*, r.name as room_name, r.room_number, r.price_per_night, rt.name as type_name, r.floor, r.capacity 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN room_types rt ON r.room_type_id = rt.id 
    WHERE b.id = ? AND b.user_id = ?
", [$bId, $userId]);

if (!$booking) {
    setFlash('error', 'The requested booking could not be found.');
    header("Location: my-bookings.php");
    exit;
}

releaseReadOnlySession();

$payments = dbRows("
    SELECT p.*, v.first_name as verifier_first_name, v.last_name as verifier_last_name, v.role as verifier_role 
    FROM payments p 
    LEFT JOIN users v ON p.verified_by = v.id 
    WHERE p.booking_id = ? 
    ORDER BY p.id DESC
", [$bId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking Details #<?= h($booking['booking_code']) ?> — Marvelous Hotel</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/user.css">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('user'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="user-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Booking Voucher #<?= h($booking['booking_code']) ?></div>
      </div>
    </header>

    <div class="page-content">
      <div style="margin-bottom:20px;">
        <a href="my-bookings.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to My Bookings</a>
      </div>

      <div class="glass-card" style="max-width:760px;padding:32px;">
        <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:16px;margin-bottom:24px;">
          <div>
            <h2 style="font-family:var(--font-serif);font-size:1.6rem;color:#fff;">Booking Voucher</h2>
            <div style="font-size:0.85rem;color:var(--text-muted);">Reference: <strong>#<?= h($booking['booking_code']) ?></strong></div>
          </div>
          <div><?= statusBadge($booking['status']) ?></div>
        </div>

        <div class="form-row-2col" style="gap:24px;margin-bottom:24px;">
          <div>
            <h4 style="color:var(--gold);font-size:0.85rem;text-transform:uppercase;margin-bottom:8px;">Room and Floor</h4>
            <div style="font-size:1.1rem;font-weight:700;color:#fff;"><?= h($booking['room_name']) ?></div>
            <div style="font-size:0.85rem;color:var(--text-muted);">Room #<?= h($booking['room_number']) ?> · Floor <?= $booking['floor'] ?> (<?= h($booking['type_name']) ?>)</div>
          </div>
          <div>
            <h4 style="color:var(--gold);font-size:0.85rem;text-transform:uppercase;margin-bottom:8px;">Stay Dates</h4>
            <div style="font-size:0.95rem;color:#fff;">Check-in: <strong><?= formatDate($booking['check_in']) ?></strong> (2:00 PM)</div>
            <div style="font-size:0.95rem;color:#fff;">Check-out: <strong><?= formatDate($booking['check_out']) ?></strong> (12:00 PM)</div>
            <div style="font-size:0.85rem;color:var(--text-muted);"><?= $booking['nights'] ?> Nights · <?= $booking['num_guests'] ?> Pax</div>
          </div>
        </div>

        <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:24px;">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
              <span style="color:var(--text-muted);font-size:0.85rem;">Total Room Amount:</span>
              <div style="color:var(--gold);font-size:1.4rem;font-weight:700;line-height:1.2;"><?= formatCurrency($booking['total_amount']) ?></div>
            </div>
            <div style="text-align:right;">
              <span style="color:var(--text-muted);font-size:0.8rem;">Selected Payment Method:</span>
              <div style="margin-top:4px;">
                <?php if (($booking['payment_method'] ?? '') === 'gcash'): ?>
                  <span class="badge" style="background:rgba(41,121,255,0.15);color:#64b5f6;border:1px solid rgba(41,121,255,0.35);font-size:0.75rem;padding:4px 10px;">
                    <i class="fa-solid fa-mobile-screen-button"></i> GCash Express
                  </span>
                <?php else: ?>
                  <span class="badge" style="background:rgba(0,210,106,0.15);color:#00e074;border:1px solid rgba(0,210,106,0.35);font-size:0.75rem;padding:4px 10px;">
                    <i class="fa-solid fa-qrcode"></i> PayMaya (Maya) QR
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Reservation Lifecycle Timestamps -->
        <div style="background:rgba(201,168,76,0.04);border:1px solid rgba(201,168,76,0.18);border-radius:12px;padding:20px;margin-bottom:24px;">
          <h4 style="color:var(--gold);font-size:0.85rem;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-clock-rotate-left"></i> Reservation Activity Timestamps
          </h4>
          <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;font-size:0.85rem;">
            <div>
              <div style="color:var(--text-muted);font-size:0.75rem;text-transform:uppercase;">Booked On</div>
              <div style="color:#fff;font-weight:600;margin-top:2px;"><?= formatDate($booking['created_at'], 'M d, Y') ?></div>
              <div style="color:var(--gold);font-size:0.75rem;"><?= formatDate($booking['created_at'], 'h:i A') ?></div>
            </div>

            <?php if (!empty($booking['accepted_at'])): ?>
              <div>
                <div style="color:#4ade80;font-size:0.75rem;text-transform:uppercase;"><i class="fa-solid fa-circle-check"></i> Approved</div>
                <div style="color:#fff;font-weight:600;margin-top:2px;"><?= formatDate($booking['accepted_at'], 'M d, Y') ?></div>
                <div style="color:var(--gold);font-size:0.75rem;"><?= formatDate($booking['accepted_at'], 'h:i A') ?></div>
              </div>
            <?php endif; ?>

            <?php if (!empty($booking['checked_in_at'])): ?>
              <div>
                <div style="color:#38bdf8;font-size:0.75rem;text-transform:uppercase;"><i class="fa-solid fa-key"></i> Checked In</div>
                <div style="color:#fff;font-weight:600;margin-top:2px;"><?= formatDate($booking['checked_in_at'], 'M d, Y') ?></div>
                <div style="color:var(--gold);font-size:0.75rem;"><?= formatDate($booking['checked_in_at'], 'h:i A') ?></div>
              </div>
            <?php endif; ?>

            <?php if (!empty($booking['checked_out_at'])): ?>
              <div>
                <div style="color:#a78bfa;font-size:0.75rem;text-transform:uppercase;"><i class="fa-solid fa-door-closed"></i> Checked Out</div>
                <div style="color:#fff;font-weight:600;margin-top:2px;"><?= formatDate($booking['checked_out_at'], 'M d, Y') ?></div>
                <div style="color:var(--gold);font-size:0.75rem;"><?= formatDate($booking['checked_out_at'], 'h:i A') ?></div>
              </div>
            <?php endif; ?>

            <?php if (!empty($booking['cancelled_at'])): ?>
              <div>
                <div style="color:#f87171;font-size:0.75rem;text-transform:uppercase;"><i class="fa-solid fa-ban"></i> Cancelled / Denied</div>
                <div style="color:#fff;font-weight:600;margin-top:2px;"><?= formatDate($booking['cancelled_at'], 'M d, Y') ?></div>
                <div style="color:var(--gold);font-size:0.75rem;"><?= formatDate($booking['cancelled_at'], 'h:i A') ?></div>
                <?php if (!empty($booking['cancelled_reason'])): ?>
                  <div style="color:#fca5a5;font-size:0.7rem;font-style:italic;margin-top:2px;">Reason: <?= h($booking['cancelled_reason']) ?></div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Payment Transactions History & Timestamps -->
        <div style="margin-top:24px;border-top:1px solid var(--border);padding-top:20px;">
          <h4 style="color:var(--gold);font-size:0.9rem;text-transform:uppercase;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-receipt"></i> Payment Records &amp; Timestamps
          </h4>

          <?php if (empty($payments)): ?>
            <div style="background:rgba(255,255,255,0.02);border:1px dashed var(--border);border-radius:10px;padding:16px;text-align:center;color:var(--text-muted);font-size:0.85rem;">
              No payment transactions submitted for this booking yet.
            </div>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:12px;">
              <?php foreach ($payments as $p): ?>
                <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;padding:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                  <div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                      <strong style="color:#fff;font-size:1.05rem;"><?= formatCurrency($p['amount']) ?></strong>
                      <span class="badge" style="text-transform:uppercase;font-size:0.7rem;background:rgba(201,168,76,0.15);color:var(--gold);border:1px solid rgba(201,168,76,0.3);"><?= h($p['method']) ?></span>
                      <?= statusBadge($p['status']) ?>
                    </div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">
                      Ref #: <code style="color:var(--gold);"><?= h($p['reference_number'] ?: 'N/A') ?></code>
                    </div>
                    <div style="font-size:0.8rem;color:#cbd5e1;margin-top:4px;">
                      <i class="fa-solid fa-calendar-day" style="color:var(--gold);margin-right:4px;"></i>
                      Paid on: <strong><?= formatDate($p['created_at'], 'F d, Y') ?></strong> at <strong><?= formatDate($p['created_at'], 'h:i:s A') ?></strong>
                    </div>
                    <?php if (!empty($p['verified_at']) && $p['status'] === 'verified'): ?>
                      <div style="font-size:0.75rem;color:#4ade80;margin-top:3px;">
                        <i class="fa-solid fa-circle-check"></i>
                        <?php if ($p['verifier_role'] === 'admin'): ?>
                          Verified by Super Admin (<?= h(trim($p['verifier_first_name'] . ' ' . $p['verifier_last_name']) ?: 'Management') ?>)
                        <?php elseif ($p['verifier_role'] === 'staff'): ?>
                          Verified by Frontdesk Staff (<?= h(trim($p['verifier_first_name'] . ' ' . $p['verifier_last_name']) ?: 'Frontdesk') ?>)
                        <?php else: ?>
                          Verified by Hotel Administration
                        <?php endif; ?>
                        on <?= formatDate($p['verified_at'], 'F d, Y \a\t h:i A') ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div>
                    <?php if ($p['proof_image']): ?>
                      <a href="<?= getUploadUrl($p['proof_image']) ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-size:0.75rem;">
                        <i class="fa-solid fa-image"></i> View Receipt
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($booking['status'] === 'pending'): ?>
          <div style="text-align:right;margin-top:20px;">
            <a href="payment.php?booking_id=<?= $booking['id'] ?>" class="btn btn-gold"><i class="fa-solid fa-credit-card"></i> Upload Payment</a>
          </div>
        <?php elseif (in_array($booking['status'], ['confirmed', 'checked_in', 'checked_out'])): ?>
          <div style="text-align:right;margin-top:20px;">
            <a href="reviews.php?booking_id=<?= $booking['id'] ?>" class="btn btn-gold"><i class="fa-solid fa-star"></i> Rate This Stay &amp; Room</a>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/user.js"></script>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
</body>
</html>
