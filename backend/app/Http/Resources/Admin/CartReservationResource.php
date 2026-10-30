<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One cart_item row holding stock reserved against a variant — see
 * ProductVariantController::reservations(). Exists purely so an admin
 * looking at "on hand but 0 available" can see WHY: which cart(s) are
 * holding the difference, whose they are (if known), and how long ago that
 * reservation was last touched, as a staleness signal (no automatic
 * cart/reservation expiry exists in this app - see InventoryService's own
 * docblock). releaseReservation() below is the admin's way to clear one.
 *
 * @mixin \App\Models\CartItem
 */
class CartReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'cart_id' => $this->cart_id,
            'is_guest' => $this->cart?->isGuestCart() ?? true,
            'customer_email' => $this->cart?->user?->email,
            'guest_token' => $this->cart?->guest_token,
            'added_at' => $this->created_at?->toIso8601String(),
            'last_touched_at' => $this->updated_at?->toIso8601String(),
            'days_since_last_touched' => $this->updated_at !== null ? (int) $this->updated_at->diffInDays(now()) : null,
        ];
    }
}
