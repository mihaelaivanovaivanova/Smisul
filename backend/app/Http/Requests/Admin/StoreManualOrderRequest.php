<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A quick phone/in-person sale entered straight into the admin panel — no
 * email, no cart, one line item. Deliberately office/locker delivery only
 * (no "address" option, unlike checkout's PlaceOrderRequest): a full
 * settlement/street-address picker isn't worth the form complexity for this
 * flow, and every real shipment still needs a pickup point either way.
 * shipping_price/cod_fee are trusted as submitted rather than recomputed
 * here — the admin form prefills both from the same public
 * shipping-methods/payment-methods endpoints checkout uses, then lets the
 * admin edit or zero either one out before submitting.
 */
class StoreManualOrderRequest extends FormRequest
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
            'customer_first_name' => ['required', 'string', 'max:100'],
            'customer_last_name' => ['required', 'string', 'max:100'],
            // Same shape the checkout PhoneField always submits (+359 plus
            // the 9-digit local number) — a real carrier shipment needs a
            // valid Bulgarian mobile number regardless of how the order
            // came in.
            'customer_phone' => ['required', 'string', 'regex:/^\+3598\d{8}$/'],

            'shipping_carrier' => ['required', 'string', Rule::in(array_column(ShippingCarrier::active(), 'value'))],
            'shipping_delivery_type' => ['required', 'string', Rule::in([
                ShippingDeliveryType::Office->value,
                ShippingDeliveryType::Locker->value,
            ])],
            'shipping_office_id' => ['required', 'string', 'max:100'],
            'shipping_office_name' => ['required', 'string', 'max:255'],
            'shipping_office_city' => ['required', 'string', 'max:100'],
            'shipping_office_address' => ['required', 'string', 'max:255'],
            'shipping_price' => ['required', 'numeric', 'min:0'],

            // Cash on delivery only works for Speedy - its courier collects
            // cash in person at hand-off, which BOX NOW's locker network has
            // no equivalent for (see PaymentMethod's own docblock /
            // PaymentService::availablePaymentMethods()).
            'payment_method' => [
                'required',
                'string',
                Rule::in(['cash_on_delivery', 'paid']),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === 'cash_on_delivery' && $this->input('shipping_carrier') !== ShippingCarrier::Speedy->value) {
                        $fail('Cash on delivery is only available for Speedy.');
                    }
                },
            ],
            'cod_fee' => ['required', 'numeric', 'min:0'],

            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
