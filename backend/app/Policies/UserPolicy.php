<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Administrators may list/browse all customers (see Sprint 9's admin
     * customers screen) — nothing else in this policy grants that, so it's
     * explicit here rather than implied.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->isAdministrator();
    }

    /**
     * A user may view a profile if it is their own, or if they're an
     * administrator (see Sprint 9's admin customer detail screen — the
     * cross-user override deferred from earlier sprints).
     */
    public function view(User $authUser, User $user): bool
    {
        return $authUser->is($user) || $authUser->isAdministrator();
    }

    /**
     * A user may update a profile only if it is their own — administrators
     * do not get a write override here; editing another customer's account
     * is out of scope (see the sprint's excluded "role management").
     */
    public function update(User $authUser, User $user): bool
    {
        return $authUser->is($user);
    }
}
