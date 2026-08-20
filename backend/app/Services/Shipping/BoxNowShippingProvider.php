<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProviderInterface;
use App\DataTransferObjects\Shipping\ShipmentData;
use App\DataTransferObjects\Shipping\ShippingOfficeData;
use App\DataTransferObjects\Shipping\ShippingQuoteData;
use App\DataTransferObjects\Shipping\ShippingQuoteRequestData;
use App\DataTransferObjects\Shipping\TrackingData;
use App\DataTransferObjects\Shipping\TrackingEventData;
use App\Enums\PaymentMethod;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Exceptions\Shipping\ShippingProviderException;
use App\Models\Order;
use App\Services\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BOX NOW's real Partner API — confirmed against their official integration
 * guide (PDF linked from https://boxnow.bg/partner-api, "Ръководство за
 * работа с API на BOX NOW" v1.65) and live production credentials, not a
 * placeholder:
 *
 *  - Auth: OAuth2 client-credentials against POST auth-sessions — a JSON
 *    body, unlike Speedy's form-encoded oauth/token — returning
 *    {access_token, token_type, expires_in}. The guide's own example shows
 *    expires_in: 3600 (1 hour); cached for 50 minutes as a safety margin,
 *    same pattern as before.
 *  - Lockers: GET destinations returns every BOX NOW locker (APM)
 *    nationwide in one call, with no confirmed server-side name/city
 *    filter — so, like the Speedy settlement list, this always fetches the
 *    full list and lets the frontend's own city/office pickers filter it
 *    client-side; the $city parameter here is accepted (interface
 *    requirement) but unused.
 *  - Shipment creation: POST delivery-requests. This storefront has no
 *    registered BOX NOW warehouse — orders are fulfilled by dropping the
 *    parcel off at a locker in person, and "any-apm" is BOX NOW's own
 *    documented origin locationId for exactly that flow (see the
 *    BOX_NOW_ORIGIN_LOCATION_ID config default below). paymentMode is
 *    "prepaid" for every order except cash-on-delivery ones (only ever
 *    possible for this carrier — see PaymentService::availablePaymentMethods()),
 *    which send "cod" plus the real amountToBeCollected — confirmed
 *    against guide v1.69, section 4.4/4.8 (valid values: prepaid, cod;
 *    amountToBeCollected must be a number in (0, 5000) whenever cod is
 *    used).
 *  - Quote: BOX NOW's guide has no live pricing endpoint at all — the flat
 *    rate in baseRate() IS the real (contractual) price, not a guessed
 *    fallback, so quote() never makes a network call.
 *  - Tracking: GET parcels?parcelId={id}; state vocabulary matches the
 *    guide's section 4.5 (new/in-transit/in-depot/final-destination/
 *    delivered/returned/cancelled/wait-for-load/...).
 *
 * BOX NOW is locker-only (no office or home delivery) — the only provider
 * whose supportedDeliveryTypes() is a single entry. Independent of
 * SpeedyShippingProvider by design — no shared base class.
 */
