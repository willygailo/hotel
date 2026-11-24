<?php
// ============================================================
// MARVELOUS HOTEL — System Settings
// admin/settings.php
// ============================================================

require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('admin');
releaseReadOnlySession();

// Handler: Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $allowedSettings = [
            'hotel_name', 'hotel_tagline', 'hotel_email', 'hotel_phone',
            'hotel_address', 'check_in_time', 'check_out_time',
            'hotel_maya_name', 'hotel_maya_handle', 'hotel_maya_phone', 'hotel_gcash',
        ];
        if (!empty($_POST['settings']) && is_array($_POST['settings'])) {
            $errMsg = null;
            $toSave = [];
            foreach ($_POST['settings'] as $key => $val) {
                if (!in_array($key, $allowedSettings, true) || !is_string($val)) {
                    continue;
                }
                $trimmedVal = trim($val);
                if ($key === 'hotel_email' && $trimmedVal !== '' && !filter_var($trimmedVal, FILTER_VALIDATE_EMAIL)) {
                    $errMsg = 'Official Hotel Contact Email format is invalid.';
                    break;
                }
                $toSave[$key] = $trimmedVal;
            }

            if ($errMsg !== null) {
                setFlash('error', $errMsg);
            } else {
                foreach ($toSave as $key => $val) {
                    dbExec(
                        "INSERT INTO settings (`key`, `value`, `updated_at`) VALUES (?, ?, NOW()) 
                         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()",
                        [$key, $val]
                    );
                }
                clearSettingsCache();
                auditLog('Updated System Settings', 'Admin Module', 'Modified global hotel configuration');
                setFlash('success', 'System settings updated successfully!');
            }
        }
    }
    header("Location: settings.php");
    exit;
}

// Fetch all settings as key-value map
$rawSettings = dbRows("SELECT * FROM settings ORDER BY id ASC");
$s = [];
foreach ($rawSettings as $row) {
    $s[$row['key']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Settings — Admin Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/admin.css')) ?>">
  <style>
    .settings-section-card {
      background: rgba(15, 23, 42, 0.7);
      border: 1px solid var(--border-subtle);
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 24px;
    }
    .settings-section-title {
      font-size: 1.15rem;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 6px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      padding-bottom: 12px;
    }
    .settings-section-desc {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-bottom: 20px;
    }
    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    @media (max-width: 768px) {
      .grid-2 { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('admin'); ?>
  
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="admin-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Global Configuration &amp; Policies</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Hotel &amp; System Settings</h1>
          <div class="page-header-sub">Manage hotel contacts, check-in policies, and e-wallet payment numbers.</div>
        </div>
      </div>

      <form action="" method="POST" id="main-settings-form">
        <?= csrfField() ?>
        <input type="hidden" name="save_settings" value="1">

        <!-- 1. HOTEL INFORMATION & POLICIES -->
        <div class="settings-section-card">
          <div class="settings-section-title">
            <i class="fa-solid fa-hotel"></i> Hotel Information &amp; Operational Policies
          </div>
          <div class="settings-section-desc">
            General details presented in public guest invoices, contact portals, and navigation headers.
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Hotel Name</label>
              <input type="text" name="settings[hotel_name]" class="form-control" value="<?= h($s['hotel_name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Tagline</label>
              <input type="text" name="settings[hotel_tagline]" class="form-control" value="<?= h($s['hotel_tagline'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Official Hotel Contact Email</label>
              <input type="email" name="settings[hotel_email]" class="form-control" value="<?= h($s['hotel_email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Official Contact Phone</label>
              <input type="text" name="settings[hotel_phone]" class="form-control" value="<?= h($s['hotel_phone'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
              <label class="form-label">Physical Address</label>
              <input type="text" name="settings[hotel_address]" class="form-control" value="<?= h($s['hotel_address'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Standard Check-In Time</label>
              <input type="time" name="settings[check_in_time]" class="form-control" value="<?= h($s['check_in_time'] ?? '14:00') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Standard Check-Out Time</label>
              <input type="time" name="settings[check_out_time]" class="form-control" value="<?= h($s['check_out_time'] ?? '12:00') ?>">
            </div>
          </div>
        </div>

        <!-- 2. PAYMENT ACCOUNTS (MAYA & GCASH) -->
        <div class="settings-section-card">
          <div class="settings-section-title">
            <i class="fa-solid fa-money-bill-wave"></i> Payment Gateways &amp; E-Wallet Accounts
          </div>
          <div class="settings-section-desc">
            E-wallet accounts displayed to guests on checkout modals and invoice vouchers.
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Maya Account Name</label>
              <input type="text" name="settings[hotel_maya_name]" class="form-control" value="<?= h($s['hotel_maya_name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Maya Username / Handle</label>
              <input type="text" name="settings[hotel_maya_handle]" class="form-control" value="<?= h($s['hotel_maya_handle'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Maya Phone Reference</label>
              <input type="text" name="settings[hotel_maya_phone]" class="form-control" value="<?= h($s['hotel_maya_phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">GCash Registered Number</label>
              <input type="text" name="settings[hotel_gcash]" class="form-control" value="<?= h($s['hotel_gcash'] ?? '') ?>">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-gold" style="padding:14px 28px;font-size:1rem;font-weight:700;">
          <i class="fa-solid fa-save"></i> Save All Settings
        </button>
      </form>

    </div>
  </main>
</div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/admin.js')) ?>"></script>
</body>
</html>
