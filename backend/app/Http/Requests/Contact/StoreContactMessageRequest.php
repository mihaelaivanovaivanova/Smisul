<?php

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    /**
     * Anyone, including guests, may submit the contact form.
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
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
