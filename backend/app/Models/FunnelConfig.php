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
     *
     * Defaults a brand-new row (first call ever, e.g. right after a fresh
     * install's migrations run) to enabled — the funnel landing page is
     * the real "/" experience, not an opt-in extra. Only applies once:
     * every later call finds the existing row and leaves is_enabled
     * exactly as an admin last set it from /admin/funnel.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['is_enabled' => true]);
    }
}