class BoxNowShippingProvider implements ShippingProviderInterface
{
    public function __construct(
        private readonly ShippingProviderSettingsService $settings,
        private readonly SettingService $storeSettings,
    ) {}

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
            price: $this->settings->priceFor('box_now', $deliveryType) ?? 4.99,
            currency: 'EUR',
            estimatedDelivery: '1-2 работни дни',
        );
    }

    /**
     * BOX NOW's Partner API has no live quote/pricing endpoint (confirmed
     * against the official guide) — the flat rate above is the real price,
     * so there's nothing to call out for.
     */
    public function quote(ShippingQuoteRequestData $request): ShippingQuoteData
    {
        return $this->baseRate(ShippingDeliveryType::Locker);
    }

    public function offices(?string $city = null): array
    {
        try {
            $response = $this->client()->get('destinations');

            if (! $response->successful() || ! is_array($response->json('data'))) {
                return [];
            }

            return collect($response->json('data'))
                ->map(fn (array $locker) => new ShippingOfficeData(
                    id: (string) $locker['id'],
                    carrier: $this->carrier(),
                    type: ShippingDeliveryType::Locker,
                    name: (string) ($locker['name'] ?? $locker['title'] ?? ''),
                    // addressLine1 is the street, addressLine2 the
                    // settlement/city — confirmed against the guide's
                    // /origins example, the only one with realistic
                    // (non-placeholder) values for both fields. trim() is
                    // load-bearing, not cosmetic: a meaningful share of real
                    // destinations have stray trailing tabs/spaces on
                    // addressLine2 (e.g. "София\t\t"), which without this
                    // made the city dropdown show the same city twice under
                    // two different-looking (but functionally identical)
                    // entries.
                    city: trim((string) ($locker['addressLine2'] ?? '')),
                    address: trim((string) ($locker['addressLine1'] ?? '')),
                ))
                ->values()
                ->all();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('BOX NOW lockers lookup failed', ['error' => $exception->getMessage()]);

            return [];
        } catch (Throwable $exception) {
            // The exact response shape can drift without notice on a
            // partner API we don't control — degrade to an empty list
            // rather than a 500 if a field is ever missing or reshaped.
            Log::warning('BOX NOW lockers response had an unexpected shape', ['error' => $exception->getMessage()]);

            return [];
        }
    }

    public function createShipment(Order $order, ShippingDeliveryType $deliveryType, ?string $officeId): ShipmentData
    {
        $items = $order->items->values()->map(fn ($item, int $index) => [
            'id' => $item->sku ?: "{$order->order_number}-{$index}",
            'name' => $item->product_name,
            'value' => (string) $item->unit_price,
            // Product weight isn't tracked anywhere in this app — 1 is the
            // guide's own documented fallback ("Ако не знаете теглото на
            // пратката, моля подайте стойност 1").
            'weight' => 1,
        ])->all();

        // Cash on delivery only ever reaches this provider (see
        // PaymentService::availablePaymentMethods()) — every other order,
        // including every Speedy one, is prepaid with nothing to collect.
        $isCashOnDelivery = $order->payments()->where('payment_method', PaymentMethod::CashOnDelivery)->exists();

        try {
            $response = $this->client()->post('delivery-requests', [
                'orderNumber' => $order->order_number,
                'paymentMode' => $isCashOnDelivery ? 'cod' : 'prepaid',
                'invoiceValue' => (string) $order->grand_total,
                'amountToBeCollected' => $isCashOnDelivery ? (string) $order->grand_total : '0.00',
                'allowReturn' => true,
                'origin' => [
                    'contactNumber' => $this->originContactNumber(),
                    'contactEmail' => (string) ($this->storeSettings->get('general.store_email') ?? ''),
                    'contactName' => (string) ($this->storeSettings->get('general.store_name') ?? ''),
                    'locationId' => (string) config('services.shipping.box_now.origin_location_id', 'any-apm'),
                ],
                'destination' => [
                    'contactNumber' => $order->customer_phone,
                    'contactEmail' => $order->customer_email,
                    'contactName' => $order->customerFullName(),
                    'locationId' => $officeId,
                ],
                'items' => $items,
            ]);
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('box_now', 'createShipment', $exception->getMessage());
        }

        $parcelId = $response->json('parcels.0.id');

        if (! $response->successful() || $parcelId === null) {
            throw ShippingProviderException::requestFailed('box_now', 'createShipment', (string) $response->status());
        }

        return new ShipmentData(
            trackingNumber: (string) $parcelId,
            status: ShipmentStatus::Accepted,
            // Requires an authenticated GET to parcels/{id}/label.pdf —
            // not a URL that's fetchable on its own, so nothing to store.
            labelUrl: null,
            rawResponse: $response->json() ?? [],
        );
    }

    public function track(string $trackingNumber): TrackingData
    {
        try {
            $response = $this->client()->get('parcels', ['parcelId' => $trackingNumber]);
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('box_now', 'track', $exception->getMessage());
        }

        if (! $response->successful()) {
            throw ShippingProviderException::requestFailed('box_now', 'track', (string) $response->status());
        }

        $parcel = $response->json('data.0');

        if ($parcel === null) {
            throw ShippingProviderException::requestFailed('box_now', 'track', 'Parcel not found.');
        }

        $events = collect($parcel['events'] ?? [])
            ->map(fn (array $event) => new TrackingEventData(
                status: $this->mapStatus((string) $event['type']),
                description: $event['locationDisplayName'] ?? null,
                occurredAt: CarbonImmutable::parse($event['createTime']),
            ))
            ->values()
            ->all();

        return new TrackingData(
            currentStatus: $this->mapStatus((string) ($parcel['state'] ?? 'new')),
            events: $events,
            estimatedDeliveryAt: null, // not returned by this endpoint.
        );
    }

    /** BOX NOW's own state vocabulary — see the guide's section 4.5. */
    private function mapStatus(string $rawStatus): ShipmentStatus
    {
        return match ($rawStatus) {
            'new' => ShipmentStatus::Accepted,
            'in-transit', 'in-depot', 'wait-for-load' => ShipmentStatus::InTransit,
            'final-destination' => ShipmentStatus::OutForDelivery,
            'delivered' => ShipmentStatus::Delivered,
            'returned', 'cancelled-return', 'expired-return' => ShipmentStatus::Returned,
            'cancelled', 'canceled', 'lost', 'missing' => ShipmentStatus::Failed,
            default => ShipmentStatus::Pending,
        };
    }

    /** BOX NOW expects a bare "+359..." number, no internal spacing. */
    private function originContactNumber(): string
    {
        return str_replace(' ', '', (string) ($this->storeSettings->get('general.support_phone') ?? ''));
    }

    private function client(): PendingRequest
    {
        $credentials = $this->settings->credentialsFor('box_now');

        return Http::baseUrl((string) ($credentials['base_url'] ?? ''))
            ->withToken($this->accessToken($credentials))
            ->acceptJson()
            ->timeout(10);
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
                    ->acceptJson()
                    ->timeout(10)
                    ->post('auth-sessions', [
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
