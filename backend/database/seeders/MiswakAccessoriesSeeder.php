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
                'short_description' => 'Стъргалка за език от мед - бърз и лесен начин да завършите устната си хигиена.',
                'description' => <<<'TEXT'
                Малък, но съществен детайл в ежедневната устна хигиена. Стъргалката премахва бактериалния налеп от повърхността на езика - място, което четката и Miswak не достигат пряко.

                Ползи:
                - Намалява бактериалния налеп и свежи дъха
                - Мед - традиционен материал за стъргалки за език, лесен за почистване
                - Компактна - побира се във всяка чанта или несесер

                Как да използвате:
                Поставете стъргалката в основата на езика и леко я прекарайте напред, към върха. Изплакнете с вода след всяко прекарване и повторете 3-4 пъти.

                Грижа и съхранение:
                Измивайте с топла вода и течен сапун след всяка употреба и подсушавайте добре. Съхранявайте на сухо място. С времето медта може леко да потъмнее - това е естествен процес на патиниране, а не дефект.

                Материал:
                Мед.

                Често задавани въпроси:
                В: Стъргалката заменя ли четката за зъби?
                О: Не - тя е допълнение към устната хигиена, не заместител на четкането.

                В: Подходяща ли е за ежедневна употреба?
                О: Да, препоръчваме употреба сутрин и/или вечер, като част от рутината.
                TEXT,
                'variants' => [
                    // 6.99 is the real, always-shown price — the product's own
                    // page only ever shows this. 5.99 only ever applies through
                    // the cart drawer's own upsell card (see CartService's
                    // isEligibleForUpsellPrice()) — never a real
                    // compare_at_amount sale, which would show on the
                    // product page too.
                    ['sku' => 'SCRAPER-1', 'name' => '1 бр.', 'pack_size' => 1, 'is_default' => true, 'amount' => 6.99, 'upsell_amount' => 5.99, 'stock' => 100],
                ],
                'seo' => [
                    'meta_title' => 'Стъргалка за език | Smisul',
                    'meta_description' => 'Стъргалка за език от мед - практично допълнение към устната хигиена от Smisul.',
                    'meta_keywords' => 'стъргалка за език, стъргалка от мед, устна хигиена, smisul',
                    'og_title' => 'Стъргалка за език',
                    'og_description' => 'Стъргалка за език от мед - бърз и лесен начин да завършите устната си хигиена.',
                ],
                // Real product photography, same as the bamboo case's own
                // real_images above - replaces the placeholder SVG gallery.
                'real_images' => [
                    ['file' => 'tongue-scraper-standing.png', 'alt' => 'Стъргалка за език, изглед отстрани'],
                    ['file' => 'tongue-scraper-pouch-top.png', 'alt' => 'Стъргалка за език върху торбичката, изглед отгоре'],
                    ['file' => 'tongue-scraper-pouch-angled.png', 'alt' => 'Стъргалка за език върху ленена торбичка'],
                    ['file' => 'tongue-scraper-dimensions.png', 'alt' => 'Размери на стъргалката - 12 см дължина, 4.8 см ширина'],
                    ['file' => 'tongue-scraper-in-hand.png', 'alt' => 'Стъргалка за език, държана в ръка'],
                    ['file' => 'tongue-scraper-in-use.png', 'alt' => 'Стъргалка за език в употреба'],
                ],
            ],

            [
                'slug' => 'bambukov-keis-za-miswak',
                'name' => 'Бамбуков кейс за Miswak',
                'short_description' => 'Компактен бамбуков кейс, който пази Miswak чист и сух между употребите.',
                'description' => <<<'TEXT'
                Естествен Miswak заслужава естествен начин за съхранение.

                Този бамбуков кейс е създаден специално за удобно и хигиенично носене на Miswak у дома, в чантата, в офиса или по време на път. Изработен е от MOSO бамбук – бързорастящ вид бамбук, който не е хранителен източник за пандите.

                Кейсът е с изчистен, минималистичен дизайн и е съобразен с естествената форма на Miswak, която може леко да варира по дебелина, тъй като всеки Miswak е истински корен, а не фабрично стандартизиран продукт.

                Ползи:
                - Поддържа Miswak-а сух, чист и защитен през деня.
                - Има два вентилационни отвора – един в капачето и един в корпуса, които подпомагат циркулацията на въздуха.
                - Намалява задържането на влага около влакната след употреба.
                - Подходящ е за различни дебелини Miswak, благодарение на просторния вътрешен диаметър.
                - Предпазва Miswak-а от контакт с прах, съдържанието на чантата и други повърхности.
                - Компактен и удобен за носене.
                - Елегантен, естествен дизайн, който се вписва в минималистичен начин на живот.
                - Многократна употреба – практична алтернатива на еднократните пластмасови опаковки.

                Как да се използва:
                След употреба изплакни добре влакната на Miswak-а и отстрани излишната вода. Постави го в кейса с почистващия край нагоре и затвори капачето. Вентилационните отвори позволяват на въздуха да циркулира и помагат Miswak-ът да не остава затворен в напълно влажна среда. За най-добра хигиена е препоръчително Miswak-ът да се поставя в кейса влажен, но не мокър и капещ.

                Грижа и съхранение:
                Почиствай вътрешността на кейса периодично с леко влажна кърпа или мека четка. Оставяй го да изсъхне напълно преди повторна употреба. Не го накисвай продължително във вода и не го поставяй в съдомиялна машина, тъй като бамбукът е естествен материал. Съхранявай на сухо място и избягвай продължително излагане на силна влага или директен контакт с вода.

                Материал:
                MOSO бамбук - бързорастящ вид бамбук, широко използван за устойчиви продукти за ежедневна употреба. MOSO бамбукът не е видът, който пандите обичайно консумират. Естествената структура на бамбука означава, че е възможно да има леки разлики в цвета, шарката и текстурата между отделните кейсове. Това не е дефект, а естествена характеристика на материала.

                Размери:
                Основна част: 17 см. Капаче: 3 см. Обща дължина: около 20 см. Диаметър: 3 см.

                Често задавани въпроси:
                В: Ще се побере ли всеки Miswak?
                О: Кейсът е създаден така, че да побира различни размери Miswak. Тъй като Miswak е естествен корен, диаметърът му може да варира от бройка до бройка.
                В: Защо има отвори в кейса?
                О: Има два вентилационни отвора – в капачето и в корпуса. Те подпомагат движението на въздуха и намаляват задържането на влага вътре.
                В: Мога ли да сложа Miswak-а веднага след употреба?
                О: Да, но е добре първо да го изплакнеш и леко да отстраниш излишната вода. Не е препоръчително да го поставяш в кейса, докато капе.
                В: Предпазва ли от мухъл?
                О: Вентилацията е създадена да намалява задържането на влага, което е важна част от правилното съхранение. Все пак Miswak-ът трябва да се почиства редовно и да не се държи продължително мокър.
                В: Може ли кейсът да се мие?
                О: Да, но не трябва да се накисва. Почиствай го с влажна кърпа или бързо изплакване и го оставяй да изсъхне напълно.
                В: Подходящ ли е за носене в чанта?
                О: Да. Именно това е една от основните му функции – да пази Miswak-а отделен, чист и защитен, когато си навън.
                TEXT,
                'variants' => [
                    // 6.49 is the real, always-shown price — the product's
                    // own page only ever shows this. 5.49 only ever applies
                    // through the cart drawer's bamboo-case cross-sell card
                    // (CartDrawer.tsx's CartUpsell, backed by CartService::
                    // upsellOffer()/addItem()'s server-validated eligibility
                    // check) — never a real compare_at_amount sale, which
                    // would show on the product page too.
                    ['sku' => 'MISWAK-CASE-1', 'name' => '1 бр.', 'pack_size' => 1, 'is_default' => true, 'amount' => 6.49, 'upsell_amount' => 5.49, 'stock' => 100],
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
                upsellAmount: $variantDefinition['upsell_amount'] ?? null,
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
