<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

requireRole('user');

$userId = currentUserId();
$user   = dbRow("SELECT * FROM users WHERE id = ?", [$userId]);

// Check Profile Verification Requirement (KYC)
$kyc = getUserKycStatus($userId);
if (!$kyc['is_verified']) {
    if ($kyc['is_pending']) {
        setFlash('info', 'Your identity document is currently under review by the administration. You will be able to book once verified.');
    } elseif ($kyc['is_rejected']) {
        setFlash('error', 'Your ID verification was not approved: ' . ($kyc['rejection_reason'] ?: 'Please re-upload a clear valid ID.') . ' Please update your profile.');
    } else {
        setFlash('warning', 'Please complete your profile and upload a valid government or school ID for verification before booking.');
    }
    header("Location: profile.php");
    exit;
}

releaseReadOnlySession();

throttleStaleBookingSweep(24, 15);

$selectedRoomId = (int)($_GET['room_id'] ?? 0);
$rooms = dbRows("
    SELECT r.*, rt.name AS type_name
    FROM rooms r
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    WHERE r.status != 'maintenance'
    ORDER BY r.price_per_night ASC
");
$allBookingsMap = getAllRoomsActiveBookingsMap();
$defaultRoomImg = ASSETS_URL . '/images/rooms/default-room.jpg';

$roomOccupancyMap = [];
$roomsClientMap = [];
foreach ($rooms as $rm) {
    $rid = (int)$rm['id'];
    $occ = getRoomOccupancyStatus($rid, $rm['status'], $allBookingsMap[$rid] ?? []);
    $roomOccupancyMap[$rid] = $occ;
    $roomsClientMap[$rid] = [
        'id'              => $rid,
        'name'            => (string)$rm['name'],
        'room_number'     => (string)($rm['room_number'] ?? ''),
        'type_name'       => (string)($rm['type_name'] ?? 'Room'),
        'floor'           => (int)($rm['floor'] ?? 1),
        'capacity'        => (int)($rm['capacity'] ?? 1),
        'price_per_night' => (float)$rm['price_per_night'],
        'description'     => (string)($rm['description'] ?? ''),
        'amenities'       => safeJson($rm['amenities'] ?? null),
        'thumbnail_url'   => roomThumbUrl($rm['thumbnail'] ?? null),
        'status_state'    => (string)$occ['state'],
        'status_label'    => (string)$occ['label'],
    ];
}

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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $roomId        = (int)($_POST['room_id'] ?? 0);
        $checkIn       = $_POST['check_in'] ?? '';
        $checkOut      = $_POST['check_out'] ?? '';
        $guests        = (int)($_POST['guests'] ?? 1);
        $requests      = trim($_POST['special_requests'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'maya';
        $actionMode    = $_POST['action_mode'] ?? 'book_and_pay'; // 'book_and_pay' or 'book_only'

        if (!in_array($paymentMethod, ['maya', 'gcash'], true)) {
            $paymentMethod = 'maya';
        }

        if (!$roomId || empty($checkIn) || empty($checkOut)) {
            $error = 'Please select a room, check-in, and check-out date.';
        } elseif ($guests < 1) {
            $error = 'Number of guests must be at least 1.';
        } elseif ($checkIn >= $checkOut) {
            $error = 'The check-out date must be later than the check-in date.';
        } elseif ($checkIn < date('Y-m-d')) {
            $error = 'Check-in date cannot be in the past.';
        } else {
            $conflict = findConflictingBooking($roomId, $checkIn, $checkOut);
            if ($conflict) {
                $error = 'Duplicate Booking Prevented: Room "' . $conflict['room_name'] . '" is already reserved from ' . formatDate($conflict['check_in']) . ' to ' . formatDate($conflict['check_out']) . ' (Booking #' . $conflict['booking_code'] . '). Please select other dates.';
            } else {
                $refNum    = trim($_POST['reference_number'] ?? '');
                $notes     = trim($_POST['payment_notes'] ?? '');
                $proofFile = null;

                if ($actionMode === 'book_and_pay') {
                    if (empty($refNum)) {
                        $error = 'Please enter your payment reference number / transaction ID.';
                    } elseif (empty($_FILES['proof']['name'])) {
                        $error = 'Please upload a screenshot or photo of your payment receipt.';
                    } else {
                        $proofFile = uploadFile($_FILES['proof'], 'proofs', ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE, $bookProofErr);
                        if (!$proofFile) {
                            $error = $bookProofErr ?: 'Invalid image type or the receipt file exceeds limit (Max 15MB JPG, PNG, and WebP).';
                        }
                    }
                }

                if (empty($error)) {
                    $bookingId = 0;
                    getDB()->beginTransaction();
                    try {
                        $room = dbRow("SELECT * FROM rooms WHERE id = ? FOR UPDATE", [$roomId]);
                        $conflictLock = findConflictingBooking($roomId, $checkIn, $checkOut);

                        if (!$room || $room['status'] === 'maintenance') {
                            getDB()->rollBack();
                            $error = 'The selected room is unavailable or currently under maintenance.';
                        } elseif ($guests > (int)$room['capacity']) {
                            getDB()->rollBack();
                            $error = 'Selected guest count (' . $guests . ') exceeds the maximum capacity (' . (int)$room['capacity'] . ' pax) of ' . h($room['name']) . '.';
                        } elseif ($conflictLock) {
                            getDB()->rollBack();
                            $error = 'This room was just booked by another guest for those dates. Please choose another date or room.';
                        } else {
                        $nights      = calculateNights($checkIn, $checkOut);
                        $totalAmount = $nights * $room['price_per_night'];
                        $bookingCode = generateBookingCode();

                        // 1. Insert Booking Record
                        $bookingId = dbInsert(
                            "INSERT INTO bookings (booking_code, user_id, room_id, check_in, check_out, num_guests, nights, subtotal, total_amount, special_requests, payment_method, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                            [$bookingCode, $userId, $roomId, $checkIn, $checkOut, $guests, $nights, $totalAmount, $totalAmount, $requests, $paymentMethod]
                        );

                        // 2. If guest submitted payment proof simultaneously in the pop-up modal
                        if ($actionMode === 'book_and_pay' && $proofFile) {
                            dbInsert(
                                "INSERT INTO payments (booking_id, user_id, amount, method, reference_number, proof_image, status, notes) 
                                 VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)",
                                [$bookingId, $userId, $totalAmount, $paymentMethod, $refNum, $proofFile, $notes]
                            );

                            createNotification($userId, 'Payment Submitted', "Your receipt for booking #$bookingCode has been received. Verification is in progress.", 'payment');
                            auditLog('Created Booking & Uploaded Proof', 'User Module', "Booking #$bookingCode created with immediate payment proof by User #$userId");
                            getDB()->commit();

                            setFlash('success', "Your reservation (#$bookingCode) and payment receipt were submitted successfully! Hotel staff will verify it shortly.");
                            header("Location: booking-detail.php?id=$bookingId");
                            exit;
                        } else {
                            // Reserved without instant proof; take to payment screen
                            createNotification($userId, 'Booking Created!', "Your booking (#$bookingCode) has been created. Please settle your reservation via " . ($paymentMethod === 'gcash' ? 'GCash' : 'PayMaya') . ".", 'booking');
                            auditLog('Created Booking', 'User Module', "Booking #$bookingCode created (pay later) by User #$userId");
                            getDB()->commit();

                            setFlash('success', "Your reservation (#$bookingCode) has been created! Please complete your payment below.");
                            header("Location: payment.php?booking_id=$bookingId&method=$paymentMethod");
                            exit;
                        }
                    }
                } catch (Throwable $e) {
                    if (getDB()->inTransaction()) {
                        getDB()->rollBack();
                    }
                    throw $e;
                }
            }
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book a Room — Marvelous Hotel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../frontend/assets/css/base.css">
  <link rel="stylesheet" href="../frontend/assets/css/user.css">
  <?php require_once __DIR__ . '/../components/frontend-frameworks.php'; renderFrontendFrameworks(true, true); ?>
  <style>
    .verified-banner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      background: rgba(34, 197, 94, 0.08);
      border: 1px solid rgba(34, 197, 94, 0.25);
      border-radius: 12px;
      padding: 14px 20px;
      margin-bottom: 24px;
    }
    .badge-maya {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: rgba(0, 210, 106, 0.15);
      color: #00e074;
      border: 1px solid rgba(0, 210, 106, 0.35);
      padding: 3px 8px;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-gcash {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: rgba(41, 121, 255, 0.15);
      color: #64b5f6;
      border: 1px solid rgba(41, 121, 255, 0.35);
      padding: 3px 8px;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .calc-summary-card {
      background: rgba(255, 255, 255, 0.02);
      border: 1px dashed rgba(201, 168, 76, 0.35);
      border-radius: 12px;
      padding: 18px 20px;
      margin-top: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 14px;
    }

    /* Pop-up Modal Styling */
    .payment-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(5, 9, 24, 0.88);
      backdrop-filter: blur(8px);
      z-index: 9999;
      padding: 20px;
      overflow-y: auto;
      align-items: center;
      justify-content: center;
    }
    .payment-modal-overlay.active {
      display: flex;
    }
    .payment-modal-card {
      background: #0d1527;
      border: 1px solid rgba(201, 168, 76, 0.4);
      border-radius: 16px;
      width: 100%;
      max-width: 680px;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7), 0 0 25px rgba(201, 168, 76, 0.15);
      overflow: hidden;
      animation: modalSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes modalSlideUp {
      from { opacity: 0; transform: translateY(20px) scale(0.97); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .channel-tab-btn {
      flex: 1;
      padding: 12px 16px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 10px;
      color: var(--text-muted);
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: all 0.2s ease;
    }
    .channel-tab-btn:hover {
      background: rgba(255, 255, 255, 0.06);
      color: #fff;
    }
    .channel-tab-btn.active.tab-maya {
      background: rgba(0, 210, 106, 0.12);
      border-color: #00d26a;
      color: #fff;
    }
    .channel-tab-btn.active.tab-gcash {
      background: rgba(41, 121, 255, 0.12);
      border-color: #2979ff;
      color: #fff;
    }
    .copy-btn {
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.15);
      color: #cbd5e1;
      border-radius: 6px;
      padding: 3px 8px;
      font-size: 0.72rem;
      cursor: pointer;
      transition: all 0.2s;
    }
    .copy-btn:hover {
      background: rgba(201,168,76,0.2);
      color: var(--gold);
      border-color: var(--gold);
    }
    .modal-file-drop {
      border: 2px dashed rgba(201, 168, 76, 0.4);
      background: rgba(201, 168, 76, 0.03);
      border-radius: 12px;
      padding: 16px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
    }
    .modal-file-drop:hover {
      background: rgba(201, 168, 76, 0.08);
      border-color: var(--gold);
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
        <div class="topbar-title">Instant Room Booking</div>
      </div>
      <div class="topbar-actions">
        <span class="badge-maya"><i class="fa-solid fa-bolt"></i> Instant Checkout Enabled</span>
      </div>
    </header>

    <div class="page-content">
      <?= renderFlash() ?>

      <?php if (!empty($error)): ?>
        <div class="flash-message flash-error"><i class="fa-solid fa-circle-xmark"></i> <span><?= h($error) ?></span></div>
      <?php endif; ?>

      <!-- Verified Identity Banner -->
      <div class="verified-banner">
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:36px;height:36px;border-radius:50%;background:rgba(34,197,94,0.18);display:flex;align-items:center;justify-content:center;color:#4ade80;font-size:1.1rem;">
            <i class="fa-solid fa-shield-check"></i>
          </div>
          <div>
            <div style="color:#fff;font-weight:700;font-size:0.95rem;">Identity Verified: <?= h($user['first_name'] . ' ' . $user['last_name']) ?></div>
            <div style="font-size:0.8rem;color:var(--text-muted);">Your account is authorized for one-click reservation and instant online payments.</div>
          </div>
        </div>
        <div>
          <span class="badge" style="background:#22c55e;color:#fff;font-size:0.75rem;padding:4px 10px;">
            <i class="fa-solid fa-circle-check"></i> Verified User
          </span>
        </div>
      </div>

      <div class="page-header">
        <div>
          <h1 class="page-header-title">Create a Reservation</h1>
          <div class="page-header-sub">Select your room and dates below. When you click proceed, your payment channels (GCash / PayMaya) will pop up instantly.</div>
        </div>
      </div>

      <div class="glass-card" style="max-width:960px;padding:32px;">
        <form action="" method="POST" id="bookingForm" enctype="multipart/form-data">
          <?= csrfField() ?>
          <input type="hidden" name="action_mode" id="actionModeInput" value="book_and_pay">
          <input type="hidden" name="payment_method" id="selectedMethodInput" value="maya">

          <!-- 1. Room Selection (Visual Room Gallery + Synced Dropdown) -->
          <div class="form-group">
            <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
              <label class="form-label" style="margin-bottom:0;">Choose a Room or Dormitory <span class="required">*</span></label>
              <span style="font-size:0.76rem;color:var(--text-muted);"><i class="fa-solid fa-images" style="color:var(--gold);"></i> Click a room card to select, or click <i class="fa-solid fa-expand"></i> to view photo</span>
            </div>

            <?php if (!empty($rooms)): ?>
              <?php
                $bkHotelCnt = count(array_filter($rooms, fn($x) => strcasecmp((string)($x['type_name'] ?? ''), 'Dormitory') !== 0));
                $bkDormCnt  = count($rooms) - $bkHotelCnt;
              ?>
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                <button type="button" class="btn btn-gold btn-sm bk-cat-btn" onclick="filterBookingPicker('all', this)">All (<?= count($rooms) ?>)</button>
                <button type="button" class="btn btn-ghost btn-sm bk-cat-btn" onclick="filterBookingPicker('hotel', this)" style="border:1px solid rgba(201,168,76,0.35);"><i class="fa-solid fa-moon" style="color:var(--gold);"></i> Hotel Rooms · Overnight (<?= $bkHotelCnt ?>)</button>
                <button type="button" class="btn btn-ghost btn-sm bk-cat-btn" onclick="filterBookingPicker('dormitory', this)" style="border:1px solid rgba(96,165,250,0.35);"><i class="fa-solid fa-building" style="color:#60a5fa;"></i> Dormitory (<?= $bkDormCnt ?>)</button>
              </div>
              <div class="booking-room-picker-grid" id="bookingRoomPickerGrid">
                <?php foreach ($rooms as $rm):
                  $rid = (int)$rm['id'];
                  $occ = $roomOccupancyMap[$rid];
                  $thumbUrl = roomThumbUrl($rm['thumbnail'] ?? null);
                  $isSelected = ($selectedRoomId === $rid);
                  $isDorm = strcasecmp((string)($rm['type_name'] ?? ''), 'Dormitory') === 0;
                ?>
                  <div class="booking-room-card<?= $isSelected ? ' selected' : '' ?>"
                       data-room-id="<?= $rid ?>"
                       data-room-cat="<?= $isDorm ? 'dormitory' : 'hotel' ?>"
                       role="button"
                       tabindex="0"
                       aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"
                       onclick="selectBookingRoom(<?= $rid ?>)"
                       onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();selectBookingRoom(<?= $rid ?>);}">
                    <div class="booking-room-card-media">
                      <img src="<?= h($thumbUrl) ?>"
                           alt="<?= h($rm['name']) ?>"
                           loading="lazy"
                           decoding="async"
                           onerror="this.src='<?= h($defaultRoomImg) ?>';this.onerror=null;">
                      <div class="booking-room-card-status"><?= $occ['tag_html'] ?></div>
                      <button type="button"
                              class="booking-room-card-zoom"
                              title="View full room photo"
                              aria-label="View full photo of <?= h($rm['name']) ?>"
                              onclick="event.stopPropagation();openRoomLightbox(<?= $rid ?>);">
                        <i class="fa-solid fa-expand"></i>
                      </button>
                      <span class="booking-room-selected-pill"><i class="fa-solid fa-check"></i> Selected</span>
                    </div>
                    <div class="booking-room-card-body">
                      <div class="booking-room-card-type"><?= $isDorm ? 'Dormitory' : 'Hotel Room · Overnight' ?> · #<?= h($rm['room_number']) ?></div>
                      <div class="booking-room-card-title"><?= h($rm['name']) ?></div>
                      <div class="booking-room-card-meta">Floor <?= (int)$rm['floor'] ?> · Max <?= (int)$rm['capacity'] ?> pax</div>
                      <div class="booking-room-card-price">₱<?= number_format((float)$rm['price_per_night'], 2) ?> <span><?= $isDorm ? '/bed·night' : '/night' ?></span></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <script>
                function filterBookingPicker(cat, btn) {
                  document.querySelectorAll('#bookingRoomPickerGrid .booking-room-card').forEach(c => {
                    c.style.display = (cat === 'all' || c.dataset.roomCat === cat) ? '' : 'none';
                  });
                  document.querySelectorAll('.bk-cat-btn').forEach(b => {
                    b.classList.remove('btn-gold');
                    b.classList.add('btn-ghost');
                  });
                  if (btn) { btn.classList.remove('btn-ghost'); btn.classList.add('btn-gold'); }
                }
              </script>
            <?php endif; ?>

            <select name="room_id" id="roomSelect" class="form-control" required onchange="onRoomOrDateChanged('room')">
              <option value="" data-price="0" data-capacity="0">-- Select Room Here --</option>
              <?php foreach ($rooms as $rm): 
                $occ = $roomOccupancyMap[$rm['id']];
                $statusSuffix = '';
                if ($occ['state'] === 'occupied') {
                    $statusSuffix = ' · [Occupied until ' . formatDate($occ['current_until'], 'M d') . ' · Advance Bookings Open]';
                } elseif ($occ['state'] === 'reserved') {
                    $statusSuffix = ' · [Reserved ' . formatDate($occ['next_booking']['check_in'], 'M d') . '–' . formatDate($occ['next_booking']['check_out'], 'M d') . ' · Other Dates Open]';
                } else {
                    $statusSuffix = ' · [Available Now]';
                }
              ?>
                <option value="<?= $rm['id'] ?>" 
                        data-price="<?= $rm['price_per_night'] ?>" 
                        data-capacity="<?= $rm['capacity'] ?>"
                        data-name="<?= htmlspecialchars($rm['name'], ENT_QUOTES) ?>"
                        data-status="<?= $occ['state'] ?>"
                        <?= $selectedRoomId === (int)$rm['id'] ? 'selected' : '' ?>>
                  <?= h($rm['name']) ?> — ₱<?= number_format($rm['price_per_night'], 2) ?>/night (Max <?= $rm['capacity'] ?> pax)<?= $statusSuffix ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Selected Room Featured Photo Preview -->
          <div class="selected-room-preview" id="selectedRoomPreview">
            <div class="selected-room-preview-media" id="selectedRoomPreviewMedia" onclick="openSelectedRoomLightbox()" title="Click to enlarge room photo">
              <img id="selectedRoomPreviewImg" src="<?= h($defaultRoomImg) ?>" alt="Selected Room Photo">
              <span class="selected-room-zoom-hint"><i class="fa-solid fa-magnifying-glass-plus"></i> View Photo</span>
            </div>
            <div class="selected-room-preview-info">
              <div class="selected-room-preview-header">
                <div>
                  <span class="badge badge-gold" id="selectedRoomPreviewType" style="font-size:0.68rem;margin-bottom:4px;display:inline-block;">Room</span>
                  <div class="selected-room-preview-name" id="selectedRoomPreviewName">--</div>
                </div>
                <div class="selected-room-preview-price" id="selectedRoomPreviewPrice">₱0.00/night</div>
              </div>
              <div class="selected-room-preview-meta" id="selectedRoomPreviewMeta"></div>
              <p class="selected-room-preview-desc" id="selectedRoomPreviewDesc"></p>
              <div class="selected-room-preview-amenities" id="selectedRoomPreviewAmenities"></div>
            </div>
          </div>

          <!-- Real-Time Room Schedule & Reserved Dates Display -->
          <div id="roomScheduleContainer" style="display:none;margin-top:12px;margin-bottom:16px;border-radius:12px;padding:14px 18px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-calendar-days" style="color:var(--gold);"></i>
                <span style="font-weight:700;color:#fff;font-size:0.88rem;" id="scheduleHeaderTitle">Room Availability Schedule</span>
              </div>
              <div id="scheduleBadgeContainer"></div>
            </div>
            
            <div id="scheduleDetailsText" style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px;"></div>
            
            <!-- List of already booked dates -->
            <div id="bookedDatesChips" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
          </div>

          <!-- 2. Stay Dates -->
          <?php
            $prefillCheckIn  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['check_in'] ?? '') ? $_GET['check_in'] : '';
            $prefillCheckOut = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['check_out'] ?? '') ? $_GET['check_out'] : '';
          ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Check-in Date <span class="required">*</span></label>
              <input type="date" name="check_in" id="checkInDate" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= h($prefillCheckIn) ?>" required onchange="onRoomOrDateChanged('check_in')">
            </div>
            <div class="form-group">
              <label class="form-label">Check-out Date <span class="required">*</span></label>
              <input type="date" name="check_out" id="checkOutDate" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= h($prefillCheckOut) ?>" required onchange="onRoomOrDateChanged('check_out')">
            </div>
          </div>

          <!-- Live Slim 4 REST API Availability Verification (Tailwind CSS + Alpine.js) -->
          <div x-data="liveApiAvailabilityChecker()" x-init="init()" x-cloak class="mt-2 mb-3">
            <div x-show="state === 'checking'" class="flex items-center gap-2 rounded-xl border border-gold/30 bg-gold/10 px-4 py-2.5 text-xs text-gold-light">
              <i class="fa-solid fa-spinner fa-spin"></i>
              <span>Verifying real-time availability via Slim 4 REST API...</span>
            </div>
            <div x-show="state === 'available'" class="flex items-center justify-between flex-wrap gap-2 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2.5 text-xs text-emerald-300">
              <div class="flex items-center gap-2 font-semibold">
                <i class="fa-solid fa-shield-check text-emerald-400"></i>
                <span x-text="message"></span>
              </div>
              <span class="rounded-full bg-emerald-500/20 px-2.5 py-0.5 font-mono text-[11px] text-emerald-200" x-text="quoteLabel"></span>
            </div>
            <div x-show="state === 'unavailable'" class="flex items-center gap-2 rounded-xl border border-rose-500/45 bg-rose-500/15 px-4 py-2.5 text-xs text-rose-200 font-medium">
              <i class="fa-solid fa-circle-xmark text-rose-400"></i>
              <span x-text="message"></span>
            </div>
          </div>

          <!-- Real-Time Overlap / Duplicate Conflict Alert -->
          <div id="dateConflictAlert" style="display:none;background:rgba(239,68,68,0.16);border:1px solid rgba(239,68,68,0.5);border-radius:12px;padding:14px 18px;margin-top:10px;margin-bottom:14px;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
              <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;font-size:1.3rem;margin-top:2px;"></i>
              <div style="flex:1;">
                <div style="color:#fecaca;font-weight:700;font-size:0.95rem;" id="conflictAlertTitle">Duplicate Booking Conflict Detected!</div>
                <div style="color:#cbd5e1;font-size:0.84rem;margin-top:4px;" id="conflictAlertDesc">
                  This room is already reserved on the selected dates. Please adjust your dates or choose another room to prevent duplicate booking.
                </div>
              </div>
            </div>
          </div>

          <!-- 3. Pax Count -->
          <div class="form-group">
            <label class="form-label">Number of Pax (Persons)</label>
            <input type="number" name="guests" id="guestsCount" class="form-control" value="1" min="1" max="10">
          </div>

          <!-- 4. Special Requests -->
          <div class="form-group">
            <label class="form-label">Special Requests (Optional)</label>
            <textarea name="special_requests" id="specialRequests" class="form-control" rows="2" placeholder="e.g. Quiet corner, late check-in, extra pillows..."></textarea>
          </div>

          <!-- 5. Dynamic Live Stay & Payment Calculator Summary -->
          <div class="calc-summary-card" id="calcSummaryBox">
            <div style="display:flex;align-items:center;gap:14px;">
              <img id="summaryRoomThumb" src="<?= h($defaultRoomImg) ?>" alt="Selected room thumbnail" class="booking-thumb-md" style="display:none;cursor:pointer;" onclick="openSelectedRoomLightbox()" title="Click to enlarge room photo">
              <div>
                <div style="font-size:0.75rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.5px;">Stay Summary</div>
                <div style="color:#fff;font-weight:600;font-size:0.95rem;margin-top:2px;" id="summaryRoomName">Select a room and stay dates</div>
                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;" id="summaryDatesText">0 nights · Rate: ₱0.00/night</div>
              </div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:0.75rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.5px;">Estimated Total</div>
              <div style="color:var(--gold);font-size:1.6rem;font-weight:700;line-height:1.2;" id="summaryTotalText">₱0.00</div>
              <div style="font-size:0.72rem;color:#4ade80;"><i class="fa-solid fa-lock"></i> Verified Instant Checkout</div>
            </div>
          </div>

          <!-- Primary Pop-up Trigger Button -->
          <button type="button" class="btn btn-gold btn-block" id="bookSubmitBtn" onclick="openPaymentModal()" style="margin-top:22px;padding:15px;font-size:1.05rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:10px;">
            <i class="fa-solid fa-qrcode"></i> Click to Book &amp; Open Payment Methods (GCash / Maya)
          </button>

          <!-- Hidden Modal Elements that will submit with the form -->
          <div id="hiddenModalFormInputs" style="display:none;">
            <input type="text" name="reference_number" id="hiddenRefNum">
            <input type="file" name="proof" id="hiddenProofFile">
            <textarea name="payment_notes" id="hiddenPaymentNotes"></textarea>
          </div>
        </form>
      </div>

    </div>
  </main>
</div>

<!-- ======================================================== -->
<!-- Pop-up Modal: Payment Methods & Instant Checkout         -->
<!-- ======================================================== -->
<div class="payment-modal-overlay" id="paymentCheckoutModal" onclick="closePaymentModal(event)">
  <div class="payment-modal-card" onclick="event.stopPropagation()">
    
    <!-- Modal Header -->
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;justify-content:space-between;align-items:center;">
      <div>
        <div style="display:flex;align-items:center;gap:8px;">
          <h3 style="color:#fff;font-size:1.25rem;font-weight:700;margin:0;">Confirm Booking &amp; Pay</h3>
          <span class="badge" style="background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);font-size:0.7rem;">Verified</span>
        </div>
        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;">Settle via official hotel accounts below to secure your room.</div>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closePaymentModal()" style="padding:4px 8px;font-size:1.2rem;color:#94a3b8;">&times;</button>
    </div>

    <div style="padding:24px;max-height:calc(90vh - 140px);overflow-y:auto;">
      
      <!-- Summary Bar in Modal -->
      <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;">
          <img id="modalRoomThumb" src="<?= h($defaultRoomImg) ?>" alt="Room thumbnail" class="booking-thumb-md" style="display:none;">
          <div>
            <div style="font-size:0.75rem;text-transform:uppercase;color:var(--text-muted);">Assigned Room</div>
            <div style="color:#fff;font-weight:700;font-size:1.05rem;" id="modalRoomName">--</div>
            <div style="font-size:0.8rem;color:var(--text-muted);" id="modalStayDates">--</div>
          </div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:0.75rem;text-transform:uppercase;color:var(--text-muted);">Amount to Pay</div>
          <div style="color:var(--gold);font-size:1.6rem;font-weight:700;" id="modalTotalAmount">₱0.00</div>
        </div>
      </div>

      <!-- Payment Channel Selector Tabs -->
      <div style="margin-bottom:18px;">
        <label class="form-label" style="font-size:0.85rem;margin-bottom:8px;">Choose Payment Channel:</label>
        <div style="display:flex;gap:12px;">
          <button type="button" class="channel-tab-btn active tab-maya" id="tabMayaBtn" onclick="switchModalChannel('maya')">
            <i class="fa-solid fa-qrcode" style="font-size:1.2rem;color:#00e074;"></i>
            <div style="text-align:left;">
              <div style="font-weight:600;font-size:0.9rem;">PayMaya (Maya) QR</div>
              <div style="font-size:0.72rem;opacity:0.8;">InstaPay / QR Ph Scannable</div>
            </div>
          </button>
          <button type="button" class="channel-tab-btn" id="tabGcashBtn" onclick="switchModalChannel('gcash')">
            <i class="fa-solid fa-mobile-screen-button" style="font-size:1.2rem;color:#2979ff;"></i>
            <div style="text-align:left;">
              <div style="font-weight:600;font-size:0.9rem;">GCash Express</div>
              <div style="font-size:0.72rem;opacity:0.8;">Direct Send / Scan QR</div>
            </div>
          </button>
        </div>
      </div>

      <!-- Channel Details: Maya -->
      <div id="modalMayaDetails" style="background:rgba(0,210,106,0.04);border:1px solid rgba(0,210,106,0.25);border-radius:12px;padding:16px;margin-bottom:20px;">
        <div class="modal-qr-grid" style="display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:center;">
          <div style="text-align:center;">
            <div style="background:#fff;padding:8px;border-radius:10px;display:inline-block;">
              <img src="<?= h($mayaQrUrl) ?>" alt="Maya QR" style="width:130px;height:130px;object-fit:cover;display:block;border-radius:4px;">
            </div>
            <div style="margin-top:6px;">
              <a href="<?= h($mayaQrUrl) ?>" download="Marvelous_Hotel_Maya_QR.jpg" class="btn btn-ghost btn-sm" style="font-size:0.7rem;padding:2px 8px;">
                <i class="fa-solid fa-download"></i> Save QR
              </a>
            </div>
          </div>
          <div>
            <div style="font-size:0.75rem;text-transform:uppercase;color:var(--gold);font-weight:700;margin-bottom:6px;">
              <i class="fa-solid fa-id-card"></i> PayMaya (Maya) Official Account
            </div>
            <div style="font-size:0.85rem;color:#fff;margin-bottom:4px;">
              Account: <strong><?= h($mayaName) ?></strong> 
              <button type="button" class="copy-btn" onclick="copyModalText('<?= h($mayaName) ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
            </div>
            <div style="font-size:0.85rem;color:#00e074;margin-bottom:4px;">
              Handle: <strong><?= h($mayaHandle) ?></strong> 
              <button type="button" class="copy-btn" onclick="copyModalText('<?= h($mayaHandle) ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
            </div>
            <div style="font-size:0.8rem;color:#cbd5e1;">Mobile: <?= h($mayaPhone) ?></div>
            <div style="font-size:0.75rem;color:#94a3b8;margin-top:6px;line-height:1.4;">
              <i class="fa-solid fa-lightbulb" style="color:#00e074;"></i> <strong>Gamit mo ba ay GCash o Bank App?</strong> Buksan ang inyong app, piliin ang <em>'Scan QR'</em>, at itapat dito!
            </div>
          </div>
        </div>
      </div>

      <!-- Channel Details: GCash -->
      <div id="modalGcashDetails" style="display:none;background:rgba(41,121,255,0.04);border:1px solid rgba(41,121,255,0.25);border-radius:12px;padding:16px;margin-bottom:20px;">
        <div class="modal-qr-grid" style="display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:center;">
          <div style="text-align:center;">
            <div style="background:#fff;padding:8px;border-radius:10px;display:inline-block;">
              <img src="<?= h($gcashQrUrl) ?>" alt="GCash QR" style="width:130px;height:130px;object-fit:cover;display:block;border-radius:4px;">
            </div>
            <div style="margin-top:6px;">
              <a href="<?= h($gcashQrFull) ?>" download="Marvelous_Hotel_GCash_QR.jpg" class="btn btn-ghost btn-sm" style="font-size:0.7rem;padding:2px 8px;">
                <i class="fa-solid fa-download"></i> Save QR
              </a>
            </div>
          </div>
          <div>
            <div style="font-size:0.75rem;text-transform:uppercase;color:#64b5f6;font-weight:700;margin-bottom:6px;">
              <i class="fa-solid fa-qrcode"></i> GCash Official Account Details
            </div>
            <div style="font-size:0.85rem;color:#fff;margin-bottom:4px;">
              Account: <strong><?= h($gcashName) ?></strong> 
              <button type="button" class="copy-btn" onclick="copyModalText('<?= h($gcashName) ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
            </div>
            <div style="font-size:0.85rem;color:#64b5f6;margin-bottom:4px;">
              Mobile: <strong><?= h($gcashPhone) ?></strong> 
              <button type="button" class="copy-btn" onclick="copyModalText('<?= h($gcashPhone) ?>', this)"><i class="fa-regular fa-copy"></i> Copy</button>
            </div>
            <div style="font-size:0.8rem;color:#cbd5e1;">User ID: <?= h($gcashUserId) ?></div>
            <div style="font-size:0.75rem;color:#94a3b8;margin-top:6px;line-height:1.4;">
              <i class="fa-solid fa-circle-info" style="color:#2979ff;"></i> I-scan ang QR code sa kaliwa gamit ang inyong <strong>GCash App</strong> o mag-send via <em>Express Send</em>.
            </div>
          </div>
        </div>
      </div>

      <!-- Step 2: Input Reference & Upload Receipt in Modal -->
      <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:16px;">
        <div style="font-weight:700;color:#fff;font-size:0.95rem;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-receipt" style="color:var(--gold);"></i> Submit Payment Details Now:
        </div>

        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label" style="font-size:0.82rem;">Reference Number / Transaction ID <span class="required">*</span></label>
          <input type="text" id="modalRefInput" class="form-control" placeholder="Halimbawa: 100293848123 mula sa resibo">
        </div>

        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label" style="font-size:0.82rem;">Upload Receipt Screenshot (Proof of Payment) <span class="required">*</span></label>
          <div class="modal-file-drop" onclick="document.getElementById('modalFileInput').click()">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.6rem;color:var(--gold);margin-bottom:4px;"></i>
            <div style="font-size:0.85rem;font-weight:600;color:#fff;" id="modalFileLabel">Click to select receipt screenshot</div>
            <div style="font-size:0.72rem;color:var(--text-muted);">Supports JPG, PNG, or WebP (Max 15MB)</div>
            <div id="modalPreviewContainer" style="display:none;margin-top:10px;">
              <img id="modalPreviewImg" src="#" alt="Receipt" style="max-height:120px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);">
            </div>
          </div>
          <input type="file" id="modalFileInput" accept="image/*" style="display:none;" onchange="previewModalReceipt(this)">
        </div>

        <div class="form-group" style="margin-bottom:16px;">
          <label class="form-label" style="font-size:0.82rem;">Additional Notes (Optional)</label>
          <input type="text" id="modalNotesInput" class="form-control" placeholder="Hal. Pangalan ng account na nagbayad...">
        </div>
      </div>

    </div>

    <!-- Modal Footer Actions -->
    <div style="padding:16px 24px;border-top:1px solid rgba(255,255,255,0.08);background:rgba(0,0,0,0.25);">
      <button type="button" class="btn btn-gold btn-block" onclick="submitModalCheckout('book_and_pay')" style="padding:13px;font-size:0.95rem;font-weight:700;">
        <i class="fa-solid fa-circle-check"></i> Confirm Booking &amp; Submit Payment
      </button>

      <button type="button" class="btn btn-ghost btn-block" onclick="submitModalCheckout('book_only')" style="margin-top:8px;font-size:0.82rem;color:var(--text-muted);padding:8px;">
        <i class="fa-solid fa-clock"></i> Reserve Room Now &amp; Pay Later (Upload Proof Later)
      </button>
    </div>

  </div>
</div>

<!-- Room Photo Lightbox Modal -->
<div class="room-lightbox-overlay" id="roomImageLightbox" onclick="closeRoomLightbox(event)">
  <div class="room-lightbox-card" onclick="event.stopPropagation()">
    <div class="room-lightbox-img-wrap">
      <img id="roomLightboxImg" src="<?= h($defaultRoomImg) ?>" alt="Room Photo">
      <button type="button" class="room-lightbox-close" onclick="closeRoomLightbox()" aria-label="Close photo preview">&times;</button>
    </div>
    <div class="room-lightbox-footer">
      <div>
        <div style="font-family:var(--font-serif);font-size:1.15rem;font-weight:700;color:#fff;" id="roomLightboxTitle">--</div>
        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;" id="roomLightboxMeta">--</div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="color:var(--gold);font-weight:700;font-size:1.05rem;" id="roomLightboxPrice">₱0.00/night</div>
        <button type="button" class="btn btn-gold btn-sm" id="roomLightboxSelectBtn" onclick="selectRoomFromLightbox()">
          <i class="fa-solid fa-check"></i> Select Room
        </button>
      </div>
    </div>
  </div>
</div>

<script src="../frontend/assets/js/core.js"></script>
<script src="../frontend/assets/js/user.js"></script>
<script>
let activeChannel = 'maya';

function switchModalChannel(channel) {
  activeChannel = channel;
  document.getElementById('selectedMethodInput').value = channel;
  
  const tabMaya = document.getElementById('tabMayaBtn');
  const tabGcash = document.getElementById('tabGcashBtn');
  const mayaDetails = document.getElementById('modalMayaDetails');
  const gcashDetails = document.getElementById('modalGcashDetails');

  if (channel === 'maya') {
    tabMaya.classList.add('active', 'tab-maya');
    tabGcash.classList.remove('active', 'tab-gcash');
    mayaDetails.style.display = 'block';
    gcashDetails.style.display = 'none';
  } else {
    tabGcash.classList.add('active', 'tab-gcash');
    tabMaya.classList.remove('active', 'tab-maya');
    mayaDetails.style.display = 'none';
    gcashDetails.style.display = 'block';
  }
}

// Global data for room availability, room visuals & anti-duplicate schedule checking
window.ROOM_ACTIVE_BOOKINGS = <?= json_encode($allBookingsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.BOOKING_ROOMS_DATA = <?= json_encode($roomsClientMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.DEFAULT_ROOM_IMG = <?= json_encode($defaultRoomImg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.TODAY_DATE = "<?= date('Y-m-d') ?>";

let lightboxRoomId = null;

function getRoomData(roomId) {
  if (!roomId || !window.BOOKING_ROOMS_DATA) return null;
  return window.BOOKING_ROOMS_DATA[String(roomId)] || window.BOOKING_ROOMS_DATA[Number(roomId)] || null;
}

function setImgWithFallback(imgEl, url, altText) {
  if (!imgEl) return;
  const fallback = window.DEFAULT_ROOM_IMG || '../frontend/assets/images/rooms/default-room.jpg';
  imgEl.onerror = function() {
    this.onerror = null;
    this.src = fallback;
  };
  imgEl.src = url || fallback;
  if (altText !== undefined) {
    imgEl.alt = altText;
  }
}

function selectBookingRoom(roomId) {
  const roomSelect = document.getElementById('roomSelect');
  if (!roomSelect) return;
  const targetVal = String(roomId);
  if (roomSelect.value !== targetVal) {
    roomSelect.value = targetVal;
    roomSelect.dispatchEvent(new Event('change', { bubbles: true }));
  } else {
    syncSelectedRoomVisuals();
  }
}

function syncSelectedRoomVisuals() {
  const roomSelect = document.getElementById('roomSelect');
  const previewEl = document.getElementById('selectedRoomPreview');
  const summaryThumb = document.getElementById('summaryRoomThumb');
  const guestsInput = document.getElementById('guestsCount');
  const selectedId = roomSelect ? roomSelect.value : '';
  const room = getRoomData(selectedId);

  // Sync card selection state
  document.querySelectorAll('#bookingRoomPickerGrid .booking-room-card').forEach((card) => {
    const isMatch = selectedId && card.getAttribute('data-room-id') === String(selectedId);
    card.classList.toggle('selected', Boolean(isMatch));
    card.setAttribute('aria-pressed', isMatch ? 'true' : 'false');
  });

  if (!room) {
    if (previewEl) previewEl.classList.remove('visible');
    if (summaryThumb) summaryThumb.style.display = 'none';
    if (guestsInput) guestsInput.max = '10';
    return;
  }

  if (previewEl) {
    previewEl.classList.add('visible');
    const previewImg = document.getElementById('selectedRoomPreviewImg');
    const typeEl = document.getElementById('selectedRoomPreviewType');
    const nameEl = document.getElementById('selectedRoomPreviewName');
    const priceEl = document.getElementById('selectedRoomPreviewPrice');
    const metaEl = document.getElementById('selectedRoomPreviewMeta');
    const descEl = document.getElementById('selectedRoomPreviewDesc');
    const amenitiesEl = document.getElementById('selectedRoomPreviewAmenities');

    setImgWithFallback(previewImg, room.thumbnail_url, room.name);
    if (typeEl) typeEl.textContent = room.type_name || 'Room';
    if (nameEl) nameEl.textContent = room.name;
    if (priceEl) {
      priceEl.textContent = '₱' + Number(room.price_per_night || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '/night';
    }
    if (metaEl) {
      metaEl.innerHTML = '';
      const metaItems = [
        { icon: 'fa-door-closed', text: 'Room #' + (room.room_number || '-') },
        { icon: 'fa-layer-group', text: 'Floor ' + (room.floor || 1) },
        { icon: 'fa-users', text: 'Max ' + (room.capacity || 1) + ' pax' }
      ];
      metaItems.forEach((m) => {
        const span = document.createElement('span');
        span.innerHTML = '<i class="fa-solid ' + m.icon + '" style="color:var(--gold);margin-right:4px;"></i>';
        span.appendChild(document.createTextNode(m.text));
        metaEl.appendChild(span);
      });
    }
    if (descEl) {
      descEl.textContent = room.description || 'Comfortable accommodation ready for your stay.';
    }
    if (amenitiesEl) {
      amenitiesEl.innerHTML = '';
      const list = Array.isArray(room.amenities) ? room.amenities.slice(0, 6) : [];
      list.forEach((am) => {
        const chip = document.createElement('span');
        chip.className = 'room-amenity-tag';
        chip.innerHTML = '<i class="fa-solid fa-check" style="color:var(--gold);font-size:0.62rem;"></i>';
        chip.appendChild(document.createTextNode(' ' + String(am)));
        amenitiesEl.appendChild(chip);
      });
    }
  }

  if (summaryThumb) {
    setImgWithFallback(summaryThumb, room.thumbnail_url, room.name);
    summaryThumb.style.display = 'block';
  }

  if (guestsInput && room.capacity > 0) {
    guestsInput.max = String(room.capacity);
    if (parseInt(guestsInput.value, 10) > room.capacity) {
      guestsInput.value = String(room.capacity);
    }
  }
}

function openRoomLightbox(roomId) {
  const room = getRoomData(roomId);
  if (!room) return;
  lightboxRoomId = room.id;

  setImgWithFallback(document.getElementById('roomLightboxImg'), room.thumbnail_url, room.name);
  document.getElementById('roomLightboxTitle').textContent = room.name;
  document.getElementById('roomLightboxMeta').textContent =
    (room.type_name || 'Room') + ' · Room #' + (room.room_number || '-') + ' · Floor ' + (room.floor || 1) + ' · Max ' + (room.capacity || 1) + ' pax';
  document.getElementById('roomLightboxPrice').textContent =
    '₱' + Number(room.price_per_night || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '/night';

  const selectBtn = document.getElementById('roomLightboxSelectBtn');
  const currentSelected = document.getElementById('roomSelect')?.value || '';
  if (selectBtn) {
    if (String(currentSelected) === String(room.id)) {
      selectBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Selected';
    } else {
      selectBtn.innerHTML = '<i class="fa-solid fa-check"></i> Select Room';
    }
  }

  document.getElementById('roomImageLightbox').classList.add('active');
}

function openSelectedRoomLightbox() {
  const roomId = document.getElementById('roomSelect')?.value;
  if (roomId) openRoomLightbox(roomId);
}

function selectRoomFromLightbox() {
  if (lightboxRoomId) {
    selectBookingRoom(lightboxRoomId);
  }
  closeRoomLightbox();
}

function closeRoomLightbox(e) {
  document.getElementById('roomImageLightbox')?.classList.remove('active');
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeRoomLightbox();
  }
});

function onRoomOrDateChanged(triggerSource) {
  const checkInInput = document.getElementById('checkInDate');
  const checkOutInput = document.getElementById('checkOutDate');

  // If check-in date changed, advance check-out min date by at least 1 day
  if (triggerSource === 'check_in' && checkInInput.value) {
    const d = new Date(checkInInput.value + 'T00:00:00');
    d.setDate(d.getDate() + 1);
    const minOut = d.toISOString().split('T')[0];
    checkOutInput.min = minOut;
    if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
      checkOutInput.value = minOut;
    }
  }

  syncSelectedRoomVisuals();
  updatePriceCalculator();
  checkRoomScheduleAndConflict();
}

function checkRoomScheduleAndConflict() {
  const roomSelect     = document.getElementById('roomSelect');
  const checkInInput   = document.getElementById('checkInDate');
  const checkOutInput  = document.getElementById('checkOutDate');
  const scheduleBox    = document.getElementById('roomScheduleContainer');
  const conflictAlert  = document.getElementById('dateConflictAlert');
  const conflictDesc   = document.getElementById('conflictAlertDesc');
  const submitBtn      = document.getElementById('bookSubmitBtn');

  const roomId = roomSelect.value;
  if (!roomId) {
    if (scheduleBox) scheduleBox.style.display = 'none';
    if (conflictAlert) conflictAlert.style.display = 'none';
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Click to Book &amp; Open Payment Methods (GCash / Maya)';
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
      submitBtn.style.pointerEvents = 'auto';
    }
    return false;
  }

  const selectedOption = roomSelect.options[roomSelect.selectedIndex];
  const roomName = selectedOption.getAttribute('data-name') || selectedOption.text;
  const bookings = (window.ROOM_ACTIVE_BOOKINGS && window.ROOM_ACTIVE_BOOKINGS[roomId]) ? window.ROOM_ACTIVE_BOOKINGS[roomId] : [];
  const today = window.TODAY_DATE || new Date().toISOString().split('T')[0];

  // 1. Render Room Schedule & Reserved Dates Display Box
  if (scheduleBox) {
    scheduleBox.style.display = 'block';
    const headerTitle     = document.getElementById('scheduleHeaderTitle');
    const badgeContainer  = document.getElementById('scheduleBadgeContainer');
    const detailsText     = document.getElementById('scheduleDetailsText');
    const chipsContainer  = document.getElementById('bookedDatesChips');

    if (headerTitle) headerTitle.textContent = 'Room Schedule: ' + roomName;

    if (bookings.length === 0) {
      badgeContainer.innerHTML = '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Available - No Reserved Dates</span>';
      detailsText.innerHTML = 'This room has no upcoming reservations on file. All dates are available for instant booking!';
      chipsContainer.innerHTML = '<span style="font-size:0.8rem;color:#4ade80;"><i class="fa-solid fa-check"></i> 100% Free &amp; Open</span>';
    } else {
      const isOccupiedToday = bookings.some(b => b.check_in <= today && b.check_out > today);
      if (isOccupiedToday) {
        badgeContainer.innerHTML = '<span class="badge badge-danger"><i class="fa-solid fa-bed"></i> Currently Booked / Occupied</span>';
        detailsText.innerHTML = '⚠️ This room is currently occupied by another guest today. You can still reserve it for future dates outside of these booked periods:';
      } else {
        badgeContainer.innerHTML = '<span class="badge badge-warning" style="background:rgba(234,179,8,0.22);color:#fde047;border:1px solid rgba(234,179,8,0.4);"><i class="fa-solid fa-calendar-check"></i> Has Reserved Dates</span>';
        detailsText.innerHTML = 'This room has active bookings on file. <strong>Please select dates that do NOT overlap with the reserved periods below:</strong>';
      }

      let chips = '';
      bookings.forEach(b => {
        chips += `<div style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.35);color:#fca5a5;border-radius:8px;padding:5px 10px;font-size:0.78rem;display:inline-flex;align-items:center;gap:6px;">
          <i class="fa-solid fa-calendar-xmark" style="color:#f87171;"></i>
          <strong>${b.display_dates}</strong>
          <span style="opacity:0.75;font-size:0.72rem;">(#${b.booking_code})</span>
        </div>`;
      });
      chipsContainer.innerHTML = chips;
    }
  }

  // 2. Real-time Date Conflict Check
  const checkIn  = checkInInput.value;
  const checkOut = checkOutInput.value;

  if (checkIn && checkOut && bookings.length > 0) {
    let conflict = null;
    for (let i = 0; i < bookings.length; i++) {
      const b = bookings[i];
      // Interval overlap: (checkIn < b.check_out && checkOut > b.check_in)
      if (checkIn < b.check_out && checkOut > b.check_in) {
        conflict = b;
        break;
      }
    }

    if (conflict) {
      if (conflictAlert) {
        conflictAlert.style.display = 'block';
        conflictDesc.innerHTML = 'Room <strong>' + roomName + '</strong> is already reserved on <strong>' + conflict.display_dates + '</strong> (Booking #' + conflict.booking_code + '). Please pick different dates to prevent duplicate booking.';
      }
      checkInInput.style.borderColor = '#ef4444';
      checkOutInput.style.borderColor = '#ef4444';

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-ban"></i> Dates Unavailable (Already Booked)';
        submitBtn.style.opacity = '0.55';
        submitBtn.style.cursor = 'not-allowed';
        submitBtn.style.pointerEvents = 'none';
      }
      return true; // conflict detected!
    }
  }

  // No conflict detected
  if (conflictAlert) conflictAlert.style.display = 'none';
  checkInInput.style.borderColor = '';
  checkOutInput.style.borderColor = '';

  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Click to Book &amp; Open Payment Methods (GCash / Maya)';
    submitBtn.style.opacity = '1';
    submitBtn.style.cursor = 'pointer';
    submitBtn.style.pointerEvents = 'auto';
  }
  return false;
}

function updatePriceCalculator() {
  const roomSelect = document.getElementById('roomSelect');
  const checkInInput = document.getElementById('checkInDate');
  const checkOutInput = document.getElementById('checkOutDate');
  
  const summaryRoomName = document.getElementById('summaryRoomName');
  const summaryDatesText = document.getElementById('summaryDatesText');
  const summaryTotalText = document.getElementById('summaryTotalText');

  const selectedOption = roomSelect.options[roomSelect.selectedIndex];
  const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
  const roomName = selectedOption.getAttribute('data-name') || selectedOption.text;

  const checkInVal = checkInInput.value;
  const checkOutVal = checkOutInput.value;

  if (checkInVal && checkOutVal) {
    const d1 = new Date(checkInVal + 'T00:00:00');
    const d2 = new Date(checkOutVal + 'T00:00:00');
    const diffTime = d2.getTime() - d1.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays > 0 && price > 0) {
      const total = diffDays * price;
      summaryRoomName.textContent = roomName;
      summaryDatesText.textContent = diffDays + (diffDays === 1 ? ' night' : ' nights') + ' · Rate: ₱' + price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '/night';
      summaryTotalText.textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      return;
    }
  }

  if (price > 0) {
    summaryRoomName.textContent = roomName;
    summaryDatesText.textContent = 'Select check-in & check-out dates · Rate: ₱' + price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '/night';
  } else {
    summaryRoomName.textContent = 'Select a room and stay dates';
    summaryDatesText.textContent = '0 nights · Rate: ₱0.00/night';
  }
  summaryTotalText.textContent = '₱0.00';
}

function openPaymentModal() {
  const roomSelect = document.getElementById('roomSelect');
  const checkIn = document.getElementById('checkInDate').value;
  const checkOut = document.getElementById('checkOutDate').value;

  if (!roomSelect.value) {
    alert('Please choose a room first before proceeding to payment.');
    roomSelect.focus();
    return;
  }
  if (!checkIn || !checkOut) {
    alert('Please select both your check-in and check-out dates.');
    return;
  }
  if (checkIn >= checkOut) {
    alert('Your check-out date must be later than your check-in date.');
    return;
  }

  // Prevent opening modal if conflict exists
  if (checkRoomScheduleAndConflict()) {
    alert('Cannot proceed: The room is already booked on your selected dates. Please choose different dates to prevent duplicate booking.');
    return;
  }

  // Populate Modal Summary
  const selectedOption = roomSelect.options[roomSelect.selectedIndex];
  const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
  const roomName = selectedOption.getAttribute('data-name') || selectedOption.text;

  const d1 = new Date(checkIn + 'T00:00:00');
  const d2 = new Date(checkOut + 'T00:00:00');
  const diffDays = Math.ceil((d2.getTime() - d1.getTime()) / (1000 * 60 * 60 * 24));
  const total = diffDays * price;

  document.getElementById('modalRoomName').textContent = roomName;
  document.getElementById('modalStayDates').textContent = checkIn + ' to ' + checkOut + ' (' + diffDays + (diffDays === 1 ? ' night' : ' nights') + ')';
  document.getElementById('modalTotalAmount').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

  const modalThumb = document.getElementById('modalRoomThumb');
  const selectedRoomData = getRoomData(roomSelect.value);
  if (modalThumb && selectedRoomData) {
    setImgWithFallback(modalThumb, selectedRoomData.thumbnail_url, selectedRoomData.name);
    modalThumb.style.display = 'block';
  } else if (modalThumb) {
    modalThumb.style.display = 'none';
  }

  // Open the pop-up modal
  document.getElementById('paymentCheckoutModal').classList.add('active');
}

function closePaymentModal(e) {
  document.getElementById('paymentCheckoutModal').classList.remove('active');
}

function copyModalText(text, btn) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="fa-solid fa-check" style="color:#00e074;"></i> Copied!';
      setTimeout(() => btn.innerHTML = orig, 1800);
    });
  } else {
    const temp = document.createElement('input');
    temp.value = text;
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    document.body.removeChild(temp);
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check" style="color:#00e074;"></i> Copied!';
    setTimeout(() => btn.innerHTML = orig, 1800);
  }
}

