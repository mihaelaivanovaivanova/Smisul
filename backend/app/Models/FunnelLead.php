<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An email captured by the funnel landing page's opt-in block. Deliberately
 * minimal — no user linkage, no verification flow: it's a marketing lead
 * list, exportable straight from the table.
 */
class FunnelLead extends Model
{
    protected $fillable = [
        'email',
    ];
}
