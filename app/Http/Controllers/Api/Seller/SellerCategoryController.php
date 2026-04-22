<?php
// app/Http/Controllers/Api/Seller/SellerCategoryController.php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SellerCategoryController extends Controller
{
    /**
     * Get all parent categories with subcategory counts and product previews
     */
  public function parentCategories()
{
    $perPage = request()->get('per_page', 12);
    
    $categories = Category::where('is_active', true)
        ->whereNull('parent_id')
        ->with(['children' => function($q) {
            $q->where('is_active', true)
              ->with(['products' => function($pq) {
                  $pq->where('is_active', true)
                     ->where('seller_id', auth()->id())
                     ->select('id', 'name', 'image', 'category_id', 'price')
                     ->latest()
                     ->limit(5);
              }])
              ->withCount(['products' => function($pq) {
                  $pq->where('seller_id', auth()->id());
              }]);
        }])
        ->withCount(['children' => function($q) {
            $q->where('is_active', true);
        }])
        ->orderBy('name')
        ->paginate($perPage);
    
    // Transform categories to include full image URLs and calculate product counts
    $categories->getCollection()->transform(function ($category) {
        // Add category image URL
        if ($category->image) {
            if (!filter_var($category->image, FILTER_VALIDATE_URL)) {
                $category->image = url($category->image);
            }
        }
        
        // Calculate total products in all subcategories
        $totalProducts = 0;
        foreach ($category->children as $subcategory) {
            // Add subcategory image URL
            if ($subcategory->image) {
                if (!filter_var($subcategory->image, FILTER_VALIDATE_URL)) {
                    $subcategory->image = url($subcategory->image);
                }
            }
            
            // Transform product images in subcategories
            if ($subcategory->products) {
                $subcategory->products->transform(function ($product) {
                    if ($product->image) {
                        if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                            $product->image = url($product->image);
                        }
                    }
                    return $product;
                });
            }
            
            $totalProducts += $subcategory->products_count;
        }
        
        $category->subcategories_count = $category->children_count;
        $category->total_products_count = $totalProducts;
        
        return $category;
    });
    
    return response()->json($categories);
}
    /**
 * Get subcategories for a specific parent category with product previews
 */
public function subcategories($parentId)
{
    $perPage = request()->get('per_page', 12);
    
    $parentCategory = Category::where('is_active', true)
        ->whereNull('parent_id')
        ->findOrFail($parentId);
    
    // Add image URL for parent category
    if ($parentCategory->image) {
        if (!filter_var($parentCategory->image, FILTER_VALIDATE_URL)) {
            $parentCategory->image = url($parentCategory->image);
        }
    }
    
    $subcategories = Category::where('is_active', true)
        ->where('parent_id', $parentId)
        ->with(['products' => function($q) {
            $q->where('is_active', true)
              ->where('seller_id', auth()->id())
              ->select('id', 'name', 'image', 'category_id', 'price', 'quantity')
              ->latest()
              ->limit(6);
        }])
        ->withCount(['products' => function($q) {
            $q->where('is_active', true)
              ->where('seller_id', auth()->id());
        }])
        ->orderBy('name')
        ->paginate($perPage);
    
    // Transform subcategories to include full image URLs
    $subcategories->getCollection()->transform(function ($subcategory) {
        if ($subcategory->image) {
            if (!filter_var($subcategory->image, FILTER_VALIDATE_URL)) {
                $subcategory->image = url($subcategory->image);
            }
        }
        
        // Transform product images
        if ($subcategory->products) {
            $subcategory->products->transform(function ($product) {
                if ($product->image) {
                    if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                        $product->image = url($product->image);
                    }
                }
                return $product;
            });
        }
        
        return $subcategory;
    });
    
    return response()->json([
        'parent_category' => $parentCategory,
        'subcategories' => $subcategories
    ]);
}

    /**
     * Get products for a specific category with full image URLs
     */
    public function categoryProducts($categoryId, Request $request)
    {
        $category = Category::where('is_active', true)->findOrFail($categoryId);
        
        // Add category image URL
        if ($category->image) {
            if (!filter_var($category->image, FILTER_VALIDATE_URL)) {
                $category->image = url($category->image);
            }
        }
        
        $query = Product::where('is_active', true)
            ->where('category_id', $categoryId)
            ->where('seller_id', auth()->id());
        
        // Search
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        
        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }
        
        // Filter by stock
        if ($request->has('stock') && $request->stock !== 'all') {
            if ($request->stock === 'in_stock') {
                $query->where('quantity', '>', 0);
            } elseif ($request->stock === 'out_of_stock') {
                $query->where('quantity', 0);
            } elseif ($request->stock === 'low_stock') {
                $query->whereColumn('quantity', '<=', 'min_stock_alert');
            }
        }
        
        // Sort options
        if ($request->has('sort')) {
            switch ($request->sort) {
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                default:
                    $query->latest();
            }
        } else {
            $query->latest();
        }
        
        $products = $query->paginate(12);
        
        // Transform products to include full image URLs
        $products->getCollection()->transform(function ($product) {
            if ($product->image) {
                if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                    $product->image = url($product->image);
                }
            }
            return $product;
        });
        
        return response()->json([
            'category' => $category,
            'products' => $products
        ]);
    }

    /**
     * Get category statistics for dashboard
     */
    public function categoryStats()
    {
        $stats = [
            'total_categories' => Category::where('is_active', true)
                ->whereNull('parent_id')
                ->count(),
            'total_subcategories' => Category::where('is_active', true)
                ->whereNotNull('parent_id')
                ->count(),
            'categories_with_products' => Category::whereHas('products', function($q) {
                $q->where('seller_id', auth()->id());
            })->count(),
            'top_categories' => Category::select('categories.id', 'categories.name', 'categories.image')
                ->withCount(['products' => function($q) {
                    $q->where('seller_id', auth()->id());
                }])
                ->having('products_count', '>', 0)
                ->orderBy('products_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function($category) {
                    if ($category->image && !filter_var($category->image, FILTER_VALIDATE_URL)) {
                        $category->image = url($category->image);
                    }
                    return $category;
                })
        ];
        
        return response()->json($stats);
    }

    /**
     * Search across all categories and products
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $query = $request->query;
        
        // Search categories
        $categories = Category::where('is_active', true)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->withCount(['products' => function($pq) {
                $pq->where('seller_id', auth()->id());
            }])
            ->limit(5)
            ->get()
            ->map(function($category) {
                if ($category->image && !filter_var($category->image, FILTER_VALIDATE_URL)) {
                    $category->image = url($category->image);
                }
                return $category;
            });
        
        // Search products
        $products = Product::where('is_active', true)
            ->where('seller_id', auth()->id())
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get()
            ->map(function($product) {
                if ($product->image && !filter_var($product->image, FILTER_VALIDATE_URL)) {
                    $product->image = url($product->image);
                }
                return $product;
            });
        
        return response()->json([
            'categories' => $categories,
            'products' => $products,
            'total_results' => $categories->count() + $products->count()
        ]);
    }
}