<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('admin');
releaseReadOnlySession();

throttleStaleBookingSweep(24, 15);

// ------------------------------------------------------------
// Helpers: Room Category (Hotel Room vs Dormitory), Amenities & Presets
// ------------------------------------------------------------
$builtInRoomPhotos = [
    'default-room.jpg' => ['label' => 'Default Hotel Room', 'cat' => 'hotel'],
    'room-101.jpg'     => ['label' => 'Standard Hotel Room (Garden View)', 'cat' => 'hotel'],
    'room-102.jpg'     => ['label' => 'Standard Hotel Room (Street View)', 'cat' => 'hotel'],
    'room-201.jpg'     => ['label' => 'Deluxe Hotel Room (Navy & Brass)', 'cat' => 'hotel'],
    'room-202.jpg'     => ['label' => 'Deluxe Hotel Room (Balcony Dusk)', 'cat' => 'hotel'],
    'suite-301.jpg'    => ['label' => 'Executive Suite (City Lounge)', 'cat' => 'hotel'],
    'room-401.jpg'     => ['label' => 'Family Room (Two Double Beds)', 'cat' => 'hotel'],
    'studio-s01.jpg'   => ['label' => 'Studio Unit (Kitchenette & Desk)', 'cat' => 'hotel'],
    'dorm-f01.jpg'     => ['label' => 'Dormitory — Birchwood Pods (Female)', 'cat' => 'dormitory'],
    'dorm-m02.jpg'     => ['label' => 'Dormitory — Dark Wood & Steel (Male)', 'cat' => 'dormitory'],
    'dorm-03.jpg'      => ['label' => 'Dormitory — Timber Pods & Study Nook', 'cat' => 'dormitory'],
];

$roomTypes = dbRows("SELECT * FROM room_types ORDER BY id ASC");
$dormTypeId = 0;
$hotelTypes = [];
foreach ($roomTypes as $rt) {
    if (strcasecmp((string)$rt['name'], 'Dormitory') === 0) {
        $dormTypeId = (int)$rt['id'];
    } else {
        $hotelTypes[] = $rt;
    }
}
if ($dormTypeId <= 0) {
    $dormTypeId = dbInsert("INSERT INTO room_types (name, description, icon) VALUES ('Dormitory', 'Budget-friendly shared dormitory room', 'fa-building')");
    $roomTypes = dbRows("SELECT * FROM room_types ORDER BY id ASC");
}
if (empty($hotelTypes)) {
    $stdId = dbInsert("INSERT INTO room_types (name, description, icon) VALUES ('Standard Room', 'Overnight private hotel room', 'fa-bed')");
    $roomTypes = dbRows("SELECT * FROM room_types ORDER BY id ASC");
    $hotelTypes = [['id' => $stdId, 'name' => 'Standard Room']];
}
$defaultHotelTypeId = (int)$hotelTypes[0]['id'];
$validHotelTypeIds = array_map(fn($t) => (int)$t['id'], $hotelTypes);

$resolveCategoryAndType = function (string $rawCategory, int $postedTypeId) use ($dormTypeId, $validHotelTypeIds, $defaultHotelTypeId): array {
    $cat = strtolower(trim($rawCategory));
    if ($cat === 'dormitory' || ($cat === '' && $postedTypeId === $dormTypeId)) {
        return ['category' => 'dormitory', 'room_type_id' => $dormTypeId, 'is_dorm' => 1];
    }
    $resolvedTypeId = in_array($postedTypeId, $validHotelTypeIds, true) ? $postedTypeId : $defaultHotelTypeId;
    return ['category' => 'hotel', 'room_type_id' => $resolvedTypeId, 'is_dorm' => 0];
};

$normalizeAmenitiesJson = function (string $rawAmenities, bool $isDorm, bool $applyDefaultIfEmpty = true): ?string {
    $parts = array_values(array_unique(array_filter(
        array_map('trim', explode(',', $rawAmenities)),
        fn($v) => $v !== ''
    )));
    if (empty($parts) && $applyDefaultIfEmpty) {
        $parts = $isDorm
            ? ['WiFi', 'Air Con', 'Locker', 'Privacy Curtain', 'Shared Bathroom', 'Charging Station']
            : ['WiFi', 'Air Con', 'TV', 'Hot Shower', 'Ref'];
    }
    if (empty($parts)) {
        return null;
    }
    $parts = array_map(fn($s) => mb_substr($s, 0, 50, 'UTF-8'), array_slice($parts, 0, 12));
    return json_encode($parts, JSON_UNESCAPED_UNICODE);
};

