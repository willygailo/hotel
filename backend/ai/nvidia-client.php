<?php
// ============================================================
// MARVELOUS HOTEL — NVIDIA NIM Real-Time AI Engine Integration
// ai/nvidia-client.php
// ============================================================

if (!defined('SITE_ROOT')) {
    require_once __DIR__ . '/../core/config.php';
    require_once __DIR__ . '/../core/db.php';
    require_once __DIR__ . '/../core/functions.php';
}

if (!defined('NVIDIA_DEFAULT_MODEL')) {
    define('NVIDIA_DEFAULT_MODEL', 'nvidia/nemotron-3.5-bolt-e4b');
}

if (!function_exists('detectMessageLanguage')) {
    /**
     * Accurately detects whether a message is in English or Tagalog/Filipino.
     * Returns 'tl' for Tagalog/Taglish, 'en' for English.
     */
    function detectMessageLanguage(string $message): string {
        $clean = mb_strtolower(trim($message), 'UTF-8');
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean);
        $words = array_filter(explode(' ', $clean));
        if (empty($words)) return 'en';

        $tagalogMarkers = [
            'ang', 'mga', 'ng', 'sa', 'at', 'ay', 'na', 'pa', 'po', 'opo', 'ho', 'oho',
            'ba', 'ko', 'mo', 'ka', 'niya', 'nila', 'kami', 'tayo', 'kayo', 'natin', 'namin',
            'ano', 'anong', 'paano', 'pano', 'magkano', 'kano', 'saan', 'san', 'kailan', 'kelan',
            'sino', 'bakit', 'meron', 'mayroon', 'may', 'wala', 'walang', 'merong',
            'pwede', 'pede', 'maaari', 'maari', 'dapat', 'gusto', 'nais', 'kailangan',
            'kwarto', 'kuwarto', 'oras', 'bayad', 'magbayad', 'bayaran', 'araw', 'gabi',
            'tanghali', 'umaga', 'hapon', 'salamat', 'kamusta', 'kumusta', 'tulong',
            'rekomenda', 'mura', 'murang', 'mahal', 'tulugan', 'gamit', 'dito', 'doon', 'dyan',
            'ninyo', 'inyo', 'aming', 'atin', 'nandito', 'nasaan', 'tumatanggap', 'subalit',
            'pero', 'habang', 'kapag', 'kung', 'tapos', 'tsaka', 'kasi', 'kase', 'naman', 'nga',
            'punta', 'ipunta', 'dalhin', 'muba', 'sige', 'oo', 'hindi', 'yun', 'iyon', 'yan'
        ];

        $tagalogCount = 0;
        foreach ($words as $word) {
            if (in_array($word, $tagalogMarkers, true)) {
                $tagalogCount++;
            }
        }
        if ($tagalogCount === 0) {
            foreach ($words as $word) {
                if (preg_match('/^(mag|nag|pag|um|makapag|napaka|pinaka)/i', $word) && strlen($word) > 4) {
                    $tagalogCount++;
                }
            }
        }

        return ($tagalogCount > 0) ? 'tl' : 'en';
    }
}

/**
 * Retrieve metadata & diagnostic info from the most recent NVIDIA NIM API call.
 */
function getNvidiaLastMeta(): array {
    return $GLOBALS['__nvidia_last_meta'] ?? [
        'ok'         => false,
        'model'      => null,
        'http_code'  => 0,
        'latency_ms' => 0,
        'error'      => null,
    ];
}

/**
 * Resolve NVIDIA API key and ordered active model fallback list.
 */
