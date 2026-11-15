<?php
// ============================================================
// MARVELOUS HOTEL — Helper Functions
// includes/functions.php
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

// ---- Sanitize output ----
function h(?string $str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ---- Generate unique booking code ----
function generateBookingCode(): string {
    do {
        $code = 'MH-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        try {
            $exists = dbRow("SELECT id FROM bookings WHERE booking_code = ?", [$code]);
        } catch (\Throwable $e) {
            $exists = false;
        }
    } while ($exists);
    return $code;
}

// ---- Format PHP currency ----
function formatCurrency(float $amount): string {
    return '₱ ' . number_format($amount, 2);
}

// ---- Format date for display ----
function formatDate(string $date, string $format = 'M d, Y'): string {
    return date($format, strtotime($date));
}

// ---- Calculate nights between two dates ----
function calculateNights(string $checkIn, string $checkOut): int {
    $d1 = new DateTime($checkIn);
    $d2 = new DateTime($checkOut);
    return max(1, (int) $d1->diff($d2)->days);
}

// ---- Check date range overlap (standard half-open interval [in, out)) ----
function datesOverlap(string $in1, string $out1, string $in2, string $out2): bool {
    return ($in1 < $out2 && $out1 > $in2);
}

// ---- Find specific conflicting booking (if any) ----
function findConflictingBooking(int $roomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): ?array {
    $sql = "SELECT b.id, b.booking_code, b.room_id, b.check_in, b.check_out, b.status, r.name as room_name, r.room_number
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            WHERE b.room_id = ? 
              AND b.status NOT IN ('cancelled','rejected')
              AND b.check_in < ? AND b.check_out > ?";
    $params = [$roomId, $checkOut, $checkIn];
    if ($excludeBookingId) {
        $sql .= " AND b.id != ?";
        $params[] = $excludeBookingId;
    }
    $sql .= " ORDER BY b.check_in ASC LIMIT 1";
    return dbRow($sql, $params);
}

