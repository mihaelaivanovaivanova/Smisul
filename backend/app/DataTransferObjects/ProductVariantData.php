<?php

namespace App\DataTransferObjects;

use App\Enums\VariantStatus;

final readonly class ProductVariantData
{
    public function __construct(
        public string $sku,
        public string $name,
        public int $packSize,
        public ?string $barcode = null,
        public bool $isDefault = false,
        public VariantStatus $status = VariantStatus::Active,
        public int $sortOrder = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sku: $data['sku'],
            name: $data['name'],
            packSize: (int) $data['pack_size'],
            barcode: $data['barcode'] ?? null,
            isDefault: (bool) ($data['is_default'] ?? false),
            status: isset($data['status']) ? VariantStatus::from($data['status']) : VariantStatus::Active,
            sortOrder: (int) ($data['sort_order'] ?? 0),
        );
    }
}
