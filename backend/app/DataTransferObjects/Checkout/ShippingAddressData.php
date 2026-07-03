<?php

namespace App\DataTransferObjects\Checkout;

final readonly class ShippingAddressData
{
    public function __construct(
        public string $country,
        public string $city,
        public string $postalCode,
        public string $addressLine,
        public ?string $apartment,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            country: (string) $data['country'],
            city: (string) $data['city'],
            postalCode: (string) $data['postal_code'],
            addressLine: (string) $data['address_line'],
            apartment: isset($data['apartment']) ? (string) $data['apartment'] : null,
        );
    }
}
