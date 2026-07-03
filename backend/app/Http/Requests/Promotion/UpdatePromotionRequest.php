<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends StorePromotionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('promotion'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('promotions', 'code')->ignore($this->route('promotion')),
            ],
        ]);
    }
}
