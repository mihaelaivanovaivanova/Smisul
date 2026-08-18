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
     * Econt's real getOffices response mixes staffed offices in with APS
     * machines (isAPS) and mobile post-station vans (isMPS) in the same
     * flat list — confirmed by hand against the live demo endpoint. The
     * checkout office picker must only offer staffed offices.
     */
    #[Test]
    public function econt_aps_machines_and_mobile_post_stations_are_excluded(): void
    {
        Http::fake([
            'demo.econt.com/*' => Http::response([
                'offices' => [
                    [
                        'id' => 37381,
                        'isAPS' => false,
                        'isMPS' => false,
                        'name' => 'Econt Sofia Center',
                        'address' => [
                            'fullAddress' => 'София, ул. Резбарска №9',
                            'city' => ['name' => 'София', 'nameEn' => 'Sofia'],
                        ],
                    ],
                    [
                        'id' => 106441,
                        'isAPS' => true,
                        'isMPS' => false,
                        'name' => 'Econt APS Sofia Mall',
                        'address' => [
                            'fullAddress' => 'София, бул. Черни връх №100',
                            'city' => ['name' => 'София', 'nameEn' => 'Sofia'],
                        ],
                    ],
                    [
                        'id' => 100022756,
                        'isAPS' => false,
                        'isMPS' => true,
                        'name' => 'Мобилен офис (София)',
                        'address' => [
                            'fullAddress' => 'София, ул. Тестова №1',
                            'city' => ['name' => 'София', 'nameEn' => 'Sofia'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/checkout/shipping-offices?carrier=econt&city=Sofia');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => '37381', 'name' => 'Econt Sofia Center']);
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
     * the office's own siteName, same as Econt's city mapping already did.
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
