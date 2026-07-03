<?php

namespace App\Exceptions\Checkout;

use App\Enums\LegalDocumentType;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalDocumentsNotAcceptedException extends Exception
{
    /**
     * @param  list<LegalDocumentType>  $missingTypes
     */
    public function __construct(private readonly array $missingTypes)
    {
        $labels = array_map(fn (LegalDocumentType $type) => $type->label(), $missingTypes);

        parent::__construct('You must accept the following before placing an order: '.implode(', ', $labels));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'missing_types' => array_map(fn (LegalDocumentType $type) => $type->value, $this->missingTypes),
        ], 422);
    }
}
