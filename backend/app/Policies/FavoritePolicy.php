<?php

namespace App\Policies;

use App\Models\Favorite;
use App\Models\User;

/**
 * Favorites are customer-only (see the sprint's explicit rule) — an
 * administrator hitting these endpoints gets the same 403 a guest would
 * get from auth:sanctum, just one layer up.
 */
class FavoritePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomer();
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $user->id === $favorite->user_id;
    }
}
