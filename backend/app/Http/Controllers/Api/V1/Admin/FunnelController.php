<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FunnelContentUpdateRequest;
use App\Http\Requests\Admin\FunnelPackagesRequest;
use App\Http\Requests\Admin\FunnelToggleRequest;
use App\Models\FunnelConfig;
use App\Services\AdminActionLogger;
use App\Services\FunnelContentService;
use App\Services\FunnelService;
use Illuminate\Http\JsonResponse;

class FunnelController extends Controller
{
    public function __construct(
        private readonly FunnelService $funnel,
        private readonly FunnelContentService $content,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize('viewAny', FunnelConfig::class);

        return response()->json(['data' => $this->funnel->adminPayload()]);
    }

    public function toggle(FunnelToggleRequest $request): JsonResponse
    {
        $config = $this->funnel->toggle($request->boolean('is_enabled'));

        $this->actionLogger->log($request->user(), 'funnel.toggled', changes: ['is_enabled' => $config->is_enabled]);

        return response()->json(['data' => $this->funnel->adminPayload()]);
    }

    public function updatePackages(FunnelPackagesRequest $request): JsonResponse
    {
        $config = $this->funnel->updatePackages(
            $request->integer('product_id'),
            $request->validated('packages'),
        );

        $this->actionLogger->log($request->user(), 'funnel.packages.updated', changes: [
            'product_id' => $config->product_id,
            'packages' => $config->packages,
        ]);

        return response()->json(['data' => $this->funnel->adminPayload()]);
    }

    public function updateContent(FunnelContentUpdateRequest $request, string $section): JsonResponse
    {
        $content = $this->content->updateSection($section, $request->validated());

        $this->actionLogger->log($request->user(), "funnel.content.{$section}.updated");

        return response()->json(['data' => $content]);
    }
}
