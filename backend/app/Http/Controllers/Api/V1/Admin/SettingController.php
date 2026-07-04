<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingUpdateRequest;
use App\Models\Setting;
use App\Services\AdminActionLogger;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return response()->json([
            'data' => [
                'editable' => $this->settings->allGrouped(),
                'providers' => $this->settings->providerStatus(),
            ],
        ]);
    }

    public function update(SettingUpdateRequest $request): JsonResponse
    {
        $values = $request->validated('settings');

        $this->settings->updateMany($values);

        $this->actionLogger->log($request->user(), 'settings.updated', changes: $values);

        return response()->json([
            'data' => [
                'editable' => $this->settings->allGrouped(),
            ],
        ]);
    }
}
