<?php

namespace App\DataTransferObjects\Admin;

final readonly class LogFilterData
{
    public function __construct(
        public string $type,
        public int $page = 1,
        public int $perPage = 25,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 25), 100),
        );
    }
}
