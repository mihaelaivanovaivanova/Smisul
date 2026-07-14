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
 * variants (so the product page and cart/checkout stay fully wired even
 * though the funnel landing page itself no longer shows a package grid —
 * see FunnelLandingPage.tsx), its 3 package-card overrides (kept for
 * potential reuse, unused by the current layout), and the funnel page's 9
 * editable copy sections. Funnel mode itself is seeded OFF; an admin turns
 * it on from /admin/funnel.
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
                'title' => "Едно дърво.\nХиляди години\nдоверие.",
                'body' => 'Miswak е естествен клон от дървото Salvadora Persica, използван от поколения хора много преди появата на пластмасовата четка за зъби. Днес той отново намира своето място като практично и природосъобразно допълнение към ежедневната устна хигиена.',
                'cta_primary' => 'ПОРЪЧАЙ СЕГА',
                'cta_secondary' => 'НАУЧИ ПОВЕЧЕ',
                'trust_items' => [
                    ['icon' => 'leaf', 'label' => "100%\nнатурален"],
                    ['icon' => 'recycle', 'label' => 'Биоразградим'],
                    ['icon' => 'no-plastic', 'label' => 'Без пластмаса'],
                    ['icon' => 'envelope', 'label' => "Навсякъде\nс теб"],
                ],
            ],
            'funnel.intro' => [
                'title' => 'Не винаги новото е по-доброто.',
                'paragraphs' => [
                    'Преди появата на съвременната четка за зъби хората в Азия, Африка и Близкия изток използват клонките на дървото Salvadora Persica за почистване на зъбите и освежаване на дъха.',
                    'Тази традиция не е оцеляла случайно. Тя е предавана от поколение на поколение в продължение на хилядолетия, защото работи.',
                    'Днес, когато все повече хора търсят естествени, устойчиви и екологични алтернативи, Miswak отново заема заслуженото си място.',
                ],
            ],
            'funnel.why' => [
                'title' => 'Защо милиони хора по света използват Miswak?',
                'cards' => [
                    [
                        'icon' => 'clock',
                        'title' => 'Винаги под ръка',
                        'text' => 'Не ти трябва вода. Не ти трябва паста. Не ти трябва мивка. Използвай го в офиса, в колата, по време на път, в планината или между две срещи.',
                    ],
                    [
                        'icon' => 'tooth',
                        'title' => 'Допълва ежедневната грижа',
                        'text' => 'Miswak не е създаден, за да замени обичайната ти четка за зъби. Той е естествен помощник през деня — за свеж дъх, приятно усещане за чистота и поддържане на добра устна хигиена, когато си извън дома.',
                    ],
                    [
                        'icon' => 'globe',
                        'title' => 'Малък навик. Голяма разлика.',
                        'text' => 'Всяка година по света се изхвърлят милиарди пластмасови четки за зъби. Повечето от тях остават в природата десетилетия. Miswak се връща обратно в нея. Напълно естествено.',
                    ],
                ],
            ],
            'funnel.history' => [
                'title' => 'Повече от 7000 години история',
                'paragraphs' => [
                    'Преди науката да започне да изучава Miswak, хората вече са го използвали ежедневно. Исторически сведения показват употребата му в различни древни цивилизации, където естествените средства за здравето са били част от ежедневието.',
                    'Днес интересът към Miswak не се дължи единствено на традицията. Все повече научни изследвания разглеждат неговия състав и свойствата на естествените вещества, които се съдържат в дървото Salvadora Persica. Така древната практика среща съвременния интерес към природните решения.',
                ],
                'stats' => [
                    ['icon' => 'hourglass', 'label' => 'Използван от хилядолетия'],
                    ['icon' => 'leaf', 'label' => 'Естествени активни съединения'],
                    ['icon' => 'users', 'label' => 'Доверие, предавано от поколение на поколение'],
                    ['icon' => 'check-badge', 'label' => 'Традиция, подкрепена от съвременни изследвания'],
                ],
            ],
            'funnel.features' => [
                'title' => 'Какво прави Miswak толкова специален?',
                'items' => [
                    ['label' => '100% натурален'],
                    ['label' => 'Биоразградим'],
                    ['label' => 'Без пластмаса'],
                    ['label' => 'Лесен за носене'],
                    ['label' => 'Подходящ за пътуване'],
                    ['label' => 'Без батерии, без консумативи'],
                    ['label' => 'Без отпадък'],
                    ['label' => 'Естествен избор за ежедневието'],
                ],
            ],
            'funnel.from_tree' => [
                'title' => 'Традицията продължава и днес',
                'paragraphs' => [
                    'Хилядолетия наред хората използват Miswak за ежедневна устна хигиена, а днес науката проявява все по-голям интерес към естествените вещества, които дървото Salvadora Persica съдържа.',
                    '✓ Силициев диоксид – подпомага механичното почистване.',
                    '✓ Калций и калий – естествено срещащи се минерали.',
                    '✓ Естествен флуорид – традиционно свързван с поддържането на устната хигиена.',
                    '✓ Етерични масла – допринасят за усещане за свежест.',
                    '✓ Растителни антиоксиданти и биоактивни съединения – естествена част от структурата на дървото.',
                ],
                'steps' => [],
            ],
            'funnel.awareness' => [
                'title' => "Ако Miswak съществува от хиляди години,\nзащо толкова малко хора го познават днес?",
                'paragraphs' => [
                    'С течение на времето светът се промени. Появиха се нови технологии, нови материали и масово производство. Пластмасовата четка постепенно се превърна в стандарт.',
                    'Но това не означава, че по-старите решения са изгубили своята стойност. Понякога най-ценните открития не са новите. А тези, които просто сме забравили.',
                ],
            ],
            'funnel.final_cta' => [
                'title' => 'Един малък избор. С по-голям смисъл.',
                'paragraphs' => [
                    'Всеки ден правим десетки малки избори. Какво да ядем. Какво да купим. Какво да изхвърлим. Miswak няма да промени света сам. Но може да промени начина, по който гледаш на ежедневните навици.',
                    'Защото понякога една малка естествена промяна в началото е нещо много по-голямо.',
                ],
                'cta' => 'ПОРЪЧАЙ СВОЯ MISWAK',
                'trust_items' => [
                    ['icon' => 'truck', 'label' => 'Бърза доставка'],
                    ['icon' => 'lock', 'label' => 'Сигурно плащане'],
                    ['icon' => 'check-badge', 'label' => '100% гаранция за качество'],
                    ['icon' => 'undo', 'label' => '30 дни право на връщане'],
                ],
            ],
            'funnel.faq' => [
                'title' => 'Често задавани въпроси',
                'items' => [
                    [
                        'question' => 'Как се използва Miswak?',
                        'answer' => 'Използването на Miswak е лесно и интуитивно. Отстранете около 1–2 см от кората в единия край и леко сдъвчете дървесните влакна, докато се разтворят и образуват естествена "четка". След това почиствайте зъбите с нежни движения, подобно на обикновена четка за зъби.  Когато влакната се износят, просто ги отрежете с няколко милиметра и повторете процеса. Един Miswak може да се използва в продължение на няколко седмици, в зависимост от честотата на употреба.  За най-добри резултати го съхранявайте на сухо и проветриво място между отделните използвания.',
                        'attachment_url' => '/funnel/docs/miswak-usage-manual.pdf',
                        'attachment_label' => 'Изтегли упътване за употреба (PDF)',
                    ],
                    [
                        'question' => 'Колко често може да се използва?',
                        'answer' => 'Няма строго ограничение. Miswak е създаден така, че да бъде удобен за използване винаги, когато почувствате нужда от свежест.  Много хора го използват сутрин и вечер като част от ежедневната си грижа, а през деня – след хранене, преди важна среща, по време на пътуване или когато нямат достъп до вода и четка за зъби.  Той е чудесно допълнение към обичайната устна хигиена и лесно се превръща в полезен ежедневен навик.',
                    ],
                    [
                        'question' => 'Подходящ ли е за деца?',
                        'answer' => 'Да, Miswak може да бъде използван и от деца под наблюдението на родител.  Тъй като представлява изцяло естествена дървесна клонка без пластмасови части и изкуствени влакна, много родители го избират като начин да запознаят децата си с природните алтернативи.  Все пак, както при всяко средство за устна хигиена, е важно детето да го използва правилно и внимателно.',
                    ],
                    [
                        'question' => 'Замества ли четката и пастата за зъби?',
                        'answer' => 'Ние не разглеждаме Miswak като заместител на традиционната четка за зъби, а като естествено допълнение към ежедневната грижа за устната хигиена.  Той е особено удобен, когато сте извън дома, пътувате, къмпингувате, сте в офиса или просто искате да освежите дъха си през деня.  Много хора успешно съчетават използването на Miswak с обичайната си рутина за миене на зъбите.',
                    ],
                    [
                        'question' => 'Има ли срок на годност?',
                        'answer' => 'Като естествен продукт Miswak запазва качествата си най-добре, когато се съхранява на сухо и прохладно място.  След започване на употреба е препоръчително да освежавате върха периодично, като отрязвате използваните влакна.  Ако клонката изсъхне прекомерно, може за кратко да се потопи във вода, за да възстанови част от естествената си еластичност.',
                    ],
                    [
                        'question' => 'Има ли вкус?',
                        'answer' => 'Да.  Miswak има лек естествен, дървесен и свеж вкус, който много хора определят като приятен и ненатрапчив.  Той не съдържа изкуствени аромати, подсладители или оцветители. Именно това е една от причините все повече хора да го предпочитат като естествена алтернатива в ежедневието си.',
                    ],
                    [
                        'question' => 'Откъде произхожда Miswak?',
                        'answer' => 'Нашият Miswak е изработен от дървото Salvadora Persica – растение, което естествено расте в сухите райони на Близкия изток, Северна и Източна Африка, както и части от Южна Азия.  Именно клонките на това дърво се използват от хилядолетия за поддържане на устната хигиена и са познати в много култури като естествено средство за почистване на зъбите.',
                    ],
                    [
                        'question' => 'Защо Miswak е по-екологичен избор?',
                        'answer' => 'Всяка пластмасова четка за зъби, която използваме днес, рано или късно се превръща в отпадък.  Miswak е различен.  Той е изцяло естествен, биоразградим и не съдържа пластмаса, синтетични влакна или микропластмаси.  След като приключи жизненият му цикъл, той просто се връща обратно в природата.  Понякога най-устойчивото решение е и най-простото.',
                    ],
                    [
                        'question' => 'Защо толкова малко хора знаят за Miswak?',
                        'answer' => 'Вероятно защото светът постепенно е възприел масово произвежданите продукти като стандарт.  Пластмасовата четка за зъби е удобна, позната и присъства във всеки магазин. Това обаче не означава, че природните решения са изгубили своята стойност.  Miswak е пример как една традиция, оцеляла хилядолетия, днес отново намира своето място сред хората, които търсят по-осъзнат и устойчив начин на живот.',
                    ],
                    [
                        'question' => 'Колко време издържа един Miswak?',
                        'answer' => 'Продължителността на употреба зависи от това колко често го използвате.  Средно една клонка може да служи между две и четири седмици при редовна употреба.  Когато влакната се износят, е достатъчно да отрежете малка част от върха и да оформите нова естествена четка.  Това прави Miswak не само практичен, но и изключително икономичен избор.',
                    ],
                    [
                        'question' => 'Защо избрахте да продавате Miswak?',
                        'answer' => 'Защото вярваме, че не всяка добра идея трябва да бъде нова. Понякога най-ценните решения вече съществуват от хилядолетия – просто сме ги забравили. Smisul не е създаден, за да се противопоставя на съвременната стоматология или на традиционната четка за зъби. Нашата мисия е да покажем, че природата все още има какво да ни предложи и че малките, осъзнати избори могат да бъдат по-добри както за нас, така и за околната среда. Ако успеем да заменим дори част от ежедневната употреба на пластмасови продукти с естествени алтернативи, значи сме постигнали своята цел.',
                    ],
                ],
            ],
        ];

        foreach ($blocks as $key => $content) {
            ContentBlock::query()->updateOrCreate(['key' => $key], ['content' => $content]);
        }
    }
}
