<?php
declare(strict_types=1);

namespace MarvelousHotel\Services;

class RoomService
{
    /**
     * Pure stay quote calculation (DB-free, unit-testable).
     */
    public function calculateStayQuote(float $weekdayRate, float $weekendRate, string $checkIn, string $checkOut): array
    {
        $nights = \calculateNights($checkIn, $checkOut);
        $total = 0.0;
        $weekendNights = 0;

        $cursor = new \DateTime($checkIn);
        for ($i = 0; $i < $nights; $i++) {
            $dayStr = $cursor->format('Y-m-d');
            if ($weekendRate > 0 && \isWeekend($dayStr)) {
                $total += $weekendRate;
                $weekendNights++;
            } else {
                $total += $weekdayRate;
            }
            $cursor->modify('+1 day');
        }

        return [
            'nights'           => $nights,
            'weekday_nights'   => $nights - $weekendNights,
            'weekend_nights'   => $weekendNights,
            'weekday_rate'     => round($weekdayRate, 2),
            'weekend_rate'     => round($weekendRate > 0 ? $weekendRate : $weekdayRate, 2),
            'estimated_total'  => round($total, 2),
            'formatted_total'  => \formatCurrency($total),
        ];
    }

    /**
     * List rooms with optional filters (budget, guests, type_id, search query, check_in/check_out).
     */
    public function listRooms(array $filters = []): array
    {
        $sql = "SELECT r.*, rt.name AS type_name, rt.icon AS type_icon
                FROM rooms r
                JOIN room_types rt ON r.room_type_id = rt.id
                WHERE r.status != 'maintenance'";
        $params = [];

        if (!empty($filters['type_id'])) {
            $sql .= " AND r.room_type_id = ?";
            $params[] = (int)$filters['type_id'];
        }
        if (!empty($filters['guests'])) {
            $sql .= " AND r.capacity >= ?";
            $params[] = max(1, (int)$filters['guests']);
        }
        if (!empty($filters['budget']) && (float)$filters['budget'] > 0) {
            $sql .= " AND r.price_per_night <= ?";
            $params[] = (float)$filters['budget'];
        }
        if (!empty($filters['q'])) {
            $q = '%' . trim((string)$filters['q']) . '%';
            $sql .= " AND (r.name LIKE ? OR r.room_number LIKE ? OR rt.name LIKE ? OR r.description LIKE ?)";
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= " ORDER BY r.price_per_night ASC, r.room_number ASC";
        $rows = \dbRows($sql, $params);

        $checkIn  = trim((string)($filters['check_in'] ?? ''));
        $checkOut = trim((string)($filters['check_out'] ?? ''));
        $filterByDates = ($checkIn !== '' && $checkOut !== '' && $checkIn < $checkOut);

        $result = [];
        foreach ($rows as $row) {
            $availableForDates = true;
            if ($filterByDates) {
                $availableForDates = \isRoomAvailable((int)$row['id'], $checkIn, $checkOut);
                if (!empty($filters['only_available']) && !$availableForDates) {
                    continue;
                }
            }
            $result[] = $this->formatRoom($row, $availableForDates);
        }

        return $result;
    }

    /**
     * Fetch a single room with active future booking ranges.
     */
    public function getRoomById(int $roomId): ?array
    {
        $row = \dbRow(
            "SELECT r.*, rt.name AS type_name, rt.icon AS type_icon
             FROM rooms r
             JOIN room_types rt ON r.room_type_id = rt.id
             WHERE r.id = ? LIMIT 1",
            [$roomId]
        );
        if (!$row) {
            return null;
        }

        $formatted = $this->formatRoom($row, $row['status'] !== 'maintenance');
        $formatted['active_bookings'] = array_map(static function (array $b): array {
            return [
                'check_in'  => $b['check_in'],
                'check_out' => $b['check_out'],
                'status'    => $b['status'],
            ];
        }, \getRoomActiveBookings($roomId));

        return $formatted;
    }

    /**
     * Real-time availability check and stay quote for a specific room and date window.
     */
    public function checkAvailability(int $roomId, string $checkIn, string $checkOut): array
    {
        if ($roomId <= 0) {
            return ['valid' => false, 'error' => 'Invalid room ID.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
            return ['valid' => false, 'error' => 'Dates must use YYYY-MM-DD format.'];
        }
        if ($checkIn < date('Y-m-d')) {
            return ['valid' => false, 'error' => 'Check-in date cannot be in the past.'];
        }
        if ($checkIn >= $checkOut) {
            return ['valid' => false, 'error' => 'Check-out date must be after the check-in date.'];
        }

        $room = \dbRow(
            "SELECT r.*, rt.name AS type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = ? LIMIT 1",
            [$roomId]
        );
        if (!$room) {
            return ['valid' => false, 'error' => 'Room not found.'];
        }

        $quote = $this->calculateStayQuote(
            (float)$room['price_per_night'],
            (float)($room['weekend_rate'] ?? $room['price_per_night']),
            $checkIn,
            $checkOut
        );

        if ($room['status'] === 'maintenance') {
            return [
                'valid'     => true,
                'available' => false,
                'reason'    => 'This room is currently under maintenance.',
                'room'      => $this->formatRoom($room, false),
                'quote'     => $quote,
                'conflict'  => null,
            ];
        }

        $conflict = \findConflictingBooking($roomId, $checkIn, $checkOut);
        $isAvailable = ($conflict === null);

        return [
            'valid'     => true,
            'available' => $isAvailable,
            'reason'    => $isAvailable
                ? 'Room is available for your selected dates!'
                : 'Dates overlap with an existing reservation (' . \formatDate($conflict['check_in'], 'M d') . ' – ' . \formatDate($conflict['check_out'], 'M d, Y') . ').',
            'room'      => $this->formatRoom($room, $isAvailable),
            'quote'     => $quote,
            'conflict'  => $conflict ? [
                'check_in'  => $conflict['check_in'],
                'check_out' => $conflict['check_out'],
                'status'    => $conflict['status'],
            ] : null,
            'booked_ranges' => array_map(static function (array $b): array {
                return [
                    'check_in'  => $b['check_in'],
                    'check_out' => $b['check_out'],
                ];
            }, \getRoomActiveBookings($roomId)),
        ];
    }

    private function formatRoom(array $row, bool $isAvailable): array
    {
        $amenities = json_decode((string)($row['amenities'] ?? '[]'), true);
        return [
            'id'              => (int)$row['id'],
            'room_number'     => (string)$row['room_number'],
            'name'            => (string)$row['name'],
            'type_name'       => (string)($row['type_name'] ?? ''),
            'floor'           => (int)($row['floor'] ?? 1),
            'capacity'        => (int)($row['capacity'] ?? 1),
            'price_per_night' => (float)$row['price_per_night'],
            'weekend_rate'    => (float)($row['weekend_rate'] ?? $row['price_per_night']),
            'status'          => (string)$row['status'],
            'available'       => $isAvailable,
            'amenities'       => is_array($amenities) ? $amenities : [],
            'thumbnail_url'   => \roomThumbUrl($row['thumbnail'] ?? null),
            'book_url'        => SITE_URL . '/user/booking.php?room_id=' . (int)$row['id'],
        ];
    }
}
