<?php

namespace App\Http\Resources\Cart;

use App\Models\Cart;
use App\Services\CartPricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartPricingService $pricing */
        $pricing = app(CartPricingService::class);
        $totals = $pricing->totals($this->resource);

        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'items' => $this->items->map(
                fn ($item) => new CartItemResource($item, $this->currency)
            )->values(),
            'items_count' => $this->items->count(),
            'total_quantity' => $this->totalQuantity(),
            'totals' => [
                'subtotal' => $totals->subtotal,
                'discount_total' => $totals->discountTotal,
                'shipping_total' => $totals->shippingTotal,
                'tax_total' => $totals->taxTotal,
                'grand_total' => $totals->grandTotal,
                'currency' => $totals->currency,
            ],
        ];
    }
}
