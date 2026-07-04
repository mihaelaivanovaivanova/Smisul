<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\Admin\LogFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LogIndexRequest;
use App\Services\LogService;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
    public function __construct(private readonly LogService $logs) {}

    public function index(LogIndexRequest $request): JsonResponse
    {
        $filters = LogFilterData::fromArray($request->validated());

        $entries = $this->logs->forType($filters->type, $filters->page, $filters->perPage);

        return response()->json([
            'data' => $entries->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }
}
