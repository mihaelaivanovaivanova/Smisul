<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Exceptions\Cart\BundleInUseException;
use App\Models\ProductBundle;
use Illuminate\Support\Facades\DB;

/**
 * Admin CRUD for kits (ProductBundle). Price/name/status/sort changes are
 * always allowed - a kit line's price is read live until the order is
 * placed, exactly like an ordinary variant's. Component changes and
 * deletion are refused while any cart or unconfirmed order still holds
 * stock against the kit's current components (see BundleInUseException).
 *
 * Every create/update also creates or syncs the kit's backing catalog
 * "shell" (see KitShellProductService); delete archives it.
 */
class ProductBundleAdminService
{
    public function __construct(
        private readonly BundleService $bundles,
        private readonly KitShellProductService $shells,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProductBundle
    {
        return DB::transaction(function () use ($data) {
            $bundle = ProductBundle::create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'badge' => $data['badge'] ?? null,
                'currency' => $data['currency'] ?? Currency::EUR->value,
                'amount' => $data['amount'],
                'status' => $data['status'] ?? VariantStatus::Active->value,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncItems($bundle, $data['items']);

            // short_description/description are optional here (the admin
            // kit form doesn't carry them) - used only to seed the shell's
            // content on creation, then edited via /admin/products.
            $this->shells->sync($bundle, $data['short_description'] ?? null, $data['description'] ?? null);

            return $bundle->fresh(ProductBundle::COMPONENT_EAGER_LOAD);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProductBundle $bundle, array $data): ProductBundle
    {
        return DB::transaction(function () use ($bundle, $data) {
            $bundle = ProductBundle::whereKey($bundle->id)->with('items')->lockForUpdate()->firstOrFail();

            if (array_key_exists('items', $data) && $this->itemsDiffer($bundle, $data['items'])) {
                if ($this->bundles->hasOutstandingReservations($bundle)) {
                    throw BundleInUseException::forSlug($bundle->slug);
                }

                $this->syncItems($bundle, $data['items']);
            }

            $bundle->update(collect($data)->only(['slug', 'name', 'badge', 'currency', 'amount', 'status', 'sort_order'])->all());

            // Name/slug/status/price only - never the shell's
            // short_description/description (see KitShellProductService).
            // Also creates the shell for a kit that predates it.
            $this->shells->sync($bundle);

            return $bundle->fresh(ProductBundle::COMPONENT_EAGER_LOAD);
        });
    }

    public function delete(ProductBundle $bundle): void
    {
        DB::transaction(function () use ($bundle) {
            if ($this->bundles->hasOutstandingReservations($bundle)) {
                throw BundleInUseException::forSlug($bundle->slug);
            }

            // Historical (confirmed/cancelled) order lines keep their
            // bundle_name/sku/price snapshots - order_items.bundle_id is
            // nullOnDelete. The shell is archived rather than deleted, so
            // its reviews and those lines' product_variant_id survive too.
            $this->shells->archive($bundle);
            $bundle->delete();
        });
    }

    /**
     * @param  list<array{product_variant_id: int|string, quantity: int|string}>  $items
     */
    private function syncItems(ProductBundle $bundle, array $items): void
    {
        $bundle->items()->delete();

        foreach ($items as $item) {
            $bundle->items()->create([
                'product_variant_id' => (int) $item['product_variant_id'],
                'quantity' => (int) $item['quantity'],
            ]);
        }
    }

    /**
     * @param  list<array{product_variant_id: int|string, quantity: int|string}>  $items
     */
    private function itemsDiffer(ProductBundle $bundle, array $items): bool
    {
        $current = $bundle->items->mapWithKeys(fn ($item) => [$item->product_variant_id => $item->quantity])->sortKeys()->all();
        $requested = collect($items)->mapWithKeys(fn ($item) => [(int) $item['product_variant_id'] => (int) $item['quantity']])->sortKeys()->all();

        return $current !== $requested;
    }
}
