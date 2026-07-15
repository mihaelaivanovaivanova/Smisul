<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnel\StoreFunnelLeadRequest;
use App\Mail\FunnelLeadWelcomeMail;
use App\Models\FunnelLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The funnel landing page's email opt-in — no auth, throttled (see the
 * "funnel-leads" rate limiter in AppServiceProvider) since it's a public,
 * unauthenticated write endpoint.
 */
class FunnelLeadController extends Controller
{
    public function store(StoreFunnelLeadRequest $request): JsonResponse
    {
        // firstOrCreate + a constant response: re-submitting an already
        // captured email behaves identically to a first submission, so the
        // endpoint can't be used to probe which emails are on the list.
        $lead = FunnelLead::firstOrCreate(['email' => mb_strtolower($request->validated('email'))]);

        // Welcome email (with the promised usage-manual PDF) only on the
        // first capture — resubmissions stay silent. A mailer failure must
        // never lose the lead itself, so it's reported, not rethrown.
        if ($lead->wasRecentlyCreated) {
            try {
                Mail::to($lead->email)->send(new FunnelLeadWelcomeMail);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return response()->json(['message' => 'Благодарим! Ще се чуем скоро.'], 201);
    }
}
