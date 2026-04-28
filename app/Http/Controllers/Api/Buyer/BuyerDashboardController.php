<?php
// app/Http/Controllers/Api/Buyer/BuyerDashboardController.php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BuyerDashboardController extends Controller
{
    public function index()
    {
        $buyerId = auth()->id();
        $cacheKey = "buyer_dashboard_stats_{$buyerId}";

        return Cache::remember($cacheKey, 600, function() use ($buyerId) { // Cache for 10 minutes
            // Get order statistics
            $orderStats = [
                'total_orders' => Order::where('buyer_id', $buyerId)->count(),
                'pending_orders' => Order::where('buyer_id', $buyerId)->where('status', 'pending')->count(),
                'processing_orders' => Order::where('buyer_id', $buyerId)->where('status', 'processing')->count(),
                'shipped_orders' => Order::where('buyer_id', $buyerId)->where('status', 'shipped')->count(),
                'delivered_orders' => Order::where('buyer_id', $buyerId)->where('status', 'delivered')->count(),
                'cancelled_orders' => Order::where('buyer_id', $buyerId)->where('status', 'cancelled')->count(),
                'total_spent' => Order::where('buyer_id', $buyerId)->where('status', 'delivered')->sum('total'),
            ];
            
            // Calculate average order value
            $orderStats['avg_order_value'] = $orderStats['total_orders'] > 0 
                ? $orderStats['total_spent'] / $orderStats['total_orders'] 
                : 0;
            
            // Get cart items count
            $cartItemsCount = Cart::where('buyer_id', $buyerId)->sum('quantity');
            
            // Prepare main stats object
            $stats = [
                'total_spent' => $orderStats['total_spent'],
                'total_orders' => $orderStats['total_orders'],
                'pending_orders' => $orderStats['pending_orders'],
                'processing_orders' => $orderStats['processing_orders'],
                'shipped_orders' => $orderStats['shipped_orders'],
                'delivered_orders' => $orderStats['delivered_orders'],
                'cancelled_orders' => $orderStats['cancelled_orders'],
                'cart_items' => $cartItemsCount,
                'avg_order_value' => $orderStats['avg_order_value'],
            ];
            
            // Get recent orders with relationships and image URLs
            $recent_orders = Order::where('buyer_id', $buyerId)
                ->with(['seller', 'items.product'])
                ->latest()
                ->take(5)
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
                    
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number ?? $order->id,
                        'total' => $order->total,
                        'status' => $order->status,
                        'created_at' => $order->created_at,
                        'item_count' => $order->items->sum('quantity'),
                        'items' => $order->items,
                        'seller' => $order->seller,
                    ];
                });
            
            // Get monthly spending data for the last 12 months
            $monthly_spending = $this->getMonthlySpending($buyerId);
            
            // Get recommended products based on order history (with image URLs)
            $recommended_products = $this->getRecommendedProducts($buyerId);
            
            // Get order status distribution for pie chart
            $order_status_distribution = [
                ['name' => 'Delivered', 'value' => $orderStats['delivered_orders'], 'color' => '#10B981'],
                ['name' => 'Pending', 'value' => $orderStats['pending_orders'], 'color' => '#F59E0B'],
                ['name' => 'Processing', 'value' => $orderStats['processing_orders'], 'color' => '#3B82F6'],
                ['name' => 'Shipped', 'value' => $orderStats['shipped_orders'], 'color' => '#8B5CF6'],
                ['name' => 'Cancelled', 'value' => $orderStats['cancelled_orders'], 'color' => '#EF4444']
            ];
            
            return [
                'stats' => $stats,
                'recent_orders' => $recent_orders,
                'monthly_spending' => $monthly_spending,
                'recommended_products' => $recommended_products,
                'order_status_distribution' => $order_status_distribution,
            ];
        });
    }
    
    /**
     * Get monthly spending data for the last 12 months
     */
    private function getMonthlySpending($buyerId)
    {
        $monthlyData = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthNumber = $month->month;
            $year = $month->year;
            
            $startDate = $month->copy()->startOfMonth();
            $endDate = $month->copy()->endOfMonth();
            
            $orders = Order::where('buyer_id', $buyerId)
                ->where('status', 'delivered')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();
            
            $amount = $orders->sum('total');
            $orderCount = $orders->count();
            
            $monthlyData[] = [
                'month' => $monthNumber,
                'year' => $year,
                'amount' => $amount,
                'order_count' => $orderCount,
            ];
        }
        
        return $monthlyData;
    }
    
    /**
     * Get recommended products based on user's order history
     */
    private function getRecommendedProducts($buyerId)
    {
        // Get categories from user's past orders
        $purchasedCategories = Order::where('buyer_id', $buyerId)
            ->where('status', 'delivered')
            ->with('items.product.category')
            ->get()
            ->pluck('items.*.product.category_id')
            ->flatten()
            ->unique()
            ->toArray();
        
        // Get already purchased product IDs
        $purchasedProductIds = Order::where('buyer_id', $buyerId)
            ->with('items.product')
            ->get()
            ->pluck('items.*.product.id')
            ->flatten()
            ->unique()
            ->toArray();
        
        $query = Product::fromPaidSellers()
            ->with('category')
            ->where('is_active', true)
            ->where('quantity', '>', 0) // Only show in-stock products
            ->whereNotIn('id', $purchasedProductIds);
        
        if (!empty($purchasedCategories)) {
            $query->whereIn('category_id', $purchasedCategories);
        }
        
        // Try to get products from same categories first, ordered by sales_count (popularity)
        $recommended = $query->orderBy('sales_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->take(4)
            ->get()
            ->map(function($product) {
                // Add full image URL
                $imageUrl = null;
                if ($product->image) {
                    if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                        $imageUrl = url($product->image);
                    } else {
                        $imageUrl = $product->image;
                    }
                }
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'image' => $imageUrl,
                    'original_price' => $product->original_price ?? null,
                    'discount' => $product->discount ?? null,
                    'category' => $product->category ? $product->category->name : null,
                    'sales_count' => $product->sales_count ?? 0,
                ];
            });
        
        // If not enough recommendations, add popular products (by sales_count or views_count)
        if ($recommended->count() < 4) {
            $needed = 4 - $recommended->count();
            $existingIds = $recommended->pluck('id')->toArray();
            
            $popularProducts = Product::fromPaidSellers()
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->whereNotIn('id', array_merge($purchasedProductIds, $existingIds))
                ->orderBy('sales_count', 'desc')
                ->orderBy('views_count', 'desc')
                ->take($needed)
                ->get()
                ->map(function($product) {
                    // Add full image URL
                    $imageUrl = null;
                    if ($product->image) {
                        if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                            $imageUrl = url($product->image);
                        } else {
                            $imageUrl = $product->image;
                        }
                    }
                    
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'image' => $imageUrl,
                        'original_price' => $product->original_price ?? null,
                        'discount' => $product->discount ?? null,
                        'category' => $product->category ? $product->category->name : null,
                        'sales_count' => $product->sales_count ?? 0,
                    ];
                });
            
            $recommended = $recommended->concat($popularProducts);
        }
        
        return $recommended;
    }
}