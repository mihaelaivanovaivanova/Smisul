<?php

namespace App\Services;

use App\Models\User;

class ProfileService
{
    /**
     * Update the authenticated user's profile.
     *
     * Changing the email address revokes verification and re-sends the
     * verification notification, mirroring Laravel's default behavior for
     * email-verifiable models.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $user->email;

        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user->refresh();
    }

    /**
     * Update the authenticated user's password.
     *
     * The "hashed" cast on User::password hashes this automatically.
     */
    public function updatePassword(User $user, string $newPassword): void
    {
        $user->forceFill(['password' => $newPassword])->save();
    }
}
