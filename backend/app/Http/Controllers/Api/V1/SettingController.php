<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated — only the whitelisted merchant-identity subset
 * (see SettingService::publicSettings), consumed by the storefront footer.
 */
class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->settings->publicSettings()]);
    }
}
