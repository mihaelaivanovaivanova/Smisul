<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Three categories - enough to exercise category listing, filtering, and
 * category-scoped promotions without pretending this is the final taxonomy.
 * Products are linked to these by slug in ProductSeeder.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'original',
                'name' => 'Original',
                'description' => 'Оригиналната серия на Smisul - семпъл състав и ясен произход.',
                'sort_order' => 0,
            ],
            [
                'slug' => 'bilki-i-chaiove',
                'name' => 'Билки и чайове',
                'description' => 'Билкови смеси и чайове, създадени за спокойни моменти от деня.',
                'sort_order' => 1,
            ],
            [
                'slug' => 'yadki-semena-i-masla',
                'name' => 'Ядки, семена и масла',
                'description' => 'Семена, ядки и масла, поднесени в естествения им вид.',
                'sort_order' => 2,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                ],
            );
        }
    }
}
