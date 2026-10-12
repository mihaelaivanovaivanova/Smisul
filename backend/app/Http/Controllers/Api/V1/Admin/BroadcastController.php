<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendBroadcastRequest;
use App\Services\AdminActionLogger;
use App\Services\BroadcastService;
use Illuminate\Http\JsonResponse;

/**
 * The admin "Send broadcast" screen - a plain-text subject/body/optional
 * link, sent to everyone BroadcastService::recipients() currently resolves
 * (consented Users + funnel leads, minus anyone suppressed). No draft/
 * schedule/campaign-history concept - this is the weekly "write it, hit
 * send" button that was actually asked for, not a marketing-automation
 * platform.
 */
class BroadcastController extends Controller
{
    public function __construct(
        private readonly BroadcastService $broadcasts,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    /**
     * Lets the admin screen show "this will go to N people" before the
     * irreversible send button is pressed.
     */
    public function recipientCount(): JsonResponse
    {
        return response()->json(['data' => ['count' => count($this->broadcasts->recipients())]]);
    }

    public function send(SendBroadcastRequest $request): JsonResponse
    {
        $result = $this->broadcasts->send(
            $request->string('subject')->toString(),
            BroadcastService::paragraphs($request->string('body')->toString()),
            $request->filled('cta_url') ? $request->string('cta_url')->toString() : null,
            $request->filled('cta_label') ? $request->string('cta_label')->toString() : null,
        );

        $this->actionLogger->log($request->user(), 'broadcasts.sent', null, [
            'subject' => $request->string('subject')->toString(),
            ...$result,
        ]);

        return response()->json(['data' => $result]);
    }
}
