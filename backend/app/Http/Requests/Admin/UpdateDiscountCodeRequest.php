<?php

namespace App\Http\Requests\Admin;

use App\Models\DiscountCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateDiscountCodeRequest extends FormRequest
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
        /** @var DiscountCode $discountCode */
        $discountCode = $this->route('discountCode');

        return [
            'code' => ['required', 'string', 'max:191', function (string $attribute, mixed $value, \Closure $fail) use ($discountCode) {
                $exists = DiscountCode::whereRaw('UPPER(code) = ?', [Str::upper(trim((string) $value))])
                    ->where('id', '!=', $discountCode->id)
                    ->exists();

                if ($exists) {
                    $fail('A discount code with this name already exists.');
                }
            }],
            'discount_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'max_uses' => ['required', 'integer', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($discountCode) {
                // Setting max_uses below what's already been redeemed would
                // make isRedeemable() permanently false with no visible
                // reason - reject it outright instead of silently bricking
                // an existing code.
                if ((int) $value < $discountCode->used_count) {
                    $fail("Can't be lower than the {$discountCode->used_count} time(s) this code has already been used.");
                }
            }],
            'starts_at' => ['nullable', 'date_format:Y-m-d'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
