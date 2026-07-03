<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\ShippingCarrier;
use Carbon\Carbon;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property OrderStatus $status
 * @property ShippingCarrier $shipping_carrier
 * @property Carbon $created_at
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'guest_access_token',
        'status',
        'currency',
        'customer_first_name',
        'customer_last_name',
        'customer_email',
        'customer_phone',
        'customer_company',
        'customer_vat_number',
        'delivery_notes',
        'shipping_country',
        'shipping_city',
        'shipping_postal_code',
        'shipping_address_line',
        'shipping_apartment',
        'shipping_carrier',
        'shipping_method_label',
        'shipping_price',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_carrier' => ShippingCarrier::class,
            'shipping_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderLegalAcceptance, $this>
     */
    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(OrderLegalAcceptance::class);
    }

    public function isGuestOrder(): bool
    {
        return $this->user_id === null;
    }

    public function customerFullName(): string
    {
        return trim("{$this->customer_first_name} {$this->customer_last_name}");
    }
}
