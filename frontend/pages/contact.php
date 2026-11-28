<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

// MAIN WEBSITE GATE: dapat naka-login (register/login) bago makapasok
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/welcome.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/hotel/contact.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    releaseReadOnlySession();
}

$hotelName = getSetting('hotel_name', 'Marvelous Dormitel');
$hotelAddress = getSetting('hotel_address', 'Bonifacio Street, 9506');
$hotelPhone = getSetting('hotel_phone', '0949 785 3328');
$hotelEmail = getSetting('hotel_email', 'info@marveloushotel.com');

$submitted = false;
$contactError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token   = $_POST[CSRF_TOKEN_NAME] ?? '';
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!verifyCsrfToken($token)) {
        $contactError = 'Invalid security session token. Please refresh and try again.';
    } elseif ($name === '' || $email === '' || $message === '') {
        $contactError = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Invalid email address.';
    } else {
        dbExec(
            "INSERT INTO contact_messages (name, email, message, ip_address) VALUES (?, ?, ?, ?)",
            [$name, $email, mb_substr($message, 0, 2000), $_SERVER['REMOTE_ADDR'] ?? null]
        );
        $submitted = true;

        // Best-effort email notification (kung walang mail server, naka-log lang).
        $safeName = str_replace(["\r", "\n"], ' ', $name);
        $sent = @mail(
            $hotelEmail,
            "[Contact Form] Message from " . $safeName,
            "From: {$safeName} <{$email}>\n\n" . mb_substr($message, 0, 2000),
            "From: {$hotelEmail}\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8"
        );
        if (!$sent) {
            error_log("Contact form: naipadala sa DB ang mensahe mula {$email} (mail() hindi available)");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us — <?= h($hotelName) ?></title>
  <meta name="description" content="Get in touch with <?= h($hotelName) ?> for booking inquiries, dormitory slots, and special requests.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= SITE_URL ?>/contact.php">
  <meta property="og:title" content="Contact Us — <?= h($hotelName) ?>">
  <meta property="og:description" content="Get in touch with <?= h($hotelName) ?> for booking inquiries, dormitory slots, and special requests.">
  <meta property="og:image" content="<?= ASSETS_URL ?>/images/hotel/hero-bg.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Contact Us — <?= h($hotelName) ?>">
  <meta name="twitter:description" content="Get in touch with <?= h($hotelName) ?> for booking inquiries, dormitory slots, and special requests.">
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

  <div class="container" style="padding-top:110px;padding-bottom:80px;max-width:960px;">
    <div class="text-center" style="margin-bottom:40px;">
      <div class="section-label">Contact Us</div>
      <h1 class="section-title">We're Here to Help</h1>
      <p class="section-desc" style="margin:0 auto;">Questions about booking or special requests? Send us a message or give us a call.</p>
    </div>

    <div class="contact-grid">
      <!-- Contact Info -->
      <div class="glass-card" style="padding:32px;display:flex;flex-direction:column;gap:20px;">
        <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.3rem;">Hotel Information</h3>
        <div>
          <div style="font-size:0.75rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;">Location</div>
          <div style="color:#fff;margin-top:4px;"><i class="fa-solid fa-location-dot" style="color:var(--gold);margin-right:6px;"></i> <?= h($hotelAddress) ?></div>
        </div>
        <div>
          <div style="font-size:0.75rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;">Phone / Mobile</div>
          <div style="color:#fff;margin-top:4px;"><i class="fa-solid fa-phone" style="color:var(--gold);margin-right:6px;"></i> <?= h($hotelPhone) ?></div>
        </div>
        <div>
          <div style="font-size:0.75rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;">Email Address</div>
          <div style="color:#fff;margin-top:4px;"><i class="fa-solid fa-envelope" style="color:var(--gold);margin-right:6px;"></i> <?= h($hotelEmail) ?></div>
        </div>
        <div>
          <div style="font-size:0.75rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;">Front Desk Hours</div>
          <div style="color:#fff;margin-top:4px;"><i class="fa-solid fa-clock" style="color:var(--gold);margin-right:6px;"></i> Reception Open 24 Hours</div>
        </div>
      </div>

      <!-- Message Form -->
      <div class="glass-card" style="padding:32px;">
        <h3 style="font-family:var(--font-serif);color:#fff;font-size:1.3rem;margin-bottom:16px;">Leave a Message</h3>
        <?php if ($submitted): ?>
          <div class="flash-message flash-success">
            <i class="fa-solid fa-circle-check"></i>
            <span>Thanks! We've received your message and will get back to you as soon as possible.</span>
          </div>
        <?php endif; ?>
        <?php if ($contactError): ?>
          <div class="flash-message flash-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= h($contactError) ?></span>
          </div>
        <?php endif; ?>
        <form action="" method="POST">
          <?= csrfField() ?>
          <div class="form-group">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required placeholder="Juan Dela Cruz" value="<?= h($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required placeholder="juan@email.com" value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Message</label>
            <textarea name="message" class="form-control" required placeholder="How can we help you?"><?= h($_POST['message'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-gold btn-block"><i class="fa-solid fa-paper-plane"></i> Send Message</button>
        </form>
      </div>
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
renderCentralNav('contact');
?>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script defer src="<?= h(assetUrl('js/public.js')) ?>"></script>
</body>
</html>
