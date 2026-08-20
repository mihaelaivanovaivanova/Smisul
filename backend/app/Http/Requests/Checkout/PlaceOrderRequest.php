<?php

namespace App\Http\Requests\Checkout;

use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Services\PaymentService;
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
        // Validated against currently-*enabled* methods for the payload's
        // own shipping_carrier, not just any PaymentMethod case — cash on
        // delivery is only enabled for BOX NOW (see
        // PaymentService::availablePaymentMethods()), and a disabled
        // method must fail here with a clean 422, not reach
        // PaymentService::initiate() (which would also reject it, but
        // with a less specific error for the caller).
        $enabledMethods = array_map(
            fn ($method) => $method->value,
            app(PaymentService::class)->availablePaymentMethods(
                ShippingCarrier::tryFrom((string) $this->input('shipping_carrier')),
            ),
        );

        return [
            'payment_method' => ['sometimes', 'string', Rule::in($enabledMethods)],
            'stored_payment_method_id' => ['nullable', 'integer', 'exists:stored_payment_methods,id'],
            'customer.first_name' => ['required', 'string', 'max:100'],
            'customer.last_name' => ['required', 'string', 'max:100'],
            'customer.email' => ['required', 'string', 'email', 'max:255'],
            // Must match the frontend's PhoneField exactly: it always
            // submits the +359 prefix plus the 9-digit local number the
            // customer types (see frontend/src/utils/phone.ts).
            'customer.phone' => ['required', 'string', 'regex:/^\+3598\d{8}$/'],
            'customer.company' => ['nullable', 'string', 'max:150'],
            'customer.vat_number' => ['nullable', 'string', 'max:50'],

            // Only collected (and only meaningful) for home delivery — an
            // office/locker pickup has nowhere to put a street address, so
            // the form doesn't ask for one and sends these empty. 'nullable'
            // is required alongside required_if (not redundant with it):
            // ConvertEmptyStringsToNull turns that empty string into null
            // before validation runs, and without 'nullable' the 'string'
            // rule would reject the null even though it isn't required.
            'address.country' => ['nullable', 'required_if:shipping_delivery_type,address', 'string', 'max:100'],
            'address.city' => ['nullable', 'required_if:shipping_delivery_type,address', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'required_if:shipping_delivery_type,address', 'string', 'max:20'],
            'address.address_line' => ['nullable', 'required_if:shipping_delivery_type,address', 'string', 'max:255'],
            'address.apartment' => ['nullable', 'string', 'max:100'],

            'wants_invoice' => ['sometimes', 'boolean'],
            'billing_same_as_shipping' => ['sometimes', 'boolean'],
            // Billing address only exists to support an invoice, so it's
            // never required unless wants_invoice is set — and even then,
            // "same as shipping" only means something when there IS a
            // shipping address to copy: for office/locker pickup, billing
            // details are always collected on their own, regardless of what
            // that flag says (the frontend forces it false in that case,
            // but this closes the gap for a direct API call that doesn't).
            'billing_address' => ['nullable', Rule::requiredIf(fn () => $this->billingAddressRequired()), 'array'],
            'billing_address.country' => ['nullable', Rule::requiredIf(fn () => $this->billingAddressRequired()), 'string', 'max:100'],
            'billing_address.city' => ['nullable', Rule::requiredIf(fn () => $this->billingAddressRequired()), 'string', 'max:100'],
            'billing_address.postal_code' => ['nullable', Rule::requiredIf(fn () => $this->billingAddressRequired()), 'string', 'max:20'],
            'billing_address.address_line' => ['nullable', Rule::requiredIf(fn () => $this->billingAddressRequired()), 'string', 'max:255'],
            'billing_address.apartment' => ['nullable', 'string', 'max:100'],

            'delivery_notes' => ['nullable', 'string', 'max:1000'],

            'shipping_carrier' => ['required', 'string', Rule::in(array_column(ShippingCarrier::cases(), 'value'))],
            'shipping_delivery_type' => ['required', 'string', Rule::in(array_column(ShippingDeliveryType::cases(), 'value'))],
            'shipping_office_id' => ['required_unless:shipping_delivery_type,address', 'nullable', 'string', 'max:100'],
            'shipping_office_name' => ['required_unless:shipping_delivery_type,address', 'nullable', 'string', 'max:255'],
            // Used to store the pickup point's own address as the order's
            // shipping address instead of the (still-collected, e.g. for
            // billing) free-text address fields above — see OrderService.
            'shipping_office_city' => ['required_unless:shipping_delivery_type,address', 'nullable', 'string', 'max:100'],
            'shipping_office_address' => ['required_unless:shipping_delivery_type,address', 'nullable', 'string', 'max:255'],

            'legal_document_ids' => ['required', 'array', 'min:1'],
            'legal_document_ids.*' => ['integer', 'exists:legal_documents,id'],
        ];
    }

    /**
     * Mirrors PlaceOrderData::fromArray()'s own `?? true` default for a
     * missing billing_same_as_shipping — $request->boolean() defaults an
     * absent key to false instead, which would wrongly demand a separate
     * billing address on every payload that omits the flag.
     */
    private function billingAddressRequired(): bool
    {
        if (! $this->boolean('wants_invoice')) {
            return false;
        }

        return ! $this->boolean('billing_same_as_shipping', true) || $this->input('shipping_delivery_type') !== 'address';
    }
}
