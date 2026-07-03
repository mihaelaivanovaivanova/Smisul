<?php

namespace App\Models\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasMedia
{
    /**
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    /**
     * @return MorphOne<Media, $this>
     */
    public function primaryMedia(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')->where('is_primary', true);
    }
}
