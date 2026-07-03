<?php

namespace App\Services;

use App\DataTransferObjects\ProductVariantData;
use App\Exceptions\DuplicateSkuException;
use App\Models\Product;
use App\Models\ProductVariant;

class ProductVariantService
{
    public function create(Product $product, ProductVariantData $data): ProductVariant
    {
        $this->ensureSkuIsUnique($data->sku);

        $variant = $product->variants()->create([
            ...$this->baseAttributes($data),
            'is_default' => false,
        ]);

        // Every variant has exactly one inventory row (schema-enforced via a
        // unique constraint on product_variant_id) — create it up front so
        // no variant can exist without one to query against.
        $variant->inventory()->create([
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        if ($data->isDefault) {
            $this->makeDefault($variant);
        }

        return $variant->refresh();
    }

    public function update(ProductVariant $variant, ProductVariantData $data): ProductVariant
    {
        $this->ensureSkuIsUnique($data->sku, ignore: $variant);

        $variant->update($this->baseAttributes($data));

        if ($data->isDefault) {
            $this->makeDefault($variant);
        }

        return $variant->refresh();
    }

    public function delete(ProductVariant $variant): void
    {
        $variant->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseAttributes(ProductVariantData $data): array
    {
        return [
            'sku' => $data->sku,
            'name' => $data->name,
            'pack_size' => $data->packSize,
            'barcode' => $data->barcode,
            'status' => $data->status,
            'sort_order' => $data->sortOrder,
        ];
    }

    private function ensureSkuIsUnique(string $sku, ?ProductVariant $ignore = null): void
    {
        $query = ProductVariant::withTrashed()->where('sku', $sku);

        if ($ignore !== null) {
            $query->whereKeyNot($ignore->getKey());
        }

        if ($query->exists()) {
            throw DuplicateSkuException::forSku($sku);
        }
    }

    /**
     * Exactly one variant per product may be flagged default. This is an
     * application-level invariant (not a DB constraint — MySQL has no
     * portable partial-unique-index syntax for "unique where true"), so
     * it's enforced here rather than left to chance at the call sites.
     */
    private function makeDefault(ProductVariant $variant): void
    {
        ProductVariant::where('product_id', $variant->product_id)
            ->whereKeyNot($variant->getKey())
            ->update(['is_default' => false]);

        if (! $variant->is_default) {
            $variant->update(['is_default' => true]);
        }
    }
}
