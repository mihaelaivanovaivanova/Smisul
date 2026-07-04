<?php

namespace App\Http\Resources\Admin;

use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'type_label' => $this->type->label(),
            'version' => $this->version,
            'title' => $this->title,
            'content' => $this->content,
            'is_current' => $this->is_current,
            'published_at' => $this->published_at,
        ];
    }
}
