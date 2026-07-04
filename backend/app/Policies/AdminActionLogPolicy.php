<?php

namespace App\Policies;

use App\Models\User;

class AdminActionLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }
}
