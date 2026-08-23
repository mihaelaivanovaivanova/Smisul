<?php

namespace Tests\Feature\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingQuoteTest extends TestCase
{
    private function quote(array $params): TestResponse
    {
        return $this->getJson('/api/v1/checkout/shipping-quote?'.http_build_query($params));
    }

    #[Test]
    public function the_flat_rate_fallback_is_used_when_the_carrier_api_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Could not resolve host');
        });

        $response = $this->quote(['carrier' => 'speedy', 'delivery_type' => 'address', 'city' => 'Sofia', 'postal_code' => '1000']);

        $response->assertOk();
        $response->assertJsonPath('data.price', 5.99);
        $response->assertJsonPath('data.currency', 'EUR');
    }

    #[Test]
    public function the_flat_rate_fallback_is_used_when_the_carrier_api_returns_an_unexpected_shape(): void
    {
        Http::fake(['api.speedy.bg/*' => Http::response(['unexpected' => 'shape'])]);

        $response = $this->quote(['carrier' => 'speedy', 'delivery_type' => 'office', 'city' => 'Sofia', 'postal_code' => '1000']);

        $response->assertOk();
        $response->assertJsonPath('data.price', 5.99);
    }

    /**
     * The Speedy fake response mirrors their real, confirmed response shape
     * (calculations[] array wrapping price/deliveryDeadline — see
     * https://api.speedy.bg/api/docs/, verified live during development),
     * not a flat guess.
     */
    #[Test]
    public function speedy_quote_uses_its_own_endpoint(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::response([
                'calculations' => [
                    ['price' => ['total' => 6.20, 'currency' => 'EUR'], 'deliveryDeadline' => '2026-07-08T12:00:00+03:00'],
                ],
            ]),
        ]);

        $speedy = $this->quote(['carrier' => 'speedy', 'delivery_type' => 'office', 'city' => 'Sofia', 'postal_code' => '1000']);
        $speedy->assertOk();
        $speedy->assertJsonPath('data.price', 6.2);
    }

    /**
     * Confirmed against BOX NOW's real partner API guide: there is no live
     * pricing/quote endpoint at all, so baseRate() is the real (contractual)
     * price — quote() must never make a network call for it, unlike every
     * other carrier here.
     */
    #[Test]
    public function box_now_quote_never_makes_a_network_call(): void
    {
        // Travels past the launch promo window (see
        // BoxNowShippingProvider::isFreeShippingPromoActive()) so this
        // asserts the underlying flat rate, not the temporary €0 promo
        // price - the promo itself is covered separately below.
        $this->travelTo(\Carbon\Carbon::parse('2026-10-01'));

        Http::fake([
            'api-production.boxnow.bg/*' => Http::response(['error' => 'quote endpoint does not exist'], 404),
        ]);

        $boxNow = $this->quote(['carrier' => 'box_now', 'delivery_type' => 'locker', 'city' => 'Sofia', 'postal_code' => '1000']);
        $boxNow->assertOk();
        $boxNow->assertJsonPath('data.price', 4.99);

        Http::assertNothingSent();
    }

    /**
     * Launch promotion confirmed by the business owner on 2026-08-21: every
     * BOX NOW delivery is free through the end of September 2026, so the
     * site's "free shipping with BOX NOW" badge is never a false claim.
     */
    #[Test]
    public function box_now_delivery_is_free_during_the_launch_promo(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-15'));

        $boxNow = $this->quote(['carrier' => 'box_now', 'delivery_type' => 'locker', 'city' => 'Sofia', 'postal_code' => '1000']);
        $boxNow->assertOk();
        $boxNow->assertJsonPath('data.price', 0);
    }

    /**
     * Confirmed against the real Speedy sandbox with live test credentials:
     * `calculate` wants `recipient.addressLocation` (not `address`),
     * `recipient.privatePerson`, and `service.serviceIds` as an array (not
     * a singular `serviceId`) — unlike the `shipment` endpoint, which wants
     * `address` + a singular `serviceId`. Getting these mixed up is exactly
     * the bug this test guards against.
     */
    #[Test]
    public function speedy_quote_request_uses_the_calculate_endpoints_real_field_shape(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::response([
                'calculations' => [
                    ['price' => ['total' => 6.20, 'currency' => 'EUR'], 'deliveryDeadline' => '2026-07-08T12:00:00+03:00'],
                ],
            ]),
        ]);

        $this->quote(['carrier' => 'speedy', 'delivery_type' => 'office', 'city' => 'Sofia', 'postal_code' => '1000'])->assertOk();

        Http::assertSent(function ($request) {
            return $request['recipient']['privatePerson'] === true
                && $request['recipient']['addressLocation']['siteName'] === 'Sofia'
                && $request['service']['serviceIds'] === [505];
        });
    }

    #[Test]
    public function an_invalid_carrier_is_rejected(): void
    {
        $response = $this->quote(['carrier' => 'dhl', 'delivery_type' => 'address', 'city' => 'Sofia', 'postal_code' => '1000']);

        $response->assertStatus(422);
    }

    #[Test]
    public function a_missing_required_field_is_rejected(): void
    {
        $response = $this->quote(['carrier' => 'speedy', 'delivery_type' => 'address']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['city', 'postal_code']);
    }
}
