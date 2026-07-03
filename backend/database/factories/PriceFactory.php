<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Price;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    protected $model = Price::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'currency' => Currency::EUR->value,
            'amount' => fake()->randomFloat(2, 5, 200),
            'compare_at_amount' => null,
        ];
    }

    public function onSale(): static
    {
        return $this->state(function (array $attributes) {
            $amount = (float) $attributes['amount'];

            return ['compare_at_amount' => $amount * 1.2];
        });
    }
}
