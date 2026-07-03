<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    /**
     * Confirm a user's email address.
     *
     * The route this action is bound to is protected by the "signed"
     * middleware, so reaching this method already proves the link is
     * genuine and unexpired — no session/auth is required to click it,
     * which lets verification work even if the link is opened on a
     * different device than the one used to register.
     */
    public function __invoke(int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email address already verified.']);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Email address verified successfully.']);
    }
}
