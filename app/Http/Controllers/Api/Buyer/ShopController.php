<?php
// app/Http/Controllers/Api/Buyer/ShopController.php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ShopController extends Controller
{
    // 
    public function products(Request $request)
    {
        $query = Product::where('is_active', true)
            ->where('quantity', '>', 0)
            ->with(['seller', 'category']);
        
        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        
        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
        }
        
        // Sorting
        $sortBy = $request->get('sort_by', 'latest');
        switch ($sortBy) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'popular':
                $query->orderBy('sales_count', 'desc');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }
        
        $products = $query->paginate(12);
        
        // Transform products to include full image URLs
        $products->getCollection()->transform(function ($product) {
            if ($product->image) {
                if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                    $product->image = url($product->image);
                }
            }
            
            // Handle multiple images if they exist
            if ($product->images) {
                $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
                if (is_array($images)) {
                    $product->images = array_map(function($image) {
                        if (!filter_var($image, FILTER_VALIDATE_URL)) {
                            return url($image);
                        }
                        return $image;
                    }, $images);
                }
            }
            
            return $product;
        });
        
        return response()->json($products);
    }

    public function productDetails($id)
    {
        $product = Product::where('is_active', true)
            ->with(['seller', 'category'])
            ->findOrFail($id);
        
        // Add full image URL
        if ($product->image) {
            if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                $product->image = url($product->image);
            }
        }
        
        // Handle multiple images
        if ($product->images) {
            $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
            if (is_array($images)) {
                $product->images = array_map(function($image) {
                    if (!filter_var($image, FILTER_VALIDATE_URL)) {
                        return url($image);
                    }
                    return $image;
                }, $images);
            }
        }
        
        // Increment view count
        $product->increment('views_count');
        
        // Get related products (same category)
        $related = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->limit(10)
            ->get();
        
        // Add full image URLs to related products
        $related->transform(function ($item) {
            if ($item->image) {
                if (!filter_var($item->image, FILTER_VALIDATE_URL)) {
                    $item->image = url($item->image);
                }
            }
            return $item;
        });
        
        return response()->json([
            'product' => $product,
            'related_products' => $related
        ]);
    }


