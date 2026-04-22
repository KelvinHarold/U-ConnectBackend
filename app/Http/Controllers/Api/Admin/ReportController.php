<?php
// app/Http/Controllers/Api/Admin/ReportController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function salesReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        
        $sales = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as total_sales'),
                DB::raw('AVG(total) as average_order_value')
            )
            ->groupBy('date')
            ->get();
        
        $summary = [
            'total_orders' => $sales->sum('orders_count'),
            'total_revenue' => $sales->sum('total_sales'),
            'average_order' => $sales->avg('average_order_value'),
        ];
        
        return response()->json([
            'summary' => $summary,
            'daily_sales' => $sales,
            'period' => ['start' => $startDate, 'end' => $endDate]
        ]);
    }

    public function sellerPerformance()
    {
        $sellers = User::role('seller')
            ->withCount(['products', 'ordersAsSeller'])
            ->withSum('ordersAsSeller', 'total')
            ->withSum('ordersAsSeller', 'total')
            ->orderBy('orders_as_seller_sum_total', 'desc')
            ->get();
        
        return response()->json($sellers);
    }

    public function productPerformance()
    {
        $topProducts = Product::with('seller')
            ->orderBy('sales_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->take(20)
            ->get();
        
        $lowStock = Product::whereColumn('quantity', '<=', 'min_stock_alert')
            ->with('seller')
            ->get();
        
        $inactive = Product::where('is_active', false)->count();
        
        return response()->json([
            'top_products' => $topProducts,
            'low_stock_products' => $lowStock,
            'inactive_products_count' => $inactive
        ]);
    }

    public function userActivity()
    {
        $activeBuyers = User::role('buyer')
            ->whereHas('ordersAsBuyer', function($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            })
            ->count();
        
        $activeSellers = User::role('seller')
            ->whereHas('products', function($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            })
            ->count();
        
        $newUsers = User::where('created_at', '>=', now()->subDays(30))->count();
        
        return response()->json([
            'active_buyers_last_30_days' => $activeBuyers,
            'active_sellers_last_30_days' => $activeSellers,
            'new_users_last_30_days' => $newUsers,
        ]);
    }
}