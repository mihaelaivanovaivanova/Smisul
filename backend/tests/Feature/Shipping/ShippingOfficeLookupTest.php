<?php

namespace Tests\Feature\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingOfficeLookupTest extends TestCase
{
    /**
     * The fake response mirrors BOX NOW's real, confirmed shape (see their
     * partner API guide, https://boxnow.bg/partner-api — addressLine1 is
     * the street, addressLine2 the settlement/city) — verified against a
     * live production account during development, not a guess.
     */
    #[Test]
    public function box_now_lockers_are_listed(): void
    {
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/destinations*' => Http::response([
                'data' => [[
                    'id' => 'BN1',
                    'type' => 'apm',
                    'name' => 'BOX NOW Sofia Mall',
                    'addressLine1' => 'bul. Cherni Vrah 100',
                    'addressLine2' => 'Sofia',
                    'postalCode' => '1000',
                    'country' => 'BG',
                ]],
            ]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=box_now&city=Sofia');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'BN1', 'type' => 'locker', 'city' => 'Sofia', 'address' => 'bul. Cherni Vrah 100']);
    }

    /**
     * Regression test for a real bug found in production: a meaningful
     * share of BOX NOW's real destinations have stray trailing tabs/spaces
     * on addressLine2 (e.g. "София\t\t") — left untrimmed, the same city
     * showed up twice in the checkout city dropdown under two
     * different-looking (but functionally identical) entries.
     */
    #[Test]
    public function box_now_locker_city_whitespace_is_trimmed(): void
    {
        Http::fake([
            'api-production.boxnow.bg/api/v1/auth-sessions' => Http::response(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'api-production.boxnow.bg/api/v1/destinations*' => Http::response([
                'data' => [
                    ['id' => 'BN1', 'type' => 'apm', 'name' => 'BOX NOW Mall 1', 'addressLine1' => 'ul. Test 1', 'addressLine2' => "София\t\t"],
                    ['id' => 'BN2', 'type' => 'apm', 'name' => 'BOX NOW Mall 2', 'addressLine1' => ' ul. Test 2 ', 'addressLine2' => 'София'],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=box_now');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'BN1', 'city' => 'София']);
        $response->assertJsonFragment(['id' => 'BN2', 'city' => 'София', 'address' => 'ul. Test 2']);
    }

    /**
     * The fake response mirrors Speedy's real, confirmed shape (address is
     * a nested object with fullAddressString — see
     * https://api.speedy.bg/api/docs/, verified live during development).
     * Speedy's real API requires userName/password in every request body
     * (confirmed by the "invalid credentials" vs. "missing parameter"
     * response difference during development) — without real credentials
     * this always returns an empty list in practice, which
     * offices_lookup_returns_an_empty_list_when_the_carrier_api_fails below
     * also covers.
     */
    #[Test]
    public function speedy_offices_are_listed(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::response([
                'offices' => [[
                    'id' => 'SP1',
                    'name' => 'Speedy Office Sofia',
                    'address' => ['siteName' => 'СОФИЯ', 'fullAddressString' => 'bul. Bulgaria 1'],
                ]],
            ]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=speedy&city=Sofia');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'SP1', 'carrier' => 'speedy', 'address' => 'bul. Bulgaria 1', 'city' => 'СОФИЯ']);
    }

    /**
     * Regression test: the office's `city` field used to just echo back
     * whatever city string the caller filtered by (harmless while every
     * call passed a city, but broken for an unfiltered nationwide lookup,
     * where every office would report the same — empty — city). Now reads
     * the office's own siteName instead.
     */
    #[Test]
    public function speedy_offices_report_their_own_city_when_no_city_filter_is_given(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::response([
                'offices' => [
                    [
                        'id' => 'SP1',
                        'type' => 'OFFICE',
                        'name' => 'Speedy Office Sofia',
                        'address' => ['siteName' => 'СОФИЯ', 'fullAddressString' => 'bul. Bulgaria 1'],
                    ],
                    [
                        'id' => 'SP2',
                        'type' => 'OFFICE',
                        'name' => 'Speedy Office Plovdiv',
                        'address' => ['siteName' => 'ПЛОВДИВ', 'fullAddressString' => 'ul. Ruski 1'],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=speedy');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'SP1', 'city' => 'СОФИЯ']);
        $response->assertJsonFragment(['id' => 'SP2', 'city' => 'ПЛОВДИВ']);
    }

    /**
     * Speedy's real location/office response mixes staffed offices in with
     * APT entries (automated parcel terminals/machines) in the same flat
     * list, distinguished by a `type` field ("OFFICE" vs "APT") — confirmed
     * live against the sandbox. The checkout office picker must only offer
     * staffed offices.
     */
    #[Test]
    public function speedy_apt_machines_are_excluded(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::response([
                'offices' => [
                    [
                        'id' => 'SP1',
                        'type' => 'OFFICE',
                        'name' => 'Speedy Office Sofia',
                        'address' => ['fullAddressString' => 'bul. Bulgaria 1'],
                    ],
                    [
                        'id' => 'SP2',
                        'type' => 'APT',
                        'name' => 'Speedy Machine Sofia Mall',
                        'address' => ['fullAddressString' => 'bul. Cherni Vrah 100'],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=speedy&city=Sofia');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => 'SP1', 'name' => 'Speedy Office Sofia']);
    }

    #[Test]
    public function offices_lookup_returns_an_empty_list_when_the_carrier_api_fails(): void
    {
        Http::fake(function () {
            throw new ConnectionException('unreachable');
        });

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=speedy&city=Sofia');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function a_carrier_is_required(): void
    {
        $this->getJson('/api/v1/checkout/shipping-offices')->assertStatus(422);
    }
}
