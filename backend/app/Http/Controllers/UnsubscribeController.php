<?php

namespace App\Http\Controllers;

use App\Enums\ConsentType;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\SuppressionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The one-click unsubscribe link every BroadcastMail (and the 30-day order
 * reminder) carries in its List-Unsubscribe/List-Unsubscribe-Post headers -
 * see Mail\Concerns\HasUnsubscribeLink. Gmail/Yahoo's bulk-sender rules
 * require this to work with a bare POST and no further confirmation step
 * (RFC 8058); GET is also accepted for a human who clicks the footer link
 * directly in their mail client instead of letting the client one-click it.
 * No auth, by necessity - the recipient of a marketing email has no session
 * here. Trust comes entirely from the signed URL (see
 * Mail\Concerns\HasUnsubscribeLink::unsubscribeUrl()), not from who's
 * asking - exactly the ICardWebhookController pattern, with a route
 * signature standing in for ICard's payload signature.
 */
class UnsubscribeController extends Controller
{
    public function __construct(
        private readonly SuppressionService $suppressions,
        private readonly ConsentService $consents,
    ) {}

    public function handle(Request $request, string $email): Response
    {
        $this->suppressions->suppress($email, 'unsubscribed', 'link');
        $this->withdrawConsent($email, $request);

        if ($request->isMethod('post')) {
            return response()->noContent();
        }

        return response()->view('unsubscribed', ['email' => $email]);
    }

    /**
     * Keeps the existing consent system (an account's profile toggles, the
     * Consent audit log) honest about a link-driven unsubscribe, on top of
     * the email_suppressions row that actually gates future sends - a
     * registered user who unsubscribes from a broadcast shouldn't see their
     * account settings still claim they're opted in. guest_identifier =
     * the email itself when no account matches (funnel leads, or any
     * address with no User row), the same slot the cookie-consent banner
     * uses for an anonymous visitor.
     */
    private function withdrawConsent(string $email, Request $request): void
    {
        $user = User::where('email', SuppressionService::normalize($email))->first();

        $this->consents->recordMany(
            [ConsentType::Marketing->value => false, ConsentType::Newsletter->value => false],
            $user,
            $user === null ? SuppressionService::normalize($email) : null,
            $request->ip(),
            (string) $request->userAgent(),
        );

        $user?->update(['marketing_consent' => false, 'newsletter_subscription' => false]);
    }
}
