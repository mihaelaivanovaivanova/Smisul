<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Payments\ICardPaymentGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the active payment gateway. Adding a second provider (Apple Pay,
 * Google Pay, ...) means resolving PaymentGatewayInterface conditionally
 * here (e.g. by a "provider" argument or a per-order stored choice)
 * instead of the single fixed binding below — nothing in PaymentService
 * or the controllers needs to change either way.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, ICardPaymentGateway::class);
    }
}
