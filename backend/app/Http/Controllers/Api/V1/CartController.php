<?php

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\Cart\AddCartItemData;
use App\DataTransferObjects\Cart\UpdateCartItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\Cart\CartResource;
use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    private const GUEST_TOKEN_HEADER = 'X-Guest-Cart-Token';

    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolve($request);

        return $this->respond($cart);
    }

    public function storeItem(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->resolve($request);
        $result = $this->carts->addItem($cart, AddCartItemData::fromArray($request->validated()));

        return $this->respond($cart, $result->wasCreated ? 201 : 200);
    }

    public function updateItem(UpdateCartItemRequest $request, int $item): JsonResponse
    {
        $cart = $this->resolve($request);
        $this->carts->updateItemQuantity($cart, $item, UpdateCartItemData::fromArray($request->validated()));

        return $this->respond($cart);
    }

    public function destroyItem(Request $request, int $item): JsonResponse
    {
        $cart = $this->resolve($request);
        $this->carts->removeItem($cart, $item);

        return $this->respond($cart);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->resolve($request);
        $this->carts->clear($cart);

        return $this->respond($cart);
    }

    private function resolve(Request $request): Cart
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

    private function respond(Cart $cart, int $status = 200): JsonResponse
    {
        $cart = $cart->fresh(CartService::EAGER_LOAD) ?? $cart;

        return (new CartResource($cart))
            ->additional(['meta' => ['guest_token' => $cart->guest_token]])
            ->response()
            ->setStatusCode($status);
    }
}
