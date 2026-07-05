<?php

namespace App\DataTransferObjects;

use App\Enums\ProductStatus;

final readonly class ProductData
{
    /**
     * @param  list<int>  $categoryIds
     */
    public function __construct(
        public string $name,
        public ?string $shortDescription = null,
        public ?string $description = null,
        public ProductStatus $status = ProductStatus::Draft,
        public array $categoryIds = [],
        /** Applied to the product's default variant — see ProductService::applyDefaultVariantValues(). */
        public ?int $quantity = null,
        public ?float $price = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            shortDescription: $data['short_description'] ?? null,
            description: $data['description'] ?? null,
            status: isset($data['status']) ? ProductStatus::from($data['status']) : ProductStatus::Draft,
            categoryIds: $data['category_ids'] ?? [],
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : null,
            price: isset($data['price']) ? (float) $data['price'] : null,
        );
    }
}
