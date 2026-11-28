<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/core/email.php';
require_once __DIR__ . '/../../backend/core/rate-limiter.php';

redirectIfLoggedIn();

applyAuthRateLimit('register');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName  = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $suffix     = trim($_POST['suffix'] ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $phone      = trim($_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $token      = $_POST[CSRF_TOKEN_NAME] ?? '';

    $nameError = validatePersonName($firstName, $lastName, $middleName, $suffix);

    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security session token.';
    } elseif ($nameError !== null) {
        $error = $nameError;
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in all required information.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 8) {
        $error = 'The password must be at least 8 characters long.';
    } elseif ($password !== $confirmPass) {
        $error = 'Your password confirmation does not match.';
    } else {
        $existing = dbRow("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $error = 'This email is already registered. Log in or use another one.';
        } else {
            $hashedPass = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            $smtpConfigured = isSmtpConfigured();
            $emailVerified = $smtpConfigured ? 0 : 1;
            $verifyToken = $smtpConfigured ? bin2hex(random_bytes(32)) : null;

            $newUserId = dbInsert(
                "INSERT INTO users (first_name, middle_name, last_name, suffix, email, phone, password, role, status, email_verified, verify_token) VALUES (?, ?, ?, ?, ?, ?, ?, 'user', 'active', ?, ?)",
                [$firstName, $middleName !== '' ? $middleName : null, $lastName, $suffix !== '' ? $suffix : null, $email, $phone, $hashedPass, $emailVerified, $verifyToken]
            );

            // Send verification email if SMTP is configured
            if ($smtpConfigured && $verifyToken) {
                $verifyLink = SITE_URL . '/auth/verify-email.php?token=' . $verifyToken;
                $userName = formatFullName($firstName, $middleName, $lastName, $suffix);
                $htmlBody = emailTemplateVerifyEmail($verifyLink, $userName);
                $textBody = "Verify Your Email\n\nWelcome {$userName},\n\nThank you for registering at " . SITE_NAME . ".\n\nPlease verify your email by clicking: {$verifyLink}\n\nIf you didn't create this account, please ignore this email.\n\n" . SITE_NAME;
                sendEmail($email, 'Verify Your Email - ' . SITE_NAME, $htmlBody, $textBody);
            }

            createNotification(
                $newUserId,
                'Welcome!',
                'Thank you for registering at Marvelous Hotel. Use our AI Room Recommender to pick a room!',
                'system'
            );

            auditLog('New User Registration', 'Auth', "User ID $newUserId registered ($email; email_verified={$emailVerified})");

            // If SMTP email verification is required, do not bypass verification via auto-login
            if (!$emailVerified) {
                setFlash('info', 'Account created! Please check your email inbox to verify your address before logging in.');
                header('Location: login.php?email=' . urlencode($email));
                exit;
            }

            // Auto-login verified user so they can immediately access the main hotel website
            session_regenerate_id(true);
            $_SESSION['user_id']     = $newUserId;
            $_SESSION['user_name']   = formatFullName($firstName, $middleName, $lastName, $suffix);
            $_SESSION['user_email']  = $email;
            $_SESSION['user_role']   = 'user';
            $_SESSION['user_avatar'] = 'default-avatar.png';
            $_SESSION['_status_checked_at'] = time();

            dbExec("UPDATE users SET last_login = NOW() WHERE id = ?", [$newUserId]);

            setFlash('success', 'Registration successful! Welcome to Marvelous Hotel.');
            $target = getSafeRedirectUrl($_GET['redirect'] ?? '', SITE_URL . '/index.php');
            header('Location: ' . $target);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — Marvelous Hotel</title>
  <?php require_once __DIR__ . '/../components/page-head.php'; renderPerformanceHeadLinks(false); ?>
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/auth.css')) ?>">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--navy);">

  <div class="glass-card auth-form-card">
    <div style="text-align:center;margin-bottom:24px;">
      <a href="../index.php" style="display:inline-flex;align-items:center;gap:10px;margin-bottom:12px;">
        <div style="width:38px;height:38px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--navy);font-weight:800;">M</div>
        <div style="text-align:left;line-height:1.1;">
          <div style="font-family:var(--font-serif);font-size:1.1rem;font-weight:700;color:var(--gold);">Marvelous Hotel</div>
          <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">&amp; Dormitory</div>
        </div>
      </a>
      <h2 style="font-family:var(--font-serif);font-size:1.45rem;color:#fff;">Create User Account</h2>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Sign up to access the hotel main website and book rooms</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error">
        <i class="fa-solid fa-circle-xmark"></i>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <form action="" method="POST">
      <?= csrfField() ?>

      <div class="form-row-2col">
        <div class="form-group">
          <label class="form-label">First Name <span class="required">*</span></label>
          <input type="text" name="first_name" id="regFirstName" class="form-control" placeholder="e.g. Juan" value="<?= h($_POST['first_name'] ?? '') ?>" required minlength="2" maxlength="50" pattern="^[a-zA-ZÀ-ÿ\s'\-\.]+$" title="Letters, spaces, hyphens, and apostrophes only (2 to 50 chars)">
        </div>
        <div class="form-group">
          <label class="form-label">Middle Name <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;">(Optional)</span></label>
          <input type="text" name="middle_name" id="regMiddleName" class="form-control" placeholder="e.g. Santos" value="<?= h($_POST['middle_name'] ?? '') ?>" maxlength="50" pattern="^[a-zA-ZÀ-ÿ\s'\-\.]*$" title="Letters, spaces, hyphens, and apostrophes only (max 50 chars)">
        </div>
      </div>

      <div class="form-row-name-suffix">
        <div class="form-group">
          <label class="form-label">Last Name <span class="required">*</span></label>
          <input type="text" name="last_name" id="regLastName" class="form-control" placeholder="e.g. Dela Cruz" value="<?= h($_POST['last_name'] ?? '') ?>" required minlength="2" maxlength="50" pattern="^[a-zA-ZÀ-ÿ\s'\-\.]+$" title="Letters, spaces, hyphens, and apostrophes only (2 to 50 chars)">
        </div>
        <div class="form-group">
          <label class="form-label">Suffix <span style="font-weight:400;color:var(--text-muted);font-size:0.75rem;">(Optional)</span></label>
          <select name="suffix" id="regSuffix" class="form-control" style="cursor:pointer;">
            <option value="" <?= ($_POST['suffix'] ?? '') === '' ? 'selected' : '' ?>>None</option>
            <option value="Jr." <?= ($_POST['suffix'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
            <option value="Sr." <?= ($_POST['suffix'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
            <option value="II" <?= ($_POST['suffix'] ?? '') === 'II' ? 'selected' : '' ?>>II</option>
            <option value="III" <?= ($_POST['suffix'] ?? '') === 'III' ? 'selected' : '' ?>>III</option>
            <option value="IV" <?= ($_POST['suffix'] ?? '') === 'IV' ? 'selected' : '' ?>>IV</option>
            <option value="V" <?= ($_POST['suffix'] ?? '') === 'V' ? 'selected' : '' ?>>V</option>
          </select>
        </div>
      </div>

      <div id="nameCheckBadge" style="display:none;font-size:0.78rem;padding:6px 12px;border-radius:6px;margin-top:-6px;margin-bottom:12px;"></div>
      <small style="font-size:0.75rem;color:var(--text-muted);display:block;margin-top:-6px;margin-bottom:14px;">
        <i class="fa-solid fa-circle-info"></i> Limit: 2–50 characters per field. Duplicate full names are not allowed.
      </small>

      <div class="form-group">
        <label class="form-label">Gmail / Email Address <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-envelope form-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="e.g.: name@gmail.com" value="<?= h($_POST['email'] ?? ($_GET['email'] ?? '')) ?>" required autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="email">
        </div>
        <small style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;display:block;">This will be the login email for your User account.</small>
      </div>

      <div class="form-group">
        <label class="form-label">Contact Number</label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-phone form-icon"></i>
          <input type="tel" name="phone" class="form-control" placeholder="09123456789" value="<?= h($_POST['phone'] ?? '') ?>" autocomplete="tel">
        </div>
      </div>

      <div class="form-row-2col">
        <div class="form-group">
          <label class="form-label">Password <span class="required">*</span></label>
          <div class="form-icon-wrap">
            <i class="fa-solid fa-lock form-icon"></i>
            <input type="password" name="password" id="regPass" class="form-control" placeholder="Min. 8 chars" minlength="8" required autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="new-password">
            <i class="fa-solid fa-eye form-icon-right" onclick="togglePass('regPass', this)" style="cursor:pointer;" title="Show/Hide"></i>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm <span class="required">*</span></label>
          <div class="form-icon-wrap">
            <i class="fa-solid fa-lock form-icon"></i>
            <input type="password" name="confirm_password" id="regConfirmPass" class="form-control" placeholder="Repeat password" minlength="8" required autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="new-password">
            <i class="fa-solid fa-eye form-icon-right" onclick="togglePass('regConfirmPass', this)" style="cursor:pointer;" title="Show/Hide"></i>
          </div>
        </div>
      </div>

      <button type="submit" id="submitBtn" class="btn btn-gold btn-block" style="margin-top:10px;">
        <i class="fa-solid fa-user-plus"></i> Create Account
      </button>
    </form>

    <div style="text-align:center;margin-top:20px;font-size:0.85rem;color:var(--text-muted);">
      Already have an account? <a href="login.php" style="color:var(--gold);font-weight:600;">Log In Here</a>
    </div>
  </div>

<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script>
let checkTimeout = null;
const fnInput  = document.getElementById('regFirstName');
const mnInput  = document.getElementById('regMiddleName');
const lnInput  = document.getElementById('regLastName');
const sufInput = document.getElementById('regSuffix');
const badge    = document.getElementById('nameCheckBadge');

function runNameCheck() {
  const fn  = fnInput ? fnInput.value.trim() : '';
  const mn  = mnInput ? mnInput.value.trim() : '';
  const ln  = lnInput ? lnInput.value.trim() : '';
  const suf = sufInput ? sufInput.value.trim() : '';

  if (!fn || !ln) {
    badge.style.display = 'none';
    return;
  }

  const query = 'first_name=' + encodeURIComponent(fn) +
                '&middle_name=' + encodeURIComponent(mn) +
                '&last_name=' + encodeURIComponent(ln) +
                '&suffix=' + encodeURIComponent(suf);

  fetch('check-name.php?' + query)
    .then(res => res.json())
    .then(data => {
      if (!badge) return;
      badge.style.display = 'block';
      badge.innerHTML = '';
      const icon = document.createElement('i');
      if (data.available) {
        badge.style.background = 'rgba(0, 210, 106, 0.15)';
        badge.style.border = '1px solid rgba(0, 210, 106, 0.35)';
        badge.style.color = '#00e074';
        icon.className = 'fa-solid fa-circle-check';
      } else {
        badge.style.background = 'rgba(235, 32, 38, 0.15)';
        badge.style.border = '1px solid rgba(235, 32, 38, 0.35)';
        badge.style.color = '#ff6b6b';
        icon.className = 'fa-solid fa-circle-xmark';
      }
      badge.appendChild(icon);
      badge.appendChild(document.createTextNode(' ' + String(data.message || '')));
    })
    .catch(() => {
      if (badge) badge.style.display = 'none';
    });
}

[fnInput, mnInput, lnInput, sufInput].forEach(inp => {
  if (inp) {
    inp.addEventListener('input', () => {
      clearTimeout(checkTimeout);
      checkTimeout = setTimeout(runNameCheck, 350);
    });
    inp.addEventListener('change', () => {
      clearTimeout(checkTimeout);
      checkTimeout = setTimeout(runNameCheck, 150);
    });
  }
});

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
</script>
</body>
</html>
