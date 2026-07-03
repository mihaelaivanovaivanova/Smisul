<?php

namespace App\Exceptions\Order;

use App\Enums\OrderStatus;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidOrderStatusTransitionException extends Exception
{
    public static function notAllowed(string $orderNumber, OrderStatus $from, OrderStatus $to): self
    {
        return new self(
            "Order {$orderNumber} cannot move from {$from->label()} to {$to->label()} — that transition isn't allowed."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
