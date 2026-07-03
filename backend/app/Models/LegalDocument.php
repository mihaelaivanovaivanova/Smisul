<?php

namespace App\Models;

use App\Enums\LegalDocumentType;
use Carbon\Carbon;
use Database\Factories\LegalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property LegalDocumentType $type
 * @property Carbon $published_at
 */
class LegalDocument extends Model
{
    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'version',
        'title',
        'content',
        'is_current',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'is_current' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
