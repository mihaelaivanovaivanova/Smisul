<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FunnelContentUpdateRequest;
use App\Http\Requests\Admin\FunnelFaqAttachmentUploadRequest;
use App\Http\Requests\Admin\FunnelPackagesRequest;
use App\Http\Requests\Admin\FunnelToggleRequest;
use App\Models\FunnelConfig;
use App\Services\AdminActionLogger;
use App\Services\FunnelContentService;
use App\Services\FunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Uploads a replacement PDF for a funnel.faq item's attachment_url —
     * a standalone file (not tied to any mediable model), so this stores
     * directly via Storage rather than through MediaService/the Media
     * table. Returns the public URL; the admin still has to Save the FAQ
     * section for it to take effect, matching how every other content
     * field here already works (edit locally, Save to persist).
     */
    public function uploadFaqAttachment(FunnelFaqAttachmentUploadRequest $request): JsonResponse
    {
        $path = $request->file('file')->store('funnel/faq-attachments', 'public');

        $this->actionLogger->log($request->user(), 'funnel.faq_attachment.uploaded', changes: ['path' => $path]);

        return response()->json(['data' => [
            'url' => Storage::disk('public')->url($path),
            'filename' => $request->file('file')->getClientOriginalName(),
        ]]);
    }
}
