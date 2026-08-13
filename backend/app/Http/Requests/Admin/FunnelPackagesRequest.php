<?php

namespace App\Http\Requests\Admin;

use App\Models\FunnelConfig;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class FunnelPackagesRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'packages' => ['required', 'array', 'size:4'],
            'packages.*.variant_id' => ['required', 'integer'],
            'packages.*.badge' => ['required', 'string', 'max:255'],
            'packages.*.detail' => ['required', 'string', 'max:255'],
            'packages.*.value_label' => ['required', 'string', 'max:255'],
            'packages.*.button_text' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $productId = $this->input('product_id');
            $packages = $this->input('packages', []);

            if (! is_array($packages) || $productId === null) {
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
