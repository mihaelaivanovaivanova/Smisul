<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consent\StoreCookieConsentRequest;
use App\Models\Consent;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, guest-and-authenticated cookie consent endpoints — the cookie
 * banner talks to this. Terms/Privacy/Marketing/Newsletter consent are
 * NOT exposed here as separate write endpoints: they're recorded as a
 * side effect of registration/profile updates (see AuthService::register,
 * ProfileService::updateProfile), which are the actual moments those
 * decisions are made.
 */
class ConsentController extends Controller
{
    public function __construct(private readonly ConsentService $consents) {}

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
