<?php
// app/Http/Controllers/Api/Admin/OrderManagementController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderManagementController extends Controller
{

    public function index(Request $request)
    {
        $query = Order::with(['buyer', 'seller', 'items.product']);
        
        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Filter by seller
        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }
        
        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }
        
        // Search by order number
        if ($request->has('search')) {
            $query->where('order_number', 'like', "%{$request->search}%");
        }
        
        $orders = $query->latest()->paginate(5);
        
        return response()->json($orders);
    }

    public function show($id)
    {
        $order = Order::with(['buyer', 'seller', 'items.product'])
            ->findOrFail($id);
        
        return response()->json($order);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready_for_delivery,delivered,cancelled,rejected',
        ]);
        
        $oldStatus = $order->status;
        $order->status = $validated['status'];
        
        if ($validated['status'] === 'delivered') {
            $order->delivered_at = now();
            $order->payment_status = 'paid';
        }
        
        if ($validated['status'] === 'cancelled') {
            $order->cancelled_at = now();
            // Restore stock
            foreach ($order->items as $item) {
                $item->product->increaseStock(
                    $item->quantity,
                    "Order #{$order->order_number} cancelled by admin"
                );
            }
        }
        
        $order->save();
        
        return response()->json([
            'message' => 'Order status updated',
            'order' => $order
        ]);
    }

    public function statistics()
    {
        $stats = [
            'total_orders' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'processing' => Order::whereIn('status', ['confirmed', 'preparing', 'ready_for_delivery'])->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'total_revenue' => Order::where('status', 'delivered')->sum('total'),
            'average_order_value' => Order::where('status', 'delivered')->avg('total'),
        ];
        
        $daily_orders = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->get();
        
        return response()->json([
            'stats' => $stats,
            'daily_orders' => $daily_orders
        ]);
    }
}