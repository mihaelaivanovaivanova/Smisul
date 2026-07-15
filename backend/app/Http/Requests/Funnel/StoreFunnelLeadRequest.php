<?php

namespace App\Http\Requests\Funnel;

use Illuminate\Foundation\Http\FormRequest;

class StoreFunnelLeadRequest extends FormRequest
{
    /**
     * Anyone, including guests, may leave their email — that's the point.
     */
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
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
