<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\Admin\OrderFilterData;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Requests\Admin\StoreManualOrderRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Order;
use App\Services\AdminActionLogger;
use App\Services\AdminOrderService;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderStatusService $orderStatus,
        private readonly ShippingService $shipping,
        private readonly AdminActionLogger $actionLogger,
        private readonly AdminOrderService $adminOrders,
    ) {}

    public function index(OrderIndexRequest $request): AnonymousResourceCollection
    {
        $orders = $this->orders->listForAdmin(OrderFilterData::fromArray($request->validated()));

        return OrderResource::collection($orders);
    }

    /**
     * A quick phone/in-person sale entered straight in — see
     * AdminOrderService::createManual()'s own docblock for why this never
     * touches the cart/checkout flow, and never auto-creates a shipment the
     * way a real checkout order does the moment it's confirmed.
     */
    public function store(StoreManualOrderRequest $request): JsonResponse
    {
        $order = $this->adminOrders->createManual($request->validated(), $request->user());

        $this->actionLogger->log($request->user(), 'order.created_manually', $order, ['order_number' => $order->order_number]);

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(OrderService::ADMIN_EAGER_LOAD));
    }

    /**
     * A permanent hard delete (see OrderService::delete()'s own docblock
     * for the cascade/complaint details) — cancels any non-final shipment
     * with the carrier first on a best-effort basis, mirroring
     * CancelShipmentOnOrderCancelled: a carrier-side failure must never
     * block an admin's already-made delete decision, only get logged for
     * them to finish manually.
     */
    public function destroy(Order $order): Response|JsonResponse
    {
        $shipment = $order->shipment;
        if ($shipment !== null && ! $shipment->status->isFinal()) {
            try {
                $this->shipping->cancelShipment($shipment, 'Order deleted by store.');
            } catch (Throwable $exception) {
                Log::error('Automatic shipment cancellation failed before order was deleted.', [
                    'order_number' => $order->order_number,
                    'carrier' => $order->shipping_carrier->value,
                    'tracking_number' => $shipment->tracking_number,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->actionLogger->log(request()->user(), 'order.deleted', $order, ['order_number' => $order->order_number]);

        try {
            $this->orders->delete($order);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->noContent();
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

    /**
     * Cancels the order's shipment with the carrier — a real cancellation
     * request, not just a local status flip (see
     * ShippingService::cancelShipment()). Same 422-with-message shape as
     * createShipment() above for the same reason. `reason` is optional
     * free text for the carrier's own records (the frontend prompts for
     * it, but leaving it blank is fine — ShippingService::cancelShipment()/
     * SpeedyShippingProvider::cancelShipment() fall back to a safe default
     * rather than ever sending a request Speedy would reject for being too
     * short).
     */
    public function cancelShipment(Request $request, Order $order): OrderResource|JsonResponse
    {
        $shipment = $order->shipment;
        abort_if($shipment === null, 404, 'This order has no shipment yet.');

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        try {
            $this->shipping->cancelShipment($shipment, $reason);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new OrderResource($order->fresh()->load(OrderService::ADMIN_EAGER_LOAD));
    }
}
