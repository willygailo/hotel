<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';
require_once __DIR__ . '/../../backend/core/email.php';

requireRole('admin');
releaseReadOnlySession();
ensureTempCredentialSchema();

$adminId = currentUserId();
$tab = $_GET['tab'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$buildUsersRedirect = function () use ($tab, $search, $page): string {
    $q = [];
    if ($tab !== 'all') $q['tab'] = $tab;
    if ($search !== '') $q['q'] = $search;
    if ($page > 1) $q['page'] = $page;
    return 'users.php' . (!empty($q) ? ('?' . http_build_query($q)) : '');
};

// Handle Create Walk-In Guest Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_walkin_user') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $fn    = trim($_POST['first_name'] ?? '');
        $mn    = trim($_POST['middle_name'] ?? '');
        $ln    = trim($_POST['last_name'] ?? '');
        $sf    = trim($_POST['suffix'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $pass  = $_POST['initial_password'] ?? '';

        $nameErr = validatePersonName($fn, $ln, $mn, $sf);
        if ($nameErr !== null) {
            setFlash('error', $nameErr);
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid guest email address.');
        } elseif (strlen($pass) < 8) {
            setFlash('error', 'Initial password must be at least 8 characters long.');
        } elseif (dbRow("SELECT id FROM users WHERE email = ?", [$email])) {
            setFlash('error', "An account with email '" . h($email) . "' already exists.");
        } else {
            $hashed = password_hash($pass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            $newId = dbInsert(
                "INSERT INTO users (first_name, middle_name, last_name, suffix, email, phone, password, role, status, email_verified)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'user', 'active', 1)",
                [$fn, $mn !== '' ? $mn : null, $ln, $sf !== '' ? $sf : null, $email, $phone, $hashed]
            );
            setAdminAssignedTempPassword($newId, $pass);
            auditLog('Admin Created Walk-In Guest', 'Admin Module', "Admin #$adminId registered walk-in Guest #$newId ($email)");
            setFlash('success', "Walk-in guest account created for <strong>" . h(formatFullName(['first_name' => $fn, 'middle_name' => $mn, 'last_name' => $ln, 'suffix' => $sf])) . "</strong> (" . h($email) . "). View or copy the temporary password in the Credentials column.");
        }
    }
    header("Location: " . $buildUsersRedirect());
    exit;
}

// Handle Edit User Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $uId   = (int)($_POST['user_id'] ?? 0);

    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $target = dbRow("SELECT * FROM users WHERE id = ? AND role = 'user'", [$uId]);
        if (!$target) {
            setFlash('error', 'User account not found.');
        } else {
            $fn       = trim($_POST['first_name'] ?? '');
            $mn       = trim($_POST['middle_name'] ?? '');
            $ln       = trim($_POST['last_name'] ?? '');
            $sf       = trim($_POST['suffix'] ?? '');
            $email    = strtolower(trim($_POST['email'] ?? ''));
            $phone    = trim($_POST['phone'] ?? '');
            $address  = trim($_POST['address'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $status   = in_array($_POST['status'] ?? '', ['active', 'banned'], true) ? $_POST['status'] : 'active';
            $kyc      = in_array($_POST['verification_status'] ?? '', ['unverified', 'pending', 'verified', 'rejected'], true) ? $_POST['verification_status'] : 'unverified';
            $isVer    = ($kyc === 'verified') ? 1 : 0;

            $nameErr = validatePersonName($fn, $ln, $mn, $sf, $uId);
            if ($nameErr !== null) {
                setFlash('error', $nameErr);
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                setFlash('error', 'Please enter a valid email address.');
            } else {
                $emailExists = dbRow("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $uId]);
                if ($emailExists) {
                    setFlash('error', "The email '$email' is already taken by another account.");
                } else {
                    $prevKyc = $target['verification_status'] ?? 'unverified';
                    $verifiedByVal = $target['verified_by'] ?? null;
                    $verifiedAtVal = $target['verified_at'] ?? null;
                    $rejReasonVal  = $target['rejection_reason'] ?? null;

                    if ($kyc === 'verified' && $prevKyc !== 'verified') {
                        $verifiedByVal = $adminId;
                        $verifiedAtVal = date('Y-m-d H:i:s');
                        $rejReasonVal  = null;
                        createNotification($uId, 'KYC Identity Verified!', 'Your identity verification status has been marked Verified by hotel administration.', 'system');
                    } elseif ($kyc === 'rejected' && $prevKyc !== 'rejected') {
                        $verifiedByVal = $adminId;
                        $verifiedAtVal = date('Y-m-d H:i:s');
                        if (empty($rejReasonVal)) {
                            $rejReasonVal = 'Marked rejected by administration during account review.';
                        }
                        createNotification($uId, 'ID Verification Action Required', 'Your ID verification status requires updates: ' . $rejReasonVal, 'alert');
                    }

                    dbExec("UPDATE users SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        suffix = ?, 
                        email = ?, 
                        phone = ?, 
                        address = ?, 
                        location = ?, 
                        status = ?, 
                        verification_status = ?, 
                        profile_verified = ?,
                        verified_by = ?,
                        verified_at = ?,
                        rejection_reason = ?
                    WHERE id = ? AND role = 'user'", [
                        $fn, $mn, $ln, $sf, $email, $phone, $address, $location, $status, $kyc, $isVer,
                        $verifiedByVal, $verifiedAtVal, $rejReasonVal, $uId
                    ]);

                    auditLog('Admin Updated User Account', 'Admin Module', "Admin #$adminId edited Guest Account #$uId ($email)");
                    setFlash('success', "Account for <strong>" . h("$fn $ln") . "</strong> (#$uId) has been updated successfully.");
                }
            }
        }
    }
    header("Location: " . $buildUsersRedirect());
    exit;
}

// Handle Delete User Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $uId   = (int)($_POST['user_id'] ?? 0);

    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } elseif ($uId === $adminId) {
        setFlash('error', 'You cannot delete your own admin account.');
    } else {
        $target = dbRow("SELECT * FROM users WHERE id = ? AND role = 'user'", [$uId]);
        if (!$target) {
            setFlash('error', 'User account not found or already deleted.');
        } else {
            dbExec("DELETE FROM users WHERE id = ? AND role = 'user'", [$uId]);
            auditLog('Admin Deleted User Account', 'Admin Module', "Admin #$adminId permanently deleted User #$uId ({$target['email']})");
            setFlash('success', "User account for <strong>" . h(formatFullName($target)) . "</strong> (" . h($target['email']) . ") has been permanently deleted.");
        }
    }
    header("Location: " . $buildUsersRedirect());
    exit;
}

