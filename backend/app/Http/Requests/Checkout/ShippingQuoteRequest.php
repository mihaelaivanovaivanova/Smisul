<?php

namespace App\Http\Requests\Checkout;

use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingQuoteRequest extends FormRequest
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
            'carrier' => ['required', 'string', Rule::in(array_column(ShippingCarrier::active(), 'value'))],
            'delivery_type' => ['required', 'string', Rule::in(array_column(ShippingDeliveryType::cases(), 'value'))],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
