<?php

namespace Database\Factories;

use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $packSize = fake()->randomElement([1, 3, 6, 12]);

        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'name' => "{$packSize}-pack",
            'pack_size' => $packSize,
            'barcode' => null,
            'is_default' => false,
            'status' => VariantStatus::Active,
            'sort_order' => 0,
        ];
    }

    public function packSize(int $packSize): static
    {
        return $this->state(fn (array $attributes) => [
            'pack_size' => $packSize,
            'name' => "{$packSize}-pack",
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => VariantStatus::Inactive]);
    }
}
