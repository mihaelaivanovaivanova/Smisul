<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Media $media): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->isAdministrator();
    }
}
