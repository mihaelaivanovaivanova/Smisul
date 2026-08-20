<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Starts (or, if one is already in flight, returns) the payment
     * session for an order — normally triggered automatically right after
     * checkout (see CheckoutController::placeOrder), but exposed
     * separately for a customer retrying after a Failed/Cancelled payment,
     * optionally with a different payment_method than the original attempt.
     */
    public function initiate(Request $request, Order $order): PaymentResource
    {
        $this->authorizeAccess($request, $order);

        $enabledMethods = array_map(fn ($method) => $method->value, $this->payments->availablePaymentMethods($order->shipping_carrier));
        $request->validate([
            'payment_method' => ['sometimes', 'string', Rule::in($enabledMethods)],
            'stored_payment_method_id' => ['nullable', 'integer', 'exists:stored_payment_methods,id'],
        ]);

        $method = PaymentMethod::from($request->input('payment_method', PaymentMethod::Card->value));

        return new PaymentResource($this->payments->initiate(
            $order,
            $method,
            $request->filled('stored_payment_method_id') ? (int) $request->input('stored_payment_method_id') : null,
        ));
    }

    /**
     * Polled by the frontend's success/failed/cancelled pages and their
     * loading state — the authoritative source is always this endpoint,
     * never the query parameters iCard redirected the browser back with.
     */
    public function status(Request $request, Order $order): PaymentResource
    {
        $this->authorizeAccess($request, $order);

        $payment = $this->payments->latestForOrder($order);
        abort_if($payment === null, 404, 'No payment found for this order.');

        return new PaymentResource($payment);
    }

    /**
     * Hit once by the frontend when the customer's browser lands back on
     * the success/failed page — logs the return and opportunistically
     * reconciles with the gateway in case the webhook is delayed.
     */
    public function handleReturn(Request $request, Order $order): PaymentResource
    {
        $this->authorizeAccess($request, $order);

        $payment = $this->payments->latestForOrder($order);
        abort_if($payment === null, 404, 'No payment found for this order.');

        return new PaymentResource($this->payments->recordReturn($payment));
    }

    /**
     * Hit by the frontend's cancelled page (customer cancelled at iCard)
     * or a customer-initiated "cancel" action before completing payment.
     */
    public function cancel(Request $request, Order $order): PaymentResource
    {
        $this->authorizeAccess($request, $order);

        return new PaymentResource($this->payments->cancel($order, $request->user()));
    }

    /**
     * Mirrors OrderController::authorizeAccess() — same ownership rules
     * (registered-customer session or guest_access_token) since a payment
     * is only ever reachable through its order.
     */
    private function authorizeAccess(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($order->user_id !== null) {
            abort_unless($user !== null && $user->can('view', $order), 403);

            return;
        }

        abort_unless(
            $order->guest_access_token !== null && hash_equals($order->guest_access_token, (string) $request->query('token')),
            403,
        );
    }
}
