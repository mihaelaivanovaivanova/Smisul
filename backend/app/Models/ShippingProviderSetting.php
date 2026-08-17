<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingProviderSetting extends Model
{
    protected $table = 'shipping_provider_settings';

    protected $fillable = [
        'provider', 'enabled', 'base_url', 'username', 'password', 'client_id', 'client_secret',
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
        ];
    }
}
