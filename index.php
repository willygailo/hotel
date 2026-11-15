<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

// MAIN WEBSITE GATE: dapat naka-login (register/login) bago makapasok
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/welcome.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/hotel/index.php'));
    exit;
}

// Release PHP session lock for high-concurrency read requests (GET) so concurrent users load instantly
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    releaseSessionLock();
}

// Fetch data for homepage
$rooms = dbRows("
    SELECT r.*, rt.name as type_name 
    FROM rooms r 
    JOIN room_types rt ON r.room_type_id = rt.id 
    WHERE r.status != 'maintenance' 
    ORDER BY r.price_per_night ASC 
    LIMIT 6
");
$allBookingsMap = getAllRoomsActiveBookingsMap();

// Handle Review Submission from Homepage Modal
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submit_homepage_review'])) {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/auth/welcome.php?redirect=' . urlencode('index.php#reviews-section'));
        exit;
    }
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $uId     = currentUserId();
        $bId     = (int)($_POST['booking_id'] ?? 0);
        $rating  = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
            $roomId = null;
            $bookingId = null;
            if ($bId > 0) {
                $bk = dbRow("SELECT id, room_id FROM bookings WHERE id = ? AND user_id = ?", [$bId, $uId]);
                if ($bk) {
                    $bookingId = $bk['id'];
                    $roomId = $bk['room_id'];
                }
            } else {
                $latestBk = dbRow("SELECT room_id FROM bookings WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$uId]);
                if ($latestBk) {
                    $roomId = $latestBk['room_id'];
                }
            }

            dbInsert(
                "INSERT INTO reviews (user_id, room_id, booking_id, rating, comment, status) 
                 VALUES (?, ?, ?, ?, ?, 'approved')",
                [$uId, $roomId, $bookingId, $rating, $comment]
            );

            auditLog('Submitted Homepage Review', 'User Module', "Review submitted by User #$uId");
            setFlash('success', 'Maraming salamat! Naidagdag na ang iyong rating at review sa Customer Reviews.');
        } else {
            setFlash('error', 'Pakilagay ang iyong rating at feedback comment.');
        }
    }
    header('Location: ' . SITE_URL . '/index.php#reviews-section');
    exit;
}

