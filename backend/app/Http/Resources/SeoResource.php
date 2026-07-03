<?php

namespace App\Http\Resources;

use App\Models\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Seo
 */
class SeoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image_url' => $this->og_image_path,
            'canonical_url' => $this->canonical_url,
        ];
    }
}
