<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class SellerDashboardController extends Controller
{
    public function index()
    {
        $sellerId = auth()->id();

        // Orders stats
        $totalOrders = Order::where('seller_id', $sellerId)->count();
        $deliveredOrders = Order::where('seller_id', $sellerId)
            ->where('status', 'delivered')
            ->count();

        // Stats
        $stats = [
            'total_products' => Product::where('seller_id', $sellerId)->count(),

            'out_of_stock' => Product::where('seller_id', $sellerId)
                ->where('quantity', 0)
                ->count(),

            'total_orders' => $totalOrders,

            'pending_orders' => Order::where('seller_id', $sellerId)
                ->where('status', 'pending')->count(),

            'processing_orders' => Order::where('seller_id', $sellerId)
                ->where('status', 'processing')->count(),

            'delivered_orders' => $deliveredOrders,

            'cancelled_orders' => Order::where('seller_id', $sellerId)
                ->where('status', 'cancelled')->count(),

            'total_revenue' => Order::where('seller_id', $sellerId)
                ->where('status', 'delivered')
                ->sum('total'),

            'completion_rate' => $totalOrders > 0
                ? round(($deliveredOrders / $totalOrders) * 100, 1)
                : 0,
        ];

        // Recent Orders with product images
        $recent_orders = Order::with(['buyer', 'items.product' => function($q) {
            $q->select('id', 'name', 'image', 'price');
        }])
            ->where('seller_id', $sellerId)
            ->latest()
            ->take(10)
            ->get()
            ->map(function($order) {
                // Transform product images in order items
                if ($order->items) {
                    $order->items->transform(function($item) {
                        if ($item->product && $item->product->image) {
                            if (!filter_var($item->product->image, FILTER_VALIDATE_URL)) {
                                $item->product->image = url($item->product->image);
                            }
                        }
                        return $item;
                    });
                }
                return $order;
            });

        // Monthly Sales
        $monthly_sales = Order::where('seller_id', $sellerId)
            ->where('status', 'delivered')
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as revenue')
            )
            ->whereYear('created_at', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function($sale) {
                $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                $sale->month_name = $monthNames[$sale->month - 1];
                return $sale;
            });

        // Top Products with images and sales data (without reviews)
        $top_products = Product::where('seller_id', $sellerId)
            ->withSum(['orderItems as sold_count' => function ($q) {
                $q->select(DB::raw("COALESCE(SUM(quantity), 0)"));
            }], 'quantity')
            ->withSum(['orderItems as revenue' => function ($q) {
                $q->select(DB::raw("COALESCE(SUM(quantity * price), 0)"));
            }], 'price')
            ->withSum(['orderItems as order_count' => function ($q) {
                $q->select(DB::raw("COUNT(DISTINCT order_id)"));
            }], 'order_id')
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->map(function($product) {
                // Add full image URL
                if ($product->image) {
                    if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                        $product->image = url($product->image);
                    }
                }
                
                // Calculate profit margin (example calculation)
                $product->profit_margin = $product->price > 0 ? round(($product->price - ($product->cost_price ?? 0)) / $product->price * 100, 1) : 0;
                
                // Add stock status
                $product->stock_status = $product->quantity == 0 ? 'out_of_stock' : 
                                        ($product->quantity <= $product->min_stock_alert ? 'low_stock' : 'in_stock');
                
                return $product;
            });

        // Low stock alerts
        $low_stock_products = Product::where('seller_id', $sellerId)
            ->whereColumn('quantity', '<=', 'min_stock_alert')
            ->where('quantity', '>', 0)
            ->take(5)
            ->get()
            ->map(function($product) {
                if ($product->image && !filter_var($product->image, FILTER_VALIDATE_URL)) {
                    $product->image = url($product->image);
                }
                return $product;
            });

        return response()->json([
            'stats' => $stats,
            'recent_orders' => $recent_orders,
            'monthly_sales' => $monthly_sales,
            'top_products' => $top_products,
            'low_stock_products' => $low_stock_products,
        ]);
    }
}