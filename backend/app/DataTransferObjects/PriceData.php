<?php

namespace App\DataTransferObjects;

final readonly class PriceData
{
    public function __construct(
        public string $currency,
        public float $amount,
        public ?float $compareAtAmount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'],
            amount: (float) $data['amount'],
            compareAtAmount: isset($data['compare_at_amount']) ? (float) $data['compare_at_amount'] : null,
        );
    }
}
