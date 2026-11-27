<?php
// ============================================================
// MARVELOUS HOTEL — Reset Password (Code-Gated)
// frontend/auth/reset-password.php
// User enters Admin-dispatched Reset Code + sets own new password → Done → Login
// ============================================================

require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/core/rate-limiter.php';

$fromProfile = (($_GET['from'] ?? '') === 'profile') || (($_POST['from'] ?? '') === 'profile');
if (isLoggedIn() && !isUser()) {
    redirectIfLoggedIn();
}

applyAuthRateLimit('reset');
ensurePasswordResetSchema();

// Validate any active session watch on GET/POST load
$activeWatch = $_SESSION['password_reset_watch'] ?? null;
$activeWatchRow = null;
if (is_array($activeWatch) && !empty($activeWatch['token'])) {
    $watchReqId = (int)($activeWatch['request_id'] ?? 0);
    if ($watchReqId > 0) {
        try {
            $wRow = dbRow(
                "SELECT id, email, status, dispatch_type, request_token,
                        reset_code, reset_code_expires, user_notified_at, code_used_at
                 FROM password_reset_requests
                 WHERE id = ?
                 LIMIT 1",
                [$watchReqId]
            );
            if (
                !$wRow
                || !verifyResetRequestToken($wRow['request_token'] ?? null, (string)$activeWatch['token'])
                || $wRow['status'] === 'dismissed'
                || !empty($wRow['code_used_at'])
            ) {
                unset($_SESSION['password_reset_watch']);
                $activeWatch = null;
            } else {
                $activeWatchRow = $wRow;
            }
        } catch (\Throwable $e) {
            // Keep watch active on transient DB read error
        }
    }
} else {
    $activeWatch = null;
}

$error = '';
$submittedEmail = strtolower(trim((string)($_POST['email'] ?? ($_GET['email'] ?? ($activeWatch['email'] ?? '')))));
$submittedCodeRaw = trim((string)($_POST['reset_code'] ?? ''));

