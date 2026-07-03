<?php

namespace App\DataTransferObjects;

final readonly class CategoryData
{
    public function __construct(
        public string $name,
        public ?int $parentId = null,
        public ?string $description = null,
        public bool $isActive = true,
        public int $sortOrder = 0,
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
        );
    }
}
