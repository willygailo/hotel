<?php
declare(strict_types=1);

namespace MarvelousHotel\Controllers;

use MarvelousHotel\JsonResponder;
use MarvelousHotel\Services\RoomService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RoomController
{
    private RoomService $rooms;

    public function __construct(?RoomService $rooms = null)
    {
        $this->rooms = $rooms ?? new RoomService();
    }

    public function list(Request $request, Response $response): Response
    {
        if (\function_exists('releaseSessionLock')) {
            \releaseSessionLock();
        }
        $q = $request->getQueryParams();
        $items = $this->rooms->listRooms([
            'type_id'        => $q['type_id'] ?? null,
            'guests'         => $q['guests'] ?? null,
            'budget'         => $q['budget'] ?? null,
            'q'              => $q['q'] ?? null,
            'check_in'       => $q['check_in'] ?? '',
            'check_out'      => $q['check_out'] ?? '',
            'only_available' => !empty($q['only_available']),
        ]);

        return JsonResponder::ok($response, [
            'count' => count($items),
            'rooms' => $items,
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return JsonResponder::error($response, 'Invalid room ID.', 400);
        }

        $room = $this->rooms->getRoomById($id);
        if (!$room) {
            return JsonResponder::error($response, 'Room not found.', 404);
        }

        return JsonResponder::ok($response, ['room' => $room]);
    }

    public function availability(Request $request, Response $response): Response
    {
        if (\function_exists('releaseSessionLock')) {
            \releaseSessionLock();
        }
        $q = $request->getQueryParams();
        $roomId   = (int)($q['room_id'] ?? 0);
        $checkIn  = trim((string)($q['check_in'] ?? ''));
        $checkOut = trim((string)($q['check_out'] ?? ''));

        $result = $this->rooms->checkAvailability($roomId, $checkIn, $checkOut);
        if (empty($result['valid'])) {
            return JsonResponder::error($response, (string)($result['error'] ?? 'Invalid availability query.'), 422);
        }

        return JsonResponder::ok($response, $result);
    }
}
