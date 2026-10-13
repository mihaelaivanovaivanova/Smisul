<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\AuthorizesOrderAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Customer-facing tracking view — same ownership rules as OrderController
 * (registered-customer session or guest_access_token - see
 * AuthorizesOrderAccess). Serves the shipment's persisted state; it does not
 * poll the carrier live on every request (see ShippingService::track(),
 * which is the seam a future scheduled sync or admin action would call to
 * refresh it).
 */
class ShipmentController extends Controller
{
    use AuthorizesOrderAccess;

    public function show(Request $request, Order $order): ShipmentResource
    {
        $this->authorizeOrderAccess($request, $order);

        $shipment = $order->shipment;
        abort_if($shipment === null, 404, 'No shipment found for this order.');

        return new ShipmentResource($shipment->load('statusEvents'));
    }
}
