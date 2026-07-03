<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->isAdministrator();
    }
}
