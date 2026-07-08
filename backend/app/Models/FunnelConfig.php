<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $is_enabled
 * @property ?int $product_id
 * @property ?array<int, array<string, mixed>> $packages
 */
class FunnelConfig extends Model
{
    protected $fillable = ['is_enabled', 'product_id', 'packages'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'packages' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * A single row governs the whole feature, so callers never need to
     * think about which row to load — this is the only entry point.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['is_enabled' => false]);
    }
}
