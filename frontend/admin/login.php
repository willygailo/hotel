<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/core/rate-limiter.php';

applyAuthRateLimit('admin_login');

// If already logged in as admin, redirect to admin dashboard
if (isLoggedIn() && isAdmin()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token    = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security session token. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter your Admin Email and Password.';
    } else {
        $user = dbRow("SELECT * FROM users WHERE email = ? AND role = 'admin'", [$email]);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your admin account is inactive or locked.';
                logAuth('admin_login', $email, false, ['reason' => $user['status'] === 'banned' ? 'banned' : 'inactive']);
            } else {
                // Session fixation defense: bagong session ID bago mag-login
                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['user_name']   = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email']  = $user['email'];
                $_SESSION['user_role']   = 'admin';
                $_SESSION['user_avatar'] = $user['avatar'] ?: 'default-avatar.png';

                dbExec("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                auditLog('Admin Logged In via Dedicated Portal', 'Admin Auth', "Admin {$user['email']} authenticated.");
                logAuth('admin_login', $email, true);

                header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = 'Invalid Admin credentials. This portal is exclusively for Administrators.';
            auditLog('Admin Login Failed (Bad Password)', 'Admin Auth', "Failed admin login attempt for email: {$email}");
            logAuth('admin_login', $email, false, ['reason' => 'invalid_credentials']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Executive Login — Marvelous Hotel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/admin.css')) ?>">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#060919;">

  <div class="glass-card" style="width:100%;max-width:440px;padding:36px;box-shadow:var(--shadow-deep);border:1px solid rgba(233,69,96,0.3);">
    <div style="text-align:center;margin-bottom:28px;">
      <div style="width:50px;height:50px;background:linear-gradient(135deg,var(--gold),#e94560);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;margin-bottom:12px;box-shadow:0 8px 24px rgba(233,69,96,0.3);">👑</div>
      <h2 style="font-family:var(--font-serif);font-size:1.6rem;color:#fff;">Admin Executive Portal</h2>
      <p style="font-size:0.8rem;color:#ff899b;margin-top:4px;letter-spacing:1px;text-transform:uppercase;font-weight:700;">Restricted Management Server</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error"><i class="fa-solid fa-circle-xmark"></i> <span><?= h($error) ?></span></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label">Admin Gmail / Email Address <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-user-shield form-icon" style="color:var(--gold);"></i>
          <input type="email" name="email" class="form-control" placeholder="admin@domain.com" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Admin Master Password <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-key form-icon" style="color:var(--gold);"></i>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:14px;background:linear-gradient(135deg,var(--gold),#e94560);color:#fff;">
        <i class="fa-solid fa-lock-open"></i> Enter Admin Server
      </button>
    </form>

    <div style="text-align:center;margin-top:24px;font-size:0.82rem;color:var(--text-muted);border-top:1px solid rgba(255,255,255,0.06);padding-top:18px;">
      <a href="<?= h(USER_SITE_URL . '/index.php') ?>" style="color:var(--text-muted);">&larr; Back to Public Website</a>
    </div>
  </div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/admin.js')) ?>"></script>
</body>
</html>
