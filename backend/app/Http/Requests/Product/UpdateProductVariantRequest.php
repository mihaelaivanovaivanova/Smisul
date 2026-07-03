<?php

namespace App\Http\Requests\Product;

use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends StoreProductVariantRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->ignore($this->route('variant')),
            ],
        ]);
    }
}