const MAX_RESET_CODE_ATTEMPTS = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfPost    = (string)($_POST[CSRF_TOKEN_NAME] ?? '');
    $email       = strtolower(trim((string)($_POST['email'] ?? '')));
    $codeInput   = normalizeResetCode((string)($_POST['reset_code'] ?? ''));
    $password    = (string)($_POST['password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    $submittedEmail = $email;

    if (!verifyCsrfToken($csrfPost)) {
        $error = 'Invalid security session token. Please refresh and try again.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter your registered email address.';
    } elseif ($codeInput === '') {
        $error = 'Reset code is required. You cannot reset your password without a valid code sent by the Hotel Admin.';
    } elseif (strlen($codeInput) < 6 || strlen($codeInput) > 8) {
        $error = 'Please enter the valid 6-digit Reset Code sent by the Hotel Admin.';
    } elseif ($password === '' || $confirmPass === '') {
        $error = 'Please enter and confirm your new password.';
    } elseif (strlen($password) < 8) {
        $error = 'Your new password must be at least 8 characters long.';
    } elseif ($password !== $confirmPass) {
        $error = 'Password confirmation does not match.';
    } else {
        $user = dbRow(
            "SELECT id, first_name, last_name, email FROM users WHERE email = ? AND role = 'user' LIMIT 1",
            [$email]
        );

        if (!$user) {
            $error = 'No active reset code found for this email address. Please request a reset code from the Hotel Admin first.';
        } else {
            $userId = (int)$user['id'];
            $nowSql = appNowSql();

            // Fetch latest unused Admin-dispatched reset code for this user
            $codeRow = dbRow(
                "SELECT id, reset_code, reset_code_hash, reset_code_expires, reset_code_attempts
                 FROM password_reset_requests
                 WHERE user_id = ?
                   AND status = 'completed'
                   AND dispatch_type = 'code'
                   AND code_used_at IS NULL
                   AND reset_code_hash IS NOT NULL
                 ORDER BY id DESC
                 LIMIT 1",
                [$userId]
            );

            if (!$codeRow) {
                $error = 'No active reset code found for your account. Wait for the Hotel Admin to send your code or request a new one.';
            } else {
                $reqId = (int)$codeRow['id'];
                $expiresRaw = (string)($codeRow['reset_code_expires'] ?? '');
                $expiresTs = $expiresRaw !== '' ? strtotime($expiresRaw) : false;
                $attempts = (int)($codeRow['reset_code_attempts'] ?? 0);

                if ($expiresTs !== false && $expiresTs <= time()) {
                    dbExec(
                        "UPDATE password_reset_requests SET reset_code = NULL, reset_code_hash = NULL WHERE id = ?",
                        [$reqId]
                    );
                    $error = 'This reset code has expired. Please request a new reset code from the Hotel Admin.';
                } elseif ($attempts >= MAX_RESET_CODE_ATTEMPTS) {
                    dbExec(
                        "UPDATE password_reset_requests SET reset_code = NULL, reset_code_hash = NULL WHERE id = ?",
                        [$reqId]
                    );
                    $error = 'This reset code has been locked due to too many invalid attempts. Please request a new code from the Hotel Admin.';
                } elseif (!verifyPasswordResetCode((string)$codeRow['reset_code_hash'], $codeInput)) {
                    $newAttempts = $attempts + 1;
                    if ($newAttempts >= MAX_RESET_CODE_ATTEMPTS) {
                        dbExec(
                            "UPDATE password_reset_requests SET reset_code_attempts = ?, reset_code = NULL, reset_code_hash = NULL WHERE id = ?",
                            [$newAttempts, $reqId]
                        );
                        $error = 'Invalid reset code. Maximum attempts reached — please request a new reset code from the Hotel Admin.';
                    } else {
                        dbExec(
                            "UPDATE password_reset_requests SET reset_code_attempts = ? WHERE id = ?",
                            [$newAttempts, $reqId]
                        );
                        $remaining = MAX_RESET_CODE_ATTEMPTS - $newAttempts;
                        $error = "Invalid reset code. Please check the code sent by Admin ({$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining).";
                    }
                    auditLog('Invalid Password Reset Code', 'User Auth', "Failed reset code attempt ({$newAttempts}/" . MAX_RESET_CODE_ATTEMPTS . ") for {$user['email']}");
                } else {
                    // Valid Admin-issued code → update user's chosen password & consume code (single-use)
                    $hashedPass = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);

                    dbExec(
                        "UPDATE users
                         SET password = ?, reset_token = NULL, reset_expires = NULL,
                             remember_token = NULL, remember_expires = NULL,
                             status = 'active', updated_at = NOW()
                         WHERE id = ?",
                        [$hashedPass, $userId]
                    );

                    dbExec(
                        "UPDATE password_reset_requests
                         SET code_used_at = ?, reset_code = NULL, reset_code_hash = NULL,
                             user_notified_at = COALESCE(user_notified_at, ?)
                         WHERE id = ?",
                        [$nowSql, $nowSql, $reqId]
                    );

                    clearAdminTempPassword($userId);
                    unset($_SESSION['password_reset_watch']);

                    // Clear any existing logged-in session & remember-me cookie so login.php opens cleanly
                    unset(
                        $_SESSION['user_id'],
                        $_SESSION['user_name'],
                        $_SESSION['user_email'],
                        $_SESSION['user_role'],
                        $_SESSION['user_avatar']
                    );
                    if (isset($_COOKIE['MH_REMEMBER_USER'])) {
                        setcookie('MH_REMEMBER_USER', '', time() - 3600, '/');
                    }

                    auditLog('Password Reset Completed', 'User Auth', "Password reset completed via Admin reset code for {$user['email']}");
                    session_regenerate_id(true);

                    setFlash('success', 'Your new password has been saved! You can now log in with your new password.');
                    header('Location: login.php?email=' . urlencode($user['email']));
                    exit;
                }
            }
        }
    }
}

