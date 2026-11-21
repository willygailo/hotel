<?php
// ============================================================
// MARVELOUS HOTEL — Resend Verification Email
// auth/resend-verification.php
// ============================================================

require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/core/email.php';
require_once __DIR__ . '/../../backend/core/rate-limiter.php';

redirectIfLoggedIn();

applyAuthRateLimit('resend');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security session token. Please try again.';
    } elseif (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Always show success to prevent email enumeration
        $success = 'If the email is registered and not yet verified, we will send the verification link again.';

        $user = dbRow("SELECT id, first_name, last_name, email, email_verified FROM users WHERE email = ? AND role = 'user'", [$email]);
        
        if ($user && !$user['email_verified']) {
            // Generate new verification token
            $verifyToken = bin2hex(random_bytes(32));
            
            dbExec(
                "UPDATE users SET verify_token = ?, updated_at = NOW() WHERE id = ?",
                [$verifyToken, $user['id']]
            );

            $verifyLink = SITE_URL . '/auth/verify-email.php?token=' . $verifyToken;
            $userName = $user['first_name'] . ' ' . $user['last_name'];
            $htmlBody = emailTemplateVerifyEmail($verifyLink, $userName);
            $textBody = "Verify Your Email\n\nWelcome {$userName},\n\nPlease verify your email by clicking: {$verifyLink}\n\nIf you didn't create this account, please ignore this email.\n\n" . SITE_NAME;

            sendEmail($user['email'], 'Verify Your Email - ' . SITE_NAME, $htmlBody, $textBody);
            
            auditLog('Verification Email Resent', 'User Auth', "Verification email resent to {$user['email']}");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resend Verification — Marvelous Hotel</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/auth.css">
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
      <h2 style="font-family:var(--font-serif);font-size:1.5rem;color:#fff;">Resend Verification Email</h2>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Enter the email to resend the verification link</p>
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

    <form action="" method="POST">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label">Email Address <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-envelope form-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="name@email.com" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
        </div>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:10px;">
        <i class="fa-solid fa-paper-plane"></i> Send Verification Link
      </button>
    </form>

    <div style="margin-top:24px;text-align:center;font-size:0.88rem;color:var(--text-muted);border-top:1px solid var(--border-subtle);padding-top:20px;">
      <a href="login.php" style="color:var(--gold);font-weight:700;">&larr; Back to Login</a>
    </div>
  </div>

<script src="../frontend/assets/js/core.js"></script>
</body>
</html>