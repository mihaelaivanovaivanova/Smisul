<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
                // Registered accounts (excludes the same internal/test
                // accounts OrderService::statistics() excludes from order/
                // revenue figures - a test account shouldn't inflate "how
                // many real customers do we have" either) plus everyone who
                // bought as a guest and never created one - guest checkout
                // is a fully supported path, not an edge case, so a metric
                // that only counted User rows would systematically
                // undercount actual customers (see
                // OrderService::uniqueGuestCustomerCount()).
                'total_customers' => User::query()
                    ->where('role', Role::Customer)
                    ->whereNotIn(DB::raw('LOWER(email)'), array_map('mb_strtolower', OrderService::TEST_CUSTOMER_EMAILS))
                    ->count() + $this->orders->uniqueGuestCustomerCount(),
                'total_products' => Product::query()->count(),
                'low_stock_products' => $lowStockCount,
                'out_of_stock_products' => $outOfStockCount,
                'total_favorites' => Favorite::query()->count(),
            ],
        ]);
    }
}
