<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/core/rate-limiter.php';

applyAuthRateLimit('staff_login');

// If already logged in as staff, redirect to staff dashboard
if (isLoggedIn() && isStaff()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $token    = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security session token.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter your Staff Email and Password.';
    } else {
        $user = dbRow("SELECT * FROM users WHERE email = ? AND role = 'staff'", [$email]);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'inactive' || $user['status'] === 'banned') {
                $error = 'Your staff access is deactivated or suspended.';
                logAuth('staff_login', $email, false, ['reason' => 'inactive_or_banned']);
            } else {
                // Session fixation defense: bagong session ID bago mag-login
                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['user_name']   = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email']  = $user['email'];
                $_SESSION['user_role']   = 'staff';
                $_SESSION['user_avatar'] = $user['avatar'] ?: 'default-avatar.png';

                dbExec("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                auditLog('Staff Logged In via Dedicated Portal', 'Staff Auth', "Staff {$user['email']} authenticated.");
                logAuth('staff_login', $email, true);

                header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = 'Invalid Staff credentials. This portal is for authorized Staff/Frontdesk only.';
            auditLog('Staff Login Failed (Bad Password)', 'Staff Auth', "Failed staff login attempt for email: {$email}");
            logAuth('staff_login', $email, false, ['reason' => 'invalid_credentials']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Frontdesk Login — Marvelous Hotel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/staff.css')) ?>">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#080d24;">

  <div class="glass-card" style="width:100%;max-width:440px;padding:36px;box-shadow:var(--shadow-deep);border:1px solid rgba(78,154,241,0.3);">
    <div style="text-align:center;margin-bottom:28px;">
      <div style="width:50px;height:50px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:14px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;margin-bottom:12px;box-shadow:0 8px 24px rgba(78,154,241,0.3);">👨‍💼</div>
      <h2 style="font-family:var(--font-serif);font-size:1.6rem;color:#fff;">Staff Frontdesk Desk</h2>
      <p style="font-size:0.8rem;color:var(--accent);margin-top:4px;letter-spacing:1px;text-transform:uppercase;font-weight:700;">Employee Operations Server</p>
    </div>

    <div style="background:rgba(78,154,241,0.08);border:1px solid rgba(78,154,241,0.25);border-radius:10px;padding:12px;margin-bottom:20px;text-align:center;">
      <div style="font-size:0.8rem;color:#93c5fd;font-weight:600;"><i class="fa-solid fa-shield-halved"></i> Authorized Staff Only</div>
      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:3px;">
        Ang accounts dito ay eksklusibong nililikha ng Hotel Admin. Gamitin ang ibinigay na credentials upang ma-access ang Frontdesk Dashboard.
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error"><i class="fa-solid fa-circle-xmark"></i> <span><?= h($error) ?></span></div>
    <?php endif; ?>

    <form action="" method="POST">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label">Staff Gmail / Email Address <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-id-badge form-icon" style="color:var(--accent);"></i>
          <input type="email" name="email" class="form-control" placeholder="staff@domain.com" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Staff Password <span class="required">*</span></label>
        <div class="form-icon-wrap" style="position:relative;">
          <i class="fa-solid fa-key form-icon" style="color:var(--accent);"></i>
          <input type="password" name="password" id="staffPassInput" class="form-control" placeholder="••••••••" required style="padding-right:38px;">
          <button type="button" onclick="toggleStaffPass(this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:4px;z-index:2;">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:14px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;">
        <i class="fa-solid fa-bell-concierge"></i> Sign in to Staff Desk
      </button>
    </form>

    <div style="text-align:center;margin-top:24px;font-size:0.82rem;color:var(--text-muted);border-top:1px solid rgba(255,255,255,0.06);padding-top:18px;">
      <a href="<?= h((defined('USER_SITE_URL') ? USER_SITE_URL : '..') . '/index.php') ?>" style="color:var(--text-muted);">&larr; Back to Public Website</a>
    </div>
  </div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/staff.js')) ?>"></script>
<script>
function toggleStaffPass(btn) {
  const inp = document.getElementById('staffPassInput');
  if (inp.type === 'password') {
    inp.type = 'text';
    btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
  } else {
    inp.type = 'password';
    btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
  }
}
</script>
</body>
</html>