// Add Room handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $roomNum    = trim($_POST['room_number'] ?? '');
        $resolved   = $resolveCategoryAndType((string)($_POST['room_category'] ?? 'hotel'), (int)($_POST['room_type_id'] ?? $defaultHotelTypeId));
        $typeId     = $resolved['room_type_id'];
        $isDorm     = $resolved['is_dorm'];
        $name       = trim($_POST['name'] ?? '');
        $floor      = max(1, (int)($_POST['floor'] ?? 1));
        $cap        = max(1, (int)($_POST['capacity'] ?? ($isDorm ? 1 : 2)));
        $price      = max(0, (float)($_POST['price_per_night'] ?? 0));
        $wkndRaw    = trim((string)($_POST['weekend_rate'] ?? ''));
        $wkndRate   = ($wkndRaw !== '' && is_numeric($wkndRaw) && (float)$wkndRaw >= 0) ? (float)$wkndRaw : null;
        $desc       = trim($_POST['description'] ?? '');
        $amenities  = $normalizeAmenitiesJson((string)($_POST['amenities'] ?? ''), $isDorm === 1, true);
        $tagsJson   = json_encode($isDorm ? ['dormitory', 'budget'] : ['hotel room', 'overnight'], JSON_UNESCAPED_UNICODE);

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

        // Handle built-in photo preset or custom thumbnail upload (upload wins if provided)
        $preset = trim((string)($_POST['thumbnail_preset'] ?? ''));
        $thumbnail = isset($builtInRoomPhotos[$preset]) ? $preset : ($isDorm ? 'dorm-03.jpg' : 'default-room.jpg');
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
            "INSERT INTO rooms (room_number, room_type_id, name, floor, capacity, price_per_night, weekend_rate, amenities, tags, description, thumbnail, status, is_dorm) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?)",
            [$roomNum, $typeId, $name, $floor, $cap, $price, $wkndRate, $amenities, $tagsJson, $desc, $thumbnail, $isDorm]
        );

        $catWord = $isDorm ? 'Dormitory Room' : 'Hotel Room (Overnight)';
        auditLog('Added Room', 'Admin Module', "Added {$catWord} #{$roomNum} - {$name}");
        setFlash('success', "New <strong>{$catWord}</strong> (#" . h($roomNum) . " — " . h($name) . ") added successfully!");
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
        $roomId     = (int)($_POST['room_id'] ?? 0);
        $roomNum    = trim($_POST['room_number'] ?? '');
        $resolved   = $resolveCategoryAndType((string)($_POST['room_category'] ?? ''), (int)($_POST['room_type_id'] ?? $defaultHotelTypeId));
        $typeId     = $resolved['room_type_id'];
        $isDorm     = $resolved['is_dorm'];
        $name       = trim($_POST['name'] ?? '');
        $floor      = max(1, (int)($_POST['floor'] ?? 1));
        $cap        = max(1, (int)($_POST['capacity'] ?? 1));
        $price      = max(0, (float)($_POST['price_per_night'] ?? 0));
        $wkndRaw    = trim((string)($_POST['weekend_rate'] ?? ''));
        $wkndRate   = ($wkndRaw !== '' && is_numeric($wkndRaw) && (float)$wkndRaw >= 0) ? (float)$wkndRaw : null;
        $status     = $_POST['status'] ?? 'available';
        $desc       = trim($_POST['description'] ?? '');
        $amenities  = $normalizeAmenitiesJson((string)($_POST['amenities'] ?? ''), $isDorm === 1, false);
        $tagsJson   = json_encode($isDorm ? ['dormitory', 'budget'] : ['hotel room', 'overnight'], JSON_UNESCAPED_UNICODE);

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
            if ($status !== 'occupied') {
                $activeOccupant = dbRow("SELECT booking_code FROM bookings WHERE room_id = ? AND status = 'checked_in' LIMIT 1", [$roomId]);
                if ($activeOccupant) {
                    setFlash('error', "Cannot set Room #{$roomNum} to '{$status}' while Booking #{$activeOccupant['booking_code']} is currently Checked In. Check out the guest first.");
                    header("Location: rooms.php");
                    exit;
                }
            }

            $thumbnail = (string)($existing['thumbnail'] ?? 'default-room.jpg');
            $preset = trim((string)($_POST['thumbnail_preset'] ?? ''));
            if (!empty($_FILES['thumbnail']['name'])) {
                $upErr = null;
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
            } elseif ($preset !== '' && isset($builtInRoomPhotos[$preset])) {
                if (!empty($thumbnail) && strpos($thumbnail, '/') !== false) {
                    deleteUpload($thumbnail);
                }
                $thumbnail = $preset;
            }

            if ($amenities === null && !empty($existing['amenities']) && trim((string)($_POST['amenities'] ?? '')) === '') {
                $amenities = $existing['amenities'];
            }

            dbExec(
                "UPDATE rooms SET room_number = ?, room_type_id = ?, name = ?, floor = ?, capacity = ?, price_per_night = ?, weekend_rate = ?, amenities = ?, tags = ?, status = ?, description = ?, thumbnail = ?, is_dorm = ? WHERE id = ?",
                [$roomNum, $typeId, $name, $floor, $cap, $price, $wkndRate, $amenities, $tagsJson, $status, $desc, $thumbnail, $isDorm, $roomId]
            );
            $catWord = $isDorm ? 'Dormitory Room' : 'Hotel Room (Overnight)';
            auditLog('Updated Room', 'Admin Module', "Updated {$catWord} #$roomNum ($name), Price: ₱" . number_format($price, 2) . ", Status: $status");
            setFlash('success', "<strong>{$catWord}</strong> #" . h($roomNum) . " updated successfully.");
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

