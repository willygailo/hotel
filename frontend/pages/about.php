<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

// MAIN WEBSITE GATE: dapat naka-login (register/login) bago makapasok
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/welcome.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/hotel/about.php'));
    exit;
}

releaseReadOnlySession();

$hotelName = getSetting('hotel_name', 'Marvelous Hotel');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us — <?= h($hotelName) ?></title>
  <meta name="description" content="Discover the story and mission of <?= h($hotelName) ?>. Blending comfort and elegance.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= SITE_URL ?>/about.php">
  <meta property="og:title" content="About Us — <?= h($hotelName) ?>">
  <meta property="og:description" content="Discover the story and mission of <?= h($hotelName) ?>. Blending comfort and elegance.">
  <meta property="og:image" content="<?= ASSETS_URL ?>/images/hotel/hero-bg.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="About Us — <?= h($hotelName) ?>">
  <meta name="twitter:description" content="Discover the story and mission of <?= h($hotelName) ?>. Blending comfort and elegance.">
  <meta name="twitter:image" content="<?= ASSETS_URL ?>/images/hotel/hero-bg.jpg">
  <?php require_once __DIR__ . '/../components/page-head.php'; renderPerformanceHeadLinks(true); ?>
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/public.css')) ?>">
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar" style="background:var(--navy-mid);border-bottom:1px solid var(--border);">
    <div class="container">
      <div class="nav-inner">
        <a href="index.php" class="nav-logo">
          <div class="nav-logo-icon">M</div>
          <div class="nav-logo-text"><div class="nav-logo-title"><?= h($hotelName) ?></div><div class="nav-logo-sub">&amp; Dormitory</div></div>
        </a>
        <div class="nav-actions" style="display:flex;gap:10px;align-items:center;flex-shrink:0;">
          <a href="index.php" class="btn btn-ghost btn-sm" style="white-space:nowrap;"><i class="fa-solid fa-house"></i> <span class="nav-btn-text">Home</span></a>
          <a href="rooms.php" class="btn btn-ghost btn-sm" style="white-space:nowrap;"><i class="fa-solid fa-bed"></i> <span class="nav-btn-text">Rooms</span></a>
          <?php if (isLoggedIn()): ?>
            <?php 
              $u = currentUser(); 
            ?>
            <a href="javascript:void(0)" class="nav-profile-badge" id="navProfileBadge" title="Open Navigation Menu" role="button" aria-expanded="false">
              <img src="<?= h(getUserAvatarUrl($u['avatar'])) ?>" alt="<?= h($u['name']) ?>" class="nav-user-avatar" decoding="async" onerror="this.src='<?= SITE_URL ?>/frontend/assets/images/avatars/default-avatar.png'">
              <span class="nav-user-name"><?= h(explode(' ', $u['name'])[0]) ?></span>
              <i class="fa-solid fa-chevron-down nav-user-chevron"></i>
            </a>
          <?php else: ?>
            <button class="nav-menu-btn" id="navMenuBtn" aria-label="Open Navigation Menu" title="Navigation Menu" aria-expanded="false">
              <i class="fa-solid fa-bars"></i>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <div class="container" style="padding-top:110px;padding-bottom:80px;max-width:880px;">
    <div class="text-center" style="margin-bottom:40px;">
      <div class="section-label">Our Story</div>
      <h1 class="section-title">Welcome to <?= h($hotelName) ?></h1>
      <p class="section-desc" style="margin:0 auto;">The perfect blend of luxurious hotel service and affordable dormitory stays.</p>
    </div>

    <div class="glass-card about-card" style="padding:36px;line-height:1.8;color:var(--text-main);margin-bottom:32px;">
      <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.4rem;margin-bottom:12px;">Our Mission</h3>
      <p style="margin-bottom:20px;color:var(--text-muted);">
        At Marvelous Hotel and Dormitory, we believe every traveler, student, and family deserves a safe, clean, and affordable home. With modern technology and AI-based room recommendations, we make every step easier — from choosing your room to instant checkout.
      </p>

      <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.4rem;margin-bottom:12px;">Why Choose Us?</h3>
      <ul style="margin-left:20px;color:var(--text-muted);display:flex;flex-direction:column;gap:10px;">
        <li>✨ <strong>Smart Room Matcher:</strong> AI that matches rooms to your budget.</li>
        <li>🛌 <strong>Flexible Accommodation:</strong> Dormitory beds for solo backpackers and luxury suites for vacations.</li>
        <li>💳 <strong>Fast &amp; Secure Payments:</strong> GCash, Maya, and bank transfers supported.</li>
        <li>🛡️ <strong>24/7 Security &amp; Front Desk:</strong> A safe stay with active security checks.</li>
      </ul>
    </div>

    <div class="text-center">
      <a href="rooms.php" class="btn btn-gold btn-lg"><i class="fa-solid fa-magnifying-glass"></i> Find a Room Now</a>
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
        <a href="auth/logout.php?portal=<?= h(currentRole() ?: 'user') ?>" class="footer-link">Logout</a>
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
    </div>
  </div>
</footer>

<?php 
require_once __DIR__ . '/../components/central-nav.php'; 
renderCentralNav('about');
?>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script defer src="<?= h(assetUrl('js/public.js')) ?>"></script>
</body>
</html>
