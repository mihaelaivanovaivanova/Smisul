<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Seo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seo>
 */
class SeoFactory extends Factory
{
    protected $model = Seo::class;

    public function definition(): array
    {
        return [
            'seoable_type' => Product::class,
            'seoable_id' => Product::factory(),
            'meta_title' => fake()->sentence(6),
            'meta_description' => fake()->sentence(15),
            'meta_keywords' => implode(', ', fake()->words(5)),
            'og_title' => fake()->sentence(6),
            'og_description' => fake()->sentence(15),
            'og_image_path' => null,
            'canonical_url' => null,
        ];
    }
}
