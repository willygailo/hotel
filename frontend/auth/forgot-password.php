<?php
// ============================================================
// MARVELOUS HOTEL — Forgot Password
// auth/forgot-password.php
// ============================================================

require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/core/email.php';
require_once __DIR__ . '/../../backend/core/rate-limiter.php';

$fromProfile = (($_GET['from'] ?? '') === 'profile') || (($_POST['from'] ?? '') === 'profile');
if (isLoggedIn()) {
    if (!isUser() || (!$fromProfile && empty($_SESSION['password_reset_watch']['token']))) {
        redirectIfLoggedIn();
    }
}

applyAuthRateLimit('forgot');
ensurePasswordResetSchema();

$error = '';
$successHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security session token. Please try again.';
    } elseif (empty($email) && empty($phone)) {
        $error = 'Please enter your registered email address or phone number.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $user = null;
        if (!empty($email)) {
            $user = dbRow("SELECT id, first_name, last_name, email, phone, role FROM users WHERE email = ? AND role = 'user'", [$email]);
        } elseif (!empty($phone)) {
            $user = dbRow("SELECT id, first_name, last_name, email, phone, role FROM users WHERE phone = ? AND role = 'user'", [$phone]);
        }

        if ($user) {
            $targetEmail = $user['email'];
            $targetPhone = $user['phone'] ?? $phone;
            $userId = (int)$user['id'];

            // 1. Create or refresh active pending row in password_reset_requests with session-bound token
            $rawWatchToken = bin2hex(random_bytes(32));
            $tokenHash = hashResetRequestToken($rawWatchToken);
            $nowSql = appNowSql();

            $existingPending = dbRows(
                "SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending' ORDER BY id DESC",
                [$userId]
            );
            if (!empty($existingPending)) {
                $requestId = (int)$existingPending[0]['id'];
                dbExec(
                    "UPDATE password_reset_requests SET email = ?, phone = ?, request_token = ?, requested_at = ?, user_notified_at = NULL WHERE id = ?",
                    [$targetEmail, $targetPhone, $tokenHash, $nowSql, $requestId]
                );
                if (count($existingPending) > 1) {
                    try {
                        dbExec(
                            "UPDATE password_reset_requests SET status = 'dismissed', resolved_at = ? WHERE user_id = ? AND status = 'pending' AND id != ?",
                            [$nowSql, $userId, $requestId]
                        );
                    } catch (\Throwable $e) {
                        error_log('Secondary pending reset cleanup failed: ' . $e->getMessage());
                    }
                }
            } else {
                $requestId = dbInsert(
                    "INSERT INTO password_reset_requests (user_id, email, phone, request_token, status, requested_at) VALUES (?, ?, ?, ?, 'pending', ?)",
                    [$userId, $targetEmail, $targetPhone, $tokenHash, $nowSql]
                );
            }

            // 2. Insert into notifications table for Admin
            $adminUser = dbRow("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            if ($adminUser) {
                createNotification(
                    (int)$adminUser['id'],
                    'Password Reset Request',
                    "Guest {$user['first_name']} {$user['last_name']} ({$targetEmail}) requested a password reset.",
                    'alert',
                    SITE_URL . '/admin/users.php?tab=resets',
                    'fa-key'
                );
            }

            auditLog('Password Reset Requested', 'User Auth', "Reset request submitted to Admin for {$targetEmail}");

            // 3. Send real email via SMTP only when SMTP is configured
            $emailSent = false;
            $resetToken = bin2hex(random_bytes(32));
            $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            dbExec("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?", [$resetToken, $resetExpires, $userId]);
            if (isSmtpConfigured()) {
                $resetLink = SITE_URL . '/auth/reset-password.php?token=' . $resetToken;
                $userName = formatFullName($user);
                $htmlBody = emailTemplateResetPassword($resetLink, $userName, 1);
                $textBody = "Password Reset Request\n\nHello {$userName},\n\nClick the link below to reset your password:\n{$resetLink}\n\nThis link will expire in 1 hour.\n\n" . SITE_NAME;
                $sendErr = null;
                $emailSent = sendEmail($targetEmail, 'Password Reset Link — ' . SITE_NAME, $htmlBody, $textBody, $sendErr);
            }

            // 4. Bind live status watcher strictly to this browser's isolated user session
            $_SESSION['password_reset_watch'] = [
                'request_id' => $requestId,
                'token'      => $rawWatchToken,
                'email'      => $targetEmail,
                'email_sent' => $emailSent,
                'created_at' => time(),
            ];
        } else {
            // Reassuring dummy watch even if email wasn't found to prevent account enumeration
            $_SESSION['password_reset_watch'] = [
                'request_id' => 0,
                'token'      => bin2hex(random_bytes(32)),
                'email'      => $email ?: $phone,
                'email_sent' => false,
                'created_at' => time(),
            ];
        }

        // PRG pattern: prevents form resubmission prompts on reload while resuming live polling
        header('Location: forgot-password.php' . ($fromProfile ? '?from=profile' : ''));
        exit;
    }
}

