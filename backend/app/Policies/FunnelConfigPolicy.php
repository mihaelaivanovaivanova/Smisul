<?php

namespace App\Policies;

use App\Models\User;

/**
 * Funnel config is always addressed as a class-level resource, same as
 * SettingPolicy/ContentBlockPolicy — there's no per-instance authorization
 * needed since the whole feature is governed by one singleton row.
 */
class FunnelConfigPolicy
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
