<?php
// app/Http/Controllers/Api/Buyer/CartController.php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Mail\SellerOrderMail;

class CartController extends Controller
{
    /**
     * Get user's cart
     */
    public function index()
    {
        $cart = Cart::where('buyer_id', auth()->id())
            ->with('product')
            ->get();
        
        $summary = [
            'total_items' => $cart->sum('quantity'),
            'subtotal' => $cart->sum(function($item) {
                return $item->product->discounted_price * $item->quantity;
            }),
        ];
        
        return response()->json([
            'cart' => $cart,
            'summary' => $summary
        ]);
    }

    /**
     * Add product to cart
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $product = Product::findOrFail($request->product_id);
        
        // Check if product is available
        if (!$product->is_active || $product->quantity <= 0) {
            return response()->json(['message' => 'Product is not available'], 400);
        }
        
        // Check if seller is active and can sell
        if (!$product->seller->canSell()) {
            return response()->json([
                'message' => 'This seller account is currently not activated.',
                'error_code' => 'SELLER_INACTIVE'
            ], 403);
        }
        
        // Check if product already in cart
        $existingCartItem = Cart::where('buyer_id', auth()->id())
            ->where('product_id', $request->product_id)
            ->first();
        
        if ($existingCartItem) {
            $newQuantity = $existingCartItem->quantity + $request->quantity;
            if ($newQuantity > $product->quantity) {
                return response()->json(['message' => 'Not enough stock'], 400);
            }
            $existingCartItem->quantity = $newQuantity;
            $existingCartItem->save();
        } else {
            if ($request->quantity > $product->quantity) {
                return response()->json(['message' => 'Not enough stock'], 400);
            }
            Cart::create([
                'buyer_id' => auth()->id(),
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }
        
        return response()->json(['message' => 'Product added to cart']);
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $cartItem = Cart::where('buyer_id', auth()->id())
            ->with('product')
            ->findOrFail($id);
        
        $product = $cartItem->product;
        
        if ($request->quantity > $product->quantity) {
            return response()->json(['message' => 'Not enough stock'], 400);
        }
        
        $cartItem->quantity = $request->quantity;
        $cartItem->save();
        
        return response()->json(['message' => 'Cart updated']);
    }

    /**
     * Remove item from cart
     */
    public function remove($id)
    {
        $cartItem = Cart::where('buyer_id', auth()->id())
            ->findOrFail($id);
        
        $cartItem->delete();
        
        return response()->json(['message' => 'Item removed from cart']);
    }

    /**
     * Clear entire cart
     */
    public function clear()
    {
        Cart::where('buyer_id', auth()->id())->delete();
        
        return response()->json(['message' => 'Cart cleared']);
    }

    /**
     * Checkout - create order from cart items
     */
    public function checkout(\App\Http\Requests\Buyer\Cart\CheckoutRequest $request)
    {
        $cartItems = Cart::where('buyer_id', auth()->id())
            ->with('product')
            ->get();
        
        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }
        
        // Check if all items are from the same seller
        $sellerId = $cartItems->first()->product->seller_id;
        $seller = \App\Models\User::find($sellerId);

        if (!$seller || !$seller->canSell()) {
            return response()->json([
                'message' => 'This seller account is currently not activated.',
                'error_code' => 'SELLER_INACTIVE'
            ], 403);
        }

        foreach ($cartItems as $item) {
            if ($item->product->seller_id != $sellerId) {
                return response()->json([
                    'message' => 'Please order from one seller at a time'
                ], 400);
            }
            
            if ($item->quantity > $item->product->quantity) {
                return response()->json([
                    'message' => "Not enough stock for {$item->product->name}"
                ], 400);
            }
        }

        $subtotal = $cartItems->sum(function($item) {
            return $item->product->discounted_price * $item->quantity;
        });

        DB::beginTransaction();

        try {
            // Create order
            $orderNumber = 'ORD-' . strtoupper(uniqid());
            $order = Order::create([
                'order_number' => $orderNumber,
                'buyer_id' => auth()->id(),
                'seller_id' => $sellerId,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'delivery_address' => $request->delivery_address,
                'notes' => $request->notes,
                'status' => 'pending',
                'payment_method' => 'cash_on_delivery',
                'payment_status' => 'pending',
            ]);
            
            // Create order items and reduce stock
            foreach ($cartItems as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_price' => $item->product->discounted_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->product->discounted_price * $item->quantity,
                ]);
                
                // Reduce stock
                $item->product->decreaseStock($item->quantity);
            }
            
            // Clear cart
            Cart::where('buyer_id', auth()->id())->delete();
            
            // Generate PDF
            $pdf = Pdf::loadView('pdf.order', compact('order'));
            $pdfContent = $pdf->output();

            // Send Email to seller
            Mail::to($seller->email)->send(new SellerOrderMail($order, $pdfContent));

            DB::commit();

            // Notify the seller in real-time about the new order
            notify()->sendToUser($order->seller, [
                'type' => 'order_placed',
                'title' => 'New Order Received! 🛍️',
                'body' => "You have received a new order #{$order->order_number} from {$order->buyer->name}.",
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'buyer_name' => $order->buyer->name,
                ],
                'actions' => [
                    ['label' => 'View Order Details', 'url' => "/seller/orders/{$order->id}"],
                ],
            ]);

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Order placement failed: " . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to process the order. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}