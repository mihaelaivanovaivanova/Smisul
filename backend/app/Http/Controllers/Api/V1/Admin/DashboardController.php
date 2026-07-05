<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

/**
 * Aggregates figures from existing services/models for the admin dashboard
 * — deliberately thin: every number is either an existing service call
 * (OrderService::statistics()) or a single straightforward count query,
 * not a new reporting engine.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(): JsonResponse
    {
        $orderStats = $this->orders->statistics();

        $availableExpr = '(quantity_on_hand - quantity_reserved)';

        $lowStockCount = Inventory::query()
            ->whereRaw("{$availableExpr} > 0")
            ->whereRaw("{$availableExpr} <= low_stock_threshold")
            ->count();

        $outOfStockCount = Inventory::query()
            ->whereRaw("{$availableExpr} <= 0")
            ->where('backorders_allowed', false)
            ->count();

        return response()->json([
            'data' => [
                'total_orders' => $orderStats['total_orders'],
                'orders_today' => $orderStats['orders_today'],
                'revenue_today' => $orderStats['revenue_today'],
                'total_revenue' => $orderStats['total_revenue'],
                'total_customers' => User::query()->where('role', Role::Customer)->count(),
                'total_products' => Product::query()->count(),
                'low_stock_products' => $lowStockCount,
                'out_of_stock_products' => $outOfStockCount,
            ],
        ]);
    }
}
