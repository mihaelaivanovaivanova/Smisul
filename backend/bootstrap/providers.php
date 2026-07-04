<?php

use App\Providers\AppServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\ShippingServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    PaymentServiceProvider::class,
    ShippingServiceProvider::class,
];
