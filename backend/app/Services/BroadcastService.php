<?php

namespace App\Services;

use App\Mail\BroadcastMail;
use App\Models\FunnelLead;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Powers the admin "Send broadcast" button (Admin\BroadcastController) -
 * the one-click weekly promo send to everyone who's actually opted in.
 * Synchronous, like OrderReminderService::sendDueReminders() and
 * SendOrderStatusEmails - this host has no persistent queue worker (shared
 * cPanel hosting, cron-only), so a "send to N people" action here means
 * "loop and send them one at a time within this request", same as every
 * other bulk-ish mail already in this app. At the ~100-recipient scale this
 * is built for, a Brevo API call per recipient (a few hundred ms each) is
 * well inside a normal request timeout; this isn't meant to scale past
 * low thousands without becoming a queued job instead.
 */
class BroadcastService
{
    /**
     * Every address this app has actual marketing permission to email,
     * deduplicated and with anyone who's unsubscribed/bounced/complained
     * (email_suppressions - see SuppressionService) already excluded. Two
     * sources, since this store's "mailing list" isn't one table: Users
     * who opted in via their account/checkout (marketing_consent or
     * newsletter_subscription - either is enough, this is one broadcast
     * list, not two), and FunnelLeads, whose only reason to exist is the
     * marketing opt-in at capture time (see FunnelLead's own docblock).
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        $userEmails = User::query()
            ->where(fn ($query) => $query->where('marketing_consent', true)->orWhere('newsletter_subscription', true))
            ->pluck('email');

        $leadEmails = FunnelLead::query()->pluck('email');

        $suppressions = new SuppressionService;

        return $userEmails->merge($leadEmails)
            ->map(fn (string $email) => Str::lower(trim($email)))
            ->unique()
            ->reject(fn (string $email) => $suppressions->isSuppressed($email))
            ->values()
            ->all();
    }

    /**
     * Splits the admin's plain-text textarea input into paragraphs on blank
     * lines - the simplest possible authoring format that still lets
     * BroadcastMail's view render each as its own <p>, rather than storing/
     * trusting raw admin-authored HTML for what is, after all, a form only
     * admins can reach.
     *
     * @return list<string>
     */
    public static function paragraphs(string $body): array
    {
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/\R{2,}/', trim($body)) ?: [],
        ), fn (string $paragraph) => $paragraph !== ''));
    }

    /**
     * @return array{sent: int, failed: int, total: int}
     */
    public function send(string $subject, array $paragraphs, ?string $ctaUrl, ?string $ctaLabel): array
    {
        $recipients = $this->recipients();
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $email) {
            try {
                Mail::mailer('brevo')->to($email)->send(
                    new BroadcastMail($email, $subject, $paragraphs, $ctaUrl, $ctaLabel),
                );
                $sent++;
            } catch (Throwable $exception) {
                $failed++;
                Log::error('Could not send a broadcast email.', [
                    'email' => $email,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'total' => count($recipients)];
    }
}
