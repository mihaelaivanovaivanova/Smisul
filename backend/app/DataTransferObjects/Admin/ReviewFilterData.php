<?php

namespace App\DataTransferObjects\Admin;

final readonly class ReviewFilterData
{
    public function __construct(
        public ?string $status = null,
        public ?int $productId = null,
        public ?int $rating = null,
        public ?string $search = null,
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
            status: $data['status'] ?? null,
            productId: isset($data['product_id']) ? (int) $data['product_id'] : null,
            rating: isset($data['rating']) ? (int) $data['rating'] : null,
            search: $data['search'] ?? null,
            sort: $data['sort'] ?? 'newest',
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 15), 100),
        );
    }
}
