<?php

namespace App\DataTransferObjects\Checkout;

final readonly class CustomerData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public ?string $company,
        public ?string $vatNumber,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: (string) $data['first_name'],
            lastName: (string) $data['last_name'],
            email: (string) $data['email'],
            phone: (string) $data['phone'],
            company: isset($data['company']) ? (string) $data['company'] : null,
            vatNumber: isset($data['vat_number']) ? (string) $data['vat_number'] : null,
        );
    }
}
