<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\DataTransferObjects\Checkout\PlaceOrderData;
use App\DataTransferObjects\Shipping\ShippingQuoteRequestData;
use App\Enums\ShippingDeliveryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Http\Requests\Checkout\ShippingQuoteRequest;
use App\Http\Resources\Checkout\LegalDocumentResource;
use App\Http\Resources\Checkout\ShippingMethodResource;
use App\Http\Resources\Checkout\ShippingOfficeResource;
use App\Http\Resources\Checkout\ShippingQuoteResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Cart;
use App\Services\CartService;
use App\Services\LegalDocumentService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ShippingMethodService;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    private const GUEST_TOKEN_HEADER = 'X-Guest-Cart-Token';

    public function __construct(
        private readonly CartService $carts,
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
        private readonly ShippingMethodService $shippingMethods,
        private readonly ShippingService $shipping,
        private readonly LegalDocumentService $legalDocuments,
    ) {}

    public function shippingMethods(): JsonResponse
    {
        return ShippingMethodResource::collection($this->shippingMethods->all())->response();
    }

    /**
     * A live, destination-aware price/eta for a specific carrier + delivery
     * type — distinct from shippingMethods() above, which lists the flat
     * catalog rate with no network call (see ShippingService::quote()).
     */
    public function shippingQuote(ShippingQuoteRequest $request): JsonResponse
    {
        $quote = $this->shipping->quote(
            $request->string('carrier')->toString(),
            new ShippingQuoteRequestData(
                deliveryType: ShippingDeliveryType::from($request->string('delivery_type')->toString()),
                city: $request->string('city')->toString(),
                postalCode: $request->string('postal_code')->toString(),
                weightKg: $request->filled('weight_kg') ? (float) $request->input('weight_kg') : null,
            ),
        );

        return (new ShippingQuoteResource($quote))->response();
    }

    /**
     * Offices/lockers for a carrier, optionally filtered by city — used by
     * the checkout office/locker selector once a carrier requiring one is
     * chosen (see ShippingMethodData::requiresOffice).
     */
    public function shippingOffices(Request $request): JsonResponse
    {
        $request->validate([
            'carrier' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $offices = $this->shipping->offices(
            $request->string('carrier')->toString(),
            $request->filled('city') ? $request->string('city')->toString() : null,
        );

        return ShippingOfficeResource::collection($offices)->response();
    }

    public function legalDocuments(): JsonResponse
    {
        return LegalDocumentResource::collection($this->legalDocuments->current())->response();
    }

    /**
     * Places the order, then immediately starts its payment session —
     * the frontend redirects the customer's browser straight to the
     * returned payment.redirect_url (see the sprint's checkout flow).
     */
    public function placeOrder(PlaceOrderRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $user = $request->user();

        $order = $this->orders->placeOrder($cart, PlaceOrderData::fromArray($request->validated()), $user);
        $payment = $this->payments->initiate($order);

        // initiate() moves the order Pending -> AwaitingPayment via a
        // separately-fetched model instance (see OrderStatusService) — this
        // $order reference needs reloading to reflect that before it's
        // serialized below.
        $order = $order->fresh(OrderService::EAGER_LOAD);

        return (new OrderResource($order))
            ->additional([
                'meta' => ['guest_access_token' => $order->guest_access_token],
                'payment' => (new PaymentResource($payment))->resolve(),
            ])
            ->response()
            ->setStatusCode(201);
    }

    private function resolveCart(Request $request): Cart
    {
        return $this->carts->resolveCart($request->user()?->id, $this->extractGuestToken($request));
    }

    private function extractGuestToken(Request $request): ?string
    {
        $token = $request->header(self::GUEST_TOKEN_HEADER);

        if ($token !== null && ! Str::isUuid($token)) {
            abort(422, 'Invalid cart token.');
        }

        return $token;
    }
}
