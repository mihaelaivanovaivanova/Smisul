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
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderStatusService $orderStatus,
        private readonly ShippingService $shipping,
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

    /**
     * Manual fallback for CreateShipmentOnOrderPaid (which already runs
     * automatically on payment confirmation): retries a failed automatic
     * attempt, or dispatches a historical order placed before that listener
     * existed. A 422 with the real carrier-failure reason, not a generic
     * 500 — an admin retrying this needs to see *why* it failed (bad
     * address, locker unavailable, carrier outage, ...), not just that it
     * did.
     */
    public function createShipment(Order $order): OrderResource|JsonResponse
    {
        try {
            $this->shipping->createShipment($order);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new OrderResource($order->fresh()->load(OrderService::ADMIN_EAGER_LOAD));
    }

    /**
     * Streams the dispatch label PDF straight from the carrier — fetched
     * fresh on every request (see ShippingService::fetchLabel()), never
     * cached on our side.
     */
    public function shipmentLabel(Order $order): Response|JsonResponse
    {
        $shipment = $order->shipment;
        abort_if($shipment === null, 404, 'This order has no shipment yet.');

        try {
            $pdf = $this->shipping->fetchLabel($shipment);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$order->order_number}-label.pdf\"");
    }
}
