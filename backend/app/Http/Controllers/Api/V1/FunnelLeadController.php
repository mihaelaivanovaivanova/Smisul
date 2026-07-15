<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnel\StoreFunnelLeadRequest;
use App\Models\FunnelLead;
use Illuminate\Http\JsonResponse;

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
        FunnelLead::firstOrCreate(['email' => mb_strtolower($request->validated('email'))]);

        return response()->json(['message' => 'Благодарим! Ще се чуем скоро.'], 201);
    }
}
