<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('admin');
releaseReadOnlySession();

// Add Room handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $roomNum = trim($_POST['room_number'] ?? '');
        $typeId  = (int)($_POST['room_type_id'] ?? 1);
        $name    = trim($_POST['name'] ?? '');
        $floor   = (int)($_POST['floor'] ?? 1);
        $cap     = max(1, (int)($_POST['capacity'] ?? 1));
        $price   = max(0, (float)($_POST['price_per_night'] ?? 0));
        $desc    = trim($_POST['description'] ?? '');

        $dupRoom = dbRow("SELECT id FROM rooms WHERE room_number = ? LIMIT 1", [$roomNum]);
        if ($roomNum === '' || $name === '') {
            setFlash('error', 'Room number and room name are required.');
            header("Location: rooms.php");
            exit;
        } elseif ($dupRoom) {
            setFlash('error', "Room number '{$roomNum}' already exists. Please use a unique room number.");
            header("Location: rooms.php");
            exit;
        }

        // Handle thumbnail upload
        $thumbnail = 'default-room.jpg';
        if (!empty($_FILES['thumbnail']['name'])) {
            $upErr = null;
            $uploaded = uploadFile($_FILES['thumbnail'], 'rooms', ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE, $upErr);
            if ($uploaded) {
                $thumbnail = $uploaded;
            } else {
                setFlash('error', $upErr ?: 'Invalid image type or the file size exceeds 15MB.');
                header("Location: rooms.php");
                exit;
            }
        }

        dbInsert(
            "INSERT INTO rooms (room_number, room_type_id, name, floor, capacity, price_per_night, description, thumbnail, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available')",
            [$roomNum, $typeId, $name, $floor, $cap, $price, $desc, $thumbnail]
        );

        auditLog('Added Room', 'Admin Module', "Added Room $roomNum - $name");
        setFlash('success', 'New room added successfully!');
        header("Location: rooms.php");
        exit;
    }
}

// Edit Room handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_room'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $roomId  = (int)($_POST['room_id'] ?? 0);
        $roomNum = trim($_POST['room_number'] ?? '');
        $typeId  = (int)($_POST['room_type_id'] ?? 1);
        $name    = trim($_POST['name'] ?? '');
        $floor   = (int)($_POST['floor'] ?? 1);
        $cap     = max(1, (int)($_POST['capacity'] ?? 1));
        $price   = max(0, (float)($_POST['price_per_night'] ?? 0));
        $status  = $_POST['status'] ?? 'available';
        $desc    = trim($_POST['description'] ?? '');

        $existing = dbRow("SELECT * FROM rooms WHERE id = ?", [$roomId]);
        $dupRoom  = ($roomNum !== '') ? dbRow("SELECT id FROM rooms WHERE room_number = ? AND id != ? LIMIT 1", [$roomNum, $roomId]) : null;
        if (!$existing) {
            setFlash('error', 'Room not found.');
        } elseif ($roomNum === '' || $name === '') {
            setFlash('error', 'Room number and room name are required.');
        } elseif ($dupRoom) {
            setFlash('error', "Room number '{$roomNum}' is already assigned to another room.");
        } else {
            if (!in_array($status, ['available', 'occupied', 'dirty', 'maintenance', 'reserved'], true)) {
                $status = $existing['status'];
            }
            $thumbnail = $existing['thumbnail'];
            if (!empty($_FILES['thumbnail']['name'])) {
                $uploaded = uploadFile($_FILES['thumbnail'], 'rooms', ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE, $upErr);
                if ($uploaded) {
                    if (!empty($thumbnail) && strpos($thumbnail, '/') !== false) {
                        deleteUpload($thumbnail);
                    }
                    $thumbnail = $uploaded;
                } else {
                    setFlash('error', $upErr ?: 'Invalid image type or file size exceeds limit.');
                    header("Location: rooms.php");
                    exit;
                }
            }

            dbExec(
                "UPDATE rooms SET room_number = ?, room_type_id = ?, name = ?, floor = ?, capacity = ?, price_per_night = ?, status = ?, description = ?, thumbnail = ? WHERE id = ?",
                [$roomNum, $typeId, $name, $floor, $cap, $price, $status, $desc, $thumbnail, $roomId]
            );
            auditLog('Updated Room', 'Admin Module', "Updated Room #$roomNum ($name), Price: ₱" . number_format($price, 2) . ", Status: $status");
            setFlash('success', "Room #$roomNum updated successfully.");
        }
    }
    header("Location: rooms.php");
    exit;
}

