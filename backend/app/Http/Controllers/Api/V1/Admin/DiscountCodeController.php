<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\Admin\DiscountCodeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountCodeRequest;
use App\Http\Requests\Admin\UpdateDiscountCodeRequest;
use App\Http\Resources\Admin\DiscountCodeResource;
use App\Models\DiscountCode;
use App\Services\AdminActionLogger;
use App\Services\DiscountCodeAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DiscountCodeController extends Controller
{
    public function __construct(
        private readonly DiscountCodeAdminService $discountCodes,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return DiscountCodeResource::collection(DiscountCode::query()->latest()->paginate(20));
    }

    public function store(StoreDiscountCodeRequest $request): JsonResponse
    {
        $discountCode = $this->discountCodes->create(DiscountCodeData::fromArray($request->validated()));

        $this->actionLogger->log($request->user(), 'discount_code.created', $discountCode, ['code' => $discountCode->code]);

        return (new DiscountCodeResource($discountCode))->response()->setStatusCode(201);
    }

    public function update(UpdateDiscountCodeRequest $request, DiscountCode $discountCode): DiscountCodeResource
    {
        $discountCode = $this->discountCodes->update($discountCode, DiscountCodeData::fromArray($request->validated()));

        $this->actionLogger->log($request->user(), 'discount_code.updated', $discountCode, ['code' => $discountCode->code]);

        return new DiscountCodeResource($discountCode);
    }

    public function destroy(Request $request, DiscountCode $discountCode): Response
    {
        $this->actionLogger->log($request->user(), 'discount_code.deleted', $discountCode, ['code' => $discountCode->code]);

        $this->discountCodes->delete($discountCode);

        return response()->noContent();
    }
}
