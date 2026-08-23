<?php

namespace App\DataTransferObjects\Admin;

final readonly class ComplaintFilterData
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public string $sort = 'submitted_desc',
        public int $page = 1,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: $data['status'] ?? null,
            sort: $data['sort'] ?? 'submitted_desc',
            page: (int) ($data['page'] ?? 1),
        );
    }
}
