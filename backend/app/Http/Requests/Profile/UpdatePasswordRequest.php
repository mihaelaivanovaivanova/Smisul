<?php

namespace App\Http\Requests\Profile;

use App\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordPolicy::rules()],
        ];
    }
}
