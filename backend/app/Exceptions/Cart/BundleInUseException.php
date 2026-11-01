<?php

namespace App\Exceptions\Cart;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when an admin tries to change a kit's components (or delete the
 * kit) while carts or not-yet-confirmed orders still hold stock reserved
 * against its CURRENT components - every later release/fulfill of those
 * holds reads the kit's live components, so changing them underneath would
 * leak or mis-commit stock. See BundleService::hasOutstandingReservations().
 */
class BundleInUseException extends Exception
{
    public static function forSlug(string $slug): self
    {
        return new self(
            "Kit [{$slug}] is in active carts or unconfirmed orders, so its components can't be changed or the kit deleted right now. Deactivate it instead (and create a new kit if the contents need to change)."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
