<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Registered customers may view their own orders (see OrderPolicy).
     * Guests instead prove ownership with the guest_access_token minted at
     * placement time (see CheckoutController::placeOrder) and passed back
     * as ?token= — there's no account to check ownership against.
     */
    public function show(Request $request, Order $order): OrderResource
    {
        $user = $request->user();

        if ($order->user_id !== null) {
            abort_unless($user !== null && $user->can('view', $order), 403);
        } else {
            abort_unless(
                $order->guest_access_token !== null && hash_equals($order->guest_access_token, (string) $request->query('token')),
                403,
            );
        }

        return new OrderResource($order->load(OrderService::EAGER_LOAD));
    }
}