function previewModalReceipt(input) {
  if (input.files && input.files[0]) {
    const syncReceipt = (fileToSync) => {
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('modalPreviewImg').src = e.target.result;
        document.getElementById('modalPreviewContainer').style.display = 'block';
        document.getElementById('modalFileLabel').innerHTML = '<i class="fa-solid fa-check-circle" style="color:#00e074;"></i> ' + escapeHtml(fileToSync.name);
      };
      reader.readAsDataURL(fileToSync);
      document.getElementById('hiddenProofFile').files = input.files;
    };

    if (typeof optimizeImageInput === 'function') {
      optimizeImageInput(input, { maxDim: 1400, minBytes: 300 * 1024, quality: 0.85, onDone: syncReceipt });
    } else {
      syncReceipt(input.files[0]);
    }
  }
}

function submitModalCheckout(mode) {
  document.getElementById('actionModeInput').value = mode;

  if (mode === 'book_and_pay') {
    const refNum = document.getElementById('modalRefInput').value.trim();
    const modalFile = document.getElementById('modalFileInput');

    if (!refNum) {
      alert('Please enter your payment reference number / transaction ID.');
      document.getElementById('modalRefInput').focus();
      return;
    }
    if (!modalFile.files || modalFile.files.length === 0) {
      alert('Please upload a screenshot or photo of your payment receipt.');
      return;
    }

    // Sync inputs into main form
    document.getElementById('hiddenRefNum').value = refNum;
    document.getElementById('hiddenPaymentNotes').value = document.getElementById('modalNotesInput').value.trim();
    document.getElementById('hiddenProofFile').files = modalFile.files;
  }

  // Submit the form
  document.getElementById('bookingForm').submit();
}

