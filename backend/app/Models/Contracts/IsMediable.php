<?php

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Implemented by models using the HasMedia trait, so services that accept
 * "any mediable model" can depend on this capability instead of the generic
 * Eloquent Model base class.
 */
interface IsMediable
{
    public function media(): MorphMany;

    public function primaryMedia(): MorphOne;
}
