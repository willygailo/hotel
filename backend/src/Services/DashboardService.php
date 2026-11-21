<?php
declare(strict_types=1);

namespace MarvelousHotel\Services;

class DashboardService
{
    /**
     * Return live KPI metrics tailored to Guest, Staff, or Admin role.
     */
    public function getMetricsForActor(int $actorId, string $role): array
    {
        if ($role === 'admin') {
            $revenue = (float)(\dbRow("SELECT COALESCE(SUM(amount), 0) AS t FROM payments WHERE status = 'verified'")['t'] ?? 0);
            $monthRev = (float)(\dbRow("SELECT COALESCE(SUM(amount), 0) AS t FROM payments WHERE status = 'verified' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")['t'] ?? 0);
            $totalRooms = (int)(\dbRow("SELECT COUNT(*) AS c FROM rooms")['c'] ?? 0);
            $occupied = (int)(\dbRow("SELECT COUNT(*) AS c FROM rooms WHERE status = 'occupied'")['c'] ?? 0);
            $pendingPayments = (int)(\dbRow("SELECT COUNT(*) AS c FROM payments WHERE status = 'pending'")['c'] ?? 0);
            $pendingKyc = (int)(\dbRow("SELECT COUNT(*) AS c FROM users WHERE role = 'user' AND verification_status = 'pending'")['c'] ?? 0);
            $pendingResets = (int)(\dbRow("SELECT COUNT(*) AS c FROM password_reset_requests WHERE status = 'pending'")['c'] ?? 0);

            return [
                'role'             => 'admin',
                'total_revenue'    => $revenue,
                'month_revenue'    => $monthRev,
                'total_rooms'      => $totalRooms,
                'occupied_rooms'   => $occupied,
                'occupancy_rate'   => $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0.0,
                'pending_payments' => $pendingPayments,
                'pending_kyc'      => $pendingKyc,
                'pending_resets'   => $pendingResets,
            ];
        }

        if ($role === 'staff') {
            $today = date('Y-m-d');
            return [
                'role'              => 'staff',
                'arrivals_today'    => (int)(\dbRow("SELECT COUNT(*) AS c FROM bookings WHERE check_in = ? AND status IN ('confirmed','checked_in')", [$today])['c'] ?? 0),
                'departures_today'  => (int)(\dbRow("SELECT COUNT(*) AS c FROM bookings WHERE check_out = ? AND status = 'checked_in'", [$today])['c'] ?? 0),
                'dirty_rooms'       => (int)(\dbRow("SELECT COUNT(*) AS c FROM rooms WHERE status IN ('dirty','maintenance')")['c'] ?? 0),
                'pending_bookings'  => (int)(\dbRow("SELECT COUNT(*) AS c FROM bookings WHERE status = 'pending'")['c'] ?? 0),
                'pending_payments'  => (int)(\dbRow("SELECT COUNT(*) AS c FROM payments WHERE status = 'pending'")['c'] ?? 0),
            ];
        }

        return [
            'role'            => 'user',
            'total_bookings'  => (int)(\dbRow("SELECT COUNT(*) AS c FROM bookings WHERE user_id = ?", [$actorId])['c'] ?? 0),
            'active_bookings' => (int)(\dbRow("SELECT COUNT(*) AS c FROM bookings WHERE user_id = ? AND status IN ('pending','confirmed','checked_in')", [$actorId])['c'] ?? 0),
            'unread_notifs'   => \unreadNotifCount($actorId),
            'kyc_verified'    => \isUserProfileVerified($actorId),
        ];
    }
}
