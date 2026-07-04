<?php

namespace App\DataTransferObjects\Admin;

final readonly class CustomerFilterData
{
    public function __construct(
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
            search: $data['search'] ?? null,
            sort: $data['sort'] ?? 'newest',
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 15), 100),
        );
    }
}
