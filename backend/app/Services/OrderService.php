<?php

namespace App\Services;

use App\DataTransferObjects\Checkout\PlaceOrderData;
use App\Enums\OrderStatus;
use App\Events\Order\OrderPlaced;
use App\Exceptions\Checkout\CartItemUnavailableException;
use App\Exceptions\Checkout\EmptyCartException;
use App\Exceptions\Checkout\InvalidShippingMethodException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates order placement: re-validates the cart exactly as it stands
 * right now (never trusting anything the client claims about price or
 * availability — see the per-item checks below), snapshots everything an
 * Order/OrderItem needs to stand alone forever, and converts each item's
 * existing cart reservation (see CartService/InventoryService) into a
 * fulfilled stock decrement. There is no separate "payment pending"
 * reservation window yet — Sprint 5 has no payment step, so placing an
 * order commits stock immediately. When payment is integrated, the
 * intended extension point is here: decreaseStock()+release() below would
 * move to a "payment succeeded" handler, and placeOrder() would leave the
 * cart's existing reservation untouched (still held, not yet fulfilled)
 * until then.
 */
class OrderService
{
    public const EAGER_LOAD = [
        'items',
        'legalAcceptances.legalDocument',
    ];

    public function __construct(
        private readonly CartPricingService $pricing,
        private readonly InventoryService $inventory,
        private readonly ShippingMethodService $shippingMethods,
        private readonly LegalDocumentService $legalDocuments,
        private readonly OrderNumberGenerator $orderNumbers,
    ) {}

    public function placeOrder(Cart $cart, PlaceOrderData $data, ?User $user): Order
    {
        return DB::transaction(function () use ($cart, $data, $user) {
            $cart = Cart::whereKey($cart->id)
                ->with(['items.productVariant.product', 'items.productVariant.prices', 'items.productVariant.inventory'])
                ->lockForUpdate()
                ->first() ?? $cart;

            if ($cart->items->isEmpty()) {
                throw EmptyCartException::create();
            }

            $shippingMethod = $this->shippingMethods->find($data->shippingCarrier)
                ?? throw InvalidShippingMethodException::forCarrier($data->shippingCarrier);

            $acceptedDocuments = $this->legalDocuments->validateAcceptance($data->legalDocumentIds);

            $unavailableSkus = [];
            $lines = [];

            foreach ($cart->items as $item) {
                $variant = $item->productVariant;

                if ($variant === null || ! $this->pricing->isVariantPurchasable($variant) || ! $this->pricing->isItemAvailable($item)) {
                    $unavailableSkus[] = $variant->sku ?? "cart-item-{$item->id}";

                    continue;
                }

                $price = $this->pricing->unitPrice($variant, $cart->currency);

                if ($price === null) {
                    $unavailableSkus[] = $variant->sku;

                    continue;
                }

                $lines[] = [
                    'item' => $item,
                    'variant' => $variant,
                    'unit_price' => (float) $price->amount,
                    'line_total' => $this->pricing->lineTotal($item, $cart->currency),
                ];
            }

            if ($unavailableSkus !== []) {
                throw new CartItemUnavailableException($unavailableSkus);
            }

            $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);
            $shippingTotal = $shippingMethod->price;
            $discountTotal = 0.0;
            $taxTotal = 0.0;

            $order = Order::create([
                'order_number' => $this->orderNumbers->generate(),
                'user_id' => $user?->id,
                'guest_access_token' => $user === null ? (string) Str::uuid() : null,
                'status' => OrderStatus::Pending,
                'currency' => $cart->currency,
                'customer_first_name' => $data->customer->firstName,
                'customer_last_name' => $data->customer->lastName,
                'customer_email' => $data->customer->email,
                'customer_phone' => $data->customer->phone,
                'customer_company' => $data->customer->company,
                'customer_vat_number' => $data->customer->vatNumber,
                'delivery_notes' => $data->deliveryNotes,
                'shipping_country' => $data->address->country,
                'shipping_city' => $data->address->city,
                'shipping_postal_code' => $data->address->postalCode,
                'shipping_address_line' => $data->address->addressLine,
                'shipping_apartment' => $data->address->apartment,
                'shipping_carrier' => $shippingMethod->carrier,
                'shipping_method_label' => $shippingMethod->label,
                'shipping_price' => $shippingTotal,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => round($subtotal - $discountTotal + $shippingTotal + $taxTotal, 2),
            ]);

            foreach ($lines as $line) {
                $variant = $line['variant'];

                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'quantity' => $line['item']->quantity,
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);

                $this->fulfillReservation($line['item']);
            }

            foreach ($acceptedDocuments as $document) {
                $order->legalAcceptances()->create([
                    'legal_document_id' => $document->id,
                    'accepted_at' => now(),
                ]);
            }

            // Not CartService::clear() — that would also try to release
            // these items' inventory reservations, which fulfillReservation()
            // above has already released (as an actual stock decrement, not
            // a no-op release) for every line here.
            $cart->items()->delete();

            OrderPlaced::dispatch($order);

            return $order->load(self::EAGER_LOAD);
        });
    }

    /**
     * Converts a cart item's existing reservation into a real stock
     * decrement: the quantity was already subtracted from availableQuantity
     * (on_hand - reserved) the moment it was added to the cart (see
     * CartService::addItem) — placing the order now makes that permanent by
     * moving it out of on_hand entirely and releasing the reservation
     * bookkeeping for it, leaving availableQuantity() unchanged by this step.
     */
    private function fulfillReservation(CartItem $item): void
    {
        $variant = $item->productVariant;
        $inventory = $variant?->inventory()->lockForUpdate()->first();

        if ($inventory === null) {
            return;
        }

        $this->inventory->decreaseStock($inventory, $item->quantity);
        $this->inventory->release($inventory, $item->quantity);
    }
}
