<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('user');
releaseReadOnlySession();

$userId = currentUserId();
$payments = dbRows("
    SELECT p.*, b.booking_code, r.name as room_name 
    FROM payments p 
    JOIN bookings b ON p.booking_id = b.id 
    JOIN rooms r ON b.room_id = r.id 
    WHERE p.user_id = ? 
    ORDER BY p.id DESC
", [$userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment History — Marvelous Hotel</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/user.css">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
  <!-- Sidebar -->
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('user'); ?>
  <!-- Main Panel -->
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="user-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Transaction and Receipt History</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">My Payments</h1>
          <div class="page-header-sub">A record of all your uploaded receipts and their verification status.</div>
        </div>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <!-- Desktop Table (Screen >= 768px: Laptop / PC) -->
          <div class="table-wrap desktop-bookings-table">
            <table class="table">
              <thead>
                <tr>
                  <th>Booking Code</th>
                  <th>Room</th>
                  <th>Method</th>
                  <th>Amount</th>
                  <th>Reference #</th>
                  <th>Date, Year &amp; Time Paid</th>
                  <th>Status</th>
                  <th>Receipt</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr>
                    <td colspan="8" class="text-center" style="padding:30px;color:var(--text-muted);">
                      You have no recorded transactions or payments yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($payments as $pay): ?>
                    <tr>
                      <td><strong>#<?= h($pay['booking_code']) ?></strong></td>
                      <td><?= h($pay['room_name']) ?></td>
                      <td><span style="text-transform:uppercase;"><?= h($pay['method']) ?></span></td>
                      <td style="color:var(--gold);font-weight:700;"><?= formatCurrency($pay['amount']) ?></td>
                      <td><code><?= h($pay['reference_number'] ?: 'No Ref #') ?></code></td>
                      <td>
                        <div style="font-weight:600;color:#fff;"><?= formatDate($pay['created_at'], 'M d, Y') ?></div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                          <i class="fa-solid fa-clock" style="color:var(--gold);margin-right:4px;"></i><?= formatDate($pay['created_at'], 'h:i A') ?>
                        </div>
                        <?php if (!empty($pay['verified_at']) && $pay['status'] === 'verified'): ?>
                          <div style="font-size:0.7rem;color:#4ade80;margin-top:3px;">
                            <i class="fa-solid fa-circle-check"></i> Approved: <?= formatDate($pay['verified_at'], 'M d, Y h:i A') ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td><?= statusBadge($pay['status']) ?></td>
                      <td>
                        <?php if ($pay['proof_image']): ?>
                          <a href="<?= getUploadUrl($pay['proof_image']) ?>" target="_blank" class="btn btn-gold btn-sm" style="padding:4px 10px;font-size:0.75rem;">
                            <i class="fa-solid fa-eye"></i> View
                          </a>
                        <?php else: ?>
                          <span style="color:var(--text-muted);font-size:0.8rem;">No receipt</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Mobile Cards (Screen < 768px: Mobile Phones) -->
          <div class="mobile-bookings-list">
            <?php if (empty($payments)): ?>
              <div class="text-center" style="padding:30px 15px;color:var(--text-muted);">
                <i class="fa-solid fa-receipt" style="font-size:2.5rem;opacity:0.4;margin-bottom:10px;display:block;"></i>
                You have no recorded payments yet.
              </div>
            <?php else: ?>
              <?php foreach ($payments as $pay): ?>
                <div class="booking-mobile-card">
                  <div class="bmc-header">
                    <div>
                      <div class="bmc-code">#<?= h($pay['booking_code']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                        <?= h($pay['room_name']) ?>
                      </div>
                    </div>
                    <div>
                      <?= statusBadge($pay['status']) ?>
                    </div>
                  </div>

                  <div class="bmc-body">
                    <div class="bmc-row" style="align-items:center;">
                      <span class="bmc-label"><i class="fa-solid fa-wallet"></i> Amount Paid:</span>
                      <span class="bmc-price"><?= formatCurrency($pay['amount']) ?></span>
                    </div>
                    <div class="bmc-row">
                      <span class="bmc-label"><i class="fa-solid fa-credit-card"></i> Channel:</span>
                      <span class="bmc-value" style="text-transform:uppercase;font-weight:600;"><?= h($pay['method']) ?></span>
                    </div>
                    <div class="bmc-row">
                      <span class="bmc-label"><i class="fa-solid fa-hashtag"></i> Reference #:</span>
                      <span class="bmc-value"><code><?= h($pay['reference_number'] ?: 'None') ?></code></span>
                    </div>
                    <div class="bmc-row">
                      <span class="bmc-label"><i class="fa-solid fa-calendar-day"></i> Date &amp; Time:</span>
                      <span class="bmc-value" style="font-size:0.8rem;">
                        <?= formatDate($pay['created_at'], 'M d, Y h:i A') ?>
                      </span>
                    </div>
                  </div>

                  <?php if ($pay['proof_image']): ?>
                    <div class="bmc-actions">
                      <a href="<?= getUploadUrl($pay['proof_image']) ?>" target="_blank" class="btn btn-gold btn-sm" style="width:100%;justify-content:center;">
                        <i class="fa-solid fa-receipt"></i> View Uploaded Receipt
                      </a>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/user.js"></script>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
</body>
</html>