// ---- Get room availability ----
function isRoomAvailable(int $roomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool {
    return findConflictingBooking($roomId, $checkIn, $checkOut, $excludeBookingId) === null;
}

// ---- Get active future/current bookings for a specific room ----
function getRoomActiveBookings(int $roomId, ?int $excludeBookingId = null): array {
    $sql = "SELECT id, booking_code, check_in, check_out, status, nights
            FROM bookings
            WHERE room_id = ?
              AND status NOT IN ('cancelled', 'rejected')
              AND check_out >= CURDATE()
            ORDER BY check_in ASC";
    $params = [$roomId];
    if ($excludeBookingId) {
        $sql .= " AND id != ?";
        $params[] = $excludeBookingId;
    }
    $rows = dbRows($sql, $params);
    foreach ($rows as &$r) {
        $r['display_dates'] = formatDate($r['check_in'], 'M d, Y') . ' — ' . formatDate($r['check_out'], 'M d, Y');
    }
    unset($r);
    return $rows;
}

// ---- Get active bookings map for all rooms (indexed by room_id) ----
function getAllRoomsActiveBookingsMap(): array {
    $rows = dbRows("SELECT b.id, b.room_id, b.booking_code, b.check_in, b.check_out, b.status, b.nights
                    FROM bookings b
                    WHERE b.status NOT IN ('cancelled', 'rejected')
                      AND b.check_out >= CURDATE()
                    ORDER BY b.check_in ASC");
    $map = [];
    foreach ($rows as $r) {
        $rid = (int)$r['room_id'];
        if (!isset($map[$rid])) {
            $map[$rid] = [];
        }
        $r['display_dates'] = formatDate($r['check_in'], 'M d, Y') . ' — ' . formatDate($r['check_out'], 'M d, Y');
        $map[$rid][] = $r;
    }
    return $map;
}

// ---- Determine real-time occupancy and booking status of a room ----
function getRoomOccupancyStatus(int $roomId, string $roomBaseStatus = 'available', ?array $preloadedBookings = null): array {
    if ($roomBaseStatus === 'maintenance') {
        return [
            'state'        => 'maintenance',
            'badge_class'  => 'badge-warning',
            'label'        => 'Maintenance',
            'subtitle'     => 'Temporarily closed for maintenance',
            'tag_html'     => '<span class="badge badge-warning"><i class="fa-solid fa-wrench"></i> Maintenance</span>',
            'is_bookable'  => false,
            'active_now'   => false,
            'next_booking' => null,
            'bookings'     => [],
        ];
    }

    $activeBookings = $preloadedBookings !== null ? $preloadedBookings : getRoomActiveBookings($roomId);
    $today = date('Y-m-d');

    // 1. Is it currently occupied today? (check_in <= today < check_out)
    foreach ($activeBookings as $b) {
        if ($b['check_in'] <= $today && $b['check_out'] > $today) {
            $untilDate = formatDate($b['check_out'], 'M d, Y');
            return [
                'state'         => 'occupied',
                'badge_class'   => 'badge-danger',
                'label'         => 'Occupied / Booked',
                'subtitle'      => 'Occupied until ' . $untilDate,
                'tag_html'      => '<span class="badge badge-danger" title="Currently occupied until ' . $untilDate . '"><i class="fa-solid fa-bed"></i> Booked until ' . formatDate($b['check_out'], 'M d') . '</span>',
                'is_bookable'   => true, // Can still be reserved for future dates after check_out
                'active_now'    => true,
                'current_until' => $b['check_out'],
                'next_booking'  => $b,
                'bookings'      => $activeBookings,
            ];
        }
    }

    // 2. Does it have upcoming reservations?
    if (!empty($activeBookings)) {
        $next = $activeBookings[0];
        $fromDate = formatDate($next['check_in'], 'M d');
        $toDate   = formatDate($next['check_out'], 'M d');
        return [
            'state'        => 'reserved',
            'badge_class'  => 'badge-warning',
            'label'        => 'Reserved (' . $fromDate . ' - ' . $toDate . ')',
            'subtitle'     => 'Reserved: ' . $fromDate . ' - ' . $toDate . ' · Other dates open',
            'tag_html'     => '<span class="badge" style="background:rgba(234,179,8,0.22);color:#fde047;border:1px solid rgba(234,179,8,0.4);" title="Reserved ' . $fromDate . ' - ' . $toDate . '"><i class="fa-solid fa-calendar-check"></i> Booked ' . $fromDate . '–' . $toDate . '</span>',
            'is_bookable'  => true,
            'active_now'   => false,
            'next_booking' => $next,
            'bookings'     => $activeBookings,
        ];
    }

    // 3. Fully available now
    return [
        'state'        => 'available',
        'badge_class'  => 'badge-success',
        'label'        => 'Available',
        'subtitle'     => 'Ready for instant check-in',
        'tag_html'     => '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Available</span>',
        'is_bookable'  => true,
        'active_now'   => false,
        'next_booking' => null,
        'bookings'     => [],
    ];
}

// ---- Build status badge HTML ----
function statusBadge(string $status): string {
    $map = [
        'pending'     => ['badge-warning',  'Pending'],
        'confirmed'   => ['badge-info',     'Confirmed'],
        'checked_in'  => ['badge-primary',  'Checked In'],
        'checked_out' => ['badge-secondary','Checked Out'],
        'cancelled'   => ['badge-danger',   'Cancelled'],
        'rejected'    => ['badge-danger',   'Rejected'],
        'verified'    => ['badge-success',  'Verified'],
        'available'   => ['badge-success',  'Available'],
        'occupied'    => ['badge-danger',   'Occupied'],
        'maintenance' => ['badge-warning',  'Maintenance'],
        'active'      => ['badge-success',  'Active'],
        'inactive'    => ['badge-secondary','Inactive'],
        'banned'      => ['badge-danger',   'Banned'],
        'approved'    => ['badge-success',  'Approved'],
        'hidden'      => ['badge-secondary','Hidden'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-secondary', ucfirst($status)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

// ---- Get star rating HTML ----
function starRating(int $rating, int $max = 5): string {
    $html = '<span class="stars">';
    for ($i = 1; $i <= $max; $i++) {
        $html .= '<i class="fa-star ' . ($i <= $rating ? 'fa-solid' : 'fa-regular') . '"></i>';
    }
    $html .= '</span>';
    return $html;
}

// ---- Create notification for a user ----
function createNotification(int $userId, string $title, string $message, string $type = 'system', string $link = '', string $icon = 'fa-bell'): void {
    $allowedTypes = ['booking', 'payment', 'review', 'system', 'alert'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'system';
    }
    dbExec(
        "INSERT INTO notifications (user_id, title, message, type, link, icon) VALUES (?,?,?,?,?,?)",
        [$userId, $title, $message, $type, $link, $icon]
    );
}

// ---- Count unread notifications ----
function unreadNotifCount(?int $userId = null): int {
    $uid = $userId ?? currentUserId();
    if (!$uid) return 0;
    $row = dbRow("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0", [$uid]);
    return (int) ($row['cnt'] ?? 0);
}

// ---- Secure Upload file helper ----
function uploadFile(array $file, string $destination, array $allowedTypes = [], int $maxSize = 0, ?string &$errorMsg = null): string|false {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file is too large for the server. Maximum file size is ' . ($maxSize ? round($maxSize / (1024 * 1024)) : 15) . 'MB.',
            UPLOAD_ERR_PARTIAL => 'File upload was interrupted. Please check your internet and try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            default => 'Upload error (code ' . $file['error'] . ').'
        };
        error_log('Upload error: ' . $file['error'] . ' - ' . $errorMsg);
        return false;
    }

    // Check file size
    if ($maxSize && $file['size'] > $maxSize) {
        $fileSizeMb = round($file['size'] / (1024 * 1024), 2);
        $maxMb = round($maxSize / (1024 * 1024));
        $errorMsg = "File size ({$fileSizeMb}MB) exceeds the maximum allowed {$maxMb}MB.";
        error_log('File too large: ' . $file['size'] . ' > ' . $maxSize);
        return false;
    }

    // Detect MIME type using finfo (more reliable than mime_content_type)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Fallback detection for mobile phone cameras or generic streams
    if (!$mime || $mime === 'application/octet-stream') {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo && !empty($imageInfo['mime'])) {
            $mime = $imageInfo['mime'];
        } elseif (is_file($file['tmp_name'])) {
            $header = @file_get_contents($file['tmp_name'], false, null, 0, 5);
            if ($header && str_starts_with($header, '%PDF')) {
                $mime = 'application/pdf';
            }
        }
    }

    if (!$mime) {
        $errorMsg = 'Could not detect the file format. Please upload a standard JPG, PNG, or PDF.';
        error_log('Could not detect MIME type');
        return false;
    }

    // Normalize and validate MIME type against allowed list
    if ($allowedTypes) {
        // Expand common variations
        $expandedAllowed = $allowedTypes;
        if (in_array('image/jpeg', $allowedTypes, true)) {
            $expandedAllowed[] = 'image/pjpeg';
            $expandedAllowed[] = 'image/jpg';
        }
        if (in_array('image/png', $allowedTypes, true)) {
            $expandedAllowed[] = 'image/x-png';
        }
        if (in_array('application/pdf', $allowedTypes, true)) {
            $expandedAllowed[] = 'application/x-pdf';
        }

        if (!in_array($mime, $expandedAllowed, true)) {
            $errorMsg = 'Unsupported file format (Detected: ' . htmlspecialchars($mime) . '). Allowed: JPG, PNG, WebP, PDF.';
            error_log('Disallowed MIME type: ' . $mime);
            return false;
        }
    }

    // Map MIME to safe extension
    $mimeToExt = [
        'image/jpeg'  => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/jpg'   => 'jpg',
        'image/png'   => 'png',
        'image/x-png' => 'png',
        'image/webp'  => 'webp',
        'image/gif'   => 'gif',
        'application/pdf'   => 'pdf',
        'application/x-pdf' => 'pdf',
    ];
    $ext = $mimeToExt[$mime] ?? 'bin';

    // Generate secure random filename
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    // Use secure upload path (outside webroot if possible)
    $securePath = defined('SECURE_UPLOADS_PATH') ? SECURE_UPLOADS_PATH : (UPLOADS_PATH . '_secure/');
    $fullPath = $securePath . $destination . '/' . $filename;

    // Create directory with secure permissions
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            $errorMsg = 'Failed to create upload folder on the server.';
            error_log('Failed to create upload directory: ' . $dir);
            return false;
        }
        @chmod($dir, 0777);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        $errorMsg = 'Server could not save the uploaded file. Please verify directory permissions.';
        error_log('move_uploaded_file failed for: ' . $fullPath);
        return false;
    }

    // Set readable file permissions
    chmod($fullPath, 0666);

    // Return relative path for database storage
    return $destination . '/' . $filename;
}

