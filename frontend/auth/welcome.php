<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/session.php';

// Naka-login na? Diretso sa main website (o dashboard kung admin/staff)
redirectIfLoggedIn();

$redirect = $_GET['redirect'] ?? '';
$qs = $redirect !== '' ? '?redirect=' . urlencode($redirect) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome — Marvelous Hotel</title>
  <meta name="description" content="Welcome to Marvelous Hotel & Dormitory. Register or log in to explore and reserve rooms.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= SITE_URL ?>/auth/welcome.php">
  <meta property="og:title" content="Welcome — Marvelous Hotel & Dormitory">
  <meta property="og:description" content="Register or log in to explore and reserve rooms at Marvelous Hotel & Dormitory.">
  <meta property="og:image" content="<?= ASSETS_URL ?>/images/hotel/hero-bg.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Welcome — Marvelous Hotel & Dormitory">
  <meta name="twitter:description" content="Register or log in to explore and reserve rooms at Marvelous Hotel & Dormitory.">
  <meta name="twitter:image" content="<?= ASSETS_URL ?>/images/hotel/hero-bg.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/auth.css">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:var(--navy);">

  <div class="glass-card" style="width:100%;max-width:520px;padding:40px 36px;box-shadow:var(--shadow-deep);">

    <div style="text-align:center;margin-bottom:30px;">
      <a href="../index.php" style="display:inline-flex;align-items:center;gap:10px;margin-bottom:14px;">
        <div style="width:44px;height:44px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:11px;display:flex;align-items:center;justify-content:center;color:var(--navy);font-weight:800;font-size:1.3rem;">M</div>
        <div style="text-align:left;line-height:1.1;">
          <div style="font-family:var(--font-serif);font-size:1.2rem;font-weight:700;color:var(--gold);">Marvelous Hotel</div>
          <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">&amp; Dormitory</div>
        </div>
      </a>
      <h1 style="font-family:var(--font-serif);font-size:1.7rem;color:#fff;">Welcome!</h1>
      <p style="font-size:0.9rem;color:var(--text-muted);margin-top:8px;line-height:1.6;">
        You need an account first before entering the hotel's main website.
        Choose one:
      </p>
    </div>

    <div style="display:flex;flex-direction:column;gap:14px;">

      <a href="register.php<?= $qs ?>" style="display:flex;align-items:center;gap:16px;padding:20px 22px;border-radius:14px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));color:var(--navy);text-decoration:none;transition:transform .15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
        <div style="width:46px;height:46px;border-radius:12px;background:rgba(15,22,36,.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">
          <i class="fa-solid fa-user-plus"></i>
        </div>
        <div style="text-align:left;">
          <div style="font-weight:800;font-size:1.05rem;">Register</div>
          <div style="font-size:0.8rem;opacity:.8;">New here — create a new account</div>
        </div>
        <i class="fa-solid fa-arrow-right" style="margin-left:auto;"></i>
      </a>

      <a href="login.php<?= $qs ?>" style="display:flex;align-items:center;gap:16px;padding:20px 22px;border-radius:14px;background:rgba(255,255,255,.06);border:1px solid var(--border-subtle);color:#fff;text-decoration:none;transition:transform .15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
        <div style="width:46px;height:46px;border-radius:12px;background:rgba(212,175,55,.15);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:1.2rem;flex-shrink:0;">
          <i class="fa-solid fa-right-to-bracket"></i>
        </div>
        <div style="text-align:left;">
          <div style="font-weight:800;font-size:1.05rem;">Log In</div>
          <div style="font-size:0.8rem;color:var(--text-muted);">Already have an account? Log in here</div>
        </div>
        <i class="fa-solid fa-arrow-right" style="margin-left:auto;color:var(--text-muted);"></i>
      </a>

    </div>

  </div>

<script src="../frontend/assets/js/core.js"></script>
</body>
</html>
