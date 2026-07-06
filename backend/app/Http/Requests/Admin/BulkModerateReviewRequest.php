<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkModerateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'review_ids' => ['required', 'array', 'min:1'],
            'review_ids.*' => ['integer', 'exists:reviews,id'],
            'status' => ['required', Rule::in([
                ReviewStatus::Approved->value,
                ReviewStatus::Rejected->value,
                ReviewStatus::Hidden->value,
            ])],
        ];
    }
}
