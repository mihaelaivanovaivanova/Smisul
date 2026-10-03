<?php

namespace Database\Seeders;

use App\DataTransferObjects\PriceData;
use App\DataTransferObjects\ProductVariantData;
use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Models\Media;
use App\Models\Product;
use App\Services\PriceService;
use App\Services\ProductVariantService;
use App\Support\PlaceholderMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Real oral-care accessory products that pair with Miswak (seeded by
 * FunnelSeeder) - unlike ProductSeeder's catalog, these are genuine
 * storefront products, not dev-only fixture data, so this seeder runs in
 * production too (see deployment/public_html/install.php). The cart
 * drawer's bamboo-case upsell (frontend CartDrawer.tsx's UPSELL_CASE_SLUG)
 * depends on 'bambukov-keis-za-miswak' existing wherever the site runs.
 *
 * Re-running this seeder (with or without migrate:fresh first) is safe:
 * every write is an updateOrCreate keyed on a stable identifier (slug,
 * SKU, or media path), never a blind insert.
 */
class MiswakAccessoriesSeeder extends Seeder
{
    public function run(): void
    {
        $variantService = app(ProductVariantService::class);
        $priceService = app(PriceService::class);

        foreach ($this->productDefinitions() as $definition) {
            $this->seedProduct($definition, $variantService, $priceService);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productDefinitions(): array
    {
        return [
            // No category - same as Miswak itself; these are oral-care
            // accessories, not food/wellness consumables. No real
            // photography yet - seeded with the same placeholder SVG
            // convention ProductSeeder uses until real photos exist.
            [
                'slug' => 'stargalka-za-ezik',
                'name' => 'Стъргалка за език',
                'short_description' => 'Стъргалка за език от неръждаема стомана - бърз и лесен начин да завършите устната си хигиена.',
                'description' => <<<'TEXT'
                Малък, но съществен детайл в ежедневната устна хигиена. Стъргалката премахва бактериалния налеп от повърхността на езика - място, което четката и Miswak не достигат пряко.

                Ползи:
                - Намалява бактериалния налеп и свежи дъха
                - Издръжлива неръждаема стомана, лесна за почистване
                - Компактна - побира се във всяка чанта или несесер

                Как да използвате:
                Поставете стъргалката в основата на езика и леко я прекарайте напред, към върха. Изплакнете с вода след всяко прекарване и повторете 3-4 пъти.

                Грижа и съхранение:
                Измивайте с топла вода и течен сапун след всяка употреба. Съхранявайте на сухо място.

                Материал:
                Неръждаема стомана, устойчива на драскотини и корозия.

                Често задавани въпроси:
                В: Стъргалката заменя ли четката за зъби?
                О: Не - тя е допълнение към устната хигиена, не заместител на четкането.

                В: Подходяща ли е за ежедневна употреба?
                О: Да, препоръчваме употреба сутрин и/или вечер, като част от рутината.
                TEXT,
                'variants' => [
                    ['sku' => 'SCRAPER-1', 'name' => '1 бр.', 'pack_size' => 1, 'is_default' => true, 'amount' => 6.49, 'stock' => 100],
                ],
                'seo' => [
                    'meta_title' => 'Стъргалка за език | Smisul',
                    'meta_description' => 'Стъргалка за език от неръждаема стомана - практично допълнение към устната хигиена от Smisul.',
                    'meta_keywords' => 'стъргалка за език, устна хигиена, smisul',
                    'og_title' => 'Стъргалка за език',
                    'og_description' => 'Стъргалка за език от неръждаема стомана - бърз и лесен начин да завършите устната си хигиена.',
                ],
                'images' => [
                    'Стъргалка за език - опаковка на продукта',
                ],
            ],

            [
                'slug' => 'bambukov-keis-za-miswak',
                'name' => 'Бамбуков кейс за Miswak',
                'short_description' => 'Компактен бамбуков кейс, който пази Miswak чист и сух между употребите.',
                'description' => <<<'TEXT'
                Практичен калъф от бамбук, създаден да съхранява Miswak четката ви чиста, суха и готова за следващата употреба - независимо дали е вкъщи, в чантата или по пътуване.

                Ползи:
                - Естествен материал - бамбук, биоразградим и устойчив
                - Предпазва върха на Miswak между употребите
                - Компактен размер за чанта, джоб или несесер

                Как да използвате:
                Поставете Miswak четката в кейса след употреба и изплакване. Оставете капачето леко открехнато, за да изсъхне напълно, преди да затворите плътно.

                Грижа и съхранение:
                Избърсвайте кейса с влажна кърпа при нужда. Избягвайте продължително потапяне във вода.

                Материал:
                100% естествен бамбук.

                Често задавани въпроси:
                В: Кейсът пасва ли на всички разфасовки Miswak?
                О: Да, кейсът е с универсален размер и побира стандартна Miswak пръчица.

                В: Мога ли да го нося в чанта или раница?
                О: Да - компактен е и е създаден точно за това.
                TEXT,
                'variants' => [
                    // 5.99 is the "normal" price; the 4.99 sale price is what
                    // actually gets charged (CartItemResource reads the real
                    // Price row), which is also what makes the cart drawer's
                    // bamboo-case upsell card (CartDrawer.tsx's CartUpsell)
                    // honestly show 4.99 struck-through-5.99 instead of a
                    // cosmetic-only discount that doesn't match checkout.
                    ['sku' => 'MISWAK-CASE-1', 'name' => '1 бр.', 'pack_size' => 1, 'is_default' => true, 'amount' => 4.99, 'compare_at_amount' => 5.99, 'stock' => 100],
                ],
                'seo' => [
                    'meta_title' => 'Бамбуков кейс за Miswak | Smisul',
                    'meta_description' => 'Бамбуков кейс за Miswak - пази четката чиста и суха между употребите.',
                    'meta_keywords' => 'кейс за miswak, калъф за miswak, бамбуков калъф, smisul',
                    'og_title' => 'Бамбуков кейс за Miswak',
                    'og_description' => 'Компактен бамбуков кейс, който пази Miswak чист и сух между употребите.',
                ],
                // Real product photography (database/seeders/assets/products/),
                // not the PlaceholderMedia SVG the scraper above uses.
                'real_images' => [
                    ['file' => 'miswak-case-with-box.png', 'alt' => 'Бамбуков кейс за Miswak с опаковъчна кутия'],
                    ['file' => 'miswak-case-open-with-stick.png', 'alt' => 'Отворен бамбуков кейс с Miswak пръчица отгоре'],
                    ['file' => 'miswak-case-flatlay.png', 'alt' => 'Бамбуков кейс, капаче и Miswak пръчица, изглед отгоре'],
                    ['file' => 'miswak-case-angled-with-stick.png', 'alt' => 'Отворен бамбуков кейс с Miswak пръчица до него'],
                    ['file' => 'miswak-case-dimensions.png', 'alt' => 'Размери на бамбуковия кейс - 20 см дължина, 3 см диаметър'],
                    ['file' => 'miswak-case-open-angled.png', 'alt' => 'Отворен бамбуков кейс с отделено капаче'],
                    ['file' => 'miswak-case-closeup-open.png', 'alt' => 'Близък план на отворения край на бамбуковия кейс и капачето'],
                    ['file' => 'miswak-case-in-hand.png', 'alt' => 'Бамбуков кейс за Miswak, държан в ръка'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedProduct(array $definition, ProductVariantService $variantService, PriceService $priceService): void
    {
        $product = Product::updateOrCreate(
            ['slug' => $definition['slug']],
            [
                'name' => $definition['name'],
                'short_description' => $definition['short_description'],
                'description' => $definition['description'],
                'status' => ProductStatus::Published,
                'published_at' => now(),
            ],
        );

        foreach ($definition['variants'] as $sortOrder => $variantDefinition) {
            $variant = $product->variants()->where('sku', $variantDefinition['sku'])->first();

            if ($variant === null) {
                $variant = $variantService->create($product, new ProductVariantData(
                    sku: $variantDefinition['sku'],
                    name: $variantDefinition['name'],
                    packSize: $variantDefinition['pack_size'],
                    isDefault: $variantDefinition['is_default'] ?? false,
                    sortOrder: $sortOrder,
                ));
            }

            $priceService->setPrice($variant, new PriceData(
                currency: Currency::EUR->value,
                amount: $variantDefinition['amount'],
                compareAtAmount: $variantDefinition['compare_at_amount'] ?? null,
            ));

            $variant->inventory()->update([
                'quantity_on_hand' => $variantDefinition['stock'],
                'backorders_allowed' => $variantDefinition['backorders_allowed'] ?? false,
            ]);
        }

        if (isset($definition['seo'])) {
            $product->seo()->updateOrCreate([], $definition['seo']);
        }

        if (isset($definition['real_images'])) {
            foreach ($definition['real_images'] as $index => $image) {
                $this->seedRealImage($product, $image['file'], $image['alt'], $index, $index === 0);
            }
        } else {
            foreach ($definition['images'] as $index => $altText) {
                $this->seedImage($product, $definition, $index, $altText);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedImage(Product $product, array $definition, int $index, string $altText): void
    {
        $filename = "{$definition['slug']}-{$index}.svg";
        $path = "products/{$filename}";

        Storage::disk('public')->put($path, PlaceholderMedia::productImageSvg($definition['name']));

        Media::updateOrCreate(
            ['mediable_type' => Product::class, 'mediable_id' => $product->id, 'path' => $path],
            [
                'disk' => 'public',
                'filename' => $filename,
                'mime_type' => 'image/svg+xml',
                'size' => Storage::disk('public')->size($path),
                'alt_text' => $altText,
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ],
        );
    }

    /**
     * Real product photography, copied from database/seeders/assets/products/
     * - the real-image counterpart to seedImage() above (which only ever
     * generates a PlaceholderMedia SVG). Keyed on the real filename so
     * updateOrCreate stays idempotent across reseeds.
     */
    private function seedRealImage(Product $product, string $sourceFilename, string $altText, int $sortOrder, bool $isPrimary): void
    {
        $sourcePath = __DIR__."/assets/products/{$sourceFilename}";
        $contents = file_get_contents($sourcePath);
        $path = "products/{$sourceFilename}";

        Storage::disk('public')->put($path, $contents);

        Media::updateOrCreate(
            ['mediable_type' => Product::class, 'mediable_id' => $product->id, 'path' => $path],
            [
                'disk' => 'public',
                'filename' => $sourceFilename,
                'mime_type' => $this->imageMimeTypeFor($sourceFilename),
                'size' => Storage::disk('public')->size($path),
                'alt_text' => $altText,
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
            ],
        );
    }

    private function imageMimeTypeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
