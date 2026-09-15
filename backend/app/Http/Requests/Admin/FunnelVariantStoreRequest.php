<?php

namespace App\Http\Requests\Admin;

use App\Models\FunnelConfig;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class FunnelVariantStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', FunnelConfig::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Immutable after creation (see FunnelVariantUpdateRequest) —
            // it's both the public URL segment and the content_blocks key
            // prefix, so renaming it would orphan a live ad link and every
            // section override already saved under the old slug.
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'unique:funnel_variants,slug'],
            'name' => ['required', 'string', 'max:255'],
            // Null means "inherit the base funnel's product/packages" —
            // most angles sell the same product, just a different story.
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'packages' => ['nullable', 'array', 'size:4'],
            'packages.*.variant_id' => ['required_with:packages', 'integer'],
            'packages.*.badge' => ['required_with:packages', 'string', 'max:255'],
            'packages.*.detail' => ['required_with:packages', 'string', 'max:255'],
            'packages.*.value_label' => ['required_with:packages', 'string', 'max:255'],
            'packages.*.button_text' => ['required_with:packages', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $productId = $this->input('product_id');
            $packages = $this->input('packages');

            if (! is_array($packages)) {
                return;
            }

            if ($productId === null) {
                $validator->errors()->add('product_id', 'Pick a product before setting a package override, or clear the packages to inherit the base product too.');

                return;
            }

            $variantIds = ProductVariant::query()->where('product_id', $productId)->pluck('id');

            foreach ($packages as $index => $package) {
                $variantId = $package['variant_id'] ?? null;

                if ($variantId !== null && ! $variantIds->contains($variantId)) {
                    $validator->errors()->add(
                        "packages.{$index}.variant_id",
                        'The selected variant does not belong to the chosen product.',
                    );
                }
            }
        });
    }
}
