<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Seo extends Model
{
    protected $table = 'seo';

    protected $fillable = [
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image_path',
        'canonical_url',
    ];

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
