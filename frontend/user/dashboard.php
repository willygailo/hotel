<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('user');
releaseReadOnlySession();

$userId = currentUserId();
$user = dbRow("SELECT * FROM users WHERE id = ?", [$userId]);

// Stats for user
$totalBookings = dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE user_id = ?", [$userId])['cnt'] ?? 0;
$activeBookings = dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE user_id = ? AND status IN ('pending','confirmed','checked_in')", [$userId])['cnt'] ?? 0;
$unreadNotifs = unreadNotifCount($userId);
$kyc = getUserKycStatus($userId);

// Fetch recent bookings
$recentBookings = dbRows("
    SELECT b.*, r.name as room_name, r.thumbnail 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.user_id = ? 
    ORDER BY b.id DESC 
    LIMIT 5
", [$userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard — Marvelous Hotel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/user.css">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
  <!-- Sidebar -->
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('user'); ?>
  <!-- Main Content -->
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="user-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">User Dashboard</div>
      </div>
      <div class="topbar-actions">
        <a href="notifications.php" class="topbar-notif" title="Notifications">
          <i class="fa-solid fa-bell"></i>
          <?php if ($unreadNotifs > 0): ?>
            <span class="badge-dot"></span>
          <?php endif; ?>
        </a>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
            <h1 class="page-header-title" style="margin:0;">Welcome, <?= h($user['first_name']) ?>! 👋</h1>
            <?php if ($kyc['is_verified']): ?>
              <span class="badge" style="background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);font-size:0.75rem;padding:4px 8px;">
                <i class="fa-solid fa-circle-check"></i> Identity Verified
              </span>
            <?php elseif ($kyc['is_pending']): ?>
              <span class="badge" style="background:rgba(234,179,8,0.15);color:#fde047;border:1px solid rgba(234,179,8,0.3);font-size:0.75rem;padding:4px 8px;">
                <i class="fa-solid fa-clock"></i> ID Under Review
              </span>
            <?php else: ?>
              <a href="profile.php" class="badge" style="background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);font-size:0.75rem;padding:4px 8px;text-decoration:none;">
                <i class="fa-solid fa-id-card"></i> Verify ID to Book
              </a>
            <?php endif; ?>
          </div>
          <div class="page-header-sub">Manage your reservations and payments quickly and easily.</div>
        </div>
      </div>

      <!-- Quick Stats -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:28px;">
        <div class="stat-card">
          <div class="stat-icon gold"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $totalBookings ?></div>
            <div class="stat-label">Total Bookings</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $activeBookings ?></div>
            <div class="stat-label">Active Stays / Pending</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple"><i class="fa-solid fa-robot"></i></div>
          <div class="stat-info">
            <div class="stat-value">AI Active</div>
            <div class="stat-label">Room Matcher Ready</div>
          </div>
        </div>
      </div>

      <!-- Recent Bookings Table -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--gold);margin-right:8px;"></i> Latest Bookings</h2>
          <a href="my-bookings.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
          <!-- Desktop Table (Screen >= 768px: Laptop / PC) -->
          <div class="table-wrap desktop-bookings-table">
            <table class="table">
              <thead>
                <tr>
                  <th>Booking Code</th>
                  <th>Room</th>
                  <th>Stay Dates</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recentBookings)): ?>
                  <tr>
                    <td colspan="6" class="text-center" style="padding:30px;color:var(--text-muted);">
                      You have no past bookings yet. Book now!
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recentBookings as $b): ?>
                    <tr>
                      <td><strong><?= h($b['booking_code']) ?></strong></td>
                      <td><?= h($b['room_name']) ?></td>
                      <td><?= formatDate($b['check_in']) ?> - <?= formatDate($b['check_out']) ?></td>
                      <td style="color:var(--gold);font-weight:600;"><?= formatCurrency($b['total_amount']) ?></td>
                      <td><?= statusBadge($b['status']) ?></td>
                      <td>
                        <a href="booking-detail.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm">Details</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Mobile Cards (Screen < 768px: Mobile Phones) -->
          <div class="mobile-bookings-list">
            <?php if (empty($recentBookings)): ?>
              <div class="text-center" style="padding:30px 15px;color:var(--text-muted);">
                <i class="fa-solid fa-calendar-xmark" style="font-size:2.5rem;opacity:0.4;margin-bottom:10px;display:block;"></i>
                You have no past bookings yet.
              </div>
            <?php else: ?>
              <?php foreach ($recentBookings as $b): ?>
                <div class="booking-mobile-card">
                  <div class="bmc-header">
                    <div>
                      <div class="bmc-code"><?= h($b['booking_code']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                        <?= h($b['room_name']) ?>
                      </div>
                    </div>
                    <div>
                      <?= statusBadge($b['status']) ?>
                    </div>
                  </div>

                  <div class="bmc-body">
                    <div class="bmc-row">
                      <span class="bmc-label"><i class="fa-solid fa-calendar-day"></i> Stay:</span>
                      <span class="bmc-value" style="font-size:0.82rem;">
                        <?= formatDate($b['check_in']) ?> &rarr; <?= formatDate($b['check_out']) ?>
                      </span>
                    </div>
                    <div class="bmc-row" style="align-items:center;">
                      <span class="bmc-label"><i class="fa-solid fa-wallet"></i> Total:</span>
                      <span class="bmc-price"><?= formatCurrency($b['total_amount']) ?></span>
                    </div>
                  </div>

                  <div class="bmc-actions">
                    <a href="booking-detail.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">
                      <i class="fa-solid fa-eye"></i> View Details
                    </a>
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

<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/user.js"></script>
</body>
</html>