// Validate any active session watch on GET load
$activeWatch = $_SESSION['password_reset_watch'] ?? null;
if (is_array($activeWatch) && !empty($activeWatch['token'])) {
    $watchReqId = (int)($activeWatch['request_id'] ?? 0);
    if ($watchReqId > 0) {
        try {
            $wRow = dbRow(
                "SELECT status, request_token, user_notified_at FROM password_reset_requests WHERE id = ? LIMIT 1",
                [$watchReqId]
            );
            if (
                !$wRow
                || !verifyResetRequestToken($wRow['request_token'] ?? null, (string)$activeWatch['token'])
                || $wRow['status'] === 'dismissed'
                || !empty($wRow['user_notified_at'])
            ) {
                unset($_SESSION['password_reset_watch']);
                $activeWatch = null;
            }
        } catch (\Throwable $e) {
            // Keep watch active if DB read fails transiently
        }
    }
} else {
    $activeWatch = null;
}

if ($activeWatch && !empty($activeWatch['email_sent'])) {
    $successHtml = "A password reset link has been dispatched to <strong>" . h($activeWatch['email']) . "</strong>! Please check your Inbox and Spam/Junk folder, or stay on this page if the Admin updates your password directly.";
}

$prefillEmail = $_POST['email'] ?? ($_GET['email'] ?? ($activeWatch['email'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — Marvelous Hotel</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/auth.css">
  <style>
    .reset-watch-card {
      background: rgba(34, 197, 94, 0.08);
      border: 1px solid rgba(34, 197, 94, 0.32);
      border-radius: 12px;
      padding: 15px 16px;
      margin-bottom: 20px;
      transition: all 0.25s ease;
    }
    .reset-watch-card.is-ready {
      background: rgba(34, 197, 94, 0.14);
      border-color: rgba(74, 222, 128, 0.6);
      box-shadow: 0 0 22px rgba(34, 197, 94, 0.16);
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
      margin-top: 10px;
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
  </style>
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:var(--navy);">

  <div class="glass-card" style="width:100%;max-width:440px;padding:36px;box-shadow:var(--shadow-deep);position:relative;">
    <div style="text-align:center;margin-bottom:28px;">
      <a href="../index.php" style="display:inline-flex;align-items:center;gap:10px;margin-bottom:12px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg,var(--gold),var(--gold-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--navy);font-weight:800;font-size:1.2rem;">M</div>
        <div style="text-align:left;line-height:1.1;">
          <div style="font-family:var(--font-serif);font-size:1.1rem;font-weight:700;color:var(--gold);">Marvelous Hotel</div>
          <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">&amp; Dormitory</div>
        </div>
      </a>
      <h2 style="font-family:var(--font-serif);font-size:1.5rem;color:#fff;">Forgot Password?</h2>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Enter your email to request a password reset</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error" style="margin-bottom:20px;">
        <i class="fa-solid fa-circle-xmark"></i>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($activeWatch): ?>
      <div id="resetWatchCard" class="reset-watch-card">
        <div style="display:flex;align-items:flex-start;gap:12px;">
          <div class="reset-watch-icon">
            <i class="fa-solid fa-bell"></i>
          </div>
          <div style="flex:1;">
            <div style="font-weight:700;color:#4ade80;font-size:0.9rem;">
              <span class="reset-live-dot"></span>Waiting for Hotel Admin...
            </div>
            <div style="font-size:0.82rem;color:#cbd5e1;margin-top:4px;line-height:1.5;">
              <?php if ($successHtml !== ''): ?>
                <?= $successHtml ?>
              <?php else: ?>
                Password reset request sent for <strong><?= h($activeWatch['email']) ?></strong>! Keep this page open — a popup will appear automatically as soon as the Admin updates your password.
              <?php endif; ?>
            </div>
            <div class="reset-watch-actions">
              <button type="button" id="prManualCheckBtn" class="btn btn-ghost btn-sm" style="padding:6px 12px;font-size:0.78rem;border:1px solid rgba(74,222,128,0.35);color:#bbf7d0;" onclick="window.MarvelousResetWatcher && window.MarvelousResetWatcher.checkNow()">
                <i class="fa-solid fa-rotate"></i> Check Now
              </button>
              <button type="button" class="reset-watch-link-btn" onclick="window.MarvelousResetWatcher && window.MarvelousResetWatcher.cancelWatch()">
                Use a different email
              </button>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <form action="" method="POST">
      <?= csrfField() ?>
      <?php if ($fromProfile): ?>
        <input type="hidden" name="from" value="profile">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Registered Email Address <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-envelope form-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="name@email.com" value="<?= h($prefillEmail) ?>" required <?= $activeWatch ? '' : 'autofocus' ?> autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="email">
        </div>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:10px;">
        <i class="fa-solid fa-paper-plane"></i> <?= $activeWatch ? 'Resend Reset Request' : 'Send Reset Link' ?>
      </button>
    </form>

    <div style="margin-top:24px;text-align:center;font-size:0.88rem;color:var(--text-muted);border-top:1px solid var(--border-subtle);padding-top:20px;">
      Remembered your password? <a href="login.php<?= !empty($prefillEmail) ? '?email=' . urlencode($prefillEmail) : '' ?>" style="color:var(--gold);font-weight:700;">Log In &rarr;</a>
    </div>
  </div>

<script src="../frontend/assets/js/core.js"></script>
<?php
if ($activeWatch) {
    require_once __DIR__ . '/../components/reset-status-modal.php';
    renderPasswordResetStatusModal([
        'poll_interval_ms' => 3000,
        'inline_status_id' => 'resetWatchCard',
        'auto_start'       => true,
    ]);
}
?>
</body>
</html>
