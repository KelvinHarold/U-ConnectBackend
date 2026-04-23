<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductComment;
use Illuminate\Http\Request;

class ProductCommentController extends Controller
{
    /**
     * GET /api/products/{id}/comments
     * Public: list all comments for a product (newest first).
     */
    public function index($id)
    {
        $product = Product::findOrFail($id);

        $comments = ProductComment::with(['user:id,name,profile_photo'])
            ->where('product_id', $product->id)
            ->latest()
            ->get();

        return response()->json(['comments' => $comments], 200);
    }

    /**
     * POST /api/products/{id}/comments
     * Auth required: any logged-in user can comment.
     */
    public function store(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'body'   => 'required|string|max:500',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $comment = ProductComment::create([
            'product_id' => $product->id,
            'user_id'    => auth()->id(),
            'body'       => $request->body,
            'rating'     => $request->rating,
        ]);

        // Load the user relationship for the response
        $comment->load('user:id,name,profile_photo');

        return response()->json(['comment' => $comment], 201);
    }

    /**
     * DELETE /api/products/{id}/comments/{commentId}
     * Auth required: users can only delete their own comments.
     */
    public function destroy($id, $commentId)
    {
        $comment = ProductComment::where('product_id', $id)
            ->where('id', $commentId)
            ->firstOrFail();

        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted'], 200);
    }
}
