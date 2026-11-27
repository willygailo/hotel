<?php
// ============================================================
// MARVELOUS HOTEL — Real-Time Streaming AI Chatbot Engine
// ai/chatbot.php
// Supports:
//   - Server-Sent Events (SSE) live token streaming (?stream=1 or stream:true)
//   - Multi-turn session conversation memory
//   - Live room catalog & logged-in guest booking/KYC awareness
//   - Server-verified interactive Room Cards & "Take Me There" buttons
//   - Backwards-compatible JSON mode
// ============================================================

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/rate-limiter.php';
require_once __DIR__ . '/nvidia-client.php';

$rawInput = file_get_contents('php://input');
$input = json_decode((string)$rawInput, true);
if (!is_array($input)) {
    $input = [];
}

// Handle explicit conversation reset
if (($input['action'] ?? '') === 'reset') {
    unset($_SESSION['ai_chat_history'], $_SESSION['ai_chat_lang']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'cleared' => true]);
    exit;
}

$wantsStream = !empty($input['stream']) || (($_GET['stream'] ?? '') === '1');
$message = trim((string)($input['message'] ?? ''));

if ($message === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'response' => 'No message received.']);
    exit;
}

if (mb_strlen($message, 'UTF-8') > 1000) {
    $message = mb_substr($message, 0, 1000, 'UTF-8');
}

$userId     = currentUserId();
$isLoggedIn = isLoggedIn();
$sessId     = session_id();
$lowerMsg   = mb_strtolower($message, 'UTF-8');

// Load multi-turn conversation memory (prefer server session; allow normalized client fallback if session is fresh)
$sessionHistory = $_SESSION['ai_chat_history'] ?? [];
if (empty($sessionHistory) && !empty($input['history']) && is_array($input['history'])) {
    $sessionHistory = $input['history'];
}
$history = normalizeAiChatHistory($sessionHistory, 10, 600);

// Detect language with gentle stickiness for very short/neutral follow-ups (e.g. "ok", "yes", "1000")
$lang = detectMessageLanguage($message);
$lastLang = $_SESSION['ai_chat_lang'] ?? null;
if ($lang === 'en' && $lastLang === 'tl' && preg_match('/^[\d\s₱,.\?!]+$|^(ok|okay|yes|sige|go|book|thanks|ty|link)$/i', trim($message))) {
    $lang = 'tl';
}

// ------------------------------------------------------------
// HELPER: Safely persist session history even after SSE headers were flushed
// ------------------------------------------------------------
function saveAiChatSessionTurn(string $sessId, array $updatedHistory, string $lang): void {
    if ($sessId === '') {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @ini_set('session.use_cookies', '0');
        @ini_set('session.use_only_cookies', '0');
        @session_id($sessId);
        @session_start();
    }
    $_SESSION['ai_chat_history'] = normalizeAiChatHistory($updatedHistory, 12, 600);
    $_SESSION['ai_chat_lang'] = $lang;
    @session_write_close();
}

// ------------------------------------------------------------
// HELPER: Emit SSE event frame immediately
// ------------------------------------------------------------
function emitSseEvent(string $event, array $data): void {
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

function startSseStreamHeaders(): void {
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', 'off');
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    if (function_exists('ob_implicit_flush')) {
        @ob_implicit_flush(true);
    }
}

// ------------------------------------------------------------
// HELPER: Extract Budget from Query (e.g. "1k", "1000", "₱1,000", "500 pesos")
// ------------------------------------------------------------
function extractBudgetFromQuery(string $msg): ?float {
    $clean = str_replace([",", "₱", "php", "pesos", "peso"], "", mb_strtolower($msg, "UTF-8"));
    if (preg_match("/\b(\d+(?:\.\d+)?)\s*(?:k|kilo)\b/i", $clean, $m)) {
        return (float)$m[1] * 1000.0;
    }
    if (preg_match("/\b(\d+(?:\.\d+)?)\s*libo\b/i", $clean, $m)) {
        return (float)$m[1] * 1000.0;
    }
    if (preg_match("/\b(\d{3,6})\b/", $clean, $m)) {
        return (float)$m[1];
    }
    return null;
}

// ------------------------------------------------------------
// HELPER: Fetch Room by ID or Keyword with Resilient Fallbacks
// ------------------------------------------------------------
function getFallbackSeedRooms(): array {
    return [
        ['id' => 4, 'room_number' => 'D01', 'name' => 'Dormitory Room - Female Wing', 'type_name' => 'Dormitory', 'price_per_night' => 350.00, 'floor' => 1, 'capacity' => 1, 'amenities' => '["WiFi","Air Con","Locker","Privacy Curtain","Shared Bathroom"]', 'thumbnail' => 'dorm-f01.jpg'],
        ['id' => 5, 'room_number' => 'D02', 'name' => 'Dormitory Room - Male Wing', 'type_name' => 'Dormitory', 'price_per_night' => 350.00, 'floor' => 1, 'capacity' => 1, 'amenities' => '["WiFi","Air Con","Locker","Privacy Curtain","Shared Bathroom"]', 'thumbnail' => 'dorm-m02.jpg'],
        ['id' => 6, 'room_number' => 'D03', 'name' => 'Dormitory Room - Co-ed Deluxe Wing', 'type_name' => 'Dormitory', 'price_per_night' => 450.00, 'floor' => 2, 'capacity' => 1, 'amenities' => '["WiFi","Air Con","Locker","Privacy Curtain","Study Nook"]', 'thumbnail' => 'dorm-03.jpg'],
        ['id' => 1, 'room_number' => '101', 'name' => 'Standard Hotel Room 101', 'type_name' => 'Standard Room', 'price_per_night' => 800.00, 'floor' => 1, 'capacity' => 2, 'amenities' => '["WiFi","Air Con","TV","Hot Shower","Ref"]', 'thumbnail' => 'room-101.jpg'],
        ['id' => 2, 'room_number' => '201', 'name' => 'Deluxe Hotel Room 201', 'type_name' => 'Deluxe Room', 'price_per_night' => 1500.00, 'floor' => 2, 'capacity' => 2, 'amenities' => '["WiFi","Air Con","Smart TV","Hot Shower","Breakfast"]', 'thumbnail' => 'room-201.jpg'],
        ['id' => 3, 'room_number' => '301', 'name' => 'Executive Suite 301', 'type_name' => 'Suite', 'price_per_night' => 2500.00, 'floor' => 3, 'capacity' => 4, 'amenities' => '["WiFi","Air Con","Smart TV","Hot Tub","Mini Bar"]', 'thumbnail' => 'suite-301.jpg'],
    ];
}

function getRoomByIdForCard(int $roomId): ?array {
    if ($roomId <= 0) {
        return null;
    }
    if (isDbAvailable() && function_exists('dbRow')) {
        try {
            $row = dbRow(
                "SELECT r.*, rt.name AS type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = ? LIMIT 1",
                [$roomId]
            );
            if ($row) {
                return $row;
            }
        } catch (\Throwable $e) {}
    }
    foreach (getFallbackSeedRooms() as $seed) {
        if ((int)$seed['id'] === $roomId) {
            return $seed;
        }
    }
    return null;
}

function findRoomByContextText(string $text): ?array {
    $lower = mb_strtolower($text, 'UTF-8');
    $rooms = [];
    if (isDbAvailable() && function_exists('dbRows')) {
        try {
            $rooms = dbRows("SELECT r.*, rt.name AS type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id ORDER BY r.price_per_night ASC");
        } catch (\Throwable $e) {}
    }
    if (empty($rooms)) {
        $rooms = getFallbackSeedRooms();
    }

    // Direct match by room number or exact name first
    foreach ($rooms as $r) {
        $rNum  = mb_strtolower((string)($r['room_number'] ?? ''), 'UTF-8');
        $rName = mb_strtolower((string)($r['name'] ?? ''), 'UTF-8');
        if (($rNum !== '' && preg_match('/\b' . preg_quote($rNum, '/') . '\b/i', $lower)) || ($rName !== '' && str_contains($lower, $rName))) {
            return $r;
        }
    }

    // Match by room category keyword
    $keywordGroups = [
        'dormitory' => ['dorm', 'dormitory', 'bedspace', 'female wing', 'male wing', 'co-ed', 'd01', 'd02', 'd03', '350', '450', 'pinakamura', 'cheapest'],
        'suite'     => ['suite', 'executive', 'luxury', 'hot tub', 'family', 'pamilya', '4 pax', '4 tao', 'apat', '2500', '2,500'],
        'deluxe'    => ['deluxe', 'king', 'couple', 'mag-asawa', '1500', '1,500'],
        'standard'  => ['standard', '800', 'private room', 'overnight', 'pang-gabi', 'pang gabi', 'hotel room'],
    ];
    foreach ($keywordGroups as $typeHint => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                foreach ($rooms as $r) {
                    $hay = mb_strtolower(($r['name'] ?? '') . ' ' . ($r['type_name'] ?? ''), 'UTF-8');
                    if (str_contains($hay, $typeHint)) {
                        return $r;
                    }
                }
            }
        }
    }
    return null;
}

