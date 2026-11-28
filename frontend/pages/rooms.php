<?php
require_once __DIR__ . '/../../backend/core/config.php';
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/session.php';
require_once __DIR__ . '/../../backend/core/functions.php';

// MAIN WEBSITE GATE: dapat naka-login (register/login) bago makapasok
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/welcome.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/hotel/rooms.php'));
    exit;
}

releaseReadOnlySession();

throttleStaleBookingSweep(24, 15);

$hotelName = getSetting('hotel_name', 'Marvelous Hotel');

// Filter inputs (including homepage Quick Search params: check_in, check_out, guests)
$categoryFilter = strtolower(trim($_GET['category'] ?? ''));
$typeFilter = $_GET['type'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');
$checkInFilter = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['check_in'] ?? '')) ? (string)$_GET['check_in'] : '';
$checkOutFilter = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['check_out'] ?? '')) ? (string)$_GET['check_out'] : '';
$guestsFilter = max(0, (int)($_GET['guests'] ?? 0));
$hasValidDateFilter = ($checkInFilter !== '' && $checkOutFilter !== '' && $checkInFilter >= date('Y-m-d') && $checkOutFilter > $checkInFilter);

if ($typeFilter === 'hotel') {
    $categoryFilter = 'hotel';
    $typeFilter = '';
} elseif ($typeFilter === 'dormitory') {
    $categoryFilter = 'dormitory';
    $typeFilter = '';
}

$sql = "SELECT r.*, rt.name as type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.status != 'maintenance'";
$params = [];

if ($categoryFilter === 'hotel') {
    $sql .= " AND rt.name != 'Dormitory'";
} elseif ($categoryFilter === 'dormitory') {
    $sql .= " AND rt.name = 'Dormitory'";
}

if (!empty($typeFilter)) {
    $sql .= " AND rt.name = ?";
    $params[] = $typeFilter;
}

