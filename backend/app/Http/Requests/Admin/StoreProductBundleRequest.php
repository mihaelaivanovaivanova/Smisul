<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Services\CartPricingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Also unique among products: the kit's catalog shell (see
            // KitShellProductService) takes this same slug.
            'slug' => ['required', 'string', 'max:191', 'alpha_dash', Rule::unique('product_bundles', 'slug'), Rule::unique('products', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:255'],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'status' => ['sometimes', Rule::enum(VariantStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            ...self::itemRules(required: true),
        ];
    }

    /**
     * Shared with UpdateProductBundleRequest. A component may appear only
     * once per kit (distinct) - product_bundle_items is unique on
     * (product_bundle_id, product_variant_id); a kit needing two of
     * something just sets that component's quantity to 2.
     *
     * @return array<string, mixed>
     */
    public static function itemRules(bool $required): array
    {
        return [
            'items' => [$required ? 'required' : 'sometimes', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('product_variants', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.CartPricingService::MAX_QUANTITY_PER_ITEM],
        ];
    }
}
