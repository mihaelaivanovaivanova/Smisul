<?php

namespace App\Models;

use App\Enums\ConsentType;
use Carbon\Carbon;
use Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ConsentType $type
 * @property bool $accepted
 * @property Carbon $created_at
 */
class Consent extends Model
{
    /** @use HasFactory<ConsentFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'guest_identifier',
        'type',
        'version',
        'legal_document_id',
        'accepted',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'accepted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LegalDocument, $this>
     */
    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }
}
