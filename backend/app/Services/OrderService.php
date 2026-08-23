<?php

namespace App\Services;

use App\DataTransferObjects\Admin\OrderFilterData;
use App\DataTransferObjects\Checkout\PlaceOrderData;
use App\Enums\OrderStatus;
use App\Enums\ShippingDeliveryType;
use App\Events\Order\OrderPlaced;
use App\Exceptions\Checkout\CartItemUnavailableException;
use App\Exceptions\Checkout\EmptyCartException;
use App\Exceptions\Checkout\InvalidShippingMethodException;
use App\Exceptions\Order\InvalidOrderStatusTransitionException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
 * before being paid. Nothing in this sprint calls either automatically yet
 * (no payment gateway is integrated) — they exist as the seam a future
 * payment webhook/gateway callback wires up, and the admin status-update
 * endpoint can call cancel() directly today.
 */
class OrderService
{
    public const EAGER_LOAD = [
        'items.productVariant.product',
        'legalAcceptances.legalDocument',
        'statusHistories.changedBy',
    ];

    /**
     * Adds payment attempts and shipment/tracking to the base eager-load
     * list — admin-only, since the customer-facing endpoints already have
     * dedicated Payment/Shipment endpoints and don't need them embedded.
     */
    public const ADMIN_EAGER_LOAD = [
        ...self::EAGER_LOAD,
        'payments.transactions',
        'payments.webhookLogs',
        'shipment.statusEvents',
    ];

    public function __construct(
        private readonly CartPricingService $pricing,
        private readonly InventoryService $inventory,
        private readonly ShippingMethodService $shippingMethods,
        private readonly LegalDocumentService $legalDocuments,
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly OrderStatusService $orderStatus,
    ) {}