// Delete Room handler — POST + CSRF-protected + FK booking guard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_room'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $delId   = (int)$_POST['room_id'];
        $hasBookings = (int)(dbRow("SELECT COUNT(*) AS cnt FROM bookings WHERE room_id = ?", [$delId])['cnt'] ?? 0);
        if ($hasBookings > 0) {
            setFlash('warning', "Cannot delete this room because it has {$hasBookings} booking record(s) in history. Set its status to Maintenance instead.");
        } else {
            $delRoom = dbRow("SELECT thumbnail FROM rooms WHERE id = ?", [$delId]);
            dbExec("DELETE FROM rooms WHERE id = ?", [$delId]);
            if ($delRoom && strpos($delRoom['thumbnail'] ?? '', '/') !== false) {
                deleteUpload($delRoom['thumbnail']);
            }
            auditLog('Deleted Room', 'Admin Module', "Deleted Room ID $delId");
            setFlash('warning', 'Room deleted.');
        }
    }
    header("Location: rooms.php");
    exit;
}

$rooms = dbRows("SELECT r.*, rt.name as type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id ORDER BY r.id DESC");
$roomTypes = dbRows("SELECT * FROM room_types");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Rooms — Admin Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body style="background:var(--navy);">

<div class="dashboard-layout">
<?php require_once __DIR__ . '/../components/sidebar.php';
  renderSidebar('admin'); ?>
  <main class="dashboard-main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="admin-mobile-toggle" data-sidebar-toggle><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Room and Dormitory Management</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Room List</h1>
          <div class="page-header-sub">Add, edit, or delete hotel inventory.</div>
        </div>
      </div>

      <!-- Add Room Accordion / Form -->
      <div class="glass-card" style="padding:24px;margin-bottom:28px;">
        <h3 style="font-family:var(--font-serif);font-size:1.15rem;color:var(--gold);margin-bottom:14px;"><i class="fa-solid fa-plus-circle"></i> Add New Room</h3>
        <form action="" method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;">
          <?= csrfField() ?>
          <input type="hidden" name="add_room" value="1">
          <div>
            <label class="form-label">Room Number</label>
            <input type="text" name="room_number" class="form-control" placeholder="e.g., 105" required>
          </div>
          <div>
            <label class="form-label">Room Type</label>
            <select name="room_type_id" class="form-control" required>
              <?php foreach ($roomTypes as $tp): ?>
                <option value="<?= $tp['id'] ?>"><?= h($tp['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g., Deluxe Suite 105" required>
          </div>
          <div>
            <label class="form-label">Floor</label>
            <input type="number" name="floor" class="form-control" value="1" required>
          </div>
          <div>
            <label class="form-label">Capacity (Pax)</label>
            <input type="number" name="capacity" class="form-control" value="2" required>
          </div>
          <div>
            <label class="form-label">Price / Night (₱)</label>
            <input type="number" step="0.01" name="price_per_night" class="form-control" placeholder="1500.00" required>
          </div>
          <div>
            <label class="form-label">Thumbnail Image</label>
            <input type="file" name="thumbnail" class="form-control" accept="image/*">
            <small style="color:var(--text-muted);font-size:0.75rem;">JPG, PNG, WebP (Max 15MB)</small>
          </div>
          <div style="grid-column:1/-1;">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" style="min-height:60px;" placeholder="Describe the room, amenities, and view..."></textarea>
          </div>
          <button type="submit" class="btn btn-gold" style="grid-column:1/-1;width:fit-content;"><i class="fa-solid fa-save"></i> Save Room</button>
        </form>
      </div>

      <!-- Rooms Inventory Table -->
      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th class="col-nowrap">No.</th>
                  <th>Name</th>
                  <th class="col-nowrap">Type</th>
                  <th class="col-nowrap">Floor</th>
                  <th class="col-nowrap">Capacity</th>
                  <th class="col-amount">Price / Night</th>
                  <th class="col-nowrap">Status</th>
                  <th class="col-nowrap">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rooms as $r): ?>
                  <tr>
                    <td class="col-nowrap"><strong>#<?= h($r['room_number']) ?></strong></td>
                    <td><?= h($r['name']) ?></td>
                    <td class="col-nowrap"><span class="badge badge-gold"><?= h($r['type_name']) ?></span></td>
                    <td class="col-nowrap">Floor <?= $r['floor'] ?></td>
                    <td class="col-nowrap"><?= $r['capacity'] ?> pax</td>
                    <td class="col-amount" style="color:var(--gold);font-weight:700;"><?= formatCurrency($r['price_per_night']) ?></td>
                    <td class="col-nowrap"><?= statusBadge($r['status']) ?></td>
                    <td class="col-nowrap" style="display:flex;gap:6px;">
                      <button type="button"
                              class="btn btn-ghost btn-sm"
                              onclick="openEditRoomModal(this)"
                              data-id="<?= (int)$r['id'] ?>"
                              data-number="<?= h($r['room_number']) ?>"
                              data-type="<?= (int)$r['room_type_id'] ?>"
                              data-name="<?= h($r['name']) ?>"
                              data-floor="<?= (int)$r['floor'] ?>"
                              data-capacity="<?= (int)$r['capacity'] ?>"
                              data-price="<?= h($r['price_per_night']) ?>"
                              data-status="<?= h($r['status']) ?>"
                              data-description="<?= h($r['description'] ?? '') ?>"
                              title="Edit Room">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>
                      <form action="rooms.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this room?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="delete_room" value="1">
                        <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Room"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Edit Room Modal -->
<div id="editRoomModal" style="display:none;position:fixed;inset:0;background:rgba(4,7,20,0.85);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div class="glass-card" style="width:100%;max-width:680px;padding:24px;max-height:90vh;overflow-y:auto;border:1px solid rgba(201,168,76,0.35);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-family:var(--font-serif);color:var(--gold);margin:0;"><i class="fa-solid fa-pen-to-square"></i> Edit Room</h3>
      <button type="button" onclick="closeEditRoomModal()" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form action="rooms.php" method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
      <?= csrfField() ?>
      <input type="hidden" name="edit_room" value="1">
      <input type="hidden" name="room_id" id="edit_room_id">
      <div>
        <label class="form-label">Room Number</label>
        <input type="text" name="room_number" id="edit_room_number" class="form-control" required>
      </div>
      <div>
        <label class="form-label">Room Type</label>
        <select name="room_type_id" id="edit_room_type_id" class="form-control" required>
          <?php foreach ($roomTypes as $tp): ?>
            <option value="<?= $tp['id'] ?>"><?= h($tp['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Name</label>
        <input type="text" name="name" id="edit_room_name" class="form-control" required>
      </div>
      <div>
        <label class="form-label">Floor</label>
        <input type="number" name="floor" id="edit_room_floor" class="form-control" required>
      </div>
      <div>
        <label class="form-label">Capacity (Pax)</label>
        <input type="number" name="capacity" id="edit_room_capacity" class="form-control" min="1" required>
      </div>
      <div>
        <label class="form-label">Price / Night (₱)</label>
        <input type="number" step="0.01" name="price_per_night" id="edit_room_price" class="form-control" min="0" required>
      </div>
      <div>
        <label class="form-label">Status</label>
        <select name="status" id="edit_room_status" class="form-control" required>
          <option value="available">Available</option>
          <option value="dirty">Needs Cleaning (Dirty)</option>
          <option value="maintenance">Maintenance</option>
          <option value="reserved">Reserved</option>
          <option value="occupied">Occupied</option>
        </select>
      </div>
      <div>
        <label class="form-label">Replace Thumbnail <small style="color:var(--text-muted);">(optional)</small></label>
        <input type="file" name="thumbnail" class="form-control" accept="image/*">
      </div>
      <div style="grid-column:1/-1;">
        <label class="form-label">Description</label>
        <textarea name="description" id="edit_room_description" class="form-control" style="min-height:70px;"></textarea>
      </div>
      <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closeEditRoomModal()" class="btn btn-ghost">Cancel</button>
        <button type="submit" class="btn btn-gold"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/admin.js"></script>
<script>
function openEditRoomModal(btn) {
  document.getElementById('edit_room_id').value = btn.dataset.id;
  document.getElementById('edit_room_number').value = btn.dataset.number;
  document.getElementById('edit_room_type_id').value = btn.dataset.type;
  document.getElementById('edit_room_name').value = btn.dataset.name;
  document.getElementById('edit_room_floor').value = btn.dataset.floor;
  document.getElementById('edit_room_capacity').value = btn.dataset.capacity;
  document.getElementById('edit_room_price').value = btn.dataset.price;
  document.getElementById('edit_room_status').value = btn.dataset.status;
  document.getElementById('edit_room_description').value = btn.dataset.description;
  document.getElementById('editRoomModal').style.display = 'flex';
}
function closeEditRoomModal() {
  document.getElementById('editRoomModal').style.display = 'none';
}
</script>
</body>
</html>
