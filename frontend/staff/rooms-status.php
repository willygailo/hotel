<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('staff');
releaseReadOnlySession();

// Auto-release any checked-in/past bookings whose scheduled check-out date & time have elapsed
throttleStaleBookingSweep(24, 15);
$configuredCheckOutTime = getNormalizedCheckOutTime();
$formattedCheckOutTime  = date('g:i A', strtotime('2000-01-01 ' . $configuredCheckOutTime));

$staffId = currentUserId();

// Update status (available / dirty / maintenance)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room_status'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $rId = (int)($_POST['room_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $roomRow = dbRow("SELECT id, room_number, status FROM rooms WHERE id = ?", [$rId]);

        if (!$roomRow) {
            setFlash('error', 'Room record not found.');
        } elseif (!in_array($newStatus, ['available', 'dirty', 'maintenance'], true)) {
            setFlash('error', 'Invalid room status selected.');
        } else {
            // Check bookings table directly for active checked-in occupants (covers stale room flags & dorms)
            $activeOccupant = dbRow(
                "SELECT booking_code FROM bookings WHERE room_id = ? AND status = 'checked_in' LIMIT 1",
                [$rId]
            );
            if ($activeOccupant) {
                setFlash('error', "Cannot change Room #{$roomRow['room_number']} to '" . strtoupper($newStatus) . "' while Booking #{$activeOccupant['booking_code']} is currently Checked In. Check out the guest first.");
            } else {
                dbExec("UPDATE rooms SET status = ? WHERE id = ?", [$newStatus, $rId]);
                auditLog('Staff Updated Room Status', 'Staff Module', "Room #{$roomRow['room_number']} (ID $rId) changed from {$roomRow['status']} to $newStatus by Staff #$staffId");
                setFlash('success', "Room #{$roomRow['room_number']} status updated to " . strtoupper($newStatus) . '.');
            }
        }
    }
    header("Location: rooms-status.php");
    exit;
}

