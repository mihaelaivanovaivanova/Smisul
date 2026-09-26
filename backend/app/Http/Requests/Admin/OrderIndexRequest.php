<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderIndexRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'nullable', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            // Not Laravel's 'boolean' rule - it only accepts true/false/0/1
            // as actual values, not the literal strings "true"/"false" that
            // a GET query string sends (?hide_cancelled=true). Parsed
            // leniently either way in OrderFilterData::fromArray() via
            // filter_var(..., FILTER_VALIDATE_BOOLEAN).
            'hide_cancelled' => ['sometimes', 'nullable', 'string', 'in:0,1,true,false'],
            'hide_failed' => ['sometimes', 'nullable', 'string', 'in:0,1,true,false'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['sometimes', Rule::in(['newest', 'oldest', 'total_asc', 'total_desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
