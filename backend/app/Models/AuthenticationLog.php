<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit log entry (no updated_at) — see the auth listeners in
 * app/Listeners (LogSuccessfulLogin, LogFailedLogin, LogLogout), the sole
 * writers.
 *
 * @property Carbon $created_at
 */
class AuthenticationLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'email',
        'event',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
