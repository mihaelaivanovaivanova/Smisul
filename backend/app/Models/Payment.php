<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Carbon\Carbon;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PaymentProvider $provider
 * @property PaymentMethod $payment_method
 * @property PaymentStatus $status
 * @property ?array<string, mixed> $raw_response
 * @property ?Carbon $initiated_at
 * @property ?Carbon $completed_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'gateway_environment',
        'payment_method',
        'status',
        'amount',
        'currency',
        'transaction_reference',
        'provider_reference',
        'gateway_transaction_reference',
        'operation_status',
        'operation_code',
        'operation_message',
        'approval_code',
        'masked_pan',
        'card_type',
        'cardholder_name',
        'refunded_amount',
        'redirect_url',
        'raw_response',
        'initiated_at',
        'completed_at',
        'paid_at',
        'reversed_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'raw_response' => 'array',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
            'refunded_at' => 'datetime',
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
     * @return HasMany<PaymentTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * @return HasMany<PaymentWebhookLog, $this>
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(PaymentWebhookLog::class);
    }
}
