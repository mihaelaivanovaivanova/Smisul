<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\Admin\OrderFilterData;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderStatusService $orderStatus,
    ) {}

    public function index(OrderIndexRequest $request): AnonymousResourceCollection
    {
        $orders = $this->orders->listForAdmin(OrderFilterData::fromArray($request->validated()));

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(OrderService::ADMIN_EAGER_LOAD));
    }

    /**
     * Moves an order to a new status, validated against
     * OrderStatusService::TRANSITIONS. Cancelling here (status=cancelled)
     * goes through OrderService::cancel() so any still-held stock
     * reservation is released; every other target status is a plain
     * transition with no side effects beyond the history record.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $status = OrderStatus::from($request->validated('status'));
        $note = $request->validated('note');
        $admin = $request->user();

        $updated = $status === OrderStatus::Cancelled
            ? $this->orders->cancel($order, $admin, $note)
            : $this->orderStatus->transitionTo($order, $status, $admin, $note);

        return new OrderResource($updated->load(OrderService::ADMIN_EAGER_LOAD));
    }

    public function statistics(): JsonResponse
    {
        return response()->json(['data' => $this->orders->statistics()]);
    }
}
