<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingProviderSetting extends Model
{
    protected $table = 'shipping_provider_settings';

    protected $fillable = [
        'provider', 'enabled', 'base_url', 'username', 'password', 'client_id', 'client_secret',
        'price_office', 'price_locker', 'price_address',
    ];

    protected $hidden = ['username', 'password', 'client_id', 'client_secret'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'username' => 'encrypted',
            'password' => 'encrypted',
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'price_office' => 'float',
            'price_locker' => 'float',
            'price_address' => 'float',
        ];
    }
}