function resolveNvidiaConfig(?string $overrideModel = null): array {
    $envKey   = trim((string)(getenv('NVIDIA_API_KEY') ?: ''));
    $envModel = trim((string)(getenv('NVIDIA_MODEL') ?: ''));

    $apiKey = $envKey;
    $model  = $envModel ?: NVIDIA_DEFAULT_MODEL;

    try {
        if (function_exists('getSetting')) {
            if ($apiKey === '') {
                $apiKey = trim((string)getSetting('ai_nvidia_key', ''));
            }
            $dbModel = trim((string)getSetting('ai_nvidia_model', ''));
            if ($dbModel !== '') {
                $model = $dbModel;
            }
        }
    } catch (\Throwable $e) {
        // Keep env/default values
    }

    if ($overrideModel !== null && trim($overrideModel) !== '') {
        $model = trim($overrideModel);
    }

    // Known EOL models are automatically bypassed in favor of active live models
    $eolModels = ['google/diffusiongemma-26b-a4b-it'];
    if (in_array($model, $eolModels, true)) {
        $model = NVIDIA_DEFAULT_MODEL;
    }

    $candidates = array_values(array_unique(array_filter([
        $model,
        NVIDIA_DEFAULT_MODEL,
        'nvidia/nemotron-3.5-lightning-30b-a3b',
        'minimaxai/minimax-m3.1-flash',
    ])));

    return [
        'api_key'    => $apiKey,
        'primary'    => $model,
        'candidates' => $candidates,
    ];
}

/**
 * Build live room catalog (with Room IDs) & unavailable room lines from MySQL.
 */
