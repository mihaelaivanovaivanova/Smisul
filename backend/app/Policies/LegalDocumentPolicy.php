<?php

namespace App\Policies;

use App\Models\LegalDocument;
use App\Models\User;

class LegalDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, LegalDocument $legalDocument): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }
}
