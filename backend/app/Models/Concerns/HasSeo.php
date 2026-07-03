<?php

namespace App\Models\Concerns;

use App\Models\Seo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSeo
{
    public function seo(): MorphOne
    {
        return $this->morphOne(Seo::class, 'seoable');
    }
}
