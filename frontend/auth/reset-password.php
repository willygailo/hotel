<?php
// ============================================================
// MARVELOUS HOTEL — Reset Password
// auth/reset-password.php
// ============================================================

require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/core/rate-limiter.php';

if (isLoggedIn()) {
    if (!isUser() || (empty($_GET['token']) && empty($_SESSION['password_reset_watch']['token']))) {
        redirectIfLoggedIn();
    }
}

applyAuthRateLimit('reset');
ensurePasswordResetSchema();

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';
$validToken = false;
$user = null;

// Validate token using PHP timezone timestamp to match reset_expires written by PHP
if ($token) {
    $user = dbRow(
        "SELECT id, first_name, last_name, email FROM users 
         WHERE reset_token = ? AND reset_expires > ? AND role = 'user'",
        [$token, appNowSql()]
    );
    if ($user) {
        $validToken = true;
    } else {
        $error = 'Invalid or expired reset link. Please request again.';
    }
} else {
    $error = 'No reset token found.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $tokenPost = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!verifyCsrfToken($tokenPost)) {
        $error = 'Invalid security session token. Please try again.';
    } elseif (empty($password) || empty($confirmPass)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 8) {
        $error = 'The password must be at least 8 characters long.';
    } elseif ($password !== $confirmPass) {
        $error = 'The password confirmation does not match.';
    } else {
        $hashedPass = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);

        // Update password and clear reset token & temporary credentials
        dbExec(
            "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL, updated_at = NOW() WHERE id = ?",
            [$hashedPass, $user['id']]
        );
        clearAdminTempPassword((int)$user['id']);
        consumePasswordResetWatch();

        auditLog('Password Reset Completed', 'User Auth', "Password changed for user {$user['email']}");
        // Session fixation defense: bagong session ID pagkatapos magbago ng password
        session_regenerate_id(true);
        
        setFlash('success', 'Your password was updated successfully. You can now log in.');
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — Marvelous Hotel</title>
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
      <h2 style="font-family:var(--font-serif);font-size:1.5rem;color:#fff;">Reset Password</h2>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;"><?= $validToken ? 'Enter your new password' : 'Invalid or expired link' ?></p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error" style="margin-bottom:20px;">
        <i class="fa-solid fa-circle-xmark"></i>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($validToken): ?>
    <form action="" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="token" value="<?= h($token) ?>">

      <div class="form-group">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <label class="form-label" style="margin-bottom:0;">New Password <span class="required">*</span></label>
        </div>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-lock form-icon"></i>
          <input type="password" name="password" id="newPass" class="form-control" placeholder="••••••••" required autofocus minlength="8">
          <i class="fa-solid fa-eye form-icon-right" onclick="togglePass('newPass')"></i>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm Password <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-lock form-icon"></i>
          <input type="password" name="confirm_password" id="confirmPass" class="form-control" placeholder="••••••••" required minlength="8">
          <i class="fa-solid fa-eye form-icon-right" onclick="togglePass('confirmPass')"></i>
        </div>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:10px;">
        <i class="fa-solid fa-key"></i> Change Password
      </button>
    </form>
    <?php endif; ?>

    <div style="margin-top:24px;text-align:center;font-size:0.88rem;color:var(--text-muted);border-top:1px solid var(--border-subtle);padding-top:20px;">
      <a href="forgot-password.php" style="color:var(--gold);font-weight:700;">&larr; Request Again</a> | 
      <a href="login.php" style="color:var(--gold);font-weight:700; margin-left:10px;">Log In &rarr;</a>
    </div>
  </div>

<script>
  function togglePass(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
  }
</script>
<script src="../frontend/assets/js/core.js"></script>
<?php
if (!$validToken && !empty($_SESSION['password_reset_watch']['token'])) {
    require_once __DIR__ . '/../components/reset-status-modal.php';
    renderPasswordResetStatusModal([
        'poll_interval_ms' => 4000,
        'auto_start'       => true,
    ]);
}
?>
</body>
</html>