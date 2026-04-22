<?php
// app/Http/Controllers/Api/Seller/OrderController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::where('seller_id', auth()->id())
            ->with(['buyer', 'items.product']);
        
        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Filter by date
        if ($request->has('date_filter')) {
            if ($request->date_filter === 'today') {
                $query->whereDate('created_at', today());
            } elseif ($request->date_filter === 'week') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($request->date_filter === 'month') {
                $query->whereMonth('created_at', now()->month);
            }
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
        $order = Order::where('seller_id', auth()->id())
            ->with(['buyer', 'items.product'])
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
        
        return response()->json($order);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::where('seller_id', auth()->id())->findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|in:confirmed,preparing,ready_for_delivery,delivered,cancelled,rejected',
        ]);
        
        $oldStatus = $order->status;
        $order->status = $validated['status'];
        
        if ($validated['status'] === 'delivered') {
            $order->delivered_at = now();
            $order->payment_status = 'paid';
            
            // Update product sales count
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('sales_count', $item->quantity);
                }
            }
        }
        
        if ($validated['status'] === 'cancelled' || $validated['status'] === 'rejected') {
            $order->cancelled_at = now();
            
            // Restore stock
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increaseStock(
                        $item->quantity,
                        "Order #{$order->order_number} {$validated['status']}"
                    );
                }
            }
        }
        
        $order->save();
        
        // Notify the buyer in real-time about the status update
        notify()->sendToUser($order->buyer, [
            'type' => 'order_status_updated',
            'title' => 'Order Status Updated',
            'body' => "Your order #{$order->order_number} has been " . strtoupper($validated['status']) . ".",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $validated['status'],
            ],
            'actions' => [
                ['label' => 'View Order', 'url' => "/buyer/orders/{$order->id}"],
            ],
        ]);
        
        
        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order
        ]);
    }

    public function statistics()
    {
        $sellerId = auth()->id();
        
        $stats = [
            'total_orders' => Order::where('seller_id', $sellerId)->count(),
            'pending' => Order::where('seller_id', $sellerId)->where('status', 'pending')->count(),
            'processing' => Order::where('seller_id', $sellerId)->whereIn('status', ['confirmed', 'preparing', 'ready_for_delivery'])->count(),
            'delivered' => Order::where('seller_id', $sellerId)->where('status', 'delivered')->count(),
            'cancelled' => Order::where('seller_id', $sellerId)->where('status', 'cancelled')->count(),
            'revenue' => Order::where('seller_id', $sellerId)->where('status', 'delivered')->sum('total'),
        ];
        
        return response()->json($stats);
    }

    /**
     * Send WhatsApp notification to buyer with order status update
     * Returns both desktop app and web URLs
     */
    public function sendWhatsAppNotification($id)
    {
        try {
            $order = Order::where('seller_id', auth()->id())
                ->with(['buyer', 'items'])
                ->findOrFail($id);
            
            // Get buyer's phone number
            $buyerPhone = $order->buyer->phone;
            
            if (!$buyerPhone) {
                Log::warning("Buyer {$order->buyer_id} has no phone number for WhatsApp notification");
                return response()->json([
                    'message' => 'Buyer has no phone number',
                    'whatsapp_urls' => null
                ], 400);
            }
            
            // Clean phone number
            $cleanPhone = preg_replace('/[^0-9]/', '', $buyerPhone);
            $cleanPhone = ltrim($cleanPhone, '0');
            
            // Build the WhatsApp message
            $message = $this->buildWhatsAppMessage($order);
            
            // Encode the message for URL
            $encodedMessage = urlencode($message);
            
            // Return both URLs
            $whatsappUrls = [
                'app' => "whatsapp://send?phone={$cleanPhone}&text={$encodedMessage}",
                'web' => "https://wa.me/{$cleanPhone}?text={$encodedMessage}"
            ];
            
            // Log the notification (optional)
            Log::info("WhatsApp notification prepared for Order #{$order->order_number}", [
                'order_id' => $order->id,
                'buyer_phone' => $buyerPhone,
                'status' => $order->status
            ]);
            
            return response()->json([
                'message' => 'WhatsApp notification generated',
                'whatsapp_urls' => $whatsappUrls,
                'order' => $order
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp notification: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to generate WhatsApp notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build WhatsApp message for buyer with status update
     */
    private function buildWhatsAppMessage($order)
    {
        // Get seller info
        $seller = auth()->user();
        
        // Build order items list with better formatting
        $itemsList = "";
        foreach ($order->items as $item) {
            $subtotal = $item->product_price * $item->quantity;
            $itemsList .= "• {$item->quantity}x {$item->product_name} - $" . number_format($item->product_price, 2) . " (Total: $" . number_format($subtotal, 2) . ")\n";
        }
        
        // Format the message - similar to buyer's style but for status updates
        $message = "🧾 *ORDER STATUS UPDATE - U-CONNECT*\n\n";
        $message .= "─────────────────────\n";
        $message .= "📋 *ORDER DETAILS*\n";
        $message .= "─────────────────────\n";
        $message .= "Order #: *{$order->order_number}*\n";
        $message .= "Date: " . $order->created_at->format('Y-m-d H:i:s') . "\n";
        $message .= "Status: *" . $this->getStatusEmoji($order->status) . " " . strtoupper($order->status) . "*\n\n";
        
        $message .= "👤 *SELLER INFORMATION*\n";
        $message .= "─────────────────────\n";
        $message .= "Store: {$seller->name}\n";
        if ($seller->phone) {
            $message .= "Seller Phone: {$seller->phone}\n";
        }
        $message .= "\n";
        
        $message .= "📦 *ORDER ITEMS*\n";
        $message .= "─────────────────────\n";
        $message .= $itemsList . "\n";
        
        $message .= "💰 *PAYMENT SUMMARY*\n";
        $message .= "─────────────────────\n";
        $message .= "Subtotal: $" . number_format($order->subtotal, 2) . "\n";
        $message .= "Total: $" . number_format($order->total, 2) . "\n\n";
        
        $message .= "🚚 *DELIVERY ADDRESS*\n";
        $message .= "─────────────────────\n";
        $message .= "{$order->delivery_address}\n\n";
        
        $message .= "💳 *PAYMENT METHOD*\n";
        $message .= "─────────────────────\n";
        $message .= "Cash on Delivery\n\n";
        
        $message .= "📌 *STATUS UPDATE*\n";
        $message .= "─────────────────────\n";
        $message .= $this->getStatusMessage($order->status) . "\n\n";
        
        if ($order->notes) {
            $message .= "📝 *ORDER NOTES*\n";
            $message .= "─────────────────────\n";
            $message .= "{$order->notes}\n\n";
        }
        
        $message .= "─────────────────────\n";
        $message .= "Thank you for shopping with U-Connect! 🙏\n";
        $message .= "For support, contact: support@uconnect.com";
        
        return $message;
    }

    private function sendBuyerNotification($order)
    {
        // Here you can implement SMS or email notification
        // For now, just log it
        Log::info("Order #{$order->order_number} status updated to {$order->status} for buyer #{$order->buyer_id}");
    }

    /**
     * Get status emoji for order status
     */
    private function getStatusEmoji($status)
    {
        $emojis = [
            'pending' => '⏳',
            'confirmed' => '✅',
            'preparing' => '🔧',
            'ready_for_delivery' => '🚚',
            'delivered' => '🎉',
            'cancelled' => '❌',
            'rejected' => '⚠️'
        ];
        
        return $emojis[$status] ?? '📦';
    }

    /**
     * Get status message for order status
     */
    private function getStatusMessage($status)
    {
        $messages = [
            'pending' => "⏳ Your order is pending confirmation.\n                    We'll notify you once confirmed.",
            'confirmed' => "✅ Your order has been CONFIRMED!\n                    Our team is preparing your items.",
            'preparing' => "🔧 Your order is being PREPARED.\n                    We're getting everything ready for you.",
            'ready_for_delivery' => "🚚 Your order is READY FOR DELIVERY!\n                    Our delivery partner will contact you soon.",
            'delivered' => "🎉 Your order has been DELIVERED!\n                    We hope you love your purchase!",
            'cancelled' => "❌ Your order has been CANCELLED.\n                    Please contact support for assistance.",
            'rejected' => "⚠️ Your order has been REJECTED.\n                    Please contact support for alternatives."
        ];
        
        $baseMessage = $messages[$status] ?? "📦 Status: " . strtoupper($status);
        
        if ($status === 'delivered') {
            $baseMessage .= "\n\n                    ⭐ Enjoyed your purchase? Rate us 5 stars! ⭐";
        } elseif ($status === 'ready_for_delivery') {
            $baseMessage .= "\n\n                    💡 Tip: Have cash ready for delivery";
        }
        
        return $baseMessage;
    }
}