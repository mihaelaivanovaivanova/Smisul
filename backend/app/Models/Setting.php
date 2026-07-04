<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string $group
 * @property ?string $value
 * @property string $type
 * @property string $label
 */
class Setting extends Model
{
    protected $fillable = ['key', 'group', 'value', 'type', 'label'];

    public function castValue(): string|int|bool|null
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'integer' => $this->value !== null ? (int) $this->value : null,
            default => $this->value,
        };
    }
}
