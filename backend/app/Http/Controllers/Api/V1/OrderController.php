<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    /**
     * The authenticated customer's own order history, newest first. Guests
     * have no account to list orders against — this route sits behind
     * auth:sanctum (see routes/api.php).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()
            ->orders()
            ->with('items')
            ->latest()
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    /**
     * Registered customers may view their own orders (see OrderPolicy).
     * Guests instead prove ownership with the guest_access_token minted at
     * placement time (see CheckoutController::placeOrder) and passed back
     * as ?token= — there's no account to check ownership against.
     */
    public function show(Request $request, Order $order): OrderResource
    {
        $this->authorizeAccess($request, $order);

        return new OrderResource($order->load(OrderService::EAGER_LOAD));
    }

    /**
     * A minimal, dependency-free invoice document — see the view for the
     * "not a real tax invoice" disclaimer. Real invoicing (PDF generation,
     * legal numbering sequence, VAT breakdown) is a future extension point;
     * this establishes the endpoint/URL shape it would live behind.
     */
    public function invoice(Request $request, Order $order): Response
    {
        $this->authorizeAccess($request, $order);

        $order->load('items');
        $html = view('invoices.order', ['order' => $order])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "attachment; filename=\"invoice-{$order->order_number}.html\"",
        ]);
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