$userBookings = isLoggedIn() ? dbRows("
    SELECT b.id, b.booking_code, r.name as room_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.user_id = ? 
    ORDER BY b.id DESC
", [currentUserId()]) : [];

$reviewCount = dbRow("SELECT COUNT(*) as cnt FROM reviews WHERE status='approved'")['cnt'] ?? 0;
$totalBookings = dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE status NOT IN ('cancelled','rejected')")['cnt'] ?? 0;
$hotelName = getSetting('hotel_name', 'Marvelous Hotel');
$hotelTagline = getSetting('hotel_tagline', 'Your Home Away From Home');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($hotelName) ?> — <?= h($hotelTagline) ?></title>
  <meta name="description" content="Experience luxury and comfort at <?= h($hotelName) ?>. Book rooms, dormitories, and studios online with AI-powered room recommendations.">
  <meta name="keywords" content="hotel booking, dormitory, room reservation, <?= h($hotelName) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= SITE_URL ?>/index.php">
  <meta property="og:title" content="<?= h($hotelName) ?> — <?= h($hotelTagline) ?>">
  <meta property="og:description" content="Book hotel rooms, dormitories, and studios online with AI recommendations at <?= h($hotelName) ?>.">
  <meta property="og:image" content="<?= ASSETS_URL ?>/images/hotel/share-preview.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= h($hotelName) ?> — <?= h($hotelTagline) ?>">
  <meta name="twitter:description" content="Book hotel rooms, dormitories, and studios online with AI recommendations at <?= h($hotelName) ?>.">
  <meta name="twitter:image" content="<?= ASSETS_URL ?>/images/hotel/share-preview.jpg">

  <!-- Performance Preconnect & Resource Hints -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Main CSS -->
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/public.css">
  <link rel="stylesheet" href="assets/css/ai-chat.css">

  <style>
    /* ===== HERO ===== */
    .hero {
      min-height: 100vh;
      position: relative;
      display: flex;
      align-items: center;
      overflow: hidden;
    }
    .hero-bg {
      position: absolute;
      inset: 0;
      background: 
        linear-gradient(to bottom, rgba(10,15,44,0.3) 0%, rgba(10,15,44,0.85) 100%),
        url('assets/images/hotel/hero-bg.jpg') center/cover no-repeat;
      z-index: 0;
    }
    .hero-bg::before {
      content: '';
      position: absolute;
      inset: 0;
      background: 
        radial-gradient(ellipse 80% 60% at 50% 0%, rgba(201,168,76,0.12) 0%, transparent 70%),
        radial-gradient(ellipse 50% 40% at 80% 50%, rgba(78,154,241,0.08) 0%, transparent 60%);
    }
    /* Animated particles */
    .hero-particles {
      position: absolute;
      inset: 0;
      z-index: 1;
      pointer-events: none;
    }
    .particle {
      position: absolute;
      width: 2px; height: 2px;
      background: var(--gold);
      border-radius: 50%;
      opacity: 0;
      animation: floatParticle linear infinite;
    }
    @keyframes floatParticle {
      0%   { opacity: 0; transform: translateY(0) scale(0); }
      10%  { opacity: 0.6; }
      90%  { opacity: 0.2; }
      100% { opacity: 0; transform: translateY(-100vh) scale(1.5); }
    }
    .hero-content {
      position: relative;
      z-index: 2;
      padding-top: 100px;
      padding-bottom: 80px;
    }
    .hero-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: rgba(201,168,76,0.1);
      border: 1px solid rgba(201,168,76,0.25);
      border-radius: 50px;
      padding: 6px 18px;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--gold);
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 24px;
      animation: fadeUp 0.6s ease both;
    }
    .hero-title {
      font-family: var(--font-serif);
      font-size: clamp(2.5rem, 6vw, 5rem);
      line-height: 1.1;
      color: var(--white);
      margin-bottom: 20px;
      animation: fadeUp 0.7s 0.1s ease both;
    }
    .hero-title .highlight {
      background: linear-gradient(135deg, var(--gold), var(--gold-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .hero-desc {
      font-size: 1.1rem;
      color: rgba(232,232,240,0.8);
      max-width: 540px;
      line-height: 1.7;
      margin-bottom: 36px;
      animation: fadeUp 0.7s 0.2s ease both;
    }
    .hero-actions {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 60px;
      animation: fadeUp 0.7s 0.3s ease both;
    }
    .hero-stats {
      display: flex;
      gap: 40px;
      flex-wrap: wrap;
      animation: fadeUp 0.7s 0.4s ease both;
    }
    .hero-stat-value {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--white);
      line-height: 1;
    }
    .hero-stat-label {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-top: 4px;
    }
    .hero-stat + .hero-stat { padding-left: 40px; border-left: 1px solid var(--border); }

    /* Search bar */
    .search-bar {
      background: var(--navy-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      padding: 10px 10px 10px 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      max-width: 760px;
      margin: 0 auto;
      flex-wrap: wrap;
      animation: fadeUp 0.7s 0.5s ease both;
      box-shadow: var(--shadow-deep);
    }
    .search-field {
      flex: 1;
      min-width: 140px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .search-field + .search-field { border-left: 1px solid var(--border); padding-left: 12px; }
    .search-field-icon { color: var(--gold); font-size: 0.9rem; flex-shrink: 0; }
    .search-field-label { font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 2px; }
    .search-field input, .search-field select {
      background: none;
      border: none;
      outline: none;
      color: var(--text-main);
      font-size: 0.9rem;
      font-weight: 500;
      width: 100%;
    }
    .search-field input::placeholder { color: var(--text-muted); }
    .search-field select option { background: var(--navy-mid); }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(30px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ===== FEATURES SECTION ===== */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
    }
    .feature-card {
      background: var(--navy-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 32px 24px;
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      right: 0; height: 2px;
      background: linear-gradient(90deg, transparent, var(--gold), transparent);
      transform: scaleX(0);
      transition: transform 0.4s ease;
    }
    .feature-card:hover::before { transform: scaleX(1); }
    .feature-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-card); border-color: rgba(201,168,76,0.3); }
    .feature-icon {
      width: 60px; height: 60px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 20px;
      background: linear-gradient(135deg, rgba(201,168,76,0.15), rgba(201,168,76,0.05));
      border: 1px solid rgba(201,168,76,0.2);
      color: var(--gold);
    }
    .feature-title { font-size: 1.05rem; font-weight: 700; color: var(--white); margin-bottom: 10px; }
    .feature-desc { font-size: 0.875rem; color: var(--text-muted); line-height: 1.6; }

    /* ===== ROOMS SECTION ===== */
    .rooms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 24px;
    }

    /* ===== TESTIMONIALS ===== */
    .testimonials-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
    }
    .testimonial-card {
      background: var(--navy-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 28px;
    }
    .testimonial-stars { color: var(--gold); margin-bottom: 14px; }
    .testimonial-text { font-size: 0.9rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 20px; font-style: italic; }
    .testimonial-author { display: flex; align-items: center; gap: 12px; }
    .testimonial-avatar {
      width: 42px; height: 42px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--gold-dark), var(--accent2));
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: white;
      font-size: 0.9rem;
    }
    .testimonial-name { font-weight: 700; font-size: 0.9rem; color: var(--white); }
    .testimonial-label { font-size: 0.75rem; color: var(--text-muted); }

    /* ===== CTA SECTION ===== */
    .cta-section {
      background: linear-gradient(135deg, var(--navy-light), var(--navy-mid));
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      padding: 72px 48px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .cta-section::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse 70% 60% at 50% 50%, rgba(201,168,76,0.08) 0%, transparent 70%);
      pointer-events: none;
    }
    .cta-section .section-title { margin-bottom: 16px; }

    /* ===== FOOTER ===== */
    footer {
      background: var(--navy-card);
      border-top: 1px solid var(--border);
      padding: 60px 0 30px;
    }
    .footer-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 48px;
      margin-bottom: 48px;
    }
    .footer-brand-desc { font-size: 0.875rem; color: var(--text-muted); line-height: 1.7; margin: 16px 0; }
    .footer-socials { display: flex; gap: 10px; }
    .social-btn {
      width: 36px; height: 36px;
      border-radius: 8px;
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      display: flex; align-items: center; justify-content: center;
      color: var(--text-muted);
      transition: var(--transition);
    }
    .social-btn:hover { color: var(--gold); border-color: var(--gold); background: rgba(201,168,76,0.08); }
    .footer-heading { font-size: 0.8rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--gold); margin-bottom: 16px; }
    .footer-link { display: block; font-size: 0.875rem; color: var(--text-muted); padding: 4px 0; transition: var(--transition); }
    .footer-link:hover { color: var(--text-main); padding-left: 6px; }
    .footer-bottom { border-top: 1px solid var(--border); padding-top: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
    .footer-bottom-text { font-size: 0.8rem; color: var(--text-muted); }

    /* ===== SCROLL ANIMATIONS ===== */
    .reveal {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.7s ease, transform 0.7s ease;
    }
    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    /* ===== AI RECOMMENDATION BANNER ===== */
    .ai-banner {
      background: linear-gradient(135deg, rgba(123,94,167,0.15), rgba(78,154,241,0.1));
      border: 1px solid rgba(123,94,167,0.25);
      border-radius: var(--radius-xl);
      padding: 40px 48px;
      display: flex;
      align-items: center;
      gap: 32px;
      position: relative;
      overflow: hidden;
    }
    .ai-banner::before {
      content: '';
      position: absolute;
      right: -60px; top: -60px;
      width: 200px; height: 200px;
      background: radial-gradient(circle, rgba(123,94,167,0.2) 0%, transparent 70%);
    }
    .ai-banner-icon {
      width: 80px; height: 80px;
      border-radius: 20px;
      background: linear-gradient(135deg, var(--accent2), var(--accent));
      display: flex; align-items: center; justify-content: center;
      font-size: 2rem;
      flex-shrink: 0;
      box-shadow: 0 8px 32px rgba(123,94,167,0.4);
    }
    .ai-banner-content { flex: 1; }
    .ai-banner-title {
      font-family: var(--font-serif);
      font-size: 1.5rem;
      color: var(--white);
      margin-bottom: 8px;
    }
    .ai-banner-desc { font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; }
    @media (max-width: 768px) {
      .footer-grid { grid-template-columns: 1fr 1fr; gap: 32px; }
      .ai-banner { flex-direction: column; text-align: center; padding: 32px 24px; }
      .search-bar { flex-direction: column; align-items: stretch; }
      .search-field + .search-field { border-left: none; border-top: 1px solid var(--border); padding-left: 0; padding-top: 12px; }
    }
    @media (max-width: 480px) {
      .footer-grid { grid-template-columns: 1fr; }
      .hero-stats { gap: 24px; }
      .hero-stat + .hero-stat { padding-left: 24px; }
      .hero-actions {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        gap: 12px;
      }
      .hero-actions .btn {
        width: 100%;
        justify-content: center;
        text-align: center;
      }
      .cta-content .btn-lg {
        width: 100%;
        margin: 6px 0 !important;
      }
    }
  </style>
</head>
<body>

<!-- ======================== NAVBAR ======================== -->
<nav class="navbar" id="navbar">
  <div class="container">
    <div class="nav-inner">
      <a href="index.php" class="nav-logo">
        <div class="nav-logo-icon">M</div>
        <div class="nav-logo-text">
          <div class="nav-logo-title">Marvelous Hotel</div>
          <div class="nav-logo-sub">& Dormitory</div>
        </div>
      </a>

      <ul class="nav-links">
        <li><a href="index.php" class="nav-link active">Home</a></li>
        <li><a href="rooms.php" class="nav-link">Rooms</a></li>
        <li><a href="about.php" class="nav-link">About</a></li>
        <li><a href="contact.php" class="nav-link">Contact</a></li>
      </ul>

      <div class="nav-actions">
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
          <a href="auth/login.php" class="btn btn-ghost btn-sm">Log In</a>
          <a href="auth/register.php" class="btn btn-gold btn-sm">
            <i class="fa-solid fa-user-plus"></i> Register
          </a>
          <button class="nav-menu-btn" id="navMenuBtn" onclick="toggleCentralNav()" aria-label="Open Navigation Menu" title="Navigation Menu" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- ======================== HERO ======================== -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-particles" id="particles"></div>

  <div class="container" style="width:100%;">
    <div class="hero-content">
      <div class="hero-eyebrow">
        <i class="fa-solid fa-star"></i>
        Premium Hotel &amp; Dormitory Experience
      </div>
      <h1 class="hero-title">
        Your Perfect Stay<br>
        Awaits at <span class="highlight">Marvelous</span>
      </h1>
      <p class="hero-desc">
        From cozy dormitory beds to luxurious suites — book your ideal room instantly with 
        our AI-powered recommendation system. Comfort, value, and service like no other.
      </p>
      <div class="hero-actions">
        <a href="rooms.php" class="btn btn-gold btn-lg" id="browseRoomsBtn">
          <i class="fa-solid fa-magnifying-glass"></i>
          Browse Rooms
        </a>
        <a href="about.php" class="btn btn-outline btn-lg">
          <i class="fa-solid fa-play"></i>
          Our Story
        </a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat">
          <div class="hero-stat-value">9+</div>
          <div class="hero-stat-label">Room Options</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-value"><?= number_format($totalBookings) ?>+</div>
          <div class="hero-stat-label">Happy Visitors</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-value">4.9★</div>
          <div class="hero-stat-label">Customer Rating</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-value">24/7</div>
          <div class="hero-stat-label">AI Support</div>
        </div>
      </div>
    </div>

    <!-- Quick Search -->
    <div class="search-bar reveal" id="searchBar">
      <div class="search-field">
        <div class="search-field-icon"><i class="fa-solid fa-calendar-day"></i></div>
        <div>
          <div class="search-field-label">Check In</div>
          <input type="date" id="searchCheckIn" min="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="search-field">
        <div class="search-field-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <div>
          <div class="search-field-label">Check Out</div>
          <input type="date" id="searchCheckOut" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
        </div>
      </div>
      <div class="search-field">
        <div class="search-field-icon"><i class="fa-solid fa-users"></i></div>
        <div>
          <div class="search-field-label">Pax (Persons)</div>
          <select id="searchGuests">
            <option>1 Person</option>
            <option>2 Persons</option>
            <option>3 Persons</option>
            <option>4+ Persons</option>
          </select>
        </div>
      </div>
      <div class="search-field">
        <div class="search-field-icon"><i class="fa-solid fa-bed"></i></div>
        <div>
          <div class="search-field-label">Room Type</div>
          <select id="searchType">
            <option>All Types</option>
            <option>Standard Room</option>
            <option>Deluxe Room</option>
            <option>Suite</option>
            <option>Dormitory</option>
            <option>Studio</option>
          </select>
        </div>
      </div>
      <button class="btn btn-gold" onclick="performSearch()" id="searchBtn" style="flex-shrink:0;">
        <i class="fa-solid fa-search"></i> Search
      </button>
    </div>
  </div>
</section>

<!-- ======================== FEATURES ======================== -->
<section class="section">
  <div class="container">
    <div class="text-center mb-4 reveal">
      <div class="section-label">Why Choose Us</div>
      <h2 class="section-title">Everything You Need,<br>All in One Place</h2>
    </div>
    <div class="features-grid">
      <?php
      $features = [
        ['fa-robot','AI Room Recommendation','Our smart AI learns your preferences and recommends the perfect room for your needs and budget.'],
        ['fa-bolt','Instant Booking','Book your room in seconds. Real-time availability, instant confirmation, zero hassle.'],
        ['fa-shield-halved','Secure Payments','Multiple payment options — GCash, bank transfer, Maya. All transactions are secure and verified.'],
        ['fa-star','Premium Comfort','Hand-curated rooms with top amenities. From budget dorms to luxury suites, we have it all.'],
        ['fa-headset','24/7 Support','Our AI chatbot and staff are always ready to assist you any time of the day or night.'],
        ['fa-tag','Best Price Guarantee','We offer the best rates, seasonal promos, and exclusive discounts for loyal customers.'],
      ];
      foreach($features as $i => [$icon, $title, $desc]):
      ?>
      <div class="feature-card reveal" style="transition-delay:<?= $i * 0.08 ?>s;">
        <div class="feature-icon"><i class="fa-solid <?= $icon ?>"></i></div>
        <h3 class="feature-title"><?= $title ?></h3>
        <p class="feature-desc"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ======================== AI BANNER ======================== -->
<section style="padding:0 0 80px;">
  <div class="container">
    <div class="ai-banner reveal">
      <div class="ai-banner-icon">🤖</div>
      <div class="ai-banner-content">
        <h3 class="ai-banner-title">AI-Powered Room Recommendations</h3>
        <p class="ai-banner-desc">
          Not sure which room to pick? Let our intelligent AI assistant guide you! 
          Answer a few questions about your preferences, budget, and stay duration — 
          and we'll find the perfect room match for you instantly.
        </p>
        <div class="mt-3" style="display:flex;gap:12px;flex-wrap:wrap;">
          <?php if(isLoggedIn()): ?>
          <a href="user/ai-recommend.php" class="btn btn-sm" style="background:linear-gradient(135deg,#7B5EA7,#4E9AF1);color:white;">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Get AI Recommendation
          </a>
          <?php else: ?>
          <a href="auth/register.php" class="btn btn-sm" style="background:linear-gradient(135deg,#7B5EA7,#4E9AF1);color:white;">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Try AI Recommendation
          </a>
          <?php endif; ?>
          <button class="btn btn-ghost btn-sm" onclick="openAIChat()">
            <i class="fa-solid fa-comment-dots"></i> Chat with AI
          </button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ======================== ROOMS ======================== -->
<section class="section" style="background:var(--navy-mid);">
  <div class="container">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:40px;" class="reveal">
      <div>
        <div class="section-label">Available Rooms</div>
        <h2 class="section-title">Find Your Perfect Room</h2>
      </div>
      <a href="rooms.php" class="btn btn-outline">
        View All Rooms <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <div class="rooms-grid">
      <?php if(empty($rooms)): ?>
        <div class="empty-state" style="grid-column:1/-1;">
          <div class="empty-state-icon"><i class="fa-solid fa-bed"></i></div>
          <div class="empty-state-title">No rooms available right now</div>
          <div class="empty-state-desc">Please check back soon.</div>
        </div>
      <?php else: ?>
        <?php foreach($rooms as $i => $room):
          $amenities = safeJson($room['amenities']);
          $tags = safeJson($room['tags']);
          $rating = roomAvgRating($room['id']);
          $occ = getRoomOccupancyStatus((int)$room['id'], $room['status'], $allBookingsMap[(int)$room['id']] ?? []);
        ?>
        <div class="room-card reveal" style="transition-delay:<?= $i * 0.06 ?>s;">
          <div class="room-card-img">
            <img src="<?= roomThumbUrl($room['thumbnail']) ?>"
                 alt="<?= h($room['name']) ?>"
                 loading="lazy"
                 decoding="async"
                 onerror="this.src='assets/images/rooms/default-room.jpg'">
            <div class="room-card-badge">
              <?= $occ['tag_html'] ?>
            </div>
            <button class="room-card-fav" aria-label="Save room">
              <i class="fa-regular fa-heart"></i>
            </button>
          </div>
          <div class="room-card-body">
            <div style="display:flex;justify-content:space-between;align-items:start;gap:8px;margin-bottom:6px;">
              <h3 class="room-card-name"><?= h($room['name']) ?></h3>
              <span class="badge badge-gold"><?= h($room['type_name']) ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
              <span class="stars">
                <?php for($s=1;$s<=5;$s++): ?>
                  <i class="fa-<?= $s <= round($rating) ? 'solid' : 'regular' ?> fa-star"></i>
                <?php endfor; ?>
              </span>
              <span style="font-size:0.78rem;color:var(--text-muted);">
                (<?= $rating > 0 ? $rating : 'New' ?>)
                · Floor <?= $room['floor'] ?>
                · <?= $room['capacity'] ?> Pax
              </span>
            </div>
            <?php if ($occ['state'] === 'occupied'): ?>
              <div style="font-size:0.74rem;color:#f87171;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);border-radius:6px;padding:4px 8px;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-bed"></i> Occupied until <?= formatDate($occ['current_until']) ?> · Future dates open
              </div>
            <?php elseif ($occ['state'] === 'reserved'): ?>
              <div style="font-size:0.74rem;color:#fbbf24;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.25);border-radius:6px;padding:4px 8px;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-calendar-check"></i> Booked <?= formatDate($occ['next_booking']['check_in'], 'M d') ?> – <?= formatDate($occ['next_booking']['check_out'], 'M d') ?> · Other dates open
              </div>
            <?php endif; ?>
            <div class="room-card-amenities">
              <?php foreach(array_slice($amenities, 0, 4) as $a): ?>
              <span class="room-amenity-tag"><i class="fa-solid fa-check" style="color:var(--gold);font-size:0.6rem;"></i> <?= h($a) ?></span>
              <?php endforeach; ?>
              <?php if(count($amenities) > 4): ?>
              <span class="room-amenity-tag">+<?= count($amenities) - 4 ?> more</span>
              <?php endif; ?>
            </div>
            <div class="room-card-footer">
              <div>
                <div class="room-price-main"><?= formatCurrency($room['price_per_night']) ?></div>
                <div class="room-price-sub">per night</div>
              </div>
              <a href="<?= isLoggedIn() ? 'user/booking.php?room_id='.$room['id'] : 'auth/login.php?redirect='.urlencode('user/booking.php?room_id='.$room['id']) ?>" 
                 class="btn btn-gold btn-sm" id="bookBtn<?= $room['id'] ?>">
                Book Now
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ======================== TESTIMONIALS ======================== -->
<section class="section" id="reviews-section">
  <div class="container">
    <?= renderFlash() ?>
    <div class="text-center mb-4 reveal">
      <div class="section-label">Customer Reviews</div>
      <h2 class="section-title">What Our Customers Say</h2>
      <p style="color:var(--text-muted);font-size:0.92rem;max-width:540px;margin:8px auto 20px;">
        Real ratings and verified guest feedback from our hotel visitors and dormitory occupants.
      </p>
      <div>
        <button type="button" class="btn btn-gold btn-sm" onclick="openWriteReviewModal()" style="font-weight:700;box-shadow:0 4px 15px rgba(201,168,76,0.3);padding:8px 20px;">
          <i class="fa-solid fa-star"></i> Write a Review
        </button>
      </div>
    </div>
    <div class="testimonials-grid">
      <?php
      $dbReviews = dbRows("
          SELECT rev.*, 
                 u.first_name, u.last_name, u.avatar,
                 r.name as room_name
          FROM reviews rev
          JOIN users u ON rev.user_id = u.id
          LEFT JOIN rooms r ON rev.room_id = r.id
          WHERE rev.status = 'approved'
          ORDER BY rev.id DESC
          LIMIT 9
      ");

      $fallbackReviews = [
        ['name' => 'Maria L.','type' => 'Regular Customer','comment' => 'The AI actually recommended the perfect room for my budget! Staff was amazing, room was spotless. Will definitely come back.', 'rating' => 5],
        ['name' => 'Carlos D.','type' => 'Business Traveler','comment' => 'Stayed in the Studio unit for a week. The workspace setup was great and WiFi was fast. Exactly what I needed.', 'rating' => 5],
        ['name' => 'Ana R.','type' => 'College Student','comment' => 'The dorm was super clean and affordable. The online booking was so easy and my payment was verified fast!', 'rating' => 4],
        ['name' => 'James T.','type' => 'Family Trip','comment' => 'Booked the Family Room for 4 of us. Spacious, comfortable, and the breakfast was delicious. Kids loved it!', 'rating' => 5],
        ['name' => 'Lisa M.','type' => 'Couple','comment' => 'The Deluxe Room with balcony was breathtaking! We watched the sunrise together. Most romantic stay ever.', 'rating' => 5],
        ['name' => 'Renz P.','type' => 'Solo Backpacker','comment' => 'Cheapest option with the cleanest dorm I\'ve ever stayed in. AI chatbot helped me pick the right bed type!', 'rating' => 4],
      ];

      $displayReviews = [];
      foreach ($dbReviews as $dbr) {
          $authorName = trim($dbr['first_name'] . ' ' . (!empty($dbr['last_name']) ? substr($dbr['last_name'], 0, 1) . '.' : ''));
          $displayReviews[] = [
              'name' => $authorName ?: 'Verified Guest',
              'type' => !empty($dbr['room_name']) ? 'Stayed in ' . $dbr['room_name'] : 'Verified Guest',
              'comment' => $dbr['comment'],
              'rating' => (int)$dbr['rating'],
              'avatar' => $dbr['avatar'] ?? null,
              'is_db' => true,
              'date' => formatDate($dbr['created_at'], 'M d, Y')
          ];
      }

      $fIdx = 0;
      while (count($displayReviews) < 6 && isset($fallbackReviews[$fIdx])) {
          $fb = $fallbackReviews[$fIdx];
          $displayReviews[] = [
              'name' => $fb['name'],
              'type' => $fb['type'],
              'comment' => $fb['comment'],
              'rating' => $fb['rating'],
              'avatar' => null,
              'is_db' => false,
              'date' => null
          ];
          $fIdx++;
      }

      foreach($displayReviews as $i => $rv):
        $stars = $rv['rating'];
      ?>
      <div class="testimonial-card reveal" style="transition-delay:<?= $i * 0.07 ?>s;position:relative;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
          <div class="testimonial-stars" style="margin:0;">
            <?php for($s=1;$s<=5;$s++): ?>
              <i class="fa-<?= $s <= $stars ? 'solid' : 'regular' ?> fa-star" style="color:var(--gold);"></i>
            <?php endfor; ?>
          </div>
          <?php if (!empty($rv['is_db'])): ?>
            <span class="badge" style="background:rgba(34,197,94,0.15);color:#4ade80;font-size:0.65rem;padding:2px 6px;border:1px solid rgba(34,197,94,0.3);">
              <i class="fa-solid fa-circle-check"></i> Verified Stay
            </span>
          <?php endif; ?>
        </div>
        <p class="testimonial-text">"<?= h($rv['comment']) ?>"</p>
        <div class="testimonial-author">
          <?php if (!empty($rv['avatar']) && $rv['avatar'] !== 'default-avatar.png'): ?>
            <img src="<?= h(getUserAvatarUrl($rv['avatar'])) ?>" alt="<?= h($rv['name']) ?>" class="testimonial-avatar" style="object-fit:cover;" onerror="this.outerHTML='<div class=\'testimonial-avatar\'><?= strtoupper($rv['name'][0]) ?></div>'">
          <?php else: ?>
            <div class="testimonial-avatar"><?= strtoupper($rv['name'][0]) ?></div>
          <?php endif; ?>
          <div>
            <div class="testimonial-name"><?= h($rv['name']) ?></div>
            <div class="testimonial-label">
              <?= h($rv['type']) ?>
              <?php if (!empty($rv['date'])): ?>
                &bull; <?= $rv['date'] ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ======================== CTA ======================== -->
<section class="section">
  <div class="container">
    <div class="cta-section reveal">
      <div class="section-label" style="justify-content:center;">Book Your Stay Today</div>
      <h2 class="section-title">Ready for an Unforgettable Stay?</h2>
      <p class="section-desc" style="margin:0 auto 32px; max-width:480px;">
        Register now and get access to exclusive deals, AI-powered recommendations, 
        and a seamless booking experience.
      </p>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
        <?php if(!isLoggedIn()): ?>
        <a href="auth/register.php" class="btn btn-gold btn-lg" id="ctaRegisterBtn">
          <i class="fa-solid fa-user-plus"></i> Create Account — It's Free
        </a>
        <a href="rooms.php" class="btn btn-outline btn-lg">
          Browse Rooms First
        </a>
        <?php else: ?>
        <a href="user/booking.php" class="btn btn-gold btn-lg" id="ctaBookBtn">
          <i class="fa-solid fa-calendar-plus"></i> Book a Room Now
        </a>
        <a href="user/dashboard.php" class="btn btn-outline btn-lg">
          Go to Dashboard
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- ======================== FOOTER ======================== -->
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

<!-- ======================== AI CHATBOT WIDGET ======================== -->
<?php require_once __DIR__ . '/includes/components/ai-chatbot.php'; ?>

<!-- ======================== CENTRALIZED NAVIGATION (THREE-DOT MENU) ======================== -->
<?php 
require_once __DIR__ . '/includes/components/central-nav.php'; 
renderCentralNav('home');
?>

<script src="assets/js/main.js"></script>
<script>
  // Navbar scroll effect
  window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 30);
  });

  // Reveal on scroll
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if(e.isIntersecting) e.target.classList.add('visible'); });
  }, { threshold: 0.08 });
  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

  // Particles
  function createParticles() {
    const container = document.getElementById('particles');
    for(let i = 0; i < 25; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      p.style.cssText = `
        left:${Math.random()*100}%;
        bottom:${Math.random()*30}%;
        animation-duration:${4 + Math.random()*8}s;
        animation-delay:${Math.random()*6}s;
        opacity:${0.3 + Math.random()*0.5};
        width:${1 + Math.random()*2}px;
        height:${1 + Math.random()*2}px;
      `;
      container.appendChild(p);
    }
  }
  createParticles();

  // Quick search
  function performSearch() {
    const checkIn = document.getElementById('searchCheckIn').value;
    const checkOut = document.getElementById('searchCheckOut').value;
    const guests = document.getElementById('searchGuests').value;
    const type = document.getElementById('searchType').value;
    let params = new URLSearchParams();
    if(checkIn) params.append('check_in', checkIn);
    if(checkOut) params.append('check_out', checkOut);
    if(guests) params.append('guests', guests.charAt(0));
    if(type !== 'All Types') params.append('type', type);
    window.location.href = 'rooms.php?' + params.toString();
  }

  // Set default search dates
  const today = new Date().toISOString().split('T')[0];
  const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
  document.getElementById('searchCheckIn').value = today;
  document.getElementById('searchCheckOut').value = tomorrow;

  // Centralized Navigation & Mobile Drawer handlers (public.js handles primary, fallback here)
  const menuTrigger = document.getElementById('navMenuBtn') || document.getElementById('navHamburger');
  if (menuTrigger) {
    menuTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      if (typeof window.toggleCentralNav === 'function') {
        window.toggleCentralNav();
      } else {
        const cn = document.getElementById('centralNav');
        const cno = document.getElementById('centralNavOverlay');
        if (cn) cn.classList.toggle('open');
        if (cno) cno.classList.toggle('open');
      }
    });
  }

  // Write Review Modal & Interactive Star Picker
  function openWriteReviewModal() {
    <?php if (!isLoggedIn()): ?>
      window.location.href = 'auth/login.php?redirect=' + encodeURIComponent('index.php#reviews-section');
      return;
    <?php else: ?>
      document.getElementById('writeReviewModal').style.display = 'flex';
    <?php endif; ?>
  }

  function closeWriteReviewModal() {
    var m = document.getElementById('writeReviewModal');
    if (m) m.style.display = 'none';
  }

  (function() {
    const starLabels = {
      1: '1.0 — Needs Improvement',
      2: '2.0 — Below Average',
      3: '3.0 — Good & Decent Stay',
      4: '4.0 — Very Good Experience',
      5: '5.0 — Excellent & Outstanding!'
    };

    const container = document.getElementById('starPickerContainer');
    const input = document.getElementById('reviewRatingInput');
    const label = document.getElementById('starRatingLabel');

    if (container && input && label) {
      const stars = container.querySelectorAll('.fa-star');

      function highlightStars(val) {
        stars.forEach(s => {
          const sVal = parseInt(s.getAttribute('data-val'), 10);
          if (sVal <= val) {
            s.classList.remove('fa-regular');
            s.classList.add('fa-solid');
          } else {
            s.classList.remove('fa-solid');
            s.classList.add('fa-regular');
          }
        });
        label.textContent = starLabels[val] || (val + '.0 Stars');
      }

      stars.forEach(s => {
        s.addEventListener('mouseenter', () => {
          highlightStars(parseInt(s.getAttribute('data-val'), 10));
        });

        s.addEventListener('click', () => {
          const chosen = parseInt(s.getAttribute('data-val'), 10);
          input.value = chosen;
          highlightStars(chosen);
        });
      });

      container.addEventListener('mouseleave', () => {
        highlightStars(parseInt(input.value || 5, 10));
      });
    }
  })();
