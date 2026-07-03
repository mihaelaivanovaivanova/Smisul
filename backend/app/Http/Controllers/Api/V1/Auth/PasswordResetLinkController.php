<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends Controller
{
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        // Intentionally return the same generic message whether or not the
        // email exists, to avoid leaking which addresses are registered.
        return response()->json([
            'message' => 'If an account exists for that email address, a password reset link has been sent.',
        ]);
    }
}
