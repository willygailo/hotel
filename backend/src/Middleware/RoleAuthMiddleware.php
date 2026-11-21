<?php
declare(strict_types=1);

namespace MarvelousHotel\Middleware;

use MarvelousHotel\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class RoleAuthMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowedRoles;

    /**
     * @param string[] $allowedRoles Allowed roles (e.g., ['user'], ['staff', 'admin'], ['user', 'staff', 'admin'])
     */
    public function __construct(array $allowedRoles = ['user', 'staff', 'admin'])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if (!\isLoggedIn()) {
            return JsonResponder::error(
                new SlimResponse(),
                'Authentication required. Please log in to access this API endpoint.',
                401
            );
        }

        $role = \currentRole();
        if (!in_array($role, $this->allowedRoles, true)) {
            return JsonResponder::error(
                new SlimResponse(),
                'Forbidden: your account role does not have access to this resource.',
                403,
                ['required_roles' => $this->allowedRoles, 'current_role' => $role]
            );
        }

        if ($request->getMethod() === 'GET' && \function_exists('releaseSessionLock')) {
            \releaseSessionLock();
        }

        return $handler->handle($request);
    }
}
