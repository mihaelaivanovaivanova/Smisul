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
use App\Models\ProductVariant;
use App\Services\PriceService;
use App\Services\ProductVariantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the "funnel mode" feature: a real Miswak product with 3 real
 * variants (so the product page and cart/checkout stay fully wired even
 * though the funnel landing page itself no longer shows a package grid -
 * see FunnelLandingPage.tsx), its 3 package-card overrides (kept for
 * potential reuse, unused by the current layout), and the funnel page's 10
 * editable copy sections. Funnel mode itself is seeded ON - the funnel
 * landing page is the actual live "/" experience, so a fresh install
 * should show it immediately rather than the plain HomePage; an admin can
 * still turn it off from /admin/funnel.
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
                'short_description' => 'Естествена четка за зъби от Salvadora persica - без паста, без вода, без пластмаса.',
                'description' => <<<'TEXT'
                Твоите зъби не спират да имат нужда от грижа само защото в момента не си вкъщи. Miswak е естествената алтернатива на четката за зъби - направена от корена на растението Salvadora persica, което хора по света използват за устна хигиена от хилядолетия, много преди изобщо да се появи пластмасовата четка.

                Без пластмасова дръжка, която ще лежи в природата стотици години. Без паста. Без нужда дори от глътка вода. Просто обели върха, сдъвчи леко, докато влакната се разделят в естествена четина, и почисти - където и да си: в колата, в офиса, след обяд, на път.

                Защо ще го обикнеш:
                Естествените съединения в Salvadora persica - силика, танини и сапонини - подпомагат почистването с всяко движение. Един Miswak стига за седмици напред: отрязваш износения връх и продължаваш със същата пръчица, без нов консуматив всеки месец. И тъй като е изцяло растителен, се връща обратно в природата, вместо да остане в нея с векове.

                Съставки:
                Естествени растителни влакна от корена на Salvadora persica, съдържащи силика, танини, сапонини и минерални компоненти. Без добавки, без изкуствени вещества.

                Как да използвате:
                Обели около 1 см от единия край, сдъвчи внимателно, докато влакната се разделят във фина четина, и почисти зъбите и венците с нежни кръгови движения за 2-3 минути. Изплакни устата, когато приключиш.

                Съхранение:
                Дръж на сухо и чисто място между употреби. Когато върхът се износи, отрежи го и оголи следващия сантиметър, за да подготвиш нов.
                TEXT,
                'status' => ProductStatus::Published,
                'published_at' => now(),
            ],
        );

        foreach ($this->variantDefinitions() as $sortOrder => $definition) {
            $variant = $product->variants()->where('sku', $definition['sku'])->first();
            $variantData = new ProductVariantData(
                sku: $definition['sku'],
                name: $definition['name'],
                packSize: $definition['pack_size'],
                isDefault: $definition['is_default'] ?? false,
                sortOrder: $sortOrder,
            );

            // update() (not just create()) runs for already-existing
            // variants too - otherwise is_default only ever gets set at
            // creation time, and moving the default from MISWAK-3 to
            // MISWAK-5 (both already existed) would silently never take
            // effect on a re-seed.
            $variant = $variant === null
                ? $variantService->create($product, $variantData)
                : $variantService->update($variant, $variantData);

            $priceService->setPrice($variant, new PriceData(
                currency: Currency::EUR->value,
                amount: $definition['amount'],
                compareAtAmount: $definition['compare_at_amount'] ?? null,
            ));

            $variant->inventory()->update(['quantity_on_hand' => $definition['stock']]);

            // Per-pack-size gallery photo (real product packaging shots,
            // one per pack count) — by request, so switching "Разфасовка"
            // on the product page swaps the gallery to match. Only set for
            // pack sizes with a real photo on hand; variants without a
            // 'gallery_image' key here just fall back to the product's own
            // gallery (see getGalleryImagesForVariant() on the frontend).
            if (isset($definition['gallery_image'])) {
                $this->seedVariantImage(
                    $variant,
                    $definition['gallery_image'],
                    "products/variants/{$definition['sku']}.jpg",
                    "Miswak - опаковка {$definition['pack_size']} бр.",
                );
            }
        }

        $product->seo()->updateOrCreate([], [
            'meta_title' => 'Miswak - натурална четка за зъби | Smisul',
            'meta_description' => 'Открий Miswak - 100% натурална четка за зъби от Salvadora persica. Без паста, без вода, без пластмаса.',
            'meta_keywords' => 'miswak, натурална четка за зъби, salvadora persica, smisul',
            'og_title' => 'Miswak - избирай с мисъл',
            'og_description' => 'Натурална четка за зъби директно от природата.',
        ]);

        $this->seedImage($product, 'hero-miswak-hand.webp', 'products/miswak-hero-hand.webp', 'Ръка, която държи Miswak', 0, true);
        $this->seedImage($product, 'miswak-closeup.jpg', 'products/miswak-closeup.jpg', 'Близък план на подготвен връх на Miswak', 1, false);
        $this->seedImage($product, 'miswak-bundle.webp', 'products/miswak-bundle.webp', 'Miswak натурални пръчици', 2, false);
        // The real retail package photo (gift-pack bundle) — added alongside
        // the staged lifestyle/studio shots above, not replacing them.
        $this->seedImage($product, 'miswak-package-real.jpg', 'products/miswak-package-real.jpg', 'Miswak в оригиналната опаковка', 3, false);
        // Powers HowToUseSection's optional demo clip (frontend/src/components/
        // funnel/sections/HowToUseSection.tsx) - the video isn't mounted/
        // requested until the visitor clicks the poster's play button, so
        // this ~44MB file is never loaded automatically.
        $this->seedVideo($product, 'miswak-how-to-use.mp4', 'products/miswak-how-to-use.mp4', 'Демонстрация как се използва Miswak', 4);

        return $product;
    }

    /**
     * Prices match ai/context/14_Offer_and_Pricing.md's target table
     * exactly (re-verified against that doc, then confirmed with the user
     * after a discrepancy was caught on the 5-pack - see git history for
     * the conversation). compare_at_amount is intentionally omitted on
     * every variant: the previous values (10.47 / 17.45 / 34.90) had no
     * documented basis anywhere in the project - fabricated "was more
     * expensive" anchors, not real former prices - so Price.isOnSale()
     * now correctly returns false for all of these; no bundle is
     * actually "on sale". Real, defensible savings are computed instead
     * in PackageOffers.tsx by comparing each bundle's price against
     * pack_size × the verified 1-stick price (€3.99) - the same
     * methodology as the doc's own "Saving vs. single price" column.
     *
     * is_default moved from MISWAK-3 to MISWAK-5 per the doc: "The
     * 5-pack should be pre-selected by default on the product page
     * during the launch test."
     */
    private function variantDefinitions(): array
    {
        return [
            ['sku' => 'MISWAK-1', 'name' => '1 бр.', 'pack_size' => 1, 'amount' => 3.99, 'stock' => 200, 'gallery_image' => 'miswak-pack-1.jpg'],
            ['sku' => 'MISWAK-3', 'name' => '3 бр.', 'pack_size' => 3, 'amount' => 10.99, 'stock' => 200, 'gallery_image' => 'miswak-pack-3.jpg'],
            ['sku' => 'MISWAK-5', 'name' => '5 бр.', 'pack_size' => 5, 'is_default' => true, 'amount' => 17.49, 'stock' => 200, 'gallery_image' => 'miswak-pack-5.jpg'],
            ['sku' => 'MISWAK-10', 'name' => '10 бр.', 'pack_size' => 10, 'amount' => 32.99, 'stock' => 200, 'gallery_image' => 'miswak-pack-10.jpg'],
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

    /**
     * Same as seedImage() above but attaches to a ProductVariant instead of
     * the Product - a single, sort_order-0 photo per variant is enough
     * (there's no per-variant gallery/thumbnail strip, just the one photo
     * the product page swaps to on pack-size selection), so isPrimary isn't
     * a parameter here.
     */
    private function seedVariantImage(ProductVariant $variant, string $sourceFilename, string $path, string $altText): void
    {
        $sourcePath = __DIR__."/assets/funnel/{$sourceFilename}";
        $contents = file_get_contents($sourcePath);

        Storage::disk('public')->put($path, $contents);

        Media::updateOrCreate(
            ['mediable_type' => ProductVariant::class, 'mediable_id' => $variant->id, 'path' => $path],
            [
                'disk' => 'public',
                'filename' => basename($path),
                'mime_type' => 'image/jpeg',
                'size' => Storage::disk('public')->size($path),
                'alt_text' => $altText,
                'sort_order' => 0,
                'is_primary' => true,
            ],
        );
    }

    /**
     * Same idea as seedImage() above, kept as its own method rather than a
     * generalized mime-type parameter - video assets are large enough
     * (tens of MB) that streaming the copy (a resource, not
     * file_get_contents' full in-memory string) actually matters here.
     */
    private function seedVideo(Product $product, string $sourceFilename, string $path, string $altText, int $sortOrder): void
    {
        $sourcePath = __DIR__."/assets/funnel/{$sourceFilename}";
        $stream = fopen($sourcePath, 'r');

        Storage::disk('public')->put($path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        Media::updateOrCreate(
            ['mediable_type' => Product::class, 'mediable_id' => $product->id, 'path' => $path],
            [
                'disk' => 'public',
                'filename' => basename($path),
                'mime_type' => 'video/mp4',
                'size' => Storage::disk('public')->size($path),
                'alt_text' => $altText,
                'sort_order' => $sortOrder,
                'is_primary' => false,
            ],
        );
    }

    private function seedConfig(Product $product): void
    {
        $variantIds = $product->variants()->pluck('id', 'sku');

        // FunnelConfig::current() defaults a first-ever row to is_enabled
        // true (see that method) - not forced here, so re-seeding content
        // on an existing install never overwrites an admin's later choice
        // to turn funnel mode off from /admin/funnel.
        $config = FunnelConfig::current();
        $config->update([
            'product_id' => $product->id,
            // 4 packages (1/3/5/10) matching ai/context/14_Offer_and_Pricing.md
            // exactly. No duration_label anywhere - "lasts N weeks/months"
            // was an unverified usage-duration claim (flagged explicitly,
            // removed). No "Безплатна доставка с BOX NOW" offer text on
            // the 10-pack either, even though it was requested: the
            // free-shipping-over-€25-via-BOX-NOW threshold isn't actually
            // implemented in ShippingService yet (flat per-carrier rates
            // only - see [[funnel-relaunch-decisions]] memory), so
            // advertising it here would be a promise checkout doesn't
            // honor. Add it back once that logic exists.
            'packages' => [
                [
                    'variant_id' => $variantIds['MISWAK-1'],
                    'badge' => 'ОПИТАЙ РАЗЛИКАТА',
                    'detail' => '1 брой',
                    'value_label' => 'Само да пробвам първо',
                    'button_text' => 'ИЗБИРАМ 1 БРОЙ',
                ],
                [
                    'variant_id' => $variantIds['MISWAK-3'],
                    'badge' => 'ЗА НАЧАЛО',
                    'detail' => '3 броя',
                    'value_label' => 'Един у дома. Един в чантата. Един в офиса.',
                    'button_text' => 'ИЗБИРАМ 3 БРОЯ',
                ],
                [
                    'variant_id' => $variantIds['MISWAK-5'],
                    'badge' => 'НАЙ-ПОПУЛЯРЕН',
                    'detail' => '5 броя',
                    'value_label' => 'За ежедневието ти + няколко резервни.',
                    'button_text' => 'ИЗБИРАМ 5 БРОЯ',
                ],
                [
                    'variant_id' => $variantIds['MISWAK-10'],
                    'badge' => 'НАЙ-ИЗГОДЕН',
                    'detail' => '10 броя',
                    'value_label' => 'За двама или за по-дълъг запас.',
                    'button_text' => 'ИЗБИРАМ 10 БРОЯ',
                ],
            ],
        ]);
    }

    private function seedContent(): void
    {
        $blocks = [
            // The H1 carries the benefit + product promise (ad visitors
            // decide in seconds); the brand poetry lives in the eyebrow
            // line above it instead of being the headline itself.
            'funnel.hero' => [
                'eyebrow' => '100% ЕСТЕСТВЕНА ГРИЖА. КЪДЕТО И ДА СИ.',
                'title' => 'Не ти ли писна зъбите ти да са чисти... АМА САМО ПОНЯКОГА?',
                'body' => 'Miswak е естествена пръчица от Salvadora persica за почистване на зъбите, която можеш да използваш без паста и без мивка - след кафе, в офиса, в колата или когато си на път.',
                'cta_primary' => 'ВЗЕМИ MISWAK',
                'cta_secondary' => 'НАУЧИ ПОВЕЧЕ',
                'trust_items' => [
                    ['icon' => 'leaf', 'label' => "100%\nнатурален"],
                    ['icon' => 'recycle', 'label' => 'Биоразградим'],
                    ['icon' => 'no-plastic', 'label' => 'Без пластмаса'],
                    ['icon' => 'envelope', 'label' => "Навсякъде\nс теб"],
                    ['icon' => 'no-paste', 'label' => 'Без паста'],
                ],
            ],
            // "What Is Miswak" (#what-is-miswak) - a plain product explainer,
            // not the tradition/history angle (that lives in funnel.history
            // instead, so the two no longer overlap). benefits_title/benefits
            // are the "what natural substances does the wood contain" list -
            // supplied wording, verbatim (fixed two apparent mixed-script
            // typos - a Latin "M"/"e" mixed into otherwise-Cyrillic words -
            // not a content change).
            'funnel.intro' => [
                'title' => 'Какво всъщност е Miswak?',
                'paragraphs' => [
                    'Miswak е пръчица от дървото Salvadora persica, използвана традиционно за почистване на зъбите и устната кухина, употребявана от поколения хора много преди появата на пластмасовата четка за зъби. Механичните движения на влакната премахват плаката и полират емайла, създавайки естествен блясък без необходимостта от абразивни и агресивни химически препарати, връщайки натуралната сияйност на усмивката.',
                ],
                'benefits_title' => 'Ползи от естествените вещества, които дървото Salvadora Persica съдържа:',
                'benefits' => [
                    ['label' => 'Силициев диоксид', 'description' => 'подпомага механичното почистване.'],
                    ['label' => 'Калций и калий', 'description' => 'естествено срещащи се минерали.'],
                    ['label' => 'Естествен флуорид', 'description' => 'традиционно свързван с поддържането на устната хигиена.'],
                    ['label' => 'Етерични масла', 'description' => 'допринасят за усещане за свежест.'],
                    [
                        'label' => 'Растителни антиоксиданти и биоактивни съединения',
                        'description' => 'естествена част от структурата на дървото.',
                    ],
                ],
            ],
            // Core Benefits (#core-benefits) - exactly 3 cards by design
            // (see CoreBenefitsSection.tsx). The old card 2 ("допълва
            // ежедневната грижа" - complement, not replacement) and card 3
            // (yearly-discarded-toothbrushes eco stat) were replaced, not
            // relocated - reported to the user rather than silently
            // dropped, since the complement-not-replacement message is
            // brand-critical (still lives in the FAQ's "Замества ли..."
            // answer, just no longer always-visible).
            'funnel.why' => [
                'title' => 'Защо милиони хора по света използват Miswak?',
                'cards' => [
                    [
                        'icon' => 'clock',
                        'title' => 'Винаги под ръка',
                        'text' => 'Използвай го в офиса, колата или когато си на път.',
                    ],
                    [
                        'icon' => 'tooth',
                        'title' => 'Пълна устна хигиена',
                        'text' => 'Чистота, свеж дъх и сияйна усмивка без плака.',
                    ],
                    [
                        'icon' => 'globe',
                        'title' => 'Естествено прост',
                        'text' => 'Една растителна пръчица от Salvadora persica без пластмаса и химикали.',
                    ],
                ],
                'closing' => 'По-малко неща. Повече свобода кога и къде да се погрижиш за зъбите си.',
            ],
            // History (#history) - deliberately short (one mobile screen),
            // moved later in the page order (see FunnelLandingPage.tsx's
            // section map) since it's supplementary context, not a
            // primary argument. No "if a longer article exists" link:
            // no such article/route exists anywhere in this codebase, so
            // omitted rather than invented - see HistorySection.tsx's
            // doc comment.
            'funnel.history' => [
                'title' => 'Не е нов тренд.',
                'subtitle' => 'Miswak се използва от хилядолетия.',
                'body' => 'Употребата на растителни пръчици за устна хигиена има дълга история в части от Африка, Азия и Близкия изток. Днес Salvadora persica продължава да представлява интерес и за съвременната наука.',
            ],
            // Natural / Eco (#natural-eco). Deliberately avoids absolute
            // environmental claims ("zero waste", unqualified "100%
            // biodegradable" - packaging/labels aren't verified as
            // biodegradable, "microplastic-free" as a blanket claim,
            // or any environmental claim without evidence behind it).
            // The brand statement below is philosophy ("по-малко
            // излишно"), not a factual claim, so it doesn't need the
            // same evidentiary bar.
            'funnel.natural_eco' => [
                'eyebrow' => 'ПО-МАЛКО ИЗЛИШНО',
                'title' => 'Естествено прост по замисъл.',
                'paragraphs' => [
                    'В основата на Miswak няма сложна технология - има растителна пръчица от Salvadora persica, чиито естествени влакна се използват за механично почистване на зъбите.',
                    'Без пластмасова дръжка. Без нужда от паста при употреба. Един прост продукт с ясна функция.',
                ],
                'brand_statement' => 'За нас това е по-смисленият тип устойчивост - не повече „еко“ продукти, а по-малко излишно.',
            ],
            // Actual Product / What You Receive (#actual-product). Two
            // items removed from the original 8 - "Биоразградим"
            // (unqualified "biodegradable" claim spanning the whole
            // delivered product, not just the raw stick - packaging/
            // labels aren't verified) and "Без отпадък" ("zero waste" -
            // an unsupported absolute claim). Each item now carries its
            // own icon explicitly (previously matched to a fixed
            // frontend array by array position - silently wrong the
            // moment items were added/removed/reordered here).
            'funnel.features' => [
                'title' => 'Какво прави Miswak толкова специален?',
                'items' => [
                    ['icon' => 'icon-feature-natural-100', 'label' => '100% натурален'],
                    ['icon' => 'icon-feature-no-plastic', 'label' => 'Без пластмаса'],
                    ['icon' => 'icon-feature-easy-carry', 'label' => 'Лесен за носене'],
                    ['icon' => 'icon-feature-travel', 'label' => 'Подходящ за пътуване'],
                    ['icon' => 'icon-feature-no-batteries', 'label' => 'Без батерии, без консумативи'],
                    ['icon' => 'icon-feature-natural-daily', 'label' => 'Естествен избор за ежедневието'],
                ],
            ],
            // "Us vs. them" checklist - each row is a positive statement
            // that is true for Miswak (✓) and false for a plastic brush
            // (✗). Every claim must stay defensible: no invented competitor
            // prices, only structural differences.
            // Comparison (#comparison) - deliberately complementary, not
            // "us vs. them": Miswak is framed as the better fit for
            // specific moments, not a wholesale toothbrush replacement
            // (row 6 makes that explicit - Miswak "complements", the
            // brush still wins "standard home routine").
            'funnel.comparison' => [
                'title' => 'Виж разликата',
                'miswak_label' => 'Miswak',
                'brush_label' => 'Четка + паста',
                'rows' => [
                    ['label' => 'Удобен извън дома', 'miswak_value' => '✓', 'brush_value' => '✕'],
                    ['label' => 'Използва се без паста', 'miswak_value' => '✓', 'brush_value' => '✕'],
                    ['label' => 'Използва се навсякъде и по всяко време', 'miswak_value' => '✓', 'brush_value' => '△'],
                    ['label' => 'Лесен за носене', 'miswak_value' => '✓', 'brush_value' => '△'],
                    ['label' => 'Без микропластмаса - изцяло растителен', 'miswak_value' => '✓', 'brush_value' => '✕'],
                    ['label' => 'Стандартна домашна рутина', 'miswak_value' => 'Допълва', 'brush_value' => '✓'],
                    ['label' => '100% биоразградим - нулев отпадък', 'miswak_value' => '✓', 'brush_value' => '✕'],
                    ['label' => 'Без консумативи - отрязваш върха и продължаваш', 'miswak_value' => '✓', 'brush_value' => '✕'],
                ],
                'closing' => 'Две решения за различни моменти от деня.',
            ],
            // Science (#science) - every claim here is the supplied wording
            // verbatim, not paraphrased.
            //  - PMID 35944735: systematic review/meta-analysis - miswak
            //    "comparable with the toothbrush" alone, "significantly
            //    more superior" as an adjunct, for plaque/gingivitis.
            //  - PMID 40475057: systematic review, 10 studies / 442
            //    participants - antibacterial/antibiofilm activity. The
            //    callout's "Стотици участници." is this exact paper's own
            //    participant count; "Десетки клинични проучвания" is a
            //    deliberate looser rounding (user-requested), not this
            //    paper's own "10 studies" figure anymore.
            //  - PMID 32565699: user-supplied (whitening potential / silica
            //    abrasive, ~2-shade change at 0.4-0.5% extract in a lab
            //    study) - card + link both supplied verbatim, not
            //    independently fetched/checked by the assistant.
            'funnel.science' => [
                'eyebrow' => 'НЕ САМО ТРАДИЦИЯ',
                'title' => 'Древен навик. Съвременни доказателства.',
                'intro' => 'Miswak се използва от поколения. Днес Salvadora persica е обект и на клинични изследвания за ролята ѝ в ежедневната орална хигиена.',
                'cards' => [
                    [
                        'title' => 'Редуцира плаката и възпалението',
                        'body' => 'Систематични прегледи и клинични проучвания показват, че правилната употреба Miswak подпомага механичното отстраняване на зъбната плака, и възпалението на венците в степен, сравнима със стандартната четка за зъби, а комбинираното им използване гарантира дори по-добри резултати.',
                        'source_url' => 'https://pubmed.ncbi.nlm.nih.gov/35944735/',
                        'source_label' => 'ВИЖ НАУЧНИЯ ИЗТОЧНИК ↗',
                    ],
                    [
                        'title' => 'Антибактериална зашита',
                        'body' => 'Проучване от 2025 г. показва, че Мисвакът има изразено антибактериално и антибиофилмново действие. Той може да подпомогне ограничаването на кариесогенните бактерии и натрупването на зъбна плака, като в редица проучвания резултатите му са сравними с конвенционални средства за орална хигиена.',
                        'source_url' => 'https://pubmed.ncbi.nlm.nih.gov/40475057/',
                        'source_label' => 'ВИЖ НАУЧНИЯ ИЗТОЧНИК ↗',
                    ],
                    [
                        'title' => 'Избелващи свойства',
                        'body' => 'Научният преглед посочва избелващия потенциал на мисвака, свързан включително със съдържащата се в него силика, която действа като естествен абразив. В лабораторно изследване мисвак паста с 0,4 – 0,5% екстракт постига средно промяна от около два нюанса, с ефект, сравним с използваната избелваща паста за зъби.',
                        'source_url' => 'https://pubmed.ncbi.nlm.nih.gov/32565699/',
                        'source_label' => 'ВИЖ НАУЧНИЯ ИЗТОЧНИК ↗',
                    ],
                ],
                'callout' => [
                    'stat' => 'Десетки клинични проучвания. Стотици участници. Един прост растителен продукт.',
                    'body' => 'Не твърдим, че Miswak е чудо. Научните данни просто показват, че зад дългата му традиция има повече от история.',
                ],
                'safety' => [
                    'title' => 'Правилната техника има значение.',
                    'body' => 'Използвай добре омекотени влакна и нежни движения без прекомерен натиск.',
                ],
            ],
            // Skepticism/Honesty (#skepticism-honesty). The brief's body
            // paragraph offered "Пробвахме я" (we tried it ourselves) only
            // if that's factually true for the founders/team - nothing in
            // ai/context/ or elsewhere confirms that, so this uses the
            // brief's own supplied safer fallback wording instead. Flag
            // this to the user before ever switching to the first-person
            // "tried it" version.
            'funnel.awareness' => [
                'title' => 'Пръчка за зъби?',
                'subtitle' => 'И на нас в началото ни звучеше странно.',
                'body' => 'После разбрахме как се използва, разгледахме историята ѝ и прочетохме какво показват проучванията. И тогава започна да има смисъл.',
            ],
            // Positioning Statement (#positioning-statement) - the
            // complement-not-replacement message, immediately after
            // Skepticism/Honesty. Previously only lived inside the old
            // Core Benefits card 2 (removed) and the FAQ's "Замества ли
            // четката и пастата?" answer.
            'funnel.positioning' => [
                'title' => 'Не е нужно да заменяш четката си.',
                'body' => 'Просто вече имаш какво да използваш, когато тя не е с теб.',
            ],
            // Brand Statement (#brand-statement). title unchanged (already
            // matched the brief exactly); paragraphs replaced. Kept short
            // by design - this stays a brand statement, not an About Us
            // page (see BrandStatementSection.tsx's doc comment). The
            // small tagline under it ("По-малко излишно. Повече смисъл.")
            // is the brand promise from ai/context/00_Project_Vision.md -
            // fixed copy (frontend content/copy.ts), not stored here,
            // since it's a permanent brand asset, not admin-editable
            // marketing content.
            'funnel.final_cta' => [
                'title' => 'Един малък избор. С по-голям смисъл.',
                'paragraphs' => [
                    'Не всеки продукт трябва да бъде сложен, нов или технологичен.',
                    'Понякога доброто решение вече съществува. Просто трябва да има ясна причина да бъде част от ежедневието ни.',
                    'Такъв е смисълът зад с|мисъл.',
                ],
                'cta' => 'ПОРЪЧАЙ СВОЯ MISWAK',
                // "Доставка 1-2 работни дни" matches the shipping methods'
                // estimated delivery. Payment: card via iCard is the only
                // method now (PaymentMethod::active()) — the former
                // "Наложен платеж" item was dropped along with cash on
                // delivery itself (BOX NOW deliveries are card-only). The
                // "100% гаранция за качество" item was dropped for a while
                // as a vague claim with no policy behind it, but is back
                // by request.
                'trust_items' => [
                    ['icon' => 'truck', 'label' => 'Доставка 1-2 работни дни'],
                    ['icon' => 'card', 'label' => 'Сигурно плащане с карта'],
                    ['icon' => 'check-badge', 'label' => "100%\nгаранция за качество"],
                    ['icon' => 'undo', 'label' => '30 дни право на връщане'],
                ],
            ],
            // FAQ (#faq) - refactored to a fixed 10-question order. Every
            // answer either (a) reuses previously-verified copy that was
            // already live (just retitled where the old question label
            // didn't actually match its own content - e.g. "Има ли срок на
            // годност?" was pure storage advice, not an expiry claim, so
            // now lives under "Как се съхранява?"), (b) is new copy built
            // only from a fact verified elsewhere in this project
            // (ai/context/02_Product_Strategy.md's "Imported from Pakistan"
            // for Q7; the verified PMIDs behind funnel.science for Q9), or
            // (c) - Q10 only - is an explicit "we don't have this verified"
            // answer rather than invented child-safety guidance (see below).
            // Three old items (eco-choice, "why so few people know",
            // "why we sell Miswak") were dropped: not part of the supplied
            // order, and their substance already lives in NaturalEcoSection,
            // SkepticismHonestySection, and BrandStatementSection.
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
                        // Exact required wording - supplied verbatim, not paraphrased.
                        'question' => 'Замества ли четката и пастата?',
                        'answer' => 'Не е необходимо да избираш между тях. Miswak може да бъде удобен начин да допълниш ежедневната си грижа - особено когато четката и пастата не са под ръка.',
                    ],
                    [
                        // = old "Колко време издържа един Miswak?" (question retitled to match the requested order; answer unchanged).
                        'question' => 'Колко време се използва един Miswak?',
                        'answer' => 'Продължителността на употреба зависи от това колко често го използвате.  Средно една клонка може да служи между две и четири седмици при редовна употреба.  Когато влакната се износят, е достатъчно да отрежете малка част от върха и да оформите нова естествена четка.  Това прави Miswak не само практичен, но и изключително икономичен избор.',
                    ],
                    [
                        // = old "Има ли срок на годност?" - that content was
                        // already pure storage advice (no expiry claim), so
                        // it belongs here, not under a shelf-life question.
                        'question' => 'Как се съхранява?',
                        'answer' => 'Като естествен продукт Miswak запазва качествата си най-добре, когато се съхранява на сухо и прохладно място.  След започване на употреба е препоръчително да освежавате върха периодично, като отрязвате използваните влакна.  Ако клонката изсъхне прекомерно, може за кратко да се потопи във вода, за да възстанови част от естествената си еластичност.',
                    ],
                    [
                        // = old "Има ли вкус?" (retitled; answer unchanged).
                        'question' => 'Какъв вкус има?',
                        'answer' => 'Да.  Miswak има лек естествен, дървесен и свеж вкус, който много хора определят като приятен и ненатрапчив.  Той не съдържа изкуствени аромати, подсладители или оцветители. Именно това е една от причините все повече хора да го предпочитат като естествена алтернатива в ежедневието си.',
                    ],
                    [
                        'question' => 'Колко често може да се използва?',
                        'answer' => 'Няма строго ограничение. Miswak е създаден така, че да бъде удобен за използване винаги, когато почувствате нужда от свежест.  Много хора го използват сутрин и вечер като част от ежедневната си грижа, а през деня – след хранене, преди важна среща, по време на пътуване или когато нямат достъп до вода и четка за зъби.  Той е чудесно допълнение към обичайната устна хигиена и лесно се превръща в полезен ежедневен навик.',
                    ],
                    [
                        // New - the only verified fact about sourcing of the
                        // specific product sold (not the species in general,
                        // see Q8): ai/context/02_Product_Strategy.md.
                        'question' => 'Откъде произхожда конкретният продукт?',
                        'answer' => 'Продуктът, който предлагаме, се внася от Пакистан. с|мисъл не е производител на Miswak - внасяме и предлагаме готовите естествени пръчици на българския пазар.',
                    ],
                    [
                        // New - species/history facts split out from the old
                        // "Откъде произхожда Miswak?" answer (which conflated
                        // species info with product sourcing, now Q7).
                        'question' => 'Какво е Salvadora persica?',
                        'answer' => 'Salvadora persica е растение, което естествено расте в сухите райони на Близкия изток, Северна и Източна Африка, както и части от Южна Азия. От хилядолетия клонките му се използват за поддържане на устната хигиена в много култури - именно от тях е направен Miswak.',
                    ],
                    [
                        // New - same verified PMIDs cited in funnel.science, not new claims.
                        'question' => 'Има ли научни изследвания?',
                        'answer' => 'Да. Salvadora persica е обект на клинични изследвания за ролята ѝ в ежедневната устна хигиена - систематични прегледи отчитат ефект върху контрола на зъбната плака, сравним с този на стандартна четка за зъби, както и антибактериална активност. Виж повече в раздел "Древен навик. Съвременни доказателства." по-горе.',
                    ],
                    [
                        // FLAGGED TO USER: no verified child-safety/medical
                        // guidance exists anywhere in ai/context/ or
                        // elsewhere in this project (the old answer here
                        // asserted "yes, under parental supervision" with no
                        // cited basis - an unverified claim this rewrite
                        // deliberately does not carry forward). This answer
                        // states that honestly instead of inventing an age
                        // recommendation or a supervision/safety claim.
                        'question' => 'Подходящ ли е за деца?',
                        'answer' => 'Нямаме официално потвърдени препоръки за употреба от деца. Ако имаш въпроси относно подходящата възраст или начин на използване, препоръчваме консултация със зъболекар.',
                    ],
                ],
            ],
        ];

        foreach ($blocks as $key => $content) {
            ContentBlock::query()->updateOrCreate(['key' => $key], ['content' => $content]);
        }
    }
}
