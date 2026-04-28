<?php
// app/Http/Controllers/Api/Common/LandingPageController.php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LandingPageController extends Controller
{
    private function getImageUrl($imagePath)
    {
        if (!$imagePath) {
            return null;
        }
        
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }
        
        if (str_starts_with($imagePath, '/storage/')) {
            return url($imagePath);
        }
        
        if (str_starts_with($imagePath, 'storage/')) {
            return url('/' . $imagePath);
        }
        
        return url('/storage/' . ltrim($imagePath, '/'));
    }

    /**
     * Get all products for landing page (paginated)
     */
    public function getProducts(Request $request)
    {
        try {
            $query = Product::fromPaidSellers()
                ->with(['category', 'seller'])
                ->where('is_active', true)
                ->where('quantity', '>', 0);

            if ($request->has('category_id') && $request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            $allowedSortFields = ['name', 'price', 'created_at', 'views_count', 'sales_count'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $perPage = min($request->get('per_page', 12), 100); // Cap at 100 max
            $products = $query->paginate($perPage);

            $formattedProducts = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'price' => (float) $product->price,
                    'quantity' => (int) $product->quantity,
                    'image' => $this->getImageUrl($product->image),
                    'images' => $product->images ? json_decode($product->images, true) : [],
                    'is_featured' => (bool) $product->is_featured,
                    'discount_percentage' => (int) $product->discount_percentage,
                    'discounted_price' => (float) $product->discounted_price,
                    'views_count' => (int) $product->views_count,
                    'sales_count' => (int) $product->sales_count,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'slug' => $product->category->slug,
                    ] : null,
                    'seller' => $product->seller ? [
                        'id' => $product->seller->id,
                        'name' => $product->seller->name,
                    ] : null,
                    'created_at' => $product->created_at ? $product->created_at->toISOString() : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedProducts,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'message' => 'Products retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getProducts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Error fetching products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured products (limited, no pagination needed)
     */
    public function getFeaturedProducts(Request $request)
    {
        try {
            $limit = min($request->get('limit', 8), 50); // Cap at 50
            
            $formattedProducts = Cache::remember("featured_products_limit_{$limit}", 3600, function() use ($limit) {
                $products = Product::fromPaidSellers()
                    ->with(['category', 'seller'])
                    ->where('is_active', true)
                    ->where('quantity', '>', 0)
                    ->where('is_featured', true)
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();

                return $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'description' => $product->description,
                        'price' => (float) $product->price,
                        'quantity' => (int) $product->quantity,
                        'image' => $this->getImageUrl($product->image),
                        'images' => $product->images ? json_decode($product->images, true) : [],
                        'is_featured' => (bool) $product->is_featured,
                        'discount_percentage' => (int) $product->discount_percentage,
                        'discounted_price' => (float) $product->discounted_price,
                        'views_count' => (int) $product->views_count,
                        'sales_count' => (int) $product->sales_count,
                        'category' => $product->category ? [
                            'id' => $product->category->id,
                            'name' => $product->category->name,
                        ] : null,
                        'created_at' => $product->created_at ? $product->created_at->toISOString() : null,
                    ];
                })->toArray();
            });

            return response()->json([
                'success' => true,
                'data' => $formattedProducts,
                'message' => 'Featured products retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getFeaturedProducts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Error fetching featured products'
            ], 500);
        }
    }

    /**
     * Get all categories (paginated)
     */
    public function getCategories(Request $request)
    {
        try {
            $perPage = min($request->get('per_page', 20), 50); // Cap at 50
            $page = $request->get('page', 1);

            return Cache::remember("categories_page_{$page}_per_{$perPage}", 86400, function() use ($perPage) {
                $categories = Category::where('is_active', true)
                    ->whereNull('parent_id')
                    ->withCount(['products' => function($q) {
                        $q->fromPaidSellers()
                          ->where('is_active', true)->where('quantity', '>', 0);
                    }])
                    ->orderBy('name', 'asc')
                    ->paginate($perPage);

                $formattedCategories = $categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'description' => $category->description,
                        'image' => $category->image ? $this->getImageUrl($category->image) : null,
                        'parent_id' => $category->parent_id,
                        'is_active' => (bool) $category->is_active,
                        'product_count' => $category->products_count,
                        'created_at' => $category->created_at ? $category->created_at->toISOString() : null,
                    ];
                });

                return response()->json([
                    'success' => true,
                    'data' => $formattedCategories,
                    'pagination' => [
                        'current_page' => $categories->currentPage(),
                        'last_page' => $categories->lastPage(),
                        'per_page' => $categories->perPage(),
                        'total' => $categories->total(),
                    ],
                    'message' => 'Categories retrieved successfully'
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error in getCategories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Error fetching categories'
            ], 500);
        }
    }

    /**
     * Get subcategories for a specific category (paginated)
     */
    public function getSubcategories($categoryId, Request $request)
    {
        try {
            $category = Category::find($categoryId);
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            $perPage = min($request->get('per_page', 20), 50);
            
            $subcategories = Category::where('parent_id', $categoryId)
                ->where('is_active', true)
                ->withCount(['products' => function($q) {
                    $q->fromPaidSellers()
                      ->where('is_active', true)->where('quantity', '>', 0);
                }])
                ->orderBy('name', 'asc')
                ->paginate($perPage);
            
            $formattedSubcategories = $subcategories->map(function ($subcategory) {
                return [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'slug' => $subcategory->slug,
                    'description' => $subcategory->description,
                    'image' => $subcategory->image ? $this->getImageUrl($subcategory->image) : null,
                    'parent_id' => $subcategory->parent_id,
                    'products_count' => $subcategory->products_count,
                    'created_at' => $subcategory->created_at ? $subcategory->created_at->toISOString() : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedSubcategories,
                'pagination' => [
                    'current_page' => $subcategories->currentPage(),
                    'last_page' => $subcategories->lastPage(),
                    'per_page' => $subcategories->perPage(),
                    'total' => $subcategories->total(),
                ],
                'message' => 'Subcategories retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getSubcategories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Error fetching subcategories'
            ], 500);
        }
    }

/**
 * Get all subcategories (for filter display) - PAGINATED to 8 per page
 */
public function getAllSubcategories(Request $request)
{
    try {
        $perPage = min($request->get('per_page', 8), 20); // Default 8, max 20
        
        $subcategories = Category::where('is_active', true)
            ->whereNotNull('parent_id')
            ->with(['parent']) // Eager load parent
            ->withCount(['products' => function($q) {
                $q->fromPaidSellers()
                  ->where('is_active', true)->where('quantity', '>', 0);
            }])
            ->orderBy('name', 'asc')
            ->paginate($perPage); // <- Changed from get() to paginate()

        $formattedSubcategories = $subcategories->map(function ($subcategory) {
            $image = $subcategory->image;
            if (!$image && $subcategory->parent) {
                $image = $subcategory->parent->image;
            }
            
            return [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'slug' => $subcategory->slug,
                'description' => $subcategory->description,
                'image' => $image ? $this->getImageUrl($image) : null,
                'parent_id' => $subcategory->parent_id,
                'parent_name' => $subcategory->parent ? $subcategory->parent->name : null,
                'products_count' => $subcategory->products_count,
                'created_at' => $subcategory->created_at ? $subcategory->created_at->toISOString() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedSubcategories,
            'pagination' => [
                'current_page' => $subcategories->currentPage(),
                'last_page' => $subcategories->lastPage(),
                'per_page' => $subcategories->perPage(),
                'total' => $subcategories->total(),
            ],
            'message' => 'Subcategories retrieved successfully'
        ]);
    } catch (\Exception $e) {
        Log::error('Error in getAllSubcategories: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 8,
                'total' => 0,
            ],
            'message' => 'Error fetching subcategories'
        ], 500);
    }
}

    /**
     * Get single product details (no pagination needed)
     */
    public function getProductDetails($id)
    {
        try {
            $product = Product::fromPaidSellers()
                ->with(['category', 'seller'])
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->findOrFail($id);

            $product->increment('views_count');

            $formattedProduct = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'price' => (float) $product->price,
                'quantity' => (int) $product->quantity,
                'min_stock_alert' => (int) $product->min_stock_alert,
                'image' => $this->getImageUrl($product->image),
                'images' => $product->images ? json_decode($product->images, true) : [],
                'is_featured' => (bool) $product->is_featured,
                'is_active' => (bool) $product->is_active,
                'discount_percentage' => (int) $product->discount_percentage,
                'discounted_price' => (float) $product->discounted_price,
                'views_count' => (int) $product->views_count,
                'sales_count' => (int) $product->sales_count,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
                'seller' => $product->seller ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->name,
                    'email' => $product->seller->email,
                ] : null,
                'created_at' => $product->created_at ? $product->created_at->toISOString() : null,
                'updated_at' => $product->updated_at ? $product->updated_at->toISOString() : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedProduct,
                'message' => 'Product details retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getProductDetails: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Get products by category (paginated)
     */
    public function getProductsByCategory($categoryId, Request $request)
    {
        try {
            $category = Category::findOrFail($categoryId);
            
            $query = Product::fromPaidSellers()
                ->with(['category', 'seller'])
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->where('category_id', $categoryId);

            $perPage = min($request->get('per_page', 12), 100);
            $products = $query->paginate($perPage);

            $formattedProducts = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'price' => (float) $product->price,
                    'quantity' => (int) $product->quantity,
                    'image' => $this->getImageUrl($product->image),
                    'images' => $product->images ? json_decode($product->images, true) : [],
                    'is_featured' => (bool) $product->is_featured,
                    'discount_percentage' => (int) $product->discount_percentage,
                    'discounted_price' => (float) $product->discounted_price,
                    'views_count' => (int) $product->views_count,
                    'sales_count' => (int) $product->sales_count,
                    'created_at' => $product->created_at ? $product->created_at->toISOString() : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedProducts,
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'image' => $category->image ? $this->getImageUrl($category->image) : null,
                ],
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'message' => 'Products by category retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getProductsByCategory: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Category not found'
            ], 404);
        }
    }

    /**
     * Get homepage statistics (no pagination - aggregate only)
     */
    public function getHomepageStats()
    {
        try {
            $stats = Cache::remember('homepage_stats', 3600, function() {
                $totalProducts = Product::fromPaidSellers()
                    ->where('is_active', true)
                    ->where('quantity', '>', 0)
                    ->count();
                
                $totalCategories = Category::where('is_active', true)
                    ->whereNull('parent_id')
                    ->count();
                
                $totalSellers = User::role('seller')->count();
                
                $totalProductsSold = Product::sum('sales_count');

                return [
                    'total_products' => $totalProducts,
                    'total_categories' => $totalCategories,
                    'total_sellers' => $totalSellers,
                    'total_products_sold' => $totalProductsSold,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getHomepageStats: ' . $e->getMessage());
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_products' => 0,
                    'total_categories' => 0,
                    'total_sellers' => 0,
                    'total_products_sold' => 0,
                ],
                'message' => 'Statistics retrieved successfully'
            ]);
        }
    }
}