function liveApiAvailabilityChecker() {
  return {
    state: 'idle',
    message: '',
    quoteLabel: '',
    controller: null,
    init() {
      const bind = () => this.check();
      ['roomSelect', 'checkInDate', 'checkOutDate'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', bind);
      });
      this.check();
    },
    async check() {
      const roomId = document.getElementById('roomSelect')?.value || '';
      const checkIn = document.getElementById('checkInDate')?.value || '';
      const checkOut = document.getElementById('checkOutDate')?.value || '';
      if (!roomId || !checkIn || !checkOut || checkIn >= checkOut || !window.HotelAPI) {
        this.state = 'idle';
        return;
      }
      if (this.controller) this.controller.abort();
      this.controller = new AbortController();
      this.state = 'checking';
      try {
        const res = await window.HotelAPI.checkAvailability(roomId, checkIn, checkOut, this.controller.signal);
        const d = res.data || {};
        if (d.available) {
          this.state = 'available';
          this.message = 'Live API Verified: ' + (d.reason || 'Room is open for these dates.');
          this.quoteLabel = d.quote ? `${d.quote.nights} night(s) · ${d.quote.formatted_total}` : '';
        } else {
          this.state = 'unavailable';
          this.message = d.reason || 'Room is unavailable on the selected dates.';
          this.quoteLabel = '';
        }
      } catch (e) {
        if (e && e.name === 'AbortError') return;
        this.state = 'idle';
      }
    }
  };
}

document.addEventListener('DOMContentLoaded', function() {
  onRoomOrDateChanged();
});
</script>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
</body>
</html>
