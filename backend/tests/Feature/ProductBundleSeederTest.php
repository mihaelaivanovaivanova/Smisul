<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use Database\Seeders\FunnelSeeder;
use Database\Seeders\MiswakAccessoriesSeeder;
use Database\Seeders\ProductBundleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductBundleSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_both_kits_from_the_real_existing_variants_and_is_safe_to_rerun(): void
    {
        Storage::fake('public');

        $this->seed(FunnelSeeder::class);
        $this->seed(MiswakAccessoriesSeeder::class);
        $this->seed(ProductBundleSeeder::class);
        $this->seed(ProductBundleSeeder::class);

        $this->assertSame(2, ProductBundle::count());

        $components = fn (string $slug) => ProductBundle::where('slug', $slug)->firstOrFail()
            ->items()->with('productVariant')->get()
            ->mapWithKeys(fn ($item) => [$item->productVariant->sku => $item->quantity])
            ->sortKeys()
            ->all();

        $onePerson = ProductBundle::where('slug', 'miswak-1-person-kit')->firstOrFail();
        $this->assertSame('29.99', $onePerson->amount);
        $this->assertSame(['MISWAK-5' => 1, 'MISWAK-CASE-1' => 1, 'SCRAPER-1' => 1], $components('miswak-1-person-kit'));

        $twoPerson = ProductBundle::where('slug', 'miswak-2-person-kit')->firstOrFail();
        $this->assertSame('54.99', $twoPerson->amount);
        $this->assertSame(['MISWAK-10' => 1, 'MISWAK-CASE-1' => 2, 'SCRAPER-1' => 2], $components('miswak-2-person-kit'));

        // Live separate-purchase totals at the seeded prices - the Miswak
        // pack's own price (17.99 / 34.49) is itself already a bulk
        // discount vs. buying that many single sticks, so the real
        // "bought separately" total uses the 1-stick sibling's price
        // (4.43) x pack size instead (see ProductBundle::retailValue()).
        $onePerson->load(ProductBundle::COMPONENT_EAGER_LOAD);
        $twoPerson->load(ProductBundle::COMPONENT_EAGER_LOAD);
        $this->assertSame(34.93, $onePerson->retailValue('EUR'));   // 5x4.29 + 6.49 + 6.99
        $this->assertSame(69.86, $twoPerson->retailValue('EUR'));   // 10x4.29 + 2x6.49 + 2x6.99
    }

    #[Test]
    public function it_gives_each_kit_a_catalog_shell_with_seeded_content_that_a_rerun_never_clobbers(): void
    {
        Storage::fake('public');

        $this->seed(FunnelSeeder::class);
        $this->seed(MiswakAccessoriesSeeder::class);
        $realSkus = ProductVariant::pluck('sku')->all();
        $this->seed(ProductBundleSeeder::class);

        foreach ([
            'miswak-1-person-kit' => ['KIT-MISWAK-1-PERSON-KIT-SHELL', 'Вземи пълна устна рутина.', '29.99'],
            'miswak-2-person-kit' => ['KIT-MISWAK-2-PERSON-KIT-SHELL', 'Вземи пълна устна рутина за теб и твой близък.', '54.99'],
        ] as $slug => [$sku, $tagline, $amount]) {
            $bundle = ProductBundle::where('slug', $slug)->firstOrFail();
            $shell = ProductVariant::with('product')->findOrFail($bundle->shell_product_variant_id);

            $this->assertSame($sku, $shell->sku);
            $this->assertNotContains($sku, $realSkus);
            $this->assertSame($slug, $shell->product->slug);
            $this->assertSame($tagline, $shell->product->short_description);
            $this->assertStringContainsString("\n\nЗащо е по-добре в комплект:\n", $shell->product->description);
            $this->assertStringStartsNotWith(' ', $shell->product->description);
            $this->assertSame($amount, $shell->priceFor('EUR')->amount);
        }

        // Every real SKU still belongs to exactly one variant.
        $this->assertSame(count($realSkus) + 2, ProductVariant::count());
        $this->assertSame(ProductVariant::count(), ProductVariant::distinct()->count('sku'));

        // An admin's own edit survives a re-run, and no duplicate shell appears.
        $onePersonShell = Product::where('slug', 'miswak-1-person-kit')->firstOrFail();
        $onePersonShell->update(['short_description' => 'Admin tagline', 'description' => 'Admin copy']);

        $this->seed(ProductBundleSeeder::class);

        $this->assertSame('Admin tagline', $onePersonShell->fresh()->short_description);
        $this->assertSame('Admin copy', $onePersonShell->fresh()->description);
        $this->assertSame(count($realSkus) + 2, ProductVariant::count());
    }

    #[Test]
    public function the_upgrade_catch_up_creates_missing_shells_without_touching_admin_edited_kits(): void
    {
        Storage::fake('public');

        $this->seed(FunnelSeeder::class);
        $this->seed(MiswakAccessoriesSeeder::class);
        $this->seed(ProductBundleSeeder::class);

        // A site whose kits predate shells, with an admin-edited kit price.
        ProductBundle::query()->each(function (ProductBundle $bundle) {
            $shell = ProductVariant::findOrFail($bundle->shell_product_variant_id);
            $shell->prices()->delete();
            $shell->inventory()->delete();
            $productId = $shell->product_id;
            $shell->forceDelete();
            Product::withTrashed()->whereKey($productId)->forceDelete();
        });
        ProductBundle::where('slug', 'miswak-1-person-kit')->update(['amount' => 27.5, 'shell_product_variant_id' => null]);
        $this->assertSame(0, ProductBundle::whereNotNull('shell_product_variant_id')->count());

        (new ProductBundleSeeder)->ensureShells();

        $onePerson = ProductBundle::where('slug', 'miswak-1-person-kit')->firstOrFail();
        $this->assertSame('27.50', $onePerson->amount);
        $shell = ProductVariant::with('product')->findOrFail($onePerson->shell_product_variant_id);
        $this->assertSame('27.50', $shell->priceFor('EUR')->amount);
        $this->assertSame('Вземи пълна устна рутина.', $shell->product->short_description);
        $this->assertSame(0, ProductBundle::whereNull('shell_product_variant_id')->count());
    }
}