</script>

<!-- ======================== WRITE A REVIEW MODAL ======================== -->
<div id="writeReviewModal" style="display:none;position:fixed;inset:0;background:rgba(5,10,24,0.85);backdrop-filter:blur(8px);z-index:9999;align-items:center;justify-content:center;padding:20px;" onclick="closeWriteReviewModal()">
  <div class="glass-card" style="max-width:540px;width:100%;max-height:90vh;overflow-y:auto;padding:32px;background:var(--navy-card,#0f172a);border:1px solid rgba(201,168,76,0.3);border-radius:18px;box-shadow:0 25px 60px rgba(0,0,0,0.7);" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:20px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="width:40px;height:40px;border-radius:10px;background:rgba(201,168,76,0.15);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:1.2rem;">
          <i class="fa-solid fa-star"></i>
        </span>
        <div>
          <h3 style="color:#fff;font-size:1.25rem;margin:0;font-family:var(--font-serif);">Rate Your Stay Experience</h3>
          <div style="font-size:0.8rem;color:var(--text-muted);">Share your feedback to appear on Customer Reviews</div>
        </div>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeWriteReviewModal()" style="font-size:1.3rem;padding:4px 8px;color:#94a3b8;">&times;</button>
    </div>

    <form action="index.php" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="submit_homepage_review" value="1">
      <input type="hidden" name="rating" id="reviewRatingInput" value="5">

      <!-- Interactive Star Picker -->
      <div style="text-align:center;margin-bottom:20px;background:rgba(255,255,255,0.03);padding:18px;border-radius:12px;border:1px solid var(--border);">
        <div style="font-size:0.75rem;text-transform:uppercase;color:var(--gold);font-weight:700;margin-bottom:8px;">Your Star Rating</div>
        <div id="starPickerContainer" style="font-size:2.2rem;color:var(--gold);cursor:pointer;display:inline-flex;gap:8px;">
          <i class="fa-solid fa-star" data-val="1"></i>
          <i class="fa-solid fa-star" data-val="2"></i>
          <i class="fa-solid fa-star" data-val="3"></i>
          <i class="fa-solid fa-star" data-val="4"></i>
          <i class="fa-solid fa-star" data-val="5"></i>
        </div>
        <div id="starRatingLabel" style="font-size:0.88rem;color:#cbd5e1;font-weight:600;margin-top:6px;">5.0 — Excellent &amp; Outstanding!</div>
      </div>

      <!-- Stay / Room Selection -->
      <div class="form-group" style="margin-bottom:16px;">
        <label class="form-label" style="font-size:0.85rem;font-weight:600;color:#e2e8f0;">Stay / Room Experience</label>
        <select name="booking_id" class="form-control" style="font-size:0.875rem;">
          <?php if (!empty($userBookings)): ?>
            <?php foreach ($userBookings as $ub): ?>
              <option value="<?= $ub['id'] ?>">Booking #<?= h($ub['booking_code']) ?> — <?= h($ub['room_name']) ?></option>
            <?php endforeach; ?>
          <?php endif; ?>
          <option value="0">General Stay &amp; Customer Experience</option>
        </select>
      </div>

      <!-- Feedback comment -->
      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label" style="font-size:0.85rem;font-weight:600;color:#e2e8f0;">Your Honest Feedback &amp; Review</label>
        <textarea name="comment" class="form-control" rows="4" placeholder="How was your stay, cleanliness, hospitality, and amenities?..." required style="font-size:0.875rem;resize:vertical;"></textarea>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeWriteReviewModal()">Cancel</button>
        <button type="submit" class="btn btn-gold" style="font-weight:700;padding:10px 24px;">
          <i class="fa-solid fa-paper-plane"></i> Post Review
        </button>
      </div>
    </form>
  </div>
</div>

</script>
<script src="assets/js/core.js"></script>
<script src="assets/js/public.js"></script>
<script src="assets/js/share.js"></script>
</body>
</html>
