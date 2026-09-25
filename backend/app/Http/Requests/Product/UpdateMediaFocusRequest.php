<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaFocusRequest extends FormRequest
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
            'focus_x' => ['required', 'numeric', 'min:0', 'max:1'],
            'focus_y' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
