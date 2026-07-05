<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductMediaRequest extends FormRequest
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
            // Same mime/size allowance as the admin Media Library's replace
            // endpoint (MediaReplaceRequest) — one whitelist to keep in
            // sync instead of two drifting sets of "allowed media" rules.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,pdf', 'max:20480'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
