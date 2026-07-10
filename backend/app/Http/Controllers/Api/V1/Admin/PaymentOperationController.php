<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\AdminActionLogger;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentOperationController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function reverse(Request $request, Payment $payment): PaymentResource
    {
        $result = $this->payments->reverse($payment, $request->user());
        $this->actionLogger->log($request->user(), 'payment.reversed', $payment, ['payment_id' => $payment->id]);
        return new PaymentResource($result);
    }

    public function refund(Request $request, Payment $payment): PaymentResource
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01']]);
        $result = $this->payments->refund($payment, (string) $data['amount'], $request->user());
        $this->actionLogger->log($request->user(), 'payment.refunded', $payment, ['payment_id' => $payment->id, 'amount' => $data['amount']]);
        return new PaymentResource($result);
    }
}

