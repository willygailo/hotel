<?php
declare(strict_types=1);

namespace MarvelousHotel\Services;

class AuthService
{
    /**
     * Return active portal session summary, KYC status, and CSRF token for the current caller.
     */
    public function getSessionStatus(): array
    {
        $loggedIn = \isLoggedIn();
        $user = $loggedIn ? \currentUser() : null;
        $kyc = null;

        if ($loggedIn && $user && ($user['role'] ?? '') === 'user' && \isDbAvailable()) {
            $kyc = \getUserKycStatus((int)$user['id']);
        }

        $watch = $_SESSION['password_reset_watch'] ?? null;

        return [
            'authenticated'      => $loggedIn,
            'portal'             => defined('ACTIVE_PORTAL') ? ACTIVE_PORTAL : 'user',
            'user'               => $user,
            'kyc'                => $kyc,
            'has_reset_watch'    => is_array($watch) && !empty($watch['token']),
            'csrf_token'         => \generateCsrfToken(),
        ];
    }
}
