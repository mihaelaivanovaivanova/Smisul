<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\PriceHistory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceHistory>
 */
class PriceHistoryFactory extends Factory
{
    protected $model = PriceHistory::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'currency' => Currency::EUR->value,
            'old_amount' => fake()->randomFloat(2, 5, 200),
            'new_amount' => fake()->randomFloat(2, 5, 200),
            'changed_by' => null,
            'reason' => null,
        ];
    }
}
