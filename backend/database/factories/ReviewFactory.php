<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => ReviewStatus::Approved,
            'verified_purchase' => true,
            'helpful_count' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Pending]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Rejected]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Hidden]);
    }
}
