
<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

// MAIN WEBSITE GATE: dapat naka-login (register/login) bago makapasok
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/welcome.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/hotel/rooms.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    releaseSessionLock();
}

$hotelName = getSetting('hotel_name', 'Marvelous Hotel');

// Filter inputs
$typeFilter = $_GET['type'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

$sql = "SELECT r.*, rt.name as type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.status != 'maintenance'";
$params = [];

if (!empty($typeFilter)) {
    $sql .= " AND rt.name = ?";
    $params[] = $typeFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (r.name LIKE ? OR r.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY r.price_per_night ASC";
$rooms = dbRows($sql, $params);
$roomTypes = dbRows("SELECT * FROM room_types");
$allBookingsMap = getAllRoomsActiveBookingsMap();
$currentKyc = isLoggedIn() ? getUserKycStatus(currentUserId()) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse All Rooms — <?= h($hotelName) ?></title>
  <meta name="description" content="Explore comfortable dormitories, luxury hotel suites, and private rooms at <?= h($hotelName) ?>.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= SITE_URL ?>/rooms.php">
  <meta property="og:title" content="Browse All Rooms — <?= h($hotelName) ?>">
  <meta property="og:description" content="Explore comfortable dormitories, luxury hotel suites, and private rooms at <?= h($hotelName) ?>.">
  <meta property="og:image" content="<?= ASSETS_URL ?>/images/hotel/share-preview.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Browse All Rooms — <?= h($hotelName) ?>">
  <meta name="twitter:description" content="Explore comfortable dormitories, luxury hotel suites, and private rooms at <?= h($hotelName) ?>.">
  <meta name="twitter:image" content="<?= ASSETS_URL ?>/images/hotel/share-preview.jpg">
  <!-- Performance Preconnect & Resource Hints -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/public.css">
  <link rel="stylesheet" href="assets/css/ai-chat.css">
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar" style="background:var(--navy-mid);border-bottom:1px solid var(--border);">
    <div class="container">
      <div class="nav-inner">
        <a href="index.php" class="nav-logo">
          <div class="nav-logo-icon">M</div>
          <div class="nav-logo-text">
            <div class="nav-logo-title">Marvelous Hotel</div>
            <div class="nav-logo-sub">&amp; Dormitory</div>
          </div>
        </a>
        <div class="nav-actions" style="display:flex;gap:10px;align-items:center;">
          <a href="index.php" class="btn btn-ghost btn-sm" title="Go Home"><i class="fa-solid fa-house"></i> <span class="nav-btn-text">Home</span></a>
          <?php if (isLoggedIn()): ?>
            <?php 
              $u = currentUser(); 
            ?>
            <a href="javascript:void(0)" onclick="toggleCentralNav()" class="nav-profile-badge" id="navProfileBadge" title="Open Navigation Menu" role="button" aria-expanded="false">
              <img src="<?= h(getUserAvatarUrl($u['avatar'])) ?>" alt="<?= h($u['name']) ?>" class="nav-user-avatar" onerror="this.src='<?= SITE_URL ?>/assets/images/avatars/default-avatar.png'">
              <span class="nav-user-name"><?= h(explode(' ', $u['name'])[0]) ?></span>
              <i class="fa-solid fa-chevron-down nav-user-chevron"></i>
            </a>
          <?php else: ?>
            <a href="auth/login.php" class="btn btn-gold btn-sm"><i class="fa-solid fa-user"></i> <span class="nav-btn-text">Login</span></a>
            <button class="nav-menu-btn" id="navMenuBtn" onclick="toggleCentralNav()" aria-label="Open Navigation Menu" title="Navigation Menu" aria-expanded="false">
              <i class="fa-solid fa-bars"></i>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <div class="container" style="padding-top:110px;padding-bottom:60px;">
    <div style="margin-bottom:30px;text-align:center;">
      <h1 style="font-family:var(--font-serif);font-size:2.2rem;color:#fff;">Rooms &amp; Dormitory</h1>
      <p style="color:var(--text-muted);">Choose the perfect room for your vacation, work trip, or stay.</p>
      <?php if (!empty($currentKyc['is_verified'])): ?>
        <div style="margin-top:10px;">
          <span class="badge" style="background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);font-size:0.75rem;padding:4px 12px;">
            <i class="fa-solid fa-circle-check"></i> Identity Verified — Instant Booking &amp; Payment Checkout Active
          </span>
        </div>
      <?php endif; ?>
    </div>

    <!-- Filter Form -->
    <div class="glass-card" style="padding:16px 20px;margin-bottom:32px;">
      <form action="" method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <input type="text" name="q" class="form-control" placeholder="Search by name..." value="<?= h($searchQuery) ?>" style="flex:2;min-width:200px;">
        <select name="type" class="form-control" style="flex:1;min-width:160px;">
          <option value="">All Types</option>
          <?php foreach ($roomTypes as $t): ?>
            <option value="<?= h($t['name']) ?>" <?= $typeFilter === $t['name'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-gold"><i class="fa-solid fa-filter"></i> Filter</button>
      </form>
    </div>

    <!-- Rooms List -->
    <div class="rooms-grid">
      <?php if (empty($rooms)): ?>
        <div class="empty-state" style="grid-column:1/-1;">
          <div class="empty-state-icon"><i class="fa-solid fa-bed"></i></div>
          <div class="empty-state-title">No rooms found</div>
          <div class="empty-state-desc">Try changing your keyword or filter.</div>
        </div>
      <?php else: ?>
        <?php foreach ($rooms as $r): 
          $amenities = safeJson($r['amenities']);
          $occ = getRoomOccupancyStatus((int)$r['id'], $r['status'], $allBookingsMap[(int)$r['id']] ?? []);
        ?>
          <div class="room-card">
            <div class="room-card-img">
              <img src="<?= roomThumbUrl($r['thumbnail']) ?>" loading="lazy" decoding="async" onerror="this.src='assets/images/rooms/default-room.jpg'">
              <div class="room-card-badge">
                <?= $occ['tag_html'] ?>
              </div>
            </div>
            <div class="room-card-body">
              <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px;">
                <h3 class="room-card-name"><?= h($r['name']) ?></h3>
                <span class="badge badge-gold"><?= h($r['type_name']) ?></span>
              </div>
              <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px;">Floor <?= $r['floor'] ?> · Up to <?= $r['capacity'] ?> Pax</p>
              
              <?php if ($occ['state'] === 'occupied'): ?>
                <div style="font-size:0.75rem;color:#f87171;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);border-radius:6px;padding:5px 9px;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                  <i class="fa-solid fa-bed"></i> Currently occupied until <?= formatDate($occ['current_until']) ?>. Advance bookings open!
                </div>
              <?php elseif ($occ['state'] === 'reserved'): ?>
                <div style="font-size:0.75rem;color:#fbbf24;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.25);border-radius:6px;padding:5px 9px;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                  <i class="fa-solid fa-calendar-check"></i> Booked <?= formatDate($occ['next_booking']['check_in'], 'M d') ?> – <?= formatDate($occ['next_booking']['check_out'], 'M d') ?>. Other dates available!
                </div>
              <?php endif; ?>

              <div class="room-card-amenities">
                <?php foreach (array_slice($amenities, 0, 4) as $am): ?>
                  <span class="room-amenity-tag"><i class="fa-solid fa-check" style="color:var(--gold);font-size:0.6rem;"></i> <?= h($am) ?></span>
                <?php endforeach; ?>
              </div>

              <div class="room-card-footer">
                <div>
                  <div class="room-price-main"><?= formatCurrency($r['price_per_night']) ?></div>
                  <div class="room-price-sub">per night</div>
                </div>
                <?php if (isLoggedIn() && empty($currentKyc['is_verified'])): ?>
                  <a href="user/profile.php" class="btn btn-sm" style="background:rgba(234,179,8,0.15);color:#fde047;border:1px solid rgba(234,179,8,0.35);" title="You must verify your ID before booking">
                    <i class="fa-solid fa-id-card"></i> Verify ID to Book
                  </a>
                <?php else: ?>
                  <a href="<?= isLoggedIn() ? 'user/booking.php?room_id='.$r['id'] : 'auth/login.php?redirect='.urlencode('user/booking.php?room_id='.$r['id']) ?>" class="btn btn-gold btn-sm">
                    Select &amp; Book
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

<footer>
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="index.php" class="nav-logo" style="margin-bottom:12px;display:inline-flex;">
          <div class="nav-logo-icon">M</div>
          <div class="nav-logo-text">
            <div class="nav-logo-title">Marvelous Hotel</div>
            <div class="nav-logo-sub">& Dormitory</div>
          </div>
        </a>
        <p class="footer-brand-desc">
          Experience premium hospitality with our modern booking system, 
          AI room recommendations, and world-class service in every stay.
        </p>
        <div class="footer-socials">
          <a href="#" class="social-btn" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="#" class="social-btn" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="#" class="social-btn" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
          <a href="#" class="social-btn" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
        </div>
      </div>
      <div>
        <div class="footer-heading">Quick Links</div>
        <a href="index.php" class="footer-link">Home</a>
        <a href="rooms.php" class="footer-link">Browse Rooms</a>
        <a href="about.php" class="footer-link">About Us</a>
        <a href="contact.php" class="footer-link">Contact</a>
      </div>
      <div>
        <div class="footer-heading">Account</div>
        <?php if (!isLoggedIn()): ?>
        <a href="auth/register.php" class="footer-link">Register</a>
        <a href="auth/login.php" class="footer-link">Login</a>
        <?php else: ?>
        <a href="<?= currentRole() ?>/dashboard.php" class="footer-link">Dashboard</a>
        <a href="user/my-bookings.php" class="footer-link">My Bookings</a>
        <a href="user/payment-history.php" class="footer-link">Payments</a>
        <a href="auth/logout.php" class="footer-link">Logout</a>
        <?php endif; ?>
      </div>
      <div>
        <div class="footer-heading">Contact</div>
        <p class="footer-link" style="cursor:default;"><i class="fa-solid fa-location-dot" style="color:var(--gold);width:16px;"></i> <?= h(getSetting('hotel_address','Quezon City, PH')) ?></p>
        <a href="tel:<?= h(getSetting('hotel_phone')) ?>" class="footer-link"><i class="fa-solid fa-phone" style="color:var(--gold);width:16px;"></i> <?= h(getSetting('hotel_phone')) ?></a>
        <a href="mailto:<?= h(getSetting('hotel_email')) ?>" class="footer-link"><i class="fa-solid fa-envelope" style="color:var(--gold);width:16px;"></i> <?= h(getSetting('hotel_email')) ?></a>
        <p class="footer-link" style="cursor:default;"><i class="fa-solid fa-clock" style="color:var(--gold);width:16px;"></i> Check-in: <?= h(getSetting('check_in_time')) ?> | Out: <?= h(getSetting('check_out_time')) ?></p>
      </div>
    </div>
    <div class="footer-bottom">
      <p class="footer-bottom-text">
        &copy; <?= date('Y') ?> <?= h($hotelName) ?>. All rights reserved.
      </p>
      <div style="display:flex;gap:20px;">
        <a href="#" class="footer-bottom-text" style="transition:color 0.3s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color=''">Privacy Policy</a>
        <a href="#" class="footer-bottom-text" style="transition:color 0.3s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color=''">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>

<?php 
require_once __DIR__ . '/includes/components/central-nav.php'; 
renderCentralNav('rooms');
?>
<?php require_once __DIR__ . '/includes/components/ai-chatbot.php'; ?>
<script src="assets/js/core.js"></script>
<script src="assets/js/public.js"></script>
<script src="assets/js/share.js"></script>
</body>
</html>
