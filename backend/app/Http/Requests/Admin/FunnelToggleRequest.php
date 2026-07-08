<?php

namespace App\Http\Requests\Admin;

use App\Models\FunnelConfig;
use Illuminate\Foundation\Http\FormRequest;

class FunnelToggleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', FunnelConfig::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
