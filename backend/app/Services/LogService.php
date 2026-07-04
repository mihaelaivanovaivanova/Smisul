<?php

namespace App\Services;

use App\Models\AdminActionLog;
use App\Models\AuthenticationLog;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\ShipmentStatusEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Backs the admin Logs screen's five tabs. Each tab reads a different
 * immutable audit table (see Sprint 7/8's OrderStatusHistory,
 * PaymentTransaction, ShipmentStatusEvent, and this sprint's
 * AuthenticationLog/AdminActionLog) and is normalized into a common shape
 * so the frontend can render every tab with one generic log table
 * component.
 */
class LogService
{
    public const TYPES = ['orders', 'payments', 'shipments', 'authentication', 'admin_actions'];

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function forType(string $type, int $page, int $perPage): LengthAwarePaginator
    {
        return match ($type) {
            'orders' => $this->orders($page, $perPage),
            'payments' => $this->payments($page, $perPage),
            'shipments' => $this->shipments($page, $perPage),
            'authentication' => $this->authentication($page, $perPage),
            'admin_actions' => $this->adminActions($page, $perPage),
            default => throw new InvalidArgumentException("Unknown log type [{$type}]."),
        };
    }

    private function orders(int $page, int $perPage): LengthAwarePaginator
    {
        return OrderStatusHistory::query()
            ->with('changedBy')
            ->latest()
            ->paginate($perPage, page: $page)
            ->through(fn (OrderStatusHistory $event) => [
                'id' => $event->id,
                'message' => "Order #{$event->order_id} moved to {$event->status->value}",
                'user' => $this->normalizeUser($event->changedBy),
                'created_at' => $event->created_at,
                'meta' => [
                    'order_id' => $event->order_id,
                    'status' => $event->status->value,
                    'previous_status' => $event->previous_status?->value,
                    'note' => $event->note,
                ],
            ]);
    }

    private function payments(int $page, int $perPage): LengthAwarePaginator
    {
        return PaymentTransaction::query()
            ->with('payment')
            ->latest()
            ->paginate($perPage, page: $page)
            ->through(fn (PaymentTransaction $event) => [
                'id' => $event->id,
                'message' => "Payment #{$event->payment_id} {$event->type} — {$event->status->value}",
                'user' => null,
                'created_at' => $event->created_at,
                'meta' => [
                    'payment_id' => $event->payment_id,
                    'order_id' => $event->payment?->order_id,
                    'type' => $event->type,
                    'status' => $event->status->value,
                ],
            ]);
    }

    private function shipments(int $page, int $perPage): LengthAwarePaginator
    {
        return ShipmentStatusEvent::query()
            ->with('shipment')
            ->latest()
            ->paginate($perPage, page: $page)
            ->through(fn (ShipmentStatusEvent $event) => [
                'id' => $event->id,
                'message' => "Shipment #{$event->shipment_id} — {$event->status->value}",
                'user' => null,
                'created_at' => $event->created_at,
                'meta' => [
                    'shipment_id' => $event->shipment_id,
                    'order_id' => $event->shipment?->order_id,
                    'status' => $event->status->value,
                    'description' => $event->description,
                ],
            ]);
    }

    private function authentication(int $page, int $perPage): LengthAwarePaginator
    {
        return AuthenticationLog::query()
            ->with('user')
            ->latest()
            ->paginate($perPage, page: $page)
            ->through(fn (AuthenticationLog $event) => [
                'id' => $event->id,
                'message' => "{$event->event}: {$event->email}",
                'user' => $this->normalizeUser($event->user),
                'created_at' => $event->created_at,
                'meta' => [
                    'event' => $event->event,
                    'email' => $event->email,
                    'ip_address' => $event->ip_address,
                    'user_agent' => $event->user_agent,
                ],
            ]);
    }

    private function adminActions(int $page, int $perPage): LengthAwarePaginator
    {
        return AdminActionLog::query()
            ->with('user')
            ->latest()
            ->paginate($perPage, page: $page)
            ->through(fn (AdminActionLog $event) => [
                'id' => $event->id,
                'message' => $event->action,
                'user' => $this->normalizeUser($event->user),
                'created_at' => $event->created_at,
                'meta' => [
                    'action' => $event->action,
                    'subject_type' => $event->subject_type,
                    'subject_id' => $event->subject_id,
                    'changes' => $event->changes,
                ],
            ]);
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private function normalizeUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => trim("{$user->first_name} {$user->last_name}"),
            'email' => $user->email,
        ];
    }
}