function getBestRoomForBudget(?float $budget): array {
    $room = null;
    $cheaperAlt = null;

    if ($budget !== null && isDbAvailable() && function_exists('dbRows')) {
        try {
            $candidates = dbRows("
                SELECT r.*, rt.name as type_name
                FROM rooms r
                JOIN room_types rt ON r.room_type_id = rt.id
                WHERE r.status = 'available'
                  AND r.price_per_night <= ?
                  AND r.id NOT IN (
                      SELECT room_id FROM bookings WHERE status IN ('pending', 'confirmed', 'checked_in')
                  )
                ORDER BY r.price_per_night DESC
            ", [$budget]);

            if (!empty($candidates)) {
                $room = $candidates[0];
                if (count($candidates) > 1) {
                    $cheaperAlt = end($candidates);
                }
            }
        } catch (\Throwable $e) {}
    }

    if (!$room) {
        $seeds = getFallbackSeedRooms();
        if ($budget === null || $budget >= 800.0) {
            $room = $seeds[3]; // Standard Hotel Room 101
            $cheaperAlt = $seeds[0]; // Dormitory Room - Female Wing
        } else {
            $room = $seeds[0]; // Dormitory Room - Female Wing
        }
    }

    return ['best' => $room, 'cheaper' => $cheaperAlt];
}

// ------------------------------------------------------------
// HELPER: Build Interactive Clickable Room Card HTML
// ------------------------------------------------------------
function buildInteractiveRoomCard(array $room, ?float $budget = null, string $lang = 'tl'): string {
    $roomId   = (int)($room['id'] ?? 1);
    $name     = htmlspecialchars((string)($room['name'] ?? 'Standard Room 101'));
    $typeName = htmlspecialchars((string)($room['type_name'] ?? 'Standard Room'));
    $price    = (float)($room['price_per_night'] ?? 800.0);
    $floor    = (int)($room['floor'] ?? 1);
    $capacity = (int)($room['capacity'] ?? 2);

    $rawAmenities = $room['amenities'] ?? [];
    if (is_string($rawAmenities)) {
        $rawAmenities = json_decode($rawAmenities, true) ?: ['WiFi', 'Air Con', 'Hot Shower'];
    }
    $amenityList = htmlspecialchars(implode(' · ', array_slice((array)$rawAmenities, 0, 4)));

    $savingsHtml = '';
    $badgeText = ($lang === 'en') ? 'Top Value Pick' : 'Pinakamagandang Rekomendasyon';
    if ($budget !== null && $budget >= $price) {
        $diff = $budget - $price;
        if ($diff > 0) {
            $savingsLabel = ($lang === 'en') ? "Save ₱" . number_format($diff, 2) : "May sukli kang ₱" . number_format($diff, 2);
            $savingsHtml = '<span class="savings-pill"><i class="fa-solid fa-piggy-bank"></i> ' . $savingsLabel . '!</span>';
        }
        $badgeText = ($lang === 'en') ? "Fits your ₱" . number_format($budget, 0) . " budget" : "Pasok sa ₱" . number_format($budget, 0) . " badyet";
    }

    $bookUrl     = SITE_URL . '/user/booking.php?room_id=' . $roomId;
    $viewUrl     = SITE_URL . '/rooms.php?q=' . urlencode((string)($room['name'] ?? 'Standard'));
    $imgUrl      = roomThumbUrl($room['thumbnail'] ?? '');
    $fallbackImg = ASSETS_URL . '/images/rooms/default-room.jpg';

    $bookBtnText = ($lang === 'en')
        ? "Book {$name} (₱" . number_format($price, 0) . ")"
        : "I-book ang {$name} (₱" . number_format($price, 0) . ")";
    $viewBtnText = ($lang === 'en') ? "View Room Details" : "Tingnan ang Detalye";
    $formattedPrice = number_format($price, 2);

    return <<<HTML
<div class="ai-room-card">
  <div class="ai-room-card-img-wrap">
    <img src="{$imgUrl}" alt="{$name}" class="ai-room-card-img" onerror="this.src='{$fallbackImg}';this.onerror=null;">
    <div class="ai-room-card-badge"><i class="fa-solid fa-star"></i> {$badgeText}</div>
  </div>
  <div class="ai-room-card-name">🏨 {$name} <span style="font-size:0.8rem;color:var(--text-muted);font-weight:normal;">({$typeName})</span></div>
  <div class="ai-room-card-price">₱{$formattedPrice} / night {$savingsHtml}</div>
  <div class="ai-room-card-desc">Floor {$floor} · Up to {$capacity} Pax<br>{$amenityList}</div>
  <div class="ai-room-card-actions">
    <a href="{$bookUrl}" class="ai-room-btn-book">
      <i class="fa-solid fa-calendar-check"></i> {$bookBtnText}
    </a>
    <a href="{$viewUrl}" class="ai-room-btn-view">
      <i class="fa-solid fa-eye"></i> {$viewBtnText}
    </a>
  </div>
</div>
HTML;
}

// ------------------------------------------------------------
// HELPER: Build Verified "Take Me There" Action Buttons + Room Cards
// ------------------------------------------------------------
function buildNavigationButtonsHtml(array $buttons): string {
    if (empty($buttons)) {
        return '';
    }
    $items = [];
    foreach (array_slice($buttons, 0, 3) as $btn) {
        $url   = htmlspecialchars($btn['url'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($btn['label'], ENT_QUOTES, 'UTF-8');
        $icon  = htmlspecialchars($btn['icon'] ?? 'fa-arrow-right', ENT_QUOTES, 'UTF-8');
        $items[] = "<a href=\"{$url}\" class=\"ai-nav-action-btn\"><i class=\"fa-solid {$icon}\"></i> <span>{$label}</span> <i class=\"fa-solid fa-arrow-right ai-nav-arrow\"></i></a>";
    }
    return '<div class="ai-nav-actions">' . implode('', $items) . '</div>';
}

function resolveAiActionsHtml(array $parsed, string $userMsg, string $replyText, array $history, string $lang, bool $isLoggedIn, ?float $detectedBudget = null): array {
    $cardsHtml = '';
    $buttons   = [];
    $seenUrls  = [];

    $addButton = function (string $label, string $url, string $icon) use (&$buttons, &$seenUrls): void {
        if (isset($seenUrls[$url])) {
            return;
        }
        $seenUrls[$url] = true;
        $buttons[] = ['label' => $label, 'url' => $url, 'icon' => $icon];
    };

    // 1. Explicit [[BOOK_ROOM:id]] tags from LLM (validated against live rooms)
    $renderedRoomIds = [];
    foreach (array_slice($parsed['room_ids'] ?? [], 0, 2) as $rid) {
        $room = getRoomByIdForCard((int)$rid);
        if ($room) {
            $cardsHtml .= buildInteractiveRoomCard($room, $detectedBudget, $lang);
            $renderedRoomIds[] = (int)$room['id'];
        }
    }

    // 2. Explicit [[VIEW_ROOMS:query]] tags
    foreach (array_slice($parsed['room_queries'] ?? [], 0, 2) as $q) {
        $addButton(
            ($lang === 'en' ? "Browse {$q}" : "Tingnan ang {$q}"),
            SITE_URL . '/rooms.php?q=' . urlencode($q),
            'fa-magnifying-glass'
        );
    }

    // 3. Explicit allowlisted [[GOTO:route]] tags
    $routeCatalog = [
        'rooms' => [
            'en' => 'Browse All Rooms', 'tl' => 'Tingnan Lahat ng Kwarto',
            'url' => SITE_URL . '/rooms.php', 'icon' => 'fa-door-open'
        ],
        'my_bookings' => [
            'en' => 'View My Bookings', 'tl' => 'Tingnan ang My Bookings',
            'url' => SITE_URL . ($isLoggedIn ? '/user/my-bookings.php' : '/auth/login.php?redirect=' . urlencode('/user/my-bookings.php')),
            'icon' => 'fa-book-bookmark'
        ],
        'payment' => [
            'en' => 'Go to Payment & Receipts', 'tl' => 'Pumunta sa Pagbabayad / Resibo',
            'url' => SITE_URL . ($isLoggedIn ? '/user/my-bookings.php' : '/auth/login.php'),
            'icon' => 'fa-credit-card'
        ],
        'payment_history' => [
            'en' => 'View Payment History', 'tl' => 'Tingnan ang Payment History',
            'url' => SITE_URL . ($isLoggedIn ? '/user/payment-history.php' : '/auth/login.php?redirect=' . urlencode('/user/payment-history.php')),
            'icon' => 'fa-receipt'
        ],
        'profile' => [
            'en' => 'Profile & KYC Verification', 'tl' => 'Profile at KYC Verification',
            'url' => SITE_URL . ($isLoggedIn ? '/user/profile.php' : '/auth/login.php'),
            'icon' => 'fa-id-card'
        ],
        'reviews' => [
            'en' => 'My Reviews & Ratings', 'tl' => 'My Reviews at Ratings',
            'url' => SITE_URL . ($isLoggedIn ? '/user/reviews.php' : '/auth/login.php?redirect=' . urlencode('/user/reviews.php')),
            'icon' => 'fa-star'
        ],
        'notifications' => [
            'en' => 'View Notifications', 'tl' => 'Tingnan ang Notifications',
            'url' => SITE_URL . ($isLoggedIn ? '/user/notifications.php' : '/auth/login.php?redirect=' . urlencode('/user/notifications.php')),
            'icon' => 'fa-bell'
        ],
        'ai_recommend' => [
            'en' => 'Open Smart Room Matcher', 'tl' => 'Buksan ang Smart Room Matcher',
            'url' => SITE_URL . ($isLoggedIn ? '/user/ai-recommend.php' : '/rooms.php'),
            'icon' => 'fa-wand-magic-sparkles'
        ],
        'contact' => [
            'en' => 'Location & Contact Us', 'tl' => 'Lokasyon at Contact Us',
            'url' => SITE_URL . '/contact.php', 'icon' => 'fa-location-dot'
        ],
        'about' => [
            'en' => 'About Marvelous Hotel', 'tl' => 'Tungkol sa Marvelous Hotel',
            'url' => SITE_URL . '/about.php', 'icon' => 'fa-hotel'
        ],
        'forgot_password' => [
            'en' => 'Reset / Forgot Password', 'tl' => 'I-reset ang Password',
            'url' => SITE_URL . '/auth/forgot-password.php', 'icon' => 'fa-key'
        ],
        'login' => [
            'en' => 'Log In to Guest Account', 'tl' => 'Mag-login sa Account',
            'url' => SITE_URL . '/auth/login.php', 'icon' => 'fa-right-to-bracket'
        ],
        'register' => [
            'en' => 'Create Guest Account', 'tl' => 'Gumawa ng Account',
            'url' => SITE_URL . '/auth/register.php', 'icon' => 'fa-user-plus'
        ],
        'dashboard' => [
            'en' => 'Open User Dashboard', 'tl' => 'Buksan ang User Dashboard',
            'url' => SITE_URL . ($isLoggedIn ? '/user/dashboard.php' : '/auth/login.php'),
            'icon' => 'fa-gauge-high'
        ],
    ];

    foreach ($parsed['routes'] ?? [] as $routeKey) {
        if (isset($routeCatalog[$routeKey])) {
            $r = $routeCatalog[$routeKey];
            $addButton($lang === 'en' ? $r['en'] : $r['tl'], $r['url'], $r['icon']);
        }
    }

    // 4. Contextual safety net: when user asks "maaari mo ba akong ipunta doon" / "take me there" / "book ko na"
    $lowerUser = mb_strtolower($userMsg, 'UTF-8');
    $wantsNavigation = (bool)preg_match('/\b(ipunta|punta|dalhin|take me|bring me|go there|direct|link|pa-?book|book ko|book now|i-?book|show me|tingnan ko)\b/i', $lowerUser);

    if (empty($renderedRoomIds)) {
        // Check if budget room query
        $isRoomIntent = (bool)preg_match('/\b(room|kwarto|kuwarto|dorm|bed|maganda|rekomenda|recommend|suggest|badyet|budget|stay|tulugan)\b/i', $lowerUser);
        if ($detectedBudget !== null && $isRoomIntent) {
            $match = getBestRoomForBudget($detectedBudget);
            if (!empty($match['best'])) {
                $cardsHtml .= buildInteractiveRoomCard($match['best'], $detectedBudget, $lang);
                $renderedRoomIds[] = (int)$match['best']['id'];
            }
        } elseif ($wantsNavigation) {
            // Search current message + last 2 conversation turns + current reply to find the referenced room
            $recentContext = $userMsg . ' ' . $replyText;
            foreach (array_slice($history, -3) as $hTurn) {
                $recentContext .= ' ' . ($hTurn['content'] ?? '');
            }
            $contextRoom = findRoomByContextText($recentContext);
            if ($contextRoom) {
                $cardsHtml .= buildInteractiveRoomCard($contextRoom, $detectedBudget, $lang);
                $renderedRoomIds[] = (int)$contextRoom['id'];
            }
        }
    }

    if ($wantsNavigation && empty($renderedRoomIds) && empty($buttons)) {
        $recentLower = mb_strtolower($userMsg . ' ' . $replyText . ' ' . implode(' ', array_column(array_slice($history, -2), 'content')), 'UTF-8');
        if (str_contains($recentLower, 'password') || str_contains($recentLower, 'reset')) {
            $r = $routeCatalog['forgot_password'];
        } elseif (str_contains($recentLower, 'payment history') || str_contains($recentLower, 'history ng bayad')) {
            $r = $routeCatalog['payment_history'];
        } elseif (str_contains($recentLower, 'review') || str_contains($recentLower, 'rating')) {
            $r = $routeCatalog['reviews'];
        } elseif (str_contains($recentLower, 'notification')) {
            $r = $routeCatalog['notifications'];
        } elseif (str_contains($recentLower, 'gcash') || str_contains($recentLower, 'maya') || str_contains($recentLower, 'bayad') || str_contains($recentLower, 'payment')) {
            $r = $routeCatalog['payment'];
        } elseif (str_contains($recentLower, 'booking') || str_contains($recentLower, 'reservation') || str_contains($recentLower, 'voucher')) {
            $r = $routeCatalog['my_bookings'];
        } elseif (str_contains($recentLower, 'location') || str_contains($recentLower, 'address') || str_contains($recentLower, 'saan') || str_contains($recentLower, 'direksyon')) {
            $r = $routeCatalog['contact'];
        } elseif (str_contains($recentLower, 'kyc') || str_contains($recentLower, 'valid id') || str_contains($recentLower, 'profile')) {
            $r = $routeCatalog['profile'];
        } else {
            $r = $routeCatalog['rooms'];
        }
        $addButton($lang === 'en' ? $r['en'] : $r['tl'], $r['url'], $r['icon']);
    }

    return [
        'cards_html'   => $cardsHtml,
        'buttons_html' => buildNavigationButtonsHtml($buttons),
        'buttons'      => $buttons,
    ];
}

// ------------------------------------------------------------
// 0. Rate limit public/user chat requests
// ------------------------------------------------------------
if (isDbAvailable()) {
    try {
        $ip = getClientIp();
        $maxReq = in_array($ip, ['127.0.0.1', '::1', 'localhost', 'unknown'], true) ? 60 : 20;
        $rl = checkRateLimit($ip, 'ai_chat', $maxReq, 60);
        if (!$rl['allowed']) {
            $wait = (int)($rl['retryAfter'] ?? 60);
            $rlMsg = ($lang === 'en')
                ? "You're sending messages too quickly. Please wait {$wait} second(s) and try again."
                : "Masyadong mabilis ang pagpapadala ng mensahe. Mangyaring maghintay ng {$wait} segundo bago magtanong muli.";
            if ($wantsStream) {
                startSseStreamHeaders();
                emitSseEvent('error', ['message' => $rlMsg, 'is_alert' => true, 'lang' => $lang]);
            } else {
                http_response_code(429);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'warning', 'is_alert' => true, 'response' => $rlMsg, 'lang' => $lang]);
            }
            exit;
        }
    } catch (\Throwable $e) {}
}

// ------------------------------------------------------------
// 1. Check for Bad / Malicious Intent (Hacking, Injection, Exploits)
// ------------------------------------------------------------
$badData = json_decode((string)file_get_contents(__DIR__ . '/training-data/bad-intent-keywords.json'), true);
$isFlagged = false;
$flagReason = '';

foreach ($badData['bad_keywords'] ?? [] as $badWord) {
    if (str_contains($lowerMsg, $badWord)) {
        $isFlagged = true;
        $flagReason = "Detected restricted term: $badWord";
        break;
    }
}

if ($isFlagged) {
    $warnMsg = ($lang === 'en')
        ? "Warning: Your query was flagged for containing restricted terms. Our system monitors and logs security threats."
        : ($badData['warning_message'] ?? "Babala: Ang iyong mensahe ay na-flag dahil sa mga ipinagbabawal na salita.");

    if (isDbAvailable()) {
        try {
            dbExec(
                "INSERT INTO ai_logs (user_id, user_message, ai_response, intent, flagged, flag_reason) VALUES (?, ?, ?, 'security_alert', 1, ?)",
                [$userId, $message, $warnMsg, $flagReason]
            );
            auditLog('Security Violation in AI Chat', 'AI Module', "User/IP attempted query: {$message} | {$flagReason}");
        } catch (\Throwable $e) {}
    }

    if ($wantsStream) {
        startSseStreamHeaders();
        emitSseEvent('error', ['message' => $warnMsg, 'is_alert' => true, 'lang' => $lang]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'warning', 'is_alert' => true, 'response' => $warnMsg, 'lang' => $lang]);
    }
    exit;
}

