<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated(), $request->ip(), $request->userAgent());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }
}
