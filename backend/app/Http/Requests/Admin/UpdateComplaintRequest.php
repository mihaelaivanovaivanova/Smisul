<?php

namespace App\Http\Requests\Admin;

use App\Enums\ComplaintStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateComplaintRequest extends FormRequest
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
            'status' => ['required', new Enum(ComplaintStatus::class)],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
