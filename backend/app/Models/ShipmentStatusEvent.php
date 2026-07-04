<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Carbon\Carbon;
use Database\Factories\ShipmentStatusEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit log entry — same pattern as PaymentTransaction/
 * PaymentWebhookLog in Sprint 7.
 *
 * @property ShipmentStatus $status
 * @property Carbon $occurred_at
 * @property ?array<string, mixed> $raw_payload
 */
class ShipmentStatusEvent extends Model
{
    /** @use HasFactory<ShipmentStatusEventFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'shipment_id',
        'status',
        'description',
        'occurred_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'occurred_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