// ------------------------------------------------------------
// 1b. Strict Website-Only Scope Gate (Politely decline off-topic queries)
// ------------------------------------------------------------
if (function_exists('isOffTopicForWebsite') && isOffTopicForWebsite($message, $history)) {
    $refusalHtml = ($lang === 'en')
        ? "I'm sorry, I can only assist with questions about the <strong>Marvelous Hotel &amp; Dormitory</strong> website — such as rooms, rates, promos, KYC verification, bookings, GCash/Maya payments, check-in/out schedules, and account help. How can I help with your stay today?"
        : "Paumanhin po, tungkol lamang sa <strong>Marvelous Hotel &amp; Dormitory</strong> website ang masasagot ko — tulad ng mga kwarto, presyo, promo codes, KYC verification, booking, GCash/Maya payment, check-in/out, at account assistance. Paano po ako makakatulong sa inyong stay?";

    $refusalActions = resolveAiActionsHtml(
        ['clean_text' => $refusalHtml, 'room_ids' => [], 'routes' => ['rooms', 'contact'], 'room_queries' => []],
        $message,
        strip_tags($refusalHtml),
        $history,
        $lang,
        $isLoggedIn,
        null
    );
    $fullRefusalHtml = $refusalHtml . $refusalActions['buttons_html'];
    $plainRefusal = aiPlainTextForSpeech($refusalHtml);

    if (isDbAvailable()) {
        try {
            dbExec(
                "INSERT INTO ai_logs (user_id, user_message, ai_response, intent, flagged) VALUES (?, ?, ?, 'off_topic_refused', 0)",
                [$userId, $message, $fullRefusalHtml]
            );
        } catch (\Throwable $e) {}
    }

    if ($wantsStream) {
        releaseSessionLock();
        startSseStreamHeaders();
        emitSseEvent('meta', ['lang' => $lang, 'stream' => true]);
        emitSseEvent('done', [
            'full_html'  => $fullRefusalHtml,
            'plain_text' => $plainRefusal,
            'source'     => 'off_topic_refused',
            'lang'       => $lang,
            'model'      => null,
            'latency_ms' => 0,
        ]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'     => 'success',
            'is_alert'   => false,
            'response'   => $fullRefusalHtml,
            'plain_text' => $plainRefusal,
            'source'     => 'off_topic_refused',
            'lang'       => $lang,
        ]);
    }
    exit;
}

