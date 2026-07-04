<?php

namespace App\Http\Requests\Checkout;

use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    /**
     * Open to guests and authenticated customers alike — ownership/ambient
     * cart resolution happens in the controller, exactly like CartController.
     */
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
            'customer.first_name' => ['required', 'string', 'max:100'],
            'customer.last_name' => ['required', 'string', 'max:100'],
            'customer.email' => ['required', 'string', 'email', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:30'],
            'customer.company' => ['nullable', 'string', 'max:150'],
            'customer.vat_number' => ['nullable', 'string', 'max:50'],

            'address.country' => ['required', 'string', 'max:100'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.postal_code' => ['required', 'string', 'max:20'],
            'address.address_line' => ['required', 'string', 'max:255'],
            'address.apartment' => ['nullable', 'string', 'max:100'],

            'billing_same_as_shipping' => ['sometimes', 'boolean'],
            'billing_address' => ['required_if:billing_same_as_shipping,false', 'array'],
            'billing_address.country' => ['required_if:billing_same_as_shipping,false', 'string', 'max:100'],
            'billing_address.city' => ['required_if:billing_same_as_shipping,false', 'string', 'max:100'],
            'billing_address.postal_code' => ['required_if:billing_same_as_shipping,false', 'string', 'max:20'],
            'billing_address.address_line' => ['required_if:billing_same_as_shipping,false', 'string', 'max:255'],
            'billing_address.apartment' => ['nullable', 'string', 'max:100'],

            'delivery_notes' => ['nullable', 'string', 'max:1000'],

            'shipping_carrier' => ['required', 'string', Rule::in(array_column(ShippingCarrier::cases(), 'value'))],
            'shipping_delivery_type' => ['required', 'string', Rule::in(array_column(ShippingDeliveryType::cases(), 'value'))],
            'shipping_office_id' => ['required_unless:shipping_delivery_type,address', 'nullable', 'string', 'max:100'],
            'shipping_office_name' => ['required_unless:shipping_delivery_type,address', 'nullable', 'string', 'max:255'],

            'legal_document_ids' => ['required', 'array', 'min:1'],
            'legal_document_ids.*' => ['integer', 'exists:legal_documents,id'],
        ];
    }
}