/**
 * Get public URL for uploaded file (served via proxy script)
 */
function getUploadUrl(string $storedPath): string {
    return SITE_URL . '/serve-file.php?file=' . urlencode($storedPath);
}

/**
 * Resolve user avatar URL safely.
 * Uploaded avatars contain a folder (avatars/x.jpg) and are served via getUploadUrl();
 * static defaults are plain filenames in assets/images/avatars/.
 */
function getUserAvatarUrl(?string $avatar): string {
    if (!empty($avatar) && strpos($avatar, '/') !== false) {
        return getUploadUrl($avatar);
    }
    return SITE_URL . '/assets/images/avatars/' . (!empty($avatar) ? $avatar : 'default-avatar.png');
}

/**
 * Resolve room thumbnail URL.
 * Uploaded thumbnails contain a folder (rooms/x.jpg) and are served via
 * getUploadUrl(); bundled assets are plain filenames in assets/images/rooms/.
 */
function roomThumbUrl(?string $thumbnail): string {
    if (!empty($thumbnail) && strpos($thumbnail, '/') !== false) {
        return getUploadUrl($thumbnail);
    }
    return SITE_URL . '/assets/images/rooms/' . ($thumbnail ?: 'default-room.jpg');
}

/**
 * Delete uploaded file
 */
