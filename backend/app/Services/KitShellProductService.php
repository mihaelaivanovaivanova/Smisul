<?php

namespace App\Services;

use App\DataTransferObjects\PriceData;
use App\DataTransferObjects\ProductVariantData;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Each kit (ProductBundle) is backed by a lightweight "shell" catalog
 * entry - one Product (slug = the kit's own slug) with one default
 * ProductVariant, Price and Inventory - purely so that:
 *  - the kit lists in the ordinary product catalog (GET /products), and
 *  - a kit order line can carry a real product_variant_id (see
 *    OrderService::placeOrder()), which is all the review/reminder system
 *    needs to treat a kit purchase like any other product purchase.
 *
 * The shell is NEVER itself sold: kits are only bought through
 * /cart/bundles (BundleService), CartService::addItem() refuses a shell
 * variant outright, and the shell's own stock (a nominal quantity plus
 * backorders_allowed, so it always reads "in stock") is never reserved,
 * released, or decremented by anything - OrderService's bundle branches
 * act on the kit's real component variants only.
 *
 * Authority is one-way: the kit's name/slug/status/price are synced onto
 * the shell on every save, but the shell's short_description/description
 * are only ever written when the shell is first created - after that they
 * belong to the admin, edited through /admin/products like any other
 * product's content, and are never clobbered by a later kit save or
 * seeder re-run.
 */
class KitShellProductService
{
    /**
     * Cosmetic only - see the class docblock.
     */
    public const NOMINAL_STOCK = 9999;

    public function __construct(
        private readonly ProductVariantService $variants,
        private readonly PriceService $prices,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Uppercase like every real catalog SKU (MISWAK-5, SCRAPER-1, ...), and
     * the KIT-/-SHELL wrapping keeps it out of the real catalog's namespace.
     * Fixed at creation time - a later slug change doesn't rename it, since
     * the shell is found via product_bundles.shell_product_variant_id.
     */
    public static function skuFor(ProductBundle $bundle): string
    {
        return 'KIT-'.Str::upper($bundle->slug).'-SHELL';
    }

    /**
     * Creates the kit's shell if it has none yet, otherwise syncs the
     * kit's name/slug/status/price onto it. $shortDescription/$description
     * are used only when the shell is created (see the class docblock).
     */
    public function sync(ProductBundle $bundle, ?string $shortDescription = null, ?string $description = null): ProductVariant
    {
        return DB::transaction(function () use ($bundle, $shortDescription, $description) {
            $variant = $this->existingShell($bundle);

            if ($variant === null) {
                $variant = $this->createShell($bundle, $shortDescription, $description);
            } else {
                $this->syncShell($bundle, $variant);
            }

            $this->syncPrice($bundle, $variant);

            if ($bundle->shell_product_variant_id !== $variant->id) {
                $bundle->forceFill(['shell_product_variant_id' => $variant->id])->save();
            }

            return $variant->refresh();
        });
    }

    /**
     * A deleted kit's shell is archived, not deleted - its product's
     * reviews and its historical order lines stay intact, and an archived
     * product neither lists publicly nor passes isVariantPurchasable().
     */
    public function archive(ProductBundle $bundle): void
    {
        $product = $this->existingShell($bundle)?->product;

        $product?->update(['status' => ProductStatus::Archived]);
    }

    private function existingShell(ProductBundle $bundle): ?ProductVariant
    {
        $variant = $bundle->shell_product_variant_id !== null
            ? ProductVariant::withTrashed()->find($bundle->shell_product_variant_id)
            : null;

        // Re-adopt a shell whose link was lost (e.g. a re-seed after the
        // column was nulled) - but only one that really is this kit's
        // shell, never a real catalog variant that happens to share the
        // SKU (that case falls through to createShell(), whose
        // ensureSkuIsUnique() refuses it with DuplicateSkuException).
        if ($variant === null) {
            $candidate = ProductVariant::withTrashed()->where('sku', self::skuFor($bundle))->first();
            $candidateProduct = $candidate !== null ? Product::withTrashed()->find($candidate->product_id) : null;

            $variant = $candidateProduct?->slug === $bundle->slug ? $candidate : null;
        }

        if ($variant === null) {
            return null;
        }

        // The kit is the authority over its shell - a shell soft-deleted
        // through /admin/products is brought back rather than recreated
        // (its slug and SKU are both still reserved withTrashed()).
        if ($variant->trashed()) {
            $variant->restore();
        }

        $product = Product::withTrashed()->find($variant->product_id);

        if ($product?->trashed()) {
            $product->restore();
        }

        return $variant->load('product');
    }

    private function createShell(ProductBundle $bundle, ?string $shortDescription, ?string $description): ProductVariant
    {
        $status = $this->productStatusFor($bundle);

        $product = Product::create([
            'slug' => $bundle->slug,
            'name' => $bundle->name,
            'short_description' => $shortDescription,
            'description' => $description,
            'status' => $status,
            'published_at' => $status === ProductStatus::Published ? now() : null,
        ]);

        $variant = $this->variants->create($product, new ProductVariantData(
            sku: self::skuFor($bundle),
            name: $bundle->name,
            packSize: 1,
            isDefault: true,
        ));

        // backorders_allowed first, so the quantity change below never
        // looks like an out-of-stock -> in-stock transition (no
        // ProductBackInStock event for a product nobody can be waiting on).
        $inventory = $variant->inventory()->firstOrFail();
        $inventory->update(['backorders_allowed' => true]);
        $this->inventory->setQuantityOnHand($inventory, self::NOMINAL_STOCK);

        return $variant;
    }

    private function syncShell(ProductBundle $bundle, ProductVariant $variant): void
    {
        $product = $variant->product;
        $status = $this->productStatusFor($bundle);

        $product->update([
            'slug' => $bundle->slug,
            'name' => $bundle->name,
            'status' => $status,
            'published_at' => $status === ProductStatus::Published ? ($product->published_at ?? now()) : $product->published_at,
        ]);

        $variant->update(['name' => $bundle->name]);
    }

    /**
     * Only written when it actually differs, so re-saving a kit (or
     * re-running the seeder) doesn't add a PriceHistory row each time.
     */
    private function syncPrice(ProductBundle $bundle, ProductVariant $variant): void
    {
        $current = $variant->prices()->where('currency', $bundle->currency)->first();

        if ($current !== null && (float) $current->amount === (float) $bundle->amount) {
            return;
        }

        $this->prices->setPrice($variant, new PriceData(
            currency: $bundle->currency,
            amount: (float) $bundle->amount,
        ), reason: 'Synced from kit price');
    }

    private function productStatusFor(ProductBundle $bundle): ProductStatus
    {
        return $bundle->isActive() ? ProductStatus::Published : ProductStatus::Draft;
    }
}
