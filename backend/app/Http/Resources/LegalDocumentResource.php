<?php

namespace App\Http\Resources;

use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public legal-pages listing/detail shape (see LegalController) —
 * intentionally the same fields as Checkout\LegalDocumentResource, kept
 * as a separate class so checkout's resource (and its tests) are never
 * touched by anything this sprint does.
 *
 * @mixin LegalDocument
 */
class LegalDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'slug' => $this->type->slug(),
            'version' => $this->version,
            'title' => $this->title,
            'content' => $this->content,
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }
}
