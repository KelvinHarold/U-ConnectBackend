<?php
// app/Http/Controllers/Api/Admin/AdminDashboardController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{

    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'total_sellers' => User::role('seller')->count(),
            'total_buyers' => User::role('buyer')->count(),
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            'total_revenue' => Order::where('status', 'delivered')->sum('total'),
            'low_stock_products' => Product::whereColumn('quantity', '<=', 'min_stock_alert')->count(),
        ];

        $recent_orders = Order::with(['buyer', 'seller'])
            ->latest()
            ->take(5)
            ->get();

        $recent_users = User::latest()
            ->take(5)
            ->get();

        $top_sellers = User::role('seller')
            ->withCount(['products', 'ordersAsSeller'])
            ->withSum('ordersAsSeller', 'total')
            ->orderBy('orders_as_seller_sum_total', 'desc')
            ->take(5)
            ->get();

        $monthly_stats = Order::select(
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(total) as revenue')
        )
            ->whereYear('created_at', date('Y'))
            ->groupBy('month')
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_orders' => $recent_orders,
            'recent_users' => $recent_users,
            'top_sellers' => $top_sellers,
            'monthly_stats' => $monthly_stats,
        ]);
    }
}
