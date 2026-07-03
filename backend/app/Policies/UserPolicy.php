<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * A user may view a profile only if it is their own.
     *
     * Administrator override is intentionally left out — an admin panel
     * with cross-user access is out of scope for this sprint.
     */
    public function view(User $authUser, User $user): bool
    {
        return $authUser->is($user);
    }

    /**
     * A user may update a profile only if it is their own.
     */
    public function update(User $authUser, User $user): bool
    {
        return $authUser->is($user);
    }
}
