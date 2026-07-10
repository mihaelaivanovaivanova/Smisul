<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ICardConfiguration extends Model
{
    protected $table = 'icard_configurations';

    protected $fillable = [
        'environment', 'is_active', 'enabled', 'mid', 'mid_name', 'originator',
        'key_index', 'key_index_resp', 'ipg_version', 'currency_numeric',
        'base_url', 'modal_js_url', 'wallet_js_url', 'webhook_url',
        'private_key', 'public_key', 'callback_ips', 'apple_pay_enabled', 'google_pay_enabled',
        'apple_merchant_id', 'apple_merchant_domain', 'google_merchant_id',
        'google_environment',
    ];

    protected $hidden = ['private_key', 'public_key'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'enabled' => 'boolean',
            'private_key' => 'encrypted',
            'public_key' => 'encrypted',
            'callback_ips' => 'array',
            'apple_pay_enabled' => 'boolean',
            'google_pay_enabled' => 'boolean',
        ];
    }
}
