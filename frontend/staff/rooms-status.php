<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('staff');
releaseReadOnlySession();

$staffId = currentUserId();
$staff = dbRow("SELECT * FROM users WHERE id = ?", [$staffId]);

$rooms = dbRows("
    SELECT r.*, rt.name as type_name,
      (SELECT b.booking_code FROM bookings b WHERE b.room_id = r.id AND b.status = 'checked_in' LIMIT 1) as active_booking_code,
      (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.room_id = r.id AND b.status = 'checked_in' LIMIT 1) as current_guest,
      (SELECT b.booking_code FROM bookings b WHERE b.room_id = r.id AND b.status = 'confirmed' AND b.check_in = CURDATE() LIMIT 1) as arriving_today_code
    FROM rooms r 
    JOIN room_types rt ON r.room_type_id = rt.id 
    ORDER BY r.floor ASC, r.room_number ASC
");

// Update status (available / dirty / maintenance)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room_status'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $rId = (int)$_POST['room_id'];
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, ['available', 'dirty', 'maintenance'], true)) {
            dbExec("UPDATE rooms SET status = ? WHERE id = ? AND status != 'occupied'", [$newStatus, $rId]);
            auditLog('Staff Updated Room Status', 'Staff Module', "Room ID $rId status updated to $newStatus");
            setFlash('success', 'Room status has been updated.');
        }
    }
    header("Location: rooms-status.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Room Status — Staff Desk</title>
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
        <div class="topbar-title">Live Room Status Board</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Status Board for All Rooms</h1>
          <div class="page-header-sub">See which rooms are vacant, currently occupied, or under maintenance.</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));gap:16px;">
        <?php foreach ($rooms as $rm): ?>
          <div class="glass-card" style="padding:20px;border-left:4px solid <?= $rm['status'] === 'available' ? 'var(--green)' : ($rm['status'] === 'occupied' ? 'var(--red)' : 'var(--orange)') ?>;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
              <h3 style="color:#fff;font-size:1.1rem;font-weight:700;">Room #<?= h($rm['room_number']) ?></h3>
              <?= statusBadge($rm['status']) ?>
            </div>
            <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px;">
              <div><?= h($rm['name']) ?> (Floor <?= $rm['floor'] ?>)</div>
              <div><?= h($rm['type_name']) ?> · Max <?= $rm['capacity'] ?> Pax</div>
            </div>

            <?php if (!empty($rm['arriving_today_code']) && $rm['status'] !== 'occupied'): ?>
              <div style="background:rgba(201,168,76,0.14);border:1px solid rgba(201,168,76,0.35);padding:6px 10px;border-radius:6px;font-size:0.78rem;color:var(--gold);margin-bottom:10px;">
                <i class="fa-solid fa-bell"></i> Arrival Today: #<?= h($rm['arriving_today_code']) ?> — Prioritize turnaround
              </div>
            <?php endif; ?>

            <?php if ($rm['status'] === 'occupied'): ?>
              <div style="background:rgba(233,69,96,0.1);padding:8px 12px;border-radius:8px;font-size:0.8rem;color:#ff899b;margin-bottom:12px;">
                <i class="fa-solid fa-user"></i> Customer: <?= h($rm['current_guest'] ?: 'Checked-in') ?><br>
                <i class="fa-solid fa-ticket"></i> #<?= h($rm['active_booking_code']) ?>
              </div>
            <?php else: ?>
              <form action="" method="POST" style="margin-top:12px;">
                <?= csrfField() ?>
                <input type="hidden" name="room_id" value="<?= $rm['id'] ?>">
                <input type="hidden" name="update_room_status" value="1">
                <div style="display:flex;gap:6px;">
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

    </div>
  </main>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/staff.js"></script>
</body>
</html>
