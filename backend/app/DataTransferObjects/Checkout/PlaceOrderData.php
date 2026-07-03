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
        public ?string $deliveryNotes,
        public string $shippingCarrier,
        public array $legalDocumentIds,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            customer: CustomerData::fromArray($data['customer']),
            address: ShippingAddressData::fromArray($data['address']),
            deliveryNotes: isset($data['delivery_notes']) ? (string) $data['delivery_notes'] : null,
            shippingCarrier: (string) $data['shipping_carrier'],
            legalDocumentIds: array_map('intval', $data['legal_document_ids']),
        );
    }
}
