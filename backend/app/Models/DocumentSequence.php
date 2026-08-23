<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per document type (currently just 'invoice') tracking the next
 * number to hand out — see InvoiceNumberGenerator, which locks the row
 * inside a transaction before incrementing it, so numbers are assigned
 * gap-free even under concurrent requests.
 */
class DocumentSequence extends Model
{
    protected $fillable = [
        'key',
        'next_number',
    ];
}
