<?php

namespace App\Helpers;

use App\Models\Category;
use App\Services\NotificationService;

class CategoryNotificationHelper
{
    /**
     * Send notification to sellers when a new category is created
     */
    public static function sendNewCategoryNotificationToSellers($category)
    {
        $notificationService = app(NotificationService::class);
        
        // Send to all sellers using your existing sendToSellers method
        return $notificationService->sendToSellers([
            'type' => 'category_created',
            'title' => 'New Category Added! 📁',
            'body' => "A new category has been added to the marketplace!\n\n" .
                      "Category: {$category->name}\n" .
                      "Description: " . ($category->description ?? 'No description provided') . "\n\n" .
                      "Start listing your products in this new category to reach more customers!",
            'data' => [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'category_slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'created_at' => $category->created_at->toDateTimeString(),
            ],
        ]);
    }
    
    /**
     * Send notification to sellers when a category is updated
     */
    public static function sendCategoryUpdatedNotificationToSellers($category, $oldName = null)
    {
        $notificationService = app(NotificationService::class);
        
        $title = $oldName ? "Category Renamed: {$oldName} → {$category->name} ✏️" : "Category Updated: {$category->name} ✏️";
        $body = $oldName 
            ? "A category has been renamed from '{$oldName}' to '{$category->name}'. Please update your product listings accordingly."
            : "The category '{$category->name}' has been updated. Review the changes to ensure your products are properly categorized.";
        
        return $notificationService->sendToSellers([
            'type' => 'category_updated',
            'title' => $title,
            'body' => $body,
            'data' => [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'old_name' => $oldName,
                'updated_at' => now()->toDateTimeString(),
            ],
        ]);
    }
    
    /**
     * Send notification to sellers when a category is deleted
     */
    public static function sendCategoryDeletedNotificationToSellers($categoryName, $affectedProductsCount = 0)
    {
        $notificationService = app(NotificationService::class);
        
        $body = "The category '{$categoryName}' has been removed from the marketplace.\n\n";
        
        if ($affectedProductsCount > 0) {
            $body .= "⚠️ {$affectedProductsCount} of your products were in this category and have been moved to uncategorized.\n";
            $body .= "Please review and reassign these products to appropriate categories.";
        } else {
            $body .= "No products were affected by this change.";
        }
        
        return $notificationService->sendToSellers([
            'type' => 'category_deleted',
            'title' => 'Category Removed: ' . $categoryName . ' 🗑️',
            'body' => $body,
            'data' => [
                'category_name' => $categoryName,
                'affected_products_count' => $affectedProductsCount,
                'deleted_at' => now()->toDateTimeString(),
            ],
        ]);
    }
    
    /**
     * Send notification to specific sellers who have products in a category when it's updated
     */
    public static function sendCategoryUpdateToAffectedSellers($category, $affectedSellers)
    {
        $notificationService = app(NotificationService::class);
        
        foreach ($affectedSellers as $seller) {
            $notificationService->sendToUser($seller, [
                'type' => 'category_affected',
                'title' => 'Category You Use Has Been Updated 📢',
                'body' => "The category '{$category->name}' which you have products in has been updated.\n\n" .
                          "Please review your products to ensure they are still correctly categorized.",
                'data' => [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'updated_at' => now()->toDateTimeString(),
                ],
            ]);
        }
    }
}