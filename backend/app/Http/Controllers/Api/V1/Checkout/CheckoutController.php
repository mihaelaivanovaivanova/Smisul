<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\DataTransferObjects\Checkout\PlaceOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Http\Resources\Checkout\LegalDocumentResource;
use App\Http\Resources\Checkout\ShippingMethodResource;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Services\CartService;
use App\Services\LegalDocumentService;
use App\Services\OrderService;
use App\Services\ShippingMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    private const GUEST_TOKEN_HEADER = 'X-Guest-Cart-Token';

    public function __construct(
        private readonly CartService $carts,
        private readonly OrderService $orders,
        private readonly ShippingMethodService $shippingMethods,
        private readonly LegalDocumentService $legalDocuments,
    ) {}

    public function shippingMethods(): JsonResponse
    {
        return ShippingMethodResource::collection($this->shippingMethods->all())->response();
    }

    public function legalDocuments(): JsonResponse
    {
        return LegalDocumentResource::collection($this->legalDocuments->current())->response();
    }

    public function placeOrder(PlaceOrderRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $user = $request->user();

        $order = $this->orders->placeOrder($cart, PlaceOrderData::fromArray($request->validated()), $user);

        return (new OrderResource($order))
            ->additional(['meta' => ['guest_access_token' => $order->guest_access_token]])
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
