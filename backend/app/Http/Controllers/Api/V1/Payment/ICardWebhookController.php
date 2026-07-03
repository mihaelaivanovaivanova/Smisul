<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated endpoint — iCard's servers call this directly,
 * so there's no session/token to check. Trust is established entirely by
 * PaymentService::handleWebhook()'s signature verification instead (see
 * ICardPaymentGateway::verifySignature()).
 */
class ICardWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function handle(Request $request): JsonResponse
    {
        $this->payments->handleWebhook($request);

        return response()->json(['status' => 'ok']);
    }
}
