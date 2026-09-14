<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single ad-angle landing page ("whitening", "fresh-breath", ...) layered
 * on top of the base funnel (see FunnelConfig). product_id/packages are
 * nullable overrides — null means "inherit the base funnel's product and
 * packages" (see FunnelService::resolveProductId()/resolvePackages()).
 *
 * @property string $slug
 * @property string $name
 * @property ?int $product_id
 * @property ?array<int, array<string, mixed>> $packages
 * @property bool $is_active
 * @property ?string $meta_title
 * @property ?string $meta_description
 */
class FunnelVariant extends Model
{
    protected $fillable = ['slug', 'name', 'product_id', 'packages', 'is_active', 'meta_title', 'meta_description'];

    protected function casts(): array
    {
        return [
            'packages' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
