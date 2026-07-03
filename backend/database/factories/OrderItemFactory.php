<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->randomFloat(2, 5, 50);

        return [
            'order_id' => Order::factory(),
            'product_variant_id' => null,
            'product_name' => fake()->words(3, true),
            'variant_name' => null,
            'sku' => fake()->unique()->bothify('SKU-####'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($unitPrice * $quantity, 2),
        ];
    }
}
