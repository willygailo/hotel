<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('user');

$userId = currentUserId();

// Enforce Strict Identity Verification (KYC) before payment settlement
$kyc = getUserKycStatus($userId);
if (!$kyc['is_verified']) {
    if ($kyc['is_pending']) {
        setFlash('info', 'Your identity document is currently under review by hotel administration. You can complete payments once verified.');
    } else {
        setFlash('warning', 'Please complete your identity verification in your profile before settling payments.');
    }
    header("Location: profile.php");
    exit;
}

$bookingId = (int)($_GET['booking_id'] ?? 0);
$booking = dbRow("SELECT b.*, r.name as room_name, r.room_number FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ? AND b.user_id = ?", [$bookingId, $userId]);

if (!$booking) {
    setFlash('error', 'The requested booking could not be found.');
    header("Location: dashboard.php");
    exit;
}

if (in_array($booking['status'], ['cancelled', 'rejected', 'checked_out'], true)) {
    setFlash('warning', 'Payment cannot be submitted for a ' . strtoupper($booking['status']) . ' booking.');
    header("Location: my-bookings.php");
    exit;
}

$existingPayment = dbRow("SELECT id, status FROM payments WHERE booking_id = ? AND status IN ('verified', 'pending') ORDER BY id DESC LIMIT 1", [$bookingId]);
if ($existingPayment) {
    $msg = ($existingPayment['status'] === 'verified')
        ? "Payment for Booking #{$booking['booking_code']} is already verified."
        : "A payment receipt for Booking #{$booking['booking_code']} is already pending verification.";
    setFlash('info', $msg);
    header("Location: payment-history.php");
    exit;
}

releaseReadOnlySession();

// Payment Channel Settings
$mayaName   = getSetting('hotel_maya_name', 'WILLY GAILO');
$mayaHandle = getSetting('hotel_maya_handle', '@willygailojr');
$mayaPhone  = getSetting('hotel_maya_phone', '+63 *** *** 6963');
$mayaQrUrl  = '../frontend/assets/images/maya_qr.jpg';

$gcashName   = getSetting('hotel_gcash_name', 'JU***N J.');
$gcashPhone  = getSetting('hotel_gcash_phone', '+63 964 142 ****');
$gcashUserId = getSetting('hotel_gcash_userid', '••••••••••6SONJP');
$gcashNum    = getSetting('hotel_gcash', '0964 142 ****');
$gcashQrUrl  = '../frontend/assets/images/gcash_qr_card.jpg';
$gcashQrFull = '../frontend/assets/images/gcash_qr.jpg';

