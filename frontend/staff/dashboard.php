<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('staff');
releaseReadOnlySession();

// Periodic housekeeping: cancel stale unpaid bookings older than 24 hours
autoExpireStaleBookings(24);

$staffId = currentUserId();
$staff = dbRow("SELECT * FROM users WHERE id = ?", [$staffId]);

// Action: Accept / Decline pending transactions (Limited powers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_booking'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $bookingId = (int)$_POST['booking_id'];
        $action = $_POST['action_type']; // 'accept' or 'decline'

        $bk = dbRow("
            SELECT b.*, v.first_name, v.last_name, v.role 
            FROM bookings b 
            LEFT JOIN users v ON b.accepted_by = v.id 
            WHERE b.id = ?
        ", [$bookingId]);

        if ($bk) {
            $vName = !empty($bk['first_name']) ? trim($bk['first_name'] . ' ' . ($bk['last_name'] ?? '')) : 'Staff/Admin';
            $vRole = (($bk['role'] ?? '') === 'admin') ? 'Super Admin' : 'Frontdesk Staff';

            if ($bk['status'] === 'confirmed') {
                setFlash('info', "Already Confirmed: Booking #{$bk['booking_code']} was already approved by $vRole ($vName). Auto-synchronized!");
            } elseif ($action === 'accept') {
                $conflict = findConflictingBooking((int)$bk['room_id'], $bk['check_in'], $bk['check_out'], $bookingId);
                if ($conflict) {
                    setFlash('error', "Cannot confirm Booking #{$bk['booking_code']}: overlaps with Booking #{$conflict['booking_code']} (" . formatDate($conflict['check_in'], 'M d') . " - " . formatDate($conflict['check_out'], 'M d') . ").");
                } else {
                    dbExec("UPDATE bookings SET status = 'confirmed', accepted_by = ?, accepted_at = NOW(), cancelled_reason = NULL WHERE id = ?", [$staffId, $bookingId]);
                    auditLog('Staff Accepted Booking', 'Staff Module', "Booking #{$bk['booking_code']} approved by Staff ID $staffId");
                    setFlash('success', "Booking #{$bk['booking_code']} confirmed successfully.");
                }
            } elseif ($action === 'decline') {
                dbExec("UPDATE bookings SET status = 'rejected', cancelled_at = NOW(), cancelled_reason = 'Denied by Frontdesk Staff' WHERE id = ?", [$bookingId]);
                auditLog('Staff Rejected Booking', 'Staff Module', "Booking #{$bk['booking_code']} rejected by Staff ID $staffId");
                setFlash('warning', "Booking #{$bk['booking_code']} has been declined.");
            }
        }
    }
    header("Location: dashboard.php");
    exit;
}

// Action: Quick Payment Approval / Rejection (Delegated Frontdesk Power)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_payment'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $payId = (int)($_POST['payment_id'] ?? 0);
        $act = $_POST['action_type'] ?? ''; // 'verify' or 'reject'
        $res = processPaymentDecision($payId, $act, $staffId, 'Staff');
        setFlash($res['flash_type'], $res['message']);
    }
    header("Location: dashboard.php");
    exit;
}

// Stats
$pendingBookings = dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'pending'")['cnt'] ?? 0;
$pendingPayments = dbRow("SELECT COUNT(*) as cnt FROM payments WHERE status = 'pending'")['cnt'] ?? 0;
$todayCheckIns   = dbRow("SELECT COUNT(*) as cnt FROM bookings WHERE check_in = CURDATE() AND status = 'confirmed'")['cnt'] ?? 0;
$occupiedRooms   = dbRow("SELECT COUNT(*) as cnt FROM rooms WHERE status = 'occupied'")['cnt'] ?? 0;