// ------------------------------------------------------------
// HELPER: Weighted Bilingual FAQ Matcher (avoids false-positive substring hits)
// ------------------------------------------------------------
function matchBestFaqEntry(string $lowerMsg, array $faqs): ?array {
    $bestFaq = null;
    $bestScore = 0;

    foreach ($faqs as $faq) {
        $score = 0;
        foreach ($faq['keywords'] ?? [] as $kw) {
            $kwLower = mb_strtolower(trim((string)$kw), 'UTF-8');
            if ($kwLower === '') continue;
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($kwLower, '/') . '(?![\p{L}\p{N}])/iu';
            if (preg_match($pattern, $lowerMsg)) {
                $wordCount = count(preg_split('/\s+/', $kwLower) ?: []);
                $score += ($wordCount > 1 ? 4 + $wordCount : 2);
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestFaq = $faq;
        }
    }

    return ($bestScore >= 2) ? $bestFaq : null;
}

// ------------------------------------------------------------
// 2. Build Local Fallback Response Helper (only used if Live AI disabled or offline)
// ------------------------------------------------------------
function buildLocalFallbackReply(string $message, string $lowerMsg, string $lang, ?int $userId, array $history, ?float $detectedBudget): array {
    // A. Booking status query
    $isBookingQuery = str_contains($lowerMsg, 'booking ko') || str_contains($lowerMsg, 'status ng booking')
        || str_contains($lowerMsg, 'my booking') || str_contains($lowerMsg, 'check my reservation')
        || str_contains($lowerMsg, 'kamusta ang booking') || str_contains($lowerMsg, 'ano status ng room ko');

    if ($isBookingQuery) {
        if (!$userId) {
            return [
                'html'   => ($lang === 'en')
                    ? "To view your active booking status, please log in to your account first!"
                    : "Upang makita ang status ng iyong reservation, mangyaring mag-login muna sa iyong account!",
                'intent' => 'my_booking_guest',
                'routes' => ['login'],
            ];
        }
        $latestBooking = isDbAvailable() ? dbRow("
            SELECT b.*, r.name as room_name, r.room_number
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            WHERE b.user_id = ?
            ORDER BY b.id DESC LIMIT 1
        ", [$userId]) : null;

        if ($latestBooking) {
            $code = htmlspecialchars($latestBooking['booking_code']);
            $rName = htmlspecialchars($latestBooking['room_name']);
            $rNum = htmlspecialchars($latestBooking['room_number']);
            $status = strtoupper($latestBooking['status']);
            $checkIn = formatDate($latestBooking['check_in']);
            $checkOut = formatDate($latestBooking['check_out']);
            $detailUrl = SITE_URL . "/user/booking-detail.php?id=" . $latestBooking['id'];

            $html = ($lang === 'en')
                ? "📋 <strong>Here is your latest booking:</strong><br><br>• <strong>Booking Code:</strong> #{$code}<br>• <strong>Room:</strong> {$rName} (Room #{$rNum})<br>• <strong>Dates:</strong> {$checkIn} &rarr; {$checkOut}<br>• <strong>Status:</strong> <span style='color:var(--gold);font-weight:700;'>{$status}</span><br><br><a href='{$detailUrl}' class='btn btn-gold btn-sm' style='display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:6px;font-size:0.8rem;'><i class='fa-solid fa-receipt'></i> View Full Booking Voucher</a>"
                : "📋 <strong>Ito po ang iyong pinakabagong reservation:</strong><br><br>• <strong>Booking Code:</strong> #{$code}<br>• <strong>Kwarto:</strong> {$rName} (Room #{$rNum})<br>• <strong>Petsa:</strong> {$checkIn} hanggang {$checkOut}<br>• <strong>Status:</strong> <span style='color:var(--gold);font-weight:700;'>{$status}</span><br><br><a href='{$detailUrl}' class='btn btn-gold btn-sm' style='display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:6px;font-size:0.8rem;'><i class='fa-solid fa-receipt'></i> Tingnan ang Booking Voucher</a>";
            return ['html' => $html, 'intent' => 'my_booking_status', 'routes' => []];
        }
        return [
            'html'   => ($lang === 'en')
                ? "You don't have any reservations yet. Would you like to browse our available rooms?"
                : "Wala ka pa pong aktibong reservation. Nais mo bang tingnan ang ating mga bakanteng kwarto?",
            'intent' => 'my_booking_status',
            'routes' => ['rooms'],
        ];
    }

    // A2. Live Active Promos query
    if (preg_match('/\b(promo|promos|promo\s*code|discount|discounts|coupon|voucher\s*code)\b/i', $lowerMsg) && isDbAvailable()) {
        try {
            $promos = dbRows("
                SELECT code, description, discount_type, discount_value, min_nights, valid_until
                FROM promos
                WHERE status = 'active' AND (valid_until IS NULL OR valid_until >= CURDATE())
                ORDER BY id ASC LIMIT 6
            ");
            if (!empty($promos)) {
                $items = [];
                foreach ($promos as $p) {
                    $disc = ($p['discount_type'] === 'percent')
                        ? rtrim(rtrim(number_format((float)$p['discount_value'], 2), '0'), '.') . '% OFF'
                        : '₱' . number_format((float)$p['discount_value'], 0) . ' OFF';
                    $minN = (int)($p['min_nights'] ?? 1);
                    $items[] = "• <strong>" . htmlspecialchars($p['code']) . "</strong> — {$disc} (min {$minN} night" . ($minN > 1 ? 's' : '') . "): " . htmlspecialchars($p['description']);
                }
                $intro = ($lang === 'en')
                    ? "🏷️ <strong>Currently Active Promo Codes:</strong><br><br>"
                    : "🏷️ <strong>Mga Active Promo Codes Ngayon:</strong><br><br>";
                $outro = ($lang === 'en')
                    ? "<br><br>Apply any eligible code on the Booking page before confirming!"
                    : "<br><br>Ilagay lamang ang code sa Booking page bago i-confirm ang reservation!";
                return ['html' => $intro . implode('<br>', $items) . $outro, 'intent' => 'promos_live_local', 'routes' => ['rooms']];
            }
        } catch (\Throwable $e) {}
    }

    // A3. Logged-in Guest KYC Status query
    if ($userId && preg_match('/\b(kyc|verified|verification|bakit\s+(di|hindi)\s+maka-?book|cannot\s+book|valid\s*id\s*status)\b/i', $lowerMsg) && isDbAvailable()) {
        try {
            $kyc = getUserKycStatus($userId);
            if ($kyc['is_verified']) {
                $html = ($lang === 'en')
                    ? "✅ Your account's <strong>KYC Identity Verification is Verified</strong>! You can book any available room right away."
                    : "✅ <strong>Verified na po ang iyong KYC Identity!</strong> Maaari ka nang mag-book ng kahit anong bakanteng kwarto ngayon.";
                return ['html' => $html, 'intent' => 'kyc_status_local', 'routes' => ['rooms']];
            }
            if ($kyc['is_pending']) {
                $html = ($lang === 'en')
                    ? "⏳ Your valid ID is currently <strong>Pending Admin Review</strong>. You will be notified as soon as it is approved so you can book."
                    : "⏳ Ang iyong na-upload na Valid ID ay kasalukuyang <strong>Pending Admin Review</strong>. Makakatanggap ka ng notification kapag na-approve na ito.";
                return ['html' => $html, 'intent' => 'kyc_status_local', 'routes' => ['profile']];
            }
            $rejNote = ($kyc['is_rejected'] && !empty($kyc['rejection_reason']))
                ? "<br><em>Reason:</em> " . htmlspecialchars($kyc['rejection_reason'])
                : '';
            $html = ($lang === 'en')
                ? "🪪 Your account is not yet KYC-verified (<strong>" . strtoupper($kyc['status']) . "</strong>).{$rejNote}<br><br>Please open <strong>Profile Settings</strong> and upload a clear Government or School ID to unlock room booking."
                : "🪪 Hindi pa verified ang iyong KYC (<strong>" . strtoupper($kyc['status']) . "</strong>).{$rejNote}<br><br>Pumunta lamang sa <strong>Profile Settings</strong> at mag-upload ng malinaw na Valid Government o School ID upang makapag-book.";
            return ['html' => $html, 'intent' => 'kyc_status_local', 'routes' => ['profile']];
        } catch (\Throwable $e) {}
    }

    // B. Budget recommendation fallback
    $isRoomQuery = (bool)preg_match('/\b(room|kwarto|kuwarto|maganda|rekomenda|recommend|suggest|badyet|budget|pera|stay|tulugan)\b/i', $lowerMsg);
    if ($detectedBudget !== null && $isRoomQuery) {
        $match = getBestRoomForBudget($detectedBudget);
        $bestRoom = $match['best'];
        $cheaperRoom = $match['cheaper'];
        $diff = $detectedBudget - (float)$bestRoom['price_per_night'];

        if ($lang === 'en') {
            $html = "For your budget of <strong>₱" . number_format($detectedBudget, 2) . "</strong>, I recommend our <strong>" . htmlspecialchars($bestRoom['name']) . "</strong> at only <strong>₱" . number_format($bestRoom['price_per_night'], 2) . "/night</strong>" . ($diff > 0 ? " (saving you <strong>₱" . number_format($diff, 2) . "</strong>!)" : "!") . "<br><br>";
            if ($cheaperRoom && $cheaperRoom['id'] !== $bestRoom['id']) {
                $html .= "• <em>Cheaper option:</em> <strong>" . htmlspecialchars($cheaperRoom['name']) . "</strong> at ₱" . number_format($cheaperRoom['price_per_night'], 2) . "/night.<br>";
            }
            $html .= "Click the button below to book right away:";
        } else {
            $diffText = ($diff > 0) ? " (at may sukli ka pang <strong>₱" . number_format($diff, 2) . "</strong>)" : "";
            $html = "Para sa iyong badyet na <strong>₱" . number_format($detectedBudget, 2) . "</strong>, inirerekomenda ko po ang <strong>" . htmlspecialchars($bestRoom['name']) . "</strong> sa halagang <strong>₱" . number_format($bestRoom['price_per_night'], 2) . "/gabi</strong> lamang" . $diffText . "!<br><br>";
            if ($cheaperRoom && $cheaperRoom['id'] !== $bestRoom['id']) {
                $html .= "• <em>Mas murang opsyon:</em> <strong>" . htmlspecialchars($cheaperRoom['name']) . "</strong> sa halagang ₱" . number_format($cheaperRoom['price_per_night'], 2) . "/gabi.<br>";
            }
            $html .= "Pindutin ang button sa ibaba upang agad itong ma-book:";
        }
        return ['html' => $html, 'intent' => 'budget_recommend_local', 'room_ids' => [(int)$bestRoom['id']]];
    }

    // C. Contextual "take me there" follow-up fallback
    if (preg_match('/\b(ipunta|punta|dalhin|take me|bring me|go there|pa-?book|book ko)\b/i', $lowerMsg)) {
        $recentText = $message . ' ' . implode(' ', array_column(array_slice($history, -3), 'content'));
        $ctxRoom = findRoomByContextText($recentText);
        if ($ctxRoom) {
            $html = ($lang === 'en')
                ? "Certainly! Click the button below to go straight to <strong>" . htmlspecialchars($ctxRoom['name']) . "</strong> and complete your booking."
                : "Opo! Pindutin lamang ang button sa ibaba upang dumiretso sa <strong>" . htmlspecialchars($ctxRoom['name']) . "</strong> at makapag-book agad.";
            return ['html' => $html, 'intent' => 'contextual_nav_local', 'room_ids' => [(int)$ctxRoom['id']]];
        }
    }

    // D. Weighted Bilingual FAQ match
    $faqs = json_decode((string)file_get_contents(__DIR__ . '/training-data/faq.json'), true);
    if (is_array($faqs)) {
        $matchedFaq = matchBestFaqEntry($lowerMsg, $faqs);
        if ($matchedFaq) {
            $ans = ($lang === 'en')
                ? ($matchedFaq['response_en'] ?? $matchedFaq['response_tl'] ?? '')
                : ($matchedFaq['response_tl'] ?? $matchedFaq['response_en'] ?? '');
            return [
                'html'   => $ans,
                'intent' => 'faq_match_' . ($matchedFaq['id'] ?? 'general'),
                'routes' => $matchedFaq['routes'] ?? [],
            ];
        }
    }

    // D2. Specific Room mention fallback (e.g., "Room 101", "Suite 301", "Deluxe 202")
    $mentionedRoom = findRoomByContextText($message);
    if ($mentionedRoom) {
        $rName = htmlspecialchars($mentionedRoom['name']);
        $rPrice = number_format((float)$mentionedRoom['price_per_night'], 2);
        $html = ($lang === 'en')
            ? "Here are the details for <strong>{$rName}</strong> (<strong>₱{$rPrice}/night</strong>, up to " . (int)$mentionedRoom['capacity'] . " pax). Click below to view full details or book!"
            : "Ito po ang detalye ng <strong>{$rName}</strong> (<strong>₱{$rPrice}/gabi</strong>, hanggang " . (int)$mentionedRoom['capacity'] . " pax). Pindutin ang button sa ibaba upang tingnan o i-book!";
        return ['html' => $html, 'intent' => 'room_lookup_local', 'room_ids' => [(int)$mentionedRoom['id']]];
    }

    // E. Default friendly website-scoped prompt
    $defaultHtml = ($lang === 'en')
        ? "Hello! I'm <strong>Marvi</strong>, the official AI concierge for the Marvelous Hotel &amp; Dormitory website. Ask me about our <strong>rooms &amp; rates</strong>, <strong>active promos</strong>, <strong>KYC verification</strong>, <strong>GCash/Maya payments</strong>, <strong>cancellations</strong>, or <strong>check-in/out times</strong>!"
        : "Kumusta po! Ako si <strong>Marvi</strong>, ang opisyal na AI concierge ng Marvelous Hotel &amp; Dormitory website. Maaari mo akong tanungin tungkol sa <strong>mga kwarto at presyo</strong>, <strong>active promos</strong>, <strong>KYC verification</strong>, <strong>GCash/Maya payment</strong>, <strong>cancellation</strong>, o <strong>check-in/out</strong>!";
    return ['html' => $defaultHtml, 'intent' => 'fallback', 'routes' => ['rooms']];
}

$aiEnabled = getSetting('ai_enabled', '1') === '1';
$detectedBudget = extractBudgetFromQuery($message);
$aiOptions = [
    'role'        => 'guest',
    'lang'        => $lang,
    'user_id'     => $userId,
    'history'     => $history,
    'max_tokens'  => 600,
    'temperature' => 0.35,
];

// ============================================================
// PATH A: REAL-TIME SSE STREAMING MODE
// ============================================================
if ($wantsStream) {
    // Release PHP session lock BEFORE opening upstream stream so concurrent tabs never stall
    releaseSessionLock();
    startSseStreamHeaders();

    emitSseEvent('meta', [
        'lang'   => $lang,
        'stream' => true,
    ]);

    $rawReply = null;
    $intent   = 'ai_live';
    $fallbackMeta = [];

    if ($aiEnabled) {
        $rawReply = streamNvidiaAI($message, function (string $delta): void {
            emitSseEvent('delta', ['text' => $delta]);
        }, $aiOptions);
    }

    if ($rawReply !== null && $rawReply !== '') {
        $parsed = parseAiActionMarkers($rawReply);
        $cleanMarkdown = $parsed['clean_text'];
        $sanitizedHtml = sanitizeAiHtml($cleanMarkdown);
        $actions = resolveAiActionsHtml($parsed, $message, $cleanMarkdown, $history, $lang, $isLoggedIn, $detectedBudget);
        $fullHtml = $sanitizedHtml . $actions['cards_html'] . $actions['buttons_html'];
        $plainForSpeech = aiPlainTextForSpeech($cleanMarkdown);
        $intent = ($detectedBudget !== null && !empty($actions['cards_html'])) ? 'budget_recommend_ai' : 'ai_live';
    } else {
        // Live AI disabled or unreachable -> seamless local fallback over SSE
        $fb = buildLocalFallbackReply($message, $lowerMsg, $lang, $userId, $history, $detectedBudget);
        $parsed = [
            'clean_text'   => $fb['html'],
            'room_ids'     => $fb['room_ids'] ?? [],
            'routes'       => $fb['routes'] ?? [],
            'room_queries' => [],
        ];
        $actions = resolveAiActionsHtml($parsed, $message, strip_tags($fb['html']), $history, $lang, $isLoggedIn, $detectedBudget);
        $fullHtml = $fb['html'] . $actions['cards_html'] . $actions['buttons_html'];
        $plainForSpeech = aiPlainTextForSpeech($fb['html']);
        $intent = $fb['intent'];
    }

    // Persist multi-turn conversation memory cleanly
    $history[] = ['role' => 'user', 'content' => $message];
    $history[] = ['role' => 'assistant', 'content' => $plainForSpeech];
    saveAiChatSessionTurn($sessId, $history, $lang);

    // Log to ai_logs safely
    if (isDbAvailable()) {
        try {
            dbExec(
                "INSERT INTO ai_logs (user_id, user_message, ai_response, intent, flagged) VALUES (?, ?, ?, ?, 0)",
                [$userId, $message, $fullHtml, $intent]
            );
        } catch (\Throwable $e) {}
    }

    $meta = getNvidiaLastMeta();
    emitSseEvent('done', [
        'full_html'  => $fullHtml,
        'plain_text' => $plainForSpeech,
        'source'     => $intent,
        'lang'       => $lang,
        'model'      => $meta['model'] ?? null,
        'latency_ms' => $meta['latency_ms'] ?? null,
    ]);
    exit;
}

// ============================================================
// PATH B: BACKWARDS-COMPATIBLE JSON MODE
// ============================================================
header('Content-Type: application/json; charset=utf-8');
releaseSessionLock();

$rawReply = $aiEnabled ? queryNvidiaAI($message, $aiOptions) : null;

if ($rawReply !== null && $rawReply !== '') {
    $parsed = parseAiActionMarkers($rawReply);
    $cleanMarkdown = $parsed['clean_text'];
    $sanitizedHtml = sanitizeAiHtml($cleanMarkdown);
    $actions = resolveAiActionsHtml($parsed, $message, $cleanMarkdown, $history, $lang, $isLoggedIn, $detectedBudget);
    $aiResponse = $sanitizedHtml . $actions['cards_html'] . $actions['buttons_html'];
    $plainForSpeech = aiPlainTextForSpeech($cleanMarkdown);
    $intent = ($detectedBudget !== null && !empty($actions['cards_html'])) ? 'budget_recommend_ai' : 'ai_live';
} else {
    $fb = buildLocalFallbackReply($message, $lowerMsg, $lang, $userId, $history, $detectedBudget);
    $parsed = [
        'clean_text'   => $fb['html'],
        'room_ids'     => $fb['room_ids'] ?? [],
        'routes'       => $fb['routes'] ?? [],
        'room_queries' => [],
    ];
    $actions = resolveAiActionsHtml($parsed, $message, strip_tags($fb['html']), $history, $lang, $isLoggedIn, $detectedBudget);
    $aiResponse = $fb['html'] . $actions['cards_html'] . $actions['buttons_html'];
    $plainForSpeech = aiPlainTextForSpeech($fb['html']);
    $intent = $fb['intent'];
}

$history[] = ['role' => 'user', 'content' => $message];
$history[] = ['role' => 'assistant', 'content' => $plainForSpeech];
saveAiChatSessionTurn($sessId, $history, $lang);

if (isDbAvailable()) {
    try {
        dbExec(
            "INSERT INTO ai_logs (user_id, user_message, ai_response, intent, flagged) VALUES (?, ?, ?, ?, 0)",
            [$userId, $message, $aiResponse, $intent]
        );
    } catch (\Throwable $e) {}
}

$meta = getNvidiaLastMeta();
echo json_encode([
    'status'     => 'success',
    'is_alert'   => false,
    'response'   => $aiResponse,
    'plain_text' => $plainForSpeech,
    'source'     => $intent,
    'lang'       => $lang,
    'model'      => $meta['model'] ?? null,
    'latency_ms' => $meta['latency_ms'] ?? null,
]);
