<?php

namespace App\Http\Requests\Admin;

use App\Enums\ComplaintStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ComplaintIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', new Enum(ComplaintStatus::class)],
            'sort' => ['nullable', Rule::in([
                'submitted_desc', 'submitted_asc',
                'number_desc', 'number_asc',
                'status_asc', 'status_desc',
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
