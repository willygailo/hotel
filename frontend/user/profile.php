<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('user');
releaseReadOnlySession();

$userId = currentUserId();
$user = dbRow("SELECT * FROM users WHERE id = ?", [$userId]);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $phone       = trim($_POST['phone'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $location    = trim($_POST['location'] ?? '');
        $idType      = trim($_POST['id_type'] ?? '');
        $idNumber    = trim($_POST['id_number'] ?? '');
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';

    $avatarPath   = $user['avatar'] ?? 'default-avatar.png';
    $idDocPath    = $user['id_document'] ?? null;

    // Handle Profile Picture Upload
    if (!empty($_FILES['avatar']['name'])) {
        $uploadedAvatar = uploadFile($_FILES['avatar'], 'avatars', ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE, $avatarErr);
        if ($uploadedAvatar) {
            $avatarPath = $uploadedAvatar;
        } else {
            $error = $avatarErr ?: 'Invalid profile picture format or file exceeds limit (Max 15MB JPG/PNG/WebP).';
        }
    }

    // Handle Valid ID Document Upload
    if (empty($error) && !empty($_FILES['id_document']['name'])) {
        $allowedDocs = [
            'image/jpeg', 'image/pjpeg', 'image/jpg',
            'image/png', 'image/x-png',
            'image/webp', 'image/gif',
            'application/pdf', 'application/x-pdf'
        ];
        $uploadedDoc = uploadFile($_FILES['id_document'], 'documents', $allowedDocs, MAX_UPLOAD_SIZE, $docErr);
        if ($uploadedDoc) {
            $idDocPath = $uploadedDoc;
        } else {
            $error = $docErr ?: 'Invalid ID document format or file exceeds limit (Max 15MB JPG, PNG, WebP, PDF).';
        }
    }

    if (empty($error)) {
        // Fetch current KYC state
        $currentKyc = getUserKycStatus($userId);

        $hasAvatar  = !empty($avatarPath) && $avatarPath !== 'default-avatar.png';
        $hasPhone   = !empty($phone);
        $hasAddress = !empty($address);
        $hasLoc     = !empty($location);
        $hasIdType  = !empty($idType);
        $hasIdNum   = !empty($idNumber);
        $hasIdDoc   = !empty($idDocPath);

        $allFieldsFilled = ($hasAvatar && $hasPhone && $hasAddress && $hasLoc && $hasIdType && $hasIdNum && $hasIdDoc);

        // Determine new verification status:
        // If already verified and no new document uploaded, maintain verified status
        $newStatus = $currentKyc['status'];
        $newProfileVerified = $user['profile_verified'] ?? 0;

        if (!empty($_FILES['id_document']['name']) || $currentKyc['status'] !== 'verified') {
            if ($allFieldsFilled) {
                $newStatus = 'pending';
                $newProfileVerified = 0;
            } else {
                $newStatus = 'unverified';
                $newProfileVerified = 0;
            }
        }

        // Update DB
        try {
            dbExec(
                "UPDATE users SET 
                    phone = ?, 
                    address = ?, 
                    location = ?, 
                    id_type = ?, 
                    id_number = ?, 
                    avatar = ?, 
                    id_document = ?, 
                    profile_verified = ?,
                    verification_status = ?,
                    rejection_reason = IF(? = 'pending', NULL, rejection_reason),
                    verification_submitted_at = IF(? = 'pending', NOW(), verification_submitted_at)
                 WHERE id = ?",
                [$phone, $address, $location, $idType, $idNumber, $avatarPath, $idDocPath, $newProfileVerified, $newStatus, $newStatus, $newStatus, $userId]
            );
        } catch (\Throwable $e) {
            // Fallback before migration 0009
            dbExec(
                "UPDATE users SET 
                    phone = ?, 
                    address = ?, 
                    location = ?, 
                    id_type = ?, 
                    id_number = ?, 
                    avatar = ?, 
                    id_document = ?, 
                    profile_verified = ?,
                    verification_submitted_at = IF(? = 1, NOW(), verification_submitted_at)
                 WHERE id = ?",
                [$phone, $address, $location, $idType, $idNumber, $avatarPath, $idDocPath, $newProfileVerified, $newProfileVerified, $userId]
            );
        }

        // Keep active session in sync with newly uploaded profile picture
        $_SESSION['user_avatar'] = $avatarPath;

        // Send in-app notification if transitioned to pending
        if ($newStatus === 'pending' && $currentKyc['status'] !== 'pending') {
            createNotification($userId, 'KYC Verification Submitted', 'Your valid ID and profile details have been submitted to the administration for review.', 'system');
        }

        // Password Update handling
        if (!empty($newPass)) {
            if (!password_verify($currentPass, $user['password'])) {
                $error = 'Incorrect current password.';
            } elseif (strlen($newPass) < 8) {
                $error = 'The new password must be at least 8 characters long.';
            } else {
                $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                dbExec("UPDATE users SET password = ? WHERE id = ?", [$hashed, $userId]);
                clearAdminTempPassword($userId);
                auditLog('Changed Password', 'User Module', "User #$userId updated password");
                setFlash('success', 'Profile details, uploaded documents, and password have been saved!');
                header("Location: profile.php");
                exit;
            }
        } else {
            auditLog('Updated Profile & Uploads', 'User Module', "User #$userId updated profile and documents (Status: $newStatus)");
            
            if ($newStatus === 'verified') {
                $flashMsg = 'Your profile was updated successfully!';
            } elseif ($newStatus === 'pending') {
                $flashMsg = 'Profile and ID document submitted successfully! Our administration will review your identity verification shortly.';
            } else {
                $flashMsg = 'Profile saved! Please complete all required fields and upload your valid government or school ID for full verification.';
            }
            
            setFlash('success', $flashMsg);
            header("Location: profile.php");
            exit;
        }
    }
}
}