function buildDynamicRoomCatalogContext(): array {
    $catalogLines = [];
    $bookedRoomNames = [];
    $rooms = [];

    if (function_exists('isDbAvailable') && isDbAvailable() && function_exists('dbRows')) {
        try {
            if (function_exists('throttleStaleBookingSweep')) {
                throttleStaleBookingSweep(24, 15);
            }
            $rooms = dbRows("
                SELECT r.id, r.room_number, r.name, r.floor, r.capacity, r.price_per_night, r.status, r.amenities, r.thumbnail, rt.name AS type_name
                FROM rooms r
                JOIN room_types rt ON r.room_type_id = rt.id
                ORDER BY r.price_per_night ASC, r.room_number ASC
            ");

            $activeBookedIds = [];
            $bookedRows = dbRows("
                SELECT DISTINCT r.id, r.name, r.room_number
                FROM rooms r
                JOIN bookings b ON r.id = b.room_id
                WHERE b.status IN ('pending', 'confirmed', 'checked_in')
                   OR r.status IN ('occupied', 'reserved', 'maintenance')
            ");
            foreach ($bookedRows as $br) {
                $activeBookedIds[(int)$br['id']] = true;
                $bookedRoomNames[] = $br['name'] . ' (Room ' . $br['room_number'] . ')';
            }

            foreach ($rooms as $r) {
                $priceFmt = number_format((float)$r['price_per_night'], 0);
                $availTag = isset($activeBookedIds[(int)$r['id']]) ? 'has active booking/check dates' : $r['status'];
                $amList = json_decode((string)($r['amenities'] ?? '[]'), true);
                $amText = is_array($amList) && !empty($amList) ? ' | Amenities: ' . implode(', ', array_slice($amList, 0, 5)) : '';
                $catalogLines[] = "- [ID #{$r['id']}] {$r['name']} (Room {$r['room_number']}, {$r['type_name']}): ₱{$priceFmt}/night, up to {$r['capacity']} pax, status: {$availTag}{$amText}";
            }
        } catch (\Throwable $e) {
            // Fall through to static defaults
        }
    }

    if (empty($catalogLines)) {
        $catalogLines = [
            '- [ID #7] Dormitory Bed - Female Wing (Room D01, Dormitory): ₱350/night, up to 1 pax (Air Con, lockers, WiFi, shared bathroom).',
            '- [ID #8] Dormitory Bed - Male Wing (Room D02, Dormitory): ₱350/night, up to 1 pax (Air Con, lockers, WiFi, shared bathroom).',
            '- [ID #1] Standard Room 101 (Room 101, Standard Room): ₱800/night, up to 2 pax (Private room, Air Con, TV, WiFi, Hot Shower, Ref).',
            '- [ID #2] Standard Room 102 (Room 102, Standard Room): ₱800/night, up to 2 pax.',
            '- [ID #3] Deluxe Room 201 (Room 201, Deluxe Room): ₱1,500/night, up to 2 pax (King bed, Smart TV, Safe box, Breakfast).',
            '- [ID #4] Deluxe Room 202 (Room 202, Deluxe Room): ₱1,600/night, up to 2 pax (King bed, Balcony, Smart TV, Breakfast).',
            '- [ID #9] Studio Unit 01 (Room S01, Studio): ₱1,800/night, up to 2 pax (Kitchenette, workspace, Smart TV).',
            '- [ID #6] Family Room 401 (Room 401, Family Room): ₱2,200/night, up to 4 pax (Two double beds, spacious).',
            '- [ID #5] Executive Suite 301 (Room 301, Suite): ₱3,500/night, up to 4 pax (Hot tub, mini bar, panoramic view).'
        ];
    }

    $bookedNotice = !empty($bookedRoomNames)
        ? 'ROOMS WITH ACTIVE RESERVATIONS / UNAVAILABLE TODAY: ' . implode(', ', $bookedRoomNames) . '. Prefer open available rooms and advise checking exact dates before booking.'
        : 'All listed active rooms can be checked for date availability.';

    return [
        'catalog'       => implode("\n   ", $catalogLines),
        'booked_notice' => $bookedNotice,
        'rooms'         => $rooms,
    ];
}

/**
 * Build strictly scoped live account context for the currently logged-in guest only.
 */
function buildGuestUserContext(?int $userId): string {
    if (!$userId || !function_exists('isDbAvailable') || !isDbAvailable() || !function_exists('dbRow')) {
        return "GUEST ACCOUNT STATUS: Visitor (not logged in).";
    }

    try {
        $u = dbRow("SELECT first_name, last_name, verification_status, profile_verified FROM users WHERE id = ? AND role = 'user' LIMIT 1", [$userId]);
        if (!$u) {
            return "GUEST ACCOUNT STATUS: Visitor.";
        }
        $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        $kyc  = $u['verification_status'] ?? (!empty($u['profile_verified']) ? 'verified' : 'unverified');

        $bookings = dbRows("
            SELECT b.id, b.booking_code, b.check_in, b.check_out, b.status, b.total_amount,
                   r.name AS room_name, r.room_number,
                   (SELECT p.status FROM payments p WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) AS payment_status
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            WHERE b.user_id = ?
            ORDER BY b.id DESC
            LIMIT 3
        ", [$userId]);

        $lines = [
            "CURRENT LOGGED-IN GUEST: {$name} (KYC Identity Status: {$kyc})."
        ];
        if (empty($bookings)) {
            $lines[] = "RECENT BOOKINGS: None yet.";
        } else {
            $lines[] = "RECENT BOOKINGS FOR THIS GUEST ONLY:";
            foreach ($bookings as $b) {
                $pay = $b['payment_status'] ?: 'unpaid';
                $amt = number_format((float)$b['total_amount'], 2);
                $lines[] = "  - Booking #{$b['booking_code']}: {$b['room_name']} (Room {$b['room_number']}), {$b['check_in']} to {$b['check_out']}, status={$b['status']}, payment={$pay}, total=₱{$amt}";
            }
        }
        return implode("\n", $lines);
    } catch (\Throwable $e) {
        return "GUEST ACCOUNT STATUS: Logged in guest.";
    }
}

/**
 * Build Guest Concierge system prompt with live room IDs, user context, and action tags.
 */
function buildGuestSystemPrompt(string $lang, ?int $userId = null): string {
    if ($lang === 'en') {
        $langDirective = <<<LANG
============================================================
CRITICAL LANGUAGE DIRECTIVE (MANDATORY RULE):
THE USER IS ASKING IN ENGLISH.
YOU MUST RESPOND 100% IN NATURAL, POLITE, PROFESSIONAL ENGLISH.
DO NOT USE ANY TAGALOG OR FILIPINO WORDS (ABSOLUTELY NO 'po', 'opo', 'kumusta', 'magandang araw', etc.).
KEEP ALL POLICIES, INSTRUCTIONS, AND GREETINGS ENTIRELY IN ENGLISH.
============================================================
LANG;
    } else {
        $langDirective = <<<LANG
============================================================
CRITICAL LANGUAGE DIRECTIVE (MANDATORY RULE):
ANG USER AY NAGTATANONG SA TAGALOG / FILIPINO.
SAGUTIN ITO NANG BUO SA NATURAL, MAGALANG, AT MALINAW NA TAGALOG / FILIPINO (O TAGLISH).
GUMAMIT NG MAGAGALANG NA PANANALITA TULAD NG 'PO' AT 'OPO'.
============================================================
LANG;
    }

    $mayaName     = function_exists('getSetting') ? getSetting('hotel_maya_name', 'WILLY GAILO') : 'WILLY GAILO';
    $mayaHandle   = function_exists('getSetting') ? getSetting('hotel_maya_handle', '@willygailojr') : '@willygailojr';
    $mayaPhone    = function_exists('getSetting') ? getSetting('hotel_maya_phone', '+63 *** *** 6963') : '+63 *** *** 6963';
    $gcashNum     = function_exists('getSetting') ? getSetting('hotel_gcash', '0912 345 6789') : '0912 345 6789';
    $checkInTime  = function_exists('getSetting') ? getSetting('check_in_time', '14:00') : '14:00';
    $checkOutTime = function_exists('getSetting') ? getSetting('check_out_time', '12:00') : '12:00';
    $hotelAddress = function_exists('getSetting') ? getSetting('hotel_address', 'Quezon City, Philippines') : 'Quezon City, Philippines';
    $hotelPhone   = function_exists('getSetting') ? getSetting('hotel_phone', 'Frontdesk 24/7') : 'Frontdesk 24/7';

    $roomContext = buildDynamicRoomCatalogContext();
    $userContext = buildGuestUserContext($userId);

    return <<<PROMPT
{$langDirective}

You are Marvi, the friendly, conversational, real-time AI concierge of Marvelous Hotel and Dormitory.
Pay close attention to the previous messages in the conversation! When the guest says follow-ups like "can you take me there?", "maaari mo ba akong ipunta doon?", "book ko na yan", or "how much for 3 nights?", understand what room or topic they are referring to from earlier messages and respond directly and helpfully.

{$userContext}

HOTEL INFORMATION & STRICT POLICIES:
1. LOCATION & CONTACT:
   - Address: {$hotelAddress} | Contact: {$hotelPhone}
   - Easily accessible via taxi, Grab, tricycle, or jeepney, with 24/7 Frontdesk and security.

2. PAYMENT METHODS:
   - We accept STRICTLY PayMaya (Maya) and GCash ONLY.
   - We DO NOT accept Bank Transfer or over-the-counter cash.
   - Official Maya (PayMaya) Account: {$mayaName} ({$mayaHandle} / {$mayaPhone}), with InstaPay QR scannable via Maya, GCash (Scan QR), or PH bank apps.
   - Official GCash: {$gcashNum}.
   - How to Pay: Send via Maya/GCash -> copy Reference Number & screenshot receipt -> go to My Bookings -> Pay -> upload proof for verification.

3. HOW TO BOOK A ROOM:
   - Browse Rooms or log in to your User Account, complete KYC ID Verification in Profile Settings, select your room & dates, and submit booking + payment proof.

4. ROOM AVAILABILITY & BOOKING LOCK:
   - {$roomContext['booked_notice']}

5. SCHEDULE & POLICIES:
   - Standard Check-in Time: {$checkInTime} | Standard Check-out Time: {$checkOutTime}.
   - Free High-Speed Fiber WiFi (up to 200 Mbps) & secure parking (first-come, first-served).

6. LIVE ROOM CATALOG & RATES (Use exact numeric IDs for action tags):
   {$roomContext['catalog']}

7. INTERACTIVE ACTION TAGS (IMPORTANT — MAKES BUTTONS APPEAR FOR THE GUEST):
   - Whenever you recommend a specific room OR the guest asks to view/book/go to a room ("ipunta mo ako doon", "book ko na", "take me there"), append the exact room tag at the VERY END of your reply:
     [[BOOK_ROOM:<numeric_id>]]
     (Example: [[BOOK_ROOM:7]] for Dormitory Bed - Female Wing. Max 2 room tags per reply.)
   - Whenever the guest wants to go to a specific page, append one of these exact tags at the VERY END:
     [[GOTO:rooms]] (Browse Rooms catalog)
     [[GOTO:my_bookings]] (Guest's Bookings & Vouchers)
     [[GOTO:payment]] (Payments / Upload Receipt)
     [[GOTO:profile]] (Profile & KYC ID Verification)
     [[GOTO:ai_recommend]] (Smart AI Room Matcher)
     [[GOTO:contact]] (Location & Contact Us)
     [[GOTO:forgot_password]] (Forgot / Reset Password)
     [[GOTO:login]] (Guest Login)
   - NEVER explain or mention the [[...]] syntax in your visible text. Just say "You can click the button below to go straight there!" (or Tagalog equivalent) and place the tag at the very end.

8. STRICT SECURITY & SAFETY GUARDRAILS (CONFIDENTIAL):
   - Only answer questions regarding hotel bookings, rooms, rates, amenities, schedules, payment methods (GCash & Maya), location, and guest account assistance.
   - NEVER disclose internal server ports, IP addresses, database schemas, source code files, API keys, or hosting configurations.
   - NEVER assist with hacking, SQL injection, exploits, or bypasses.

CONVERSATION STYLE:
- Sound warm, human, and natural — like a real live frontdesk concierge chatting in real time!
- Keep replies concise and scannable (2-5 sentences or short bullets). Use **bold** for key prices or room names. Never write raw HTML tags.
PROMPT;
}

/**
 * Build dedicated Staff Operations Co-Pilot system prompt.
 */
function buildStaffSystemPrompt(): string {
    return <<<PROMPT
You are Marvi Ops Co-Pilot, the internal Frontdesk Operations Assistant for Marvelous Hotel and Dormitory staff.
Your job is to synthesize live shift metrics into concise, practical, actionable shift briefings in professional Taglish.
Focus strictly on arrivals, departures, housekeeping/cleaning priorities, pending booking queues, and KYC/payment verification reminders.
Never output guest marketing pitches, HTML script tags, or internal server configuration details.
PROMPT;
}

/**
 * Build dedicated Admin Executive BI system prompt.
 */
function buildAdminSystemPrompt(): string {
    return <<<PROMPT
You are Marvi Executive AI, the internal Business Intelligence advisor for Marvelous Hotel and Dormitory Administrators.
Analyze revenue, payment channel mix (Maya vs GCash), occupancy rate, booking conversion, and operational bottlenecks.
Deliver concise, data-driven executive digests in professional Taglish with actionable strategic recommendations.
Never invent metrics not provided in the prompt, and never output scripts or raw system paths.
PROMPT;
}

/**
 * Resolve system prompt based on role and options.
 */
function resolveNvidiaSystemPrompt(string $userMessage, array $options): string {
    $role = $options['role'] ?? 'guest';
    if (!empty($options['system_prompt'])) {
        return (string)$options['system_prompt'];
    }
    if ($role === 'staff') {
        return buildStaffSystemPrompt();
    }
    if ($role === 'admin') {
        return buildAdminSystemPrompt();
    }
    $lang = $options['lang'] ?? detectMessageLanguage($userMessage);
    $userId = isset($options['user_id']) ? (int)$options['user_id'] : (function_exists('currentUserId') ? currentUserId() : null);
    return buildGuestSystemPrompt($lang, $userId ?: null);
}

/**
 * Build chat completions payload with multi-turn history and fast non-thinking overrides where supported.
 */
function buildNvidiaRequestPayload(string $model, string $systemPrompt, string $userMessage, array $options, bool $stream): array {
    $messages = [['role' => 'system', 'content' => $systemPrompt]];

    if (!empty($options['history']) && is_array($options['history'])) {
        $normalized = function_exists('normalizeAiChatHistory')
            ? normalizeAiChatHistory($options['history'], 10, 600)
            : [];
        foreach ($normalized as $turn) {
            $messages[] = $turn;
        }
    }

    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'max_tokens'  => (int)($options['max_tokens'] ?? 512),
        'temperature' => (float)($options['temperature'] ?? 0.55),
        'top_p'       => 0.92,
        'stream'      => $stream,
    ];

    // Disable hidden reasoning trace on reasoning-capable models for snappy real-time chat
    if (preg_match('/(lightning|minimax|glm|kimi|step)/i', $model)) {
        $payload['chat_template_kwargs'] = ['enable_thinking' => false];
    }

    return $payload;
}

/**
 * Parse HTTP status code from PHP's $http_response_header array.
 */
function parseHttpStatusFromHeaders(array $headers): int {
    if (!empty($headers[0]) && preg_match('/HTTP\/\S+\s+(\d{3})/', $headers[0], $m)) {
        return (int)$m[1];
    }
    return 0;
}

/**
 * Send a non-streaming message to NVIDIA NIM API with multi-turn memory and automatic model fallback.
 *
 * @param string $userMessage
 * @param array $options Supported keys: 'role', 'lang', 'user_id', 'history', 'system_prompt', 'max_tokens', 'temperature', 'model'
 * @return string|null Response string on success, or null on error/fallback
 */
function queryNvidiaAI(string $userMessage, array $options = []): ?string {
    $cfg = resolveNvidiaConfig($options['model'] ?? null);
    $apiKey = $cfg['api_key'];
    if ($apiKey === '') {
        $GLOBALS['__nvidia_last_meta'] = ['ok' => false, 'model' => null, 'http_code' => 0, 'latency_ms' => 0, 'error' => 'NVIDIA API key is not configured.'];
        return null;
    }

    $url = 'https://integrate.api.nvidia.com/v1/chat/completions';
    $systemPrompt = resolveNvidiaSystemPrompt($userMessage, $options);
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    foreach ($cfg['candidates'] as $model) {
        $t0 = microtime(true);
        $jsonPayload = json_encode(buildNvidiaRequestPayload($model, $systemPrompt, $userMessage, $options, false));
        $httpCode = 0;
        $raw = null;
        $errMsg = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_TIMEOUT        => 14,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            if ($curlErr) {
                $errMsg = $curlErr;
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => implode("\r\n", $headers) . "\r\n",
                    'content'       => $jsonPayload,
                    'timeout'       => 14,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true
                ]
            ]);
            $http_response_header = [];
            $raw = @file_get_contents($url, false, $context);
            $httpCode = parseHttpStatusFromHeaders($http_response_header);
            if ($raw === false) {
                $errMsg = 'HTTP stream request failed or timed out.';
            }
        }

        $latencyMs = (int)round((microtime(true) - $t0) * 1000);

        if ($httpCode === 200 && is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true);
            $content = trim((string)($data['choices'][0]['message']['content'] ?? ''));
            if ($content !== '') {
                $GLOBALS['__nvidia_last_meta'] = [
                    'ok'         => true,
                    'model'      => $model,
                    'http_code'  => 200,
                    'latency_ms' => $latencyMs,
                    'error'      => null,
                ];
                return $content;
            }
        }

        // Extract API error detail for diagnostics
        $detail = $errMsg;
        if (is_string($raw) && $raw !== '') {
            $errJson = json_decode($raw, true);
            $detail = $errJson['detail'] ?? ($errJson['error']['message'] ?? substr($raw, 0, 180));
        }
        $GLOBALS['__nvidia_last_meta'] = [
            'ok'         => false,
            'model'      => $model,
            'http_code'  => $httpCode,
            'latency_ms' => $latencyMs,
            'error'      => $detail ?: "HTTP {$httpCode}",
        ];
        error_log("Marvi AI error [{$model}] HTTP {$httpCode} ({$latencyMs}ms): " . ($detail ?: 'unknown'));

        // Only fail over on model/server unavailability or timeout; stop immediately on 401/403 bad key
        if (in_array($httpCode, [401, 403], true)) {
            break;
        }
    }

    return null;
}

