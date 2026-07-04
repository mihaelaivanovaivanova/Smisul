<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProviderInterface;
use App\DataTransferObjects\Shipping\ShipmentData;
use App\DataTransferObjects\Shipping\ShippingOfficeData;
use App\DataTransferObjects\Shipping\ShippingQuoteData;
use App\DataTransferObjects\Shipping\ShippingQuoteRequestData;
use App\DataTransferObjects\Shipping\TrackingData;
use App\DataTransferObjects\Shipping\TrackingEventData;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Exceptions\Shipping\ShippingProviderException;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Speedy integration against their REST API (username/password exchanged
 * for a session, per Speedy's real-world v1 API shape) — request/response
 * shapes here are a best-effort placeholder, not verified against Speedy's
 * actual integration docs or a live test account (see the sprint's "known
 * limitations"). Independent of EcontShippingProvider/BoxNowShippingProvider
 * by design — no shared base class.
 */
class SpeedyShippingProvider implements ShippingProviderInterface
{
    public function carrier(): ShippingCarrier
    {
        return ShippingCarrier::Speedy;
    }

    public function supportedDeliveryTypes(): array
    {
        return [ShippingDeliveryType::Office, ShippingDeliveryType::Address];
    }

    public function baseRate(ShippingDeliveryType $deliveryType): ShippingQuoteData
    {
        return new ShippingQuoteData(
            carrier: $this->carrier(),
            deliveryType: $deliveryType,
            price: 5.99,
            currency: 'EUR',
            estimatedDelivery: '1-2 работни дни',
        );
    }

    public function quote(ShippingQuoteRequestData $request): ShippingQuoteData
    {
        try {
            $response = $this->client()->post('calculate', [
                'recipient' => [
                    'address' => [
                        'siteName' => $request->city,
                        'postCode' => $request->postalCode,
                    ],
                    'dropoffOffice' => $request->deliveryType === ShippingDeliveryType::Office,
                ],
                'service' => ['serviceId' => 505],
                'content' => ['parcelsCount' => 1, 'totalWeight' => $request->weightKg ?? 1.0],
                'payment' => ['courierServicePayer' => 'SENDER'],
            ]);

            if ($response->successful() && $response->json('price.total') !== null) {
                return new ShippingQuoteData(
                    carrier: $this->carrier(),
                    deliveryType: $request->deliveryType,
                    price: (float) $response->json('price.total'),
                    currency: (string) $response->json('price.currency', 'EUR'),
                    estimatedDelivery: (string) $response->json('estimatedDeliveryTime', '1-2 работни дни'),
                );
            }
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Speedy quote request failed, using flat-rate fallback', ['error' => $exception->getMessage()]);
        }

        return $this->baseRate($request->deliveryType);
    }

    public function offices(?string $city = null): array
    {
        try {
            $response = $this->client()->get('location/office', array_filter(['siteName' => $city]));

            if ($response->successful() && is_array($response->json('offices'))) {
                return collect($response->json('offices'))
                    ->map(fn (array $office) => new ShippingOfficeData(
                        id: (string) $office['id'],
                        carrier: $this->carrier(),
                        type: ShippingDeliveryType::Office,
                        name: (string) $office['name'],
                        city: (string) ($office['siteName'] ?? $city ?? ''),
                        address: (string) ($office['address'] ?? ''),
                    ))
                    ->values()
                    ->all();
            }
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Speedy offices lookup failed', ['error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            // The real API's response shape has not been verified (see the
            // sprint's "known limitations") — degrade to an empty list
            // rather than a 500 if a field is missing or shaped
            // differently than assumed here.
            Log::warning('Speedy offices response had an unexpected shape', ['error' => $exception->getMessage()]);
        }

        return [];
    }

    public function createShipment(Order $order, ShippingDeliveryType $deliveryType, ?string $officeId): ShipmentData
    {
        try {
            $response = $this->client()->post('shipment', [
                'recipient' => [
                    'phone1' => ['number' => $order->customer_phone],
                    'clientName' => $order->customerFullName(),
                    'address' => [
                        'siteName' => $order->shipping_city,
                        'postCode' => $order->shipping_postal_code,
                        'fullAddressString' => $order->shipping_address_line,
                    ],
                    'dropoffOfficeId' => $deliveryType === ShippingDeliveryType::Office ? $officeId : null,
                ],
                'reference' => $order->order_number,
            ]);
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('speedy', 'createShipment', $exception->getMessage());
        }

        if (! $response->successful() || $response->json('parcels.0.parcelId') === null) {
            throw ShippingProviderException::requestFailed('speedy', 'createShipment', (string) $response->status());
        }

        return new ShipmentData(
            trackingNumber: (string) $response->json('parcels.0.parcelId'),
            status: ShipmentStatus::Accepted,
            labelUrl: $response->json('label.url'),
            rawResponse: $response->json() ?? [],
        );
    }

    public function track(string $trackingNumber): TrackingData
    {
        try {
            $response = $this->client()->post('track', [
                'parcels' => [['id' => $trackingNumber]],
            ]);
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('speedy', 'track', $exception->getMessage());
        }

        if (! $response->successful()) {
            throw ShippingProviderException::requestFailed('speedy', 'track', (string) $response->status());
        }

        $events = collect($response->json('parcels.0.operations', []))
            ->map(fn (array $event) => new TrackingEventData(
                status: $this->mapStatus((string) $event['status']),
                description: $event['description'] ?? null,
                occurredAt: CarbonImmutable::parse($event['date']),
            ))
            ->values()
            ->all();

        $estimatedDelivery = $response->json('parcels.0.estimatedDeliveryDate');

        return new TrackingData(
            currentStatus: $events === [] ? ShipmentStatus::Pending : end($events)->status,
            events: $events,
            estimatedDeliveryAt: $estimatedDelivery !== null ? CarbonImmutable::parse($estimatedDelivery) : null,
        );
    }

    private function mapStatus(string $rawStatus): ShipmentStatus
    {
        return match ($rawStatus) {
            'accepted' => ShipmentStatus::Accepted,
            'prepared', 'sorted' => ShipmentStatus::Prepared,
            'picked_up' => ShipmentStatus::PickedUp,
            'in_transit', 'forwarded' => ShipmentStatus::InTransit,
            'out_for_delivery', 'loaded_for_delivery' => ShipmentStatus::OutForDelivery,
            'delivered' => ShipmentStatus::Delivered,
            'returned' => ShipmentStatus::Returned,
            'failed', 'refused' => ShipmentStatus::Failed,
            default => ShipmentStatus::Pending,
        };
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.shipping.speedy.base_url'))
            ->withBasicAuth(
                (string) config('services.shipping.speedy.username'),
                (string) config('services.shipping.speedy.password'),
            )
            ->acceptJson()
            ->timeout(5);
    }
}