// Refresh user data
$user = dbRow("SELECT * FROM users WHERE id = ?", [$userId]);
$kyc = getUserKycStatus($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Profile & Verification — Marvelous Hotel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/user.css">
  <style>
    .profile-card { background: rgba(18, 28, 45, 0.75); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); border-radius: 16px; padding: 32px; color: #fff; }
    .status-card { border-radius: 12px; padding: 18px 22px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 16px; font-size: 0.95rem; }
    .status-card.verified { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; }
    .status-card.pending { background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.4); color: #fde047; }
    .status-card.rejected { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; }
    .status-card.unverified { background: rgba(100, 116, 139, 0.15); border: 1px solid rgba(100, 116, 139, 0.4); color: #cbd5e1; }
    .avatar-wrapper { 
      position: relative; 
      width: 110px; 
      height: 110px; 
      min-width: 110px; 
      min-height: 110px; 
      max-width: 110px; 
      max-height: 110px; 
      border-radius: 50%; 
      overflow: hidden; 
      border: 3px solid var(--gold); 
      background: #0f172a; 
      flex-shrink: 0; 
      aspect-ratio: 1 / 1; 
      box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    }
    .avatar-wrapper img { width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 50%; }
    .avatar-btn { position: absolute; bottom: 0; right: 0; left: 0; background: rgba(0,0,0,0.65); color: #fff; font-size: 0.75rem; text-align: center; padding: 4px 0; cursor: pointer; }
    .badge-verified { background: #22c55e; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .badge-pending { background: #eab308; color: #000; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .badge-rejected { background: #ef4444; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .profile-avatar-row { display: flex; align-items: center; gap: 24px; margin-bottom: 24px; }
    @media (max-width: 640px) {
      .profile-card { padding: 20px 16px; border-radius: 12px; }
      .profile-avatar-row {
        flex-direction: column;
        text-align: center;
        gap: 16px;
        align-items: center;
      }
      .profile-avatar-row .avatar-upload-info {
        width: 100%;
        text-align: center;
      }
      .profile-avatar-row input[type="file"] {
        width: 100%;
        font-size: 0.85rem;
      }
      .form-row-2col,
      .form-row-name-suffix {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
      }
      .status-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        padding: 16px;
      }
    }
  </style>
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('user'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;">
        <button class="user-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Manage Profile & Verification</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <?php if (!empty($error)): ?>
        <div class="flash-message flash-error"><i class="fa-solid fa-circle-xmark"></i> <span><?= h($error) ?></span></div>
      <?php endif; ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Account Verification & Profile</h1>
          <div class="page-header-sub">Complete your account profile, address location, and valid ID upload to enable booking.</div>
        </div>
      </div>

      <!-- Verification Status Banner -->
      <?php if ($kyc['status'] === 'verified'): ?>
        <div class="status-card verified">
          <i class="fa-solid fa-circle-check" style="font-size:2rem;margin-top:2px;"></i>
          <div>
            <div style="font-weight:700;font-size:1.05rem;">Account Fully Verified <span class="badge-verified"><i class="fa-solid fa-shield-check"></i> VERIFIED</span></div>
            <div style="font-size:0.85rem;opacity:0.9;margin-top:4px;">Your identity and valid ID have been approved by hotel administration. You have full access to reserve rooms and dormitory beds.</div>
            <?php if (!empty($kyc['verified_at'])): ?>
              <div style="font-size:0.75rem;opacity:0.75;margin-top:4px;"><i class="fa-solid fa-calendar-check"></i> Verified on <?= formatDate($kyc['verified_at'], 'M d, Y h:i A') ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php elseif ($kyc['status'] === 'pending'): ?>
        <div class="status-card pending">
          <i class="fa-solid fa-clock-rotate-left" style="font-size:2rem;margin-top:2px;"></i>
          <div>
            <div style="font-weight:700;font-size:1.05rem;">Verification Under Review <span class="badge-pending"><i class="fa-solid fa-hourglass-half"></i> PENDING ADMIN REVIEW</span></div>
            <div style="font-size:0.85rem;opacity:0.9;margin-top:4px;">Your valid ID and profile details have been submitted and are currently in the queue for manual inspection. You will receive an in-app confirmation once verified.</div>
            <?php if (!empty($kyc['submitted_at'])): ?>
              <div style="font-size:0.75rem;opacity:0.75;margin-top:4px;"><i class="fa-solid fa-clock"></i> Submitted on <?= formatDate($kyc['submitted_at'], 'M d, Y h:i A') ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php elseif ($kyc['status'] === 'rejected'): ?>
        <div class="status-card rejected">
          <i class="fa-solid fa-circle-exclamation" style="font-size:2rem;margin-top:2px;"></i>
          <div>
            <div style="font-weight:700;font-size:1.05rem;">Verification Not Approved <span class="badge-rejected"><i class="fa-solid fa-triangle-exclamation"></i> ACTION REQUIRED</span></div>
            <div style="font-size:0.88rem;margin-top:4px;font-weight:500;">Reason: <strong><?= h($kyc['rejection_reason'] ?: 'Document was illegible or did not match registered details.') ?></strong></div>
            <div style="font-size:0.82rem;opacity:0.9;margin-top:4px;">Please re-upload a clear, valid government-issued or school ID below and ensure the name matches your account.</div>
          </div>
        </div>
      <?php else: ?>
        <div class="status-card unverified">
          <i class="fa-solid fa-id-card" style="font-size:2rem;margin-top:2px;"></i>
          <div>
            <div style="font-weight:700;font-size:1.05rem;">Identity Verification Required <span class="badge" style="background:#64748b;color:#fff;"><i class="fa-solid fa-arrow-up-from-bracket"></i> UNVERIFIED</span></div>
            <div style="font-size:0.85rem;opacity:0.9;margin-top:4px;">To protect users and prevent unauthorized bookings, please upload your profile picture, complete your address/location, and submit a valid government-issued or school ID.</div>
          </div>
        </div>
      <?php endif; ?>

      <div class="profile-card" style="max-width:800px;">
        <form action="" method="POST" enctype="multipart/form-data">
          <?= csrfField() ?>

          <h3 style="font-family:var(--font-serif);font-size:1.2rem;color:var(--gold);margin-bottom:20px;">
            <i class="fa-solid fa-user-gear"></i> Profile Picture & Email
          </h3>

          <div class="profile-avatar-row">
            <div class="avatar-wrapper">
              <?php 
                $avatarUrl = getUserAvatarUrl($user['avatar'] ?? null);
              ?>
              <img src="<?= h($avatarUrl) ?>" alt="Avatar" id="avatarPreview">
            </div>
            <div class="avatar-upload-info">
              <label class="form-label" style="display:block;margin-bottom:6px;">Upload Profile Picture <span style="color:var(--gold)">*</span></label>
              <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this, 'avatarPreview'); optimizeImageInput(this, { maxDim: 400, minBytes: 102400, quality: 0.84, statusElId: 'avatarCompressStatus' });">
              <small style="color:var(--text-muted);font-size:0.75rem;">Allowed: JPG, PNG, WebP (Auto-optimized for fast loading)</small>
              <div id="avatarCompressStatus" style="display:none;font-size:0.78rem;color:#38bdf8;margin-top:4px;"></div>
            </div>
          </div>

          <div class="form-row-2col">
            <div class="form-group">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control" value="<?= h($user['first_name'] ?? '') ?>" disabled>
            </div>
            <div class="form-group">
              <label class="form-label">Middle Name</label>
              <input type="text" class="form-control" value="<?= h($user['middle_name'] ?? '—') ?>" disabled>
            </div>
          </div>

          <div class="form-row-name-suffix">
            <div class="form-group">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control" value="<?= h($user['last_name'] ?? '') ?>" disabled>
            </div>
            <div class="form-group">
              <label class="form-label">Suffix</label>
              <input type="text" class="form-control" value="<?= h($user['suffix'] ?? '—') ?>" disabled>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Gmail / Email Address</label>
            <div style="position:relative;">
              <input type="email" class="form-control" value="<?= h($user['email']) ?>" disabled style="padding-right:110px;">
              <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:0.75rem;color:#4ade80;font-weight:600;"><i class="fa-solid fa-circle-check"></i> Connected</span>
            </div>
            <small style="color:var(--text-muted);font-size:0.75rem;">Your registered Gmail is used for booking confirmations.</small>
          </div>

          <hr style="border:none;border-top:1px solid rgba(255,255,255,0.1);margin:24px 0;">

          <h3 style="font-family:var(--font-serif);font-size:1.2rem;color:var(--gold);margin-bottom:20px;">
            <i class="fa-solid fa-location-dot"></i> Contact & Address Location
          </h3>

          <div class="form-group">
            <label class="form-label">Contact Number <span style="color:var(--gold)">*</span></label>
            <input type="tel" name="phone" class="form-control" value="<?= h($user['phone'] ?? '') ?>" placeholder="e.g. 09123456789" required>
          </div>

          <div class="form-group">
            <label class="form-label">Home Address <span style="color:var(--gold)">*</span></label>
            <textarea name="address" class="form-control" placeholder="House No., Street, Barangay..." required><?= h($user['address'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">City / Province Location <span style="color:var(--gold)">*</span></label>
            <input type="text" name="location" class="form-control" value="<?= h($user['location'] ?? '') ?>" placeholder="e.g. Quezon City, Metro Manila" required>
          </div>

          <hr style="border:none;border-top:1px solid rgba(255,255,255,0.1);margin:24px 0;">

          <h3 style="font-family:var(--font-serif);font-size:1.2rem;color:var(--gold);margin-bottom:20px;">
            <i class="fa-solid fa-id-card"></i> Valid ID Verification (Government / School ID)
          </h3>

          <div class="form-row-2col">
            <div class="form-group">
              <label class="form-label">ID Type <span style="color:var(--gold)">*</span></label>
              <select name="id_type" class="form-control" required>
                <option value="">-- Select ID Type --</option>
                <?php 
                  $types = ['Student ID / School ID', 'Passport', 'Driver\'s License', 'UMID', 'National ID / PhilID', 'SSS / GSIS', 'Postal ID', 'Voter\'s ID', 'PRC ID'];
                  foreach ($types as $t):
                ?>
                  <option value="<?= h($t) ?>" <?= ($user['id_type'] ?? '') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">ID / Student / License Number <span style="color:var(--gold)">*</span></label>
              <input type="text" name="id_number" class="form-control" value="<?= h($user['id_number'] ?? '') ?>" placeholder="Enter ID serial, student ID, or license number" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Upload Valid ID Photo / Document <span style="color:var(--gold)">*</span></label>
            <input type="file" name="id_document" id="idDocInput" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" onchange="handleImageOptimization(this)">
            <small style="color:var(--text-muted);font-size:0.75rem;">Allowed formats: JPG, PNG, WebP, PDF (Up to 15MB supported).</small>
            <div id="idDocCompressStatus" style="display:none;font-size:0.78rem;color:#38bdf8;margin-top:4px;"></div>
            
            <?php if (!empty($user['id_document'])): ?>
              <div style="margin-top:8px;font-size:0.85rem;color:#4ade80;">
                <i class="fa-solid fa-file-check"></i> Current Uploaded Document: 
                <a href="<?= h(getUploadUrl($user['id_document'])) ?>" target="_blank" style="color:var(--gold);text-decoration:underline;">View Document</a>
              </div>
            <?php endif; ?>
          </div>

          <hr style="border:none;border-top:1px solid rgba(255,255,255,0.1);margin:24px 0;">

          <h3 id="security" style="font-family:var(--font-serif);font-size:1.15rem;color:var(--gold);margin-bottom:14px;scroll-margin-top:90px;">
            <i class="fa-solid fa-lock"></i> Security & Password (Optional)
          </h3>

          <div class="form-group">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <label class="form-label" style="margin-bottom:0;">Current Password</label>
              <a href="../auth/forgot-password.php?email=<?= urlencode($user['email'] ?? '') ?>" style="font-size:0.75rem;color:var(--gold);text-decoration:underline;">Forgot current password?</a>
            </div>
            <input type="password" name="current_password" class="form-control" placeholder="••••••••" style="margin-top:6px;">
          </div>

          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="••••••••">
          </div>

          <button type="submit" class="btn btn-gold" style="margin-top:10px;padding:12px 28px;font-size:1rem;">
            <i class="fa-solid fa-shield-halved"></i> Save Profile & Submit Verification
          </button>
        </form>
      </div>

    </div>
  </main>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/user.js"></script>
<script>
function previewImage(input, previewId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById(previewId).src = e.target.result;
    }
    reader.readAsDataURL(input.files[0]);
  }
}

// Client-side auto-optimizer for huge smartphone camera images
function handleImageOptimization(input) {
  if (typeof optimizeImageInput === 'function') {
    optimizeImageInput(input, {
      maxDim: 1600,
      minBytes: 512 * 1024,
      quality: 0.86,
      statusElId: 'idDocCompressStatus'
    });
  }
}
</script>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
</body>
</html>
