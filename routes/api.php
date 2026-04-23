
<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\CategoryManagementController;
use App\Http\Controllers\Api\Admin\OrderManagementController;
use App\Http\Controllers\Api\Admin\ProductManagementController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Buyer\BuyerDashboardController;
use App\Http\Controllers\Api\Buyer\CartController;
use App\Http\Controllers\Api\Buyer\OrderController as BuyerOrderController;
use App\Http\Controllers\Api\Buyer\ShopController;

use App\Http\Controllers\Api\Common\LandingPageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProductCommentController;
use App\Http\Controllers\Api\Seller\InventoryController;
use App\Http\Controllers\Api\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Api\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Api\Seller\SellerCategoryController;
use App\Http\Controllers\Api\Seller\SellerDashboardController;
use App\Http\Controllers\Api\Common\IssueReportController;

use Illuminate\Support\Facades\Route;



// ========== PUBLIC LANDING PAGE ROUTES (NO AUTH REQUIRED) ==========

Route::prefix('landing')->group(function () {
    Route::get('/products', [LandingPageController::class, 'getProducts']);
    Route::get('/products/featured', [LandingPageController::class, 'getFeaturedProducts']);
    Route::get('/products/trending', [App\Http\Controllers\Api\Common\LandingPageController::class, 'getTrendingProducts']);
    Route::get('/products/new-arrivals', [LandingPageController::class, 'getNewArrivals']);
    Route::get('/products/{id}', [LandingPageController::class, 'getProductDetails']);

    // Category routes
    Route::get('/categories', [App\Http\Controllers\Api\Common\LandingPageController::class, 'getCategories']);
    Route::get('/categories/{id}/products', [App\Http\Controllers\Api\Common\LandingPageController::class, 'getProductsByCategory']);
    Route::get('/subcategories', [App\Http\Controllers\Api\Common\LandingPageController::class, 'getAllSubcategories']);

    // Statistics
    Route::get('/stats', [App\Http\Controllers\Api\Common\LandingPageController::class, 'getHomepageStats']);
});


//Password reset routes
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
Route::post('/check-reset-token', [PasswordResetController::class, 'checkToken']);




// Public routes (AUTH) with Rate Limiting
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');

// Public: read comments for any product (no login needed)
Route::get('/products/{id}/comments', [ProductCommentController::class, 'index']);


