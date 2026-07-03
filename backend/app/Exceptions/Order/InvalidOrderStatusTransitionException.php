<?php

namespace App\Exceptions\Order;

use App\Enums\OrderStatus;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidOrderStatusTransitionException extends Exception
{
    public static function notPending(string $orderNumber, OrderStatus $actual): self
    {
        return new self(
            "Order {$orderNumber} cannot transition from its current status ({$actual->value}) — only a Pending order can."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
