<?php

namespace App\DataTransferObjects\Checkout;

final readonly class PlaceOrderData
{
    /**
     * @param  list<int>  $legalDocumentIds
     */
    public function __construct(
        public CustomerData $customer,
        public ShippingAddressData $address,
        public bool $billingSameAsShipping,
        public ?ShippingAddressData $billingAddress,
        public ?string $deliveryNotes,
        public string $shippingCarrier,
        public string $shippingDeliveryType,
        public ?string $shippingOfficeId,
        public ?string $shippingOfficeName,
        public array $legalDocumentIds,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $billingSameAsShipping = (bool) ($data['billing_same_as_shipping'] ?? true);

        return new self(
            customer: CustomerData::fromArray($data['customer']),
            address: ShippingAddressData::fromArray($data['address']),
            billingSameAsShipping: $billingSameAsShipping,
            billingAddress: $billingSameAsShipping ? null : ShippingAddressData::fromArray($data['billing_address']),
            deliveryNotes: isset($data['delivery_notes']) ? (string) $data['delivery_notes'] : null,
            shippingCarrier: (string) $data['shipping_carrier'],
            shippingDeliveryType: (string) $data['shipping_delivery_type'],
            shippingOfficeId: isset($data['shipping_office_id']) ? (string) $data['shipping_office_id'] : null,
            shippingOfficeName: isset($data['shipping_office_name']) ? (string) $data['shipping_office_name'] : null,
            legalDocumentIds: array_map('intval', $data['legal_document_ids']),
        );
    }
}
