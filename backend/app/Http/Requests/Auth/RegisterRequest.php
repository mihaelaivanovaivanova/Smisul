<?php

namespace App\Http\Requests\Auth;

use App\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Anyone (including guests) may attempt to register.
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', PasswordPolicy::rules()],
            'newsletter_subscription' => ['sometimes', 'boolean'],
            'marketing_consent' => ['sometimes', 'boolean'],
            'gdpr_consent' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'gdpr_consent' => 'GDPR consent',
        ];
    }
}
