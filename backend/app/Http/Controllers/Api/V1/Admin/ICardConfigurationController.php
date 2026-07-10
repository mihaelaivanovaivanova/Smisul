<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AdminActionLogger;
use App\Services\Payments\ICardConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ICardConfigurationController extends Controller
{
    public function __construct(
        private readonly ICardConfigurationService $configurations,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        if (! $this->configurations->storageReady()) {
            return $this->migrationRequired();
        }

        return response()->json(['data' => $this->configurations->adminProfiles()]);
    }

    public function update(Request $request, string $environment): JsonResponse
    {
        $this->authorize('update', Setting::class);
        abort_unless(in_array($environment, ['sandbox', 'production'], true), 404);

        if (! $this->configurations->storageReady()) {
            return $this->migrationRequired();
        }

        $values = $request->validate([
            'enabled' => ['required', 'boolean'],
            'mid' => ['nullable', 'string', 'max:15'],
            'mid_name' => ['required', 'string', 'max:191'],
            'originator' => ['nullable', 'string', 'max:32'],
            'key_index' => ['nullable', 'string', 'max:16'],
            'key_index_resp' => ['nullable', 'string', 'max:16'],
            'ipg_version' => ['required', 'string', Rule::in(['4.5'])],
            'currency_numeric' => ['required', 'string', 'size:3'],
            'base_url' => ['required', 'url:https', 'max:500'],
            'modal_js_url' => ['required', 'url:https', 'max:500'],
            'wallet_js_url' => ['required', 'url:https', 'max:500'],
            'webhook_url' => ['nullable', 'url:https', 'max:500'],
            'private_key' => ['nullable', 'string', 'max:20000'],
            'public_key' => ['nullable', 'string', 'max:20000'],
            'callback_ips' => ['nullable', 'array'],
            'callback_ips.*' => ['ip'],
            'apple_pay_enabled' => ['required', 'boolean'],
            'google_pay_enabled' => ['required', 'boolean'],
            'apple_merchant_id' => ['nullable', 'string', 'max:191'],
            'apple_merchant_domain' => ['nullable', 'string', 'max:191'],
            'google_merchant_id' => ['nullable', 'string', 'max:191'],
            'google_environment' => ['required', Rule::in(['TEST', 'PRODUCTION'])],
        ]);

        try {
            if (filled($values['private_key'] ?? null)) {
                $values['private_key'] = $this->configurations->normalizeKey((string) $values['private_key'], 'private');
            }
            if (filled($values['public_key'] ?? null)) {
                $values['public_key'] = $this->configurations->normalizeKey((string) $values['public_key'], 'public');
            }
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->configurations->update($environment, $values);
        $safeChanges = array_diff_key($values, array_flip(['private_key', 'public_key']));
        $this->actionLogger->log($request->user(), 'icard.configuration.updated', changes: ['environment' => $environment] + $safeChanges);

        return response()->json(['data' => $this->configurations->adminProfiles()]);
    }

    public function activate(Request $request, string $environment): JsonResponse
    {
        $this->authorize('update', Setting::class);
        abort_unless(in_array($environment, ['sandbox', 'production'], true), 404);

        if (! $this->configurations->storageReady()) {
            return $this->migrationRequired();
        }

        $this->configurations->activate($environment);
        $this->actionLogger->log($request->user(), 'icard.environment.activated', changes: ['environment' => $environment]);
        return response()->json(['data' => $this->configurations->adminProfiles()]);
    }

    private function migrationRequired(): JsonResponse
    {
        return response()->json([
            'message' => 'The iCard settings database migration is missing or incomplete. Run php artisan migrate --force in smisul.bg/backend, then reload this page.',
            'code' => 'ICARD_MIGRATION_REQUIRED',
        ], 503);
    }
}
