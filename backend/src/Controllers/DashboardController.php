<?php
declare(strict_types=1);

namespace MarvelousHotel\Controllers;

use MarvelousHotel\JsonResponder;
use MarvelousHotel\Services\DashboardService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController
{
    private DashboardService $dashboard;

    public function __construct(?DashboardService $dashboard = null)
    {
        $this->dashboard = $dashboard ?? new DashboardService();
    }

    public function metrics(Request $request, Response $response): Response
    {
        $metrics = $this->dashboard->getMetricsForActor((int)\currentUserId(), \currentRole());
        return JsonResponder::ok($response, ['metrics' => $metrics]);
    }
}
