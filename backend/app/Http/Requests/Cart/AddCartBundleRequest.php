<?php

namespace App\Http\Requests\Cart;

use App\Services\CartPricingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adds a kit (ProductBundle) to the cart as a single line - see
 * BundleService::addBundleToCart(). Same guest/authenticated open access
 * as AddCartItemRequest.
 */
class AddCartBundleRequest extends FormRequest
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
            'bundle_id' => ['required', 'integer', Rule::exists('product_bundles', 'id')],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.CartPricingService::MAX_QUANTITY_PER_ITEM],
        ];
    }
}
