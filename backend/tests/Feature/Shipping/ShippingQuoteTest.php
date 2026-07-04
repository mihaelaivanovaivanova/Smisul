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
    public function a_live_econt_quote_is_returned_when_the_carrier_api_succeeds(): void
    {
        Http::fake([
            'demo.econt.com/*' => Http::response([
                'price' => 7.50,
                'currency' => 'EUR',
                'estimatedDelivery' => '1 работен ден',
            ]),
        ]);

        $response = $this->quote(['carrier' => 'econt', 'delivery_type' => 'office', 'city' => 'Sofia', 'postal_code' => '1000']);

        $response->assertOk();
        $response->assertJsonPath('data.carrier', 'econt');
        $response->assertJsonPath('data.price', 7.5);
        $response->assertJsonPath('data.currency', 'EUR');
        $response->assertJsonPath('data.estimated_delivery', '1 работен ден');
    }

    #[Test]
    public function the_flat_rate_fallback_is_used_when_the_carrier_api_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Could not resolve host');
        });

        $response = $this->quote(['carrier' => 'econt', 'delivery_type' => 'address', 'city' => 'Sofia', 'postal_code' => '1000']);

        $response->assertOk();
        $response->assertJsonPath('data.price', 5.99);
        $response->assertJsonPath('data.currency', 'EUR');
    }

    #[Test]
    public function the_flat_rate_fallback_is_used_when_the_carrier_api_returns_an_unexpected_shape(): void
    {
        Http::fake(['demo.econt.com/*' => Http::response(['unexpected' => 'shape'])]);

        $response = $this->quote(['carrier' => 'econt', 'delivery_type' => 'office', 'city' => 'Sofia', 'postal_code' => '1000']);

        $response->assertOk();
        $response->assertJsonPath('data.price', 5.99);
    }

    #[Test]
    public function speedy_and_box_now_quotes_use_their_own_endpoints(): void
    {
        Http::fake([
            'api.speedy.bg/*' => Http::response(['price' => ['total' => 6.20, 'currency' => 'EUR'], 'estimatedDeliveryTime' => '1-2 дни']),
            'sandbox-api.boxnow.bg/*' => Http::response(['price' => 4.50, 'currency' => 'EUR']),
        ]);

        $speedy = $this->quote(['carrier' => 'speedy', 'delivery_type' => 'office', 'city' => 'Sofia', 'postal_code' => '1000']);
        $speedy->assertOk();
        $speedy->assertJsonPath('data.price', 6.2);

        $boxNow = $this->quote(['carrier' => 'box_now', 'delivery_type' => 'locker', 'city' => 'Sofia', 'postal_code' => '1000']);
        $boxNow->assertOk();
        $boxNow->assertJsonPath('data.price', 4.5);
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
        $response = $this->quote(['carrier' => 'econt', 'delivery_type' => 'address']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['city', 'postal_code']);
    }
}