public function sellers(Request $request)
{
    $sellers = User::role('seller')
        ->where('is_active', true)
        ->withCount(['products' => function($q) {
            $q->where('is_active', true)->where('quantity', '>', 0);
        }])
        ->having('products_count', '>', 0)
        ->paginate($request->get('per_page', 6)); // 6 per page for sellers
    
    // Add search functionality
    if ($request->has('search') && !empty($request->search)) {
        $search = $request->search;
        $sellers = User::role('seller')
            ->where('is_active', true)
            ->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('store_name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            })
            ->withCount(['products' => function($q) {
                $q->where('is_active', true)->where('quantity', '>', 0);
            }])
            ->having('products_count', '>', 0)
            ->paginate($request->get('per_page', 6));
    }
    
    // Transform sellers data
    $sellers->getCollection()->transform(function ($seller) {
        // Add full image URLs for cover image
        if ($seller->cover_image) {
            if (!filter_var($seller->cover_image, FILTER_VALIDATE_URL)) {
                $seller->cover_image = url($seller->cover_image);
            }
        }
        
        // Add full image URLs for store logo
        if ($seller->store_logo) {
            if (!filter_var($seller->store_logo, FILTER_VALIDATE_URL)) {
                $seller->store_logo = url($seller->store_logo);
            }
        }
        
        // Calculate rating (you can implement this based on your review system)
        $seller->rating = $seller->rating ?? 4.5;
        
        return $seller;
    });
    
    return response()->json($sellers);
}
   public function sellerProducts($sellerId, Request $request)
{
    $seller = User::role('seller')->findOrFail($sellerId);
    
    // Add full image URLs for seller
    if ($seller->cover_image) {
        if (!filter_var($seller->cover_image, FILTER_VALIDATE_URL)) {
            $seller->cover_image = url($seller->cover_image);
        }
    }
    if ($seller->store_logo) {
        if (!filter_var($seller->store_logo, FILTER_VALIDATE_URL)) {
            $seller->store_logo = url($seller->store_logo);
        }
    }
    
    $query = Product::where('seller_id', $sellerId)
        ->where('is_active', true)
        ->where('quantity', '>', 0);
    
    // Search functionality
    if ($request->has('search') && !empty($request->search)) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
    
    // Filter by price range
    if ($request->has('min_price') && is_numeric($request->min_price)) {
        $query->where('price', '>=', $request->min_price);
    }
    if ($request->has('max_price') && is_numeric($request->max_price)) {
        $query->where('price', '<=', $request->max_price);
    }
    
    // Sorting
    $sortBy = $request->get('sort_by', 'latest');
    switch ($sortBy) {
        case 'price_low':
            $query->orderBy('price', 'asc');
            break;
        case 'price_high':
            $query->orderBy('price', 'desc');
            break;
        case 'popular':
            $query->orderBy('sales_count', 'desc');
            break;
        case 'latest':
        default:
            $query->latest();
            break;
    }
    
    // Pagination (5 per page as originally set)
    $perPage = $request->get('per_page', 10);
    $products = $query->paginate($perPage);
    
    // Transform products to include full image URLs
    $products->getCollection()->transform(function ($product) {
        if ($product->image) {
            if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                $product->image = url($product->image);
            }
        }
        
        // Handle multiple images if they exist
        if ($product->images) {
            $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
            if (is_array($images)) {
                $product->images = array_map(function($image) {
                    if (!filter_var($image, FILTER_VALIDATE_URL)) {
                        return url($image);
                    }
                    return $image;
                }, $images);
            }
        }
        
        return $product;
    });
    
    return response()->json([
        'seller' => $seller,
        'products' => $products
    ]);
}

    // Get parent categories only (for main categories page)
    // Get parent categories only (for main categories page) - with pagination
    public function parentCategories(Request $request)
    {
        $query = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => function($q) {
                $q->where('is_active', true);
            }])
            ->withCount(['children' => function($q) {
                $q->where('is_active', true);
            }]);
        
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $perPage = $request->get('per_page', 6);
        $search = $request->get('search', '');
        $page = $request->get('page', 1);

        $cacheKey = "parent_categories_page_{$page}_per_{$perPage}_search_" . md5($search);

        return Cache::remember($cacheKey, 3600, function() use ($query, $perPage) {
            $parentCategories = $query->orderBy('name')->paginate($perPage);
            
            // Collect all IDs needed for queries
            $categoryIdsMap = [];
            $allNeededCategoryIds = [];
            
            foreach ($parentCategories as $category) {
                $ids = [$category->id];
                if ($category->children) {
                    $ids = array_merge($ids, $category->children->pluck('id')->toArray());
                }
                $categoryIdsMap[$category->id] = $ids;
                $allNeededCategoryIds = array_merge($allNeededCategoryIds, $ids);
            }
            
            // Batch query product counts by category_id
            $productCounts = Product::where('is_active', true)
                ->where('quantity', '>', 0)
                ->whereIn('category_id', $allNeededCategoryIds)
                ->select('category_id', DB::raw('count(*) as aggregate'))
                ->groupBy('category_id')
                ->pluck('aggregate', 'category_id')->toArray();
                
            // Batch query popular products by category
            $popularProductsByCat = Product::where('is_active', true)
                ->where('quantity', '>', 0)
                ->whereIn('category_id', $allNeededCategoryIds)
                ->orderBy('sales_count', 'desc')
                ->get(['id', 'name', 'image', 'price', 'category_id']);
                
            // Map images
            $popularProductsByCat->transform(function($product) {
                if ($product->image && !filter_var($product->image, FILTER_VALIDATE_URL)) {
                    $product->image = url($product->image);
                }
                return $product;
            });

            // Transform each category to add additional data
            $parentCategories->getCollection()->transform(function ($category) use ($categoryIdsMap, $productCounts, $popularProductsByCat) {
                if ($category->image) {
                    if (!filter_var($category->image, FILTER_VALIDATE_URL)) {
                        $category->image = url($category->image);
                    }
                }
                
                $category->subcategories_count = $category->children_count;
                
                $relevantCatIds = $categoryIdsMap[$category->id] ?? [$category->id];
                
                // Calculate total count
                $totalCount = 0;
                foreach ($relevantCatIds as $id) {
                    $totalCount += $productCounts[$id] ?? 0;
                }
                $category->products_count = $totalCount;
                
                // Get popular products
                $category->popular_products = $popularProductsByCat
                    ->whereIn('category_id', $relevantCatIds)
                    ->take(4)->values();
                
                return $category;
            });
            
            return response()->json($parentCategories);
        });
    }


    // Get category statistics
    public function categoryStats()
    {
        $totalCategories = Category::where('is_active', true)->whereNull('parent_id')->count();
        $totalProducts = Product::where('is_active', true)
            ->where('quantity', '>', 0)
            ->count();
        
        return response()->json([
            'total_categories' => $totalCategories,
            'total_products' => $totalProducts
        ]);
    }