// ========== PROTECTED ROUTES USING SANCTUM ==========
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/products/{id}/view', [App\Http\Controllers\Api\Seller\ProductController::class, 'incrementView']);


    // Announcement routes
    Route::apiResource('announcements', AnnouncementController::class);
    Route::post('announcements/bulk-update-status', [AnnouncementController::class, 'bulkUpdateStatus']);

    // Public announcement endpoint for all authenticated users
    Route::get('published-announcements', [AnnouncementController::class, 'getPublishedAnnouncements']);

    // Comments: any authenticated user can post/delete their own
    Route::post('/products/{id}/comments', [ProductCommentController::class, 'store']);
    Route::delete('/products/{id}/comments/{commentId}', [ProductCommentController::class, 'destroy']);

    // Profile routes
    Route::get('/profile', [App\Http\Controllers\Api\ProfileController::class, 'show']);
    Route::put('/profile', [App\Http\Controllers\Api\ProfileController::class, 'update']);
    Route::post('/profile/change-password', [App\Http\Controllers\Api\ProfileController::class, 'changePassword']);

    // ADMIN ROUTES
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        // User Management
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/users/{id}', [UserManagementController::class, 'show']);
        Route::put('/users/{id}', [UserManagementController::class, 'update']);
        Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
        Route::patch('/users/{id}/toggle-status', [UserManagementController::class, 'toggleStatus']);
        Route::post('/users/{id}/reset-password', [UserManagementController::class, 'resetPassword']);

        // Product Management
        Route::get('/products', [ProductManagementController::class, 'index']);
        Route::get('/products/{id}', [ProductManagementController::class, 'show']);
        Route::put('/products/{id}', [ProductManagementController::class, 'update']);
        Route::delete('/products/{id}', [ProductManagementController::class, 'destroy']);
        Route::get('/products/featured/list', [ProductManagementController::class, 'featured']);

        // Order Management
        Route::get('/orders', [OrderManagementController::class, 'index']);
        Route::get('/orders/{id}', [OrderManagementController::class, 'show']);
        Route::put('/orders/{id}', [OrderManagementController::class, 'update']);
        Route::delete('/orders/{id}', [OrderManagementController::class, 'destroy']);
        Route::patch('/orders/{id}/status', [OrderManagementController::class, 'updateStatus']);
        Route::get('/orders/statistics/summary', [OrderManagementController::class, 'statistics']);

        // Category Management
        Route::get('/categories/tree', [CategoryManagementController::class, 'tree']);
        Route::get('/categories', [CategoryManagementController::class, 'index']);
        Route::post('/categories', [CategoryManagementController::class, 'store']);
        Route::get('/categories/{id}', [CategoryManagementController::class, 'show']);
        Route::put('/categories/{id}', [CategoryManagementController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryManagementController::class, 'destroy']);



        // Reports routes
        Route::get('/reports/sales', [ReportController::class, 'salesReport']);
        Route::get('/reports/sellers', [ReportController::class, 'sellerPerformance']);
        Route::get('/reports/products', [ReportController::class, 'productPerformance']);
        Route::get('/reports/users', [ReportController::class, 'userActivity']);

        // Issue Reports Management
        Route::get('/issue-reports', [IssueReportController::class, 'adminIndex']);
        Route::get('/issue-reports/{id}', [IssueReportController::class, 'show']);
        Route::patch('/issue-reports/{id}/resolve', [IssueReportController::class, 'adminResolve']);

        // Subscription Management
        Route::get('/subscriptions/seller-status', [\App\Http\Controllers\Api\Admin\SubscriptionManagementController::class, 'sellerStatus']);
        Route::get('/subscriptions', [\App\Http\Controllers\Api\Admin\SubscriptionManagementController::class, 'index']);
        Route::post('/subscriptions/{id}/approve', [\App\Http\Controllers\Api\Admin\SubscriptionManagementController::class, 'approve']);
        Route::post('/subscriptions/{id}/reject', [\App\Http\Controllers\Api\Admin\SubscriptionManagementController::class, 'reject']);

        Route::get('/subscription-settings', [\App\Http\Controllers\Api\Admin\SubscriptionSettingController::class, 'getSettings']);
        Route::put('/subscription-settings', [\App\Http\Controllers\Api\Admin\SubscriptionSettingController::class, 'updateSettings']);
    });

    // Seller routes
    Route::middleware('role:seller')->prefix('seller')->group(function () {
        Route::get('/dashboard', [SellerDashboardController::class, 'index']);

        Route::apiResource('products', SellerProductController::class);
        Route::patch('/products/{id}/stock', [SellerProductController::class, 'updateStock']);
        Route::get('/products/categories/list', [SellerProductController::class, 'categories']);

        Route::get('/categories/parent', [SellerCategoryController::class, 'parentCategories']);
        Route::get('/categories/{id}/subcategories', [SellerCategoryController::class, 'subcategories']);
        Route::get('/categories/{id}/products', [SellerCategoryController::class, 'categoryProducts']);

        Route::apiResource('orders', SellerOrderController::class);
        Route::patch('/orders/{id}/status', [SellerOrderController::class, 'updateStatus']);
        Route::post('/orders/{id}/whatsapp', [SellerOrderController::class, 'sendWhatsAppNotification']);
        Route::get('/orders/statistics/summary', [SellerOrderController::class, 'statistics']);

        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStockProducts']);
        Route::get('/inventory/out-of-stock', [InventoryController::class, 'outOfStockProducts']);
        Route::get('/inventory/logs', [InventoryController::class, 'inventoryLogs']);
        Route::post('/inventory/bulk-update', [InventoryController::class, 'bulkStockUpdate']);
        Route::get('/inventory/summary', [InventoryController::class, 'inventorySummary']);

        Route::get('/parent', [SellerCategoryController::class, 'parentCategories']);
        Route::get('/{parentId}/subcategories', [SellerCategoryController::class, 'subcategories']);
        Route::get('/{categoryId}/products', [SellerCategoryController::class, 'categoryProducts']);
        Route::get('/export/pdf', [SellerCategoryController::class, 'exportCategoriesPDF']);
        Route::get('/export/csv', [SellerCategoryController::class, 'exportCategoriesCSV']);

        // Reports
        Route::get('/reports', [IssueReportController::class, 'index']);
        Route::post('/reports', [IssueReportController::class, 'store']);
        Route::get('/reports/{id}', [IssueReportController::class, 'show']);

        // Subscription
        Route::get('/subscription', [\App\Http\Controllers\Api\Seller\SubscriptionController::class, 'index']);
        Route::post('/subscription', [\App\Http\Controllers\Api\Seller\SubscriptionController::class, 'store']);
    });

    // Buyer routes
    Route::middleware('role:buyer')->prefix('buyer')->group(function () {
        Route::get('/dashboard', [BuyerDashboardController::class, 'index']);

        Route::get('/shop/products', [ShopController::class, 'products']);
        Route::get('/shop/products/{id}', [ShopController::class, 'productDetails']);
        Route::get('/shop/sellers', [ShopController::class, 'sellers']);
        Route::get('/shop/sellers/{id}/products', [ShopController::class, 'sellerProducts']);
        Route::get('/shop/categories', [ShopController::class, 'categories']);
        Route::get('/shop/featured', [ShopController::class, 'featuredProducts']);
        Route::get('/shop/categories/{id}/products', [ShopController::class, 'categoryProducts']);
        Route::get('/shop/categories/parent', [ShopController::class, 'parentCategories']);
        Route::get('/shop/categories/{id}/subcategories', [ShopController::class, 'subcategories']);
        Route::get('/shop/categories/{id}/products', [ShopController::class, 'categoryProducts']);
        Route::get('/shop/categories/stats', [ShopController::class, 'categoryStats']);
        Route::get('/categories/tree', [CategoryManagementController::class, 'tree']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/add', [CartController::class, 'add']);
        Route::put('/cart/{id}', [CartController::class, 'update']);
        Route::delete('/cart/{id}', [CartController::class, 'remove']);
        Route::delete('/cart/clear/all', [CartController::class, 'clear']);
        Route::post('/cart/checkout', [CartController::class, 'checkout']);

        Route::get('/orders', [BuyerOrderController::class, 'index']);
        Route::get('/orders/{id}', [BuyerOrderController::class, 'show']);
        Route::post('/orders/{id}/cancel', [BuyerOrderController::class, 'cancel']);
        Route::post('/orders/{id}/confirm-delivery', [BuyerOrderController::class, 'confirmDelivery']);
        Route::get('/orders/{id}/track', [BuyerOrderController::class, 'track']);

        Route::post('/sellers/{id}/rate', [\App\Http\Controllers\Api\Buyer\SellerRatingController::class, 'store']);

        // Reports
        Route::get('/reports', [IssueReportController::class, 'index']);
        Route::post('/reports', [IssueReportController::class, 'store']);
        Route::get('/reports/{id}', [IssueReportController::class, 'show']);
    });

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread/count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read/all', [NotificationController::class, 'markAllAsRead']);

    // Add these new routes
    Route::delete('/notifications', [NotificationController::class, 'destroyAll']); // Delete all

});
