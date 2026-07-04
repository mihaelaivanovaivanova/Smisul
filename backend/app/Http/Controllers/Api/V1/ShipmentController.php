<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Customer-facing tracking view — same ownership rules as OrderController
 * (registered-customer session or guest_access_token). Serves the shipment's
 * persisted state; it does not poll the carrier live on every request (see
 * ShippingService::track(), which is the seam a future scheduled sync or
 * admin action would call to refresh it).
 */
class ShipmentController extends Controller
{
    public function show(Request $request, Order $order): ShipmentResource
    {
        $this->authorizeAccess($request, $order);

        $shipment = $order->shipment;
        abort_if($shipment === null, 404, 'No shipment found for this order.');

        return new ShipmentResource($shipment->load('statusEvents'));
    }

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
