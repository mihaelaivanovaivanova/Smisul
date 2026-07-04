<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * @property ?int $orders_count
 */
class CustomerResource extends UserResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'orders_count' => $this->when($this->orders_count !== null, fn () => $this->orders_count),
        ];
    }
}