/**
 * Stream a real-time chat completion from NVIDIA NIM via Server-Sent Events (SSE).
 * Invokes $onDelta(string $chunk) as tokens arrive. Works with or without php-curl.
 *
 * @return string|null Complete accumulated text on success, or null if all models failed before output
 */
function streamNvidiaAI(string $userMessage, callable $onDelta, array $options = []): ?string {
    $cfg = resolveNvidiaConfig($options['model'] ?? null);
    $apiKey = $cfg['api_key'];
    if ($apiKey === '') {
        $GLOBALS['__nvidia_last_meta'] = ['ok' => false, 'model' => null, 'http_code' => 0, 'latency_ms' => 0, 'error' => 'NVIDIA API key is not configured.'];
        return null;
    }

    $url = 'https://integrate.api.nvidia.com/v1/chat/completions';
    $systemPrompt = resolveNvidiaSystemPrompt($userMessage, $options);
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: text/event-stream'
    ];

    foreach ($cfg['candidates'] as $model) {
        $t0 = microtime(true);
        $jsonPayload = json_encode(buildNvidiaRequestPayload($model, $systemPrompt, $userMessage, $options, true));

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers) . "\r\n",
                'content'       => $jsonPayload,
                'timeout'       => 18,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true
            ]
        ]);

        $http_response_header = [];
        $fp = @fopen($url, 'r', false, $context);
        $httpCode = parseHttpStatusFromHeaders($http_response_header);

        if (!$fp || $httpCode !== 200) {
            $errBody = '';
            if ($fp) {
                $errBody = (string)stream_get_contents($fp, 400);
                fclose($fp);
            }
            $latencyMs = (int)round((microtime(true) - $t0) * 1000);
            $errJson = json_decode($errBody, true);
            $detail = $errJson['detail'] ?? ($errJson['error']['message'] ?? ($errBody ?: "HTTP {$httpCode}"));
            $GLOBALS['__nvidia_last_meta'] = [
                'ok'         => false,
                'model'      => $model,
                'http_code'  => $httpCode,
                'latency_ms' => $latencyMs,
                'error'      => $detail,
            ];
            error_log("Marvi AI stream error [{$model}] HTTP {$httpCode} ({$latencyMs}ms): {$detail}");

            if (in_array($httpCode, [401, 403], true)) {
                break;
            }
            continue;
        }

        stream_set_timeout($fp, 20);
        $accumulated = '';

        while (!feof($fp)) {
            if (function_exists('connection_aborted') && connection_aborted()) {
                break;
            }
            $line = fgets($fp);
            if ($line === false) {
                break;
            }
            $trimmed = trim($line);
            if ($trimmed === '' || !str_starts_with($trimmed, 'data:')) {
                continue;
            }
            $dataStr = trim(substr($trimmed, 5));
            if ($dataStr === '[DONE]') {
                break;
            }
            $chunk = json_decode($dataStr, true);
            if (!is_array($chunk)) {
                continue;
            }
            $delta = (string)($chunk['choices'][0]['delta']['content'] ?? '');
            if ($delta !== '') {
                $accumulated .= $delta;
                $onDelta($delta);
            }
        }

        fclose($fp);
        $latencyMs = (int)round((microtime(true) - $t0) * 1000);

        if (trim($accumulated) !== '') {
            $GLOBALS['__nvidia_last_meta'] = [
                'ok'         => true,
                'model'      => $model,
                'http_code'  => 200,
                'latency_ms' => $latencyMs,
                'error'      => null,
            ];
            return trim($accumulated);
        }
    }

    return null;
}

/**
 * Shared short-TTL JSON cache helper for AI dashboard digests (avoids blocking dashboards on every reload).
 */
function getAiResponseCache(string $key, int $ttlSeconds = 300): ?array {
    $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    $path = SITE_ROOT . '/storage/cache/' . $safeKey . '.json';
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    if ((time() - (@filemtime($path) ?: 0)) > $ttlSeconds) {
        return null;
    }
    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : null;
}

function setAiResponseCache(string $key, array $payload): void {
    $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    $dir = SITE_ROOT . '/storage/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    @file_put_contents($dir . '/' . $safeKey . '.json', json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
