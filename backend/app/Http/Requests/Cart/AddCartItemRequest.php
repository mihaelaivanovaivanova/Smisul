<?php

namespace App\Http\Requests\Cart;

use App\Services\CartPricingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guests may add to their own (token-resolved) cart just like
        // authenticated users — there's no narrower permission to check
        // here. Ownership scoping happens in CartService via the resolved
        // Cart, not via a Policy (guests have no User to check against).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id')->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartPricingService::MAX_QUANTITY_PER_ITEM],
            // Just an intent signal from a specific UI entry point (the
            // cart drawer's bamboo-case cross-sell card) - CartService
            // re-validates eligibility server-side before ever honoring it,
            // never trusting this flag alone (see its isEligibleForUpsellPrice()).
            'is_upsell' => ['sometimes', 'boolean'],
        ];
    }
}