// Determine initial selected channel from URL or booking preference
$initialMethod = $_GET['method'] ?? ($booking['payment_method'] ?? 'maya');
if (!in_array($initialMethod, ['maya', 'gcash'], true)) {
    $initialMethod = 'maya';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $method = $_POST['method'] ?? 'maya';
        if (!in_array($method, ['maya', 'gcash'], true)) {
            $method = 'maya';
        }
        $refNum = trim($_POST['reference_number'] ?? '');
        $notes  = trim($_POST['notes'] ?? '');

        if (empty($refNum)) {
            $error = 'Please enter your payment reference number / transaction ID.';
        }

        $proofFile = null;
        if (empty($error)) {
            if (empty($_FILES['proof']['name'])) {
                $error = 'Please upload a screenshot or photo of your payment receipt.';
            } else {
                $proofFile = uploadFile($_FILES['proof'], 'proofs', ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE, $payErr);
                if (!$proofFile) {
                    $error = $payErr ?: 'Invalid image type or the file exceeds limit (Max 15MB JPG, PNG, and WebP).';
                }
            }
        }

        if (empty($error)) {
            dbInsert(
                "INSERT INTO payments (booking_id, user_id, amount, method, reference_number, proof_image, status, notes) 
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)",
                [$bookingId, $userId, $booking['total_amount'], $method, $refNum, $proofFile, $notes]
            );

            createNotification($userId, 'Payment Submitted', "Your receipt for booking #{$booking['booking_code']} has been received. Verification is in progress.", 'payment');
            auditLog('Uploaded Payment Proof', 'User Module', "Payment proof uploaded for Booking #{$booking['booking_code']}");

            setFlash('success', 'Your receipt has been submitted! Our team will verify it as soon as possible.');
            header("Location: payment-history.php");
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
  <title>Pay for Booking #<?= h($booking['booking_code']) ?> — Marvelous Hotel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/user.css">
  <style>
    .payment-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }
    .badge-maya {
      background: rgba(0, 210, 106, 0.15);
      color: #00e074;
      border: 1px solid rgba(0, 210, 106, 0.35);
    }
    .badge-instapay {
      background: rgba(235, 32, 38, 0.15);
      color: #ff6b6b;
      border: 1px solid rgba(235, 32, 38, 0.35);
    }
    .channel-tab-btn {
      flex: 1;
      padding: 14px 18px;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      color: var(--text-muted);
      cursor: pointer;
      font-weight: 600;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: all 0.25s ease;
    }
    .channel-tab-btn:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
    }
    .channel-tab-btn.active {
      background: linear-gradient(135deg, rgba(201, 168, 76, 0.2), rgba(201, 168, 76, 0.08));
      border-color: var(--gold);
      color: #fff;
      box-shadow: 0 4px 20px rgba(201, 168, 76, 0.15);
    }
    .channel-tab-btn.active.tab-maya {
      background: linear-gradient(135deg, rgba(0, 210, 106, 0.2), rgba(0, 210, 106, 0.05));
      border-color: #00d26a;
      box-shadow: 0 4px 20px rgba(0, 210, 106, 0.2);
    }
    .qr-container-card {
      background: linear-gradient(180deg, rgba(16, 26, 46, 0.9), rgba(10, 17, 30, 0.95));
      border: 1px solid rgba(0, 210, 106, 0.3);
      border-radius: 16px;
      padding: 24px;
      margin-top: 18px;
      position: relative;
      overflow: hidden;
    }
    .qr-container-card::before {
      content: '';
      position: absolute;
      top: -60px;
      right: -60px;
      width: 140px;
      height: 140px;
      background: radial-gradient(circle, rgba(0, 210, 106, 0.2) 0%, transparent 70%);
      pointer-events: none;
    }
    .qr-img-wrapper {
      background: #fff;
      padding: 12px;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
      display: inline-block;
      cursor: pointer;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .qr-img-wrapper:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 14px 40px rgba(0, 210, 106, 0.35);
    }
    .qr-img-wrapper img {
      display: block;
      width: 220px;
      height: auto;
      max-width: 100%;
      border-radius: 8px;
    }
    .copy-btn {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #fff;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 0.75rem;
      cursor: pointer;
      transition: background 0.2s;
    }
    .copy-btn:hover {
      background: rgba(201, 168, 76, 0.3);
      border-color: var(--gold);
    }
    .lightbox-modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(5, 10, 20, 0.85);
      backdrop-filter: blur(8px);
      z-index: 9999;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .lightbox-modal.active {
      display: flex;
    }
    .lightbox-content {
      background: #0f172a;
      border: 1px solid rgba(0, 210, 106, 0.4);
      border-radius: 20px;
      padding: 28px;
      max-width: 420px;
      width: 100%;
      text-align: center;
      position: relative;
      box-shadow: 0 20px 50px rgba(0,0,0,0.6);
      animation: modalFadeIn 0.25s ease;
    }
    @keyframes modalFadeIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    .file-drop-zone {
      border: 2px dashed rgba(201, 168, 76, 0.35);
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      background: rgba(255, 255, 255, 0.02);
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .file-drop-zone:hover, .file-drop-zone.dragover {
      border-color: var(--gold);
      background: rgba(201, 168, 76, 0.06);
    }
  </style>
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('user'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="user-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Payment Settlement #<?= h($booking['booking_code']) ?></div>
      </div>
      <div class="topbar-actions">
        <span class="payment-badge-pill badge-maya"><i class="fa-solid fa-shield-halved"></i> Secure Checkout</span>
      </div>
    </header>

    <div class="page-content">
      <div style="margin-bottom:20px;">
        <a href="dashboard.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
      </div>

      <div class="glass-card" style="max-width:820px;padding:32px;">
    <!-- Header -->
    <div style="border-bottom:1px solid var(--border);padding-bottom:20px;margin-bottom:24px;">
      <span style="font-size:0.8rem;text-transform:uppercase;color:var(--gold);font-weight:700;letter-spacing:1px;">Booking Settlement</span>
      <h2 style="font-family:var(--font-serif);font-size:1.75rem;color:#fff;margin:4px 0 8px;">Pay for Booking #<?= h($booking['booking_code']) ?></h2>
      <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;padding:14px 18px;">
        <div>
          <div style="font-size:0.8rem;color:var(--text-muted);">Assigned Room</div>
          <div style="font-size:1.05rem;font-weight:600;color:#fff;"><?= h($booking['room_name']) ?> <span style="font-size:0.85rem;color:var(--text-muted);">(Room <?= h($booking['room_number']) ?>)</span></div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:0.8rem;color:var(--text-muted);">Total Amount Payable</div>
          <div style="color:var(--gold);font-size:1.5rem;font-weight:700;letter-spacing:-0.5px;"><?= formatCurrency($booking['total_amount']) ?></div>
        </div>
      </div>
    </div>

    <!-- Payment Channel Selector Tabs -->
    <div style="margin-bottom:20px;">
      <label class="form-label" style="margin-bottom:10px;font-size:0.85rem;">Choose Official Payment Channel:</label>
      <div style="display:flex;gap:12px;">
        <button type="button" class="channel-tab-btn <?= $initialMethod === 'maya' ? 'active tab-maya' : '' ?>" id="tabMayaBtn" onclick="switchChannel('maya')">
          <i class="fa-solid fa-qrcode" style="font-size:1.2rem;color:#00e074;"></i>
          <div style="text-align:left;">
            <div>PayMaya (Maya) QR</div>
            <div style="font-size:0.72rem;opacity:0.8;">InstaPay / QR Ph Scannable</div>
          </div>
        </button>
        <button type="button" class="channel-tab-btn <?= $initialMethod === 'gcash' ? 'active' : '' ?>" id="tabGcashBtn" onclick="switchChannel('gcash')">
          <i class="fa-solid fa-mobile-screen-button" style="font-size:1.2rem;color:#2979ff;"></i>
          <div style="text-align:left;">
            <div>GCash Express</div>
            <div style="font-size:0.72rem;opacity:0.8;">Direct Send / Scan QR</div>
          </div>
        </button>
      </div>
    </div>

    <!-- Maya Payment Channel Card (Default & Recommended) -->
    <div id="channelMayaCard" class="qr-container-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <span class="payment-badge-pill badge-maya"><i class="fa-solid fa-check"></i> Recommended</span>
            <span class="payment-badge-pill badge-instapay"><i class="fa-solid fa-bolt"></i> InstaPay / QR Ph</span>
          </div>
          <h3 style="color:#fff;font-size:1.25rem;margin:4px 0 2px;">PayMaya (Maya) Official QR</h3>
          <p style="font-size:0.82rem;color:var(--text-muted);margin:0;">
            Scan using <strong>Maya</strong>, <strong>GCash</strong> (Scan QR), or any Philippine bank app with QR Ph / InstaPay.
          </p>
        </div>
      </div>

      <div class="payment-qr-grid" style="display:grid;grid-template-columns:auto 1fr;gap:24px;align-items:center;">
        <!-- QR Code Image -->
        <div style="text-align:center;">
          <div class="qr-img-wrapper" onclick="openQrModal('maya')" title="Click to enlarge QR Code">
            <img src="<?= h($mayaQrUrl) ?>" alt="Maya InstaPay QR Code Willy Gailo" id="mayaQrImg">
          </div>
          <div style="margin-top:10px;display:flex;gap:8px;justify-content:center;">
            <button type="button" class="btn btn-sm btn-ghost" onclick="openQrModal('maya')" style="font-size:0.75rem;padding:5px 10px;">
              <i class="fa-solid fa-up-right-and-down-left-from-center"></i> Enlarge
            </button>
            <a href="<?= h($mayaQrUrl) ?>" download="Marvelous_Hotel_Maya_QR.jpg" class="btn btn-sm btn-ghost" style="font-size:0.75rem;padding:5px 10px;">
              <i class="fa-solid fa-download"></i> Save QR
            </a>
          </div>
        </div>

        <!-- Account Credentials & Guide -->
        <div>
          <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:16px;margin-bottom:14px;">
            <div style="font-size:0.75rem;text-transform:uppercase;color:var(--gold);font-weight:700;margin-bottom:8px;">
              <i class="fa-solid fa-id-card"></i> Official Account Details:
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
              <span style="font-size:0.85rem;color:var(--text-muted);">Account Name:</span>
              <span style="font-size:0.9rem;font-weight:700;color:#fff;">
                <span id="mayaAccountNameText"><?= h($mayaName) ?></span>
                <button type="button" class="copy-btn" onclick="copyText('<?= h($mayaName) ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
              </span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
              <span style="font-size:0.85rem;color:var(--text-muted);">Maya Username:</span>
              <span style="font-size:0.9rem;font-weight:700;color:#00e074;">
                <span id="mayaHandleText"><?= h($mayaHandle) ?></span>
                <button type="button" class="copy-btn" onclick="copyText('<?= h($mayaHandle) ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
              </span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;">
              <span style="font-size:0.85rem;color:var(--text-muted);">Mobile Number:</span>
              <span style="font-size:0.85rem;color:#cbd5e1;"><?= h($mayaPhone) ?></span>
            </div>
          </div>

          <!-- Cross-compatibility notice for GCash users -->
          <div style="background:rgba(0, 210, 106, 0.08);border-left:3px solid #00d26a;border-radius:6px;padding:10px 14px;font-size:0.8rem;line-height:1.5;color:#e2e8f0;">
            <strong style="color:#00e074;"><i class="fa-solid fa-lightbulb"></i> Gamit mo ba ay GCash?</strong><br>
            Walang problema! Dahil ito ay <strong>InstaPay QR</strong>, buksan lamang ang iyong <strong>GCash App &rarr; Pindutin ang 'QR' / 'Scan QR'</strong> at itapat sa QR code na ito upang makapagbayad nang direkta!
          </div>
        </div>
      </div>
    </div>

    <!-- GCash Payment Channel Card -->
    <div id="channelGcashCard" class="qr-container-card" style="display:none;border-color:rgba(41, 121, 255, 0.45);box-shadow: 0 10px 30px rgba(41, 121, 255, 0.1);">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <span class="payment-badge-pill" style="background:rgba(41,121,255,0.2);color:#64b5f6;border:1px solid rgba(41,121,255,0.45);">
              <i class="fa-solid fa-qrcode"></i> GCash Official QR
            </span>
            <span class="payment-badge-pill badge-instapay"><i class="fa-solid fa-bolt"></i> InstaPay Supported</span>
          </div>
          <h3 style="color:#fff;font-size:1.25rem;margin:4px 0 2px;">GCash Official QR & Express</h3>
          <p style="font-size:0.82rem;color:var(--text-muted);margin:0;">
            I-scan ang GCash QR code gamit ang iyong <strong>GCash App</strong> o alinmang mobile banking app na may InstaPay QR.
          </p>
        </div>
      </div>

      <div class="payment-qr-grid" style="display:grid;grid-template-columns:auto 1fr;gap:24px;align-items:center;">
        <!-- QR Code Image -->
        <div style="text-align:center;">
          <div class="qr-img-wrapper" onclick="openQrModal('gcash')" title="Click to enlarge GCash QR Code" style="background:#fff;padding:8px;border-radius:12px;">
            <img src="<?= h($gcashQrUrl) ?>" alt="GCash InstaPay QR Code" id="gcashQrImg" style="width:220px;height:auto;display:block;border-radius:8px;">
          </div>
          <div style="margin-top:10px;display:flex;gap:8px;justify-content:center;">
            <button type="button" class="btn btn-sm btn-ghost" onclick="openQrModal('gcash')" style="font-size:0.75rem;padding:5px 10px;">
              <i class="fa-solid fa-up-right-and-down-left-from-center"></i> Enlarge
            </button>
            <a href="<?= h($gcashQrFull) ?>" download="Marvelous_Hotel_GCash_QR.jpg" class="btn btn-sm btn-ghost" style="font-size:0.75rem;padding:5px 10px;">
              <i class="fa-solid fa-download"></i> Save QR
            </a>
          </div>
        </div>

        <!-- GCash Account Credentials & Guide -->
        <div>
          <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(41,121,255,0.25);border-radius:12px;padding:16px;margin-bottom:14px;">
            <div style="font-size:0.75rem;text-transform:uppercase;color:#64b5f6;font-weight:700;margin-bottom:8px;">
              <i class="fa-solid fa-id-card"></i> Official GCash Account Details:
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
              <span style="font-size:0.85rem;color:var(--text-muted);">Account Name:</span>
              <span style="font-size:0.9rem;font-weight:700;color:#fff;">
                <span id="gcashAccountNameText"><?= h($gcashName) ?></span>
                <button type="button" class="copy-btn" onclick="copyText('<?= h($gcashName) ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
              </span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
              <span style="font-size:0.85rem;color:var(--text-muted);">Registered Mobile No.:</span>
              <span style="font-size:0.9rem;font-weight:700;color:#64b5f6;">
                <span><?= h($gcashPhone) ?></span>
                <button type="button" class="copy-btn" onclick="copyText('<?= h($gcashPhone) ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
              </span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;">
              <span style="font-size:0.85rem;color:var(--text-muted);">User ID:</span>
              <span style="font-size:0.85rem;color:#cbd5e1;"><?= h($gcashUserId) ?></span>
            </div>
          </div>

          <!-- Guide / instructions -->
          <div style="background:rgba(41, 121, 255, 0.08);border-left:3px solid #2979ff;border-radius:6px;padding:10px 14px;font-size:0.8rem;line-height:1.5;color:#e2e8f0;">
            <strong style="color:#64b5f6;"><i class="fa-solid fa-mobile-screen"></i> Paano magbayad gamit ang GCash?</strong><br>
            1. Buksan ang <strong>GCash App</strong> &rarr; pindutin ang <strong>QR / Scan QR</strong> at itapat sa QR code sa kaliwa.<br>
            2. Ilagay ang halaga ng booking (<strong>₱<?= number_format($booking['total_amount'] ?? $booking['subtotal'] ?? 0, 2) ?></strong>) at kumpirmahin ang bayad.<br>
            3. I-screenshot ang resibo at ilagay ang <strong>Reference Number</strong> sa ibaba.
          </div>
        </div>
      </div>
    </div>

    <!-- Error message display -->
    <?php if (!empty($error)): ?>
      <div class="flash-message flash-error" style="margin-top:20px;">
        <i class="fa-solid fa-circle-xmark"></i> <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Submission Form -->
    <form id="paymentProofForm" action="" method="POST" enctype="multipart/form-data" style="margin-top:28px;" onsubmit="return validateProofUploadForm()">
      <?= csrfField() ?>
      <input type="hidden" name="method" id="selectedMethodInput" value="<?= h($initialMethod) ?>">

      <div style="border-top:1px solid var(--border);padding-top:22px;margin-bottom:20px;">
        <h4 style="color:#fff;font-size:1.1rem;margin-bottom:4px;"><i class="fa-solid fa-receipt" style="color:var(--gold);"></i> Step 2: Upload Proof of Payment</h4>
        <p style="font-size:0.82rem;color:var(--text-muted);">Pagkatapos magbayad, ilagay ang detalye ng inyong resibo upang ma-confirm agad ang booking.</p>
      </div>

      <div class="form-group">
        <label class="form-label">Payment Channel Used <span class="required">*</span></label>
        <select id="formMethodSelect" class="form-control" onchange="onSelectMethodChange(this.value)">
          <option value="maya" <?= $initialMethod === 'maya' ? 'selected' : '' ?>>PayMaya (Maya) / InstaPay QR</option>
          <option value="gcash" <?= $initialMethod === 'gcash' ? 'selected' : '' ?>>GCash Express Send</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Reference Number / Transaction ID <span class="required">*</span></label>
        <input type="text" name="reference_number" class="form-control" placeholder="Halimbawa: 100293848123 o Maya Ref #" required>
        <small style="color:var(--text-muted);font-size:0.75rem;">Makikita sa Maya/GCash receipt pagkatapos makumpleto ang bayad.</small>
      </div>

      <div class="form-group">
        <label class="form-label">Upload Receipt Screenshot (Proof of Payment) <span class="required">*</span></label>
        <div class="file-drop-zone" id="dropZone" onclick="document.getElementById('proofFileInput').click()">
          <i class="fa-solid fa-cloud-arrow-up" style="font-size:2rem;color:var(--gold);margin-bottom:8px;"></i>
          <div style="font-size:0.9rem;font-weight:600;color:#fff;" id="fileLabelText">Click or Drag &amp; Drop receipt image here</div>
          <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">Supports JPG, PNG, or WebP (Max 15MB)</div>
          <div id="imagePreviewContainer" style="display:none;margin-top:12px;">
            <img id="imagePreview" src="#" alt="Receipt Preview" style="max-height:160px;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,0.4);border:1px solid rgba(255,255,255,0.2);">
          </div>
        </div>
        <div id="proofClientError" style="display:none;color:#f87171;font-size:0.82rem;margin-top:6px;font-weight:600;"><i class="fa-solid fa-circle-exclamation"></i> Please upload your payment receipt screenshot before submitting.</div>
        <input type="file" id="proofFileInput" name="proof" class="form-control" accept="image/*" style="display:none;" onchange="previewProofImage(this)">
      </div>

      <div class="form-group">
        <label class="form-label">Additional Notes <span style="font-weight:400;color:var(--text-muted);">(Optional)</span></label>
        <textarea name="notes" class="form-control" rows="2" placeholder="Hal. Pangalan ng account na pinanggalingan ng bayad..."></textarea>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:18px;padding:14px;font-size:1rem;font-weight:700;">
        <i class="fa-solid fa-circle-check"></i> Submit Payment Receipt for Verification
      </button>
    </form>
  </div>
</div>
</div>
</main>
</div>

<!-- Lightbox Modal for Full QR View (Dynamic for Maya & GCash) -->
<div class="lightbox-modal" id="qrLightboxModal" onclick="closeQrModal(event)">
  <div class="lightbox-content" id="lightboxModalBox" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div style="font-weight:700;color:#fff;font-size:1.1rem;" id="modalQrTitle">
        <i class="fa-solid fa-qrcode" style="color:#00e074;"></i> Scan to Pay
      </div>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeQrModal()" style="padding:4px 8px;font-size:1.1rem;color:#94a3b8;">&times;</button>
    </div>

    <div style="background:#fff;padding:16px;border-radius:14px;display:inline-block;margin-bottom:16px;">
      <img id="modalQrImage" src="<?= h($mayaQrUrl) ?>" alt="QR Code" style="width:280px;max-width:100%;display:block;border-radius:6px;">
    </div>

    <div id="modalQrAccountName" style="font-size:0.95rem;color:#fff;font-weight:700;margin-bottom:2px;"><?= h($mayaName) ?></div>
    <div id="modalQrSubDetails" style="font-size:0.85rem;color:#00e074;font-weight:600;margin-bottom:14px;"><?= h($mayaHandle) ?> &bull; <?= h($mayaPhone) ?></div>

    <div id="modalQrInstructions" style="font-size:0.78rem;color:var(--text-muted);line-height:1.4;margin-bottom:16px;">
      Scannable via Maya App, GCash (Scan QR), or any bank app supporting QR Ph / InstaPay.
    </div>

    <div style="display:flex;gap:10px;justify-content:center;">
      <a id="modalQrDownloadBtn" href="<?= h($mayaQrUrl) ?>" download="Marvelous_Hotel_Maya_QR.jpg" class="btn btn-gold btn-sm">
        <i class="fa-solid fa-download"></i> Download Image
      </a>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeQrModal()">Close</button>
    </div>
  </div>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/user.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  switchChannel('<?= $initialMethod ?>');
});

