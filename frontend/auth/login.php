<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/core/email.php';
require_once __DIR__ . '/../../backend/core/rate-limiter.php';

redirectIfLoggedIn();

applyAuthRateLimit('login');

$error = '';
$errorType = ''; // 'email' | 'password' | 'role' | 'general'
$redirectUrl = $_GET['redirect'] ?? '';

/**
 * Validates and returns a safe relative redirect URL to prevent Open Redirect vulnerabilities.
 */
function getSafeRedirectUrl(?string $url, string $default = ''): string {
    if (empty($default)) {
        $default = SITE_URL . '/index.php';
    }
    if (empty($url)) {
        return $default;
    }
    $sanitized = filter_var($url, FILTER_SANITIZE_URL);
    if (preg_match('/^(\/[^\/]|(?:\.\.\/)+)/', $sanitized)) {
        return $sanitized;
    }
    return $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember_me']);
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security session token. Please try again.';
        $errorType = 'general';
    } elseif (empty($email)) {
        $error = 'Please enter your Gmail or Email address.';
        $errorType = 'email';
    } elseif (empty($password)) {
        $error = 'Please enter your password.';
        $errorType = 'password';
    } else {
        // 1. Check if ANY user exists with this email
        $account = dbRow("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);

        if (!$account) {
            // SPECIFIC GMAIL ERROR POPUP
            $errorType = 'email';
            $error = "Incorrect or unregistered email address. No account was found for <strong>" . h($email) . "</strong>. Please check the spelling or register a new account.";
            auditLog('Login Failed (Gmail Not Found)', 'User Auth', "Unregistered email attempt: {$email}");
            logAuth('login', $email, false, ['reason' => 'email_not_found']);
        } elseif ($account['role'] === 'staff') {
            $errorType = 'role';
            $error = 'You have a Staff account. Please log in through the Staff Portal (' . STAFF_SITE_URL . '/login.php).';
            auditLog('User Login Mismatch (Staff Account)', 'User Auth', "Staff user {$email} attempted to log in via Guest portal.");
            logAuth('login', $email, false, ['reason' => 'staff_role']);
        } elseif ($account['role'] === 'admin') {
            $errorType = 'role';
            $error = 'You have an Admin account. Please log in through the Admin Portal (' . ADMIN_SITE_URL . '/login.php).';
            auditLog('User Login Mismatch (Admin Account)', 'User Auth', "Admin user {$email} attempted to log in via Guest portal.");
            logAuth('login', $email, false, ['reason' => 'admin_role']);
        } else {
            // Gmail is valid and belongs to a Guest User! Now verify password:
            $trimmedPass = trim($password);
            $passMatches = password_verify($password, $account['password']) || password_verify($trimmedPass, $account['password']);

            if (!$passMatches) {
                // SPECIFIC PASSWORD ERROR POPUP
                $errorType = 'password';
                $error = "Incorrect password for <strong>" . h($email) . "</strong>. Please check your Caps Lock or click 'Forgot Password?' below.";
                auditLog('Login Failed (Wrong Password)', 'User Auth', "Password mismatch for guest: {$email}");
                logAuth('login', $email, false, ['reason' => 'invalid_password']);
            } elseif ($account['status'] === 'banned') {
                $errorType = 'general';
                $error = 'Your account has been suspended. Please contact the hotel management.';
                auditLog('User Login Blocked (Banned)', 'User Auth', "Banned user {$email} attempted to log in.");
                logAuth('login', $email, false, ['reason' => 'banned']);
            } else {
                // SUCCESSFUL AUTHENTICATION!
                $smtpConfigured = isSmtpConfigured();
                if (!$account['email_verified'] && !$smtpConfigured) {
                    dbExec("UPDATE users SET email_verified = 1, verify_token = NULL WHERE id = ?", [$account['id']]);
                    $account['email_verified'] = 1;
                }

                if (!$account['email_verified']) {
                    $errorType = 'general';
                    $error = 'Your email is not yet verified. Check your inbox for the verification link.';
                    auditLog('User Login Blocked (Unverified)', 'User Auth', "Unverified user {$email} attempted to log in.");
                    logAuth('login', $email, false, ['reason' => 'unverified']);
                } else {
                    // Session fixation defense: bagong session ID bago mag-login
                    session_regenerate_id(true);
                    $_SESSION['user_id']     = $account['id'];
                    $_SESSION['user_name']   = formatFullName($account);
                    $_SESSION['user_email']  = $account['email'];
                    $_SESSION['user_role']   = 'user';
                    $_SESSION['user_avatar'] = $account['avatar'] ?: 'default-avatar.png';

                    // Persistent "Remember Me" for 30 days
                    if ($remember) {
                        $rawToken = bin2hex(random_bytes(32));
                        $hashedToken = hash('sha256', $rawToken);
                        $expireTime = date('Y-m-d H:i:s', strtotime('+30 days'));
                        dbExec("UPDATE users SET remember_token = ?, remember_expires = ? WHERE id = ?", [$hashedToken, $expireTime, $account['id']]);
                        setcookie('MH_REMEMBER_USER', $account['id'] . ':' . $rawToken, [
                            'expires'  => time() + (86400 * 30),
                            'path'     => '/',
                            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }

                    dbExec("UPDATE users SET last_login = NOW() WHERE id = ?", [$account['id']]);
                    consumePasswordResetWatch();
                    auditLog('User Logged In', 'User Auth', "Guest {$account['email']} successfully logged in.");
                    logAuth('login', $account['email'], true);

                    $target = getSafeRedirectUrl($redirectUrl, SITE_URL . "/index.php");
                    header("Location: " . $target);
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Marvelous Hotel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/auth.css">
  <style>
    .caps-indicator {
      display: none;
      align-items: center;
      gap: 6px;
      margin-top: 6px;
      font-size: 0.76rem;
      color: #facc15;
      background: rgba(234,179,8,0.12);
      border: 1px solid rgba(234,179,8,0.3);
      padding: 4px 10px;
      border-radius: 6px;
    }
    .input-error-highlight {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239,68,68,0.2) !important;
      animation: shake 0.3s ease;
    }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-6px); }
      40%, 80% { transform: translateX(6px); }
    }
  </style>
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:var(--navy);">

  <div class="glass-card" style="width:100%;max-width:440px;padding:36px;box-shadow:var(--shadow-deep);position:relative;">
    <div style="text-align:center;margin-bottom:24px;">
      <a href="../index.php" style="display:inline-flex;align-items:center;gap:10px;margin-bottom:12px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--navy);font-weight:800;font-size:1.2rem;">M</div>
        <div style="text-align:left;line-height:1.1;">
          <div style="font-family:var(--font-serif);font-size:1.1rem;font-weight:700;color:var(--gold);">Marvelous Hotel</div>
          <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">&amp; Dormitory</div>
        </div>
      </a>
      <h2 style="font-family:var(--font-serif);font-size:1.5rem;color:#fff;">Login to Hotel Website</h2>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Sign in to access rooms, reservations, and hotel services</p>
    </div>

    <?= renderFlash() ?>

    <!-- DISTINCT ERROR POPUP CARD -->
    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error" style="margin-bottom:20px;background:rgba(239,68,68,0.12);border:1px solid #ef4444;border-radius:10px;padding:14px 16px;">
        <div style="display:flex;align-items:flex-start;gap:12px;">
          <?php if ($errorType === 'email'): ?>
            <i class="fa-solid fa-envelope-circle-xmark" style="color:#ef4444;font-size:1.3rem;margin-top:2px;"></i>
          <?php elseif ($errorType === 'password'): ?>
            <i class="fa-solid fa-key" style="color:#f59e0b;font-size:1.3rem;margin-top:2px;"></i>
          <?php else: ?>
            <i class="fa-solid fa-circle-xmark" style="color:#ef4444;font-size:1.3rem;margin-top:2px;"></i>
          <?php endif; ?>
          <div style="flex:1;font-size:0.88rem;color:#fff;line-height:1.45;">
            <?= $error ?>
            <?php if ($errorType === 'email'): ?>
              <div style="margin-top:8px;">
                <a href="register.php?email=<?= urlencode($_POST['email'] ?? '') ?>" style="color:var(--gold);font-weight:700;text-decoration:underline;">
                  <i class="fa-solid fa-user-plus"></i> Register this Email Address &rarr;
                </a>
              </div>
            <?php elseif ($errorType === 'password'): ?>
              <div style="margin-top:8px;">
                <a href="forgot-password.php?email=<?= urlencode($_POST['email'] ?? '') ?>" style="color:var(--gold);font-weight:700;text-decoration:underline;">
                  <i class="fa-solid fa-lock-open"></i> Reset My Password &rarr;
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <form action="" method="POST" id="loginForm">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label">Gmail / Email Address <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-envelope form-icon"></i>
          <?php $loginPrefillEmail = $_POST['email'] ?? ($_GET['email'] ?? ''); ?>
          <input type="email" name="email" id="loginEmail" class="form-control <?= $errorType === 'email' ? 'input-error-highlight' : '' ?>" placeholder="name@gmail.com" value="<?= h($loginPrefillEmail) ?>" required <?= $loginPrefillEmail === '' ? 'autofocus' : '' ?> autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="email">
        </div>
      </div>

      <div class="form-group">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <label class="form-label" style="margin-bottom:0;">Password <span class="required">*</span></label>
          <a href="forgot-password.php" style="font-size:0.75rem;color:var(--gold);">Forgot Password?</a>
        </div>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-lock form-icon"></i>
          <input type="password" name="password" id="loginPass" class="form-control <?= $errorType === 'password' ? 'input-error-highlight' : '' ?>" placeholder="••••••••" required <?= $loginPrefillEmail !== '' ? 'autofocus' : '' ?> autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="current-password">
          <i class="fa-solid fa-eye form-icon-right" id="loginPassIcon" onclick="togglePass('loginPass', this)" style="cursor:pointer;" title="Show/Hide Password"></i>
        </div>
        <!-- Live Caps Lock Warning -->
        <div class="caps-indicator" id="capsIndicator">
          <i class="fa-solid fa-triangle-exclamation"></i> Caps Lock is ON (Passwords are case-sensitive)
        </div>
      </div>

      <!-- Remember Me Checkbox (Persistent Next-Day Login) -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin:14px 0 16px;">
        <label style="display:flex;align-items:center;gap:8px;font-size:0.82rem;color:var(--text-muted);cursor:pointer;user-select:none;">
          <input type="checkbox" name="remember_me" value="1" checked style="accent-color:var(--gold);width:16px;height:16px;cursor:pointer;">
          <span>Keep me signed in (30 days)</span>
        </label>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:6px;">
        <i class="fa-solid fa-right-to-bracket"></i> Log In
      </button>
    </form>

    <div style="margin-top:24px;text-align:center;font-size:0.88rem;color:var(--text-muted);border-top:1px solid var(--border-subtle);padding-top:20px;">
      Don't have an account? <a href="register.php" style="color:var(--gold);font-weight:700;">Register Here &rarr;</a>
    </div>
    <div style="text-align:center;margin-top:12px;font-size:0.8rem;color:var(--text-muted);">
      Need help? <a href="forgot-password.php" style="color:var(--gold);font-weight:600;">Contact Admin to Reset</a>
    </div>
  </div>

  <script>
    function togglePass(id, iconEl) {
      const el = document.getElementById(id);
      if (!el) return;
      if (el.type === 'password') {
        el.type = 'text';
        if (iconEl) {
          iconEl.classList.remove('fa-eye');
          iconEl.classList.add('fa-eye-slash');
        }
      } else {
        el.type = 'password';
        if (iconEl) {
          iconEl.classList.remove('fa-eye-slash');
          iconEl.classList.add('fa-eye');
        }
      }
    }

    // Detect Caps Lock in real-time
    const passInput = document.getElementById('loginPass');
    const capsBadge = document.getElementById('capsIndicator');
    if (passInput && capsBadge) {
      passInput.addEventListener('keyup', (e) => {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
          capsBadge.style.display = 'inline-flex';
        } else {
          capsBadge.style.display = 'none';
        }
      });
      passInput.addEventListener('keydown', (e) => {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
          capsBadge.style.display = 'inline-flex';
        } else {
          capsBadge.style.display = 'none';
        }
      });
    }
  </script>
<script src="../frontend/assets/js/core.js"></script>
<?php
if (!empty($_SESSION['password_reset_watch']['token'])) {
    require_once __DIR__ . '/../components/reset-status-modal.php';
    renderPasswordResetStatusModal([
        'poll_interval_ms' => 4000,
        'auto_start'       => true,
    ]);
}
?>
</body>
</html>
