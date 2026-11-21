<?php
http_response_code(404);
require_once __DIR__ . '/../../backend/core/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — Page Not Found</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Outfit', sans-serif;
      background: #0b1a33;
      color: #fff;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 24px;
    }
    .wrap { max-width: 560px; }
    .code {
      font-family: 'Playfair Display', serif;
      font-size: clamp(5rem, 18vw, 9rem);
      font-weight: 700;
      color: #d4a437;
      line-height: 1;
    }
    h1 { font-family: 'Playfair Display', serif; font-size: 1.8rem; margin: 18px 0 10px; }
    p { color: #a8b4c8; line-height: 1.6; margin-bottom: 32px; }
    .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 26px; border-radius: 8px;
      text-decoration: none; font-weight: 600; font-size: 0.95rem;
      transition: transform .2s, opacity .2s;
    }
    .btn:hover { transform: translateY(-2px); opacity: .9; }
    .btn-gold { background: #d4a437; color: #0b1a33; }
    .btn-ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.25); }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="code">404</div>
    <h1>Page Not Found or Moved</h1>
    <p>The page you are looking for is no longer here or has moved to a different address. Try going back to the homepage or browse our rooms.</p>
    <div class="actions">
      <a href="<?= SITE_URL ?>/index.php" class="btn btn-gold"><i class="fa-solid fa-house"></i> Back to Home</a>
      <a href="<?= SITE_URL ?>/rooms.php" class="btn btn-ghost"><i class="fa-solid fa-bed"></i> View Rooms</a>
    </div>
  </div>
</body>
</html>
