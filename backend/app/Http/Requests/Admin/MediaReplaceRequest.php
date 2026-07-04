<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MediaReplaceRequest extends FormRequest
{
    /**
     * Authorization is checked in the controller against the resolved
     * Media route-model binding instead (see MediaController::replace()),
     * since that's already the established pattern for this resource
     * (compare ProductMediaController::destroy()).
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
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,pdf', 'max:20480'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