if ($guestsFilter > 0) {
    $sql .= " AND r.capacity >= ?";
    $params[] = $guestsFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (r.name LIKE ? OR r.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY CASE WHEN rt.name = 'Dormitory' THEN 1 ELSE 0 END ASC, r.price_per_night ASC, r.room_number ASC";
$rooms = dbRows($sql, $params);
if ($hasValidDateFilter) {
    $rooms = array_values(array_filter($rooms, fn($rm) => isRoomAvailable((int)$rm['id'], $checkInFilter, $checkOutFilter)));
}
$bookQueryExtra = '';
$bookExtraArr = [];
if ($hasValidDateFilter) {
    $bookExtraArr['check_in'] = $checkInFilter;
    $bookExtraArr['check_out'] = $checkOutFilter;
}
if ($guestsFilter > 0) {
    $bookExtraArr['guests'] = $guestsFilter;
}
if (!empty($bookExtraArr)) {
    $bookQueryExtra = '&' . http_build_query($bookExtraArr);
}
$allActiveCounts = dbRow("SELECT COUNT(*) AS total, SUM(CASE WHEN rt.name = 'Dormitory' THEN 1 ELSE 0 END) AS dorms, SUM(CASE WHEN rt.name != 'Dormitory' THEN 1 ELSE 0 END) AS hotels FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.status != 'maintenance'");
$totalActiveRoomsCount = (int)($allActiveCounts['total'] ?? count($rooms));
$totalHotelRoomsCount  = (int)($allActiveCounts['hotels'] ?? 0);
$totalDormRoomsCount   = (int)($allActiveCounts['dorms'] ?? 0);
$roomTypes = dbRows("SELECT * FROM room_types");
$allBookingsMap = getAllRoomsActiveBookingsMap();
$currentKyc = isLoggedIn() ? getUserKycStatus(currentUserId()) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse All Rooms — <?= h($hotelName) ?></title>
  <meta name="description" content="Explore comfortable dormitories, luxury hotel suites, and private rooms at <?= h($hotelName) ?>.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= SITE_URL ?>/rooms.php">
  <meta property="og:title" content="Browse All Rooms — <?= h($hotelName) ?>">
  <meta property="og:description" content="Explore comfortable dormitories, luxury hotel suites, and private rooms at <?= h($hotelName) ?>.">
  <meta property="og:image" content="<?= ASSETS_URL ?>/images/hotel/hero-bg.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Browse All Rooms — <?= h($hotelName) ?>">
  <meta name="twitter:description" content="Explore comfortable dormitories, luxury hotel suites, and private rooms at <?= h($hotelName) ?>.">
  <meta name="twitter:image" content="<?= ASSETS_URL ?>/images/hotel/hero-bg.jpg">
  <?php require_once __DIR__ . '/../components/page-head.php'; renderPerformanceHeadLinks(true); ?>
  <link rel="stylesheet" href="<?= h(assetUrl('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= h(assetUrl('css/public.css')) ?>">
  <?php require_once __DIR__ . '/../components/frontend-frameworks.php'; renderFrontendFrameworks(true, true); ?>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar" style="background:var(--navy-mid);border-bottom:1px solid var(--border);">
    <div class="container">
      <div class="nav-inner">
        <a href="index.php" class="nav-logo">
          <div class="nav-logo-icon">M</div>
          <div class="nav-logo-text">
            <div class="nav-logo-title">Marvelous Hotel</div>
            <div class="nav-logo-sub">&amp; Dormitory</div>
          </div>
        </a>
        <div class="nav-actions" style="display:flex;gap:10px;align-items:center;">
          <a href="index.php" class="btn btn-ghost btn-sm" title="Go Home"><i class="fa-solid fa-house"></i> <span class="nav-btn-text">Home</span></a>
          <?php if (isLoggedIn()): ?>
            <?php 
              $u = currentUser(); 
            ?>
            <a href="javascript:void(0)" class="nav-profile-badge" id="navProfileBadge" title="Open Navigation Menu" role="button" aria-expanded="false">
              <img src="<?= h(getUserAvatarUrl($u['avatar'])) ?>" alt="<?= h($u['name']) ?>" class="nav-user-avatar" decoding="async" onerror="this.src='<?= SITE_URL ?>/frontend/assets/images/avatars/default-avatar.png'">
              <span class="nav-user-name"><?= h(explode(' ', $u['name'])[0]) ?></span>
              <i class="fa-solid fa-chevron-down nav-user-chevron"></i>
            </a>
          <?php else: ?>
            <a href="auth/login.php" class="btn btn-gold btn-sm"><i class="fa-solid fa-user"></i> <span class="nav-btn-text">Login</span></a>
            <button class="nav-menu-btn" id="navMenuBtn" aria-label="Open Navigation Menu" title="Navigation Menu" aria-expanded="false">
              <i class="fa-solid fa-bars"></i>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <div class="container" style="padding-top:110px;padding-bottom:60px;">
    <div style="margin-bottom:30px;text-align:center;">
      <h1 style="font-family:var(--font-serif);font-size:2.2rem;color:#fff;">Rooms &amp; Dormitory</h1>
      <p style="color:var(--text-muted);">Choose the perfect room for your vacation, work trip, or stay.</p>
      <?php if (!empty($currentKyc['is_verified'])): ?>
        <div style="margin-top:10px;">
          <span class="badge" style="background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);font-size:0.75rem;padding:4px 12px;">
            <i class="fa-solid fa-circle-check"></i> Identity Verified — Instant Booking &amp; Payment Checkout Active
          </span>
        </div>
      <?php endif; ?>
    </div>

    <!-- Category Quick Pills (Hotel Rooms Overnight vs Dormitory) -->
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:18px;">
      <a href="rooms.php" class="btn btn-sm <?= ($categoryFilter === '' && $typeFilter === '') ? 'btn-gold' : 'btn-ghost' ?>" style="border:1px solid rgba(255,255,255,0.16);">
        <i class="fa-solid fa-layer-group"></i> All Rooms (<?= $totalActiveRoomsCount ?>)
      </a>
      <a href="rooms.php?category=hotel" class="btn btn-sm <?= $categoryFilter === 'hotel' ? 'btn-gold' : 'btn-ghost' ?>" style="border:1px solid rgba(201,168,76,0.35);">
        <i class="fa-solid fa-moon" style="color:var(--gold);"></i> Hotel Rooms — Overnight (<?= $totalHotelRoomsCount ?>)
      </a>
      <a href="rooms.php?category=dormitory" class="btn btn-sm <?= $categoryFilter === 'dormitory' ? 'btn-gold' : 'btn-ghost' ?>" style="border:1px solid rgba(96,165,250,0.35);">
        <i class="fa-solid fa-building" style="color:#60a5fa;"></i> Dormitory Rooms (<?= $totalDormRoomsCount ?>)
      </a>
    </div>

    <!-- Filter Form -->
    <div class="glass-card" style="padding:16px 20px;margin-bottom:20px;">
      <form action="" method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <input type="text" name="q" class="form-control" placeholder="Search by name..." value="<?= h($searchQuery) ?>" style="flex:2;min-width:180px;">
        <select name="type" class="form-control" style="flex:1;min-width:150px;">
          <option value="">All Types</option>
          <?php foreach ($roomTypes as $t): ?>
            <option value="<?= h($t['name']) ?>" <?= $typeFilter === $t['name'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="number" name="guests" class="form-control" min="1" max="10" placeholder="Pax" value="<?= $guestsFilter > 0 ? $guestsFilter : '' ?>" style="width:95px;" title="Minimum guests capacity">
        <?php if ($hasValidDateFilter): ?>
          <input type="hidden" name="check_in" value="<?= h($checkInFilter) ?>">
          <input type="hidden" name="check_out" value="<?= h($checkOutFilter) ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-gold"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if ($hasValidDateFilter || $guestsFilter > 0 || $searchQuery !== '' || $typeFilter !== '' || $categoryFilter !== ''): ?>
          <a href="rooms.php" class="btn btn-ghost btn-sm" title="Reset all filters"><i class="fa-solid fa-rotate-left"></i> Reset</a>
        <?php endif; ?>
      </form>
      <?php if ($hasValidDateFilter || $guestsFilter > 0): ?>
        <div style="margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.08);display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:0.78rem;color:#cbd5e1;">
          <span style="color:var(--gold);font-weight:600;"><i class="fa-solid fa-sliders"></i> Active Search:</span>
          <?php if ($hasValidDateFilter): ?>
            <span class="badge" style="background:rgba(34,197,94,0.14);color:#86efac;border:1px solid rgba(34,197,94,0.35);">
              <i class="fa-solid fa-calendar-check"></i> Open <?= formatDate($checkInFilter, 'M d') ?> &rarr; <?= formatDate($checkOutFilter, 'M d, Y') ?>
            </span>
          <?php endif; ?>
          <?php if ($guestsFilter > 0): ?>
            <span class="badge" style="background:rgba(59,130,246,0.14);color:#93c5fd;border:1px solid rgba(59,130,246,0.35);">
              <i class="fa-solid fa-users"></i> Min <?= $guestsFilter ?> Pax
            </span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Live REST API Quick Availability Bar (Tailwind CSS + Alpine.js + Slim 4) -->
    <div x-data="roomsQuickAvailability()" class="glass-card rounded-2xl border border-gold/25 px-4 sm:px-5 py-4 mb-8">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end gap-3">
        <div class="sm:col-span-2 lg:flex-1 lg:min-w-[180px]">
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-gold mb-1">
            <i class="fa-solid fa-bolt mr-1"></i> Instant Availability Check
          </label>
          <select x-model="roomId" class="form-control">
            <option value="">Select a room...</option>
            <?php foreach ($rooms as $rOpt): ?>
              <option value="<?= (int)$rOpt['id'] ?>"><?= h($rOpt['name']) ?> (₱<?= number_format((float)$rOpt['price_per_night'], 0) ?>/night)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="lg:min-w-[145px]">
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Check-in</label>
          <input type="date" x-model="checkIn" min="<?= date('Y-m-d') ?>" class="form-control">
        </div>
        <div class="lg:min-w-[145px]">
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Check-out</label>
          <input type="date" x-model="checkOut" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="form-control">
        </div>
        <button type="button" @click="checkNow()" :disabled="loading" class="btn btn-gold w-full sm:col-span-2 lg:w-auto" style="height:46px;white-space:nowrap;">
          <i class="fa-solid" :class="loading ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i>
          <span x-text="loading ? 'Checking...' : 'Check Dates'"></span>
        </button>
      </div>

      <div x-show="result" x-cloak class="mt-3 rounded-xl px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-3"
           :class="result && result.available ? 'bg-emerald-500/15 border border-emerald-500/40 text-emerald-200' : 'bg-rose-500/15 border border-rose-500/40 text-rose-200'">
        <div class="flex items-center gap-2">
          <i class="fa-solid" :class="result && result.available ? 'fa-circle-check text-emerald-400' : 'fa-triangle-exclamation text-rose-400'"></i>
          <span x-text="result ? result.message : ''"></span>
        </div>
        <template x-if="result && result.available && result.bookUrl">
          <a :href="result.bookUrl" class="btn btn-gold btn-sm">
            <i class="fa-solid fa-calendar-check"></i> Book Now (<span x-text="result.quoteTotal"></span>)
          </a>
        </template>
      </div>
    </div>
    <script>
      function roomsQuickAvailability() {
        return {
          roomId: '',
          checkIn: '<?= h($hasValidDateFilter ? $checkInFilter : date('Y-m-d')) ?>',
          checkOut: '<?= h($hasValidDateFilter ? $checkOutFilter : date('Y-m-d', strtotime('+1 day'))) ?>',
          loading: false,
          result: null,
          async checkNow() {
            if (!this.roomId) {
              this.result = { available: false, message: 'Please choose a room first.' };
              return;
            }
            this.loading = true;
            this.result = null;
            try {
              const res = await window.HotelAPI.checkAvailability(this.roomId, this.checkIn, this.checkOut);
              const d = res.data || {};
              const q = d.quote || {};
              this.result = {
                available: !!d.available,
                message: d.available
                  ? `${d.room.name} is AVAILABLE for ${q.nights} night(s) — Total: ${q.formatted_total}`
                  : (d.reason || 'Room is not available on those dates.'),
                quoteTotal: q.formatted_total || '',
                bookUrl: d.room ? `${d.room.book_url}&check_in=${encodeURIComponent(this.checkIn)}&check_out=${encodeURIComponent(this.checkOut)}` : ''
              };
            } catch (e) {
              this.result = { available: false, message: e.message || 'Could not verify availability.' };
            } finally {
              this.loading = false;
            }
          }
        };
      }
    </script>

    <!-- Rooms List -->
    <div class="rooms-grid">
      <?php if (empty($rooms)): ?>
        <div class="empty-state" style="grid-column:1/-1;">
          <div class="empty-state-icon"><i class="fa-solid fa-bed"></i></div>
          <div class="empty-state-title">No rooms found</div>
          <div class="empty-state-desc">Try changing your keyword or filter.</div>
        </div>
      <?php else: ?>
        <?php foreach ($rooms as $r): 
          $amenities = safeJson($r['amenities']);
          $occ = getRoomOccupancyStatus((int)$r['id'], $r['status'], $allBookingsMap[(int)$r['id']] ?? []);
          $isDorm = strcasecmp((string)($r['type_name'] ?? ''), 'Dormitory') === 0;
        ?>
          <div class="room-card">
            <div class="room-card-img">
              <img src="<?= roomThumbUrl($r['thumbnail']) ?>" loading="lazy" decoding="async" onerror="this.src='frontend/assets/images/rooms/default-room.jpg'">
              <div class="room-card-badge">
                <?= $occ['tag_html'] ?>
              </div>
            </div>
            <div class="room-card-body">
              <div style="display:flex;justify-content:space-between;align-items:start;gap:8px;margin-bottom:6px;">
                <h3 class="room-card-name"><?= h($r['name']) ?></h3>
                <?php if ($isDorm): ?>
                  <span class="badge" style="background:rgba(59,130,246,0.18);color:#93c5fd;border:1px solid rgba(59,130,246,0.38);white-space:nowrap;"><i class="fa-solid fa-building"></i> Dormitory</span>
                <?php else: ?>
                  <span class="badge badge-gold" style="white-space:nowrap;"><i class="fa-solid fa-moon"></i> Hotel · Overnight</span>
                <?php endif; ?>
              </div>
              <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px;">Floor <?= $r['floor'] ?> · Up to <?= $r['capacity'] ?> Pax</p>
              
              <?php if ($occ['state'] === 'occupied'): ?>
                <div style="font-size:0.75rem;color:#f87171;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);border-radius:6px;padding:5px 9px;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                  <i class="fa-solid fa-bed"></i> Currently occupied until <?= formatDate($occ['current_until']) ?>. Advance bookings open!
                </div>
              <?php elseif ($occ['state'] === 'reserved'): ?>
                <div style="font-size:0.75rem;color:#fbbf24;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.25);border-radius:6px;padding:5px 9px;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                  <i class="fa-solid fa-calendar-check"></i> Booked <?= formatDate($occ['next_booking']['check_in'], 'M d') ?> – <?= formatDate($occ['next_booking']['check_out'], 'M d') ?>. Other dates available!
                </div>
              <?php endif; ?>

              <div class="room-card-amenities">
                <?php foreach (array_slice($amenities, 0, 4) as $am): ?>
                  <span class="room-amenity-tag"><i class="fa-solid fa-check" style="color:var(--gold);font-size:0.6rem;"></i> <?= h($am) ?></span>
                <?php endforeach; ?>
              </div>

              <div class="room-card-footer">
                <div>
                  <div class="room-price-main"><?= formatCurrency($r['price_per_night']) ?></div>
                  <div class="room-price-sub"><?= $isDorm ? 'per bed / night' : 'per night (overnight)' ?></div>
                </div>
                <?php if (isLoggedIn() && empty($currentKyc['is_verified'])): ?>
                  <a href="user/profile.php" class="btn btn-sm" style="background:rgba(234,179,8,0.15);color:#fde047;border:1px solid rgba(234,179,8,0.35);" title="You must verify your ID before booking">
                    <i class="fa-solid fa-id-card"></i> Verify ID to Book
                  </a>
                <?php else: ?>
                  <a href="<?= isLoggedIn() ? 'user/booking.php?room_id='.$r['id'].$bookQueryExtra : 'auth/login.php?redirect='.urlencode('user/booking.php?room_id='.$r['id'].$bookQueryExtra) ?>" class="btn btn-gold btn-sm">
                    Select &amp; Book
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

<footer>
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="index.php" class="nav-logo" style="margin-bottom:12px;display:inline-flex;">
          <div class="nav-logo-icon">M</div>
          <div class="nav-logo-text">
            <div class="nav-logo-title">Marvelous Hotel</div>
            <div class="nav-logo-sub">& Dormitory</div>
          </div>
        </a>
        <p class="footer-brand-desc">
          Experience premium hospitality with our modern booking system, 
          AI room recommendations, and world-class service in every stay.
        </p>
      </div>
      <div>
        <div class="footer-heading">Quick Links</div>
        <a href="index.php" class="footer-link">Home</a>
        <a href="rooms.php" class="footer-link">Browse Rooms</a>
        <a href="about.php" class="footer-link">About Us</a>
        <a href="contact.php" class="footer-link">Contact</a>
      </div>
      <div>
        <div class="footer-heading">Account</div>
        <?php if (!isLoggedIn()): ?>
        <a href="auth/register.php" class="footer-link">Register</a>
        <a href="auth/login.php" class="footer-link">Login</a>
        <?php else: ?>
        <a href="<?= currentRole() ?>/dashboard.php" class="footer-link">Dashboard</a>
        <a href="user/my-bookings.php" class="footer-link">My Bookings</a>
        <a href="user/payment-history.php" class="footer-link">Payments</a>
        <a href="auth/logout.php?portal=<?= h(currentRole() ?: 'user') ?>" class="footer-link">Logout</a>
        <?php endif; ?>
      </div>
      <div>
        <div class="footer-heading">Contact</div>
        <p class="footer-link" style="cursor:default;"><i class="fa-solid fa-location-dot" style="color:var(--gold);width:16px;"></i> <?= h(getSetting('hotel_address','Quezon City, PH')) ?></p>
        <a href="tel:<?= h(getSetting('hotel_phone')) ?>" class="footer-link"><i class="fa-solid fa-phone" style="color:var(--gold);width:16px;"></i> <?= h(getSetting('hotel_phone')) ?></a>
        <a href="mailto:<?= h(getSetting('hotel_email')) ?>" class="footer-link"><i class="fa-solid fa-envelope" style="color:var(--gold);width:16px;"></i> <?= h(getSetting('hotel_email')) ?></a>
        <p class="footer-link" style="cursor:default;"><i class="fa-solid fa-clock" style="color:var(--gold);width:16px;"></i> Check-in: <?= h(getSetting('check_in_time')) ?> | Out: <?= h(getSetting('check_out_time')) ?></p>
      </div>
    </div>
    <div class="footer-bottom">
      <p class="footer-bottom-text">
        &copy; <?= date('Y') ?> <?= h($hotelName) ?>. All rights reserved.
      </p>
    </div>
  </div>
</footer>

<?php 
require_once __DIR__ . '/../components/central-nav.php'; 
renderCentralNav('rooms');
?>
<?php require_once __DIR__ . '/../components/ai-chatbot.php'; ?>
<script defer src="<?= h(assetUrl('js/core.js')) ?>"></script>
<script defer src="<?= h(assetUrl('js/public.js')) ?>"></script>
</body>
</html>
