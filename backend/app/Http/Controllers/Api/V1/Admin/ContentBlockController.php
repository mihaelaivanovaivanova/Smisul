<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentBlockUpdateRequest;
use App\Models\ContentBlock;
use App\Services\AdminActionLogger;
use App\Services\ContentBlockService;
use Illuminate\Http\JsonResponse;

class ContentBlockController extends Controller
{
    public function __construct(
        private readonly ContentBlockService $content,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ContentBlock::class);

        return response()->json(['data' => $this->content->homepage()]);
    }

    public function update(ContentBlockUpdateRequest $request, string $section): JsonResponse
    {
        $content = $this->content->updateHomepageSection($section, $request->validated());

        $this->actionLogger->log($request->user(), "content.homepage.{$section}.updated");

        return response()->json(['data' => $content]);
    }
}
