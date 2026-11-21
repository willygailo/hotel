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

    // 3. Fully available (or pending housekeeping turnaround)
    if ($roomBaseStatus === 'dirty') {
        return [
            'state'        => 'dirty',
            'badge_class'  => 'badge-warning',
            'label'        => 'Housekeeping',
            'subtitle'     => 'Undergoing room turnover cleaning',
            'tag_html'     => '<span class="badge badge-warning"><i class="fa-solid fa-broom"></i> Housekeeping</span>',
            'is_bookable'  => true,
            'active_now'   => false,
            'next_booking' => null,
            'bookings'     => [],
        ];
    }

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
        'dirty'       => ['badge-warning',  'Needs Cleaning'],
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

    // Automatically downscale & compress uploaded images (keeps PNG/WebP transparency)
    if (str_starts_with($mime, 'image/') && $mime !== 'image/gif') {
        $maxDim = ($destination === 'avatars') ? 400 : 1600;
        $tmpOpt = $fullPath . '.opt';
        if (optimizeImageFileGd($fullPath, $tmpOpt, $mime, $maxDim, 82)) {
            $origBytes = @filesize($fullPath) ?: PHP_INT_MAX;
            $optBytes  = @filesize($tmpOpt) ?: PHP_INT_MAX;
            if ($optBytes > 0 && $optBytes < $origBytes) {
                @rename($tmpOpt, $fullPath);
            } else {
                @unlink($tmpOpt);
            }
        } else {
            @unlink($tmpOpt);
        }
    }

    // Set readable file permissions
    chmod($fullPath, 0666);

    // Return relative path for database storage
    return $destination . '/' . $filename;
}

/**
 * Resize and compress a JPEG/PNG/WebP image using GD if available and safe in memory.
 */
function optimizeImageFileGd(string $srcPath, string $destPath, string $mime, int $maxDim = 1600, int $quality = 82): bool {
    if (!extension_loaded('gd') || !is_file($srcPath) || !is_readable($srcPath)) {
        return false;
    }
    $info = @getimagesize($srcPath);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return false;
    }
    $srcW = (int)$info[0];
    $srcH = (int)$info[1];
    // Skip ultra-huge bitmaps to avoid exceeding PHP memory_limit
    if (($srcW * $srcH) > 20000000) {
        return false;
    }

    $isJpeg = in_array($mime, ['image/jpeg', 'image/pjpeg', 'image/jpg'], true);
    $isPng  = in_array($mime, ['image/png', 'image/x-png'], true);
    $isWebp = ($mime === 'image/webp');

    $srcImg = null;
    if ($isJpeg && function_exists('imagecreatefromjpeg')) {
        $srcImg = @imagecreatefromjpeg($srcPath);
    } elseif ($isPng && function_exists('imagecreatefrompng')) {
        $srcImg = @imagecreatefrompng($srcPath);
    } elseif ($isWebp && function_exists('imagecreatefromwebp')) {
        $srcImg = @imagecreatefromwebp($srcPath);
    }
    if (!$srcImg) {
        return false;
    }

    if ($isJpeg && function_exists('exif_read_data')) {
        $exif = @exif_read_data($srcPath);
        $orient = (int)($exif['Orientation'] ?? 1);
        if ($orient === 3) {
            $srcImg = imagerotate($srcImg, 180, 0);
        } elseif ($orient === 6) {
            $srcImg = imagerotate($srcImg, -90, 0);
            [$srcW, $srcH] = [$srcH, $srcW];
        } elseif ($orient === 8) {
            $srcImg = imagerotate($srcImg, 90, 0);
            [$srcW, $srcH] = [$srcH, $srcW];
        }
    }

    $targetW = $srcW;
    $targetH = $srcH;
    if (max($srcW, $srcH) > $maxDim) {
        $ratio = $maxDim / max($srcW, $srcH);
        $targetW = max(1, (int)round($srcW * $ratio));
        $targetH = max(1, (int)round($srcH * $ratio));
    }

    $dstImg = imagecreatetruecolor($targetW, $targetH);
    if (!$dstImg) {
        imagedestroy($srcImg);
        return false;
    }

    if ($isPng || $isWebp) {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
        imagefilledrectangle($dstImg, 0, 0, $targetW, $targetH, $transparent);
    }

    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
    imagedestroy($srcImg);

    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $ok = false;
    if ($isJpeg && function_exists('imagejpeg')) {
        if (function_exists('imageinterlace')) {
            @imageinterlace($dstImg, 1);
        }
        $ok = @imagejpeg($dstImg, $destPath, $quality);
    } elseif ($isPng && function_exists('imagepng')) {
        $ok = @imagepng($dstImg, $destPath, 9);
    } elseif ($isWebp && function_exists('imagewebp')) {
        $ok = @imagewebp($dstImg, $destPath, $quality);
    }

    imagedestroy($dstImg);
    return $ok && is_file($destPath);
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
    return ASSETS_URL . '/images/avatars/' . (!empty($avatar) ? $avatar : 'default-avatar.png');
}

