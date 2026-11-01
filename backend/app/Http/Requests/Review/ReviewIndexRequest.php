<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public, product-scoped review listing — no authorization needed beyond
 * the product itself being published (checked by the controller via
 * ProductService::findBySlug).
 */
class ReviewIndexRequest extends FormRequest
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
            'sort' => ['sometimes', Rule::in(['newest', 'highest', 'lowest', 'helpful'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            // Narrows the listing to reviews of one or more specific variants
            // (e.g. both kit pages pooling the reviews of every Miswak pack
            // size a kit can contain, rather than each kit's own single pack
            // size only) - reused as-is by reviewsSummary(), which ignores
            // sort/page/per_page but needs this same narrowing. No
            // cross-check against the route's product here: a variant
            // belonging to a different product just yields an empty result,
            // which is safe.
            'product_variant_ids' => ['sometimes', 'array', 'min:1'],
            'product_variant_ids.*' => ['integer', Rule::exists('product_variants', 'id')],
        ];
    }
}
