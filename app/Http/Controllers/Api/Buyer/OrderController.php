<?php
// app/Http/Controllers/Api/Buyer/OrderController.php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
   
    public function index(Request $request)
    {
        $query = Order::where('buyer_id', auth()->id())
            ->with(['seller', 'items.product']);
        
        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        $orders = $query->latest()->paginate(5);
        
        // Transform order items to include full image URLs
        $orders->getCollection()->transform(function ($order) {
            if ($order->items) {
                $order->items->transform(function ($item) {
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
        
        return response()->json($orders);
    }

    public function show($id)
    {
        $order = Order::where('buyer_id', auth()->id())
            ->with(['seller', 'items.product'])
            ->findOrFail($id);
        
        // Transform order items to include full image URLs
        if ($order->items) {
            $order->items->transform(function ($item) {
                if ($item->product && $item->product->image) {
                    if (!filter_var($item->product->image, FILTER_VALIDATE_URL)) {
                        $item->product->image = url($item->product->image);
                    }
                }
                return $item;
            });
        }
        
        // Add seller image if exists
        if ($order->seller && $order->seller->avatar) {
            if (!filter_var($order->seller->avatar, FILTER_VALIDATE_URL)) {
                $order->seller->avatar = url($order->seller->avatar);
            }
        }
        
        return response()->json($order);
    }

    public function cancel($id)
    {
        $order = Order::where('buyer_id', auth()->id())
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($id);
        
        $order->cancel();
        
        // Notify the seller in real-time about the cancellation
        notify()->sendToUser($order->seller, [
            'type' => 'order_cancelled',
            'title' => 'Order Cancelled',
            'body' => "Order #{$order->order_number} has been cancelled by the buyer.",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'actions' => [
                ['label' => 'View Order', 'url' => "/seller/orders/{$order->id}"],
            ],
        ]);
        
        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $order
        ]);
    }

    public function confirmDelivery($id)
    {
        $order = Order::where('buyer_id', auth()->id())
            ->where('status', 'ready_for_delivery')
            ->findOrFail($id);
        
        $order->markAsDelivered();
        
        // Notify the seller in real-time that delivery was confirmed
        notify()->sendToUser($order->seller, [
            'type' => 'payment_received',
            'title' => 'Delivery Confirmed! 💰',
            'body' => "Buyer {$order->buyer->name} has confirmed delivery of order #{$order->order_number}.",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'actions' => [
                ['label' => 'View Order', 'url' => "/seller/orders/{$order->id}"],
            ],
        ]);
        
        return response()->json([
            'message' => 'Delivery confirmed',
            'order' => $order
        ]);
    }

    public function track($id)
    {
        $order = Order::where('buyer_id', auth()->id())->findOrFail($id);
        
        $statuses = [
            'pending' => ['label' => 'Order Placed', 'completed' => true],
            'confirmed' => ['label' => 'Order Confirmed', 'completed' => $order->status != 'pending'],
            'preparing' => ['label' => 'Preparing', 'completed' => in_array($order->status, ['preparing', 'ready_for_delivery', 'delivered'])],
            'ready_for_delivery' => ['label' => 'Ready for Delivery', 'completed' => in_array($order->status, ['ready_for_delivery', 'delivered'])],
            'delivered' => ['label' => 'Delivered', 'completed' => $order->status == 'delivered'],
        ];
        
        $currentStep = array_search($order->status, array_keys($statuses));
        
        return response()->json([
            'order' => $order,
            'tracking' => $statuses,
            'current_step' => $currentStep
        ]);
    }
}