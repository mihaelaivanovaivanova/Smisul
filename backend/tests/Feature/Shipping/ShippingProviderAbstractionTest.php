<?php

namespace Tests\Feature\Shipping;

use App\Contracts\ShippingProviderInterface;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Services\Shipping\BoxNowShippingProvider;
use App\Services\Shipping\SpeedyShippingProvider;
use App\Services\ShippingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the courier abstraction itself: every provider implements
 * ShippingProviderInterface independently, ShippingServiceProvider wires
 * both into the container, and ShippingService dispatches to the right one
 * per carrier without any carrier-specific logic of its own.
 */
class ShippingProviderAbstractionTest extends TestCase
{
    #[Test]
    public function each_provider_implements_the_shared_interface(): void
    {
        $speedy = $this->app->make(SpeedyShippingProvider::class);
        $boxNow = $this->app->make(BoxNowShippingProvider::class);

        foreach ([$speedy, $boxNow] as $provider) {
            $this->assertInstanceOf(ShippingProviderInterface::class, $provider);
        }

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
    public function speedy_supports_office_locker_and_address_delivery(): void
    {
        $speedy = $this->app->make(SpeedyShippingProvider::class);

        $this->assertEqualsCanonicalizing(
            [ShippingDeliveryType::Office, ShippingDeliveryType::Locker, ShippingDeliveryType::Address],
            $speedy->supportedDeliveryTypes(),
        );
    }

    #[Test]
    public function shipping_service_aggregates_every_provider_into_one_catalog(): void
    {
        $methods = $this->app->make(ShippingService::class)->availableMethods();

        $this->assertCount(4, $methods);
        $this->assertCount(1, array_filter($methods, fn ($m) => $m->carrier === ShippingCarrier::BoxNow));
        $this->assertCount(3, array_filter($methods, fn ($m) => $m->carrier === ShippingCarrier::Speedy));
    }

    #[Test]
    public function find_resolves_the_exact_carrier_and_delivery_type_combination(): void
    {
        $service = $this->app->make(ShippingService::class);

        $method = $service->find('speedy', 'office');

        $this->assertNotNull($method);
        $this->assertSame(ShippingCarrier::Speedy, $method->carrier);
        $this->assertSame(ShippingDeliveryType::Office, $method->deliveryType);
    }

    #[Test]
    public function find_returns_null_for_an_unsupported_combination(): void
    {
        $service = $this->app->make(ShippingService::class);

        // BOX NOW has no "address" (home delivery) option.
        $this->assertNull($service->find('box_now', 'address'));
    }

    /**
     * Speedy's own automated-machine option needs a label distinct from
     * its staffed-office one (both would otherwise just read "Speedy") —
     * see ShippingService::label(). BOX NOW's bare "BOX NOW" label is
     * unaffected — it has no other delivery type to disambiguate against.
     */
    #[Test]
    public function speedy_locker_gets_a_distinct_label_from_speedy_office(): void
    {
        $service = $this->app->make(ShippingService::class);

        $office = $service->find('speedy', 'office');
        $locker = $service->find('speedy', 'locker');
        $boxNowLocker = $service->find('box_now', 'locker');

        $this->assertSame('Speedy', $office->label);
        $this->assertSame('Speedy (автомат)', $locker->label);
        $this->assertSame('BOX NOW', $boxNowLocker->label);
    }
}
