<?php
// app/Http/Controllers/Api/Admin/ProductManagementController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductManagementController extends Controller
{
    

    public function index(Request $request)
    {
        $query = Product::with(['seller', 'category']);
        
        // Filter by seller
        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        
        // Filter by stock
        if ($request->has('stock_status')) {
            if ($request->stock_status === 'out_of_stock') {
                $query->where('quantity', '<=', 0);
            } elseif ($request->stock_status === 'low_stock') {
                $query->whereColumn('quantity', '<=', 'min_stock_alert');
            } elseif ($request->stock_status === 'in_stock') {
                $query->where('quantity', '>', 0);
            }
        }
        
        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        
        $products = $query->latest()->paginate(5);
        
        // Transform products to include full image URLs
        $products->getCollection()->transform(function ($product) {
            if ($product->image) {
                // Check if it's already a full URL
                if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                    // If it starts with /storage/, use Storage::url
                    if (str_starts_with($product->image, '/storage/')) {
                        $product->image = url($product->image);
                    } else {
                        $product->image = Storage::url($product->image);
                    }
                }
            }
            return $product;
        });
        
        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::with(['seller', 'category', 'inventoryLogs' => function($q) {
            $q->latest()->take(5);
        }])->findOrFail($id);
        
        // Add full image URL
        if ($product->image) {
            if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                if (str_starts_with($product->image, '/storage/')) {
                    $product->image = url($product->image);
                } else {
                    $product->image = Storage::url($product->image);
                }
            }
        }
        
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
        ]);
        
        $product->update($validated);
        
        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        
        // Delete product image if exists
        if ($product->image) {
            $path = str_replace('/storage/', '', $product->image);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
        
        $product->delete();
        
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function featured(Request $request)
    {
        $products = Product::with(['seller', 'category'])
            ->where('is_featured', true)
            ->where('is_active', true)
            ->latest()
            ->paginate(12);
        
        // Transform products to include full image URLs
        $products->getCollection()->transform(function ($product) {
            if ($product->image) {
                if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                    if (str_starts_with($product->image, '/storage/')) {
                        $product->image = url($product->image);
                    } else {
                        $product->image = Storage::url($product->image);
                    }
                }
            }
            return $product;
        });
        
        return response()->json($products);
    }
}