<?php

namespace Tests\Feature\Shipping;

use App\Contracts\ShippingProviderInterface;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Services\Shipping\BoxNowShippingProvider;
use App\Services\Shipping\EcontShippingProvider;
use App\Services\Shipping\SpeedyShippingProvider;
use App\Services\ShippingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the courier abstraction itself: every provider implements
 * ShippingProviderInterface independently, ShippingServiceProvider wires
 * all three into the container, and ShippingService dispatches to the
 * right one per carrier without any carrier-specific logic of its own.
 */
class ShippingProviderAbstractionTest extends TestCase
{
    #[Test]
    public function each_provider_implements_the_shared_interface(): void
    {
        $econt = $this->app->make(EcontShippingProvider::class);
        $speedy = $this->app->make(SpeedyShippingProvider::class);
        $boxNow = $this->app->make(BoxNowShippingProvider::class);

        foreach ([$econt, $speedy, $boxNow] as $provider) {
            $this->assertInstanceOf(ShippingProviderInterface::class, $provider);
        }

        $this->assertSame(ShippingCarrier::Econt, $econt->carrier());
        $this->assertSame(ShippingCarrier::Speedy, $speedy->carrier());
        $this->assertSame(ShippingCarrier::BoxNow, $boxNow->carrier());
    }

    #[Test]
    public function box_now_only_supports_locker_delivery(): void
    {
        $boxNow = $this->app->make(BoxNowShippingProvider::class);

        $this->assertSame([ShippingDeliveryType::Locker], $boxNow->supportedDeliveryTypes());
    }

    #[Test]
    public function econt_and_speedy_support_office_and_address_delivery(): void
    {
        $econt = $this->app->make(EcontShippingProvider::class);
        $speedy = $this->app->make(SpeedyShippingProvider::class);

        $this->assertEqualsCanonicalizing(
            [ShippingDeliveryType::Office, ShippingDeliveryType::Address],
            $econt->supportedDeliveryTypes(),
        );
        $this->assertEqualsCanonicalizing(
            [ShippingDeliveryType::Office, ShippingDeliveryType::Address],
            $speedy->supportedDeliveryTypes(),
        );
    }

    #[Test]
    public function shipping_service_aggregates_every_provider_into_one_catalog(): void
    {
        $methods = $this->app->make(ShippingService::class)->availableMethods();

        $this->assertCount(5, $methods);
        $this->assertCount(1, array_filter($methods, fn ($m) => $m->carrier === ShippingCarrier::BoxNow));
        $this->assertCount(2, array_filter($methods, fn ($m) => $m->carrier === ShippingCarrier::Econt));
        $this->assertCount(2, array_filter($methods, fn ($m) => $m->carrier === ShippingCarrier::Speedy));
    }

    #[Test]
    public function find_resolves_the_exact_carrier_and_delivery_type_combination(): void
    {
        $service = $this->app->make(ShippingService::class);

        $method = $service->find('econt', 'office');

        $this->assertNotNull($method);
        $this->assertSame(ShippingCarrier::Econt, $method->carrier);
        $this->assertSame(ShippingDeliveryType::Office, $method->deliveryType);
    }

    #[Test]
    public function find_returns_null_for_an_unsupported_combination(): void
    {
        $service = $this->app->make(ShippingService::class);

        // BOX NOW has no "address" (home delivery) option.
        $this->assertNull($service->find('box_now', 'address'));
    }
}