$rooms = dbRows("
    SELECT r.*, rt.name as type_name
    FROM rooms r
    JOIN room_types rt ON r.room_type_id = rt.id
    ORDER BY r.floor ASC, r.room_number ASC
");

// Batch load all currently checked-in occupants per room (supports multi-guest dorms without N+1 queries)
$occupantRows = dbRows("
    SELECT b.room_id, b.booking_code, b.check_in, b.check_out, u.first_name, u.last_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.status = 'checked_in'
    ORDER BY b.id ASC
");
$occupantsByRoom = [];
foreach ($occupantRows as $occ) {
    $rid = (int)$occ['room_id'];
    $occupantsByRoom[$rid][] = $occ;
}

// Batch load confirmed arrivals scheduled for today (or overdue check-in) per room
$arrivalRows = dbRows("
    SELECT room_id, booking_code, check_in
    FROM bookings
    WHERE status = 'confirmed' AND check_in <= CURDATE()
    ORDER BY check_in ASC
");
$arrivalsByRoom = [];
foreach ($arrivalRows as $arr) {
    $rid = (int)$arr['room_id'];
    $arrivalsByRoom[$rid][] = $arr;
}

$statusCounts = [
    'all'         => count($rooms),
    'available'   => 0,
    'occupied'    => 0,
    'dirty'       => 0,
    'maintenance' => 0,
];
foreach ($rooms as &$rm) {
    $rid = (int)$rm['id'];
    $rm['occupants'] = $occupantsByRoom[$rid] ?? [];
    $rm['arrivals']  = $arrivalsByRoom[$rid] ?? [];
    // Treat room as occupied if bookings table has checked_in guests
    $rm['effective_status'] = !empty($rm['occupants']) ? 'occupied' : $rm['status'];
    $eff = $rm['effective_status'];
    if (isset($statusCounts[$eff])) {
        $statusCounts[$eff]++;
    }
}
unset($rm);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Room Status — Staff Desk</title>
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
        <div class="topbar-title">Live Room Status Board</div>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-info"><i class="fa-solid fa-bed"></i> <?= $statusCounts['all'] ?> Total Rooms</span>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Status Board for All Rooms</h1>
          <div class="page-header-sub">See which rooms are available, occupied, waiting for housekeeping, or under maintenance.</div>
        </div>
      </div>

      <div class="staff-toolbar">
        <div class="staff-filter-pills">
          <button type="button" class="staff-filter-pill active" data-status-filter="all">
            All <span class="pill-count"><?= $statusCounts['all'] ?></span>
          </button>
          <button type="button" class="staff-filter-pill" data-status-filter="available">
            Available <span class="pill-count"><?= $statusCounts['available'] ?></span>
          </button>
          <button type="button" class="staff-filter-pill" data-status-filter="occupied">
            Occupied <span class="pill-count"><?= $statusCounts['occupied'] ?></span>
          </button>
          <button type="button" class="staff-filter-pill" data-status-filter="dirty">
            Needs Cleaning <span class="pill-count"><?= $statusCounts['dirty'] ?></span>
          </button>
          <button type="button" class="staff-filter-pill" data-status-filter="maintenance">
            Maintenance <span class="pill-count"><?= $statusCounts['maintenance'] ?></span>
          </button>
        </div>

        <div style="min-width:250px;">
          <input type="text" id="roomSearchInput" class="form-control" placeholder="Filter by room #, name, guest..." style="background:rgba(255,255,255,0.05);font-size:0.85rem;">
        </div>
      </div>

      <div class="room-status-grid">
        <?php foreach ($rooms as $rm):
          $effStatus = $rm['effective_status'];
          $occupants = $rm['occupants'];
          $arrivals  = $rm['arrivals'];
        ?>
          <div class="room-status-card <?= h($effStatus) ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
              <h3 class="room-status-num">Room #<?= h($rm['room_number']) ?></h3>
              <?= statusBadge($effStatus) ?>
            </div>
            <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px;">
              <div><?= h($rm['name']) ?> (Floor <?= (int)$rm['floor'] ?>)</div>
              <div><?= h($rm['type_name']) ?> &middot; Max <?= (int)$rm['capacity'] ?> Pax<?= !empty($rm['is_dorm']) ? ' (Dorm)' : '' ?></div>
            </div>

            <?php if (!empty($arrivals) && $effStatus !== 'occupied'): ?>
              <div style="background:rgba(201,168,76,0.14);border:1px solid rgba(201,168,76,0.35);padding:6px 10px;border-radius:6px;font-size:0.78rem;color:var(--gold);margin-bottom:10px;">
                <i class="fa-solid fa-bell"></i>
                Arrival Due: #<?= h($arrivals[0]['booking_code']) ?><?= count($arrivals) > 1 ? ' (+' . (count($arrivals) - 1) . ' more)' : '' ?> — Prioritize turnaround
              </div>
            <?php endif; ?>

            <?php if ($effStatus === 'occupied'): ?>
              <div style="background:rgba(233,69,96,0.1);border:1px solid rgba(233,69,96,0.22);padding:8px 12px;border-radius:8px;font-size:0.8rem;color:#fda4af;margin-bottom:8px;">
                <?php if (empty($occupants)): ?>
                  <div><i class="fa-solid fa-user"></i> Occupied (No linked active booking)</div>
                <?php else: ?>
                  <?php if (count($occupants) > 1): ?>
                    <div style="font-weight:700;color:#fff;margin-bottom:4px;"><i class="fa-solid fa-users"></i> <?= count($occupants) ?> Dorm Guests Checked In:</div>
                  <?php endif; ?>
                  <?php foreach ($occupants as $occ): ?>
                    <div style="margin-bottom:4px;">
                      <div>
                        <i class="fa-solid fa-user"></i> <?= h(trim(($occ['first_name'] ?? '') . ' ' . ($occ['last_name'] ?? '')) ?: 'Checked-in') ?>
                        <span style="color:#cbd5e1;">(#<?= h($occ['booking_code']) ?>)</span>
                      </div>
                      <?php if (!empty($occ['check_out'])): ?>
                        <div style="font-size:0.72rem;color:#cbd5e1;margin-top:2px;">
                          <i class="fa-regular fa-clock"></i> Until <?= formatDate($occ['check_out'], 'M d, Y') ?> &middot; <?= h($formattedCheckOutTime) ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                  <div style="margin-top:6px;">
                    <a href="check-in.php?tab=inhouse" style="font-size:0.74rem;color:var(--gold);text-decoration:underline;">
                      <i class="fa-solid fa-door-closed"></i> Manage Check-out
                    </a>
                  </div>
                <?php endif; ?>
              </div>
              <?php if (empty($occupants)): ?>
                <form action="rooms-status.php" method="POST" style="margin-top:8px;">
                  <?= csrfField() ?>
                  <input type="hidden" name="room_id" value="<?= (int)$rm['id'] ?>">
                  <input type="hidden" name="update_room_status" value="1">
                  <div class="room-status-actions">
                    <select name="status" class="form-control" style="font-size:0.8rem;padding:6px 10px;">
                      <option value="dirty">Needs Cleaning (Dirty)</option>
                      <option value="available">Available (Clean)</option>
                      <option value="maintenance">Under Maintenance</option>
                    </select>
                    <button type="submit" class="btn btn-ghost btn-sm">Reset</button>
                  </div>
                </form>
              <?php endif; ?>
            <?php else: ?>
              <form action="rooms-status.php" method="POST" style="margin-top:12px;">
                <?= csrfField() ?>
                <input type="hidden" name="room_id" value="<?= (int)$rm['id'] ?>">
                <input type="hidden" name="update_room_status" value="1">
                <div class="room-status-actions">
                  <select name="status" class="form-control" style="font-size:0.8rem;padding:6px 10px;">
                    <option value="available" <?= $rm['status'] === 'available' ? 'selected' : '' ?>>Available (Clean)</option>
                    <option value="dirty" <?= $rm['status'] === 'dirty' ? 'selected' : '' ?>>Needs Cleaning (Dirty)</option>
                    <option value="maintenance" <?= $rm['status'] === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                  </select>
                  <button type="submit" class="btn btn-ghost btn-sm">Update</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div id="roomsEmptyNotice" style="display:none;text-align:center;padding:36px;color:var(--text-muted);" class="card">
        <i class="fa-solid fa-magnifying-glass" style="font-size:1.5rem;margin-bottom:8px;display:block;color:var(--accent);"></i>
        No rooms match the current filter or search term.
      </div>

    </div>
  </main>
</div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/staff.js')) ?>"></script>
</body>
</html>
