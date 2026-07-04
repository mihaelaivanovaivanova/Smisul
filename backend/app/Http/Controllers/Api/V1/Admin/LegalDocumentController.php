<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LegalDocumentStoreRequest;
use App\Http\Resources\Admin\LegalDocumentResource;
use App\Models\LegalDocument;
use App\Services\AdminActionLogger;
use App\Services\LegalDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegalDocumentController extends Controller
{
    public function __construct(
        private readonly LegalDocumentService $legalDocuments,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalDocument::class);

        return LegalDocumentResource::collection($this->legalDocuments->all());
    }

    /**
     * Publishing inserts a new version rather than editing an existing
     * one — see LegalDocumentService::publish() — so there is no update()
     * or destroy() action here by design.
     */
    public function store(LegalDocumentStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $document = $this->legalDocuments->publish(
            type: LegalDocumentType::from($data['type']),
            version: $data['version'],
            title: $data['title'],
            content: $data['content'] ?? null,
        );

        $this->actionLogger->log($request->user(), 'legal_document.published', $document);

        return (new LegalDocumentResource($document))->response()->setStatusCode(201);
    }
}
