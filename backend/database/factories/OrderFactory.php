<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\ShippingCarrier;
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
            'shipping_city' => fake()->city(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_address_line' => fake()->streetAddress(),
            'shipping_apartment' => null,
            'shipping_carrier' => fake()->randomElement(ShippingCarrier::cases()),
            'shipping_method_label' => 'Econt',
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
