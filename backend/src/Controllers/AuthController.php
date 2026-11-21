<?php
declare(strict_types=1);

namespace MarvelousHotel\Controllers;

use MarvelousHotel\JsonResponder;
use MarvelousHotel\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function status(Request $request, Response $response): Response
    {
        return JsonResponder::ok($response, $this->auth->getSessionStatus());
    }
}
