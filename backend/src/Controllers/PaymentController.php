<?php
declare(strict_types=1);

namespace MarvelousHotel\Controllers;

use MarvelousHotel\JsonResponder;
use MarvelousHotel\Services\PaymentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PaymentController
{
    private PaymentService $payments;

    public function __construct(?PaymentService $payments = null)
    {
        $this->payments = $payments ?? new PaymentService();
    }

    public function list(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $status = isset($q['status']) ? trim((string)$q['status']) : null;
        $limit  = isset($q['limit']) ? (int)$q['limit'] : 50;

        $items = $this->payments->listForActor((int)\currentUserId(), \currentRole(), $status, $limit);
        return JsonResponder::ok($response, [
            'count'    => count($items),
            'payments' => $items,
        ]);
    }

    public function decision(Request $request, Response $response, array $args): Response
    {
        $paymentId = (int)($args['id'] ?? 0);
        if ($paymentId <= 0) {
            return JsonResponder::error($response, 'Invalid payment ID.', 400);
        }

        $body = $request->getParsedBody();
        $decision = is_array($body) ? strtolower(trim((string)($body['decision'] ?? ''))) : '';
        if (!in_array($decision, ['verify', 'reject'], true)) {
            return JsonResponder::error($response, "Decision must be 'verify' or 'reject'.", 422);
        }

        $res = $this->payments->applyDecision($paymentId, $decision, (int)\currentUserId(), \currentRole());
        if (empty($res['ok'])) {
            return JsonResponder::error($response, (string)($res['message'] ?? 'Payment update failed.'), 409);
        }

        return JsonResponder::ok($response, $res);
    }
}
