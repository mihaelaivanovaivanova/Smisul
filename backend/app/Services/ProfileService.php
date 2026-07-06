<?php

namespace App\Services;

use App\Enums\ConsentType;
use App\Models\User;

class ProfileService
{
    public function __construct(private readonly ConsentService $consents) {}

    /**
     * Update the authenticated user's profile.
     *
     * Changing the email address revokes verification and re-sends the
     * verification notification, mirroring Laravel's default behavior for
     * email-verifiable models.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data, ?string $ipAddress = null, ?string $userAgent = null): User
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

        // Only log a consent row when the client actually sent that field —
        // a partial profile update (e.g. changing just the phone number)
        // must not be misread as a fresh decision about marketing/newsletter.
        $decisions = [];

        if (array_key_exists('marketing_consent', $data)) {
            $decisions[ConsentType::Marketing->value] = (bool) $data['marketing_consent'];
        }

        if (array_key_exists('newsletter_subscription', $data)) {
            $decisions[ConsentType::Newsletter->value] = (bool) $data['newsletter_subscription'];
        }

        if ($decisions !== []) {
            $this->consents->recordMany($decisions, $user, null, $ipAddress, $userAgent);
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