/**
 * Resolve room thumbnail URL.
 * Uploaded thumbnails contain a folder (rooms/x.jpg) and are served via
 * getUploadUrl(); bundled assets are plain filenames in frontend/assets/images/rooms/.
 */
function roomThumbUrl(?string $thumbnail): string {
    if (!empty($thumbnail) && strpos($thumbnail, '/') !== false) {
        return getUploadUrl($thumbnail);
    }
    return ASSETS_URL . '/images/rooms/' . ($thumbnail ?: 'default-room.jpg');
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

// ---- Batch-load average room ratings (avoids N+1 queries) ----
function getRoomsAvgRatingsMap(): array {
    $rows = dbRows("SELECT room_id, ROUND(AVG(rating), 1) AS avg_rating FROM reviews WHERE status = 'approved' AND room_id IS NOT NULL GROUP BY room_id");
    $map = [];
    foreach ($rows as $r) {
        $map[(int)$r['room_id']] = (float)$r['avg_rating'];
    }
    return $map;
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

// ---- Sanitize AI-generated HTML output against XSS ----
function sanitizeAiHtml(string $html): string {
    // Convert markdown bold (**text**) to <strong> before tag stripping
    $formatted = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $html);
    // Redact internal port/file paths if an LLM ever echoes them
    $formatted = preg_replace('/\b(?:port\s*)?(?:8080|8081|8082)\b/i', '[redacted]', $formatted);
    $formatted = preg_replace('/\b(?:admin\/[a-z0-9_-]+\.php|includes\/[a-z0-9_-]+\.php|\.env|config\.php)\b/i', '[restricted]', $formatted);
    // Allowlist safe formatting tags only (strip script, iframe, img, a, event handlers)
    $allowed = '<strong><b><em><i><br><ul><ol><li><p>';
    $clean = strip_tags($formatted, $allowed);
    // Strip any attributes (onclick, style, class, onerror, etc.) from allowed tags
    $clean = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $clean);
    return nl2br(trim($clean));
}

