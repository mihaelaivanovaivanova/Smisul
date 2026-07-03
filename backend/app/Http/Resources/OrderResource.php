<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderLegalAcceptance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'customer' => [
                'first_name' => $this->customer_first_name,
                'last_name' => $this->customer_last_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'company' => $this->customer_company,
                'vat_number' => $this->customer_vat_number,
            ],
            'address' => [
                'country' => $this->shipping_country,
                'city' => $this->shipping_city,
                'postal_code' => $this->shipping_postal_code,
                'address_line' => $this->shipping_address_line,
                'apartment' => $this->shipping_apartment,
            ],
            'billing_same_as_shipping' => $this->billing_same_as_shipping,
            'billing_address' => [
                'country' => $this->billing_country,
                'city' => $this->billing_city,
                'postal_code' => $this->billing_postal_code,
                'address_line' => $this->billing_address_line,
                'apartment' => $this->billing_apartment,
            ],
            'delivery_notes' => $this->delivery_notes,
            'shipping' => [
                'carrier' => $this->shipping_carrier->value,
                'label' => $this->shipping_method_label,
                'price' => (float) $this->shipping_price,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'totals' => [
                'subtotal' => (float) $this->subtotal,
                'discount_total' => (float) $this->discount_total,
                'shipping_total' => (float) $this->shipping_price,
                'tax_total' => (float) $this->tax_total,
                'grand_total' => (float) $this->grand_total,
                'currency' => $this->currency,
            ],
            'legal_acceptances' => $this->whenLoaded(
                'legalAcceptances',
                fn () => $this->legalAcceptances->map(fn (OrderLegalAcceptance $acceptance) => [
                    'type' => $acceptance->legalDocument->type->value,
                    'version' => $acceptance->legalDocument->version,
                    'accepted_at' => $acceptance->accepted_at->toIso8601String(),
                ])->values(),
            ),
            'timeline' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'placed_at' => $this->created_at->toIso8601String(),
        ];
    }
}
