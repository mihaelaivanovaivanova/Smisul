<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMediaRequest extends FormRequest
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
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'distinct', 'exists:media,id'],
        ];
    }
}
