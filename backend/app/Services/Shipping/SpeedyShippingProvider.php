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
 * Speedy integration against their real Web API, verified end-to-end
 * against the sandbox with live test credentials (account 1996549) —
 * quote, office lookup, shipment creation, and tracking have all returned
 * real data, not just documented-but-unverified shapes. Credentials are
 * embedded as `userName`/`password` fields in every request body, not an
 * HTTP auth header, and every endpoint requires them — including office
 * lookups, unlike Econt's public nomenclature endpoint.
 *
 * Two endpoints, two different field shapes for the same concepts —
 * confirmed by trial against the real API, not guessed:
 *  - `calculate` (quote): `recipient.addressLocation` + `recipient.privatePerson`
 *    + `service.serviceIds` (array).
 *  - `shipment` (createShipment): `recipient.address` + `service.serviceId`
 *    (singular), and `address` needs a non-empty `streetNo` — see
 *    splitStreetAndNumber() for how a single free-text address line gets
 *    split to satisfy that.
 *
 * `serviceId` 505 is confirmed valid for this account (returns real prices
 * and creates real test shipments) — no longer a placeholder guess.
 * Independent of EcontShippingProvider/BoxNowShippingProvider by design —
 * no shared base class.
 */
class SpeedyShippingProvider implements ShippingProviderInterface
{
    public function __construct(private readonly ShippingProviderSettingsService $settings) {}

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
                    'privatePerson' => true,
                    'addressLocation' => [
                        'siteName' => $request->city,
                        'postCode' => $request->postalCode,
                    ],
                ],
                'service' => ['serviceIds' => [self::SERVICE_ID]],
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
                    estimatedDelivery: $this->formatDeliveryDeadline($response->json('calculations.0.deliveryDeadline')),
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
                // Speedy's location/office list mixes staffed offices in
                // with APT entries (automated parcel terminals/machines) —
                // the checkout office picker only wants staffed offices.
                ->filter(fn (array $office) => ($office['type'] ?? 'OFFICE') === 'OFFICE')
                ->map(fn (array $office) => new ShippingOfficeData(
                    id: (string) $office['id'],
                    carrier: $this->carrier(),
                    type: ShippingDeliveryType::Office,
                    name: (string) $office['name'],
                    // The office's own site name (e.g. "СОФИЯ"), not the
                    // $city filter — the two used to always match because
                    // this only ran with a city filter applied, but an
                    // unfiltered nationwide lookup (no $city) needs the
                    // real per-office city to group by, same as Econt
                    // already does above.
                    city: (string) ($office['address']['siteName'] ?? $city ?? ''),
                    address: (string) ($office['address']['fullAddressString'] ?? ''),
                ))
                ->values()
                ->all();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Speedy offices lookup failed', ['error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            // The mapping above is confirmed against real office data (see
            // class docblock) — this only catches a genuinely unexpected
            // shape, degrading to an empty list rather than a 500.
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
            [$streetName, $streetNo] = $this->splitStreetAndNumber($order->shipping_address_line);

            $recipient['address'] = [
                'siteName' => $order->shipping_city,
                'postCode' => $order->shipping_postal_code,
                'streetName' => $streetName,
                'streetNo' => $streetNo,
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
     * Checkout only captures one free-text address line (see
     * shipping_address_line), but Speedy's real `shipment` endpoint
     * rejects an address without a non-empty streetNo — it isn't willing
     * to take a single unstructured line the way `calculate` will. Pulls
     * a trailing house number (e.g. "1", "25А", "5B") off the line as
     * streetNo; if none is found, the whole line becomes streetName and
     * streetNo falls back to "0" so the request stays deliverable-shaped
     * rather than failing outright. Confirmed against the real sandbox —
     * see the Sprint 11.5 Speedy integration notes.
     *
     * @return array{0: string, 1: string}
     */
    private function splitStreetAndNumber(string $addressLine): array
    {
        $addressLine = trim($addressLine);

        if (preg_match('/^(.*?)[\s,]+(\d+[\p{L}]?)$/u', $addressLine, $matches) === 1) {
            return [trim($matches[1]), $matches[2]];
        }

        return [$addressLine, '0'];
    }

    /**
     * Speedy's `calculate` endpoint returns a precise ISO deadline
     * (e.g. "2026-07-07T19:00:00+0300"), not a display-ready string like
     * Econt/BOX NOW's mocked responses — showing that raw value verbatim
     * in checkout looked like a bug. Falls back to the same generic range
     * the other carriers use if the value is missing or unparsable.
     */
    private function formatDeliveryDeadline(mixed $deadline): string
    {
        if (! is_string($deadline) || $deadline === '') {
            return '1-2 работни дни';
        }

        try {
            return 'до '.CarbonImmutable::parse($deadline)->format('d.m.Y');
        } catch (Throwable) {
            return '1-2 работни дни';
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function withCredentials(array $body): array
    {
        $credentials = $this->settings->credentialsFor('speedy');

        return [
            'userName' => (string) ($credentials['username'] ?? ''),
            'password' => (string) ($credentials['password'] ?? ''),
            // Bulgarian-language office names/addresses — this storefront
            // has no English UI to match an 'EN' response against.
            'language' => 'BG',
            ...$body,
        ];
    }

    private function client(): PendingRequest
    {
        $credentials = $this->settings->credentialsFor('speedy');

        return Http::baseUrl((string) ($credentials['base_url'] ?? ''))
            ->acceptJson()
            ->timeout(5);
    }
}
