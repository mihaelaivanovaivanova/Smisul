<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Services\KitShellProductService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The two curated kits, built entirely from REAL existing variants (seeded
 * by FunnelSeeder - Miswak packs - and MiswakAccessoriesSeeder - bamboo
 * case and tongue scraper), referenced by their SKUs; never new variants.
 * Must run after both of those (see DatabaseSeeder).
 *
 * Re-running is safe: kits are upserted by slug, and their component rows
 * are upserted by (kit, variant). Existing component rows are never
 * deleted here, so a re-run can't pull components out from under kit
 * lines already sitting in carts (see BundleInUseException for why that
 * matters) - change a live kit's contents through the admin instead.
 *
 * Each kit's catalog shell (see KitShellProductService) is created on the
 * first run with the short_description/description below; a re-run only
 * syncs the shell's name/slug/status/price and never overwrites that
 * content, which is admin-editable via /admin/products from then on.
 */
class ProductBundleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $sortOrder => $definition) {
            $bundle = ProductBundle::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'badge' => $definition['badge'] ?? null,
                    'currency' => Currency::EUR->value,
                    'amount' => $definition['amount'],
                    'status' => VariantStatus::Active->value,
                    'sort_order' => $sortOrder,
                ],
            );

            foreach ($definition['items'] as $sku => $quantity) {
                $variant = ProductVariant::where('sku', $sku)->first()
                    ?? throw new RuntimeException("ProductBundleSeeder: variant [{$sku}] not found - run FunnelSeeder and MiswakAccessoriesSeeder first.");

                $bundle->items()->updateOrCreate(
                    ['product_variant_id' => $variant->id],
                    ['quantity' => $quantity],
                );
            }

            $this->syncShell($bundle, $definition);
        }
    }

    /**
     * Upgrade catch-up for a site whose kits already exist but predate
     * their catalog shells (see deployment/public_html/install.php): creates
     * just the missing shells, with the seeded content, without touching
     * the kits themselves - unlike run(), whose updateOrCreate would
     * overwrite any name/price/badge an admin has since set on a kit.
     */
    public function ensureShells(): void
    {
        $definitions = collect($this->definitions())->keyBy('slug');

        ProductBundle::query()->whereNull('shell_product_variant_id')->each(
            fn (ProductBundle $bundle) => $this->syncShell($bundle, $definitions->get($bundle->slug, [])),
        );
    }

    /**
     * @param  array{short_description?: string, description?: string}  $definition
     */
    private function syncShell(ProductBundle $bundle, array $definition): void
    {
        app(KitShellProductService::class)->sync(
            $bundle,
            $definition['short_description'] ?? null,
            $definition['description'] ?? null,
        );
    }

    /**
     * @return list<array{slug: string, name: string, badge?: string, amount: float, short_description: string, description: string, items: array<string, int>}>
     */
    private function definitions(): array
    {
        return [
            [
                'slug' => 'miswak-1-person-kit',
                'name' => 'Единичен комплект',
                'badge' => 'НАЙ-ПОПУЛЯРЕН',
                'amount' => 29.99,
                'short_description' => 'Вземи пълна устна рутина.',
                'description' => <<<'TEXT'
                    Най-лесният начин да започнеш с Miswak - всичко в една кутия, готово за употреба от първия ден. Не се налага да купуваш пръчиците, калъфа и стъргалката поотделно и да се чудиш кое пасва на кое - комплектът вече е съставен за теб.

                    Защо е по-добре в комплект:
                    Пръчиците пътуват в собствения си бамбуков калъф, а стъргалката - в своя ленена торбичка, така че всичко остава чисто и сухо в чантата или на бюрото между употреби. Медната стъргалка довършва рутината - Miswak за зъбите, стъргалката за езика. И защото комплектът е съставен наведнъж, излиза по-изгодно, отколкото да вземеш всяко нещо поотделно.

                    Как да използваш комплекта:
                    Обели около 1 см от върха на пръчицата, сдъвчи леко, докато влакната се разделят в четина, и почисти зъбите с кръгови движения за 2-3 минути. Прекарай стъргалката по езика с едно движение отпред назад. Прибери пръчицата обратно в калъфа, а стъргалката - в нейната ленена торбичка.

                    Съхранение:
                    Дръж калъфа и торбичката на сухо място между употреби. Когато върхът на пръчицата се износи, отрежи го и оголи следващия сантиметър за свежа четина.
                    TEXT,
                'items' => [
                    'MISWAK-5' => 1,
                    'MISWAK-CASE-1' => 1,
                    'SCRAPER-1' => 1,
                ],
            ],
            [
                'slug' => 'miswak-2-person-kit',
                'name' => 'Комплект за двама',
                'badge' => 'НАЙ-ИЗГОДЕН',
                'amount' => 54.99,
                'short_description' => 'Вземи пълна устна рутина за теб и твой близък.',
                'description' => <<<'TEXT'
                    Пълна Miswak рутина за двама, готова от първия ден - без да купувате пръчици, калъфи и стъргалки поотделно за всеки. Комплектът е съставен така, че всеки получава своя собствена стъргалка и калъф - никой не дели нищо.

                    Защо е по-добре в комплект:
                    Два отделни калъфа и две стъргалки означават, че всеки пази своите пръчици отделно, хигиенично, в собствената си чанта или на бюрото си. Пръчиците стигат за по-дълго за двама, а взети заедно в комплект излизат по-изгодно, отколкото да купувате всичко поотделно - за себе си и за човека до вас.

                    Как да използвате комплекта:
                    Всеки обелва около 1 см от върха на своята пръчица, сдъвква леко, докато влакната се разделят в четина, и почиства зъбите с кръгови движения за 2-3 минути. Стъргалката минава по езика с едно движение отпред назад. Пръчицата се прибира обратно в собствения калъф между употреби.

                    Съхранение:
                    Дръжте калъфите и торбичките на сухо място. Когато върхът на пръчицата се износи, отрежете го и оголете следващия сантиметър за свежа четина.
                    TEXT,
                'items' => [
                    'MISWAK-10' => 1,
                    'MISWAK-CASE-1' => 2,
                    'SCRAPER-1' => 2,
                ],
            ],
        ];
    }
}
