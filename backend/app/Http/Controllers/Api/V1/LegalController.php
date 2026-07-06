<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\LegalDocumentResource;
use App\Services\LegalDocumentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public legal pages (Terms, Privacy, Cookie Policy, Shipping, Returns,
 * etc.) — distinct from CheckoutController::legalDocuments(), which only
 * returns the subset checkout requires acceptance of (see
 * LegalDocumentService::currentRequiredForCheckout). This lists every
 * current document, for the footer and the public /legal/{slug} pages.
 */
class LegalController extends Controller
{
    public function __construct(private readonly LegalDocumentService $legalDocuments) {}

    public function index(): AnonymousResourceCollection
    {
        return LegalDocumentResource::collection($this->legalDocuments->current());
    }

    public function show(string $slug): LegalDocumentResource
    {
        $type = LegalDocumentType::fromSlug($slug);

        abort_if($type === null, 404, 'Unknown legal document.');

        $document = $this->legalDocuments->currentByType($type);

        abort_if($document === null, 404, 'This legal document is not currently published.');

        return new LegalDocumentResource($document);
    }
}
