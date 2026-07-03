<?php

namespace App\DataTransferObjects;

use App\Enums\PromotionType;
use Carbon\CarbonImmutable;

final readonly class PromotionData
{
    /**
     * @param  list<int>  $productIds
     * @param  list<int>  $categoryIds
     */
    public function __construct(
        public string $name,
        public PromotionType $type,
        public float $value,
        public ?string $description = null,
        public ?string $code = null,
        public ?CarbonImmutable $startsAt = null,
        public ?CarbonImmutable $endsAt = null,
        public ?int $usageLimit = null,
        public bool $isActive = true,
        public array $productIds = [],
        public array $categoryIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: PromotionType::from($data['type']),
            value: (float) $data['value'],
            description: $data['description'] ?? null,
            code: $data['code'] ?? null,
            startsAt: isset($data['starts_at']) ? CarbonImmutable::parse($data['starts_at']) : null,
            endsAt: isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null,
            usageLimit: isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
            productIds: $data['product_ids'] ?? [],
            categoryIds: $data['category_ids'] ?? [],
        );
    }
}
