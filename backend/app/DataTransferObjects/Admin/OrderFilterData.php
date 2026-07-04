<?php

namespace App\DataTransferObjects\Admin;

final readonly class OrderFilterData
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public string $sort = 'newest',
        public int $page = 1,
        public int $perPage = 15,
        public ?int $userId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: $data['status'] ?? null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            sort: $data['sort'] ?? 'newest',
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 15), 100),
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
        );
    }
}
