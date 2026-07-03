<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Auto-generates a unique slug from a source field (default "name") when a
 * model is created and no slug was explicitly provided. Collisions are
 * resolved by appending "-2", "-3", etc.
 *
 * Soft-deleted rows still reserve their slug (checked via withTrashed()) so
 * a restored record can never silently collide with a newer one that took
 * its old URL in the meantime.
 */
trait Sluggable
{
    protected static function bootSluggable(): void
    {
        static::creating(function ($model): void {
            if (empty($model->slug)) {
                $model->slug = $model->generateUniqueSlug();
            }
        });
    }

    public function generateUniqueSlug(): string
    {
        $base = Str::slug((string) $this->{$this->slugSourceField()});
        $slug = $base;
        $attempt = 1;

        while ($this->slugAlreadyTaken($slug)) {
            $attempt++;
            $slug = "{$base}-{$attempt}";
        }

        return $slug;
    }

    protected function slugSourceField(): string
    {
        return 'name';
    }

    protected function slugAlreadyTaken(string $slug): bool
    {
        $query = static::withTrashed()->where('slug', $slug);

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->exists();
    }
}
