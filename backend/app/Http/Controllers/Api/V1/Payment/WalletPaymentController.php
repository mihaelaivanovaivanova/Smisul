<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Called directly by iCard's wallet SDK (ICardIpgGAPay) on the frontend —
 * see components/checkout/IcardWalletButtons.tsx, which configures
 * `tokenProviderSessionUrl`/`processPaymentUrl` to point here. Unlike the
 * rest of the payment flow, these aren't shaped by PaymentService/
 * PaymentController's own request contracts, but by whatever fields the
 * (third-party, source-unavailable) wallet SDK itself sends — field names
 * are read case-insensitively with the same fallbacks the reference
 * implementation uses, since there's no official field-name documentation
 * to validate strictly against.
 */
class WalletPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function tokenProviderSession(Request $request, Order $order): JsonResponse
    {
        $this->authorizeAccess($request, $order);

        $payment = $this->payments->latestForOrder($order);
        abort_if($payment === null, 404, 'No payment found for this order.');

        $response = $this->payments->createWalletValidationSession(
            $payment,
            $this->field($request, ['MerchantUrl', 'merchantUrl']) ?? (string) config('app.frontend_url'),
            $this->field($request, ['ValidationURL', 'validationUrl']) ?? '',
            $this->field($request, ['DisplayName', 'displayName']) ?? (string) config('services.icard.mid_name'),
        );

        return response()->json($response);
    }

    public function tokenizedCardPurchase(Request $request, Order $order): JsonResponse
    {
        $this->authorizeAccess($request, $order);

        $payment = $this->payments->latestForOrder($order);
        abort_if($payment === null, 404, 'No payment found for this order.');

        $tokenizedCard = $this->field($request, ['TokenizedCard', 'tokenizedCard']);
        abort_if($tokenizedCard === null, 422, 'Missing tokenized card.');

        $provider = mb_strtolower((string) $this->field($request, ['TokenizedCardProvider', 'tokenizedCardProvider']));
        $method = $provider === 'google' ? PaymentMethod::GooglePay : PaymentMethod::ApplePay;

        $response = $this->payments->processTokenizedWalletPurchase($payment, $method, $tokenizedCard);

        return response()->json($response);
    }

    /**
     * @param  list<string>  $candidates
     */
    private function field(Request $request, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($request->has($candidate)) {
                return (string) $request->input($candidate);
            }
        }

        return null;
    }

    /**
     * Mirrors PaymentController::authorizeAccess() — same ownership rules
     * (registered-customer session or guest_access_token) since a wallet
     * payment call is only ever reachable through its order.
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
