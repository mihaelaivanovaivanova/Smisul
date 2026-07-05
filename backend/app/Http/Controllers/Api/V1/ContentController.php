<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ContentBlockService;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    public function __construct(private readonly ContentBlockService $content) {}

    /**
     * Public, unauthenticated — the storefront homepage fetches its
     * editable sections from here instead of a hardcoded copy constant.
     */
    public function homepage(): JsonResponse
    {
        return response()->json(['data' => $this->content->homepage()]);
    }
}
