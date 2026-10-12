<?php

namespace App\Services;

use App\Models\EmailSuppression;
use Illuminate\Support\Str;

/**
 * The single gate every broadcast send checks before mailing an address -
 * see BroadcastService::recipients(). Written to by the one-click
 * unsubscribe link (UnsubscribeController) and by Brevo's bounce/complaint/
 * unsubscribe webhook (BrevoWebhookController); read by nothing else, since
 * "is this email suppressed" has exactly one legitimate caller shape:
 * "about to send a broadcast, should this address be skipped".
 */
class SuppressionService
{
    public function isSuppressed(string $email): bool
    {
        return EmailSuppression::query()->where('email', self::normalize($email))->exists();
    }

    /**
     * Idempotent - re-suppressing an already-suppressed address (e.g. a
     * second bounce webhook for the same address) is a no-op, not a
     * duplicate row or a failed unique-constraint insert.
     */
    public function suppress(string $email, string $reason, ?string $source = null): void
    {
        EmailSuppression::query()->firstOrCreate(
            ['email' => self::normalize($email)],
            ['reason' => $reason, 'source' => $source],
        );
    }

    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
