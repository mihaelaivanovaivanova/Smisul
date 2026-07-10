<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredPaymentMethod extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'card_token', 'token_hash', 'brand', 'last_four',
        'expiry_month', 'expiry_year', 'is_active', 'last_used_at',
    ];

    protected $hidden = ['card_token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'card_token' => 'encrypted',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

