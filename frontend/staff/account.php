<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('staff');
releaseReadOnlySession();

$staffId = currentUserId();
ensureTempCredentialSchema();
$staff = dbRow("SELECT * FROM users WHERE id = ?", [$staffId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $token       = $_POST[CSRF_TOKEN_NAME] ?? '';
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token. Please refresh and try again.');
    } elseif (!password_verify($currentPass, $staff['password'])) {
        setFlash('error', 'Current password is incorrect.');
    } elseif (strlen($newPass) < 8) {
        setFlash('error', 'New password must be at least 8 characters long.');
    } elseif ($newPass !== $confirmPass) {
        setFlash('error', 'New password and confirmation do not match.');
    } elseif ($newPass === $currentPass) {
        setFlash('warning', 'New password must be different from your current password.');
    } else {
        $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        dbExec("UPDATE users SET password = ? WHERE id = ?", [$hashed, $staffId]);
        clearAdminTempPassword($staffId);
        auditLog('Staff Changed Password', 'Staff Auth', "Staff #$staffId ({$staff['email']}) updated personal password.");
        setFlash('success', 'Your staff password has been updated securely.');
        header('Location: account.php');
        exit;
    }
    header('Location: account.php');
    exit;
}

$isTemp = !empty($staff['password_is_temp']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account &amp; Password — Staff Desk</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/staff.css')) ?>">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('staff'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="staff-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Staff Account &amp; Security</div>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-info"><i class="fa-solid fa-shield"></i> Staff Access Level</span>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <?php if ($isTemp): ?>
        <div class="flash-message flash-warning" style="margin-bottom:20px;">
          <i class="fa-solid fa-key"></i>
          <span>Kasalukuyang <strong>temporary password</strong> ang naka-set sa iyong account. Mag-palit ng sariling password sa ibaba para sa seguridad ng iyong account.</span>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Account &amp; Password Settings</h1>
          <div class="page-header-sub">Manage your staff desk credentials and verify your account security status.</div>
        </div>
      </div>

      <div class="staff-modal-grid-2">
        <!-- Profile Overview -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-id-card" style="color:var(--accent);margin-right:8px;"></i> Staff Identity</h2>
            <?php if ($isTemp): ?>
              <span class="badge badge-warning">Temporary Password</span>
            <?php else: ?>
              <span class="badge badge-success"><i class="fa-solid fa-lock"></i> Personal Password</span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
              <img src="<?= h(getUserAvatarUrl($staff['avatar'] ?? null)) ?>" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);" onerror="this.src='<?= h(ASSETS_URL) ?>/images/avatars/default-avatar.png';this.onerror=null;">
              <div>
                <div style="font-size:1.1rem;font-weight:700;color:#fff;"><?= h(formatFullName($staff)) ?></div>
                <div style="font-size:0.84rem;color:var(--accent);">Frontdesk Staff Member</div>
              </div>
            </div>

            <div style="font-size:0.86rem;display:grid;gap:10px;">
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-subtle);">
                <span style="color:var(--text-muted);">Email Address:</span>
                <strong style="color:#fff;"><?= h($staff['email']) ?></strong>
              </div>
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-subtle);">
                <span style="color:var(--text-muted);">Phone Number:</span>
                <span style="color:#fff;"><?= h($staff['phone'] ?: 'Not set') ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-subtle);">
                <span style="color:var(--text-muted);">Account Status:</span>
                <?= statusBadge($staff['status'] ?? 'active') ?>
              </div>
              <div style="display:flex;justify-content:space-between;padding:8px 0;">
                <span style="color:var(--text-muted);">Last Login:</span>
                <span style="color:#cbd5e1;"><?= !empty($staff['last_login']) ? formatDate($staff['last_login'], 'M d, Y h:i A') : 'Active Session' ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Change Password Form -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-key" style="color:var(--gold);margin-right:8px;"></i> Change Staff Password</h2>
          </div>
          <div class="card-body">
            <form action="account.php" method="POST">
              <?= csrfField() ?>
              <input type="hidden" name="change_password" value="1">

              <div class="form-group">
                <label class="form-label">Current Password <span class="required">*</span></label>
                <div class="form-icon-wrap" style="position:relative;">
                  <i class="fa-solid fa-lock form-icon"></i>
                  <input type="password" name="current_password" id="currStaffPw" class="form-control" placeholder="Enter current or temporary password" required style="padding-right:42px;">
                  <button type="button" onclick="togglePwField('currStaffPw', this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">New Password (min. 8 characters) <span class="required">*</span></label>
                <div class="form-icon-wrap" style="position:relative;">
                  <i class="fa-solid fa-key form-icon"></i>
                  <input type="password" name="new_password" id="newStaffPw" class="form-control" placeholder="Minimum 8 characters" minlength="8" required style="padding-right:42px;">
                  <button type="button" onclick="togglePwField('newStaffPw', this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Confirm New Password <span class="required">*</span></label>
                <div class="form-icon-wrap" style="position:relative;">
                  <i class="fa-solid fa-check-double form-icon"></i>
                  <input type="password" name="confirm_password" id="confStaffPw" class="form-control" placeholder="Re-enter new password" minlength="8" required style="padding-right:42px;">
                  <button type="button" onclick="togglePwField('confStaffPw', this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
              </div>

              <button type="submit" class="btn btn-primary btn-block">
                <i class="fa-solid fa-shield-halved"></i> Save New Password
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/staff.js')) ?>"></script>
<script>
function togglePwField(inputId, btn) {
  const el = document.getElementById(inputId);
  if (!el) return;
  if (el.type === 'password') {
    el.type = 'text';
    btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
  } else {
    el.type = 'password';
    btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
  }
}
</script>
</body>
</html>
