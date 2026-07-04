<?php

namespace App\Listeners;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        AuthenticationLog::create([
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'event' => 'login',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