// Handle master password reset by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['reset_password', 'generate_link'], true)) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $uId = (int)($_POST['user_id'] ?? 0);
    $reqId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'];

    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $target = dbRow("SELECT * FROM users WHERE id = ? AND role = 'user'", [$uId]);
        if (!$target) {
            setFlash('error', 'User account not found.');
        } elseif ($action === 'generate_link') {
            $resetToken = bin2hex(random_bytes(32));
            $resetExpires = date('Y-m-d H:i:s', strtotime('+2 hours'));
            dbExec("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?", [$resetToken, $resetExpires, $uId]);

            if ($reqId > 0) {
                dbExec(
                    "UPDATE password_reset_requests SET status = 'completed', dispatch_type = 'link', new_password_assigned = NULL, resolved_at = NOW(), user_notified_at = NULL WHERE id = ?",
                    [$reqId]
                );
            }
            $updatedPending = dbExec(
                "UPDATE password_reset_requests SET status = 'completed', dispatch_type = 'link', new_password_assigned = NULL, resolved_at = NOW(), user_notified_at = NULL WHERE (user_id = ? OR email = ?) AND status = 'pending'",
                [$uId, $target['email']]
            );
            if ($reqId <= 0 && $updatedPending === 0) {
                dbInsert(
                    "INSERT INTO password_reset_requests (user_id, email, phone, status, dispatch_type, new_password_assigned, requested_at, resolved_at)
                     VALUES (?, ?, ?, 'completed', 'link', NULL, NOW(), NOW())",
                    [$uId, $target['email'], $target['phone'] ?? null]
                );
            }

            $oneTimeLink = SITE_URL . '/auth/reset-password.php?token=' . $resetToken;
            $emailNote = '';
            if (isSmtpConfigured()) {
                $sendErr = null;
                $sent = sendEmail(
                    $target['email'],
                    'Password Reset Link — ' . SITE_NAME,
                    emailTemplateResetPassword($oneTimeLink, formatFullName($target), 2),
                    "Hello " . formatFullName($target) . ",\n\nUse this one-time link to reset your password (valid for 2 hours):\n{$oneTimeLink}",
                    $sendErr
                );
                if ($sent) {
                    $emailNote = ' Email notification also sent.';
                }
            }
            auditLog('Admin Generated Reset Link', 'Admin Module', "Admin generated 1-time reset link for Guest #$uId ({$target['email']})");
            setFlash('success', "One-time secure reset link generated for " . h(formatFullName($target)) . " (" . h($target['email']) . "): {$oneTimeLink}{$emailNote}");
        } else {
            $newPass = $_POST['new_password'] ?? '';
            if (strlen($newPass) < 8) {
                setFlash('error', 'New password must be at least 8 characters long.');
            } else {
                $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                dbExec("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL, status = 'active' WHERE id = ?", [$hashed, $uId]);
                setAdminAssignedTempPassword($uId, $newPass);

                if ($reqId > 0) {
                    dbExec(
                        "UPDATE password_reset_requests SET status = 'completed', dispatch_type = 'password', new_password_assigned = ?, resolved_at = NOW(), user_notified_at = NULL WHERE id = ?",
                        [$newPass, $reqId]
                    );
                }
                $updatedPending = dbExec(
                    "UPDATE password_reset_requests SET status = 'completed', dispatch_type = 'password', new_password_assigned = ?, resolved_at = NOW(), user_notified_at = NULL WHERE (user_id = ? OR email = ?) AND status = 'pending'",
                    [$newPass, $uId, $target['email']]
                );
                // Always record admin-initiated reset in password_reset_requests even if user did not submit Forgot Password form
                if ($reqId <= 0 && $updatedPending === 0) {
                    dbInsert(
                        "INSERT INTO password_reset_requests (user_id, email, phone, status, dispatch_type, new_password_assigned, requested_at, resolved_at)
                         VALUES (?, ?, ?, 'completed', 'password', ?, NOW(), NOW())",
                        [$uId, $target['email'], $target['phone'] ?? null, $newPass]
                    );
                }

                createNotification($uId, 'Password Updated', 'Your account password was updated by the Administrator. If you did not request this, please contact frontdesk assistance.', 'alert');

                $emailNote = '';
                if (isSmtpConfigured()) {
                    $sendErr = null;
                    $loginLink = SITE_URL . '/auth/login.php?email=' . urlencode($target['email']);
                    $sent = sendEmail(
                        $target['email'],
                        'Your Password Has Been Updated — ' . SITE_NAME,
                        emailTemplateNotification(
                            'Password Updated by Admin',
                            'Your account password has been updated by the Hotel Administrator. Please check your Forgot Password page popup for your temporary password or contact the frontdesk for assistance.',
                            formatFullName($target),
                            $loginLink,
                            'Go to Login'
                        ),
                        "Hello " . formatFullName($target) . ",\n\nYour password has been updated by the Hotel Administrator. Visit {$loginLink} to log in.",
                        $sendErr
                    );
                    if ($sent) {
                        $emailNote = ' Email notification also sent.';
                    }
                }

                auditLog('Admin Reset User Password', 'Admin Module', "Admin reset password for Guest #$uId ({$target['email']}) to newly dispatched temporary password.");
                setFlash('success', "Temporary password updated for <strong>" . h(formatFullName($target)) . "</strong> (" . h($target['email']) . ") and saved in Credentials.{$emailNote}");
            }
        }
    }
    header("Location: " . $buildUsersRedirect());
    exit;
}

// Handle KYC Approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_kyc') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $uId = (int)($_POST['user_id'] ?? 0);

    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $target = dbRow("SELECT * FROM users WHERE id = ? AND role = 'user'", [$uId]);
        if ($target) {
            dbExec("UPDATE users SET 
                verification_status = 'verified',
                profile_verified = 1,
                rejection_reason = NULL,
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ?", [$adminId, $uId]);

            createNotification($uId, 'KYC Identity Verified!', 'Congratulations! Your ID has been reviewed and approved by hotel administration. You now hold Verified User status.', 'system');
            auditLog('Admin Approved KYC', 'Admin Module', "Admin #$adminId approved ID verification for User #$uId ({$target['email']})");
            setFlash('success', "Identity verification for " . h(formatFullName($target)) . " was successfully approved!");
        } else {
            setFlash('error', 'User account not found.');
        }
    }
    header("Location: " . $buildUsersRedirect());
    exit;
}

// Handle KYC Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_kyc') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $uId = (int)($_POST['user_id'] ?? 0);
    $reason = trim($_POST['rejection_reason'] ?? '');
    if (empty($reason)) {
        $reason = 'The submitted document was illegible or did not match registered account details.';
    }

    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $target = dbRow("SELECT * FROM users WHERE id = ? AND role = 'user'", [$uId]);
        if ($target) {
            dbExec("UPDATE users SET 
                verification_status = 'rejected',
                profile_verified = 0,
                rejection_reason = ?,
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ?", [$reason, $adminId, $uId]);

            createNotification($uId, 'ID Verification Action Required', 'Your ID submission was not approved: ' . $reason . '. Please visit your profile to submit a valid document.', 'alert');
            auditLog('Admin Rejected KYC', 'Admin Module', "Admin #$adminId rejected ID verification for Guest #$uId ({$target['email']}). Reason: $reason");
            setFlash('info', "Identity verification for " . h(formatFullName($target)) . " was marked as rejected.");
        } else {
            setFlash('error', 'User account not found.');
        }
    }
    header("Location: " . $buildUsersRedirect());
    exit;
}

