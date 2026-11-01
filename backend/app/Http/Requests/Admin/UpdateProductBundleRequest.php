<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use App\Enums\VariantStatus;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductBundleRequest extends FormRequest
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
        /** @var ProductBundle|null $bundle */
        $bundle = $this->route('bundle');
        $shellProductId = $bundle?->shell_product_variant_id !== null
            ? ProductVariant::withTrashed()->whereKey($bundle->shell_product_variant_id)->value('product_id')
            : null;

        return [
            // Also unique among products, other than this kit's own shell
            // (see KitShellProductService), which follows the kit's slug.
            'slug' => [
                'sometimes', 'string', 'max:191', 'alpha_dash',
                Rule::unique('product_bundles', 'slug')->ignore($bundle?->id),
                Rule::unique('products', 'slug')->ignore($shellProductId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:255'],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:99999999.99'],
            'status' => ['sometimes', Rule::enum(VariantStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            ...StoreProductBundleRequest::itemRules(required: false),
        ];
    }
}
