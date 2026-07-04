<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 200);
        $shippingPrice = 5.99;
        $city = fake()->city();
        $postalCode = fake()->postcode();
        $addressLine = fake()->streetAddress();
        $carrier = fake()->randomElement(ShippingCarrier::cases());
        $deliveryType = $carrier === ShippingCarrier::BoxNow ? ShippingDeliveryType::Locker : ShippingDeliveryType::Address;

        return [
            'order_number' => 'SM-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => null,
            'guest_access_token' => (string) Str::uuid(),
            'status' => OrderStatus::Pending,
            'currency' => Currency::EUR->value,
            'customer_first_name' => fake()->firstName(),
            'customer_last_name' => fake()->lastName(),
            'customer_email' => fake()->unique()->safeEmail(),
            'customer_phone' => fake()->e164PhoneNumber(),
            'customer_company' => null,
            'customer_vat_number' => null,
            'delivery_notes' => null,
            'shipping_country' => 'Bulgaria',
            'shipping_city' => $city,
            'shipping_postal_code' => $postalCode,
            'shipping_address_line' => $addressLine,
            'shipping_apartment' => null,
            'billing_same_as_shipping' => true,
            'billing_country' => 'Bulgaria',
            'billing_city' => $city,
            'billing_postal_code' => $postalCode,
            'billing_address_line' => $addressLine,
            'billing_apartment' => null,
            'shipping_carrier' => $carrier,
            'shipping_delivery_type' => $deliveryType,
            'shipping_office_id' => $deliveryType->requiresOfficeSelection() ? 'office-1' : null,
            'shipping_office_name' => $deliveryType->requiresOfficeSelection() ? $carrier->label().' Office 1' : null,
            'shipping_method_label' => $carrier->label(),
            'shipping_price' => $shippingPrice,
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => round($subtotal + $shippingPrice, 2),
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => ($user ?? User::factory()->create())->id,
            'guest_access_token' => null,
        ]);
    }
}
