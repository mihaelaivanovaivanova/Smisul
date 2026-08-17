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
 * Econt Express integration against their JSON web-service API
 * (basic-auth protected, request/response shapes documented here as a
 * best-effort placeholder — see the sprint's "known limitations": this has
 * not been verified against Econt's real integration docs or a live test
 * account, since none were available. Swapping in the confirmed endpoint
 * paths and payload shape once real sandbox credentials arrive should not
 * require changing anything outside this class.
 */
class EcontShippingProvider implements ShippingProviderInterface
{
    public function __construct(private readonly ShippingProviderSettingsService $settings) {}

    public function carrier(): ShippingCarrier
    {
        return ShippingCarrier::Econt;
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
            $response = $this->client()->post('Nomenclatures/NomenclaturesService.calculate.json', [
                'cityName' => $request->city,
                'postCode' => $request->postalCode,
                'deliveryType' => $request->deliveryType->value,
                'weight' => $request->weightKg ?? 1.0,
                'declaredValue' => $request->declaredValue,
            ]);

            if ($response->successful() && $response->json('price') !== null) {
                return new ShippingQuoteData(
                    carrier: $this->carrier(),
                    deliveryType: $request->deliveryType,
                    price: (float) $response->json('price'),
                    currency: (string) $response->json('currency', 'EUR'),
                    estimatedDelivery: (string) $response->json('estimatedDelivery', '1-2 работни дни'),
                );
            }
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Econt quote request failed, using flat-rate fallback', ['error' => $exception->getMessage()]);
        }

        return $this->baseRate($request->deliveryType);
    }

    /**
     * Confirmed against Econt's real, publicly reachable demo endpoint
     * (https://demo.econt.com/ee/services/Nomenclatures/NomenclaturesService.getOffices.json)
     * — unlike quote()/createShipment()/track(), this one is not a guess:
     * it returns real nationwide office data with no credentials required.
     * The endpoint doesn't filter by city itself (cityName is accepted but
     * ignored), so filtering happens here; the nested address/city shape
     * below is the real response shape, not a placeholder.
     */
    public function offices(?string $city = null): array
    {
        try {
            $response = $this->client()->post('Nomenclatures/NomenclaturesService.getOffices.json', [
                'countryCode' => 'BGR',
                'cityName' => $city,
            ]);

            if (! $response->successful() || ! is_array($response->json('offices'))) {
                return [];
            }

            return collect($response->json('offices'))
                ->filter(fn (array $office) => $city === null || $city === '' || $this->matchesCity($office, $city))
                ->map(fn (array $office) => new ShippingOfficeData(
                    id: (string) $office['id'],
                    carrier: $this->carrier(),
                    type: ShippingDeliveryType::Office,
                    name: (string) $office['name'],
                    city: (string) ($office['address']['city']['name'] ?? $city ?? ''),
                    address: (string) ($office['address']['fullAddress'] ?? ''),
                ))
                ->values()
                ->all();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Econt offices lookup failed', ['error' => $exception->getMessage()]);

            return [];
        } catch (Throwable $exception) {
            // The real API's response shape isn't fully documented anywhere
            // we have access to — degrade to an empty list rather than a
            // 500 if a field is ever missing or shaped differently than
            // observed during development.
            Log::warning('Econt offices response had an unexpected shape', ['error' => $exception->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $office
     */
    private function matchesCity(array $office, string $city): bool
    {
        $cityNames = [
            $office['address']['city']['name'] ?? null,
            $office['address']['city']['nameEn'] ?? null,
        ];

        foreach ($cityNames as $cityName) {
            if (is_string($cityName) && mb_strtolower($cityName) === mb_strtolower($city)) {
                return true;
            }
        }

        return false;
    }

    public function createShipment(Order $order, ShippingDeliveryType $deliveryType, ?string $officeId): ShipmentData
    {
        try {
            $response = $this->client()->post('Shipments/LabelService.createLabel.json', [
                'orderNumber' => $order->order_number,
                'receiverName' => $order->customerFullName(),
                'receiverPhone' => $order->customer_phone,
                'deliveryType' => $deliveryType->value,
                'officeCode' => $officeId,
                'cityName' => $order->shipping_city,
                'postCode' => $order->shipping_postal_code,
                'address' => $order->shipping_address_line,
            ]);
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('econt', 'createShipment', $exception->getMessage());
        }

        if (! $response->successful() || $response->json('trackingNumber') === null) {
            throw ShippingProviderException::requestFailed('econt', 'createShipment', (string) $response->status());
        }

        return new ShipmentData(
            trackingNumber: (string) $response->json('trackingNumber'),
            status: ShipmentStatus::Accepted,
            labelUrl: $response->json('labelUrl'),
            rawResponse: $response->json() ?? [],
        );
    }

    public function track(string $trackingNumber): TrackingData
    {
        try {
            $response = $this->client()->post('Shipments/ShipmentStatusService.getStatuses.json', [
                'itemCode' => $trackingNumber,
            ]);
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('econt', 'track', $exception->getMessage());
        }

        if (! $response->successful()) {
            throw ShippingProviderException::requestFailed('econt', 'track', (string) $response->status());
        }

        $events = collect($response->json('events', []))
            ->map(fn (array $event) => new TrackingEventData(
                status: $this->mapStatus((string) $event['status']),
                description: $event['description'] ?? null,
                occurredAt: CarbonImmutable::parse($event['date']),
            ))
            ->values()
            ->all();

        $estimatedDelivery = $response->json('estimatedDelivery');

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
            'prepared' => ShipmentStatus::Prepared,
            'picked_up', 'pickup' => ShipmentStatus::PickedUp,
            'in_transit', 'transit' => ShipmentStatus::InTransit,
            'out_for_delivery' => ShipmentStatus::OutForDelivery,
            'delivered' => ShipmentStatus::Delivered,
            'returned' => ShipmentStatus::Returned,
            'failed', 'undelivered' => ShipmentStatus::Failed,
            default => ShipmentStatus::Pending,
        };
    }

    private function client(): PendingRequest
    {
        $credentials = $this->settings->credentialsFor('econt');

        return Http::baseUrl((string) ($credentials['base_url'] ?? ''))
            ->withBasicAuth(
                (string) ($credentials['username'] ?? ''),
                (string) ($credentials['password'] ?? ''),
            )
            ->acceptJson()
            ->timeout(5);
    }
}
