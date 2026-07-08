<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FunnelService;
use Illuminate\Http\JsonResponse;

class FunnelController extends Controller
{
    public function __construct(private readonly FunnelService $funnel) {}

    /**
     * Public, unauthenticated — the storefront reads this at boot to
     * decide whether to render the normal homepage or the funnel landing
     * page, and to hide search/Favorites accordingly.
     */
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->funnel->publicPayload()]);
    }
}
