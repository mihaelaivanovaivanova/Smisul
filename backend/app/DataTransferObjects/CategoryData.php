<?php

namespace App\DataTransferObjects;

final readonly class CategoryData
{
    /**
     * @param  array<string, mixed>|null  $seo  Only present when the request actually sent a `seo` key - see CategoryService::update(), which leaves the existing Seo row untouched otherwise rather than wiping it back to nulls.
     */
    public function __construct(
        public string $name,
        public ?int $parentId = null,
        public ?string $description = null,
        public bool $isActive = true,
        public int $sortOrder = 0,
        public ?array $seo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            description: $data['description'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            seo: $data['seo'] ?? null,
        );
    }
}
