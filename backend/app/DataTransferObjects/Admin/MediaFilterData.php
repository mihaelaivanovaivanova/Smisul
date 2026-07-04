<?php

namespace App\DataTransferObjects\Admin;

use App\Models\Category;
use App\Models\Product;

final readonly class MediaFilterData
{
    public function __construct(
        public ?string $search = null,
        public ?string $mediableType = null,
        public ?string $mimeType = null,
        public int $page = 1,
        public int $perPage = 24,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            mediableType: self::resolveMediableType($data['type'] ?? null),
            mimeType: $data['mime_type'] ?? null,
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 24), 100),
        );
    }

    /**
     * The API deals in short aliases ("product"/"category") rather than
     * leaking the mediable_type FQCN stored in the media table.
     */
    private static function resolveMediableType(?string $alias): ?string
    {
        return match ($alias) {
            'product' => Product::class,
            'category' => Category::class,
            default => null,
        };
    }
}
