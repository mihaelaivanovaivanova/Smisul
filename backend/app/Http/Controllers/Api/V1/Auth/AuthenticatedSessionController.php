<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function store(LoginRequest $request): UserResource
    {
        $user = $this->authService->login(
            $request,
            $request->validated('email'),
            $request->validated('password'),
            $request->boolean('remember'),
        );

        return new UserResource($user);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