function switchChannel(channel) {
  const mayaTab = document.getElementById('tabMayaBtn');
  const gcashTab = document.getElementById('tabGcashBtn');
  const mayaCard = document.getElementById('channelMayaCard');
  const gcashCard = document.getElementById('channelGcashCard');
  const methodInput = document.getElementById('selectedMethodInput');
  const methodSelect = document.getElementById('formMethodSelect');

  if (channel === 'maya') {
    mayaTab.classList.add('active', 'tab-maya');
    gcashTab.classList.remove('active');
    mayaCard.style.display = 'block';
    gcashCard.style.display = 'none';
    methodInput.value = 'maya';
    methodSelect.value = 'maya';
  } else {
    gcashTab.classList.add('active');
    mayaTab.classList.remove('active', 'tab-maya');
    mayaCard.style.display = 'none';
    gcashCard.style.display = 'block';
    methodInput.value = 'gcash';
    methodSelect.value = 'gcash';
  }
}

function onSelectMethodChange(val) {
  switchChannel(val);
}

function copyText(text, btn) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      showCopiedFeedback(btn);
    });
  } else {
    const temp = document.createElement('input');
    temp.value = text;
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    document.body.removeChild(temp);
    showCopiedFeedback(btn);
  }
}

function showCopiedFeedback(btn) {
  const origHtml = btn.innerHTML;
  btn.innerHTML = '<i class="fa-solid fa-check" style="color:#00e074;"></i> Copied!';
  setTimeout(() => {
    btn.innerHTML = origHtml;
  }, 2000);
}