// Pending Bookings queue
$pendingList = dbRows("
    SELECT b.*, u.first_name, u.last_name, u.phone, r.name as room_name 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.status = 'pending' 
    ORDER BY b.id ASC 
    LIMIT 10
");

// Pending Payments queue (Frontdesk Approval)
$pendingPaymentsList = dbRows("
    SELECT p.*, b.booking_code, u.first_name, u.last_name, u.phone, r.name as room_name, r.room_number 
    FROM payments p 
    JOIN bookings b ON p.booking_id = b.id 
    JOIN users u ON p.user_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    WHERE p.status = 'pending' 
    ORDER BY p.id ASC 
    LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard — Marvelous Hotel</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/staff.css">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
  <!-- Staff Sidebar -->
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('staff'); ?>
  <!-- Staff Main Area -->
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="staff-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Frontdesk Staff Operations</div>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-info"><i class="fa-solid fa-shield"></i> Staff Access Level</span>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="desktop-workstation-note">
        <i class="fa-solid fa-desktop"></i>
        <span><strong>Workstation Mode:</strong> Ang Staff Frontdesk Portal ay dinisenyo para sa Laptop at Desktop PC screens (Chrome, Edge, Firefox) para sa mabilisang arrivals, check-in, at payment verification.</span>
      </div>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Frontdesk Operations Desk</h1>
          <div class="page-header-sub">Confirm incoming bookings while the Admin is away.</div>
        </div>
      </div>

      <!-- Quick Metrics -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:28px;">
        <div class="stat-card">
          <div class="stat-icon red"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $pendingBookings ?></div>
            <div class="stat-label">Pending Bookings</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(234,179,8,0.15);color:#eab308;border:1px solid rgba(234,179,8,0.3);"><i class="fa-solid fa-receipt"></i></div>
          <div class="stat-info">
            <div class="stat-value" style="color:#eab308;"><?= $pendingPayments ?></div>
            <div class="stat-label">Payments Awaiting Verification</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fa-solid fa-person-walking-luggage"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $todayCheckIns ?></div>
            <div class="stat-label">Expected Check-ins Today</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon gold"><i class="fa-solid fa-key"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= $occupiedRooms ?></div>
            <div class="stat-label">Currently Occupied Rooms</div>
          </div>
        </div>
      </div>

      <!-- Marvi Staff AI Co-Pilot Shift Briefing -->
      <div class="glass-card" style="padding:22px;margin-bottom:28px;border:1px solid rgba(78,154,241,0.3);background:linear-gradient(135deg, rgba(8,13,36,0.95), rgba(16,26,60,0.9));">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:12px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;">
              <i class="fa-solid fa-robot"></i>
            </div>
            <div>
              <div style="font-weight:700;color:#fff;font-size:1.05rem;">Marvi AI — Frontdesk Operations Co-Pilot</div>
              <div style="font-size:0.75rem;color:var(--accent);">Automated Shift Operations &amp; Room Turnover Intelligence</div>
            </div>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" id="btnRefreshAiShift" onclick="loadStaffAiBriefing(true)" style="font-size:0.78rem;">
            <i class="fa-solid fa-arrows-rotate"></i> Refresh Shift Briefing
          </button>
        </div>

        <div id="staffAiLoading" style="padding:20px;text-align:center;color:var(--text-muted);font-size:0.88rem;">
          <i class="fa-solid fa-circle-notch fa-spin" style="color:var(--accent);margin-right:8px;"></i> Pinoproseso ang live shift briefing mula sa AI engine...
        </div>

        <div id="staffAiContent" style="display:none;font-size:0.88rem;line-height:1.7;color:#e2e8f0;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:10px;padding:16px;">
        </div>
      </div>

      <!-- Actionable Pending Payments (Frontdesk Delegation when Admin is away) -->
      <div class="card" style="margin-bottom:28px;border:1px solid rgba(234,179,8,0.3);">
        <div class="card-header" style="background:rgba(234,179,8,0.06);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
          <h2 class="card-title" style="margin:0;"><i class="fa-solid fa-receipt" style="color:#eab308;margin-right:8px;"></i> Payments Awaiting Frontdesk Verification</h2>
          <a href="payments.php" class="btn btn-ghost btn-sm" style="color:var(--gold);">View All Payments &rarr;</a>
        </div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Customer &amp; Contact</th>
                  <th>Room</th>
                  <th>Channel</th>
                  <th>Amount</th>
                  <th>Proof / Ref #</th>
                  <th>Date &amp; Time Paid</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pendingPaymentsList)): ?>
                  <tr>
                    <td colspan="8" class="text-center" style="padding:28px;color:var(--text-muted);">
                      <i class="fa-solid fa-circle-check" style="color:#4ade80;margin-right:6px;"></i> No pending payment receipts right now. All settled!
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($pendingPaymentsList as $pay): ?>
                    <tr>
                      <td><strong>#<?= h($pay['booking_code']) ?></strong></td>
                      <td>
                        <strong><?= h(formatFullName($pay)) ?></strong><br>
                        <small style="color:var(--text-muted);"><i class="fa-solid fa-phone"></i> <?= h($pay['phone'] ?: 'N/A') ?></small>
                      </td>
                      <td>Room <?= h($pay['room_number']) ?></td>
                      <td><span style="text-transform:uppercase;font-weight:600;color:#60a5fa;"><?= h($pay['method']) ?></span></td>
                      <td style="color:var(--gold);font-weight:700;"><?= formatCurrency($pay['amount']) ?></td>
                      <td>
                        <div><code><?= h($pay['reference_number'] ?: 'No Ref') ?></code></div>
                        <?php if ($pay['proof_image']): ?>
                          <a href="<?= getUploadUrl($pay['proof_image']) ?>" target="_blank" style="color:var(--accent);font-size:0.75rem;"><i class="fa-solid fa-image"></i> View Receipt</a>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="font-weight:600;color:#fff;"><?= formatDate($pay['created_at'], 'M d, Y') ?></div>
                        <div style="font-size:0.75rem;color:var(--gold);margin-top:2px;">
                          <i class="fa-solid fa-clock"></i> <?= formatDate($pay['created_at'], 'h:i:s A') ?>
                        </div>
                      </td>
                      <td>
                        <form action="" method="POST" style="display:inline-flex;gap:6px;">
                          <?= csrfField() ?>
                          <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                          <input type="hidden" name="action_payment" value="1">
                          
                          <button type="submit" name="action_type" value="verify" class="btn btn-success btn-sm" onclick="return confirm('Confirm and approve this payment receipt?')">
                            <i class="fa-solid fa-check"></i> Approve
                          </button>
                          <button type="submit" name="action_type" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Decline this payment receipt?')">
                            <i class="fa-solid fa-xmark"></i> Decline
                          </button>
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

      <!-- Actionable Pending Transactions -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title"><i class="fa-solid fa-bell" style="color:var(--gold);margin-right:8px;"></i> New Bookings Awaiting Approval</h2>
        </div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Customer</th>
                  <th>Contact</th>
                  <th>Room</th>
                  <th>Dates</th>
                  <th>Amount</th>
                  <th>Action (Staff Limit)</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pendingList)): ?>
                  <tr>
                    <td colspan="7" class="text-center" style="padding:30px;color:var(--text-muted);">
                      No pending bookings right now. Everything has already been processed!
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($pendingList as $p): ?>
                    <tr>
                      <td><strong><?= h($p['booking_code']) ?></strong></td>
                      <td><?= h($p['first_name'] . ' ' . $p['last_name']) ?></td>
                      <td><?= h($p['phone'] ?: 'N/A') ?></td>
                      <td><?= h($p['room_name']) ?></td>
                      <td><?= formatDate($p['check_in']) ?> - <?= formatDate($p['check_out']) ?></td>
                      <td style="color:var(--gold);font-weight:600;"><?= formatCurrency($p['total_amount']) ?></td>
                      <td>
                        <form action="" method="POST" style="display:inline-flex;gap:6px;">
                          <?= csrfField() ?>
                          <input type="hidden" name="booking_id" value="<?= $p['id'] ?>">
                          <input type="hidden" name="action_booking" value="1">
                          
                          <button type="submit" name="action_type" value="accept" class="btn btn-success btn-sm" onclick="return confirm('Confirm this booking?')">
                            <i class="fa-solid fa-check"></i> Accept
                          </button>
                          <button type="submit" name="action_type" value="decline" class="btn btn-danger btn-sm" onclick="return confirm('Decline this booking?')">
                            <i class="fa-solid fa-xmark"></i> Decline
                          </button>
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
function loadStaffAiBriefing(forceRefresh = false) {
  const loading = document.getElementById('staffAiLoading');
  const content = document.getElementById('staffAiContent');
  const btn = document.getElementById('btnRefreshAiShift');

  loading.style.display = 'block';
  content.style.display = 'none';
  if (btn) btn.disabled = true;

  fetch('../ai/staff-assistant.php' + (forceRefresh ? '?refresh=1' : ''))
    .then(res => res.json())
    .then(data => {
      loading.style.display = 'none';
      if (data.success && data.briefing) {
        content.innerHTML = data.briefing;
        content.style.display = 'block';
      } else {
        content.innerHTML = '<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Hindi ma-load ang briefing sa ngayon.</span>';
        content.style.display = 'block';
      }
    })
    .catch(() => {
      loading.style.display = 'none';
      content.innerHTML = '<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> May error sa pag-konekta sa AI server.</span>';
      content.style.display = 'block';
    })
    .finally(() => {
      if (btn) btn.disabled = false;
    });
}

document.addEventListener('DOMContentLoaded', () => {
  loadStaffAiBriefing();
});
</script>
</body>
</html>
