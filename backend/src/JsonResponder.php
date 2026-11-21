<?php
declare(strict_types=1);

namespace MarvelousHotel;

use Psr\Http\Message\ResponseInterface as Response;

class JsonResponder
{
    public static function ok(Response $response, mixed $data, int $status = 200, array $extraMeta = []): Response
    {
        $payload = [
            'ok'   => true,
            'data' => $data,
            'meta' => array_merge([
                'framework' => 'Slim 4',
                'portal'    => defined('ACTIVE_PORTAL') ? ACTIVE_PORTAL : 'user',
                'timestamp' => date('c'),
            ], $extraMeta),
        ];

        $response->getBody()->write((string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public static function error(Response $response, string $message, int $status = 400, array $details = []): Response
    {
        $payload = [
            'ok'      => false,
            'error'   => $message,
            'details' => $details,
            'meta'    => [
                'framework' => 'Slim 4',
                'portal'    => defined('ACTIVE_PORTAL') ? ACTIVE_PORTAL : 'user',
                'timestamp' => date('c'),
            ],
        ];

        $response->getBody()->write((string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
