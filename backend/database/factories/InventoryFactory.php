<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'quantity_on_hand' => fake()->numberBetween(0, 200),
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
            'backorders_allowed' => false,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => ['quantity_on_hand' => 0]);
    }
}
