<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Seed the sole administrator account.
     *
     * Administrator accounts can never be created through public
     * registration (see AuthService::register(), which never accepts a
     * "role" field). This seeder is the only way to create one, and it
     * deliberately refuses to fall back to a hardcoded default — the
     * credentials must be supplied via the environment for every install.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            throw new RuntimeException(
                'ADMIN_EMAIL and ADMIN_PASSWORD must be set in .env before running AdminSeeder.'
            );
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Smisul',
                'last_name' => 'Administrator',
                'password' => $password,
                'gdpr_consent_at' => now(),
            ],
        );

        $admin->forceFill([
            'role' => Role::Administrator,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
    }
}