function openQrModal(type) {
  const isGcash = (type === 'gcash');
  const title = document.getElementById('modalQrTitle');
  const img = document.getElementById('modalQrImage');
  const name = document.getElementById('modalQrAccountName');
  const sub = document.getElementById('modalQrSubDetails');
  const instr = document.getElementById('modalQrInstructions');
  const dl = document.getElementById('modalQrDownloadBtn');
  const box = document.getElementById('lightboxModalBox');

  if (isGcash) {
    title.innerHTML = '<i class="fa-solid fa-qrcode" style="color:#64b5f6;"></i> Scan to Pay via GCash / InstaPay';
    img.src = '<?= h($gcashQrFull) ?>';
    img.alt = 'GCash QR Full Image';
    name.textContent = '<?= h($gcashName) ?>';
    sub.innerHTML = '<span style="color:#64b5f6;"><?= h($gcashPhone) ?></span> &bull; <?= h($gcashUserId) ?>';
    instr.textContent = 'I-scan gamit ang GCash App (Scan QR) o anumang banking app na sumusuporta sa InstaPay QR Ph.';
    dl.href = '<?= h($gcashQrFull) ?>';
    dl.download = 'Marvelous_Hotel_GCash_QR.jpg';
    if (box) box.style.borderColor = 'rgba(41, 121, 255, 0.4)';
  } else {
    title.innerHTML = '<i class="fa-solid fa-qrcode" style="color:#00e074;"></i> Scan to Pay via Maya / InstaPay';
    img.src = '<?= h($mayaQrUrl) ?>';
    img.alt = 'Maya QR Code';
    name.textContent = '<?= h($mayaName) ?>';
    sub.innerHTML = '<span style="color:#00e074;"><?= h($mayaHandle) ?></span> &bull; <?= h($mayaPhone) ?>';
    instr.textContent = 'Scannable via Maya App, GCash (Scan QR), or any bank app supporting QR Ph / InstaPay.';
    dl.href = '<?= h($mayaQrUrl) ?>';
    dl.download = 'Marvelous_Hotel_Maya_QR.jpg';
    if (box) box.style.borderColor = 'rgba(0, 210, 106, 0.4)';
  }

  document.getElementById('qrLightboxModal').classList.add('active');
}

