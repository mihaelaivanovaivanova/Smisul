<?php

namespace App\DataTransferObjects\Admin;

final readonly class SettingUpdateData
{
    /**
     * @param  array<string, string|int|bool|null>  $values  key => new value, keyed by Setting::key
     */
    public function __construct(
        public array $values,
    ) {}

    /**
     * @param  array{settings: array<string, string|int|bool|null>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(values: $data['settings']);
    }
}
