<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

// Check if an admin already exists
$adminCount = (int)(dbRow("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'")['cnt'] ?? 0);

// Admin accounts can no longer be created here once one exists.
// This page is only the first-time bootstrap (0 admins). Further admins must be created via the database.
if ($adminCount > 0) {
    header('Location: ../admin/login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security session token. Please try again.';
    } else {
        $firstName   = trim($_POST['first_name'] ?? '');
        $lastName    = trim($_POST['last_name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $password    = $_POST['password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            $error = 'Please fill in all required information.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid Gmail or Email address format.';
        } elseif (strlen($password) < 8) {
            $error = 'The password must be at least 8 characters long.';
        } elseif ($password !== $confirmPass) {
            $error = 'The password confirmation does not match.';
        } else {
            $exists = dbRow("SELECT id FROM users WHERE email = ?", [$email]);
            if ($exists) {
                $error = 'This Gmail/Email is already in use in the system.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                $adminId = dbInsert(
                    "INSERT INTO users (first_name, last_name, email, phone, password, role, status, email_verified) 
                     VALUES (?, ?, ?, ?, ?, 'admin', 'active', 1)",
                    [$firstName, $lastName, $email, $phone, $hashed]
                );

                auditLog('Created Admin Account', 'Auth', "Manual Admin registered: $email");
                setFlash('success', 'Your Admin Account was created successfully! You can now log in.');
                header("Location: ../admin/login.php");
                exit;
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
  <title>Admin Account Setup — Marvelous Hotel</title>
  <?php require_once __DIR__ . '/../components/page-head.php'; renderPerformanceHeadLinks(false); ?>
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/auth.css')) ?>">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--navy);">
<?php require_once __DIR__ . '/../components/desktop-only-guard.php'; renderDesktopOnlyGuard('admin'); ?>

  <div class="glass-card" style="width:100%;max-width:540px;padding:36px;box-shadow:var(--shadow-deep);border-color:rgba(201,168,76,0.3);">
    <div style="text-align:center;margin-bottom:24px;">
      <div style="width:48px;height:48px;background:linear-gradient(135deg,var(--gold),#e94560);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem;margin-bottom:12px;">👑</div>
      <h2 style="font-family:var(--font-serif);font-size:1.5rem;color:#fff;">Admin Account Setup</h2>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Enter your own Gmail and Password for the System Administrator</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error"><i class="fa-solid fa-circle-xmark"></i> <span><?= h($error) ?></span></div>
    <?php endif; ?>

    <form action="" method="POST">
      <?= csrfField() ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label">First Name <span class="required">*</span></label>
          <input type="text" name="first_name" class="form-control" placeholder="Name" value="<?= h($_POST['first_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name <span class="required">*</span></label>
          <input type="text" name="last_name" class="form-control" placeholder="Last Name" value="<?= h($_POST['last_name'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Admin Gmail / Email Address <span class="required">*</span></label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-envelope form-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="admin.name@gmail.com" value="<?= h($_POST['email'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Contact Number</label>
        <div class="form-icon-wrap">
          <i class="fa-solid fa-phone form-icon"></i>
          <input type="tel" name="phone" class="form-control" placeholder="09123456789" value="<?= h($_POST['phone'] ?? '') ?>">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label">Admin Password <span class="required">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm <span class="required">*</span></label>
          <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:10px;">
        <i class="fa-solid fa-crown"></i> Create Admin Account
      </button>
    </form>

    <div style="text-align:center;margin-top:20px;font-size:0.85rem;">
      <a href="login.php" style="color:var(--gold);"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
    </div>
  </div>

<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
</body>
</html>
