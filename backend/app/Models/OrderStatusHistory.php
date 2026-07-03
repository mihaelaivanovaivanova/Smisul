<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\Carbon;
use Database\Factories\OrderStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable audit trail entry — one row per status change, never
 * updated after creation (hence no updated_at). See
 * OrderStatusService::transitionTo(), the sole writer.
 *
 * @property OrderStatus $status
 * @property ?OrderStatus $previous_status
 * @property Carbon $created_at
 */
class OrderStatusHistory extends Model
{
    /** @use HasFactory<OrderStatusHistoryFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'status',
        'previous_status',
        'changed_by_user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'previous_status' => OrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
