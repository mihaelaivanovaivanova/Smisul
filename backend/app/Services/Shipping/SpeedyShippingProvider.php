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
 * Speedy integration against their real, publicly documented Web API
 * (confirmed live at https://api.speedy.bg/api/docs/ during development —
 * unlike a guessed shape, this one is verified against the actual reference:
 * credentials are embedded as `userName`/`password` fields in every request
 * body, not an HTTP auth header, and every endpoint requires them —
 * including office lookups, unlike Econt's public nomenclature endpoint.
 * Without real Speedy credentials, every call here fails authentication and
 * degrades to the documented fallback (flat rate for quote(), empty list
 * for offices()) — this is expected, not a bug, until real credentials are
 * configured (see the sprint's "known limitations").
 *
 * `serviceId` (505) is an unverified placeholder — the real value must be
 * confirmed against the merchant's actual Speedy contract once credentials
 * exist. Independent of EcontShippingProvider/BoxNowShippingProvider by
 * design — no shared base class.
 */
class SpeedyShippingProvider implements ShippingProviderInterface
{
    private const SERVICE_ID = 505;

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
            $response = $this->client()->post('calculate', $this->withCredentials([
                'recipient' => [
                    'address' => [
                        'siteName' => $request->city,
                        'postCode' => $request->postalCode,
                    ],
                ],
                'service' => ['serviceId' => self::SERVICE_ID],
                'content' => [
                    'parcelsCount' => 1,
                    'totalWeight' => $request->weightKg ?? 1.0,
                    'contents' => 'Merchandise',
                    'package' => 'BOX',
                ],
                'payment' => ['courierServicePayer' => 'SENDER'],
            ]));

            $price = $response->json('calculations.0.price');

            if ($response->successful() && is_array($price) && isset($price['total'])) {
                return new ShippingQuoteData(
                    carrier: $this->carrier(),
                    deliveryType: $request->deliveryType,
                    price: (float) $price['total'],
                    currency: (string) ($price['currency'] ?? 'EUR'),
                    estimatedDelivery: (string) ($response->json('calculations.0.deliveryDeadline') ?? '1-2 работни дни'),
                );
            }
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Speedy quote request failed, using flat-rate fallback', ['error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            Log::warning('Speedy quote response had an unexpected shape', ['error' => $exception->getMessage()]);
        }

        return $this->baseRate($request->deliveryType);
    }

    public function offices(?string $city = null): array
    {
        try {
            $response = $this->client()->post('location/office', $this->withCredentials(
                array_filter(['siteName' => $city]),
            ));

            if (! $response->successful() || ! is_array($response->json('offices'))) {
                return [];
            }

            return collect($response->json('offices'))
                ->map(fn (array $office) => new ShippingOfficeData(
                    id: (string) $office['id'],
                    carrier: $this->carrier(),
                    type: ShippingDeliveryType::Office,
                    name: (string) $office['name'],
                    city: (string) ($city ?? ''),
                    address: (string) ($office['address']['fullAddressString'] ?? ''),
                ))
                ->values()
                ->all();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Speedy offices lookup failed', ['error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            // The full real response shape is documented (see class
            // docblock), but without real credentials this has never
            // actually returned office data — degrade to an empty list
            // rather than a 500 if anything is still off.
            Log::warning('Speedy offices response had an unexpected shape', ['error' => $exception->getMessage()]);
        }

        return [];
    }

    public function createShipment(Order $order, ShippingDeliveryType $deliveryType, ?string $officeId): ShipmentData
    {
        $recipient = [
            'clientName' => $order->customerFullName(),
            'privatePerson' => true,
            'phone1' => ['number' => $order->customer_phone],
        ];

        // Office and address recipients are mutually exclusive per Speedy's
        // real API (address is forbidden when pickupOfficeId is set).
        if ($deliveryType === ShippingDeliveryType::Office && $officeId !== null) {
            $recipient['pickupOfficeId'] = (int) $officeId;
        } else {
            $recipient['address'] = [
                'siteName' => $order->shipping_city,
                'postCode' => $order->shipping_postal_code,
            ];
        }

        try {
            $response = $this->client()->post('shipment', $this->withCredentials([
                'recipient' => $recipient,
                'service' => ['serviceId' => self::SERVICE_ID],
                'content' => [
                    'parcelsCount' => 1,
                    'totalWeight' => 1.0,
                    'contents' => 'Merchandise',
                    'package' => 'BOX',
                ],
                'payment' => ['courierServicePayer' => 'SENDER'],
                'ref1' => $order->order_number,
            ]));
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('speedy', 'createShipment', $exception->getMessage());
        }

        $trackingNumber = $response->json('id') ?? $response->json('parcels.0.parcelId');

        if (! $response->successful() || $trackingNumber === null) {
            throw ShippingProviderException::requestFailed('speedy', 'createShipment', (string) $response->status());
        }

        return new ShipmentData(
            trackingNumber: (string) $trackingNumber,
            status: ShipmentStatus::Accepted,
            labelUrl: null,
            rawResponse: $response->json() ?? [],
        );
    }

    public function track(string $trackingNumber): TrackingData
    {
        try {
            $response = $this->client()->post('track', $this->withCredentials([
                'parcels' => [['parcelId' => $trackingNumber]],
            ]));
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('speedy', 'track', $exception->getMessage());
        }

        if (! $response->successful()) {
            throw ShippingProviderException::requestFailed('speedy', 'track', (string) $response->status());
        }

        $events = collect($response->json('parcels.0.operations', []))
            ->map(fn (array $event) => new TrackingEventData(
                status: $this->mapStatus((int) $event['operationCode']),
                description: $event['description'] ?? null,
                occurredAt: CarbonImmutable::parse($event['dateTime']),
            ))
            ->values()
            ->all();

        return new TrackingData(
            currentStatus: $events === [] ? ShipmentStatus::Pending : end($events)->status,
            events: $events,
            estimatedDeliveryAt: null,
        );
    }

    /**
     * Maps Speedy's real Track And Trace operation codes (Appendix 1 of
     * their API docs) onto our shared ShipmentStatus vocabulary.
     */
    private function mapStatus(int $operationCode): ShipmentStatus
    {
        return match ($operationCode) {
            39 => ShipmentStatus::PickedUp,
            1, 2, 21 => ShipmentStatus::InTransit,
            11 => ShipmentStatus::Prepared,
            12 => ShipmentStatus::OutForDelivery,
            -14 => ShipmentStatus::Delivered,
            38, 111 => ShipmentStatus::Returned,
            44, 123 => ShipmentStatus::Failed,
            default => ShipmentStatus::Pending,
        };
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function withCredentials(array $body): array
    {
        return [
            'userName' => (string) config('services.shipping.speedy.username'),
            'password' => (string) config('services.shipping.speedy.password'),
            'language' => 'EN',
            ...$body,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.shipping.speedy.base_url'))
            ->acceptJson()
            ->timeout(5);
    }
}
