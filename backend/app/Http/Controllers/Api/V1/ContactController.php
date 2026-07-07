<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use App\Mail\ContactMessageMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Public contact form (frontend footer modal) — no auth, throttled (see
 * the "contact" rate limiter in AppServiceProvider) since it's a public,
 * unauthenticated write endpoint.
 */
class ContactController extends Controller
{
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        Mail::to(config('mail.contact_address'))->send(new ContactMessageMail(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('message'),
        ));

        return response()->json(['message' => 'Съобщението е изпратено.'], 201);
    }
}
