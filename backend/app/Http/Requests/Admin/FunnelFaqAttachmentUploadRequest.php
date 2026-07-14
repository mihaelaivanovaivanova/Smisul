<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FunnelFaqAttachmentUploadRequest extends FormRequest
{
    /**
     * Authorization is enforced by the admin route group's middleware
     * (auth:sanctum + admin), matching FunnelController::updateContent()
     * and updatePackages(), which don't authorize() per-action either.
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
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }
}