public function subcategories($parentId, Request $request)
{
    $parentCategory = Category::where('is_active', true)
        ->whereNull('parent_id')
        ->findOrFail($parentId);
    
    // Add parent category image URL
    if ($parentCategory->image) {
        if (!filter_var($parentCategory->image, FILTER_VALIDATE_URL)) {
            $parentCategory->image = url($parentCategory->image);
        }
    }
    
    $query = Category::where('is_active', true)
        ->where('parent_id', $parentId)
        ->withCount(['products' => function($q) {
            $q->where('is_active', true)->where('quantity', '>', 0);
        }]);
    
    // Add search functionality
    if ($request->has('search') && !empty($request->search)) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
    
    // Apply pagination (6 per page)
    $perPage = $request->get('per_page', 6);
    $subcategories = $query->orderBy('name')->paginate($perPage);
    
    $subcategoryIds = $subcategories->pluck('id')->toArray();
    
    // Batch fetch popular products
    $popularProductsBySubcat = Product::where('is_active', true)
        ->where('quantity', '>', 0)
        ->whereIn('category_id', $subcategoryIds)
        ->orderBy('sales_count', 'desc')
        ->get(['id', 'name', 'image', 'price', 'category_id']);
        
    $popularProductsBySubcat->transform(function($product) {
        if ($product->image && !filter_var($product->image, FILTER_VALIDATE_URL)) {
            $product->image = url($product->image);
        }
        return $product;
    });
    
    // Transform subcategories to add additional data
    $subcategories->getCollection()->transform(function($subcategory) use ($popularProductsBySubcat) {
        // Add subcategory image URL
        if ($subcategory->image) {
            if (!filter_var($subcategory->image, FILTER_VALIDATE_URL)) {
                $subcategory->image = url($subcategory->image);
            }
        }
        
        // Get popular products for this subcategory
        $subcategory->popular_products = $popularProductsBySubcat
            ->where('category_id', $subcategory->id)
            ->take(4)->values();
        
        return $subcategory;
    });
    
    return response()->json([
        'parent_category' => $parentCategory,
        'subcategories' => $subcategories
    ]);
}

    // Get products for a specific category (works for both parent and subcategories)
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
            ->where('quantity', '>', 0)
            ->where('category_id', $categoryId)
            ->with(['seller', 'category']);
        
        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        
        // Search within category
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }
        
        // Sorting
        $sortBy = $request->get('sort_by', 'latest');
        switch ($sortBy) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'popular':
                $query->orderBy('sales_count', 'desc');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }
        
        $products = $query->paginate(6);
        
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

    public function featuredProducts()
    {
        $products = Product::where('is_active', true)
            ->where('is_featured', true)
            ->where('quantity', '>', 0)
            ->with(['seller', 'category'])
            ->limit(12)
            ->get();
        
        // Add full image URLs
        $products->transform(function ($product) {
            if ($product->image) {
                if (!filter_var($product->image, FILTER_VALIDATE_URL)) {
                    $product->image = url($product->image);
                }
            }
            return $product;
        });
        
        return response()->json($products);
    }

    
  public function categories(Request $request)
{
    $categories = Category::where('is_active', true)
        ->whereHas('products', function($q) {
            $q->where('is_active', true)
              ->where('quantity', '>', 0);
        })
        ->withCount(['products' => function($q) {
            $q->where('is_active', true)->where('quantity', '>', 0);
        }])
        ->orderBy('name')
        ->paginate($request->get('per_page', 6)) // Add pagination
        ->through(function($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'products_count' => $category->products_count,
                'image' => $category->image ? (filter_var($category->image, FILTER_VALIDATE_URL) ? $category->image : url($category->image)) : null,
            ];
        });
    
    // Calculate statistics (these should be separate endpoints or cached)
    $statistics = [
        'total_categories' => Category::where('is_active', true)->count(),
        'total_products' => Product::where('is_active', true)->where('quantity', '>', 0)->count(),
        'categories_with_products' => Category::where('is_active', true)
            ->whereHas('products', function($q) {
                $q->where('is_active', true)->where('quantity', '>', 0);
            })->count(),
    ];
    
    return response()->json([
        'success' => true,
        'data' => $categories,
        'statistics' => $statistics,
        'message' => 'Categories retrieved successfully'
    ]);
}
}