// Check if the watched request already has an active unexpired code on initial page load
$initialReadyCode = null;
$initialReadyExpires = null;
if ($activeWatchRow && ($activeWatchRow['dispatch_type'] ?? '') === 'code' && $activeWatchRow['status'] === 'completed' && empty($activeWatchRow['code_used_at'])) {
    $rc = trim((string)($activeWatchRow['reset_code'] ?? ''));
    $expTs = !empty($activeWatchRow['reset_code_expires']) ? strtotime((string)$activeWatchRow['reset_code_expires']) : false;
    if ($rc !== '' && ($expTs === false || $expTs > time())) {
        $initialReadyCode = $rc;
        $initialReadyExpires = $expTs !== false ? date('h:i A', $expTs) : null;
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
  <style>
    .reset-watch-card {
      background: rgba(34, 197, 94, 0.08);
      border: 1px solid rgba(34, 197, 94, 0.32);
      border-radius: 12px;
      padding: 14px 15px;
      margin-bottom: 18px;
      transition: all 0.25s ease;
    }
    .reset-watch-card.is-ready {
      background: rgba(34, 197, 94, 0.14);
      border-color: rgba(74, 222, 128, 0.6);
      box-shadow: 0 0 22px rgba(34, 197, 94, 0.15);
    }
    .reset-watch-card.is-expired {
      background: rgba(234, 179, 8, 0.1);
      border-color: rgba(234, 179, 8, 0.4);
    }
    .reset-watch-icon {
      width: 34px;
      height: 34px;
      border-radius: 10px;
      background: rgba(34, 197, 94, 0.16);
      color: #4ade80;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 1rem;
    }
    .reset-watch-icon.is-expired {
      background: rgba(234, 179, 8, 0.16);
      color: #fbbf24;
    }
    .reset-live-dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #4ade80;
      margin-right: 6px;
      vertical-align: middle;
      box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7);
      animation: resetPulse 1.8s infinite;
    }
    @keyframes resetPulse {
      0% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.65); }
      70% { box-shadow: 0 0 0 8px rgba(74, 222, 128, 0); }
      100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); }
    }
    .reset-watch-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 9px;
    }
    .reset-watch-link-btn {
      background: none;
      border: none;
      color: var(--text-muted);
      font-size: 0.78rem;
      cursor: pointer;
      padding: 4px 2px;
      text-decoration: underline;
    }
    .reset-watch-link-btn:hover {
      color: #fff;
    }
    .code-input-highlight {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 1.15rem;
      font-weight: 700;
      letter-spacing: 4px;
      color: #facc15 !important;
      text-transform: uppercase;
    }
    .code-guard-note {
      background: rgba(201, 168, 76, 0.08);
      border: 1px solid rgba(201, 168, 76, 0.28);
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 0.78rem;
      color: #fde68a;
      line-height: 1.45;
      display: flex;
      gap: 9px;
      align-items: flex-start;
      margin-bottom: 16px;
    }
  </style>
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:var(--navy);">

  <div class="glass-card" style="width:100%;max-width:460px;padding:34px;box-shadow:var(--shadow-deep);position:relative;">
    <div style="text-align:center;margin-bottom:22px;">
      <a href="../index.php" style="display:inline-flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--navy);font-weight:800;font-size:1.2rem;">M</div>
        <div style="text-align:left;line-height:1.1;">
          <div style="font-family:var(--font-serif);font-size:1.1rem;font-weight:700;color:var(--gold);">Marvelous Hotel</div>
          <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">&amp; Dormitory</div>
        </div>
      </a>
      <h2 style="font-family:var(--font-serif);font-size:1.5rem;color:#fff;">Reset Password</h2>
      <p style="font-size:0.84rem;color:var(--text-muted);margin-top:4px;">Enter the code sent by Admin and set your own new password</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error" style="margin-bottom:18px;">
        <i class="fa-solid fa-circle-xmark"></i>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($activeWatch): ?>
      <div id="resetWatchCard" class="reset-watch-card<?= $initialReadyCode !== null ? ' is-ready' : '' ?>">
        <?php if ($initialReadyCode !== null): ?>
          <div style="display:flex;align-items:flex-start;gap:12px;">
            <div class="reset-watch-icon is-ready"><i class="fa-solid fa-key"></i></div>
            <div style="flex:1;">
              <div style="font-weight:700;color:#4ade80;font-size:0.94rem;">Admin Reset Code Ready!</div>
              <div style="font-size:0.8rem;color:#cbd5e1;margin-top:3px;">
                Code for <strong><?= h((string)$activeWatch['email']) ?></strong><?= $initialReadyExpires ? ' · Valid until ' . h($initialReadyExpires) : '' ?>:
              </div>
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:8px;">
                <span style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:1.15rem;font-weight:800;letter-spacing:3px;color:#facc15;background:#070b22;border:1px solid rgba(201,168,76,0.45);border-radius:8px;padding:4px 12px;user-select:all;"><?= h($initialReadyCode) ?></span>
                <button type="button" class="btn btn-ghost btn-sm" style="padding:5px 10px;font-size:0.76rem;border:1px solid rgba(201,168,76,0.4);color:#facc15;" onclick="window.MarvelousResetWatcher && window.MarvelousResetWatcher.copyCode(this)">
                  <i class="fa-regular fa-copy"></i> <span>Copy</span>
                </button>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div style="display:flex;align-items:flex-start;gap:12px;">
            <div class="reset-watch-icon"><i class="fa-solid fa-bell"></i></div>
            <div style="flex:1;">
              <div style="font-weight:700;color:#4ade80;font-size:0.9rem;">
                <span class="reset-live-dot"></span>Waiting for Hotel Admin...
              </div>
              <div style="font-size:0.81rem;color:#cbd5e1;margin-top:4px;line-height:1.45;">
                Reset request sent for <strong><?= h((string)$activeWatch['email']) ?></strong>. Keep this page open — your <strong>Reset Code</strong> will pop up here automatically once the Admin sends it.
              </div>
              <div class="reset-watch-actions">
                <button type="button" id="prManualCheckBtn" class="btn btn-ghost btn-sm" style="padding:5px 11px;font-size:0.76rem;border:1px solid rgba(74,222,128,0.35);color:#bbf7d0;" onclick="window.MarvelousResetWatcher && window.MarvelousResetWatcher.checkNow()">
                  <i class="fa-solid fa-rotate"></i> Check Now
                </button>
                <button type="button" class="reset-watch-link-btn" onclick="window.MarvelousResetWatcher && window.MarvelousResetWatcher.cancelWatch()">
                  Use a different email
                </button>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="code-guard-note">
        <i class="fa-solid fa-shield-halved" style="margin-top:2px;flex-shrink:0;color:var(--gold);"></i>
        <span>A valid <strong>Admin Reset Code</strong> is required to change your password. Don't have a code yet? <a href="forgot-password.php<?= $submittedEmail !== '' ? '?email=' . urlencode($submittedEmail) : '' ?>" style="color:var(--gold);font-weight:700;text-decoration:underline;">Request one here</a>.</span>
      </div>
    <?php endif; ?>

    <form action="" method="POST" id="codeResetForm">
      <?= csrfField() ?>
      <?php if ($fromProfile): ?>
        <input type="hidden" name="from" value="profile">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Registered Email Address <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-envelope form-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="name@email.com" value="<?= h($submittedEmail) ?>" required autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="email">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Admin Reset Code <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-key form-icon"></i>
          <input type="text" name="reset_code" id="resetCodeInput" class="form-control code-input-highlight" placeholder="6-DIGIT CODE" value="<?= h($submittedCodeRaw) ?>" required minlength="6" maxlength="8" autocomplete="one-time-code" inputmode="numeric" autofocus>
        </div>
        <small style="color:var(--text-muted);font-size:0.74rem;display:block;margin-top:4px;">Enter the code that pops up from the Hotel Admin. Without a valid code, password reset is not allowed.</small>
      </div>

      <div class="form-group">
        <label class="form-label">New Password (Min. 8 characters) <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-lock form-icon"></i>
          <input type="password" name="password" id="newPass" class="form-control" placeholder="Enter your chosen password" required minlength="8" autocomplete="new-password">
          <i class="fa-solid fa-eye form-icon-right" onclick="togglePass('newPass', this)" style="cursor:pointer;" title="Show/Hide Password"></i>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm New Password <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-lock form-icon"></i>
          <input type="password" name="confirm_password" id="confirmPass" class="form-control" placeholder="Re-enter your new password" required minlength="8" autocomplete="new-password">
          <i class="fa-solid fa-eye form-icon-right" onclick="togglePass('confirmPass', this)" style="cursor:pointer;" title="Show/Hide Password"></i>
        </div>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:10px;">
        <i class="fa-solid fa-check"></i> Done
      </button>
    </form>

    <div style="margin-top:22px;text-align:center;font-size:0.86rem;color:var(--text-muted);border-top:1px solid var(--border-subtle);padding-top:18px;">
      <a href="forgot-password.php<?= $submittedEmail !== '' ? '?email=' . urlencode($submittedEmail) : '' ?>" style="color:var(--gold);font-weight:700;">&larr; Request New Code</a>
      <span style="margin:0 8px;">|</span>
      <a href="login.php<?= $submittedEmail !== '' ? '?email=' . urlencode($submittedEmail) : '' ?>" style="color:var(--gold);font-weight:700;">Back to Login &rarr;</a>
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
</script>
<script src="../frontend/assets/js/core.js"></script>
<?php
if ($activeWatch) {
    require_once __DIR__ . '/../components/reset-status-modal.php';
    renderPasswordResetStatusModal([
        'poll_interval_ms' => 3000,
        'inline_status_id' => 'resetWatchCard',
        'on_reset_page'    => true,
        'auto_start'       => true,
    ]);
}
?>
</body>
</html>
