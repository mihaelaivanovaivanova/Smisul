<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consent\StoreCookieConsentRequest;
use App\Http\Resources\LegalDocumentResource;
use App\Models\Consent;
use App\Services\ConsentService;
use App\Services\LegalDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public, guest-and-authenticated cookie consent endpoints — the cookie
 * banner talks to this. Marketing/Newsletter consent is NOT exposed here
 * as a separate write endpoint: it's recorded as a side effect of
 * registration/profile updates (see AuthService::register,
 * ProfileService::updateProfile), which are the actual moments those
 * decisions are made. Terms/Privacy consent is recorded at those same
 * moments too, EXCEPT for the one case below (acceptOutstandingAccountDocuments):
 * re-accepting after a published update, which has no other natural
 * "moment" to hang off of.
 */
class ConsentController extends Controller
{
    public function __construct(
        private readonly ConsentService $consents,
        private readonly LegalDocumentService $legalDocuments,
    ) {}

    public function showCookiePreferences(Request $request): JsonResponse
    {
        $current = $this->consents->currentFor($request->user(), $request->query('guest_identifier'));

        return response()->json(['data' => $this->formatCookieState($current)]);
    }

    public function storeCookiePreferences(StoreCookieConsentRequest $request): JsonResponse
    {
        $user = $request->user();
        $guestIdentifier = $user === null ? $request->validated('guest_identifier') : null;

        $this->consents->recordCookiePreferences(
            $request->validated('categories'),
            $user,
            $guestIdentifier,
            $request->ip(),
            $request->userAgent(),
        );

        $current = $this->consents->currentFor($user, $guestIdentifier);

        return response()->json(['data' => $this->formatCookieState($current)], 201);
    }

    /**
     * Re-accepts every current requiredForAccount() document (Terms/Privacy)
     * for the authenticated user in one call — what the frontend's "please
     * review our updated Terms" banner/modal submits to. Always inserts a
     * fresh Consent row for each, same as registration does, in keeping
     * with the append-only audit log (see ConsentService); it's safe to
     * call even when nothing is actually outstanding.
     */
    public function acceptOutstandingAccountDocuments(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $decisions = [];
        $documents = [];

        foreach (ConsentType::cases() as $consentType) {
            $legalType = $consentType->legalDocumentType();
            $document = $legalType !== null ? $this->legalDocuments->currentByType($legalType) : null;

            if ($document === null) {
                continue;
            }

            $decisions[$consentType->value] = true;
            $documents[$consentType->value] = $document;
        }

        $this->consents->recordMany($decisions, $user, null, $request->ip(), $request->userAgent(), $documents);

        return LegalDocumentResource::collection($this->consents->outstandingForAccount($user));
    }

    /**
     * @param  array<string, Consent>  $current
     * @return array{necessary: bool, analytics: bool, marketing: bool, preferences: bool}
     */
    private function formatCookieState(array $current): array
    {
        return [
            'necessary' => true,
            'analytics' => $current[ConsentType::CookieAnalytics->value]->accepted ?? false,
            'marketing' => $current[ConsentType::CookieMarketing->value]->accepted ?? false,
            'preferences' => $current[ConsentType::CookiePreferences->value]->accepted ?? false,
        ];
    }
}
