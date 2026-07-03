<?php

namespace App\Services;

use App\DataTransferObjects\Checkout\PlaceOrderData;
use App\Enums\OrderStatus;
use App\Events\Order\OrderPlaced;
use App\Exceptions\Checkout\CartItemUnavailableException;
use App\Exceptions\Checkout\EmptyCartException;
use App\Exceptions\Checkout\InvalidShippingMethodException;
use App\Exceptions\Order\InvalidOrderStatusTransitionException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates order placement: re-validates the cart exactly as it stands
 * right now (never trusting anything the client claims about price or
 * availability — see the per-item checks below) and snapshots everything
 * an Order/OrderItem needs to stand alone forever.
 *
 * Stock is deliberately NOT decremented here. Each item's inventory
 * reservation was already made when it was added to the cart (see
 * CartService::addItem/InventoryService::reserve) and stays exactly as-is
 * through placement — placing an order just stops that reservation from
 * being tied to a cart_item (which is deleted below) without releasing it,
 * holding the stock until the outcome is known. There are exactly two ways
 * out of that held state, both below: confirmPayment() turns the
 * reservation into a real decrement once payment succeeds, and cancel()
 * releases it if payment fails/expires or the order is otherwise cancelled
 * before being paid. Nothing in this sprint calls either yet (no payment
 * gateway is integrated) — they exist as the seam the payment sprint wires
 * up, so a Pending order's held stock has a well-defined resolution rather
 * than being a dangling TODO.
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
            }

            foreach ($acceptedDocuments as $document) {
                $order->legalAcceptances()->create([
                    'legal_document_id' => $document->id,
                    'accepted_at' => now(),
                ]);
            }

            // Not CartService::clear() — that would release each item's
            // inventory reservation, which must instead stay held (see the
            // class docblock) until confirmPayment() or cancel() resolves it.
            $cart->items()->delete();

            OrderPlaced::dispatch($order);

            return $order->load(self::EAGER_LOAD);
        });
    }

    /**
     * The payment-succeeded seam (see class docblock): converts every item's
     * held reservation into a real, permanent stock decrement and moves the
     * order to Processing. No caller exists yet in this sprint — a future
     * payment webhook/gateway callback is expected to invoke this.
     */
    public function confirmPayment(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first() ?? $order;

            if ($order->status !== OrderStatus::Pending) {
                throw InvalidOrderStatusTransitionException::notPending($order->order_number, $order->status);
            }

            foreach ($order->items as $item) {
                $inventory = $item->productVariant?->inventory()->lockForUpdate()->first();

                if ($inventory === null) {
                    continue;
                }

                $this->inventory->decreaseStock($inventory, $item->quantity);
                $this->inventory->release($inventory, $item->quantity);
            }

            $order->update(['status' => OrderStatus::Processing]);

            return $order->load(self::EAGER_LOAD);
        });
    }

    /**
     * The payment-failed/abandoned seam (see class docblock): releases
     * every item's held reservation without decrementing stock, and moves
     * the order to Cancelled. No caller exists yet in this sprint.
     */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first() ?? $order;

            if ($order->status !== OrderStatus::Pending) {
                throw InvalidOrderStatusTransitionException::notPending($order->order_number, $order->status);
            }

            foreach ($order->items as $item) {
                $inventory = $item->productVariant?->inventory()->lockForUpdate()->first();

                if ($inventory !== null) {
                    $this->inventory->release($inventory, $item->quantity);
                }
            }

            $order->update(['status' => OrderStatus::Cancelled]);

            return $order->load(self::EAGER_LOAD);
        });
    }
}
