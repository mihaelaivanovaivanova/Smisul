<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Architecture placeholder for future review image/video uploads — see the
 * review_media migration. No factory, controller, or request exists for
 * this yet; nothing creates rows here today.
 */
class ReviewMedia extends Model
{
    protected $table = 'review_media';

    protected $fillable = [
        'review_id',
        'path',
        'type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
