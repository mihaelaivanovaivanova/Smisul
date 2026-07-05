<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property array<string, mixed> $content
 */
class ContentBlock extends Model
{
    protected $fillable = ['key', 'content'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
