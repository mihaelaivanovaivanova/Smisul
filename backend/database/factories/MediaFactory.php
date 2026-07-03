<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $filename = fake()->unique()->slug().'.jpg';

        return [
            'mediable_type' => Product::class,
            'mediable_id' => Product::factory(),
            'disk' => 'public',
            'path' => "products/{$filename}",
            'filename' => $filename,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 500_000),
            'alt_text' => fake()->sentence(4),
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => true]);
    }
}