function closeQrModal(e) {
  document.getElementById('qrLightboxModal').classList.remove('active');
}

function validateProofUploadForm() {
  const input = document.getElementById('proofFileInput');
  const errEl = document.getElementById('proofClientError');
  const dropZoneEl = document.getElementById('dropZone');
  if (!input || !input.files || input.files.length === 0) {
    if (errEl) errEl.style.display = 'block';
    if (dropZoneEl) {
      dropZoneEl.style.borderColor = '#ef4444';
      dropZoneEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return false;
  }
  if (errEl) errEl.style.display = 'none';
  if (dropZoneEl) dropZoneEl.style.borderColor = '';
  return true;
}

function previewProofImage(input) {
  if (input.files && input.files[0]) {
    const errEl = document.getElementById('proofClientError');
    const dropZoneEl = document.getElementById('dropZone');
    if (errEl) errEl.style.display = 'none';
    if (dropZoneEl) dropZoneEl.style.borderColor = '';

    const renderPreview = (fileToShow) => {
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('imagePreview').src = e.target.result;
        document.getElementById('imagePreviewContainer').style.display = 'block';
        document.getElementById('fileLabelText').innerHTML = '<i class="fa-solid fa-check-circle" style="color:#00e074;"></i> ' + escapeHtml(fileToShow.name);
      };
      reader.readAsDataURL(fileToShow);
    };

    if (typeof optimizeImageInput === 'function') {
      optimizeImageInput(input, { maxDim: 1400, minBytes: 300 * 1024, quality: 0.85, onDone: renderPreview });
    } else {
      renderPreview(input.files[0]);
    }
  }
}

// Drag & drop support on dropZone
const dropZone = document.getElementById('dropZone');
if (dropZone) {
  ['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.add('dragover');
    }, false);
  });
  ['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.remove('dragover');
    }, false);
  });
  dropZone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files.length) {
      const fileInput = document.getElementById('proofFileInput');
      fileInput.files = files;
      previewProofImage(fileInput);
    }
  });
}
</script>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
</body>
</html>
