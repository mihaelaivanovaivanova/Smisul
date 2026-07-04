<?php

namespace App\Services;

use App\DataTransferObjects\Admin\CustomerFilterData;
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
        $query = User::query()->where('role', Role::Customer)->withCount('orders');

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
