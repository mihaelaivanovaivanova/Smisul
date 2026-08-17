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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BOX NOW is a locker-only network (no office or home delivery) — the only
 * provider whose supportedDeliveryTypes() is a single entry. Auth is OAuth2
 * client-credentials, per BOX NOW's real-world API shape; the access token
 * is cached for its lifetime rather than fetched per request. Request/
 * response shapes are a best-effort placeholder — not verified against BOX
 * NOW's actual docs or a live sandbox account (see the sprint's "known
 * limitations"). Independent of EcontShippingProvider/SpeedyShippingProvider
 * by design — no shared base class.
 */
class BoxNowShippingProvider implements ShippingProviderInterface
{
    public function __construct(private readonly ShippingProviderSettingsService $settings) {}

    public function carrier(): ShippingCarrier
    {
        return ShippingCarrier::BoxNow;
    }

    public function supportedDeliveryTypes(): array
    {
        return [ShippingDeliveryType::Locker];
    }

    public function baseRate(ShippingDeliveryType $deliveryType): ShippingQuoteData
    {
        return new ShippingQuoteData(
            carrier: $this->carrier(),
            deliveryType: $deliveryType,
            price: 4.99,
            currency: 'EUR',
            estimatedDelivery: '1-2 работни дни',
        );
    }

    public function quote(ShippingQuoteRequestData $request): ShippingQuoteData
    {
        try {
            $response = $this->client()->post('v1/parcels/quote', [
                'city' => $request->city,
                'postalCode' => $request->postalCode,
                'weight' => $request->weightKg ?? 1.0,
            ]);

            if ($response->successful() && $response->json('price') !== null) {
                return new ShippingQuoteData(
                    carrier: $this->carrier(),
                    deliveryType: ShippingDeliveryType::Locker,
                    price: (float) $response->json('price'),
                    currency: (string) $response->json('currency', 'EUR'),
                    estimatedDelivery: (string) $response->json('estimatedDelivery', '1-2 работни дни'),
                );
            }
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('BOX NOW quote request failed, using flat-rate fallback', ['error' => $exception->getMessage()]);
        }

        return $this->baseRate(ShippingDeliveryType::Locker);
    }

    public function offices(?string $city = null): array
    {
        try {
            $response = $this->client()->get('v1/lockers', array_filter(['city' => $city]));

            if ($response->successful() && is_array($response->json('lockers'))) {
                return collect($response->json('lockers'))
                    ->map(fn (array $locker) => new ShippingOfficeData(
                        id: (string) $locker['id'],
                        carrier: $this->carrier(),
                        type: ShippingDeliveryType::Locker,
                        name: (string) $locker['name'],
                        city: (string) ($locker['city'] ?? $city ?? ''),
                        address: (string) ($locker['address'] ?? ''),
                    ))
                    ->values()
                    ->all();
            }
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('BOX NOW lockers lookup failed', ['error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            // The real API's response shape has not been verified (see the
            // sprint's "known limitations") — degrade to an empty list
            // rather than a 500 if a field is missing or shaped
            // differently than assumed here.
            Log::warning('BOX NOW lockers response had an unexpected shape', ['error' => $exception->getMessage()]);
        }

        return [];
    }

    public function createShipment(Order $order, ShippingDeliveryType $deliveryType, ?string $officeId): ShipmentData
    {
        try {
            $response = $this->client()->post('v1/parcels', [
                'lockerId' => $officeId,
                'reference' => $order->order_number,
                'recipient' => [
                    'name' => $order->customerFullName(),
                    'phone' => $order->customer_phone,
                    'email' => $order->customer_email,
                ],
            ]);
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('box_now', 'createShipment', $exception->getMessage());
        }

        if (! $response->successful() || $response->json('trackingNumber') === null) {
            throw ShippingProviderException::requestFailed('box_now', 'createShipment', (string) $response->status());
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
            $response = $this->client()->get("v1/parcels/{$trackingNumber}/tracking");
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('box_now', 'track', $exception->getMessage());
        }

        if (! $response->successful()) {
            throw ShippingProviderException::requestFailed('box_now', 'track', (string) $response->status());
        }

        $events = collect($response->json('events', []))
            ->map(fn (array $event) => new TrackingEventData(
                status: $this->mapStatus((string) $event['status']),
                description: $event['description'] ?? null,
                occurredAt: CarbonImmutable::parse($event['timestamp']),
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
            'prepared', 'awaiting_pickup' => ShipmentStatus::Prepared,
            'picked_up' => ShipmentStatus::PickedUp,
            'in_transit' => ShipmentStatus::InTransit,
            'in_locker', 'out_for_delivery' => ShipmentStatus::OutForDelivery,
            'collected', 'delivered' => ShipmentStatus::Delivered,
            'returned', 'expired' => ShipmentStatus::Returned,
            'failed' => ShipmentStatus::Failed,
            default => ShipmentStatus::Pending,
        };
    }

    private function client(): PendingRequest
    {
        $credentials = $this->settings->credentialsFor('box_now');

        return Http::baseUrl((string) ($credentials['base_url'] ?? ''))
            ->withToken($this->accessToken($credentials))
            ->acceptJson()
            ->timeout(5);
    }

    /** @param array<string, mixed> $credentials */
    private function accessToken(array $credentials): string
    {
        // Keyed by client_id so a credential change (via the admin settings
        // panel) starts fetching a fresh token immediately instead of
        // reusing one cached under the old credentials for up to 50 min.
        $cacheKey = 'shipping.box_now.access_token.'.md5((string) ($credentials['client_id'] ?? ''));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials) {
            try {
                $response = Http::baseUrl((string) ($credentials['base_url'] ?? ''))
                    ->asForm()
                    ->timeout(5)
                    ->post('oauth/token', [
                        'grant_type' => 'client_credentials',
                        'client_id' => (string) ($credentials['client_id'] ?? ''),
                        'client_secret' => (string) ($credentials['client_secret'] ?? ''),
                    ]);

                return (string) $response->json('access_token', '');
            } catch (ConnectionException|RequestException) {
                return '';
            }
        });
    }
}
