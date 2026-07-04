<?php

namespace App\Listeners;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function handle(Logout $event): void
    {
        /** @var ?User $user */
        $user = $event->user;

        if ($user === null) {
            return;
        }

        AuthenticationLog::create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'event' => 'logout',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
