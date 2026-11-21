<?php
declare(strict_types=1);

namespace MarvelousHotel\Services;

class PaymentService
{
    /**
     * List payments scoped strictly to the actor's role.
     */
    public function listForActor(int $actorId, string $role, ?string $statusFilter = null, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $where = [];
        $params = [];

        if ($role === 'user') {
            $where[] = 'p.user_id = ?';
            $params[] = $actorId;
        }

        if ($statusFilter !== null && in_array($statusFilter, ['pending', 'verified', 'rejected'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $statusFilter;
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        return \dbRows("
            SELECT p.id, p.booking_id, p.user_id, p.amount, p.method, p.reference_number,
                   p.status, p.verified_by, p.verified_at, p.created_at,
                   b.booking_code, r.name AS room_name,
                   u.first_name, u.last_name, u.email AS guest_email
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            JOIN rooms r ON b.room_id = r.id
            JOIN users u ON p.user_id = u.id
            {$whereSql}
            ORDER BY p.id DESC
            LIMIT {$limit}
        ", $params);
    }

    /**
     * Delegate atomic payment verification / rejection to shared core transaction logic.
     */
    public function applyDecision(int $paymentId, string $decision, int $actorId, string $actorRole): array
    {
        $actorContext = ($actorRole === 'admin') ? 'Admin' : 'Staff';
        return \processPaymentDecision($paymentId, $decision, $actorId, $actorContext);
    }
}