    public function placeOrder(Cart $cart, PlaceOrderData $data, ?User $user): Order
    {
        return DB::transaction(function () use ($cart, $data, $user) {
            $cart = Cart::whereKey($cart->id)
                ->with([
                    'items.productVariant.product.promotions',
                    'items.productVariant.product.categories.promotions',
                    'items.productVariant.prices',
                    'items.productVariant.inventory',
                ])
                ->lockForUpdate()
                ->first() ?? $cart;

            if ($cart->items->isEmpty()) {
                throw EmptyCartException::create();
            }

            $shippingMethod = $this->shippingMethods->find($data->shippingCarrier, $data->shippingDeliveryType)
                ?? throw InvalidShippingMethodException::forCarrierAndDeliveryType($data->shippingCarrier, $data->shippingDeliveryType);

            // чл. 54, ал. 2 ЗЗП refund cap needs "cheapest standard option
            // AT THE TIME OF THIS ORDER", which can only be answered
            // correctly later if it's captured now - carrier prices and
            // promos (e.g. BOX NOW's free-shipping window) change over
            // time. See Order::cheapestStandardShippingPriceAtPlacement().
            $shippingRateSnapshot = array_map(
                fn ($method) => [
                    'carrier' => $method->carrier->value,
                    'delivery_type' => $method->deliveryType->value,
                    'label' => $method->label,
                    'price' => $method->price,
                ],
                $this->shippingMethods->all(),
            );

            if ($shippingMethod->requiresOffice && $data->shippingOfficeId === null) {
                throw InvalidShippingMethodException::officeRequired($data->shippingCarrier, $data->shippingDeliveryType);
            }

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

                $compareAtPrice = $price->compare_at_amount !== null ? (float) $price->compare_at_amount : null;
                $discountAmount = $compareAtPrice !== null && $compareAtPrice > (float) $price->amount
                    ? round(($compareAtPrice - (float) $price->amount) * $item->quantity, 2)
                    : 0.0;

                $lines[] = [
                    'item' => $item,
                    'variant' => $variant,
                    'unit_price' => (float) $price->amount,
                    'compare_at_price' => $compareAtPrice,
                    'discount_amount' => $discountAmount,
                    'promotion_name' => $variant->product->activePromotions()->first()?->name,
                    'line_total' => $this->pricing->lineTotal($item, $cart->currency),
                ];
            }

            if ($unavailableSkus !== []) {
                throw new CartItemUnavailableException($unavailableSkus);
            }

            $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);
            $shippingTotal = $shippingMethod->price;
            $discountTotal = round(array_sum(array_column($lines, 'discount_amount')), 2);
            $taxTotal = 0.0;

            $billingAddress = $data->billingSameAsShipping ? $data->address : $data->billingAddress;

            // For office/locker pickup, the parcel's actual destination is
            // the pickup point, not the free-text address the form still
            // collects (that's kept for billing purposes) — store the
            // office's own city/address as the shipping address so it
            // reflects where the order will really be delivered.
            $isOfficeDelivery = ShippingDeliveryType::from($data->shippingDeliveryType)->requiresOfficeSelection();
            $shippingCity = $isOfficeDelivery && $data->shippingOfficeCity !== null ? $data->shippingOfficeCity : $data->address->city;
            $shippingAddressLine = $isOfficeDelivery && $data->shippingOfficeAddress !== null
                ? trim((string) $data->shippingOfficeName.', '.$data->shippingOfficeAddress, ', ')
                : $data->address->addressLine;

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
                'wants_invoice' => $data->wantsInvoice,
                'delivery_notes' => $data->deliveryNotes,
                'shipping_country' => $data->address->country,
                'shipping_city' => $shippingCity,
                'shipping_postal_code' => $data->address->postalCode,
                'shipping_address_line' => $shippingAddressLine,
                'shipping_apartment' => $data->address->apartment,
                'billing_same_as_shipping' => $data->billingSameAsShipping,
                'billing_country' => $billingAddress->country,
                'billing_city' => $billingAddress->city,
                'billing_postal_code' => $billingAddress->postalCode,
                'billing_address_line' => $billingAddress->addressLine,
                'billing_apartment' => $billingAddress->apartment,
                'shipping_carrier' => $shippingMethod->carrier,
                'shipping_delivery_type' => $shippingMethod->deliveryType,
                'shipping_office_id' => $shippingMethod->requiresOffice ? $data->shippingOfficeId : null,
                'shipping_office_name' => $shippingMethod->requiresOffice ? $data->shippingOfficeName : null,
                'shipping_method_label' => $shippingMethod->label,
                'shipping_price' => $shippingTotal,
                'shipping_rate_snapshot' => $shippingRateSnapshot,
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
                    'compare_at_price' => $line['compare_at_price'],
                    'line_total' => $line['line_total'],
                    'discount_amount' => $line['discount_amount'],
                    'promotion_name' => $line['promotion_name'],
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

            $this->orderStatus->recordInitial($order);

            OrderPlaced::dispatch($order);

            return $order->load(self::EAGER_LOAD);
        });
    }

    /**
     * The payment-succeeded seam (see class docblock): converts every item's
     * held reservation into a real, permanent stock decrement and moves the
     * order to Paid. No caller exists yet in this sprint — a future payment
     * webhook/gateway callback is expected to invoke this.
     */
    public function confirmPayment(Order $order, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $note) {
            $order = Order::whereKey($order->id)->with('items.productVariant.inventory')->lockForUpdate()->first() ?? $order;

            if (! in_array(OrderStatus::Paid, $this->orderStatus->allowedTransitions($order->status), strict: true)) {
                throw InvalidOrderStatusTransitionException::notAllowed($order->order_number, $order->status, OrderStatus::Paid);
            }

            foreach ($order->items as $item) {
                $this->fulfillReservation($item->productVariant, $item->quantity);
            }

            return $this->orderStatus->transitionTo($order, OrderStatus::Paid, changedBy: null, note: $note ?? 'Payment confirmed');
        });
    }

    /**
     * The payment-failed/abandoned seam (see class docblock): releases
     * every item's held reservation without decrementing stock, and moves
     * the order to Cancelled. $cancelledBy is null for system/automated
     * cancellations (e.g. an expired pending order) and set for an
     * admin-initiated one (see Api\V1\Admin\OrderController).
     */
    public function cancel(Order $order, ?User $cancelledBy = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $cancelledBy, $note) {
            $order = Order::whereKey($order->id)->with('items.productVariant.inventory')->lockForUpdate()->first() ?? $order;

            // Only a not-yet-paid order still holds a reservation to release —
            // an order cancelled after payment (Paid or later) already had its
            // stock permanently committed by confirmPayment() and needs a
            // real restock/refund process instead, which is out of scope here.
            if (in_array($order->status, [OrderStatus::Pending, OrderStatus::AwaitingPayment], strict: true)) {
                foreach ($order->items as $item) {
                    $inventory = $item->productVariant?->inventory()->lockForUpdate()->first();

                    if ($inventory !== null) {
                        $this->inventory->release($inventory, $item->quantity);
                    }
                }
            }

            return $this->orderStatus->transitionTo($order, OrderStatus::Cancelled, $cancelledBy, $note);
        });
    }

    /**
     * Admin order listing: search (order number, customer name/email),
     * status filter, placement-date range, and sort — all optional and
     * combinable. No ownership scoping (unlike the customer-facing index);
     * gating this to administrators is the caller's job (route middleware).
     */
    public function listForAdmin(OrderFilterData $filters): LengthAwarePaginator
    {
        $query = Order::query()->with('items');

        if ($filters->userId !== null) {
            $query->where('user_id', $filters->userId);
        }

        if ($filters->search !== null && $filters->search !== '') {
            $term = "%{$filters->search}%";
            $query->where(function ($query) use ($term) {
                $query->where('order_number', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('customer_first_name', 'like', $term)
                    ->orWhere('customer_last_name', 'like', $term);
            });
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->dateFrom !== null) {
            $query->whereDate('created_at', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->whereDate('created_at', '<=', $filters->dateTo);
        }

        match ($filters->sort) {
            'oldest' => $query->oldest(),
            'total_asc' => $query->orderBy('grand_total'),
            'total_desc' => $query->orderByDesc('grand_total'),
            default => $query->latest(),
        };

        return $query->paginate($filters->perPage, page: $filters->page);
    }

    /**
     * @return array<string, mixed>
     */
    public function statistics(): array
    {
        $ordersByStatus = Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $revenueStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Packed->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
            OrderStatus::Completed->value,
        ];

        return [
            'total_orders' => array_sum($ordersByStatus->all()),
            'orders_by_status' => collect(OrderStatus::cases())
                ->mapWithKeys(fn (OrderStatus $status) => [$status->value => (int) ($ordersByStatus[$status->value] ?? 0)])
                ->all(),
            'total_revenue' => (float) Order::query()->whereIn('status', $revenueStatuses)->sum('grand_total'),
            'orders_today' => Order::query()->whereDate('created_at', now()->toDateString())->count(),
            'revenue_today' => (float) Order::query()
                ->whereIn('status', $revenueStatuses)
                ->whereDate('created_at', now()->toDateString())
                ->sum('grand_total'),
        ];
    }

    /**
     * Converts a held reservation into a real, permanent stock decrement:
     * the quantity was already subtracted from availableQuantity() the
     * moment it was added to the cart (see InventoryService::reserve) — this
     * makes that permanent by moving it out of on_hand and releasing the
     * reservation bookkeeping for it, leaving availableQuantity() unchanged.
     */
    private function fulfillReservation(?ProductVariant $variant, int $quantity): void
    {
        $inventory = $variant?->inventory()->lockForUpdate()->first();

        if ($inventory === null) {
            return;
        }

        $this->inventory->decreaseStock($inventory, $quantity);
        $this->inventory->release($inventory, $quantity);
    }
}