function deleteUpload(string $storedPath): bool {
    $securePath = defined('SECURE_UPLOADS_PATH') ? SECURE_UPLOADS_PATH : (UPLOADS_PATH . '_secure/');
    $fullPath = $securePath . $storedPath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

// ---- Paginate results ----
function paginate(string $sql, array $params, int $page = 1, int $perPage = 10): array {
    $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as sub";
    $totalRow  = dbRow($countSql, $params);
    $total     = (int) ($totalRow['total'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;
    $rows = dbRows($sql . " LIMIT $perPage OFFSET $offset", $params);
    return [
        'data'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
    ];
}

// ---- Get average room rating ----
function roomAvgRating(int $roomId): float {
    $row = dbRow("SELECT AVG(rating) as avg FROM reviews WHERE room_id = ? AND status = 'approved'", [$roomId]);
    return round((float) ($row['avg'] ?? 0), 1);
}

// ---- Shorten text ----
function truncate(string $text, int $length = 100): string {
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '...' : $text;
}
function truncateText(string $text, int $length = 100): string {
    return truncate($text, $length);
}

// ---- Is weekend ----
function isWeekend(string $date): bool {
    $day = (int) date('N', strtotime($date));
    return $day >= 6;
}

// ---- Get room price for a date ----
function getRoomPrice(array $room, string $date): float {
    if ($room['weekend_rate'] && isWeekend($date)) {
        return (float) $room['weekend_rate'];
    }
    return (float) $room['price_per_night'];
}

// ---- JSON decode safely ----
function safeJson(?string $json): array {
    if (!$json) return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

// ---- Redirect helper ----
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ---- Active nav helper ----
function isActivePage(string $path): string {
    return str_contains($_SERVER['REQUEST_URI'], $path) ? 'active' : '';
}

// ---- Format full name with middle name and suffix ----
function formatFullName(string|array $userOrFirst, string $middle = '', string $last = '', string $suffix = ''): string {
    if (is_array($userOrFirst)) {
        $first = $userOrFirst['first_name'] ?? '';
        $middle = $userOrFirst['middle_name'] ?? '';
        $last = $userOrFirst['last_name'] ?? '';
        $suffix = $userOrFirst['suffix'] ?? '';
    } else {
        $first = $userOrFirst;
    }
    $parts = array_filter([trim($first), trim($middle), trim($last)], fn($p) => $p !== '');
    $name = implode(' ', $parts);
    if (!empty(trim($suffix))) {
        $name .= ' ' . trim($suffix);
    }
    return $name;
}

// ---- Validate user/staff name length, allowed characters, and uniqueness ----
function validatePersonName(string $firstName, string $lastName, string $middleName = '', string $suffix = '', ?int $excludeUserId = null): ?string {
    $fn = trim($firstName);
    $mn = trim($middleName);
    $ln = trim($lastName);
    $suf = trim($suffix);

    if ($fn === '' || $ln === '') {
        return 'First name and last name are required.';
    }

    // Minimum limit: 2 characters for first and last name
    if (mb_strlen($fn) < 2 || mb_strlen($ln) < 2) {
        return 'First name and last name must each be at least 2 characters long. (Ang First Name at Last Name ay dapat hindi bababa sa 2 letra).';
    }

    // Maximum limit: 50 characters
    if (mb_strlen($fn) > 50 || mb_strlen($ln) > 50 || mb_strlen($mn) > 50) {
        return 'Names cannot exceed 50 characters each. (Ang pangalan ay hindi dapat lumagpas sa 50 letra).';
    }

    // Suffix limit: 20 characters
    if (mb_strlen($suf) > 20) {
        return 'Suffix cannot exceed 20 characters.';
    }

    // Allowed characters: letters (with accents/ñ), spaces, hyphens, apostrophes, and periods
    if (!preg_match("/^[\p{L}\s'\-\.]+$/u", $fn) || !preg_match("/^[\p{L}\s'\-\.]+$/u", $ln)) {
        return 'Names can only contain valid letters, spaces, hyphens (-), and apostrophes (\'). Numbers and special symbols are not allowed.';
    }

    if ($mn !== '' && !preg_match("/^[\p{L}\s'\-\.]+$/u", $mn)) {
        return 'Middle name can only contain valid letters, spaces, hyphens (-), and apostrophes (\').';
    }

    if ($suf !== '' && !preg_match("/^[\p{L}\s'\-\.]+$/u", $suf)) {
        return 'Suffix can only contain valid letters, periods, and spaces (e.g. Jr., Sr., III).';
    }

    // Uniqueness constraint: Bawal gamitin ang buong kombinasyon ng pangalan kapag nakarehistro na sa ibang user
    if (function_exists('isDbAvailable') && isDbAvailable()) {
        $fullNameDisplay = formatFullName($fn, $mn, $ln, $suf);
        try {
            $sql = "SELECT id FROM users WHERE LOWER(TRIM(first_name)) = LOWER(?) 
                    AND LOWER(TRIM(last_name)) = LOWER(?) 
                    AND LOWER(TRIM(COALESCE(middle_name, ''))) = LOWER(?)
                    AND LOWER(TRIM(COALESCE(suffix, ''))) = LOWER(?)";
            $params = [$fn, $ln, $mn, $suf];
            if ($excludeUserId !== null) {
                $sql .= " AND id != ?";
                $params[] = $excludeUserId;
            }
            $existing = dbRow($sql, $params);
            if ($existing) {
                return "Ang buong pangalang '{$fullNameDisplay}' ay nakarehistro na sa system. Bawal gamitin ang kaparehong pangalan ng ibang user. Pakiusap gumamit ng buong legal name o magdagdag ng middle initial/suffix.";
            }
        } catch (\Throwable $e) {
            // DB fallback in case columns are pending migration
            try {
                $sql = "SELECT id FROM users WHERE LOWER(TRIM(first_name)) = LOWER(?) AND LOWER(TRIM(last_name)) = LOWER(?)";
                $params = [$fn, $ln];
                if ($excludeUserId !== null) {
                    $sql .= " AND id != ?";
                    $params[] = $excludeUserId;
                }
                $existing = dbRow($sql, $params);
                if ($existing) {
                    return "Ang pangalang '{$fn} {$ln}' ay nakarehistro na sa system. Bawal gamitin ang kaparehong pangalan ng ibang user.";
                }
            } catch (\Throwable $e2) {}
        }
    }

    return null;
}

// ---- Check if user profile is fully verified ----
function isUserProfileVerified(int $userId): bool {
    try {
        $user = dbRow("SELECT profile_verified, verification_status FROM users WHERE id = ?", [$userId]);
        if (!$user) return false;
        
        if (isset($user['verification_status'])) {
            return $user['verification_status'] === 'verified' || (int)$user['profile_verified'] === 1;
        }
        
        return !empty($user['profile_verified']) && (int)$user['profile_verified'] === 1;
    } catch (\Throwable $e) {
        // Fallback for environments before migration 0009
        $user = dbRow("SELECT profile_verified FROM users WHERE id = ?", [$userId]);
        return !empty($user['profile_verified']) && (int)$user['profile_verified'] === 1;
    }
}

// ---- Get full KYC status breakdown for a user ----
function getUserKycStatus(int $userId): array {
    try {
        $user = dbRow("SELECT avatar, phone, address, location, id_type, id_number, id_document, profile_verified, verification_status, rejection_reason, verification_submitted_at, verified_at FROM users WHERE id = ?", [$userId]);
    } catch (\Throwable $e) {
        $user = dbRow("SELECT avatar, phone, address, location, id_type, id_number, id_document, profile_verified, verification_submitted_at FROM users WHERE id = ?", [$userId]);
    }

    if (!$user) {
        return [
            'status' => 'unverified',
            'is_verified' => false,
            'is_pending' => false,
            'is_rejected' => false,
            'rejection_reason' => null,
            'submitted_at' => null,
            'verified_at' => null,
            'has_complete_fields' => false,
        ];
    }

    $hasAvatar   = !empty($user['avatar']) && $user['avatar'] !== 'default-avatar.png';
    $hasPhone    = !empty(trim($user['phone'] ?? ''));
    $hasAddress  = !empty(trim($user['address'] ?? ''));
    $hasLocation = !empty(trim($user['location'] ?? ''));
    $hasIdType   = !empty(trim($user['id_type'] ?? ''));
    $hasIdNumber = !empty(trim($user['id_number'] ?? ''));
    $hasIdDoc    = !empty(trim($user['id_document'] ?? ''));
    $complete    = $hasAvatar && $hasPhone && $hasAddress && $hasLocation && $hasIdType && $hasIdNumber && $hasIdDoc;

    $status = $user['verification_status'] ?? ($user['profile_verified'] ? 'verified' : ($complete ? 'pending' : 'unverified'));

    return [
        'status' => $status,
        'is_verified' => $status === 'verified',
        'is_pending' => $status === 'pending',
        'is_rejected' => $status === 'rejected',
        'rejection_reason' => $user['rejection_reason'] ?? null,
        'submitted_at' => $user['verification_submitted_at'] ?? null,
        'verified_at' => $user['verified_at'] ?? null,
        'has_complete_fields' => $complete,
    ];
}

// ---- Render KYC status badge ----
function kycBadge(string $status): string {
    return match($status) {
        'verified'   => '<span class="badge" style="background:#22c55e;color:#fff;font-weight:600;"><i class="fa-solid fa-shield-check"></i> Verified</span>',
        'pending'    => '<span class="badge" style="background:#eab308;color:#000;font-weight:600;"><i class="fa-solid fa-clock"></i> Pending Review</span>',
        'rejected'   => '<span class="badge" style="background:#ef4444;color:#fff;font-weight:600;"><i class="fa-solid fa-circle-exclamation"></i> Rejected</span>',
        default      => '<span class="badge" style="background:#64748b;color:#fff;font-weight:600;"><i class="fa-solid fa-id-card"></i> Unverified</span>',
    };
}

// ---- Auto-expire stale unpaid bookings older than 24 hours ----
function autoExpireStaleBookings(int $hoursThreshold = 24): int {
    try {
        $stale = dbRows("
            SELECT b.id, b.booking_code, b.user_id, b.room_id 
            FROM bookings b 
            WHERE b.status = 'pending' 
              AND b.created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
              AND NOT EXISTS (
                  SELECT 1 FROM payments p 
                  WHERE p.booking_id = b.id AND p.status IN ('verified', 'pending')
              )
        ", [$hoursThreshold]);

        $cancelledCount = 0;
        foreach ($stale as $item) {
            dbExec("
                UPDATE bookings 
                SET status = 'cancelled', 
                    cancelled_reason = 'Auto-cancelled: Payment was not settled within 24 hours of booking.',
                    cancelled_at = NOW() 
                WHERE id = ? AND status = 'pending'
            ", [$item['id']]);

            createNotification(
                (int)$item['user_id'], 
                'Booking Expired', 
                "Your booking #{$item['booking_code']} was automatically cancelled due to no payment within 24 hours.", 
                'alert'
            );
            auditLog('Booking Auto-Expired', 'System Daemon', "Booking #{$item['booking_code']} auto-cancelled after {$hoursThreshold}h without payment proof.");
            $cancelledCount++;
        }
        return $cancelledCount;
    } catch (\Throwable $e) {
        if (function_exists('logError')) {
            logError("autoExpireStaleBookings failed: " . $e->getMessage(), ['exception' => $e]);
        }
        return 0;
    }
}


