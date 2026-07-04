<?php

namespace App\Policies;

use App\Models\User;

/**
 * Settings are always addressed as a class-level resource (there's no
 * single Setting instance being authorized) — both abilities are checked
 * against Setting::class, so neither method receives a model instance.
 */
class SettingPolicy
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
