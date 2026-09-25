<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'alt_text',
        'sort_order',
        'is_primary',
        'focus_x',
        'focus_y',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'focus_x' => 'float',
            'focus_y' => 'float',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
