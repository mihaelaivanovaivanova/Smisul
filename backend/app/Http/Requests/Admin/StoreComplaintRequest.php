<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
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
            // The customer-facing reference (what a phoned-in/emailed
            // complaint actually comes with), not the internal numeric id -
            // staff logging a complaint never has that id in front of them.
            'order_number' => ['required', 'string', 'exists:orders,order_number'],
            'description' => ['required', 'string', 'max:5000'],
            'submitted_at' => ['nullable', 'date'],
        ];
    }
}
