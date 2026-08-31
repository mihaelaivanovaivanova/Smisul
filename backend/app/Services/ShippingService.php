<?php

namespace App\Services;

use App\Contracts\ShippingProviderInterface;
use App\DataTransferObjects\Shipping\ShippingMethodData;
use App\DataTransferObjects\Shipping\ShippingOfficeData;
use App\DataTransferObjects\Shipping\ShippingQuoteData;
use App\DataTransferObjects\Shipping\ShippingQuoteRequestData;
use App\DataTransferObjects\Shipping\TrackingData;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Models\Order;
use App\Models\Shipment;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The single seam between the rest of the app and the two independent
 * courier integrations (see ShippingProviderInterface) — mirrors how
 * PaymentService sits in front of PaymentGatewayInterface for payments.
 * Nothing outside this class ever talks to Speedy/BOX NOW directly.
 */
class ShippingService
{
    /**
     * @param  array<string, ShippingProviderInterface>  $providers  keyed by ShippingCarrier::value (see ShippingServiceProvider)
     */
    public function __construct(private readonly array $providers) {}

    /**
     * The checkout catalog: every (carrier, delivery type) combination
     * currently offered, at each provider's flat advertised rate — no
     * network calls, so listing methods never depends on carrier API
     * reachability (see ShippingProviderInterface::baseRate()).
     *
     * @return list<ShippingMethodData>
     */
    public function availableMethods(): array
    {
        $methods = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->supportedDeliveryTypes() as $deliveryType) {
                $rate = $provider->baseRate($deliveryType);

                $methods[] = new ShippingMethodData(
                    carrier: $provider->carrier(),
                    deliveryType: $deliveryType,
                    label: $this->label($provider->carrier(), $deliveryType),
                    description: $this->description($provider->carrier(), $deliveryType),
                    price: $rate->price,
                    currency: $rate->currency,
                    estimatedDelivery: $rate->estimatedDelivery,
                    requiresOffice: $deliveryType->requiresOfficeSelection(),
                );
            }
        }

        return $methods;
    }

    public function find(string $carrier, ?string $deliveryType = null): ?ShippingMethodData
    {
        foreach ($this->availableMethods() as $method) {
            if ($method->carrier->value !== $carrier) {
                continue;
            }

            if ($deliveryType !== null && $method->deliveryType->value !== $deliveryType) {
                continue;
            }

            return $method;
        }

        return null;
    }

    /**
     * A live, destination-aware price — falls back to the flat rate inside
     * the provider itself if the carrier's API is unreachable (see
     * ShippingProviderInterface::quote()).
     */
    public function quote(string $carrier, ShippingQuoteRequestData $request): ShippingQuoteData
    {
        return $this->providerFor($carrier)->quote($request);
    }

    /**
     * @return list<ShippingOfficeData>
     */
    public function offices(string $carrier, ?string $city = null): array
    {
        return $this->providerFor($carrier)->offices($city);
    }

    /**
     * Requests a real shipment from the carrier the order's checkout
     * selection points at, and persists the result. Called automatically
     * by Listeners\CreateShipmentOnOrderPaid the moment an order reaches
     * OrderStatus::Paid, and also exposed as a manual admin action
     * (Admin\OrderController::createShipment) for retrying a failed
     * automatic attempt or dispatching a historical order placed before
     * this existed.
     */
    public function createShipment(Order $order): Shipment
    {
        if ($order->shipment()->exists()) {
            throw new RuntimeException("Order {$order->order_number} already has a shipment.");
        }

        $deliveryType = $order->shipping_delivery_type ?? ShippingDeliveryType::Address;
        $provider = $this->providerFor($order->shipping_carrier->value);

        $result = $provider->createShipment($order, $deliveryType, $order->shipping_office_id);

        return DB::transaction(function () use ($order, $deliveryType, $result) {
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'carrier' => $order->shipping_carrier,
                'delivery_type' => $deliveryType,
                'office_id' => $order->shipping_office_id,
                'office_name' => $order->shipping_office_name,
                'tracking_number' => $result->trackingNumber,
                'status' => $result->status,
                'price' => $order->shipping_price,
                'currency' => $order->currency,
                'label_url' => $result->labelUrl,
                'raw_response' => $result->rawResponse,
            ]);

            $shipment->statusEvents()->create([
                'status' => $result->status,
                'description' => null,
                'occurred_at' => now(),
                'raw_payload' => $result->rawResponse,
            ]);

            return $shipment;
        });
    }

    /**
     * Polls the carrier for the latest tracking history and, if the status
     * has moved on, records it (see recordStatusUpdate()).
     */
    public function track(Shipment $shipment): TrackingData
    {
        if ($shipment->tracking_number === null) {
            throw new RuntimeException("Shipment {$shipment->id} has no tracking number yet.");
        }

        $tracking = $this->providerFor($shipment->carrier->value)->track($shipment->tracking_number);

        if ($tracking->currentStatus !== $shipment->status) {
            $this->recordStatusUpdate($shipment, $tracking->currentStatus, null, now());
        }

        if ($tracking->estimatedDeliveryAt !== null && $shipment->estimated_delivery_at === null) {
            $shipment->update(['estimated_delivery_at' => $tracking->estimatedDeliveryAt]);
        }

        return $tracking;
    }

    /**
     * Raw PDF bytes of the shipment's dispatch label — fetched fresh from
     * the carrier on every call (see ShippingProviderInterface::fetchLabel()
     * on why neither carrier's label is a storable static URL).
     */
    public function fetchLabel(Shipment $shipment): string
    {
        if ($shipment->tracking_number === null) {
            throw new RuntimeException("Shipment {$shipment->id} has no tracking number yet.");
        }

        return $this->providerFor($shipment->carrier->value)->fetchLabel($shipment->tracking_number);
    }

    /**
     * Manually records a status transition — the seam a future admin
     * action (or a webhook, if a carrier ever offers one) would call
     * directly instead of polling track().
     */
    public function recordStatusUpdate(
        Shipment $shipment,
        ShipmentStatus $status,
        ?string $description,
        DateTimeInterface $occurredAt,
    ): Shipment {
        return DB::transaction(function () use ($shipment, $status, $description, $occurredAt) {
            $shipment->update(['status' => $status]);

            $shipment->statusEvents()->create([
                'status' => $status,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ]);

            return $shipment->fresh();
        });
    }

    private function providerFor(string $carrier): ShippingProviderInterface
    {
        return $this->providers[$carrier] ?? throw new InvalidArgumentException("Unknown shipping carrier [{$carrier}].");
    }

    /**
     * Bare carrier name by default (e.g. "BOX NOW", or "Speedy" for its
     * office pickup) — a suffix is only added where a carrier offers more
     * than one delivery type that would otherwise share the same label.
     * BOX NOW never needs one (locker is its only type); Speedy does, now
     * that it offers both a staffed office and an automated machine
     * alongside home delivery.
     */
    private function label(ShippingCarrier $carrier, ShippingDeliveryType $deliveryType): string
    {
        return match (true) {
            $deliveryType === ShippingDeliveryType::Address => "{$carrier->label()} (до адрес)",
            $deliveryType === ShippingDeliveryType::Locker && $carrier === ShippingCarrier::Speedy => "{$carrier->label()} (автомат)",
            default => $carrier->label(),
        };
    }

    private function description(ShippingCarrier $carrier, ShippingDeliveryType $deliveryType): string
    {
        return match ($deliveryType) {
            ShippingDeliveryType::Office => "Доставка до офис на {$carrier->label()}.",
            ShippingDeliveryType::Locker => "Доставка до автомат на {$carrier->label()}.",
            ShippingDeliveryType::Address => "Доставка до адрес с {$carrier->label()}.",
        };
    }
}
