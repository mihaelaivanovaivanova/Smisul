<?php

namespace App\Http\Requests\Admin;

use App\Models\AdminActionLog;
use App\Services\LogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', AdminActionLog::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(LogService::TYPES)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
