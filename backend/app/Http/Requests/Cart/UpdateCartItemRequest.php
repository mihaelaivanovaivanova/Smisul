<?php

namespace App\Http\Requests\Cart;

use App\Services\CartPricingService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // See AddCartItemRequest — ownership is enforced by CartService
        // scoping the lookup through the resolved cart, not here.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartPricingService::MAX_QUANTITY_PER_ITEM],
        ];
    }
}
