<?php
declare(strict_types=1);

namespace MarvelousHotel\Services;

class BookingService
{
    /**
     * List bookings scoped strictly to the actor's role.
     */
    public function listForActor(int $actorId, string $role, ?string $statusFilter = null, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $where = [];
        $params = [];

        if ($role === 'user') {
            $where[] = 'b.user_id = ?';
            $params[] = $actorId;
        }

        $allowedStatuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'rejected'];
        if ($statusFilter !== null && in_array($statusFilter, $allowedStatuses, true)) {
            $where[] = 'b.status = ?';
            $params[] = $statusFilter;
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        return \dbRows("
            SELECT b.id, b.booking_code, b.user_id, b.room_id, b.check_in, b.check_out,
                   b.nights, b.num_guests, b.total_amount, b.payment_method, b.status,
                   b.created_at, b.accepted_at, b.checked_in_at, b.checked_out_at,
                   r.name AS room_name, r.room_number,
                   u.first_name, u.last_name, u.email AS guest_email
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            JOIN users u ON b.user_id = u.id
            {$whereSql}
            ORDER BY b.id DESC
            LIMIT {$limit}
        ", $params);
    }

    /**
     * Cancel a pending booking owned by the guest.
     */
    public function cancelGuestBooking(int $bookingId, int $userId, string $reason = 'Cancelled by guest via API'): array
    {
        $booking = \dbRow(
            "SELECT id, booking_code, status, user_id FROM bookings WHERE id = ? AND user_id = ? LIMIT 1",
            [$bookingId, $userId]
        );
        if (!$booking) {
            return ['ok' => false, 'status_code' => 404, 'message' => 'Booking not found.'];
        }
        if ($booking['status'] !== 'pending') {
            return ['ok' => false, 'status_code' => 409, 'message' => 'Only pending bookings can be cancelled directly.'];
        }

        \dbExec(
            "UPDATE bookings SET status = 'cancelled', cancelled_reason = ?, cancelled_at = NOW() WHERE id = ? AND user_id = ? AND status = 'pending'",
            [$reason, $bookingId, $userId]
        );
        \auditLog('Cancelled Booking', 'REST API', "Guest #{$userId} cancelled Booking #{$booking['booking_code']}");

        return [
            'ok'           => true,
            'status_code'  => 200,
            'booking_id'   => $bookingId,
            'booking_code' => $booking['booking_code'],
            'status'       => 'cancelled',
            'message'      => "Booking #{$booking['booking_code']} has been cancelled.",
        ];
    }
}
