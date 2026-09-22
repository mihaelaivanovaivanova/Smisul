<?php

namespace App\Services;

use App\DataTransferObjects\Admin\CustomerFilterData;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Admin-only customer directory — registered accounts (role=customer)
 * only, since guests never have a User row to list. Order history for a
 * given customer is deliberately not duplicated here: it's fetched via
 * OrderService::listForAdmin(new OrderFilterData(userId: $customer->id))
 * from the same admin orders endpoint the order list screen already uses.
 */
class CustomerService
{
    public function list(CustomerFilterData $filters): LengthAwarePaginator
    {
        // A cancelled order was never a real sale, so it shouldn't inflate
        // how many orders a customer "has" any more than it inflates the
        // dashboard's own total_orders (see OrderService::statistics()).
        $query = User::query()
            ->where('role', Role::Customer)
            ->withCount(['orders' => fn ($query) => $query->where('status', '!=', OrderStatus::Cancelled)]);

        if ($filters->search !== null && $filters->search !== '') {
            $term = "%{$filters->search}%";
            $query->where(function ($query) use ($term) {
                $query->where('email', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        match ($filters->sort) {
            'oldest' => $query->oldest(),
            'name' => $query->orderBy('first_name')->orderBy('last_name'),
            default => $query->latest(),
        };

        return $query->paginate($filters->perPage, page: $filters->page);
    }
}
