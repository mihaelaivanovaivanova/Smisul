<?php

namespace App\Http\Requests\Admin;

use App\Models\DiscountCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreDiscountCodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:191', function (string $attribute, mixed $value, \Closure $fail) {
                // Case-insensitive on purpose - redemption (DiscountCodeService)
                // compares via UPPER(code), so "spooky10" and "SPOOKY10" would
                // otherwise both insert and become ambiguous at checkout.
                if (DiscountCode::whereRaw('UPPER(code) = ?', [Str::upper(trim((string) $value))])->exists()) {
                    $fail('A discount code with this name already exists.');
                }
            }],
            'discount_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'max_uses' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date_format:Y-m-d'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
