<?php
declare(strict_types=1);

namespace MarvelousHotel\Controllers;

use MarvelousHotel\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController
{
    public function index(Request $request, Response $response): Response
    {
        return JsonResponder::ok($response, [
            'service'      => SITE_NAME . ' REST API',
            'version'      => 'v1',
            'architecture' => '3-Tier (Tailwind + Alpine.js / Slim 4 PHP / MySQL)',
            'endpoints'    => [
                'GET  /v1/health'               => 'System, DB, and AI Engine health status',
                'GET  /v1/rooms'                => 'Browse & filter hotel rooms and dormitories',
                'GET  /v1/rooms/{id}'           => 'Single room details with active booking ranges',
                'GET  /v1/availability'         => 'Real-time date availability check & stay quote',
                'GET  /v1/auth/status'          => 'Current isolated portal session & KYC status',
                'GET  /v1/bookings'             => 'Role-scoped bookings list (Auth required)',
                'POST /v1/bookings/{id}/cancel' => 'Cancel guest pending booking (Guest + CSRF)',
                'GET  /v1/payments'             => 'Role-scoped payments list (Auth required)',
                'POST /v1/payments/{id}/decision' => 'Verify or reject payment (Staff/Admin + CSRF)',
                'GET  /v1/dashboard/metrics'    => 'Role-scoped live KPI metrics (Auth required)',
            ],
        ]);
    }

    public function health(Request $request, Response $response): Response
    {
        $dbOnline = function_exists('isDbAvailable') ? \isDbAvailable() : false;
        $aiModel  = $dbOnline ? \getSetting('ai_nvidia_model', 'nvidia/nemotron-3.5-bolt-e4b') : 'nvidia/nemotron-3.5-bolt-e4b';

        return JsonResponder::ok($response, [
            'status'      => $dbOnline ? 'healthy' : 'degraded',
            'php_version' => PHP_VERSION,
            'database'    => $dbOnline ? 'connected' : 'offline',
            'ai_model'    => $aiModel,
            'tiers'       => [
                'frontend' => 'Tailwind CSS + Alpine.js 3',
                'backend'  => 'Slim 4 (PHP 8 PSR-4/PSR-15)',
                'database' => 'MySQL / MariaDB (PDO SQL)',
            ],
        ]);
    }
}
