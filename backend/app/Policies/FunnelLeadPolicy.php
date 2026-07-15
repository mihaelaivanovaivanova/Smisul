<?php

namespace App\Policies;

use App\Models\FunnelLead;
use App\Models\User;

class FunnelLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, FunnelLead $lead): bool
    {
        return $user->isAdministrator();
    }
}
