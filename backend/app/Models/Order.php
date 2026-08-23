<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use Carbon\Carbon;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property OrderStatus $status
 * @property ShippingCarrier $shipping_carrier
 * @property ?ShippingDeliveryType $shipping_delivery_type
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
        'wants_invoice',
        'invoice_number',
        'invoice_issued_at',
        'delivery_notes',
        'shipping_country',
        'shipping_city',
        'shipping_postal_code',
        'shipping_address_line',
        'shipping_apartment',
        'billing_same_as_shipping',
        'billing_country',
        'billing_city',
        'billing_postal_code',
        'billing_address_line',
        'billing_apartment',
        'shipping_carrier',
        'shipping_delivery_type',
        'shipping_office_id',
        'shipping_office_name',
        'shipping_method_label',
        'shipping_price',
        'shipping_rate_snapshot',
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
            'shipping_delivery_type' => ShippingDeliveryType::class,
            'billing_same_as_shipping' => 'boolean',
            'wants_invoice' => 'boolean',
            'invoice_issued_at' => 'datetime',
            'shipping_price' => 'decimal:2',
            'shipping_rate_snapshot' => 'array',
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
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<OrderLegalAcceptance, $this>
     */
    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(OrderLegalAcceptance::class);
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * @return HasOne<Shipment, $this>
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    /**
     * A HasMany, not a HasOne — retrying a failed/cancelled payment mints a
     * fresh attempt rather than reusing the old row (see PaymentService),
     * so an order can genuinely have several. Admins see the full history;
     * PaymentService::latestForOrder() is still the source of truth for
     * "what's the current one".
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isGuestOrder(): bool
    {
        return $this->user_id === null;
    }

    public function customerFullName(): string
    {
        return trim("{$this->customer_first_name} {$this->customer_last_name}");
    }

    /**
     * чл. 54, ал. 2 ЗЗП caps a withdrawal refund's delivery-cost component
     * at the least costly standard delivery option offered at the time of
     * THIS order - not whatever's cheapest today. Reads from
     * shipping_rate_snapshot (captured once, at placement, in
     * OrderService::placeOrder) rather than re-querying current carrier
     * prices, which may have changed since (promos ending, admin price
     * edits). Returns null for orders placed before this snapshot existed
     * - there's no way to reconstruct history for those retroactively.
     */
    public function cheapestStandardShippingPriceAtPlacement(): ?float
    {
        if ($this->shipping_rate_snapshot === null || $this->shipping_rate_snapshot === []) {
            return null;
        }

        return min(array_column($this->shipping_rate_snapshot, 'price'));
    }
}