$rooms = dbRows("SELECT r.*, rt.name as type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id ORDER BY CASE WHEN rt.name = 'Dormitory' OR r.is_dorm = 1 THEN 1 ELSE 0 END ASC, r.room_number ASC, r.id DESC");
$adminDormCount = count(array_filter($rooms, fn($r) => !empty($r['is_dorm']) || strcasecmp((string)($r['type_name'] ?? ''), 'Dormitory') === 0));
$adminHotelCount = count($rooms) - $adminDormCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Rooms — Admin Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/admin.css')) ?>">
  <style>
    .room-cat-selector {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 16px;
    }
    .room-cat-option {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border-radius: 10px;
      border: 1px solid rgba(255,255,255,0.14);
      background: rgba(255,255,255,0.03);
      color: #cbd5e1;
      cursor: pointer;
      text-align: left;
      transition: all 0.18s ease;
    }
    .room-cat-option:hover {
      border-color: rgba(201,168,76,0.45);
      background: rgba(255,255,255,0.06);
    }
    .room-cat-option.active-hotel {
      border-color: var(--gold);
      background: rgba(201,168,76,0.14);
      color: #fff;
      box-shadow: 0 0 0 1px rgba(201,168,76,0.35);
    }
    .room-cat-option.active-dorm {
      border-color: #60a5fa;
      background: rgba(59,130,246,0.16);
      color: #fff;
      box-shadow: 0 0 0 1px rgba(96,165,250,0.35);
    }
    .room-cat-option-icon {
      width: 36px;
      height: 36px;
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }
    @media (max-width: 600px) {
      .room-cat-selector { grid-template-columns: 1fr; }
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
        <div class="topbar-title">Room and Dormitory Management</div>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Room &amp; Dormitory Inventory</h1>
          <div class="page-header-sub">Choose <strong>Hotel Room (Overnight)</strong> or <strong>Dormitory Room</strong> when creating or editing rooms.</div>
        </div>
      </div>

      <!-- Add Room Form -->
      <div class="glass-card" style="padding:24px;margin-bottom:28px;">
        <h3 style="font-family:var(--font-serif);font-size:1.15rem;color:var(--gold);margin-bottom:14px;"><i class="fa-solid fa-plus-circle"></i> Add New Room</h3>
        <form action="rooms.php" method="POST" enctype="multipart/form-data" id="addRoomForm">
          <?= csrfField() ?>
          <input type="hidden" name="add_room" value="1">
          <input type="hidden" name="room_category" id="add_room_category" value="hotel">

          <!-- Category Selector: Hotel Room vs Dormitory Room -->
          <label class="form-label" style="margin-bottom:8px;">Select Room Category <span class="required">*</span></label>
          <div class="room-cat-selector">
            <button type="button" id="addCatBtnHotel" class="room-cat-option active-hotel" onclick="setAddRoomCategory('hotel')">
              <div class="room-cat-option-icon" style="background:rgba(201,168,76,0.2);color:var(--gold);">
                <i class="fa-solid fa-moon"></i>
              </div>
              <div>
                <div style="font-weight:700;font-size:0.92rem;">Hotel Room (Overnight / Pang-gabi)</div>
                <div style="font-size:0.74rem;color:var(--text-muted);">Private overnight rooms — Standard, Deluxe, Suite, Family, Studio</div>
              </div>
            </button>
            <button type="button" id="addCatBtnDorm" class="room-cat-option" onclick="setAddRoomCategory('dormitory')">
              <div class="room-cat-option-icon" style="background:rgba(59,130,246,0.2);color:#60a5fa;">
                <i class="fa-solid fa-building"></i>
              </div>
              <div>
                <div style="font-weight:700;font-size:0.92rem;">Dormitory Room</div>
                <div style="font-size:0.74rem;color:var(--text-muted);">Shared boutique dormitory / bedspace (Female, Male, or Co-ed Wing)</div>
              </div>
            </button>
          </div>

          <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(185px, 1fr));gap:14px;">
            <div>
              <label class="form-label">Room Number <span class="required">*</span></label>
              <input type="text" name="room_number" id="add_room_number" class="form-control" placeholder="e.g., 102" required>
            </div>
            <div id="addHotelTypeWrap">
              <label class="form-label">Hotel Room Sub-Type <span class="required">*</span></label>
              <select name="room_type_id" id="add_room_type_id" class="form-control" required>
                <?php foreach ($hotelTypes as $tp): ?>
                  <option value="<?= (int)$tp['id'] ?>"><?= h($tp['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div id="addDormTypeBadge" style="display:none;">
              <label class="form-label">Room Type</label>
              <div class="form-control" style="display:flex;align-items:center;gap:8px;background:rgba(59,130,246,0.12);border-color:rgba(96,165,250,0.4);color:#93c5fd;font-weight:600;">
                <i class="fa-solid fa-building"></i> Dormitory (Shared / Bedspace)
              </div>
            </div>
            <div>
              <label class="form-label">Room Name <span class="required">*</span></label>
              <input type="text" name="name" id="add_room_name" class="form-control" placeholder="e.g., Standard Hotel Room 102" required>
            </div>
            <div>
              <label class="form-label">Floor <span class="required">*</span></label>
              <input type="number" name="floor" id="add_room_floor" class="form-control" value="1" min="1" required>
            </div>
            <div>
              <label class="form-label">Capacity (Pax) <span class="required">*</span></label>
              <input type="number" name="capacity" id="add_room_capacity" class="form-control" value="2" min="1" required>
            </div>
            <div>
              <label class="form-label" id="addPriceLabel">Price / Night (Overnight) (₱) <span class="required">*</span></label>
              <input type="number" step="0.01" name="price_per_night" id="add_room_price" class="form-control" min="0" placeholder="800.00" required>
            </div>
            <div>
              <label class="form-label">Weekend Rate (₱) <small style="color:var(--text-muted);">(optional)</small></label>
              <input type="number" step="0.01" name="weekend_rate" id="add_room_weekend" class="form-control" min="0" placeholder="950.00">
            </div>
            <div>
              <label class="form-label">Built-in Photo Preset</label>
              <select name="thumbnail_preset" id="addRoomThumbPreset" class="form-control" onchange="onAddPresetChange(this.value)">
                <?php foreach ($builtInRoomPhotos as $file => $meta): ?>
                  <option value="<?= h($file) ?>" data-cat="<?= h($meta['cat']) ?>" <?= $file === 'default-room.jpg' ? 'selected' : '' ?>><?= h($meta['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label">Or Upload Custom Photo <small style="color:var(--text-muted);">(optional)</small></label>
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                <img id="addRoomThumbPreview" src="<?= h(assetUrl('images/rooms/default-room.jpg')) ?>" alt="New room preview" style="width:68px;height:46px;border-radius:8px;object-fit:cover;border:1px solid rgba(201,168,76,0.35);background:#0b132b;">
                <small style="color:var(--text-muted);font-size:0.73rem;">JPG, PNG, WebP (Max 15MB)</small>
              </div>
              <input type="file" name="thumbnail" id="addRoomThumbInput" class="form-control" accept="image/*" onchange="previewAddRoomThumb(this); optimizeImageInput(this, { maxDim: 1600, quality: 0.85, statusElId: 'addRoomThumbStatus' })">
              <small id="addRoomThumbStatus" style="display:none;color:var(--gold);font-size:0.74rem;margin-top:3px;"></small>
            </div>
            <div style="grid-column:1/-1;">
              <label class="form-label">Amenities <small style="color:var(--text-muted);">(comma-separated — leave blank for automatic defaults)</small></label>
              <input type="text" name="amenities" id="add_room_amenities" class="form-control" placeholder="WiFi, Air Con, TV, Hot Shower, Ref">
            </div>
            <div style="grid-column:1/-1;">
              <label class="form-label">Description</label>
              <textarea name="description" id="add_room_description" class="form-control" style="min-height:60px;" placeholder="Describe the room, bedding, view, and key features..."></textarea>
            </div>
            <button type="submit" class="btn btn-gold" id="addRoomSubmitBtn" style="grid-column:1/-1;width:fit-content;">
              <i class="fa-solid fa-save"></i> Save Hotel Room
            </button>
          </div>
        </form>
      </div>

      <!-- Inventory Category Filter Tabs & Search Toolbar -->
      <div class="admin-toolbar" style="flex-wrap:wrap;gap:12px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <button type="button" class="btn btn-gold btn-sm admin-room-filter-btn" data-cat="all" onclick="filterAdminRooms('all', this)">
            All Rooms (<?= count($rooms) ?>)
          </button>
          <button type="button" class="btn btn-ghost btn-sm admin-room-filter-btn" data-cat="hotel" onclick="filterAdminRooms('hotel', this)" style="border:1px solid rgba(201,168,76,0.35);">
            <i class="fa-solid fa-moon" style="color:var(--gold);"></i> Hotel Rooms · Overnight (<?= $adminHotelCount ?>)
          </button>
          <button type="button" class="btn btn-ghost btn-sm admin-room-filter-btn" data-cat="dormitory" onclick="filterAdminRooms('dormitory', this)" style="border:1px solid rgba(96,165,250,0.35);">
            <i class="fa-solid fa-building" style="color:#60a5fa;"></i> Dormitory Rooms (<?= $adminDormCount ?>)
          </button>
        </div>
        <div style="min-width:260px;">
          <input type="text" id="roomSearchInput" class="form-control" placeholder="Search room number, name, type, status..." oninput="applyAdminRoomFilters()" style="background:rgba(255,255,255,0.05);font-size:0.85rem;">
        </div>
      </div>

      <!-- Rooms Inventory Table -->
      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table class="table" id="roomsTable">
              <thead>
                <tr>
                  <th class="col-nowrap">Photo &amp; No.</th>
                  <th>Name</th>
                  <th class="col-nowrap">Category &amp; Type</th>
                  <th class="col-nowrap">Floor</th>
                  <th class="col-nowrap">Capacity</th>
                  <th class="col-amount">Rate</th>
                  <th class="col-nowrap">Status</th>
                  <th class="col-nowrap">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rooms)): ?>
                  <tr data-empty-row><td colspan="8" class="text-center" style="padding:32px;color:var(--text-muted);">No rooms found in inventory.</td></tr>
                <?php else: ?>
                  <?php foreach ($rooms as $r):
                    $isAdminDorm = !empty($r['is_dorm']) || strcasecmp((string)($r['type_name'] ?? ''), 'Dormitory') === 0;
                    $amArr = json_decode((string)($r['amenities'] ?? '[]'), true);
                    $amCsv = is_array($amArr) ? implode(', ', $amArr) : '';
                  ?>
                    <tr data-room-cat="<?= $isAdminDorm ? 'dormitory' : 'hotel' ?>">
                      <td class="col-nowrap">
                        <div style="display:flex;align-items:center;gap:10px;">
                          <img src="<?= h(roomThumbUrl($r['thumbnail'] ?? '')) ?>" alt="" style="width:46px;height:34px;border-radius:6px;object-fit:cover;border:1px solid rgba(255,255,255,0.12);" onerror="this.src='<?= h(assetUrl('images/rooms/default-room.jpg')) ?>';this.onerror=null;">
                          <strong>#<?= h($r['room_number']) ?></strong>
                        </div>
                      </td>
                      <td><?= h($r['name']) ?></td>
                      <td class="col-nowrap">
                        <?php if ($isAdminDorm): ?>
                          <span class="badge" style="background:rgba(59,130,246,0.18);color:#93c5fd;border:1px solid rgba(59,130,246,0.38);"><i class="fa-solid fa-building"></i> Dormitory</span>
                        <?php else: ?>
                          <span class="badge badge-gold"><i class="fa-solid fa-moon"></i> <?= h($r['type_name']) ?> · Overnight</span>
                        <?php endif; ?>
                      </td>
                      <td class="col-nowrap">Floor <?= (int)$r['floor'] ?></td>
                      <td class="col-nowrap"><?= (int)$r['capacity'] ?> pax</td>
                      <td class="col-amount" style="color:var(--gold);font-weight:700;">
                        <?= formatCurrency((float)$r['price_per_night']) ?>
                        <div style="font-size:0.7rem;font-weight:400;color:var(--text-muted);"><?= $isAdminDorm ? 'per bed / night' : 'per night' ?></div>
                      </td>
                      <td class="col-nowrap"><?= statusBadge($r['status']) ?></td>
                      <td class="col-nowrap" style="display:flex;gap:6px;">
                        <button type="button"
                                class="btn btn-ghost btn-sm"
                                onclick="openEditRoomModal(this)"
                                data-id="<?= (int)$r['id'] ?>"
                                data-category="<?= $isAdminDorm ? 'dormitory' : 'hotel' ?>"
                                data-number="<?= h($r['room_number']) ?>"
                                data-type="<?= (int)$r['room_type_id'] ?>"
                                data-name="<?= h($r['name']) ?>"
                                data-floor="<?= (int)$r['floor'] ?>"
                                data-capacity="<?= (int)$r['capacity'] ?>"
                                data-price="<?= h($r['price_per_night']) ?>"
                                data-weekend="<?= h($r['weekend_rate'] ?? '') ?>"
                                data-amenities="<?= h($amCsv) ?>"
                                data-status="<?= h($r['status']) ?>"
                                data-description="<?= h($r['description'] ?? '') ?>"
                                data-thumb="<?= h(roomThumbUrl($r['thumbnail'] ?? '')) ?>"
                                data-thumb-raw="<?= h($r['thumbnail'] ?? '') ?>"
                                title="Edit Room">
                          <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <form action="rooms.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this room?')">
                          <?= csrfField() ?>
                          <input type="hidden" name="delete_room" value="1">
                          <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm" title="Delete Room"><i class="fa-solid fa-trash"></i></button>
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

<!-- Edit Room Modal -->
<div id="editRoomModal" class="admin-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(4,7,20,0.85);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div class="glass-card" style="width:100%;max-width:720px;padding:24px;max-height:92vh;overflow-y:auto;border:1px solid rgba(201,168,76,0.35);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-family:var(--font-serif);color:var(--gold);margin:0;"><i class="fa-solid fa-pen-to-square"></i> Edit Room</h3>
      <button type="button" onclick="closeEditRoomModal()" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form action="rooms.php" method="POST" enctype="multipart/form-data" id="editRoomForm">
      <?= csrfField() ?>
      <input type="hidden" name="edit_room" value="1">
      <input type="hidden" name="room_id" id="edit_room_id">
      <input type="hidden" name="room_category" id="edit_room_category" value="hotel">

      <label class="form-label" style="margin-bottom:8px;">Room Category <span class="required">*</span></label>
      <div class="room-cat-selector">
        <button type="button" id="editCatBtnHotel" class="room-cat-option active-hotel" onclick="setEditRoomCategory('hotel')">
          <div class="room-cat-option-icon" style="background:rgba(201,168,76,0.2);color:var(--gold);">
            <i class="fa-solid fa-moon"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:0.88rem;">Hotel Room (Overnight)</div>
            <div style="font-size:0.72rem;color:var(--text-muted);">Private overnight room</div>
          </div>
        </button>
        <button type="button" id="editCatBtnDorm" class="room-cat-option" onclick="setEditRoomCategory('dormitory')">
          <div class="room-cat-option-icon" style="background:rgba(59,130,246,0.2);color:#60a5fa;">
            <i class="fa-solid fa-building"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:0.88rem;">Dormitory Room</div>
            <div style="font-size:0.72rem;color:var(--text-muted);">Shared dormitory / bedspace</div>
          </div>
        </button>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
        <div>
          <label class="form-label">Room Number <span class="required">*</span></label>
          <input type="text" name="room_number" id="edit_room_number" class="form-control" required>
        </div>
        <div id="editHotelTypeWrap">
          <label class="form-label">Hotel Room Sub-Type <span class="required">*</span></label>
          <select name="room_type_id" id="edit_room_type_id" class="form-control" required>
            <?php foreach ($hotelTypes as $tp): ?>
              <option value="<?= (int)$tp['id'] ?>"><?= h($tp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="editDormTypeBadge" style="display:none;">
          <label class="form-label">Room Type</label>
          <div class="form-control" style="display:flex;align-items:center;gap:8px;background:rgba(59,130,246,0.12);border-color:rgba(96,165,250,0.4);color:#93c5fd;font-weight:600;">
            <i class="fa-solid fa-building"></i> Dormitory (Shared / Bedspace)
          </div>
        </div>
        <div>
          <label class="form-label">Room Name <span class="required">*</span></label>
          <input type="text" name="name" id="edit_room_name" class="form-control" required>
        </div>
        <div>
          <label class="form-label">Floor <span class="required">*</span></label>
          <input type="number" name="floor" id="edit_room_floor" class="form-control" min="1" required>
        </div>
        <div>
          <label class="form-label">Capacity (Pax) <span class="required">*</span></label>
          <input type="number" name="capacity" id="edit_room_capacity" class="form-control" min="1" required>
        </div>
        <div>
          <label class="form-label" id="editPriceLabel">Price / Night (Overnight) (₱) <span class="required">*</span></label>
          <input type="number" step="0.01" name="price_per_night" id="edit_room_price" class="form-control" min="0" required>
        </div>
        <div>
          <label class="form-label">Weekend Rate (₱) <small style="color:var(--text-muted);">(optional)</small></label>
          <input type="number" step="0.01" name="weekend_rate" id="edit_room_weekend" class="form-control" min="0" placeholder="Optional">
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
          <label class="form-label">Switch to Built-in Photo <small style="color:var(--text-muted);">(optional)</small></label>
          <select name="thumbnail_preset" id="editRoomThumbPreset" class="form-control" onchange="onEditPresetChange(this.value)">
            <option value="">-- Keep Current Photo --</option>
            <?php foreach ($builtInRoomPhotos as $file => $meta): ?>
              <option value="<?= h($file) ?>"><?= h($meta['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Or Upload New Photo <small style="color:var(--text-muted);">(optional)</small></label>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
            <img id="editRoomThumbPreview" src="<?= h(assetUrl('images/rooms/default-room.jpg')) ?>" alt="Current room photo" style="width:72px;height:48px;border-radius:8px;object-fit:cover;border:1px solid rgba(201,168,76,0.35);background:#0b132b;">
            <small style="color:var(--text-muted);font-size:0.74rem;">Current room photo</small>
          </div>
          <input type="file" name="thumbnail" id="editRoomThumbInput" class="form-control" accept="image/*" onchange="previewEditRoomThumb(this); optimizeImageInput(this, { maxDim: 1600, quality: 0.85, statusElId: 'editRoomThumbStatus' })">
          <small id="editRoomThumbStatus" style="display:none;color:var(--gold);font-size:0.74rem;margin-top:3px;"></small>
        </div>
        <div style="grid-column:1/-1;">
          <label class="form-label">Amenities <small style="color:var(--text-muted);">(comma-separated)</small></label>
          <input type="text" name="amenities" id="edit_room_amenities" class="form-control" placeholder="WiFi, Air Con, TV, Hot Shower">
        </div>
        <div style="grid-column:1/-1;">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit_room_description" class="form-control" style="min-height:70px;"></textarea>
        </div>
        <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" onclick="closeEditRoomModal()" class="btn btn-ghost">Cancel</button>
          <button type="submit" class="btn btn-gold"><i class="fa-solid fa-save"></i> Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script src="<?= h(assetUrl('js/admin.js')) ?>"></script>
<script>
const ROOM_PRESET_URLS = <?= json_encode(
    array_combine(
        array_keys($builtInRoomPhotos),
        array_map(fn($f) => assetUrl('images/rooms/' . $f), array_keys($builtInRoomPhotos))
    ),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
const DEFAULT_HOTEL_TYPE_ID = <?= (int)$defaultHotelTypeId ?>;

function setAddRoomCategory(cat) {
  const isDorm = (cat === 'dormitory');
  document.getElementById('add_room_category').value = isDorm ? 'dormitory' : 'hotel';

  const btnHotel = document.getElementById('addCatBtnHotel');
  const btnDorm  = document.getElementById('addCatBtnDorm');
  btnHotel.classList.toggle('active-hotel', !isDorm);
  btnDorm.classList.toggle('active-dorm', isDorm);

  const hotelWrap = document.getElementById('addHotelTypeWrap');
  const dormBadge = document.getElementById('addDormTypeBadge');
  const typeSel   = document.getElementById('add_room_type_id');
  hotelWrap.style.display = isDorm ? 'none' : '';
  dormBadge.style.display = isDorm ? '' : 'none';
  if (typeSel) typeSel.required = !isDorm;

  document.getElementById('add_room_number').placeholder = isDorm ? 'e.g., D04' : 'e.g., 102';
  document.getElementById('add_room_name').placeholder   = isDorm ? 'e.g., Dormitory Room - Student Wing' : 'e.g., Standard Hotel Room 102';
  document.getElementById('addPriceLabel').innerHTML     = isDorm
    ? 'Price / Bed &middot; Night (₱) <span class="required">*</span>'
    : 'Price / Night (Overnight) (₱) <span class="required">*</span>';
  document.getElementById('add_room_price').placeholder   = isDorm ? '350.00' : '800.00';
  document.getElementById('add_room_weekend').placeholder = isDorm ? '400.00' : '950.00';
  document.getElementById('add_room_amenities').placeholder = isDorm
    ? 'WiFi, Air Con, Locker, Privacy Curtain, Shared Bathroom, Charging Station'
    : 'WiFi, Air Con, TV, Hot Shower, Ref';

  const capInput = document.getElementById('add_room_capacity');
  if (capInput && (capInput.value === '1' || capInput.value === '2' || capInput.value === '')) {
    capInput.value = isDorm ? '1' : '2';
  }

  const presetSel = document.getElementById('addRoomThumbPreset');
  const fileInput = document.getElementById('addRoomThumbInput');
  if (presetSel && (!fileInput || !fileInput.files || !fileInput.files.length)) {
    const targetPreset = isDorm ? 'dorm-03.jpg' : 'default-room.jpg';
    presetSel.value = targetPreset;
    onAddPresetChange(targetPreset);
  }

  const submitBtn = document.getElementById('addRoomSubmitBtn');
  if (submitBtn) {
    submitBtn.innerHTML = isDorm
      ? '<i class="fa-solid fa-save"></i> Save Dormitory Room'
      : '<i class="fa-solid fa-save"></i> Save Hotel Room';
  }
}

function onAddPresetChange(file) {
  const prev = document.getElementById('addRoomThumbPreview');
  const fileInput = document.getElementById('addRoomThumbInput');
  if (fileInput) fileInput.value = '';
  if (prev && ROOM_PRESET_URLS[file]) {
    prev.src = ROOM_PRESET_URLS[file];
  }
}

function previewAddRoomThumb(input) {
  const prev = document.getElementById('addRoomThumbPreview');
  if (!prev || !input || !input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = (e) => { prev.src = e.target.result; };
  reader.readAsDataURL(input.files[0]);
}

function setEditRoomCategory(cat) {
  const isDorm = (cat === 'dormitory');
  document.getElementById('edit_room_category').value = isDorm ? 'dormitory' : 'hotel';

  document.getElementById('editCatBtnHotel').classList.toggle('active-hotel', !isDorm);
  document.getElementById('editCatBtnDorm').classList.toggle('active-dorm', isDorm);

  const hotelWrap = document.getElementById('editHotelTypeWrap');
  const dormBadge = document.getElementById('editDormTypeBadge');
  const typeSel   = document.getElementById('edit_room_type_id');
  hotelWrap.style.display = isDorm ? 'none' : '';
  dormBadge.style.display = isDorm ? '' : 'none';
  if (typeSel) {
    typeSel.required = !isDorm;
    if (!isDorm && !typeSel.value) {
      typeSel.value = String(DEFAULT_HOTEL_TYPE_ID);
    }
  }

  document.getElementById('editPriceLabel').innerHTML = isDorm
    ? 'Price / Bed &middot; Night (₱) <span class="required">*</span>'
    : 'Price / Night (Overnight) (₱) <span class="required">*</span>';
}

let currentEditThumbUrl = '';
function openEditRoomModal(btn) {
  const cat = btn.dataset.category === 'dormitory' ? 'dormitory' : 'hotel';
  document.getElementById('edit_room_id').value = btn.dataset.id;
  document.getElementById('edit_room_number').value = btn.dataset.number;
  const typeSel = document.getElementById('edit_room_type_id');
  if (typeSel) {
    const hasOpt = Array.from(typeSel.options).some(o => o.value === btn.dataset.type);
    typeSel.value = hasOpt ? btn.dataset.type : String(DEFAULT_HOTEL_TYPE_ID);
  }
  setEditRoomCategory(cat);

  document.getElementById('edit_room_name').value = btn.dataset.name;
  document.getElementById('edit_room_floor').value = btn.dataset.floor;
  document.getElementById('edit_room_capacity').value = btn.dataset.capacity;
  document.getElementById('edit_room_price').value = btn.dataset.price;
  document.getElementById('edit_room_weekend').value = btn.dataset.weekend || '';
  document.getElementById('edit_room_amenities').value = btn.dataset.amenities || '';
  document.getElementById('edit_room_status').value = btn.dataset.status;
  document.getElementById('edit_room_description').value = btn.dataset.description;

  currentEditThumbUrl = btn.dataset.thumb || ROOM_PRESET_URLS['default-room.jpg'];
  const thumbPrev = document.getElementById('editRoomThumbPreview');
  if (thumbPrev) thumbPrev.src = currentEditThumbUrl;

  const presetSel = document.getElementById('editRoomThumbPreset');
  if (presetSel) presetSel.value = '';
  const thumbInput = document.getElementById('editRoomThumbInput');
  if (thumbInput) thumbInput.value = '';
  const st = document.getElementById('editRoomThumbStatus');
  if (st) st.style.display = 'none';

  document.getElementById('editRoomModal').style.display = 'flex';
}

function onEditPresetChange(file) {
  const prev = document.getElementById('editRoomThumbPreview');
  const thumbInput = document.getElementById('editRoomThumbInput');
  if (thumbInput) thumbInput.value = '';
  if (!prev) return;
  prev.src = (file && ROOM_PRESET_URLS[file]) ? ROOM_PRESET_URLS[file] : currentEditThumbUrl;
}

function previewEditRoomThumb(input) {
  const thumbPrev = document.getElementById('editRoomThumbPreview');
  const presetSel = document.getElementById('editRoomThumbPreset');
  if (presetSel) presetSel.value = '';
  if (!thumbPrev || !input || !input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = (e) => { thumbPrev.src = e.target.result; };
  reader.readAsDataURL(input.files[0]);
}

function closeEditRoomModal() {
  document.getElementById('editRoomModal').style.display = 'none';
}

// Live Category + Search combined filter for Admin Rooms table
let activeAdminRoomCat = 'all';
function filterAdminRooms(cat, btn) {
  activeAdminRoomCat = cat;
  document.querySelectorAll('.admin-room-filter-btn').forEach(b => {
    b.classList.remove('btn-gold');
    b.classList.add('btn-ghost');
  });
  if (btn) {
    btn.classList.remove('btn-ghost');
    btn.classList.add('btn-gold');
  }
  applyAdminRoomFilters();
}

function applyAdminRoomFilters() {
  const term = (document.getElementById('roomSearchInput')?.value || '').toLowerCase().trim();
  document.querySelectorAll('#roomsTable tbody tr').forEach(row => {
    if (row.hasAttribute('data-empty-row')) return;
    const catOk = (activeAdminRoomCat === 'all' || row.dataset.roomCat === activeAdminRoomCat);
    const textOk = term === '' || (row.innerText || '').toLowerCase().includes(term);
    row.style.display = (catOk && textOk) ? '' : 'none';
  });
}
</script>
</body>
</html>
