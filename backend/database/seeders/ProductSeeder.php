<?php

namespace Database\Seeders;

use App\DataTransferObjects\PriceData;
use App\DataTransferObjects\ProductVariantData;
use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Services\PriceService;
use App\Services\ProductVariantService;
use App\Support\PlaceholderMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The development catalog: one real MVP product (Smisul Original) plus six
 * additional Bulgarian placeholder products, covering every visual/business
 * scenario the storefront, cart, and (future) admin/checkout need to be
 * tested against - see the class-level scenario notes on each product
 * definition below. Deliberately data-driven (one seedProduct() loop, not
 * seven hand-copied blocks) so adding product #8, #80, or #800 later is the
 * same amount of code.
 *
 * Re-running this seeder (with or without migrate:fresh first) is safe:
 * every write is an updateOrCreate/sync keyed on a stable identifier
 * (slug, SKU, or the owning relation), never a blind insert.
 */
class ProductSeeder extends Seeder
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
            // Scenario coverage: active direct promotion, sale price on one
            // variant, many images, has a PDF, has a video, healthy stock.
            [
                'slug' => 'smisul-original',
                'name' => 'Smisul Original',
                'category_slug' => 'original',
                'short_description' => 'Оригиналната смес на Smisul - чиста, семпла и направена с грижа.',
                'description' => <<<'TEXT'
                Създадена от внимателно подбрани съставки, оригиналната смес на Smisul е за хората, които ценят простотата и прозрачността. Без излишни добавки, без сложни списъци - само това, което наистина има значение.

                Ползи:
                - Семпъл, разбираем състав
                - Удобен за включване в ежедневието
                - Балансиран вкус, подходящ за всеки ден

                Как да използвате:
                Добавете към сутрешния си ритуал или използвайте по начина, който пасва на вашия ден. Няма строго правило - само последователност.

                Съхранение:
                Съхранявайте на сухо и хладно място, далеч от пряка слънчева светлина. След отваряне затваряйте опаковката плътно.

                Съставки:
                Естествени съставки с ясен произход (пълен списък предстои да бъде добавен).

                Често задавани въпроси:
                В: Каква разфасовка да избера?
                О: Ако за първи път опитвате продукта, започнете от най-малката опаковка (1 бр.) и вижте какво темпо ви пасва.

                В: Колко време се съхранява след отваряне?
                О: Препоръчваме консумация в разумен срок след отваряне, при спазване на условията за съхранение.
                TEXT,
                'variants' => [
                    ['sku' => 'SMISUL-1', 'name' => '1 бр.', 'pack_size' => 1, 'is_default' => true, 'amount' => 16.99, 'compare_at_amount' => 19.99, 'stock' => 200],
                    ['sku' => 'SMISUL-3', 'name' => '3 бр.', 'pack_size' => 3, 'amount' => 56.97, 'stock' => 150],
                    ['sku' => 'SMISUL-6', 'name' => '6 бр.', 'pack_size' => 6, 'amount' => 107.94, 'stock' => 100],
                    ['sku' => 'SMISUL-12', 'name' => '12 бр.', 'pack_size' => 12, 'amount' => 203.88, 'stock' => 50],
                ],
                'seo' => [
                    'meta_title' => 'Smisul Original - оригиналната смес | Smisul',
                    'meta_description' => 'Открийте Smisul Original - семпъл състав, ясен произход и грижа във всяка опаковка. Налична в четири разфасовки.',
                    'meta_keywords' => 'smisul original, естествена смес, семпъл състав, българско производство',
                    'og_title' => 'Smisul Original',
                    'og_description' => 'Оригиналната смес на Smisul - чиста, семпла и направена с грижа.',
                ],
                'images' => [
                    'Smisul Original - опаковка на продукта',
                    'Smisul Original - едър план на съставките',
                    'Smisul Original - четирите разфасовки заедно',
                    'Smisul Original - начин на употреба',
                ],
                'pdf' => [
                    'title' => 'Smisul Original - Product Information Sheet',
                    'lines' => [
                        '(Placeholder document - Latin text only, see README)',
                        'Product: Smisul Original',
                        'Available sizes: 1, 3, 6, and 12 pieces',
                        'Storage: keep in a cool, dry place away from direct sunlight',
                        'Replace this file with real product documentation.',
                    ],
                ],
                'video' => true,
            ],

            // Scenario coverage: on-sale variant, one low-stock variant, an
            // expired direct promotion masked by an active category one.
            [
                'slug' => 'bio-bilkova-smes',
                'name' => 'Био билкова смес',
                'category_slug' => 'bilki-i-chaiove',
                'short_description' => 'Внимателно подбрана билкова смес за спокоен момент от деня.',
                'description' => <<<'TEXT'
                Съчетание от билки, избрани заради естествената им форма и минималната обработка. Създадена за хората, които търсят прост начин да си отделят момент за себе си.

                Ползи:
                - Приятен, балансиран аромат
                - Лесна за включване в ежедневния ритуал
                - Опакована на малки партиди

                Как да използвате:
                Прилагайте според собствените си предпочитания - като част от следобедния или вечерния момент за пауза.

                Съхранение:
                Дръжте опаковката плътно затворена, на сухо място, далеч от директна светлина.

                Съставки:
                Билкова смес с естествен произход (пълен списък съставки предстои).

                Често задавани въпроси:
                В: За кого е подходяща тази смес?
                О: За всеки, който търси естествен продукт с прост, разбираем състав.

                В: Има ли разлика между разфасовките?
                О: Разликата е само в количеството - съставът е един и същ.
                TEXT,
                'variants' => [
                    ['sku' => 'BIOHERB-50', 'name' => '50 г', 'pack_size' => 50, 'is_default' => true, 'amount' => 9.49, 'compare_at_amount' => 11.99, 'stock' => 4],
                    ['sku' => 'BIOHERB-100', 'name' => '100 г', 'pack_size' => 100, 'amount' => 16.99, 'stock' => 60],
                ],
                'seo' => [
                    'meta_title' => 'Био билкова смес - естествен избор | Smisul',
                    'meta_description' => 'Разгледайте билковата смес на Smisul - естествен състав, семпло опаковане и грижа в детайла.',
                    'meta_keywords' => 'билкова смес, био билки, естествен продукт, smisul',
                    'og_title' => 'Био билкова смес',
                    'og_description' => 'Внимателно подбрана билкова смес за спокоен момент от деня.',
                ],
                'images' => [
                    'Био билкова смес - опаковка на продукта',
                ],
            ],

            // Scenario coverage: no promotion at all, no images, one
            // out-of-stock variant (no backorders).
            [
                'slug' => 'bio-miks-ot-semena',
                'name' => 'Био микс от семена',
                'category_slug' => 'yadki-semena-i-masla',
                'short_description' => 'Микс от семена, подбрани заради естествената им хранителна стойност.',
                'description' => <<<'TEXT'
                Комбинация от семена, поднесени в естествената им форма, без излишна преработка. За хората, които искат прост, честен продукт в кухнята си.

                Ползи:
                - Разнообразие от семена в едно опаковане
                - Лесно добавяне към ежедневни ястия
                - Прозрачен, семпъл състав

                Как да използвате:
                Добавете към салата, кисело мляко или каша - начинът е изцяло на ваш избор.

                Съхранение:
                Съхранявайте на хладно и сухо място. След отваряне затваряйте опаковката плътно, за да запазите свежестта.

                Съставки:
                Микс от семена с ясен произход (пълен списък предстои).

                Често задавани въпроси:
                В: Може ли да се комбинира с други продукти на Smisul?
                О: Да, продуктите са създадени да се допълват лесно един друг.

                В: Каква е разликата между разфасовките?
                О: Само в количеството - съставът остава непроменен.
                TEXT,
                'variants' => [
                    ['sku' => 'SEEDMIX-200', 'name' => '200 г', 'pack_size' => 200, 'is_default' => true, 'amount' => 7.99, 'stock' => 0],
                    ['sku' => 'SEEDMIX-500', 'name' => '500 г', 'pack_size' => 500, 'amount' => 17.99, 'stock' => 80],
                ],
                'seo' => [
                    'meta_title' => 'Био микс от семена | Smisul',
                    'meta_description' => 'Открийте микса от семена на Smisul - естествен състав и лесно добавяне към ежедневието.',
                    'meta_keywords' => 'семена, био микс, естествени продукти, smisul',
                    'og_title' => 'Био микс от семена',
                    'og_description' => 'Микс от семена, подбрани заради естествената им хранителна стойност.',
                ],
                'images' => [],
            ],

            // Scenario coverage: a backorder-enabled variant (purchasable
            // at zero on-hand stock) alongside a normally-stocked one.
            [
                'slug' => 'studeno-presovano-maslo',
                'name' => 'Студено пресовано масло',
                'category_slug' => 'yadki-semena-i-masla',
                'short_description' => 'Масло, извлечено чрез студено пресоване, за да запази естествения си характер.',
                'description' => <<<'TEXT'
                Студеното пресоване запазва повече от естествения характер на суровината, без излишна термична обработка. Семпъл продукт, направен с внимание към процеса.

                Ползи:
                - Запазена естествена структура благодарение на студеното пресоване
                - Универсално приложение в кухнята
                - Ясен, семпъл произход

                Как да използвате:
                Подходящо за студени рецепти и довършителни щрихи в готвенето. Съхранявайте далеч от директна топлина.

                Съхранение:
                Съхранявайте на тъмно и хладно място. Затваряйте капачката плътно след всяка употреба.

                Съставки:
                100% масло, студено пресовано (пълна информация за произхода предстои).

                Често задавани въпроси:
                В: Защо студено пресоване?
                О: Защото минимизира термичната обработка на суровината.

                В: Каква е разликата между разфасовките?
                О: Само в обема - процесът на производство е идентичен.
                TEXT,
                'variants' => [
                    ['sku' => 'OIL-250', 'name' => '250 мл', 'pack_size' => 250, 'is_default' => true, 'amount' => 12.99, 'stock' => 40],
                    ['sku' => 'OIL-500', 'name' => '500 мл', 'pack_size' => 500, 'amount' => 22.99, 'stock' => 0, 'backorders_allowed' => true],
                ],
                'seo' => [
                    'meta_title' => 'Студено пресовано масло | Smisul',
                    'meta_description' => 'Открийте студено пресованото масло на Smisul - семпло, естествено и с ясен произход.',
                    'meta_keywords' => 'студено пресовано масло, натурално масло, smisul',
                    'og_title' => 'Студено пресовано масло',
                    'og_description' => 'Масло, извлечено чрез студено пресоване, за да запази естествения си характер.',
                ],
                'images' => [
                    'Студено пресовано масло - опаковка на продукта',
                ],
            ],

            // Scenario coverage: single-variant product (the variant picker
            // hides itself entirely for this one).
            [
                'slug' => 'naturalni-energiyni-hapki',
                'name' => 'Натурални енергийни хапки',
                'category_slug' => 'yadki-semena-i-masla',
                'short_description' => 'Малки хапки за момент на пауза през активния ви ден.',
                'description' => <<<'TEXT'
                Компактни хапки, създадени за хората в движение, които все пак държат на семплия състав. Практично решение за кратка пауза през деня.

                Ползи:
                - Удобни за носене навсякъде
                - Практична порционна опаковка
                - Семпъл състав без излишни добавки

                Как да използвате:
                Идеални за кратка пауза между задачите - просто отворете и се насладете.

                Съхранение:
                Съхранявайте на сухо място, далеч от директна топлина и слънчева светлина.

                Съставки:
                Натурални съставки (пълен списък предстои да бъде публикуван).

                Често задавани въпроси:
                В: Колко хапки има в опаковката?
                О: Точното количество е посочено на етикета на опаковката.

                В: Продуктът предлага ли се в различни разфасовки?
                О: В момента предлагаме една стандартна разфасовка.
                TEXT,
                'variants' => [
                    ['sku' => 'ENERGY-150', 'name' => '150 г', 'pack_size' => 150, 'is_default' => true, 'amount' => 8.49, 'stock' => 90],
                ],
                'seo' => [
                    'meta_title' => 'Натурални енергийни хапки | Smisul',
                    'meta_description' => 'Практични енергийни хапки от Smisul - семпъл състав, удобни за всеки ден.',
                    'meta_keywords' => 'енергийни хапки, натурална закуска, smisul',
                    'og_title' => 'Натурални енергийни хапки',
                    'og_description' => 'Малки хапки за момент на пауза през активния ви ден.',
                ],
                'images' => [
                    'Натурални енергийни хапки - опаковка на продукта',
                ],
            ],

            // Scenario coverage: no direct promotion, but an active
            // category-scoped one is inherited (see PromotionSeeder).
            [
                'slug' => 'uelnes-chay',
                'name' => 'Уелнес чай',
                'category_slug' => 'bilki-i-chaiove',
                'short_description' => 'Чаена смес за спокоен момент от деня.',
                'description' => <<<'TEXT'
                Внимателно съставена чаена смес, подходяща за момент на пауза и спокойствие. Семпъл продукт, създаден с грижа към всяка съставка.

                Ползи:
                - Приятен, балансиран вкус
                - Лесен за приготвяне
                - Опакован на малки партиди

                Как да използвате:
                Залейте едно пакетче с гореща вода и оставете за няколко минути, според предпочитания от вас вкус.

                Съхранение:
                Съхранявайте на сухо място, далеч от влага и директна светлина.

                Съставки:
                Чаена билкова смес (пълен списък съставки предстои).

                Често задавани въпроси:
                В: Колко пакетчета има във всяка опаковка?
                О: Предлагаме опаковки от 20 и 40 пакетчета.

                В: Може ли да се пие студен?
                О: Да, чаят може да се остави да изстине и да се консумира студен.
                TEXT,
                'variants' => [
                    ['sku' => 'TEA-20', 'name' => '20 пакетчета', 'pack_size' => 20, 'is_default' => true, 'amount' => 6.99, 'stock' => 70],
                    ['sku' => 'TEA-40', 'name' => '40 пакетчета', 'pack_size' => 40, 'amount' => 12.49, 'stock' => 45],
                ],
                'seo' => [
                    'meta_title' => 'Уелнес чай | Smisul',
                    'meta_description' => 'Чаената смес на Smisul - семпъл състав и спокоен момент от деня.',
                    'meta_keywords' => 'уелнес чай, билков чай, smisul',
                    'og_title' => 'Уелнес чай',
                    'og_description' => 'Чаена смес за спокоен момент от деня.',
                ],
                'images' => [
                    'Уелнес чай - опаковка на продукта',
                    'Уелнес чай - пакетче отблизо',
                ],
            ],

            // Scenario coverage: three variants, one of them low-stock.
            [
                'slug' => 'premium-miks-ot-yadki',
                'name' => 'Премиум микс от ядки',
                'category_slug' => 'yadki-semena-i-masla',
                'short_description' => 'Селектиран микс от ядки, поднесени в естествения им вид.',
                'description' => <<<'TEXT'
                Комбинация от ядки, подбрани заради качеството и естествения си характер. Продукт за хората, които предпочитат семпли, честни съставки.

                Ползи:
                - Разнообразие от ядки в едно опаковане
                - Удобна порционна употреба
                - Ясен, прозрачен състав

                Как да използвате:
                Подходящ за директна консумация или като добавка към любимите ви рецепти.

                Съхранение:
                Съхранявайте на сухо и хладно място, в плътно затворена опаковка.

                Съставки:
                Микс от ядки (пълен списък съставки предстои).

                Често задавани въпроси:
                В: Съдържа ли миксът алергени?
                О: Информацията за алергени е посочена подробно на етикета на опаковката.

                В: Каква е разликата между разфасовките?
                О: Само в количеството - съставът остава непроменен.
                TEXT,
                'variants' => [
                    ['sku' => 'NUTS-150', 'name' => '150 г', 'pack_size' => 150, 'is_default' => true, 'amount' => 6.49, 'stock' => 3],
                    ['sku' => 'NUTS-300', 'name' => '300 г', 'pack_size' => 300, 'amount' => 11.99, 'stock' => 55],
                    ['sku' => 'NUTS-500', 'name' => '500 г', 'pack_size' => 500, 'amount' => 18.99, 'stock' => 30],
                ],
                'seo' => [
                    'meta_title' => 'Премиум микс от ядки | Smisul',
                    'meta_description' => 'Открийте премиум микса от ядки на Smisul - селектирани съставки и семпло опаковане.',
                    'meta_keywords' => 'микс от ядки, премиум ядки, smisul',
                    'og_title' => 'Премиум микс от ядки',
                    'og_description' => 'Селектиран микс от ядки, поднесени в естествения им вид.',
                ],
                'images' => [
                    'Премиум микс от ядки - опаковка на продукта',
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

        $category = Category::where('slug', $definition['category_slug'])->first();

        if ($category !== null) {
            $product->categories()->syncWithoutDetaching([$category->id]);
        }

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

        foreach ($definition['images'] as $index => $altText) {
            $this->seedImage($product, $definition, $index, $altText);
        }

        if (isset($definition['pdf'])) {
            $this->seedPdf($product, $definition);
        }

        if ($definition['video'] ?? false) {
            $this->seedVideo($product, $definition);
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
     * @param  array<string, mixed>  $definition
     */
    private function seedPdf(Product $product, array $definition): void
    {
        $filename = "{$definition['slug']}-info-sheet.pdf";
        $path = "documents/{$filename}";
        $content = PlaceholderMedia::minimalPdf($definition['pdf']['title'], $definition['pdf']['lines']);

        Storage::disk('public')->put($path, $content);

        Media::updateOrCreate(
            ['mediable_type' => Product::class, 'mediable_id' => $product->id, 'path' => $path],
            [
                'disk' => 'public',
                'filename' => $filename,
                'mime_type' => 'application/pdf',
                'size' => strlen($content),
                'alt_text' => "{$definition['name']} - информационен лист (PDF)",
                'sort_order' => 90,
                'is_primary' => false,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedVideo(Product $product, array $definition): void
    {
        $filename = "{$definition['slug']}-demo.mp4";
        $path = "videos/{$filename}";
        $content = PlaceholderMedia::videoPlaceholderText($definition['name']);

        Storage::disk('public')->put($path, $content);

        Media::updateOrCreate(
            ['mediable_type' => Product::class, 'mediable_id' => $product->id, 'path' => $path],
            [
                'disk' => 'public',
                'filename' => $filename,
                'mime_type' => 'video/mp4',
                'size' => strlen($content),
                'alt_text' => "{$definition['name']} - демо видео",
                'sort_order' => 95,
                'is_primary' => false,
            ],
        );
    }
}
