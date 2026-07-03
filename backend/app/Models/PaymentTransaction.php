<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Carbon\Carbon;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per gateway interaction for a payment (initiated, return,
 * cancel_return, webhook, status_check) — an immutable log, never updated
 * after creation (hence no updated_at). See PaymentService, the sole writer.
 *
 * @property PaymentStatus $status
 * @property Carbon $created_at
 */
class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'payment_id',
        'type',
        'status',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
