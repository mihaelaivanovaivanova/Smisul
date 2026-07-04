<?php

namespace Tests\Feature\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingOfficeLookupTest extends TestCase
{
    /**
     * The fake response below mirrors the real, confirmed shape returned by
     * Econt's actual public demo endpoint (nested address/city objects,
     * not flat strings) — verified by hand against
     * https://demo.econt.com/ee/services/Nomenclatures/NomenclaturesService.getOffices.json
     * during development, since Econt's real sandbox is reachable without
     * credentials for nomenclature lookups. This also confirms the
     * (unfiltered by the API itself) client-side city filtering in
     * EcontShippingProvider::offices() actually excludes the non-matching
     * city.
     */
    #[Test]
    public function econt_offices_are_listed_for_a_city(): void
    {
        Http::fake([
            'demo.econt.com/*' => Http::response([
                'offices' => [
                    [
                        'id' => 37381,
                        'name' => 'Econt Sofia Center',
                        'address' => [
                            'fullAddress' => 'София, ул. Резбарска №9',
                            'city' => ['name' => 'София', 'nameEn' => 'Sofia'],
                        ],
                    ],
                    [
                        'id' => 40122,
                        'name' => 'Econt Plovdiv Center',
                        'address' => [
                            'fullAddress' => 'Пловдив, ул. Главна №1',
                            'city' => ['name' => 'Пловдив', 'nameEn' => 'Plovdiv'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=econt&city=Sofia');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        // Matching is done against the office's English city name (nameEn),
        // but the office is displayed with its Bulgarian name — consistent
        // with the rest of the (Bulgarian) storefront.
        $response->assertJsonFragment(['id' => '37381', 'name' => 'Econt Sofia Center', 'city' => 'София', 'type' => 'office']);
    }

    /**
     * Regression test for the real bug this shape mismatch caused during
     * development: casting an unexpected array value to string raises
     * "Array to string conversion", which Laravel's error handler promotes
     * to an ErrorException — this must degrade to an empty list rather than
     * a 500, since the exact response shape from a live carrier API is
     * never fully guaranteed.
     */
    #[Test]
    public function econt_offices_with_an_unexpected_field_type_degrade_to_an_empty_list_instead_of_500ing(): void
    {
        Http::fake([
            'demo.econt.com/*' => Http::response(['offices' => [[
                'id' => ['unexpected' => 'array'],
                'name' => 'Bad Shape',
                'address' => ['fullAddress' => 'test', 'city' => ['name' => 'София', 'nameEn' => 'Sofia']],
            ]]]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=econt&city=Sofia');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function box_now_lockers_are_listed(): void
    {
        Http::fake([
            'sandbox-api.boxnow.bg/oauth/*' => Http::response(['access_token' => 'test-token']),
            'sandbox-api.boxnow.bg/v1/lockers*' => Http::response([
                'lockers' => [['id' => 'BN1', 'name' => 'BOX NOW Sofia Mall', 'city' => 'Sofia', 'address' => 'bul. Cherni Vrah 100']],
            ]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=box_now&city=Sofia');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'BN1', 'type' => 'locker']);
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
                    'address' => ['fullAddressString' => 'bul. Bulgaria 1'],
                ]],
            ]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=speedy&city=Sofia');

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'SP1', 'carrier' => 'speedy', 'address' => 'bul. Bulgaria 1']);
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
