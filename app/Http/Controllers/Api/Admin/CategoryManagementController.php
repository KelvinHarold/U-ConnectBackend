<?php
// app/Http/Controllers/Api/Admin/CategoryManagementController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Helpers\CategoryNotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CategoryManagementController extends Controller
{
    protected $imageService;

    public function __construct(\App\Services\ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function index()
    {
        $categories = Category::with('parent')
            ->withCount('products')
            ->latest()
            ->get();

        // Transform the response to include full image URLs (matching ProductController style)
        $categories->transform(function ($category) {
            if ($category->image) {
                // Check if it's already a full URL
                if (!filter_var($category->image, FILTER_VALIDATE_URL)) {
                    $category->image = url($category->image);
                }
            }
            return $category;
        });

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $validated['slug'] = Str::slug($validated['name']);

        // Handle image upload
        if ($request->hasFile('image')) {
            $validated['image'] = $this->imageService->compressAndSave($request->file('image'), 'categories');
        }

        $category = Category::create($validated);

        // ========== SEND NOTIFICATION TO ALL SELLERS ==========
        // Notify all sellers about the new category
        CategoryNotificationHelper::sendNewCategoryNotificationToSellers($category);

        // Add full image URL to response
        if ($category->image) {
            if (!filter_var($category->image, FILTER_VALIDATE_URL)) {
                $category->image = url($category->image);
            }
        }

        return response()->json([
            'message' => 'Category created successfully. Sellers have been notified.',
            'category' => $category
        ], 201);
    }

    public function show($id)
    {
        $category = Category::with(['parent', 'children', 'products'])
            ->withCount('products')
            ->findOrFail($id);

        // Add full image URL
        if ($category->image) {
            if (!filter_var($category->image, FILTER_VALIDATE_URL)) {
                $category->image = url($category->image);
            }
        }

        // Add products count and image URLs for children
        if ($category->children) {
            foreach ($category->children as $child) {
                $child->products_count = $child->products()->count();
                if ($child->image) {
                    if (!filter_var($child->image, FILTER_VALIDATE_URL)) {
                        $child->image = url($child->image);
                    }
                }
            }
        }

        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        // Store old name for notification
        $oldName = $category->name;

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $id,
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'sometimes|boolean',
            'remove_image' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Handle image removal
        if (
            $request->input('remove_image') === true ||
            $request->input('remove_image') === 'true' ||
            $request->input('remove_image') === '1'
        ) {
            if ($category->image) {
                $this->imageService->delete($category->image);
                $category->image = null;
            }
        }

        // Handle image upload for update
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($category->image) {
                $this->imageService->delete($category->image);
            }

            // Upload new image
            $validated['image'] = $this->imageService->compressAndSave($request->file('image'), 'categories');
        }

        // Update other fields
        $category->fill($validated);
        $category->save();

        // ========== SEND NOTIFICATION TO SELLERS ABOUT UPDATE ==========
        // Only send if significant changes were made
        if (isset($validated['name']) || isset($validated['description']) || isset($validated['parent_id'])) {
            CategoryNotificationHelper::sendCategoryUpdatedNotificationToSellers($category, $oldName);
        }

        // Add full image URL to response
        if ($category->image) {
            if (!filter_var($category->image, FILTER_VALIDATE_URL)) {
                $category->image = url($category->image);
            }
        }

        return response()->json([
            'message' => 'Category updated successfully. Sellers have been notified of changes.',
            'category' => $category
        ]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        
        // Store category name and affected products count before deletion
        $categoryName = $category->name;
        
        // Get count of products in this category (for sellers who will be affected)
        $affectedProductsCount = $category->products()->count();

        // Check if category has products
        if ($category->products()->count() > 0) {
            // Move products to parent category or uncategorized
            $category->products()->update(['category_id' => null]);
        }

        // Check if category has child categories
        if ($category->children()->count() > 0) {
            // Option 1: Move children to parent
            if ($category->parent_id) {
                $category->children()->update(['parent_id' => $category->parent_id]);
            } else {
                // Option 2: Make children top-level
                $category->children()->update(['parent_id' => null]);
            }
        }

        // Delete category image if exists
        if ($category->image) {
            $this->imageService->delete($category->image);
        }

        $category->delete();

        // ========== SEND NOTIFICATION TO SELLERS ABOUT DELETION ==========
        CategoryNotificationHelper::sendCategoryDeletedNotificationToSellers($categoryName, $affectedProductsCount);

        return response()->json([
            'message' => 'Category deleted successfully. Sellers have been notified.',
            'affected_products_count' => $affectedProductsCount
        ]);
    }

    public function tree()
    {
        $categories = Category::with(['children' => function ($query) {
            $query->withCount('products');
        }])
            ->withCount('products')
            ->whereNull('parent_id')
            ->get();

        // Add full image URLs recursively
        $this->addImageUrlsRecursively($categories);

        return response()->json($categories);
    }

    // Helper method to add image URLs recursively
    private function addImageUrlsRecursively($categories)
    {
        foreach ($categories as $category) {
            if ($category->image) {
                if (!filter_var($category->image, FILTER_VALIDATE_URL)) {
                    $category->image = url($category->image);
                }
            }
            if ($category->children && $category->children->count() > 0) {
                $this->addImageUrlsRecursively($category->children);
            }
        }
    }
}