// Handle ban / unban (CSRF-protected POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $uId = (int)($_POST['user_id'] ?? 0);
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $target = dbRow("SELECT * FROM users WHERE id = ?", [$uId]);
        if ($target && $target['role'] === 'user') {
            $newStatus = $target['status'] === 'active' ? 'banned' : 'active';
            dbExec("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $uId]);
            auditLog('Toggled User Status', 'Admin Module', "User #$uId status changed to $newStatus");
            setFlash('info', "User status changed to $newStatus.");
        }
    }
    header("Location: " . $buildUsersRedirect());
    exit;
}

// Counts for KYC tabs
$countRow = dbRow("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN (verification_status = 'pending' OR (verification_status IS NULL AND id_document IS NOT NULL AND profile_verified = 0)) THEN 1 ELSE 0 END) as pending_kyc,
        SUM(CASE WHEN (verification_status = 'verified' OR profile_verified = 1) THEN 1 ELSE 0 END) as verified_kyc,
        SUM(CASE WHEN (verification_status = 'rejected') THEN 1 ELSE 0 END) as rejected_kyc,
        SUM(CASE WHEN (verification_status = 'unverified' OR (profile_verified = 0 AND (id_document IS NULL OR id_document = ''))) THEN 1 ELSE 0 END) as unverified_kyc
    FROM users 
    WHERE role = 'user'
");

$pendingKycCount = (int)($countRow['pending_kyc'] ?? 0);
$verifiedKycCount = (int)($countRow['verified_kyc'] ?? 0);
$rejectedKycCount = (int)($countRow['rejected_kyc'] ?? 0);
$unverifiedKycCount = (int)($countRow['unverified_kyc'] ?? 0);
$totalUserCount = (int)($countRow['total_users'] ?? 0);
$pendingResetsCount = (int)(dbRow("SELECT COUNT(*) as c FROM password_reset_requests WHERE status = 'pending'")['c'] ?? 0);

// Filter & paginate based on active tab
$resetRequests = [];
$users = [];
$totalTabRows = 0;
$totalPages = 1;
$offset = ($page - 1) * $perPage;

if ($tab === 'resets') {
    $rCond = [];
    $rParams = [];
    if ($search !== '') {
        $rCond[] = "(pr.email LIKE ? OR pr.phone LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $like = '%' . $search . '%';
        array_push($rParams, $like, $like, $like, $like);
    }
    $rWhere = !empty($rCond) ? ('WHERE ' . implode(' AND ', $rCond)) : '';
    $totalTabRows = (int)(dbRow("SELECT COUNT(*) as c FROM password_reset_requests pr LEFT JOIN users u ON pr.user_id = u.id {$rWhere}", $rParams)['c'] ?? 0);
    $totalPages = max(1, (int)ceil($totalTabRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }
    $resetRequests = dbRows("
        SELECT pr.*, u.first_name, u.last_name, u.avatar, u.password_is_temp
        FROM password_reset_requests pr 
        LEFT JOIN users u ON pr.user_id = u.id 
        {$rWhere}
        ORDER BY CASE WHEN pr.status = 'pending' THEN 0 ELSE 1 END, pr.id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ", $rParams);
} else {
    $uCond = ["u.role = 'user'"];
    $uParams = [];
    if ($tab === 'pending') {
        $uCond[] = "(u.verification_status = 'pending' OR (u.verification_status IS NULL AND u.id_document IS NOT NULL AND u.profile_verified = 0))";
    } elseif ($tab === 'verified') {
        $uCond[] = "(u.verification_status = 'verified' OR u.profile_verified = 1)";
    } elseif ($tab === 'rejected') {
        $uCond[] = "u.verification_status = 'rejected'";
    } elseif ($tab === 'unverified') {
        $uCond[] = "(u.verification_status = 'unverified' OR (u.profile_verified = 0 AND (u.id_document IS NULL OR u.id_document = '')))";
    }
    if ($search !== '') {
        $uCond[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $like = '%' . $search . '%';
        array_push($uParams, $like, $like, $like, $like);
    }
    $whereClause = 'WHERE ' . implode(' AND ', $uCond);

    $totalTabRows = (int)(dbRow("SELECT COUNT(*) as c FROM users u {$whereClause}", $uParams)['c'] ?? 0);
    $totalPages = max(1, (int)ceil($totalTabRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $users = dbRows("
        SELECT u.*, COUNT(b.id) as bookings_count 
        FROM users u 
        LEFT JOIN bookings b ON u.id = b.user_id 
        {$whereClause}
        GROUP BY u.id 
        ORDER BY 
            CASE WHEN u.verification_status = 'pending' THEN 0 ELSE 1 END,
            u.id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ", $uParams);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users & KYC Verification — Admin Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/admin.css')) ?>">
  <style>
    .kyc-tabs { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 12px; flex-wrap: wrap; }
    .kyc-tab { text-decoration: none; padding: 8px 18px; border-radius: 8px; font-size: 0.88rem; font-weight: 500; color: var(--text-muted); background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 8px; transition: all 0.2s ease; }
    .kyc-tab:hover { background: rgba(201,168,76,0.1); color: var(--gold); }
    .kyc-tab.active { background: var(--gold); color: #0d1238; font-weight: 600; border-color: var(--gold); }
    .kyc-pill { background: rgba(0,0,0,0.25); color: inherit; padding: 2px 7px; border-radius: 12px; font-size: 0.72rem; }
    .kyc-tab.active .kyc-pill { background: #0d1238; color: #fff; }
    .doc-preview-box { background: #0b1120; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; text-align: center; max-height: 280px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    .doc-preview-box img { max-width: 100%; max-height: 260px; object-fit: contain; border-radius: 4px; }
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
        <div class="topbar-title">User Accounts &amp; KYC Verification</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;">
        <div>
          <h1 class="page-header-title">User Accounts &amp; Identity Verification</h1>
          <div class="page-header-sub">Review government-issued IDs, manage walk-in guests, and dispatch temporary passwords.</div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <form action="users.php" method="GET" style="display:flex;gap:6px;align-items:center;">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">
            <input type="text" name="q" value="<?= h($search) ?>" data-table-search data-table-target="<?= $tab === 'resets' ? '#resetsTable' : '#usersTable' ?>" class="form-control" placeholder="Search name, email, phone..." style="min-width:230px;background:rgba(255,255,255,0.05);font-size:0.85rem;">
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if ($search !== ''): ?>
              <a href="users.php?tab=<?= urlencode($tab) ?>" class="btn btn-ghost btn-sm" title="Clear"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
          </form>
          <button type="button" class="btn btn-gold btn-sm" onclick="openWalkinGuestModal()">
            <i class="fa-solid fa-user-plus"></i> Add Walk-In Guest
          </button>
        </div>
      </div>

      <!-- KYC & Reset Filter Tabs -->
      <div class="kyc-tabs">
        <?php $qSuffix = $search !== '' ? '&q=' . urlencode($search) : ''; ?>
        <a href="users.php?tab=all<?= $qSuffix ?>" class="kyc-tab <?= $tab === 'all' ? 'active' : '' ?>">
          <i class="fa-solid fa-users"></i> All Users <span class="kyc-pill"><?= $totalUserCount ?></span>
        </a>
        <a href="users.php?tab=pending<?= $qSuffix ?>" class="kyc-tab <?= $tab === 'pending' ? 'active' : '' ?>" style="<?= $pendingKycCount > 0 ? 'border-color:#eab308;' : '' ?>">
          <i class="fa-solid fa-clock"></i> Pending Review 
          <span class="kyc-pill" style="<?= $pendingKycCount > 0 ? 'background:#eab308;color:#000;font-weight:700;' : '' ?>"><?= $pendingKycCount ?></span>
        </a>
        <a href="users.php?tab=resets<?= $qSuffix ?>" class="kyc-tab <?= $tab === 'resets' ? 'active' : '' ?>" style="<?= $pendingResetsCount > 0 ? 'border-color:#f97316;' : '' ?>">
          <i class="fa-solid fa-key"></i> Password Resets 
          <span class="kyc-pill" style="<?= $pendingResetsCount > 0 ? 'background:#f97316;color:#fff;font-weight:700;' : '' ?>"><?= $pendingResetsCount ?></span>
        </a>
        <a href="users.php?tab=verified<?= $qSuffix ?>" class="kyc-tab <?= $tab === 'verified' ? 'active' : '' ?>">
          <i class="fa-solid fa-shield-check"></i> Verified <span class="kyc-pill"><?= $verifiedKycCount ?></span>
        </a>
        <a href="users.php?tab=unverified<?= $qSuffix ?>" class="kyc-tab <?= $tab === 'unverified' ? 'active' : '' ?>">
          <i class="fa-solid fa-user-clock"></i> Unverified <span class="kyc-pill"><?= $unverifiedKycCount ?></span>
        </a>
        <a href="users.php?tab=rejected<?= $qSuffix ?>" class="kyc-tab <?= $tab === 'rejected' ? 'active' : '' ?>">
          <i class="fa-solid fa-circle-exclamation"></i> Rejected <span class="kyc-pill"><?= $rejectedKycCount ?></span>
        </a>
      </div>

      <?php if ($tab === 'resets'): ?>
      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table" id="resetsTable">
              <thead>
                <tr>
                  <th>Request ID</th>
                  <th>User / Customer</th>
                  <th>Contact Email &amp; Phone</th>
                  <th>Requested Time</th>
                  <th>Status</th>
                  <th>Assigned Password</th>
                  <th style="text-align:right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($resetRequests)): ?>
                  <tr data-empty-row>
                    <td colspan="7" style="text-align:center;padding:36px;color:var(--text-muted);">
                      <i class="fa-solid fa-circle-check" style="font-size:2rem;color:#22c55e;margin-bottom:8px;display:block;"></i>
                      No password reset requests found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($resetRequests as $req): ?>
                    <tr>
                      <td>#<?= $req['id'] ?></td>
                      <td>
                        <strong><?= h(trim(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? '')) ?: 'Guest') ?></strong>
                        <?php if ($req['user_id']): ?>
                          <div style="font-size:0.75rem;color:var(--text-muted);">User #<?= $req['user_id'] ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div><?= h($req['email']) ?></div>
                        <?php if (!empty($req['phone'])): ?>
                          <small style="color:var(--text-muted);"><i class="fa-solid fa-phone"></i> <?= h($req['phone']) ?></small>
                        <?php endif; ?>
                      </td>
                      <td><?= formatDate($req['requested_at'], 'M d, Y h:i A') ?></td>
                      <td>
                        <?php if ($req['status'] === 'pending'): ?>
                          <span class="badge" style="background:#eab308;color:#000;font-weight:700;"><i class="fa-solid fa-clock"></i> Pending</span>
                        <?php elseif ($req['status'] === 'dismissed'): ?>
                          <span class="badge" style="background:#475569;color:#e2e8f0;"><i class="fa-solid fa-ban"></i> Superseded</span>
                        <?php else: ?>
                          <span class="badge" style="background:#22c55e;color:#fff;"><i class="fa-solid fa-check"></i> Dispatched</span>
                          <?php if (!empty($req['user_notified_at'])): ?>
                            <div style="font-size:0.72rem;color:#4ade80;margin-top:4px;" title="Viewed on <?= h(formatDate($req['user_notified_at'], 'M d, Y h:i A')) ?>">
                              <i class="fa-solid fa-eye"></i> Viewed by user
                            </div>
                          <?php elseif (!empty($req['request_token'])): ?>
                            <div style="font-size:0.72rem;color:#93c5fd;margin-top:4px;">
                              <i class="fa-solid fa-hourglass-half"></i> Waiting for user
                            </div>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($req['new_password_assigned'])): ?>
                          <?= renderAdminCredentialBadge($req['new_password_assigned']) ?>
                        <?php elseif (($req['dispatch_type'] ?? '') === 'link'): ?>
                          <span style="color:var(--gold);font-size:0.8rem;"><i class="fa-solid fa-link"></i> One-time reset link</span>
                        <?php elseif (($req['dispatch_type'] ?? '') === 'password' && $req['status'] === 'completed'): ?>
                          <span class="badge badge-secondary" style="font-size:0.7rem;"><i class="fa-solid fa-lock"></i> Cleared / User Changed</span>
                        <?php else: ?>
                          <span style="color:var(--text-muted);font-size:0.8rem;">None yet</span>
                        <?php endif; ?>
                      </td>
                      <td style="text-align:right;">
                        <?php if ($req['user_id']): ?>
                          <button type="button" class="btn btn-gold btn-sm" onclick="openResetModal(<?= $req['user_id'] ?>, '<?= h(addslashes(trim(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? '')) ?: 'Guest')) ?>', '<?= h(addslashes($req['email'])) ?>', <?= $req['id'] ?>)">
                            <i class="fa-solid fa-key"></i> <?= $req['status'] === 'pending' ? 'Dispatch Password' : 'Change Again' ?>
                          </button>
                        <?php else: ?>
                          <span style="font-size:0.8rem;color:var(--text-muted);">Account not registered</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
              <span>Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalTabRows ?> record(s))</span>
              <div class="admin-pagination-links">
                <?php if ($page > 1): ?>
                  <a href="users.php?tab=resets<?= $qSuffix ?>&page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                  <a href="users.php?tab=resets<?= $qSuffix ?>&page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm">Next &raquo;</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table" id="usersTable">
              <thead>
                <tr>
                  <th class="col-nowrap">ID</th>
                  <th>User Name</th>
                  <th>Email &amp; Contact</th>
                  <th class="col-nowrap">Credentials</th>
                  <th class="col-nowrap">ID Document Info</th>
                  <th class="col-nowrap">KYC Status</th>
                  <th class="col-nowrap">Bookings</th>
                  <th class="col-nowrap">Account</th>
                  <th class="col-nowrap" style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($users)): ?>
                  <tr data-empty-row>
                    <td colspan="9" style="text-align:center;padding:36px;color:var(--text-muted);">
                      <i class="fa-solid fa-folder-open" style="font-size:2rem;margin-bottom:8px;display:block;"></i>
                      No users found matching this filter.
                    </td>
                  </tr>
                <?php endif; ?>

                <?php foreach ($users as $u): 
                  $kycStatus = $u['verification_status'] ?? ($u['profile_verified'] ? 'verified' : (!empty($u['id_document']) ? 'pending' : 'unverified'));
                  $hasDoc = !empty($u['id_document']);
                  $tempUserPass = getAdminTempPasswordForRow($u);
                  $hasActiveLink = !empty($u['reset_token']) && !empty($u['reset_expires']) && strtotime((string)$u['reset_expires']) > time();
                ?>
                  <tr>
                    <td class="col-nowrap">#<?= $u['id'] ?></td>
                    <td>
                      <div class="user-account-inline">
                        <img class="user-account-avatar" src="<?= h(getUserAvatarUrl($u['avatar'] ?? null)) ?>" onerror="this.src='../frontend/assets/images/avatars/default-avatar.png'; this.onerror=null;" alt="">
                        <div class="user-account-details">
                          <strong style="color:#fff;"><?= h(formatFullName($u)) ?></strong>
                          <div class="user-account-sub">Registered <?= formatDate($u['created_at'], 'M d, Y') ?></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div><?= h($u['email']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-muted);"><?= h($u['phone'] ?: 'No Phone') ?></div>
                    </td>
                    <td class="col-nowrap">
                      <?= renderAdminCredentialBadge($tempUserPass, $hasActiveLink) ?>
                    </td>
                    <td class="col-nowrap">
                      <?php if (!empty($u['id_type'])): ?>
                        <div style="font-size:0.85rem;font-weight:600;color:#fff;"><?= h($u['id_type']) ?></div>
                        <div style="font-size:0.75rem;color:var(--text-muted);font-family:monospace;"><?= h($u['id_number'] ?: 'No Number') ?></div>
                      <?php else: ?>
                        <span style="color:var(--text-muted);font-size:0.8rem;">Not Submitted</span>
                      <?php endif; ?>
                    </td>
                    <td class="col-nowrap">
                      <?= kycBadge($kycStatus) ?>
                      <?php if ($kycStatus === 'pending' && !empty($u['verification_submitted_at'])): ?>
                        <div style="font-size:0.7rem;color:#eab308;margin-top:2px;">
                          Submitted <?= formatDate($u['verification_submitted_at'], 'M d, h:i A') ?>
                        </div>
                      <?php elseif ($kycStatus === 'rejected' && !empty($u['rejection_reason'])): ?>
                        <div style="font-size:0.7rem;color:#ef4444;margin-top:2px;" title="<?= h($u['rejection_reason']) ?>">
                          <i class="fa-solid fa-comment-dots"></i> <?= h(truncateText($u['rejection_reason'], 24)) ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td class="col-nowrap"><span class="badge badge-gold"><?= $u['bookings_count'] ?></span></td>
                    <td class="col-nowrap"><?= statusBadge($u['status']) ?></td>
                    <td class="col-nowrap" style="text-align:right;">
                      <div style="display:inline-flex;gap:6px;justify-content:flex-end;">
                        <?php if ($hasDoc): ?>
                          <button type="button" class="btn btn-sm <?= $kycStatus === 'pending' ? 'btn-gold' : 'btn-ghost' ?>" 
                            onclick='openKycReviewModal(<?= json_encode([
                              "id" => $u["id"],
                              "name" => formatFullName($u),
                              "email" => $u["email"],
                              "phone" => $u["phone"] ?? "N/A",
                              "address" => $u["address"] ?? "N/A",
                              "location" => $u["location"] ?? "N/A",
                              "id_type" => $u["id_type"] ?? "N/A",
                              "id_number" => $u["id_number"] ?? "N/A",
                              "id_document_url" => getUploadUrl($u["id_document"]),
                              "id_document_raw" => $u["id_document"],
                              "status" => $kycStatus,
                              "submitted_at" => $u["verification_submitted_at"] ? formatDate($u["verification_submitted_at"], "M d, Y h:i A") : null,
                              "verified_at" => $u["verified_at"] ? formatDate($u["verified_at"], "M d, Y h:i A") : null,
                              "rejection_reason" => $u["rejection_reason"] ?? ""
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Review Government ID">
                            <i class="fa-solid fa-id-card"></i> <?= $kycStatus === 'pending' ? 'Review ID' : 'View ID' ?>
                          </button>
                        <?php endif; ?>

                        <button type="button" class="btn btn-ghost btn-sm" 
                          onclick='openEditUserModal(<?= json_encode([
                            "id" => $u["id"],
                            "first_name" => $u["first_name"],
                            "middle_name" => $u["middle_name"] ?? "",
                            "last_name" => $u["last_name"],
                            "suffix" => $u["suffix"] ?? "",
                            "email" => $u["email"],
                            "phone" => $u["phone"] ?? "",
                            "address" => $u["address"] ?? "",
                            "location" => $u["location"] ?? "",
                            "status" => $u["status"],
                            "verification_status" => $u["verification_status"] ?? "unverified"
                          ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Account Details">
                          <i class="fa-solid fa-pen-to-square"></i>
                        </button>

                        <button type="button" class="btn btn-ghost btn-sm" onclick="openResetModal(<?= $u['id'] ?>, '<?= addslashes(h(formatFullName($u))) ?>', '<?= addslashes(h($u['email'])) ?>')" title="Set / Reset Temporary Password">
                          <i class="fa-solid fa-key"></i>
                        </button>

                        <form action="<?= h($buildUsersRedirect()) ?>" method="POST" style="display:inline;">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="toggle_status">
                          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                          <button type="submit" class="btn <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-success' ?> btn-sm" title="<?= $u['status'] === 'active' ? 'Ban User' : 'Activate User' ?>">
                            <?= $u['status'] === 'active' ? '<i class="fa-solid fa-ban"></i>' : '<i class="fa-solid fa-check"></i>' ?>
                          </button>
                        </form>

                        <form action="<?= h($buildUsersRedirect()) ?>" method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete account for <?= addslashes(h(formatFullName($u))) ?> (#<?= $u['id'] ?>)? This will delete all associated bookings, payments, and reviews. This cannot be undone!')">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm" title="Delete Account Permanently">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
              <span>Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalTabRows ?> user(s))</span>
              <div class="admin-pagination-links">
                <?php if ($page > 1): ?>
                  <a href="users.php?tab=<?= urlencode($tab) ?><?= $qSuffix ?>&page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                  <a href="users.php?tab=<?= urlencode($tab) ?><?= $qSuffix ?>&page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm">Next &raquo;</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<!-- Admin Add Walk-In Guest Modal -->
<div id="walkinGuestModal" class="admin-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;padding:16px;overflow-y:auto;">
  <div class="glass-card" style="max-width:540px;width:100%;padding:28px;background:var(--navy-light);border:1px solid var(--border-gold);border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,0.6);margin:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid rgba(255,255,255,0.08);padding-bottom:12px;">
      <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.3rem;margin:0;">
        <i class="fa-solid fa-user-plus"></i> Register Walk-In Guest
      </h3>
      <button type="button" onclick="closeWalkinGuestModal()" style="background:none;border:none;color:#aaa;font-size:1.3rem;cursor:pointer;">&times;</button>
    </div>
    <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:16px;">
      Gumawa ng verified guest account para sa walk-in customer. Ang initial temporary password ay makikita sa <strong>Credentials</strong> column hanggang mag-set sila ng sariling password.
    </p>
    <form action="<?= h($buildUsersRedirect()) ?>" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create_walkin_user">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="form-label">First Name <span class="required">*</span></label>
          <input type="text" name="first_name" class="form-control" required minlength="2" maxlength="50" placeholder="e.g. Juan">
        </div>
        <div>
          <label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" class="form-control" maxlength="50" placeholder="Optional">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="form-label">Last Name <span class="required">*</span></label>
          <input type="text" name="last_name" class="form-control" required minlength="2" maxlength="50" placeholder="e.g. Dela Cruz">
        </div>
        <div>
          <label class="form-label">Suffix</label>
          <input type="text" name="suffix" class="form-control" maxlength="20" placeholder="Jr., III">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="form-label">Email Address <span class="required">*</span></label>
          <input type="email" name="email" class="form-control" required placeholder="guest@email.com">
        </div>
        <div>
          <label class="form-label">Contact Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="09123456789">
        </div>
      </div>

      <div style="margin-bottom:18px;">
        <label class="form-label">Initial Temporary Password (Min. 8 chars) <span class="required">*</span></label>
        <div style="display:flex;gap:8px;">
          <input type="text" name="initial_password" id="walkinInitialPass" class="form-control" required minlength="8" placeholder="Click Gen or type password">
          <button type="button" class="btn btn-ghost btn-sm" onclick="generateWalkinPass()" style="white-space:nowrap;" title="Generate random temporary password"><i class="fa-solid fa-dice"></i> Gen</button>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost" onclick="closeWalkinGuestModal()">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fa-solid fa-user-check"></i> Create Guest Account</button>
      </div>
    </form>
  </div>
</div>

<!-- Admin Edit User Account Modal -->
<div id="editUserModal" class="admin-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;padding:16px;overflow-y:auto;">
  <div class="glass-card" style="max-width:560px;width:100%;padding:28px;background:var(--navy-light);border:1px solid var(--border-gold);border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,0.6);margin:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid rgba(255,255,255,0.08);padding-bottom:12px;">
      <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.3rem;margin:0;">
        <i class="fa-solid fa-user-pen"></i> Edit User Account
      </h3>
      <button type="button" onclick="closeEditUserModal()" style="background:none;border:none;color:#aaa;font-size:1.3rem;cursor:pointer;">&times;</button>
    </div>
    
    <form action="<?= h($buildUsersRedirect()) ?>" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="editUserId">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="form-label">First Name <span class="required">*</span></label>
          <input type="text" name="first_name" id="editUserFn" class="form-control" required>
        </div>
        <div>
          <label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" id="editUserMn" class="form-control">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="form-label">Last Name <span class="required">*</span></label>
          <input type="text" name="last_name" id="editUserLn" class="form-control" required>
        </div>
        <div>
          <label class="form-label">Suffix</label>
          <input type="text" name="suffix" id="editUserSf" class="form-control" placeholder="Jr., III">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="form-label">Email Address <span class="required">*</span></label>
          <input type="email" name="email" id="editUserEmail" class="form-control" required>
        </div>
        <div>
          <label class="form-label">Contact Number</label>
          <input type="tel" name="phone" id="editUserPhone" class="form-control">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="form-label">Address / Street</label>
          <input type="text" name="address" id="editUserAddress" class="form-control">
        </div>
        <div>
          <label class="form-label">City / Location</label>
          <input type="text" name="location" id="editUserLocation" class="form-control">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
        <div>
          <label class="form-label">Account Status</label>
          <select name="status" id="editUserStatus" class="form-control">
            <option value="active">Active</option>
            <option value="banned">Banned / Suspended</option>
          </select>
        </div>
        <div>
          <label class="form-label">KYC Verification Status</label>
          <select name="verification_status" id="editUserKyc" class="form-control">
            <option value="unverified">Unverified</option>
            <option value="pending">Pending Review</option>
            <option value="verified">Verified</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost" onclick="closeEditUserModal()">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Master Reset Password Modal -->
<div id="resetPasswordModal" class="admin-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div class="glass-card" style="max-width:440px;width:100%;padding:28px;background:var(--navy-light);border:1px solid var(--border-gold);border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,0.5);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.25rem;margin:0;"><i class="fa-solid fa-key"></i> Master Password Reset</h3>
      <button type="button" onclick="closeResetModal()" style="background:none;border:none;color:#aaa;font-size:1.3rem;cursor:pointer;">&times;</button>
    </div>
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">
      Directly set a temporary password for <strong id="modalUserName" style="color:#fff;"></strong> (<span id="modalUserEmail"></span>).
    </p>
    <form action="<?= h($buildUsersRedirect()) ?>" method="POST" id="resetPassForm">
      <?= csrfField() ?>
      <input type="hidden" name="user_id" id="modalUserId">
      <input type="hidden" name="request_id" id="modalRequestId" value="0">

      <div class="form-group" style="margin-bottom:16px;">
        <label class="form-label">New Temporary Password (Min. 8 chars) <span class="required">*</span></label>
        <div style="display:flex;gap:8px;">
          <input type="text" name="new_password" id="modalNewPassword" class="form-control" minlength="8" placeholder="Enter temporary password">
          <button type="button" class="btn btn-ghost btn-sm" onclick="generateRandomPass()" style="white-space:nowrap;" title="Generate random secure password"><i class="fa-solid fa-dice"></i> Gen</button>
        </div>
        <small style="color:var(--text-muted);font-size:0.75rem;margin-top:4px;display:block;">Required when manually updating password. Not required when generating a reset link.</small>
        <div style="margin-top:10px;padding:8px 10px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.28);border-radius:8px;font-size:0.76rem;color:#bbf7d0;">
          <i class="fa-solid fa-bolt" style="color:#4ade80;margin-right:4px;"></i> Assigned temporary passwords appear cleanly under the <strong>Credentials</strong> column until the guest sets a personal password.
        </div>
      </div>

      <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
        <button type="button" class="btn btn-ghost" onclick="closeResetModal()">Cancel</button>
        <button type="submit" name="action" value="generate_link" formnovalidate class="btn btn-ghost" style="border:1px solid var(--gold);color:var(--gold);"><i class="fa-solid fa-link"></i> Get Reset Link</button>
        <button type="submit" name="action" value="reset_password" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> Update Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Admin KYC Inspection Modal -->
<div id="kycReviewModal" class="admin-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(6px);z-index:9999;align-items:center;justify-content:center;padding:20px;overflow-y:auto;">
  <div class="glass-card" style="max-width:820px;width:100%;padding:28px;background:var(--navy-light);border:1px solid var(--border-gold);border-radius:16px;box-shadow:0 24px 50px rgba(0,0,0,0.6);margin:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:12px;">
      <div>
        <h3 style="font-family:var(--font-serif);color:var(--gold);font-size:1.35rem;margin:0;">
          <i class="fa-solid fa-shield-halved"></i> User Identity &amp; Valid ID Review
        </h3>
        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;">Compare submitted document against registered legal profile details.</div>
      </div>
      <button type="button" onclick="closeKycReviewModal()" style="background:none;border:none;color:#aaa;font-size:1.5rem;cursor:pointer;">&times;</button>
    </div>

    <div class="admin-modal-grid-2-wide-left" style="margin-bottom:24px;">
      <!-- Left: Document Preview -->
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <label style="font-size:0.85rem;color:var(--gold);font-weight:600;"><i class="fa-solid fa-file-image"></i> Submitted Document</label>
          <a id="kycDocExternalLink" href="#" target="_blank" style="font-size:0.75rem;color:var(--gold);text-decoration:underline;">
            <i class="fa-solid fa-up-right-from-square"></i> Open Full View
          </a>
        </div>
        
        <div class="doc-preview-box" id="kycDocPreviewContainer">
          <img id="kycDocImg" src="" alt="ID Document Preview" style="display:none;">
          <div id="kycDocPdfNotice" style="display:none;padding:20px;">
            <i class="fa-solid fa-file-pdf" style="font-size:3rem;color:#ef4444;margin-bottom:10px;"></i>
            <div style="font-size:0.9rem;font-weight:600;color:#fff;">PDF Document Uploaded</div>
            <a id="kycDocPdfLink" href="#" target="_blank" class="btn btn-gold btn-sm" style="margin-top:12px;">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> Open PDF in New Tab
            </a>
          </div>
        </div>

        <div style="margin-top:12px;padding:12px;background:rgba(0,0,0,0.25);border-radius:8px;font-size:0.82rem;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="color:var(--text-muted);">Current Status:</span>
            <span id="kycModalStatusBadge"></span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="color:var(--text-muted);">Submitted Date:</span>
            <span id="kycModalSubmittedAt" style="color:#fff;"></span>
          </div>
          <div id="kycModalRejectionBox" style="display:none;margin-top:8px;padding-top:8px;border-top:1px dashed rgba(239,68,68,0.3);color:#fca5a5;">
            <strong>Previous Rejection:</strong> <span id="kycModalRejectionReason"></span>
          </div>
        </div>
      </div>

      <!-- Right: Registered Details -->
      <div>
        <label style="font-size:0.85rem;color:var(--gold);font-weight:600;display:block;margin-bottom:12px;">
          <i class="fa-solid fa-address-card"></i> Registered User Information
        </label>

        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:16px;font-size:0.88rem;display:flex;flex-direction:column;gap:10px;">
          <div>
            <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Legal Full Name</div>
            <div id="kycModalGuestName" style="font-weight:700;font-size:1.05rem;color:#fff;margin-top:2px;"></div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <div style="font-size:0.75rem;color:var(--text-muted);">ID Type</div>
              <div id="kycModalIdType" style="font-weight:600;color:var(--gold);margin-top:2px;"></div>
            </div>
            <div>
              <div style="font-size:0.75rem;color:var(--text-muted);">ID Serial Number</div>
              <div id="kycModalIdNumber" style="font-weight:600;color:#fff;font-family:monospace;margin-top:2px;"></div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <div style="font-size:0.75rem;color:var(--text-muted);">Contact Phone</div>
              <div id="kycModalPhone" style="color:#fff;margin-top:2px;"></div>
            </div>
            <div>
              <div style="font-size:0.75rem;color:var(--text-muted);">Email Address</div>
              <div id="kycModalEmail" style="color:#fff;margin-top:2px;"></div>
            </div>
          </div>

          <div>
            <div style="font-size:0.75rem;color:var(--text-muted);">City / Province Location</div>
            <div id="kycModalLocation" style="color:#fff;margin-top:2px;"></div>
          </div>

          <div>
            <div style="font-size:0.75rem;color:var(--text-muted);">Street Address</div>
            <div id="kycModalAddress" style="color:#fff;margin-top:2px;font-size:0.82rem;"></div>
          </div>
        </div>

        <!-- Adjudication Action Forms -->
        <div style="margin-top:16px;">
          <div style="display:flex;gap:10px;">
            <!-- Approve Form -->
            <form action="<?= h($buildUsersRedirect()) ?>" method="POST" style="flex:1;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="approve_kyc">
              <input type="hidden" name="user_id" id="kycApproveUserId">
              <button type="submit" class="btn btn-success" style="width:100%;padding:12px 14px;background:#16a34a;border-color:#16a34a;color:#fff;font-weight:600;" onclick="return confirm('Approve this user\'s valid ID verification?');">
                <i class="fa-solid fa-circle-check"></i> Approve ID
              </button>
            </form>

            <!-- Toggle Reject Section -->
            <button type="button" class="btn btn-danger" style="flex:1;padding:12px 14px;font-weight:600;" onclick="toggleRejectPanel()">
              <i class="fa-solid fa-circle-xmark"></i> Reject ID...
            </button>
          </div>

          <!-- Reject Details Drawer -->
          <div id="kycRejectDrawer" style="display:none;margin-top:14px;padding:14px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:10px;">
            <form action="<?= h($buildUsersRedirect()) ?>" method="POST">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="reject_kyc">
              <input type="hidden" name="user_id" id="kycRejectUserId">

              <label class="form-label" style="font-size:0.82rem;color:#fca5a5;margin-bottom:6px;display:block;">
                Select Reason or Enter Custom Feedback:
              </label>

              <!-- Reason Presets -->
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
                <button type="button" class="btn btn-ghost btn-sm" style="font-size:0.75rem;padding:3px 8px;" onclick="setRejectReason('Uploaded ID photo is blurry, dark, or illegible.')">Blurry Photo</button>
                <button type="button" class="btn btn-ghost btn-sm" style="font-size:0.75rem;padding:3px 8px;" onclick="setRejectReason('Legal name on ID does not match registered account name.')">Name Mismatch</button>
                <button type="button" class="btn btn-ghost btn-sm" style="font-size:0.75rem;padding:3px 8px;" onclick="setRejectReason('The submitted ID or school registration is expired.')">Expired ID</button>
                <button type="button" class="btn btn-ghost btn-sm" style="font-size:0.75rem;padding:3px 8px;" onclick="setRejectReason('Document type is unsupported. Please submit a valid government or school ID.')">Invalid Type</button>
              </div>

              <textarea name="rejection_reason" id="kycRejectionReasonInput" class="form-control" rows="2" style="font-size:0.82rem;background:#0d1238;color:#fff;" placeholder="Explain why the document was rejected..."></textarea>

              <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
                <button type="button" class="btn btn-ghost btn-sm" onclick="toggleRejectPanel()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this ID verification and notify the user?');">
                  <i class="fa-solid fa-paper-plane"></i> Confirm Rejection
                </button>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
function generateSecureTempString(prefix = '') {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  let pass = prefix;
  const len = prefix ? 5 : 10;
  for (let i = 0; i < len; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return pass;
}

function openWalkinGuestModal() {
  document.getElementById('walkinInitialPass').value = generateSecureTempString('Guest@');
  document.getElementById('walkinGuestModal').style.display = 'flex';
}
function closeWalkinGuestModal() {
  document.getElementById('walkinGuestModal').style.display = 'none';
}
function generateWalkinPass() {
  document.getElementById('walkinInitialPass').value = generateSecureTempString('Guest@');
}

function openEditUserModal(data) {
  document.getElementById('editUserId').value = data.id;
  document.getElementById('editUserFn').value = data.first_name || '';
  document.getElementById('editUserMn').value = data.middle_name || '';
  document.getElementById('editUserLn').value = data.last_name || '';
  document.getElementById('editUserSf').value = data.suffix || '';
  document.getElementById('editUserEmail').value = data.email || '';
  document.getElementById('editUserPhone').value = data.phone || '';
  document.getElementById('editUserAddress').value = data.address || '';
  document.getElementById('editUserLocation').value = data.location || '';
  document.getElementById('editUserStatus').value = data.status || 'active';
  document.getElementById('editUserKyc').value = data.verification_status || 'unverified';
  document.getElementById('editUserModal').style.display = 'flex';
}
function closeEditUserModal() {
  document.getElementById('editUserModal').style.display = 'none';
}

function openResetModal(id, name, email, reqId = 0) {
  document.getElementById('modalUserId').value = id;
  document.getElementById('modalUserName').textContent = name;
  document.getElementById('modalUserEmail').textContent = email;
  document.getElementById('modalRequestId').value = reqId;
  document.getElementById('modalNewPassword').value = '';
  document.getElementById('resetPasswordModal').style.display = 'flex';
}
function closeResetModal() {
  document.getElementById('resetPasswordModal').style.display = 'none';
}
function generateRandomPass() {
  document.getElementById('modalNewPassword').value = generateSecureTempString();
}

function openKycReviewModal(data) {
  document.getElementById('kycApproveUserId').value = data.id;
  document.getElementById('kycRejectUserId').value = data.id;
  document.getElementById('kycModalGuestName').textContent = data.name;
  document.getElementById('kycModalEmail').textContent = data.email;
  document.getElementById('kycModalPhone').textContent = data.phone;
  document.getElementById('kycModalAddress').textContent = data.address;
  document.getElementById('kycModalLocation').textContent = data.location;
  document.getElementById('kycModalIdType').textContent = data.id_type;
  document.getElementById('kycModalIdNumber').textContent = data.id_number;
  document.getElementById('kycModalSubmittedAt').textContent = data.submitted_at || 'Not Available';
  
  const imgEl = document.getElementById('kycDocImg');
  const pdfNotice = document.getElementById('kycDocPdfNotice');
  const externalLink = document.getElementById('kycDocExternalLink');
  const pdfLink = document.getElementById('kycDocPdfLink');

  externalLink.href = data.id_document_url;
  pdfLink.href = data.id_document_url;

  if (data.id_document_raw && data.id_document_raw.toLowerCase().endsWith('.pdf')) {
    imgEl.style.display = 'none';
    pdfNotice.style.display = 'block';
  } else {
    pdfNotice.style.display = 'none';
    imgEl.src = data.id_document_url;
    imgEl.style.display = 'block';
  }

  const statusBadgeMap = {
    'verified': '<span class="badge" style="background:#22c55e;color:#fff;">Verified</span>',
    'pending': '<span class="badge" style="background:#eab308;color:#000;">Pending Review</span>',
    'rejected': '<span class="badge" style="background:#ef4444;color:#fff;">Rejected</span>',
    'unverified': '<span class="badge" style="background:#64748b;color:#fff;">Unverified</span>'
  };
  document.getElementById('kycModalStatusBadge').innerHTML = statusBadgeMap[data.status] || data.status;

  const rejBox = document.getElementById('kycModalRejectionBox');
  if (data.rejection_reason && data.rejection_reason.trim() !== '') {
    document.getElementById('kycModalRejectionReason').textContent = data.rejection_reason;
    rejBox.style.display = 'block';
  } else {
    rejBox.style.display = 'none';
  }

  document.getElementById('kycRejectDrawer').style.display = 'none';
  document.getElementById('kycReviewModal').style.display = 'flex';
}

function closeKycReviewModal() {
  document.getElementById('kycReviewModal').style.display = 'none';
}

function toggleRejectPanel() {
  const drawer = document.getElementById('kycRejectDrawer');
  drawer.style.display = drawer.style.display === 'none' ? 'block' : 'none';
  if (drawer.style.display === 'block') {
    document.getElementById('kycRejectionReasonInput').focus();
  }
}

function setRejectReason(reason) {
  document.getElementById('kycRejectionReasonInput').value = reason;
}
</script>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/admin.js')) ?>"></script>
</body>
</html>
