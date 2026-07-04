<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use Carbon\Carbon;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ShippingCarrier $carrier
 * @property ShippingDeliveryType $delivery_type
 * @property ShipmentStatus $status
 * @property ?array<string, mixed> $raw_response
 * @property ?Carbon $estimated_delivery_at
 */
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'carrier',
        'delivery_type',
        'office_id',
        'office_name',
        'tracking_number',
        'status',
        'price',
        'currency',
        'estimated_delivery_at',
        'label_url',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'carrier' => ShippingCarrier::class,
            'delivery_type' => ShippingDeliveryType::class,
            'status' => ShipmentStatus::class,
            'price' => 'decimal:2',
            'estimated_delivery_at' => 'datetime',
            'raw_response' => 'array',
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
     * @return HasMany<ShipmentStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(ShipmentStatusEvent::class);
    }
}
