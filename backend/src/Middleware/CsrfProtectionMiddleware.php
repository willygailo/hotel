<?php
declare(strict_types=1);

namespace MarvelousHotel\Middleware;

use MarvelousHotel\JsonResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class CsrfProtectionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->getHeaderLine('X-CSRF-Token');
            if ($token === '') {
                $body = $request->getParsedBody();
                if (is_array($body)) {
                    $token = (string)($body[CSRF_TOKEN_NAME] ?? '');
                }
            }

            if ($token === '' || !\verifyCsrfToken($token)) {
                return JsonResponder::error(
                    new SlimResponse(),
                    'Invalid or missing CSRF security token.',
                    403
                );
            }
        }

        return $handler->handle($request);
    }
}
