<?php

namespace App\DataTransferObjects;

final readonly class ProductFilterData
{
    public function __construct(
        public ?string $search = null,
        public ?string $categorySlug = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public string $sort = 'newest',
        public int $page = 1,
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            categorySlug: $data['category'] ?? null,
            minPrice: isset($data['min_price']) ? (float) $data['min_price'] : null,
            maxPrice: isset($data['max_price']) ? (float) $data['max_price'] : null,
            sort: $data['sort'] ?? 'newest',
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 15), 100),
        );
    }
}
