<?php

namespace App\Http\Controllers;

use App\Services\SuppressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Receives Brevo's transactional event webhook (hard bounce / spam
 * complaint) so an address that bounces or gets marked as spam is
 * suppressed from future broadcasts automatically - without this, the only
 * signal would be an admin noticing by hand. Register the webhook URL from
 * Brevo's dashboard (Transactional > Settings > Webhook) once
 * BREVO_WEBHOOK_SECRET is set; Brevo doesn't sign its webhook payloads, so
 * the random secret path segment (not a query string - less likely to leak
 * via logs/referrers) is what stands in for signature verification, the
 * same role ICardWebhookController's real signature plays for iCard.
 *
 * Case-insensitive substring match on the event name rather than an exact
 * string - Brevo's own docs aren't consistent about hardBounce vs
 * hard_bounce casing, and matching loosely for "bounce"/"spam" is safe
 * (nothing else in a bounce/complaint event name could false-positive on
 * those substrings).
 */
class BrevoWebhookController extends Controller
{
    public function __construct(private readonly SuppressionService $suppressions) {}

    public function handle(Request $request, string $secret): JsonResponse
    {
        if (! hash_equals((string) config('services.brevo.webhook_secret'), $secret)) {
            throw new NotFoundHttpException;
        }

        $event = Str::lower((string) $request->input('event', ''));
        $email = (string) $request->input('email', '');

        if ($email !== '' && (Str::contains($event, 'bounce') || Str::contains($event, 'spam'))) {
            $this->suppressions->suppress($email, $event, 'brevo_webhook');
        }

        return response()->json(['status' => 'ok']);
    }
}
