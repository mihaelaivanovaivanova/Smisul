<?php

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(2, true)).' Sale',
            'description' => fake()->optional()->sentence(),
            'type' => PromotionType::Percentage,
            'value' => fake()->randomElement([10, 15, 20, 25]),
            'code' => null,
            'starts_at' => null,
            'ends_at' => null,
            'usage_limit' => null,
            'used_count' => 0,
            'is_active' => true,
        ];
    }

    public function withCode(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => strtoupper(fake()->unique()->bothify('PROMO-####')),
        ]);
    }

    public function fixedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PromotionType::FixedAmount,
            'value' => fake()->randomFloat(2, 5, 30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
