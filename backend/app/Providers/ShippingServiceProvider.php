<?php

namespace App\Providers;

use App\Enums\ShippingCarrier;
use App\Services\Shipping\BoxNowShippingProvider;
use App\Services\Shipping\EcontShippingProvider;
use App\Services\Shipping\SpeedyShippingProvider;
use App\Services\ShippingService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds ShippingService to the full set of active courier providers, keyed
 * by ShippingCarrier value. Adding a fourth carrier means adding one line
 * here (plus its own independent ShippingProviderInterface implementation)
 * — ShippingService and everything above it never changes.
 */
class ShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShippingService::class, function ($app) {
            return new ShippingService([
                // Array order is display order (see ShippingService::availableMethods())
                // — BOX NOW leads the checkout list and is the default selection.
                ShippingCarrier::BoxNow->value => $app->make(BoxNowShippingProvider::class),
                ShippingCarrier::Econt->value => $app->make(EcontShippingProvider::class),
                ShippingCarrier::Speedy->value => $app->make(SpeedyShippingProvider::class),
            ]);
        });
    }
}
