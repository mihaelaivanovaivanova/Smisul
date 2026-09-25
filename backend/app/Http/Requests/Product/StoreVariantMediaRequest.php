<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Image-only — this is the still photo shown when this pack
            // size is selected (see ProductPage.tsx's
            // getGalleryImagesForVariant), not a video swap.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:20480'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
