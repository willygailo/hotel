<?php
// ============================================================
// MARVELOUS HOTEL — Verify Email
// auth/verify-email.php
// ============================================================

require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

redirectIfLoggedIn();

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';
$verified = false;

if ($token) {
    $user = dbRow(
        "SELECT id, email FROM users WHERE verify_token = ? AND email_verified = 0 AND role = 'user'",
        [$token]
    );
    
    if ($user) {
        // Verify the email
        dbExec(
            "UPDATE users SET email_verified = 1, verify_token = NULL, updated_at = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        auditLog('Email Verified', 'User Auth', "Email verified for user {$user['email']}");
        $verified = true;
        $success = 'Your email was verified successfully! You can now log in.';
    } else {
        $error = 'Invalid or already used verification link.';
    }
} else {
    $error = 'No verification token found.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Email — Marvelous Hotel</title>
  <?php require_once __DIR__ . '/../components/page-head.php'; renderPerformanceHeadLinks(false); ?>
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/auth.css')) ?>">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:var(--navy);">

  <div class="glass-card" style="width:100%;max-width:440px;padding:36px;box-shadow:var(--shadow-deep);position:relative;">
    <div style="text-align:center;margin-bottom:28px;">
      <a href="../index.php" style="display:inline-flex;align-items:center;gap:10px;margin-bottom:12px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--navy);font-weight:800;font-size:1.2rem;">M</div>
        <div style="text-align:left;line-height:1.1;">
          <div style="font-family:var(--font-serif);font-size:1.1rem;font-weight:700;color:var(--gold);">Marvelous Hotel</div>
          <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">& Dormitory</div>
        </div>
      </a>
      <h2 style="font-family:var(--font-serif);font-size:1.5rem;color:#fff;">Verify Your Email</h2>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;"><?= $verified ? 'Email verified successfully!' : 'Verifying your email address...' ?></p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error" style="margin-bottom:20px;">
        <i class="fa-solid fa-circle-xmark"></i>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="flash-message flash-success" style="margin-bottom:20px;">
        <i class="fa-solid fa-circle-check"></i>
        <span><?= h($success) ?></span>
      </div>
    <?php endif; ?>

    <div style="text-align:center;margin-top:24px;font-size:0.88rem;color:var(--text-muted);border-top:1px solid var(--border-subtle);padding-top:20px;">
      <a href="login.php" style="color:var(--gold);font-weight:700;">Log In &rarr;</a> | 
      <a href="register.php" style="color:var(--gold);font-weight:700;margin-left:10px;">Register &rarr;</a>
    </div>
  </div>

<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
</body>
</html>