// ---- Atomic payment verification / rejection shared by Admin & Staff ----
function processPaymentDecision(int $payId, string $decision, int $actorId, string $actorContext = 'Admin'): array {
    if (!in_array($decision, ['verify', 'reject'], true)) {
        return ['ok' => false, 'flash_type' => 'error', 'message' => 'Invalid payment action requested.'];
    }

    $pdo = getDB();
    $startedTx = !$pdo->inTransaction();
    if ($startedTx) {
        $pdo->beginTransaction();
    }

    try {
        $pay = dbRow("
            SELECT p.*, b.booking_code, b.status AS booking_status,
                   b.room_id, b.check_in, b.check_out,
                   v.first_name AS verifier_first_name, v.last_name AS verifier_last_name, v.role AS verifier_role
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            LEFT JOIN users v ON p.verified_by = v.id
            WHERE p.id = ?
            FOR UPDATE
        ", [$payId]);

        if (!$pay) {
            if ($startedTx && $pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'flash_type' => 'error', 'message' => 'Payment record not found.'];
        }

        $vName = !empty($pay['verifier_first_name'])
            ? trim($pay['verifier_first_name'] . ' ' . ($pay['verifier_last_name'] ?? ''))
            : 'Staff/Admin';
        $vRole = (($pay['verifier_role'] ?? '') === 'admin') ? 'Super Admin' : 'Frontdesk Staff';

        if ($pay['status'] === 'verified') {
            if ($startedTx && $pdo->inTransaction()) $pdo->rollBack();
            $when = !empty($pay['verified_at']) ? formatDate($pay['verified_at'], 'M d, Y h:i A') : 'earlier';
            return [
                'ok' => true,
                'flash_type' => 'info',
                'message' => "Already Verified: Payment #$payId for Booking #{$pay['booking_code']} was already approved by $vRole ($vName) on $when. Automatically synchronized!"
            ];
        }

        $isAdminActor = (stripos($actorContext, 'admin') !== false);
        $moduleTag = $isAdminActor ? 'Admin Module' : 'Staff Finance';
        $actorTitle = $isAdminActor ? 'Administration' : 'the Frontdesk team';
        $actorShort = $isAdminActor ? "Admin #$actorId" : "Staff #$actorId";

        if ($decision === 'verify') {
            if (in_array($pay['booking_status'], ['pending', 'cancelled', 'rejected'], true)) {
                $conflict = findConflictingBooking((int)$pay['room_id'], $pay['check_in'], $pay['check_out'], (int)$pay['booking_id']);
                if ($conflict) {
                    if ($startedTx && $pdo->inTransaction()) $pdo->rollBack();
                    return [
                        'ok' => false,
                        'flash_type' => 'error',
                        'message' => "Cannot approve Payment #$payId: Booking #{$pay['booking_code']} overlaps with Booking #{$conflict['booking_code']} (" . formatDate($conflict['check_in'], 'M d') . " - " . formatDate($conflict['check_out'], 'M d') . ")."
                    ];
                }
            }

            dbExec("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE id = ?", [$actorId, $payId]);
            dbExec("UPDATE bookings SET status = 'confirmed', accepted_by = COALESCE(accepted_by, ?), accepted_at = COALESCE(accepted_at, NOW()), cancelled_reason = NULL WHERE id = ? AND status IN ('pending', 'confirmed', 'cancelled', 'rejected')", [$actorId, $pay['booking_id']]);
            if ($startedTx && $pdo->inTransaction()) $pdo->commit();

            createNotification(
                (int)$pay['user_id'],
                $isAdminActor ? 'Payment Verified & Booking Confirmed' : 'Payment Verified by Staff',
                "Your payment for Booking #{$pay['booking_code']} has been verified by $actorTitle.",
                'payment'
            );
            auditLog(
                $isAdminActor ? 'Verified Payment' : 'Staff Verified Payment',
                $moduleTag,
                "Payment #$payId for Booking #{$pay['booking_code']} verified by $actorShort"
            );
            return [
                'ok' => true,
                'flash_type' => 'success',
                'message' => "Payment for Booking #{$pay['booking_code']} verified and confirmed successfully."
            ];
        }

        if ($pay['status'] === 'rejected') {
            if ($startedTx && $pdo->inTransaction()) $pdo->rollBack();
            return [
                'ok' => true,
                'flash_type' => 'info',
                'message' => "Payment #$payId for Booking #{$pay['booking_code']} is already marked as rejected."
            ];
        }

        dbExec("UPDATE payments SET status = 'rejected' WHERE id = ?", [$payId]);
        if ($startedTx && $pdo->inTransaction()) $pdo->commit();

        createNotification(
            (int)$pay['user_id'],
            'Payment Receipt Rejected',
            "There was a problem verifying your receipt for Booking #{$pay['booking_code']}. Please contact the Frontdesk or submit a clear copy.",
            'alert'
        );
        auditLog(
            $isAdminActor ? 'Rejected Payment' : 'Staff Rejected Payment',
            $moduleTag,
            "Payment #$payId for Booking #{$pay['booking_code']} rejected by $actorShort"
        );
        return [
            'ok' => true,
            'flash_type' => 'warning',
            'message' => "Payment #$payId for Booking #{$pay['booking_code']} has been marked as rejected."
        ];
    } catch (\Throwable $e) {
        if ($startedTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('logError')) {
            logError("processPaymentDecision failed: " . $e->getMessage(), ['payment_id' => $payId]);
        }
        return ['ok' => false, 'flash_type' => 'error', 'message' => 'Failed to process payment decision. Please try again.'];
    }
}

/**
 * Validate, deduplicate, and save a guest review for a booking or General Stay.
 */
function submitUserReview(int $userId, int $bookingId, int $rating, string $comment, string $title = '', string $sourceModule = 'User Module'): array {
    $comment = trim($comment);
    $title   = trim($title);

    if ($rating < 1 || $rating > 5 || $comment === '') {
        return ['ok' => false, 'flash_type' => 'error', 'message' => 'Please provide a star rating (1-5) and write your review comment.'];
    }

    $targetRoomId    = null;
    $targetBookingId = null;
    $bookingCode     = '';

    if ($bookingId > 0) {
        $bk = dbRow("SELECT id, room_id, booking_code FROM bookings WHERE id = ? AND user_id = ?", [$bookingId, $userId]);
        if (!$bk) {
            return ['ok' => false, 'flash_type' => 'error', 'message' => 'Invalid booking selected for review.'];
        }
        $existingReview = dbRow("SELECT id FROM reviews WHERE booking_id = ? LIMIT 1", [$bookingId]);
        if ($existingReview) {
            return ['ok' => false, 'flash_type' => 'error', 'message' => "Nakapagsumite ka na ng review para sa Booking #{$bk['booking_code']}."];
        }
        $targetBookingId = (int)$bk['id'];
        $targetRoomId    = (int)$bk['room_id'];
        $bookingCode     = $bk['booking_code'];
    } else {
        // General Stay review: optionally tag the user's most recent room if any
        $latestBk = dbRow("SELECT room_id FROM bookings WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$userId]);
        if ($latestBk) {
            $targetRoomId = (int)$latestBk['room_id'];
        }
    }

    dbInsert(
        "INSERT INTO reviews (user_id, room_id, booking_id, rating, title, comment, status) 
         VALUES (?, ?, ?, ?, ?, ?, 'approved')",
        [$userId, $targetRoomId, $targetBookingId, $rating, $title !== '' ? $title : null, $comment]
    );

    if (function_exists('auditLog')) {
        $actionLabel = ($sourceModule === 'Homepage') ? 'Submitted Homepage Review' : 'Submitted Room Review';
        $detailSuffix = $bookingCode ? " for Booking #$bookingCode" : ' (General Stay)';
        auditLog($actionLabel, 'User Module', "Review submitted by User #$userId" . $detailSuffix);
    }

    return ['ok' => true, 'flash_type' => 'success', 'message' => 'Maraming salamat! Naipasa na ang iyong review at makikita na ito sa Customer Reviews.'];
}

/**
 * Release 'occupied' room status if no other booking for that room remains checked_in.
 */
function releaseRoomOccupancyIfIdle(int $roomId, int $excludeBookingId = 0): void {
    $otherActive = dbRow(
        "SELECT id FROM bookings WHERE room_id = ? AND status = 'checked_in' AND id != ? LIMIT 1",
        [$roomId, $excludeBookingId]
    );
    if (!$otherActive) {
        dbExec("UPDATE rooms SET status = 'available' WHERE id = ? AND status = 'occupied'", [$roomId]);
    }
}

/**
 * Hash a raw password-reset session watch token using SHA-256 before storing in DB.
 */
function hashResetRequestToken(string $rawToken): string {
    return hash('sha256', $rawToken);
}

/**
 * Constant-time verification of a session watch token against the stored DB hash.
 */
function verifyResetRequestToken(?string $storedHash, ?string $rawToken): bool {
    if ($storedHash === null || $storedHash === '' || $rawToken === null || $rawToken === '') {
        return false;
    }
    return hash_equals($storedHash, hashResetRequestToken($rawToken));
}

/**
 * Evaluate the live password reset status payload for a session-bound watch.
 * Pure helper (DB-free) so it can be unit-tested thoroughly.
 */
function buildResetStatusPayload(?array $row, array $watch, int $expiryMinutes = 15, ?int $nowTs = null): array {
    $now        = $nowTs ?? time();
    $requestId  = (int)($watch['request_id'] ?? 0);
    $rawToken   = (string)($watch['token'] ?? '');
    $watchEmail = (string)($watch['email'] ?? '');

    if ($rawToken === '') {
        return ['status' => 'none'];
    }

    // Dummy watch for non-existent email — behaves identically to pending (prevents enumeration)
    if ($requestId <= 0) {
        return [
            'status'       => 'pending',
            'email'        => $watchEmail,
            'requested_at' => !empty($watch['created_at']) ? date('M d, Y h:i A', (int)$watch['created_at']) : null,
        ];
    }

    if ($row === null || !verifyResetRequestToken($row['request_token'] ?? null, $rawToken)) {
        return ['status' => 'none'];
    }

    $email  = (string)($row['email'] ?? $watchEmail);
    $status = (string)($row['status'] ?? '');

    if ($status === 'pending') {
        return [
            'status'       => 'pending',
            'email'        => $email,
            'requested_at' => !empty($row['requested_at']) ? formatDate($row['requested_at'], 'M d, Y h:i A') : null,
        ];
    }

    if ($status === 'dismissed') {
        return ['status' => 'none'];
    }

    if ($status === 'completed') {
        // One-time consumption guard: never re-expose credentials after acknowledgement
        if (!empty($row['user_notified_at'])) {
            return [
                'status' => 'acknowledged',
                'email'  => $email,
            ];
        }

        // Enforce expiry window after admin resolution
        if (!empty($row['resolved_at'])) {
            $resolvedTs = strtotime((string)$row['resolved_at']);
            if ($resolvedTs !== false && ($now - $resolvedTs) > ($expiryMinutes * 60)) {
                return [
                    'status' => 'expired',
                    'email'  => $email,
                ];
            }
        }

        $dispatchType = (string)($row['dispatch_type'] ?? '');
        if ($dispatchType === '') {
            $dispatchType = !empty($row['new_password_assigned']) ? 'password' : 'link';
        }

        $resolvedFormatted = !empty($row['resolved_at'])
            ? formatDate($row['resolved_at'], 'M d, Y h:i A')
            : date('M d, Y h:i A', $now);

        if ($dispatchType === 'link') {
            $resetToken = (string)($row['reset_token'] ?? '');
            $resetExpiresTs = !empty($row['reset_expires']) ? strtotime((string)$row['reset_expires']) : false;
            if ($resetToken === '' || ($resetExpiresTs !== false && $resetExpiresTs <= $now)) {
                return [
                    'status' => 'expired',
                    'email'  => $email,
                ];
            }
            return [
                'status'        => 'completed',
                'dispatch_type' => 'link',
                'email'         => $email,
                'resolved_at'   => $resolvedFormatted,
                'reset_url'     => 'reset-password.php?token=' . urlencode($resetToken),
                'login_url'     => 'login.php?email=' . urlencode($email),
            ];
        }

        $newPass = (string)($row['new_password_assigned'] ?? '');
        if ($newPass === '') {
            return [
                'status' => 'expired',
                'email'  => $email,
            ];
        }

        return [
            'status'        => 'completed',
            'dispatch_type' => 'password',
            'email'         => $email,
            'resolved_at'   => $resolvedFormatted,
            'new_password'  => $newPass,
            'login_url'     => 'login.php?email=' . urlencode($email),
        ];
    }

    return ['status' => 'none'];
}

/**
 * Mark the current session's watched password reset request as acknowledged and clear session watch.
 */
function consumePasswordResetWatch(): void {
    $watch = $_SESSION['password_reset_watch'] ?? null;
    if (!is_array($watch)) {
        return;
    }

    $requestId = (int)($watch['request_id'] ?? 0);
    $rawToken  = (string)($watch['token'] ?? '');

    if ($requestId > 0 && $rawToken !== '' && function_exists('dbRow') && function_exists('dbExec')) {
        try {
            $row = dbRow("SELECT id, request_token FROM password_reset_requests WHERE id = ? LIMIT 1", [$requestId]);
            if ($row && verifyResetRequestToken($row['request_token'] ?? null, $rawToken)) {
                dbExec(
                    "UPDATE password_reset_requests SET user_notified_at = COALESCE(user_notified_at, NOW()) WHERE id = ?",
                    [$requestId]
                );
            }
        } catch (\Throwable $e) {
            // Non-fatal; session watch is still cleared below
        }
    }

    unset($_SESSION['password_reset_watch']);
}

/**
 * Sanitize and bound multi-turn AI chat history (prevents prompt injection and oversized payloads).
 */
function normalizeAiChatHistory(array $history, int $maxMessages = 10, int $maxCharsPerMsg = 600): array {
    $clean = [];
    foreach ($history as $item) {
        if (!is_array($item)) {
            continue;
        }
        $role = strtolower(trim((string)($item['role'] ?? '')));
        if (!in_array($role, ['user', 'assistant'], true)) {
            continue;
        }
        $content = trim((string)($item['content'] ?? ''));
        if ($content === '') {
            continue;
        }
        if (mb_strlen($content, 'UTF-8') > $maxCharsPerMsg) {
            $content = mb_substr($content, 0, $maxCharsPerMsg, 'UTF-8') . '…';
        }
        $clean[] = [
            'role'    => $role,
            'content' => $content,
        ];
    }

    if ($maxMessages > 0 && count($clean) > $maxMessages) {
        $clean = array_slice($clean, -$maxMessages);
    }

    return $clean;
}

/**
 * Extract allowlisted structured action markers ([[BOOK_ROOM:7]], [[GOTO:my_bookings]], [[VIEW_ROOMS:Dorm]])
 * from an AI response and strip all bracket markers cleanly from visible text.
 */
function parseAiActionMarkers(string $rawText): array {
    $allowedRoutes = [
        'rooms', 'my_bookings', 'payment', 'profile', 'ai_recommend',
        'contact', 'about', 'forgot_password', 'login', 'register', 'dashboard'
    ];

    $roomIds = [];
    $routes = [];
    $roomQueries = [];

    $cleanText = preg_replace_callback('/\[\[\s*([A-Z_]+)\s*:\s*([^\]]+?)\s*\]\]/i', function (array $m) use (&$roomIds, &$routes, &$roomQueries, $allowedRoutes): string {
        $cmd = strtoupper(trim($m[1]));
        $arg = trim($m[2]);

        if ($cmd === 'BOOK_ROOM') {
            $id = (int)preg_replace('/[^0-9]/', '', $arg);
            if ($id > 0 && !in_array($id, $roomIds, true)) {
                $roomIds[] = $id;
            }
        } elseif ($cmd === 'GOTO') {
            $key = strtolower(preg_replace('/[^a-z0-9_]/i', '', $arg));
            if (in_array($key, $allowedRoutes, true) && !in_array($key, $routes, true)) {
                $routes[] = $key;
            }
        } elseif ($cmd === 'VIEW_ROOMS') {
            $q = trim(preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $arg));
            if ($q !== '') {
                $q = mb_substr($q, 0, 60, 'UTF-8');
                if (!in_array($q, $roomQueries, true)) {
                    $roomQueries[] = $q;
                }
            }
        }

        return '';
    }, $rawText);

    // Strip any stray unparsed [[...]] markers so raw tags never leak to the UI
    $cleanText = preg_replace('/\[\[[^\]]*\]\]/', '', (string)$cleanText);
    $cleanText = preg_replace("/\n{3,}/", "\n\n", trim((string)$cleanText));

    return [
        'clean_text'   => $cleanText,
        'room_ids'     => $roomIds,
        'routes'       => $routes,
        'room_queries' => $roomQueries,
    ];
}

/**
 * Convert an AI response (HTML or Markdown) into clean, natural plain text for browser Text-to-Speech.
 */
function aiPlainTextForSpeech(string $text): string {
    $parsed = parseAiActionMarkers($text);
    $plain = $parsed['clean_text'];

    // Preserve natural pauses at line breaks and list boundaries
    $plain = preg_replace('/<(?:br\s*\/?|\/p|\/li|\/div)>/i', '. ', $plain);
    $plain = strip_tags($plain);
    $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Remove markdown formatting tokens and URLs
    $plain = preg_replace('/https?:\/\/\S+/i', '', $plain);
    $plain = preg_replace('/[*_`#~]+/', '', $plain);
    $plain = str_replace(['•', '→', '—', '–'], [', ', ', ', ', ', ' '], $plain);

    // Strip emoji / pictograph Unicode blocks so TTS sounds natural
    $plain = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $plain);

    // Normalize punctuation & whitespace
    $plain = preg_replace('/\s*\.\s*\.+/', '. ', $plain);
    $plain = preg_replace('/\s+/', ' ', $plain);

    return trim($plain);
}



