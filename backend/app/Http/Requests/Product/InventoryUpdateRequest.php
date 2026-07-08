<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class InventoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'backorders_allowed' => ['sometimes', 'boolean'],
        ];
    }
}
