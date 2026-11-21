<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('staff');
releaseReadOnlySession();

$staffId = currentUserId();

// Handle Check-in / Check-out actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_guest'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $bookingId = (int)$_POST['booking_id'];
        $act = $_POST['action_type'] ?? ''; // 'check_in' or 'check_out'
        $overrideUnpaid = !empty($_POST['confirm_unpaid_checkin']);
        $bk = dbRow("SELECT b.*, u.verification_status, u.profile_verified FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?", [$bookingId]);

        if (!$bk) {
            setFlash('error', 'Booking record not found.');
        } elseif ($act === 'check_in') {
            if ($bk['status'] !== 'confirmed') {
                setFlash('warning', "Booking #{$bk['booking_code']} cannot be checked in from status '" . strtoupper($bk['status']) . "'.");
            } else {
                $verifiedPay = dbRow("SELECT id FROM payments WHERE booking_id = ? AND status = 'verified' LIMIT 1", [$bookingId]);
                if (!$verifiedPay && !$overrideUnpaid) {
                    setFlash('warning', "Payment for Booking #{$bk['booking_code']} is not yet verified. Verify payment first or check the desk override box to proceed.");
                } else {
                    $kycState = $bk['verification_status'] ?? ($bk['profile_verified'] ? 'verified' : 'unverified');
                    dbExec("UPDATE bookings SET status = 'checked_in', checked_in_at = NOW() WHERE id = ? AND status = 'confirmed'", [$bookingId]);
                    dbExec("UPDATE rooms SET status = 'occupied' WHERE id = ?", [$bk['room_id']]);
                    $note = $verifiedPay ? 'Verified payment' : 'Frontdesk unpaid override';
                    auditLog('Customer Checked In', 'Staff Module', "Booking #{$bk['booking_code']} checked in by Staff #$staffId ({$note}; KYC: {$kycState})");
                    setFlash('success', "Customer for Booking #{$bk['booking_code']} checked in successfully.");
                }
            }
        } elseif ($act === 'check_out') {
            if ($bk['status'] !== 'checked_in') {
                setFlash('warning', "Booking #{$bk['booking_code']} is not currently checked in.");
            } else {
                dbExec("UPDATE bookings SET status = 'checked_out', checked_out_at = NOW() WHERE id = ? AND status = 'checked_in'", [$bookingId]);
                dbExec("UPDATE rooms SET status = 'dirty' WHERE id = ?", [$bk['room_id']]);
                auditLog('Customer Checked Out', 'Staff Module', "Booking #{$bk['booking_code']} marked checked out by Staff #$staffId. Room set to Dirty (Needs Cleaning).");
                setFlash('info', 'Customer checked out successfully. Room set to Housekeeping (Needs Cleaning).');
            }
        }
    }
    header("Location: check-in.php");
    exit;
}

