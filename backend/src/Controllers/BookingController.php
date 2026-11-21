<?php
declare(strict_types=1);

namespace MarvelousHotel\Controllers;

use MarvelousHotel\JsonResponder;
use MarvelousHotel\Services\BookingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BookingController
{
    private BookingService $bookings;

    public function __construct(?BookingService $bookings = null)
    {
        $this->bookings = $bookings ?? new BookingService();
    }

    public function list(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $status = isset($q['status']) ? trim((string)$q['status']) : null;
        $limit  = isset($q['limit']) ? (int)$q['limit'] : 50;

        $items = $this->bookings->listForActor((int)\currentUserId(), \currentRole(), $status, $limit);
        return JsonResponder::ok($response, [
            'count'    => count($items),
            'bookings' => $items,
        ]);
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)($args['id'] ?? 0);
        if ($bookingId <= 0) {
            return JsonResponder::error($response, 'Invalid booking ID.', 400);
        }

        $body = $request->getParsedBody();
        $reason = is_array($body) && !empty($body['reason'])
            ? trim((string)$body['reason'])
            : 'Cancelled by guest';

        $res = $this->bookings->cancelGuestBooking($bookingId, (int)\currentUserId(), $reason);
        if (empty($res['ok'])) {
            return JsonResponder::error($response, (string)$res['message'], (int)($res['status_code'] ?? 400));
        }

        return JsonResponder::ok($response, $res);
    }
}
