<?php

namespace Database\Seeders;

use App\DataTransferObjects\PriceData;
use App\DataTransferObjects\ProductVariantData;
use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\PriceService;
use App\Services\ProductVariantService;
use Illuminate\Database\Seeder;

/**
 * The MVP catalog: one product, sold as four pack-size variants. This is
 * the exact scenario the whole Product domain architecture was built to
 * support — see the Sprint 2 notes for why Product/ProductVariant are
 * split the way they are.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(
            ['slug' => 'original'],
            ['name' => 'Original', 'is_active' => true, 'sort_order' => 0],
        );

        $product = Product::firstOrCreate(
            ['slug' => 'smisul-original'],
            [
                'name' => 'Smisul Original',
                'short_description' => 'The original Smisul blend.',
                'description' => 'The original Smisul blend, available in four pack sizes.',
                'status' => ProductStatus::Published,
                'published_at' => now(),
            ],
        );

        $product->categories()->syncWithoutDetaching([$category->id]);

        $variantService = app(ProductVariantService::class);
        $priceService = app(PriceService::class);

        // Larger packs get a modest per-unit discount — realistic pricing,
        // not just N x unit price.
        $packs = [
            ['pack_size' => 1, 'sku' => 'SMISUL-1', 'unit_price' => 19.99, 'stock' => 200],
            ['pack_size' => 3, 'sku' => 'SMISUL-3', 'unit_price' => 18.99, 'stock' => 150],
            ['pack_size' => 6, 'sku' => 'SMISUL-6', 'unit_price' => 17.99, 'stock' => 100],
            ['pack_size' => 12, 'sku' => 'SMISUL-12', 'unit_price' => 16.99, 'stock' => 50],
        ];

        foreach ($packs as $pack) {
            $variant = $product->variants()->where('sku', $pack['sku'])->first();

            if ($variant === null) {
                $variant = $variantService->create($product, new ProductVariantData(
                    sku: $pack['sku'],
                    name: "{$pack['pack_size']}-pack",
                    packSize: $pack['pack_size'],
                    isDefault: $pack['pack_size'] === 1,
                ));
            }

            $amount = round($pack['unit_price'] * $pack['pack_size'], 2);

            $priceService->setPrice($variant, new PriceData(
                currency: Currency::BGN->value,
                amount: $amount,
            ));

            $variant->inventory()->update(['quantity_on_hand' => $pack['stock']]);
        }
    }
}
