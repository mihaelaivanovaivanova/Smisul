<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manual order entry for the admin panel — a phone/in-person sale typed
 * straight in, bypassing the cart/checkout flow entirely (no email, no
 * legal-document acceptance, exactly one line item). Kept separate from
 * OrderService (which owns the real checkout path) rather than added to it,
 * so this stays free to make simplifying assumptions checkout can't (see
 * StoreManualOrderRequest's own docblock) without complicating that class's
 * already-large surface.
 *
 * Created directly as Confirmed — never Pending, and never through
 * OrderStatusService::transitionTo() — so neither CreateShipmentOnOrderPaid
 * nor SendOrderStatusEmails fires (both listen for OrderStatusChanged,
 * dispatched only by transitionTo()): a manual order is reviewed by the
 * admin who just typed it in and shipped on purpose from the order detail
 * page whenever they're ready, not the instant it's saved.
 */
class AdminOrderService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array{
     *     customer_first_name: string,
     *     customer_last_name: string,
     *     customer_phone: string,
     *     shipping_carrier: string,
     *     shipping_delivery_type: string,
     *     shipping_office_id: string,
     *     shipping_office_name: string,
     *     shipping_office_city: string,
     *     shipping_office_address: string,
     *     shipping_price: float,
     *     payment_method: string,
     *     cod_fee: float,
     *     product_variant_id: int,
     *     quantity: int,
     * }  $data
     */
    public function createManual(array $data, User $admin): Order
    {
        return DB::transaction(function () use ($data, $admin) {
            $variant = ProductVariant::with(['product', 'prices', 'inventory'])
                ->lockForUpdate()
                ->findOrFail($data['product_variant_id']);

            $quantity = (int) $data['quantity'];
            $unitPrice = (float) ($variant->priceFor('EUR')?->amount ?? 0);
            $subtotal = round($unitPrice * $quantity, 2);

            if ($variant->inventory !== null) {
                $this->inventory->decreaseStock($variant->inventory, $quantity);
            }

            $shippingPrice = round((float) $data['shipping_price'], 2);
            // Trusted as submitted (not recomputed) — see this class's own
            // docblock and StoreManualOrderRequest: the admin form already
            // prefilled this from the real default and may have edited or
            // zeroed it out on purpose.
            $codFee = round((float) $data['cod_fee'], 2);

            $carrier = ShippingCarrier::from($data['shipping_carrier']);
            $deliveryType = ShippingDeliveryType::from($data['shipping_delivery_type']);

            $order = Order::create([
                'order_number' => $this->orderNumbers->generate(),
                'user_id' => null,
                'guest_access_token' => null,
                'status' => OrderStatus::Confirmed,
                'currency' => 'EUR',
                'customer_first_name' => $data['customer_first_name'],
                'customer_last_name' => $data['customer_last_name'],
                'customer_email' => null,
                'customer_phone' => $data['customer_phone'],
                // Same "office's own city/address stands in for a street
                // address" convention real checkout uses for office/locker
                // orders (see OrderService::placeOrder) — country is
                // hardcoded there too, this store being Bulgaria-only.
                'shipping_country' => 'България',
                'shipping_city' => $data['shipping_office_city'],
                'shipping_postal_code' => '',
                'shipping_address_line' => trim("{$data['shipping_office_name']}, {$data['shipping_office_address']}", ', '),
                'billing_same_as_shipping' => true,
                'shipping_carrier' => $carrier,
                'shipping_delivery_type' => $deliveryType,
                'shipping_office_id' => $data['shipping_office_id'],
                'shipping_office_name' => $data['shipping_office_name'],
                'shipping_method_label' => "{$carrier->label()} \u{2013} {$deliveryType->label()}",
                'shipping_price' => $shippingPrice,
                'cod_fee' => $codFee,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => round($subtotal + $shippingPrice + $codFee, 2),
            ]);

            $order->items()->create([
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'compare_at_price' => null,
                'line_total' => $subtotal,
                'discount_amount' => 0,
                'promotion_name' => null,
            ]);

            $order->statusHistories()->create([
                'status' => OrderStatus::Confirmed,
                'previous_status' => null,
                'changed_by_user_id' => $admin->id,
                'note' => 'Manually created by admin',
            ]);

            // Both shipping providers decide whether to tell the carrier to
            // collect cash at hand-off by checking for a Payment row with
            // payment_method = CashOnDelivery on the order (see
            // SpeedyShippingProvider::createShipment() /
            // BoxNowShippingProvider::createShipment()) — real checkout
            // creates that row via PaymentService::initiate()'s own COD
            // branch, which this manual path never goes through. Without a
            // matching row here, a manual Speedy+COD order's shipment would
            // silently go out with no cash to collect at all. Mirrors that
            // branch's own Payment/transaction shape exactly so both
            // providers' existing checks keep working unchanged.
            if ($data['payment_method'] === 'cash_on_delivery') {
                $payment = $order->payments()->create([
                    'provider' => PaymentProvider::CashOnDelivery,
                    'gateway_environment' => null,
                    'payment_method' => PaymentMethod::CashOnDelivery,
                    'status' => PaymentStatus::Pending,
                    'amount' => $order->grand_total,
                    'currency' => $order->currency,
                    'transaction_reference' => (string) Str::uuid(),
                ]);

                $payment->transactions()->create([
                    'type' => 'cash_on_delivery_created',
                    'payment_method' => PaymentMethod::CashOnDelivery,
                    'status' => PaymentStatus::Pending,
                    'raw_payload' => null,
                ]);
            }

            return $order->load(OrderService::ADMIN_EAGER_LOAD);
        });
    }
}
