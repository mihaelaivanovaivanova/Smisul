<?php

namespace Database\Seeders;

use App\DataTransferObjects\PriceData;
use App\DataTransferObjects\ProductVariantData;
use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Models\ContentBlock;
use App\Models\FunnelConfig;
use App\Models\Media;
use App\Models\Product;
use App\Services\PriceService;
use App\Services\ProductVariantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the "funnel mode" feature: a real Miswak product with 3 real
 * variants (so the funnel landing page's add-to-cart is fully wired into
 * the real cart/checkout pipeline, not a mock), its 3 package-card
 * overrides, and the funnel page's 12 editable copy sections — ported
 * verbatim from the D:\Projects\miswak-website prototype. Funnel mode
 * itself is seeded OFF; an admin turns it on from /admin/funnel.
 *
 * Idempotent throughout (updateOrCreate/firstOrCreate), matching every
 * other seeder in this codebase.
 */
class FunnelSeeder extends Seeder
{
    public function run(): void
    {
        $product = $this->seedProduct(app(ProductVariantService::class), app(PriceService::class));

        $this->seedConfig($product);
        $this->seedContent();
    }

    private function seedProduct(ProductVariantService $variantService, PriceService $priceService): Product
    {
        $product = Product::updateOrCreate(
            ['slug' => 'miswak'],
            [
                'name' => 'Miswak',
                'short_description' => 'Естествена четка за зъби от Salvadora persica — без паста, без вода, без пластмаса.',
                'description' => <<<'TEXT'
                Miswak е 100% натурална четка за зъби, направена от корена на Salvadora persica — растение, използвано от векове за ежедневна устна хигиена.

                Без пластмасова дръжка, без паста и без нужда от вода. Просто обели върха, сдъвчи леко, докато влакната се разделят като естествена четчица, и почисти.

                Съставки:
                Естествени растителни влакна от Salvadora persica, съдържащи силика, танини, сапонини и минерални компоненти.

                Как да използвате:
                Обели около 1 см от единия край, сдъвчи внимателно, докато влакната се разделят, и използвай с нежни движения върху зъбите и венците за 2-3 минути.

                Съхранение:
                Съхранявайте на сухо и чисто място. Когато върхът се износи, изрежете го и подгответе нов.
                TEXT,
                'status' => ProductStatus::Published,
                'published_at' => now(),
            ],
        );

        foreach ($this->variantDefinitions() as $sortOrder => $definition) {
            $variant = $product->variants()->where('sku', $definition['sku'])->first();

            if ($variant === null) {
                $variant = $variantService->create($product, new ProductVariantData(
                    sku: $definition['sku'],
                    name: $definition['name'],
                    packSize: $definition['pack_size'],
                    isDefault: $definition['is_default'] ?? false,
                    sortOrder: $sortOrder,
                ));
            }

            $priceService->setPrice($variant, new PriceData(
                currency: Currency::EUR->value,
                amount: $definition['amount'],
                compareAtAmount: $definition['compare_at_amount'] ?? null,
            ));

            $variant->inventory()->update(['quantity_on_hand' => $definition['stock']]);
        }

        $product->seo()->updateOrCreate([], [
            'meta_title' => 'Miswak — натурална четка за зъби | Smisul',
            'meta_description' => 'Открий Miswak — 100% натурална четка за зъби от Salvadora persica. Без паста, без вода, без пластмаса.',
            'meta_keywords' => 'miswak, натурална четка за зъби, salvadora persica, smisul',
            'og_title' => 'Miswak — избирай с мисъл',
            'og_description' => 'Натурална четка за зъби директно от природата.',
        ]);

        $this->seedImage($product, 'hero-miswak-hand.webp', 'products/miswak-hero-hand.webp', 'Ръка, която държи Miswak', 0, true);
        $this->seedImage($product, 'miswak-closeup.jpg', 'products/miswak-closeup.jpg', 'Близък план на подготвен връх на Miswak', 1, false);
        $this->seedImage($product, 'miswak-bundle.webp', 'products/miswak-bundle.webp', 'Miswak натурални пръчици', 2, false);

        return $product;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function variantDefinitions(): array
    {
        return [
            ['sku' => 'MISWAK-3', 'name' => '3 бр.', 'pack_size' => 3, 'is_default' => true, 'amount' => 8.99, 'compare_at_amount' => 10.47, 'stock' => 200],
            ['sku' => 'MISWAK-5', 'name' => '5 бр.', 'pack_size' => 5, 'amount' => 13.96, 'compare_at_amount' => 17.45, 'stock' => 200],
            ['sku' => 'MISWAK-10', 'name' => '10 бр.', 'pack_size' => 10, 'amount' => 27.92, 'compare_at_amount' => 34.9, 'stock' => 200],
        ];
    }

    private function seedImage(Product $product, string $sourceFilename, string $path, string $altText, int $sortOrder, bool $isPrimary): void
    {
        $sourcePath = __DIR__."/assets/funnel/{$sourceFilename}";
        $contents = file_get_contents($sourcePath);

        Storage::disk('public')->put($path, $contents);

        Media::updateOrCreate(
            ['mediable_type' => Product::class, 'mediable_id' => $product->id, 'path' => $path],
            [
                'disk' => 'public',
                'filename' => basename($path),
                'mime_type' => str_ends_with($path, '.jpg') ? 'image/jpeg' : 'image/webp',
                'size' => Storage::disk('public')->size($path),
                'alt_text' => $altText,
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
            ],
        );
    }

    private function seedConfig(Product $product): void
    {
        $variantIds = $product->variants()->pluck('id', 'sku');

        $config = FunnelConfig::current();
        $config->update([
            'product_id' => $product->id,
            'packages' => [
                [
                    'variant_id' => $variantIds['MISWAK-3'],
                    'badge' => 'Стартов пакет',
                    'detail' => '3 броя',
                    'value_label' => 'За дом, работа и път',
                    'button_text' => 'Вземи стартов пакет',
                ],
                [
                    'variant_id' => $variantIds['MISWAK-5'],
                    'badge' => 'Най-популярен',
                    'detail' => '5 броя',
                    'value_label' => 'Плащаш 4, получаваш 5',
                    'button_text' => 'Вземи семеен пакет',
                ],
                [
                    'variant_id' => $variantIds['MISWAK-10'],
                    'badge' => 'Най-добра стойност',
                    'detail' => '10 броя',
                    'value_label' => 'Плащаш 8, получаваш 10',
                    'button_text' => 'Вземи супер семеен пакет',
                ],
            ],
        ]);
    }

    private function seedContent(): void
    {
        $blocks = [
            'funnel.hero' => [
                'badge' => 'С | МИСЪЛ · избирай с мисъл',
                'title' => 'Смени пастата и пластмасата с четка директно от природата',
                'body' => 'Miswak е 100% натурална четка за зъби от Salvadora persica - растение, използвано от векове за ежедневна устна хигиена. Само естествени влакна, свежо усещане и чиста грижа, която разбираш.',
                'highlight' => 'Без паста. Без вода. Без пластмаса.',
                'cta_primary' => 'Поръчай Miswak',
                'cta_secondary' => 'Виж как работи',
                'bullets' => [
                    '100% натурален продукт',
                    'Растителни влакна от Salvadora persica',
                    'Подходящ за дома, офиса и път',
                    'Еко алтернатива без пластмасова дръжка',
                ],
            ],
            'funnel.dark_band' => [
                'eyebrow' => 'По-прост избор',
                'title' => 'Има по-прост начин да започнеш деня си',
                'paragraphs' => [
                    'Всеки ден слагаш паста в устата си и сменяш пластмасови четки отново и отново.',
                    'Miswak връща устната грижа към нещо по-чисто, по-просто и по-близко до природата.',
                ],
                'highlight' => 'Една клечка. Естествени влакна. Свежо усещане.',
            ],
            'funnel.problem' => [
                'eyebrow' => 'Замислял ли си се?',
                'title' => 'Какво използваш всеки ден?',
                'body' => 'Повечето хора започват деня си с паста, пълна с дълъг списък от съставки, и четка от пластмаса, която след няколко седмици отива в коша. Това не е поредният модерен продукт. Това е древен натурален ритуал, който се връща, защото е прост, чист и практичен.',
                'emphasis' => 'Miswak е различен.',
                'bullets' => [
                    'Без пластмасова дръжка',
                    'Без паста',
                    'Без нужда от вода',
                    'Без излишна сложност',
                    'Удобен навсякъде',
                ],
                'cta' => 'Опитай натуралната алтернатива',
            ],
            'funnel.benefits' => [
                'eyebrow' => 'Защо Miswak?',
                'title' => 'Защо Miswak е толкова различен?',
                'cards' => [
                    ['title' => 'Без паста', 'text' => 'Miswak се използва директно. Подготвяш върха, влакната се разтварят като естествена четчица и започваш почистването.', 'emphasis' => 'Без пяна. Без мивка. Без излишни съставки.'],
                    ['title' => 'Без пластмасова четка', 'text' => 'Няма пластмасова дръжка. Няма синтетична глава. Няма четка, която сменяш всеки месец и хвърляш.', 'emphasis' => 'По-малко пластмаса в ежедневието ти.'],
                    ['title' => 'Директно от природата', 'text' => 'Miswak идва от Salvadora persica, растение, познато още като toothbrush tree.', 'emphasis' => 'Върхът се превръща в естествена четчица.'],
                    ['title' => 'Свеж дъх навсякъде', 'text' => 'След кафе. След храна. В офиса. В колата. На път.', 'emphasis' => 'Малък, лек и винаги под ръка.'],
                ],
            ],
            'funnel.ingredients' => [
                'eyebrow' => 'Естествен състав',
                'title' => 'Какво съдържа Miswak?',
                'cards' => [
                    ['title' => 'Силика', 'text' => 'Подпомага механичното почистване на зъбната повърхност и премахването на натрупвания при правилна употреба.'],
                    ['title' => 'Танини', 'text' => 'Растителни съединения с естествен стягащ ефект, които подпомагат усещането за чисти венци.'],
                    ['title' => 'Сапонини', 'text' => 'Естествени почистващи съединения, които се срещат в растенията и допринасят за свежото усещане при употреба.'],
                    ['title' => 'Минерални компоненти', 'text' => 'Salvadora persica естествено съдържа минерални компоненти, свързани с ежедневната грижа за зъбите.'],
                    ['title' => 'Растителни съединения', 'text' => 'Miswak съдържа активни растителни компоненти, изследвани за ролята им в устната хигиена.'],
                ],
                'closing_line' => 'Затова Miswak не е просто клечка. Той е естествен инструмент за ежедневна устна грижа - с влакна, минерали и растителни съединения, създадени от природата.',
            ],
            'funnel.ritual' => [
                'eyebrow' => 'Чиста грижа',
                'title' => 'Чиста грижа, която разбираш',
                'lines' => [
                    'Не ти трябва паста с дълъг етикет.',
                    'Не ти трябва пластмасова четка.',
                    'Не ти трябва вода.',
                    'Трябва ти само Miswak.',
                ],
                'cta' => 'Вземи своя Miswak',
                'steps' => [
                    ['title' => 'Стъпка 1', 'text' => 'Обели'],
                    ['title' => 'Стъпка 2', 'text' => 'Сдъвчи леко'],
                    ['title' => 'Стъпка 3', 'text' => 'Почисти'],
                    ['title' => 'Стъпка 4', 'text' => 'Освежи'],
                ],
            ],
            'funnel.how_to' => [
                'eyebrow' => 'Как се използва?',
                'title' => 'Четири лесни движения',
                'steps' => [
                    ['title' => 'Обели върха', 'text' => 'Обели около 1 см от единия край.'],
                    ['title' => 'Сдъвчи леко', 'text' => 'Сдъвчи върха внимателно, докато влакната се разделят и стане като естествена четчица.'],
                    ['title' => 'Почисти зъбите', 'text' => 'Използвай нежни движения върху зъбите и венците за 2-3 минути.'],
                    ['title' => 'Поднови при нужда', 'text' => 'Когато върхът се износи, просто го изрежи и подготви нов.'],
                ],
                'note' => 'Не натискай прекалено силно. Съхранявай на сухо и чисто място. Не споделяй своя Miswak с други хора.',
            ],
            'funnel.packages_intro' => [
                'eyebrow' => 'Избери пакет',
                'title' => 'Избери своя натурален ритуал',
                'intro' => 'Избери пакет от 3 броя нагоре за дома, офиса, път или семейна употреба. Цените са ясни, в EUR, и можеш да добавиш директно в количката.',
            ],
            'funnel.labels' => [
                'eyebrow' => 'Съзнателен избор',
                'title' => 'За хора, които четат етикетите',
                'lines' => [
                    'Ако избираш какво слагаш върху кожата си.',
                    'Ако внимаваш какво ядеш.',
                    'Ако не искаш излишна пластмаса около себе си.',
                ],
                'body' => 'Защо устната ти грижа да е изключение? Miswak е за хора, които искат по-натурален, по-прост и по-съзнателен избор.',
                'cta' => 'Направи промяната днес',
            ],
            'funnel.testimonials' => [
                'eyebrow' => 'Отзиви',
                'title' => 'Прост продукт. Реална промяна в навика.',
                'quotes' => [
                    ['name' => 'Мария', 'quote' => 'Първо ми беше странно, но след няколко ползвания започна да ми харесва много. Най-много го използвам след кафе.'],
                    ['name' => 'Николай', 'quote' => 'Харесва ми, че няма паста, няма пластмаса и мога да го нося навсякъде.'],
                    ['name' => 'Елена', 'quote' => 'Взех пакет от 3 броя - един вкъщи, един в колата и един в офиса. Много практично.'],
                ],
            ],
            'funnel.faq' => [
                'title' => 'Често задавани въпроси',
                'items' => [
                    ['question' => 'Замества ли обикновената четка за зъби?', 'answer' => 'Miswak може да бъде част от ежедневната устна хигиена. При правилна употреба подпомага механичното почистване на зъбите и венците. Не замества стоматологични прегледи или професионален съвет.'],
                    ['question' => 'Трябва ли да използвам паста?', 'answer' => 'Не е задължително. Miswak се използва директно, след като върхът се подготви.'],
                    ['question' => 'Има ли вкус?', 'answer' => 'Да, има естествен растителен вкус. При първа употреба може да е необичаен, но повечето хора свикват бързо.'],
                    ['question' => 'Колко време издържа един Miswak?', 'answer' => 'Зависи от честотата на употреба. Когато върхът се износи, просто го изрязваш и подготвяш нов.'],
                    ['question' => 'Подходящ ли е за пътуване?', 'answer' => 'Да. Това е едно от най-големите му предимства - лек е, компактен и не изисква паста или вода.'],
                    ['question' => 'Как се съхранява?', 'answer' => 'На сухо и чисто място. Не го затваряй мокър за дълго време в плътна опаковка.'],
                ],
            ],
            'funnel.final_cta' => [
                'eyebrow' => 'Започни днес',
                'title' => 'Върни устната грижа обратно към природата',
                'lines' => [
                    'По-малко пластмаса.',
                    'По-малко излишни съставки.',
                    'Повече простота.',
                ],
                'body' => 'Miswak е малък натурален ритуал, който можеш да започнеш още днес.',
                'trust_line' => 'Доставка до адрес. Защитено плащане с карта през iCard.',
                'cta' => 'Поръчай Miswak',
            ],
        ];

        foreach ($blocks as $key => $content) {
            ContentBlock::query()->firstOrCreate(['key' => $key], ['content' => $content]);
        }
    }
}
