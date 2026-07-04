<?php

namespace App\Http\Requests\Admin;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class LegalDocumentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', LegalDocument::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(LegalDocumentType::class)],
            'version' => [
                'required', 'string', 'max:32',
                Rule::unique('legal_documents')->where('type', $this->input('type')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }
}
