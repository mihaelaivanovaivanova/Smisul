<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Order;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Shared by OrderController and ShipmentController - identical ownership
 * rule (registered-customer session or guest_access_token), previously
 * duplicated in both. The 403 body carries a machine-readable `reason` (not
 * just a message) specifically so the frontend can tell apart two very
 * different situations that look identical as a bare 403: "log in, this
 * order belongs to your account" vs "this guest order's link is wrong/
 * missing its token" - showing a login prompt to a guest who has no
 * account to log into would be actively misleading.
 */
trait AuthorizesOrderAccess
{
    private function authorizeOrderAccess(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($order->user_id !== null) {
            if ($user !== null && $user->can('view', $order)) {
                return;
            }

            throw new HttpResponseException(response()->json([
                'message' => 'You must be signed in to view this order.',
                'reason' => 'login_required',
            ], 403));
        }

        if ($order->guest_access_token !== null && hash_equals($order->guest_access_token, (string) $request->query('token'))) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'This link is missing or has an invalid access token.',
            'reason' => 'guest_link_invalid',
        ], 403));
    }
}
