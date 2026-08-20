<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ShippingCarrier;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AdminActionLogger;
use App\Services\Shipping\ShippingProviderSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingProviderConfigurationController extends Controller
{
    public function __construct(
        private readonly ShippingProviderSettingsService $settings,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        if (! $this->settings->storageReady()) {
            return $this->migrationRequired();
        }

        return response()->json(['data' => $this->settings->adminList()]);
    }

    public function update(Request $request, string $provider): JsonResponse
    {
        $this->authorize('update', Setting::class);
        abort_unless(in_array($provider, array_column(ShippingCarrier::active(), 'value'), true), 404);

        if (! $this->settings->storageReady()) {
            return $this->migrationRequired();
        }

        $values = $request->validate([
            'enabled' => ['required', 'boolean'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'username' => ['nullable', 'string', 'max:500'],
            'password' => ['nullable', 'string', 'max:500'],
            'client_id' => ['nullable', 'string', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'price_office' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'price_locker' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'price_address' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        if ($provider === 'box_now') {
            unset($values['username'], $values['password']);
        } else {
            unset($values['client_id'], $values['client_secret']);
        }

        $this->settings->update($provider, $values);

        $safeChanges = array_diff_key($values, array_flip(['username', 'password', 'client_id', 'client_secret']));
        $this->actionLogger->log($request->user(), 'shipping.configuration.updated', changes: ['provider' => $provider] + $safeChanges);

        return response()->json(['data' => $this->settings->adminList()]);
    }

    private function migrationRequired(): JsonResponse
    {
        return response()->json([
            'message' => 'The shipping settings database migration is missing or incomplete. Run php artisan migrate --force in smisul.bg/backend, then reload this page.',
            'code' => 'SHIPPING_SETTINGS_MIGRATION_REQUIRED',
        ], 503);
    }
}
