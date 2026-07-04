<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;

class SettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', Setting::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*' => ['present'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $keys = array_keys($this->input('settings', []));

            $validKeys = Setting::query()
                ->whereIn('group', SettingService::EDITABLE_GROUPS)
                ->pluck('key')
                ->all();

            foreach ($keys as $key) {
                if (! in_array($key, $validKeys, true)) {
                    $validator->errors()->add("settings.{$key}", 'Unknown setting key.');
                }
            }
        });
    }
}
