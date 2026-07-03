<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\PaymentWebhookLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every webhook delivery the app ever received, valid or not, processed or
 * not — an immutable audit log (no updated_at) and the source of truth for
 * idempotency (see PaymentService::handleWebhook(), which checks
 * idempotency_key before doing anything else).
 *
 * @property Carbon $created_at
 */
class PaymentWebhookLog extends Model
{
    /** @use HasFactory<PaymentWebhookLogFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'payment_id',
        'provider',
        'event_type',
        'provider_reference',
        'idempotency_key',
        'signature_valid',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
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
