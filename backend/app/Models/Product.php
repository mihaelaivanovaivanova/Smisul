<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\Sluggable;
use App\Models\Contracts\IsMediable;
use Carbon\Carbon;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ProductStatus $status
 * @property ?Carbon $published_at
 */
class Product extends Model implements IsMediable
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasMedia, HasSeo, Sluggable, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasOne<ProductVariant, $this>
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /**
     * @return BelongsToMany<Promotion, $this>
     */
    public function promotions(): BelongsToMany
    {
        // Explicit pivot name: Eloquent's alphabetical-order convention would
        // guess "product_promotion", but the migration uses "promotion_product".
        return $this->belongsToMany(Promotion::class, 'promotion_product');
    }

    /**
     * @return HasManyThrough<Favorite, ProductVariant, $this>
     */
    public function favorites(): HasManyThrough
    {
        return $this->hasManyThrough(Favorite::class, ProductVariant::class);
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published;
    }

    /**
     * Currently-valid promotions covering this product, whether scoped to
     * the product directly or to one of its categories. Callers should
     * eager-load "promotions" and "categories.promotions" to avoid N+1s
     * across a product listing.
     *
     * @return Collection<int, Promotion>
     */
    public function activePromotions(): Collection
    {
        $viaCategories = $this->categories->flatMap(fn (Category $category) => $category->promotions);

        return $this->promotions
            ->merge($viaCategories)
            ->unique('id')
            ->filter(fn (Promotion $promotion) => $promotion->isCurrentlyValid())
            ->values();
    }
}
