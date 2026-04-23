<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\SellerRating;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class SellerRatingController extends Controller
{
    /**
     * Store a newly created rating for a seller.
     */
    public function store(Request $request, $sellerId)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'rating'   => 'required|integer|min:1|max:5',
            'comment'  => 'nullable|string|max:1000',
        ]);

        $seller = User::findOrFail($sellerId);
        $orderId = $request->order_id;
        $buyerId = auth()->id();

        // Ensure the order belongs to the buyer and seller, and is delivered
        $order = Order::where('id', $orderId)
                      ->where('buyer_id', $buyerId)
                      ->where('seller_id', $seller->id)
                      ->where('status', 'delivered')
                      ->first();

        if (!$order) {
            return response()->json(['message' => 'You can only rate a seller for a delivered order.'], 403);
        }

        // Check if rating already exists for this order
        $existingRating = SellerRating::where('buyer_id', $buyerId)
                                      ->where('order_id', $orderId)
                                      ->first();

        if ($existingRating) {
            return response()->json(['message' => 'You have already rated this seller for this order.'], 400);
        }

        $rating = SellerRating::create([
            'seller_id' => $seller->id,
            'buyer_id'  => $buyerId,
            'order_id'  => $orderId,
            'rating'    => $request->rating,
            'comment'   => $request->comment,
        ]);

        return response()->json([
            'message' => 'Rating submitted successfully.',
            'rating'  => $rating
        ], 201);
    }
}
