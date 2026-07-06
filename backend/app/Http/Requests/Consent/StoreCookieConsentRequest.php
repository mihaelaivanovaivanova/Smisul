<?php

namespace App\Http\Requests\Consent;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Open to guests and authenticated customers alike — the cookie banner
 * appears before anyone has necessarily logged in. guest_identifier is a
 * client-generated UUID (mirrors the guest cart token pattern) used to
 * trace an anonymous visitor's consent history; it's ignored for an
 * authenticated request, which is traced by user_id instead (see
 * ConsentController).
 */
class StoreCookieConsentRequest extends FormRequest
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
            'guest_identifier' => ['nullable', 'string', 'uuid'],
            'categories' => ['required', 'array'],
            'categories.analytics' => ['required', 'boolean'],
            'categories.marketing' => ['required', 'boolean'],
            'categories.preferences' => ['required', 'boolean'],
        ];
    }
}
