<?php

namespace App\Http\Requests\Admin;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Legacy cookie/returns rows remain readable for audit history,
            // but new versions may only use the four active policy types.
            'type' => ['required', Rule::in([
                LegalDocumentType::TermsOfService->value,
                LegalDocumentType::PrivacyPolicy->value,
                LegalDocumentType::RightOfWithdrawal->value,
                LegalDocumentType::ShippingPolicy->value,
            ])],
            'version' => [
                'required', 'string', 'max:32',
                Rule::unique('legal_documents')->where('type', $this->input('type')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }
}
