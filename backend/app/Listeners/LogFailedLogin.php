<?php

namespace App\Listeners;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        AuthenticationLog::create([
            'user_id' => $user?->getKey(),
            'email' => $event->credentials['email'] ?? $user?->email,
            'event' => 'failed',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
