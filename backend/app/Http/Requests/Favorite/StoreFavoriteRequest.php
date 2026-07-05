<?php

namespace App\Http\Requests\Favorite;

use App\Models\Favorite;
use Illuminate\Foundation\Http\FormRequest;

class StoreFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Favorite::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
        ];
    }
}