$activeStays = dbRows("
    SELECT b.*, u.first_name, u.middle_name, u.last_name, u.suffix, u.phone, u.profile_verified, u.verification_status, u.id_type,
           r.name as room_name, r.room_number,
           (SELECT p.status FROM payments p WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) as payment_status,
           (SELECT p.created_at FROM payments p WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) as payment_created_at,
           (SELECT p.verified_at FROM payments p WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) as payment_verified_at,
           (SELECT v.role FROM payments p LEFT JOIN users v ON p.verified_by = v.id WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) as payment_verifier_role,
           (SELECT CONCAT(v.first_name, ' ', v.last_name) FROM payments p LEFT JOIN users v ON p.verified_by = v.id WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) as payment_verifier_name,
           COALESCE((SELECT p.method FROM payments p WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1), b.payment_method, 'maya') as payment_method
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.status IN ('confirmed', 'checked_in') 
    ORDER BY b.check_in ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Check-in &amp; Check-out — Staff Desk</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/staff.css">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('staff'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="staff-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Arrivals and Departures</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
          <h1 class="page-header-title">Frontdesk Check-in &amp; Check-out</h1>
          <div class="page-header-sub">Manage arriving and departing customers with verified identity protocol.</div>
        </div>
        <div style="min-width:260px;">
          <input type="text" id="checkInSearchInput" class="form-control" placeholder="Search by code, customer, or room..." onkeyup="filterCheckInTable()" style="background:rgba(255,255,255,0.05);font-size:0.85rem;">
        </div>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table" id="checkInTable">
              <thead>
                <tr>
                  <th class="col-nowrap">Booking ID</th>
                  <th>Customer / Contact</th>
                  <th class="col-nowrap">ID / KYC Status</th>
                  <th class="col-nowrap">Room</th>
                  <th class="col-nowrap">Stay Dates</th>
                  <th class="col-nowrap">Payment &amp; Time</th>
                  <th class="col-nowrap">Status</th>
                  <th class="col-nowrap">Frontdesk Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($activeStays)): ?>
                  <tr><td colspan="8" class="text-center" style="padding:30px;color:var(--text-muted);">No active confirmed or checked-in customers right now.</td></tr>
                <?php else: ?>
                  <?php foreach ($activeStays as $st): 
                    $kycStatus = $st['verification_status'] ?? ($st['profile_verified'] ? 'verified' : 'unverified');
                  ?>
                    <tr>
                      <td class="col-nowrap"><strong>#<?= h($st['booking_code']) ?></strong></td>
                      <td>
                        <strong><?= h(formatFullName($st)) ?></strong>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                          <i class="fa-solid fa-phone"></i> <?= h($st['phone'] ?: 'N/A') ?>
                        </div>
                      </td>
                      <td class="col-nowrap">
                        <?php if ($kycStatus === 'verified'): ?>
                          <span class="badge" style="background:#22c55e;color:#fff;font-size:0.75rem;"><i class="fa-solid fa-shield-check"></i> Verified ID</span>
                          <?php if (!empty($st['id_type'])): ?>
                            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;"><?= h($st['id_type']) ?></div>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="badge" style="background:#eab308;color:#000;font-size:0.75rem;"><i class="fa-solid fa-triangle-exclamation"></i> Check Physical ID</span>
                          <div style="font-size:0.72rem;color:#fde047;margin-top:2px;">Request card upon arrival</div>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap">Room <?= h($st['room_number']) ?> (<?= h($st['room_name']) ?>)</td>
                      <td class="col-nowrap" style="white-space:nowrap;"><?= formatDate($st['check_in']) ?> &rarr; <?= formatDate($st['check_out']) ?></td>
                      <td class="col-nowrap">
                        <?php if ($st['payment_status'] === 'verified'): ?>
                          <span class="badge" style="background:#22c55e;color:#fff;font-size:0.75rem;"><i class="fa-solid fa-circle-check"></i> Paid (<?= strtoupper($st['payment_method'] ?? 'ONLINE') ?>)</span>
                          <?php if (!empty($st['payment_verifier_role'])): ?>
                            <div style="font-size:0.72rem;margin-top:4px;">
                              <?php if ($st['payment_verifier_role'] === 'admin'): ?>
                                <span style="color:#fde047;font-weight:600;"><i class="fa-solid fa-crown" style="color:var(--gold);"></i> Admin Verified</span>
                              <?php else: ?>
                                <span style="color:#93c5fd;font-weight:600;"><i class="fa-solid fa-user-shield"></i> Staff Verified</span>
                              <?php endif; ?>
                              <?php if (!empty($st['payment_verifier_name'])): ?>
                                <span style="color:var(--text-muted);">(<?= h($st['payment_verifier_name']) ?>)</span>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;">
                            <i class="fa-solid fa-clock"></i> <?= formatDate($st['payment_verified_at'] ?? $st['payment_created_at'], 'M d, Y h:i A') ?>
                          </div>
                        <?php elseif ($st['payment_status'] === 'pending'): ?>
                          <span class="badge" style="background:#eab308;color:#000;font-size:0.75rem;"><i class="fa-solid fa-hourglass-half"></i> Pending Verification</span>
                          <div style="font-size:0.72rem;color:#fde047;margin-top:3px;">
                            <i class="fa-solid fa-clock"></i> <?= formatDate($st['payment_created_at'], 'M d, Y h:i A') ?>
                          </div>
                        <?php else: ?>
                          <span class="badge" style="background:rgba(239,68,68,0.2);color:#ef4444;font-size:0.75rem;"><i class="fa-solid fa-circle-exclamation"></i> Unpaid</span>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap"><?= statusBadge($st['status']) ?></td>
                      <td class="col-nowrap">
                        <form action="" method="POST" style="display:inline-flex;flex-direction:column;gap:6px;align-items:flex-start;">
                          <?= csrfField() ?>
                          <input type="hidden" name="booking_id" value="<?= $st['id'] ?>">
                          <input type="hidden" name="action_guest" value="1">
                          <?php if ($st['status'] === 'confirmed'): ?>
                            <?php if ($st['payment_status'] !== 'verified'): ?>
                              <label style="font-size:0.72rem;color:#fde047;display:flex;align-items:center;gap:4px;cursor:pointer;">
                                <input type="checkbox" name="confirm_unpaid_checkin" value="1"> Desk Override (Unpaid)
                              </label>
                            <?php endif; ?>
                            <button type="submit" name="action_type" value="check_in" class="btn btn-primary btn-sm"><i class="fa-solid fa-key"></i> Check In</button>
                          <?php elseif ($st['status'] === 'checked_in'): ?>
                            <button type="submit" name="action_type" value="check_out" class="btn btn-warning btn-sm"><i class="fa-solid fa-door-closed"></i> Check Out</button>
                          <?php endif; ?>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/staff.js"></script>
<script>
function filterCheckInTable() {
  const query = document.getElementById('checkInSearchInput').value.toLowerCase();
  const rows = document.querySelectorAll('#checkInTable tbody tr');
  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
}
</script>
</body>
</html>
