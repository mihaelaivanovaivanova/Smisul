<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    public function update(UpdatePasswordRequest $request): JsonResponse
    {
        $this->profileService->updatePassword($request->user(), $request->validated('password'));

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
