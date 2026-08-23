<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\InvoiceNumberGenerator;
use App\Services\OrderService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly InvoiceNumberGenerator $invoiceNumbers,
        private readonly SettingService $settings,
    ) {}

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
     * A dependency-free (no PDF library) but legally-real accounting
     * document — Smisul isn't VAT-registered, so this is a "стокова
     * разписка"-style first-level accounting document under Закона за
     * счетоводството чл. 6, ал. 1 (seller identity, buyer, goods/quantities/
     * value, document number/date), titled "Фактура" per common Bulgarian
     * practice for non-VAT sellers, not a VAT tax invoice (no ДДС
     * breakdown, since none applies). invoice_number is assigned once,
     * on first request, and reused on every subsequent view/download —
     * see InvoiceNumberGenerator.
     */
    public function invoice(Request $request, Order $order): Response
    {
        $this->authorizeAccess($request, $order);

        $order->load('items');
        $this->invoiceNumbers->generateFor($order);
        $html = view('invoices.order', ['order' => $order, 'seller' => $this->settings->sellerIdentity()])->render();

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
