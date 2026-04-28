<?php
// app/Http/Controllers/Api/Seller/ProductController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected $imageService;

    public function __construct(\App\Services\ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    private function getImageUrl($imagePath)
    {
        if (!$imagePath) {
            return null;
        }
        
        // If it's already a full URL, return as is
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }
        
        // Remove leading slashes for consistent handling
        $imagePath = ltrim($imagePath, '/');
        
        // Check if the image is in storage/categories directory (for category images)
        if (strpos($imagePath, 'storage/categories/') === 0) {
            return url($imagePath);
        }
        
        // For product images stored in storage/products directory
        if (strpos($imagePath, 'storage/products/') === 0) {
            return url($imagePath);
        }
        
        // For legacy images stored in public/images directory
        return url('images/' . $imagePath);
    }

    private function getImagePathFromUrl($url)
    {
        // Extract the path from full URL for storage operations
        $parsedUrl = parse_url($url);
        $path = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : '';
        
        // Remove domain and get relative path
        if (strpos($path, 'storage/') === 0) {
            return $path;
        }
        
        return null;
    }

    public function index(Request $request)
    {
        $query = Product::where('seller_id', auth()->id())
            ->with('category');
        
        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }
        
        // Filter by stock
        if ($request->has('stock')) {
            if ($request->stock === 'in_stock') {
                $query->where('quantity', '>', 0);
            } elseif ($request->stock === 'out_of_stock') {
                $query->where('quantity', 0);
            } elseif ($request->stock === 'low_stock') {
                $query->whereColumn('quantity', '<=', 'min_stock_alert');
            }
        }
        
        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        
        $products = $query->latest()->paginate(10);
        
        // Transform the response to include full image URLs
        $products->getCollection()->transform(function ($product) {
            $product->image = $this->getImageUrl($product->image);
            
            // Handle multiple images if they exist
            if ($product->images) {
                $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
                if (is_array($images)) {
                    $product->images = array_map(function($image) {
                        return $this->getImageUrl($image);
                    }, $images);
                }
            }
            
            // Add category image URL if category exists
            if ($product->category) {
                $product->category->image = $this->getImageUrl($product->category->image);
            }
            
            return $product;
        });
        
        return response()->json($products);
    }

    public function store(\App\Http\Requests\Seller\Product\StoreProductRequest $request)
    {
        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->imageService->compressAndSave($request->file('image'), 'products');
        }
        
        $product = Product::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . uniqid(),
            'description' => $request->description,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'min_stock_alert' => $request->get('min_stock_alert', 5),
            'seller_id' => auth()->id(),
            'category_id' => $request->category_id,
            'image' => $imagePath,
            'discount_percentage' => $request->get('discount_percentage', 0),
            'is_active' => true,
        ]);
        
        // Log initial inventory
        $product->logInventoryChange('add', $request->quantity, 0, 'Product created');
        
        // Add full image URL to response
        $product->image = $this->getImageUrl($product->image);
        
        // Add category image URL if exists
        if ($product->category) {
            $product->category->image = $this->getImageUrl($product->category->image);
        }
        
        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    public function show($id)
    {
        $product = Product::where('seller_id', auth()->id())
            ->with(['category', 'inventoryLogs' => function($q) {
                $q->latest()->take(20);
            }])
            ->findOrFail($id);
        
        // Add full URL for product image
        $product->image = $this->getImageUrl($product->image);
        
        // Handle multiple images if they exist
        if ($product->images) {
            $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
            if (is_array($images)) {
                $product->images = array_map(function($image) {
                    return $this->getImageUrl($image);
                }, $images);
            }
        }
        
        // Add category image URL if exists
        if ($product->category) {
            $product->category->image = $this->getImageUrl($product->category->image);
        }
        
        // Add sales count (if you have orders relationship)
        $product->sales_count = $product->orderItems()->sum('quantity') ?? 0;
        
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::where('seller_id', auth()->id())->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'sometimes|boolean',
            'remove_image' => 'sometimes|boolean',
            'discount_percentage' => 'sometimes|integer|min:0|max:100',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle image removal
        if ($request->input('remove_image') === true || $request->input('remove_image') === 'true' || $request->input('remove_image') === '1') {
            if ($product->image) {
                $this->imageService->delete($product->image);
                $product->image = null;
            }
        }
        
        // Handle image upload for update
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image) {
                $this->imageService->delete($product->image);
            }
            
            $product->image = $this->imageService->compressAndSave($request->file('image'), 'products');
        }
        
        // Update other fields
        $product->fill($request->only([
            'name', 'description', 'price', 'category_id', 'is_active', 'discount_percentage'
        ]));
        
        if ($request->has('name')) {
            $product->slug = Str::slug($request->name) . '-' . uniqid();
        }
        
        $product->save();
        
        // Add full image URL to response
        $product->image = $this->getImageUrl($product->image);
        
        // Add category image URL if exists
        if ($product->category) {
            $product->category->image = $this->getImageUrl($product->category->image);
        }
        
        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    public function destroy($id)
    {
        $product = Product::where('seller_id', auth()->id())->findOrFail($id);
        
        // Check if product has any orders
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product with existing orders'
            ], 400);
        }
        
        $productName = $product->name;
        $seller = auth()->user();
        
        // Delete product image if exists
        if ($product->image) {
            $this->imageService->delete($product->image);
        }
        
        $product->delete();
        
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function updateStock(Request $request, $id)
    {
        $product = Product::where('seller_id', auth()->id())->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $oldQuantity = $product->quantity;
        $change = $request->quantity - $oldQuantity;
        
        if ($change != 0) {
            $product->quantity = $request->quantity;
            $product->save();
            
            $product->logInventoryChange(
                'adjustment',
                $change,
                $oldQuantity,
                $request->get('reason', 'Manual stock adjustment')
            );
        }
        
        // Add image URL to response
        $product->image = $this->getImageUrl($product->image);
        
        return response()->json([
            'message' => 'Stock updated successfully',
            'product' => $product
        ]);
    }

    public function categories()
    {
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => function ($q) {
                $q->where('is_active', true);
            }])
            ->get();
        
        // Transform categories to include image URLs
        $categories->transform(function ($category) {
            $category->image = $this->getImageUrl($category->image);
            
            if ($category->children) {
                $category->children->transform(function ($child) {
                    $child->image = $this->getImageUrl($child->image);
                    return $child;
                });
            }
            
            return $category;
        });

        return response()->json($categories);
    }

    public function incrementView($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->increment('views_count');
            
            return response()->json([
                'success' => true,
                'views_count' => $product->views_count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to increment view count'
            ], 500);
        }
    }
}