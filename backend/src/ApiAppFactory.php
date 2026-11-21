<?php
declare(strict_types=1);

namespace MarvelousHotel;

use MarvelousHotel\Controllers\AuthController;
use MarvelousHotel\Controllers\BookingController;
use MarvelousHotel\Controllers\DashboardController;
use MarvelousHotel\Controllers\HealthController;
use MarvelousHotel\Controllers\PaymentController;
use MarvelousHotel\Controllers\RoomController;
use MarvelousHotel\Middleware\CsrfProtectionMiddleware;
use MarvelousHotel\Middleware\JsonBodyParserMiddleware;
use MarvelousHotel\Middleware\RoleAuthMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteCollectorProxy;

class ApiAppFactory
{
    public static function create(?string $basePath = null): App
    {
        $app = AppFactory::create();

        if ($basePath !== null && $basePath !== '' && $basePath !== '/') {
            $app->setBasePath(rtrim($basePath, '/'));
        }

        $app->add(new JsonBodyParserMiddleware());
        $app->addRoutingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware(
            defined('ENVIRONMENT') && ENVIRONMENT === 'development',
            false,
            false
        );
        $errorMiddleware->setDefaultErrorHandler(function (
            Request $request,
            \Throwable $exception,
            bool $displayErrorDetails
        ) {
            $status = ($exception instanceof HttpException) ? $exception->getCode() : 500;
            if ($status < 400 || $status >= 600) {
                $status = 500;
            }
            $message = ($exception instanceof HttpException || $displayErrorDetails)
                ? $exception->getMessage()
                : 'Internal server error.';
            return JsonResponder::error(new SlimResponse(), $message, $status);
        });

        // Root API discovery
        $app->get('', [HealthController::class, 'index']);
        $app->get('/', [HealthController::class, 'index']);

        // REST API v1 Group
        $app->group('/v1', function (RouteCollectorProxy $group) {
            // Public endpoints
            $group->get('/health', [HealthController::class, 'health']);
            $group->get('/rooms', [RoomController::class, 'list']);
            $group->get('/rooms/{id:[0-9]+}', [RoomController::class, 'show']);
            $group->get('/availability', [RoomController::class, 'availability']);
            $group->get('/auth/status', [AuthController::class, 'status']);

            // Authenticated Bookings
            $group->get('/bookings', [BookingController::class, 'list'])
                ->add(new RoleAuthMiddleware(['user', 'staff', 'admin']));

            $group->post('/bookings/{id:[0-9]+}/cancel', [BookingController::class, 'cancel'])
                ->add(new CsrfProtectionMiddleware())
                ->add(new RoleAuthMiddleware(['user']));

            // Authenticated Payments
            $group->get('/payments', [PaymentController::class, 'list'])
                ->add(new RoleAuthMiddleware(['user', 'staff', 'admin']));

            $group->post('/payments/{id:[0-9]+}/decision', [PaymentController::class, 'decision'])
                ->add(new CsrfProtectionMiddleware())
                ->add(new RoleAuthMiddleware(['staff', 'admin']));

            // Authenticated Role-Scoped Dashboard Metrics
            $group->get('/dashboard/metrics', [DashboardController::class, 'metrics'])
                ->add(new RoleAuthMiddleware(['user', 'staff', 'admin']));
        });

        return $app;
    }
}
