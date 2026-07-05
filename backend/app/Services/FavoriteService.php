<?php

namespace App\Services;

use App\Exceptions\DuplicateFavoriteException;
use App\Models\Favorite;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class FavoriteService
{
    /**
     * ProductVariantResource reads the product's primaryMedia specifically
     * (not the full media collection) to render a thumbnail.
     */
    private const EAGER_LOAD = ['productVariant.product.primaryMedia', 'productVariant.prices', 'productVariant.inventory'];

    /**
     * @return Collection<int, Favorite>
     */
    public function listForUser(User $user): Collection
    {
        return Favorite::query()
            ->where('user_id', $user->id)
            ->with(self::EAGER_LOAD)
            ->latest()
            ->get();
    }

    public function add(User $user, ProductVariant $variant): Favorite
    {
        if ($this->isFavorited($user, $variant)) {
            throw DuplicateFavoriteException::create();
        }

        $favorite = Favorite::create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
        ]);

        return $favorite->load(self::EAGER_LOAD);
    }

    public function remove(Favorite $favorite): void
    {
        $favorite->delete();
    }

    public function isFavorited(User $user, ProductVariant $variant): bool
    {
        return $this->findFavorite($user, $variant) !== null;
    }

    public function findFavorite(User $user, ProductVariant $variant): ?Favorite
    {
        return Favorite::query()
            ->where('user_id', $user->id)
            ->where('product_variant_id', $variant->id)
            ->first();
    }

    public function countForUser(User $user): int
    {
        return Favorite::query()->where('user_id', $user->id)->count();
    }
}
