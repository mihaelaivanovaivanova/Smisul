<?php

namespace Database\Seeders;

use App\Enums\PromotionType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Seeder;

/**
 * Three promotion scenarios, deliberately chosen to exercise every branch
 * of Product::activePromotions(): a currently-valid promotion scoped
 * directly to a product, one scoped to a category (so every product in
 * that category inherits it), and one that has already expired (still
 * `is_active`, but outside its date window) — proving expired promotions
 * are correctly filtered out even when attached.
 *
 * Bio Herbal Blend deliberately gets both an expired direct promotion and
 * an active category one at the same time, so activePromotions() has to
 * merge, dedupe, and filter correctly rather than just passing one
 * promotion through.
 */
class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $smisulOriginal = Product::where('slug', 'smisul-original')->first();
        $bioHerbalBlend = Product::where('slug', 'bio-bilkova-smes')->first();
        $herbsAndTeas = Category::where('slug', 'bilki-i-chaiove')->first();

        if ($smisulOriginal !== null) {
            $summerSale = Promotion::updateOrCreate(
                ['name' => 'Лятна разпродажба'],
                [
                    'description' => 'Сезонна отстъпка за оригиналната серия.',
                    'type' => PromotionType::Percentage,
                    'value' => 15,
                    'starts_at' => now()->subWeek(),
                    'ends_at' => now()->addMonth(),
                    'is_active' => true,
                ],
            );
            $summerSale->products()->syncWithoutDetaching([$smisulOriginal->id]);
        }

        if ($bioHerbalBlend !== null) {
            $springPromo = Promotion::updateOrCreate(
                ['name' => 'Пролетна промоция'],
                [
                    'description' => 'Промоция от началото на пролетта — вече изтекла.',
                    'type' => PromotionType::Percentage,
                    'value' => 10,
                    'starts_at' => now()->subMonths(2),
                    'ends_at' => now()->subWeek(),
                    'is_active' => true,
                ],
            );
            $springPromo->products()->syncWithoutDetaching([$bioHerbalBlend->id]);
        }

        if ($herbsAndTeas !== null) {
            $herbsDiscount = Promotion::updateOrCreate(
                ['name' => 'Отстъпка за билки и чайове'],
                [
                    'description' => 'Отстъпка, валидна за цялата категория билки и чайове.',
                    'type' => PromotionType::Percentage,
                    'value' => 10,
                    'starts_at' => now()->subDays(3),
                    'ends_at' => now()->addWeeks(2),
                    'is_active' => true,
                ],
            );
            $herbsDiscount->categories()->syncWithoutDetaching([$herbsAndTeas->id]);
        }
    }
}
