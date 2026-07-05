<?php

namespace App\Policies;

use App\Models\User;

class ContentBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user): bool
    {
        return $user->isAdministrator();
    }
}
