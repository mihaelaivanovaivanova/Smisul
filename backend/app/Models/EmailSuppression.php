<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $email
 * @property string $reason
 */
class EmailSuppression extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'reason',
        'source',
    ];
}
