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
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Speedy integration against their real Web API, verified end-to-end
 * against the sandbox with live test credentials (account 1996549) —
 * quote, office lookup, shipment creation, label printing, and tracking
 * have all returned real data, not just documented-but-unverified shapes.
 * Credentials are embedded as `userName`/`password` fields in every request
 * body, not an HTTP auth header, and every endpoint requires them,
 * including office lookups.
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
 * Independent of BoxNowShippingProvider by design — no shared base class.
 *
 * Cash on delivery: `service.additionalServices.cod` (amount, currencyCode,
 * processingType, fiscalReceiptItems) — confirmed live with a real test
 * shipment, complete with the `codPremium` surcharge Speedy adds to the
 * price breakdown for it. The only carrier this store offers COD for (see
 * PaymentService::availablePaymentMethods()) — BOX NOW's locker network
 * has no courier meeting the customer in person to collect from.
 * fiscalReceiptItems (the "касов бон" Speedy issues on our behalf for the
 * cash sale) needs `vatGroup` as the Cyrillic letter "А" — the visually
 * identical Latin "A" is rejected outright, confirmed by trial.
 *
 * Three delivery types now: staffed office, automated machine (APT — both
 * come back from the same location/office lookup, distinguished by the
 * office's own `type` field; see offices() below), and home address.
 */
class SpeedyShippingProvider implements ShippingProviderInterface
{
    public function __construct(private readonly ShippingProviderSettingsService $settings) {}

    private const SERVICE_ID = 505;

    /** See splitStreetAndNumber()'s own docblock for why this exists. */
    private const MAX_STREET_NAME_LENGTH = 50;

    public function carrier(): ShippingCarrier
    {
        return ShippingCarrier::Speedy;
    }

    public function supportedDeliveryTypes(): array
    {
        return [ShippingDeliveryType::Office, ShippingDeliveryType::Locker, ShippingDeliveryType::Address];
    }

    public function baseRate(ShippingDeliveryType $deliveryType): ShippingQuoteData
    {
        return new ShippingQuoteData(
            carrier: $this->carrier(),
            deliveryType: $deliveryType,
            price: $this->settings->priceFor('speedy', $deliveryType) ?? 5.99,
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
            // The unfiltered (no $city) nationwide office list is a large
            // payload — 1-3MB observed against the real API, since it's
            // every staffed office and APT machine in Bulgaria in one
            // response. The shared client()'s 5s timeout is tuned for the
            // small, latency-sensitive calls (quote/shipment/track) and was
            // cutting this one off mid-download (cURL error 28: timed out
            // with megabytes already received), silently degrading to an
            // empty list — intermittently, depending on how much had
            // downloaded by the 5s mark — which is exactly what made the
            // Speedy office picker unreliable at checkout.
            $response = $this->client()->timeout(30)->post('location/office', $this->withCredentials(
                array_filter(['siteName' => $city]),
            ));

            if (! $response->successful() || ! is_array($response->json('offices'))) {
                return [];
            }

            return collect($response->json('offices'))
                ->map(fn (array $office) => new ShippingOfficeData(
                    id: (string) $office['id'],
                    carrier: $this->carrier(),
                    // Speedy's location/office list mixes staffed offices
                    // in with APT entries (automated parcel terminals/
                    // machines) in one flat response, distinguished by this
                    // `type` field ("OFFICE" vs "APT") — confirmed live
                    // against the sandbox. Both are real pickup points now
                    // (see supportedDeliveryTypes()), listed as two
                    // separate checkout options ("Speedy" vs "Speedy
                    // (автомат)" — see ShippingService::label()).
                    type: ($office['type'] ?? 'OFFICE') === 'APT' ? ShippingDeliveryType::Locker : ShippingDeliveryType::Office,
                    name: (string) $office['name'],
                    // The office's own site name (e.g. "СОФИЯ"), not the
                    // $city filter — the two used to always match because
                    // this only ran with a city filter applied, but an
                    // unfiltered nationwide lookup (no $city) needs the
                    // real per-office city to group by.
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

        // Office/APT and address recipients are mutually exclusive per
        // Speedy's real API (address is forbidden when pickupOfficeId is
        // set) — pickupOfficeId is the same field for both a staffed
        // office and an automated machine, since Speedy's own
        // location/office list carries both under one id space (see
        // offices() above).
        if ($deliveryType->requiresOfficeSelection() && $officeId !== null) {
            $recipient['pickupOfficeId'] = (int) $officeId;
        } else {
            $recipient['address'] = array_filter([
                'siteName' => $order->shipping_city,
                'postCode' => $order->shipping_postal_code,
                ...$this->parseAddressComponents($order->shipping_address_line, $order->shipping_apartment),
            ], fn ($value) => $value !== null);
        }

        $ownClient = $this->ownClient();

        // Cash on delivery is only ever selectable for Speedy orders (see
        // PaymentService::availablePaymentMethods()) — every other order
        // is prepaid with nothing to collect.
        $isCashOnDelivery = $order->payments()->where('payment_method', PaymentMethod::CashOnDelivery)->exists();

        $service = ['serviceId' => self::SERVICE_ID, 'pickupDate' => $this->nextPickupDate()->toDateString()];

        if ($isCashOnDelivery) {
            $service['additionalServices'] = [
                'cod' => [
                    // grand_total already includes the shipping charge
                    // (see OrderService::placeOrder()) - includeShippingPrice
                    // false means Speedy collects exactly this amount and
                    // doesn't add its own delivery fee on top, which would
                    // overcharge the customer beyond the checkout total.
                    'amount' => (float) $order->grand_total,
                    'currencyCode' => $order->currency,
                    // Both cash and a card payment at the door are allowed
                    // (cardPaymentForbidden defaults to false) - confirmed
                    // against the real schema (ShipmentCODAdditionalService).
                    'processingType' => 'CASH',
                    'includeShippingPrice' => false,
                    // Speedy issues the fiscal receipt ("касов бон") for
                    // any cash collection on our behalf - per Speedy's own
                    // integration notice this is a legal requirement, not
                    // an optional extra, whenever "с касов бон" applies (it
                    // always does for a cash sale). One line covering the
                    // whole order rather than itemizing per product - this
                    // store has only ever had the one tax treatment, so
                    // there's no real per-line VAT group to distinguish.
                    // vatGroup "А" (Cyrillic, not Latin — confirmed live,
                    // Latin "A" is rejected) is 0% VAT: this Company isn't
                    // ДДС-registered (see LegalDocumentSeeder's "Плащане и
                    // документ за продажбата" clause), so amount and
                    // amountWithVat are always equal.
                    'fiscalReceiptItems' => [
                        [
                            'description' => "Поръчка {$order->order_number}",
                            'vatGroup' => 'А',
                            'amount' => (float) $order->grand_total,
                            'amountWithVat' => (float) $order->grand_total,
                        ],
                    ],
                ],
            ];
        }

        try {
            $response = $this->client()->post('shipment', $this->withCredentials([
                'sender' => [
                    'clientId' => $ownClient['clientId'],
                    'dropoff' => true,
                    'dropoffOfficeId' => (int) $this->settings->credentialsFor('speedy')['dropoff_office_id'],
                    // Speedy prints this account's own registered
                    // contactName (a person's name - the account holder,
                    // per Speedy's own client registration) on every label
                    // by default when only clientId is given. Overridden
                    // with the same registered *company* name instead, so
                    // the sender reads as the business, not a person -
                    // confirmed live that a blank/whitespace override is
                    // silently ignored and falls back to the registered
                    // value, but a real string does take effect.
                    'contactName' => $ownClient['clientName'],
                ],
                'recipient' => $recipient,
                'service' => $service,
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
            // Speedy returns HTTP 200 even for a business-logic rejection
            // (e.g. no courier scheduled to collect from the sender address
            // today) — the real reason lives in the response body's `error`
            // object, not the HTTP status, so surface that when present
            // rather than the useless "request failed: 200".
            $reason = $response->json('error.message') ?? (string) $response->status();

            throw ShippingProviderException::requestFailed('speedy', 'createShipment', (string) $reason);
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
     * `POST print` per Speedy's real published schema (ParcelToPrint takes
     * a ShipmentParcelRef, not a bare string id: `parcels[].parcelId.id`) —
     * confirmed live against the sandbox: a real A6 PDF label came back for
     * a real test shipment's tracking number, byte-identical in shape to
     * BOX NOW's own label response (raw PDF body, not a wrapped/base64
     * response like `print/extended` returns).
     */
    public function fetchLabel(string $trackingNumber): string
    {
        try {
            $response = $this->client()->post('print', $this->withCredentials([
                'paperSize' => 'A6',
                'parcels' => [['parcelId' => ['id' => $trackingNumber]]],
            ]));
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('speedy', 'fetchLabel', $exception->getMessage());
        }

        if (! $response->successful()) {
            $reason = $response->json('error.message') ?? (string) $response->status();

            throw ShippingProviderException::requestFailed('speedy', 'fetchLabel', (string) $reason);
        }

        return $response->body();
    }

    /**
     * `POST shipment/cancel` per Speedy's real schema
     * (`CancelShipmentRequest`: `shipmentId` + `comment`) — confirmed live:
     * cancelling a real test shipment returned an empty `{}` body, which
     * the docs describe as success (an `error` object is the only other
     * possible response shape).
     *
     * `comment` turned out not to be truly optional despite the schema
     * marking it so: Speedy's API rejects a call with fewer than 4
     * characters in it. Nothing upstream of this method is guaranteed to
     * supply a reason at all (the admin "Cancel shipment" button's prompt
     * can be left blank/cancelled — see cancelOrderShipment() on the
     * frontend), so this always pads out to a safe default rather than
     * ever sending a too-short or empty comment.
     */
    public function cancelShipment(string $trackingNumber, ?string $reason = null): void
    {
        $comment = trim((string) $reason);
        if (mb_strlen($comment) < 4) {
            $comment = 'Order cancelled by store';
        }

        try {
            $response = $this->client()->post('shipment/cancel', $this->withCredentials([
                'shipmentId' => $trackingNumber,
                'comment' => $comment,
            ]));
        } catch (ConnectionException $exception) {
            throw ShippingProviderException::requestFailed('speedy', 'cancelShipment', $exception->getMessage());
        }

        if (! $response->successful() || $response->json('error') !== null) {
            $reason = $response->json('error.message') ?? (string) $response->status();

            throw ShippingProviderException::requestFailed('speedy', 'cancelShipment', (string) $reason);
        }
    }

    /**
     * Leaving `service.pickupDate` unset defaults it to "today" on Speedy's
     * side, which their real API rejects outright once the account's
     * same-day courier collection cutoff has passed for the sender address
     * ("courier-not-working-office-collection-possible") — reproduced live:
     * an identical request succeeds immediately once a future date is sent
     * instead. Since order payment (and therefore automatic shipment
     * creation, see CreateShipmentOnOrderPaid) can happen at any hour,
     * always requesting the next working day sidesteps that cutoff entirely
     * rather than depending on what time of day checkout happens to occur.
     */
    private function nextPickupDate(): CarbonImmutable
    {
        $date = CarbonImmutable::tomorrow();

        while ($date->isWeekend()) {
            $date = $date->addDay();
        }

        return $date;
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
     * Bulgarian free-text address token -> Speedy's real Address schema
     * field (confirmed against their published JSON schema at
     * api.speedy.bg/v1/schema — Address.schema.json / ShipmentAddress.
     * schema.json both list complexName/blockNo/entranceNo/floorNo/
     * apartmentNo as real, separate fields from streetName/streetNo).
     * Ordered so complexName (whose value can itself contain multiple
     * words) is pulled out before the single-token block/entrance/floor/
     * apartment matches, which stops it from swallowing a later token that
     * happens to share the same clause.
     *
     * @var array<string, string>
     */
    private const ADDRESS_COMPONENT_PATTERNS = [
        'complexName' => '/\bж\.?\s*к\.?\s*([^,]+)/ui',
        'blockNo' => '/\bбл\.\s*([^\s,]+)/ui',
        'entranceNo' => '/\bвх\.\s*([^\s,]+)/ui',
        'floorNo' => '/\bет\.\s*([^\s,]+)/ui',
        'apartmentNo' => '/\bап\.\s*([^\s,]+)/ui',
    ];

    /**
     * Checkout only captures one free-text address line (see
     * shipping_address_line) rather than Speedy's fully structured address
     * - this pulls out the pieces that Bulgarian addresses conventionally
     * mark with a recognizable abbreviation (ж.к./бл./вх./ет./ап.) into
     * their own real fields (see ADDRESS_COMPONENT_PATTERNS) instead of
     * leaving them jumbled inside one streetName, or worse: before this
     * existed, splitStreetAndNumber() alone just grabbed whatever number
     * ended the whole line as "the" street number, which for an address
     * ending "...бл. 5, ет. 2" wrongly took the *floor* (2) as the street
     * number and left "бл. 5" as trailing junk inside streetName - a real
     * reported case. shipping_apartment (a dedicated column checkout
     * already collects) is used for apartmentNo only when the address line
     * itself didn't already spell one out.
     *
     * Whatever remains after removing every recognized token is handled
     * exactly as before: a trailing house number is pulled off as
     * streetNo, and streetName (whatever residual free text — typically
     * the actual street, plus the city/complex if either didn't already
     * get its own field above) is truncated via truncateStreetName().
     *
     * @return array{
     *     streetName: string, streetNo: string, complexName: ?string,
     *     blockNo: ?string, entranceNo: ?string, floorNo: ?string,
     *     apartmentNo: ?string,
     * }
     */
    private function parseAddressComponents(string $addressLine, ?string $apartment): array
    {
        $remaining = trim($addressLine);
        $components = [];

        foreach (self::ADDRESS_COMPONENT_PATTERNS as $field => $pattern) {
            if (preg_match($pattern, $remaining, $matches) === 1) {
                $components[$field] = trim($matches[1]);
                $remaining = trim(preg_replace($pattern, '', $remaining, 1));
                // Tidies up whatever punctuation is left stranded where the
                // removed token used to sit (a lone leading comma, or two
                // commas left next to each other where it used to bridge
                // two clauses) so the leftover text stays clean for the
                // street-name split below.
                $remaining = trim(preg_replace('/\s*,\s*,/', ',', $remaining), " \t\n\r,");
            }
        }

        if (! isset($components['apartmentNo']) && $apartment !== null && $apartment !== '') {
            $components['apartmentNo'] = $apartment;
        }

        [$streetName, $streetNo] = $this->splitStreetAndNumber($remaining);

        return [
            'streetName' => $streetName,
            'streetNo' => $streetNo,
            'complexName' => $components['complexName'] ?? null,
            'blockNo' => $components['blockNo'] ?? null,
            'entranceNo' => $components['entranceNo'] ?? null,
            'floorNo' => $components['floorNo'] ?? null,
            'apartmentNo' => $components['apartmentNo'] ?? null,
        ];
    }

    /**
     * Pulls a trailing house number (e.g. "1", "25А", "5B") off whatever
     * free text is left as streetNo; if none is found, the whole remainder
     * becomes streetName and streetNo falls back to "0" so the request
     * stays deliverable-shaped rather than failing outright — Speedy's real
     * `shipment` endpoint rejects an address without a non-empty streetNo.
     * Confirmed against the real sandbox — see the Sprint 11.5 Speedy
     * integration notes.
     *
     * streetName is also capped at MAX_STREET_NAME_LENGTH via
     * truncateStreetName() — see its own docblock for why a straight
     * left-to-right character cut is wrong here.
     *
     * @return array{0: string, 1: string}
     */
    private function splitStreetAndNumber(string $addressLine): array
    {
        $addressLine = trim($addressLine);

        if (preg_match('/^(.*?)[\s,]+(\d+[\p{L}]?)$/u', $addressLine, $matches) === 1) {
            return [$this->truncateStreetName(trim($matches[1])), $matches[2]];
        }

        return [$this->truncateStreetName($addressLine), '0'];
    }

    /**
     * Speedy's real `shipment` endpoint rejects `address.streetName` outright
     * once it passes 50 characters ("Получател Улица: Максималната позволена
     * дължина е 50") — confirmed live by a real failed request from a
     * Bulgarian address combining a residential complex with a boulevard
     * ("ж.к. Меден рудник, бул. Александър Георгиев - Коджакафалията"),
     * which alone is 60 characters.
     *
     * A plain mb_substr(..., 0, 50) would cut that from the right ("...бул.
     * Александър Гео"), losing the actual street name and building/complex
     * detail entirely and keeping only the least useful part (the city
     * quarter) — confirmed against the real reported case, this is
     * genuinely worse than useless on a printed label. Comma-separated
     * segments are instead dropped from the front (broadest context first —
     * city, then residential complex) until what's left fits, since the
     * *last* segment is conventionally the actual deliverable street name a
     * Bulgarian free-text address ends with. Falls back to a hard
     * mb_substr cut only if even that last segment alone still doesn't fit.
     *
     * The full original text is untouched in shipping_address_line for our
     * own records/label review either way — only what's sent to Speedy is
     * shortened. mb_substr/mb_strlen throughout, not the byte-oriented
     * substr/strlen — a multi-byte cut mid-character would corrupt the
     * Cyrillic text rather than just shorten it.
     */
    private function truncateStreetName(string $streetName): string
    {
        if (mb_strlen($streetName) <= self::MAX_STREET_NAME_LENGTH) {
            return $streetName;
        }

        $segments = array_values(array_filter(array_map('trim', explode(',', $streetName)), fn ($segment) => $segment !== ''));

        if ($segments === []) {
            return mb_substr($streetName, 0, self::MAX_STREET_NAME_LENGTH);
        }

        $kept = '';
        foreach (array_reverse($segments) as $segment) {
            $candidate = $kept === '' ? $segment : "{$segment}, {$kept}";

            if (mb_strlen($candidate) > self::MAX_STREET_NAME_LENGTH) {
                break;
            }

            $kept = $candidate;
        }

        return $kept !== '' ? $kept : mb_substr(end($segments), 0, self::MAX_STREET_NAME_LENGTH);
    }

    /**
     * Speedy's `calculate` endpoint returns a precise ISO deadline
     * (e.g. "2026-07-07T19:00:00+0300"), not a display-ready string like
     * BOX NOW's mocked responses — showing that raw value verbatim in
     * checkout looked like a bug. Falls back to the same generic range
     * the other carrier uses if the value is missing or unparsable.
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
     * The account's own registered identity on file with Speedy —
     * confirmed live: our production account resolves to the real
     * registered business, our sandbox account to Speedy's own "EPS/API
     * TESTERS" placeholder. `clientId` is required by `sender.dropoff`
     * shipments so the printed label shows that real sender identity
     * instead of a hand-typed one, and so Speedy accepts this account as
     * a valid payer for the courier service; `clientName` is pulled
     * alongside it purely to override the registered `contactName` (see
     * createShipment()'s own comment on why). Two real endpoints per
     * Speedy's schema - `POST client` (`GetOwnClientIdResponse`) for the
     * id, then `POST client/{id}` (`Client`) for the name. Cached
     * together per credentials set, since neither changes for a given
     * account and every shipment creation would otherwise cost two extra
     * round trips.
     *
     * @return array{clientId: int, clientName: string}
     */
    private function ownClient(): array
    {
        $credentials = $this->settings->credentialsFor('speedy');

        return Cache::remember(
            'speedy.own_client.'.md5((string) ($credentials['username'] ?? '')),
            now()->addDay(),
            function () {
                $idResponse = $this->client()->post('client', $this->withCredentials([]));

                if (! $idResponse->successful() || $idResponse->json('clientId') === null) {
                    throw ShippingProviderException::requestFailed('speedy', 'ownClient', (string) ($idResponse->json('error.message') ?? $idResponse->status()));
                }

                $clientId = $idResponse->json('clientId');

                $detailResponse = $this->client()->post("client/{$clientId}", $this->withCredentials([]));

                if (! $detailResponse->successful() || $detailResponse->json('client.clientName') === null) {
                    throw ShippingProviderException::requestFailed('speedy', 'ownClient', (string) ($detailResponse->json('error.message') ?? $detailResponse->status()));
                }

                return [
                    'clientId' => (int) $clientId,
                    'clientName' => (string) $detailResponse->json('client.clientName'),
                ];
            },
        );
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
