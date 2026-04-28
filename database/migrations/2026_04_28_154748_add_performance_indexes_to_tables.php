<?php
// database/migrations/2026_04_28_000000_add_performance_indexes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ==================== PRODUCTS TABLE ====================
        Schema::table('products', function (Blueprint $table) {
            // 1. Composite index for active products with stock (most critical for landing page)
            if (!Schema::hasIndex('products', 'products_is_active_quantity_index')) {
                $table->index(['is_active', 'quantity'], 'products_is_active_quantity_index');
            }
            
            // 2. Composite index for featured products (for /landing/products/featured)
            if (!Schema::hasIndex('products', 'products_featured_active_quantity_index')) {
                $table->index(['is_featured', 'is_active', 'quantity'], 'products_featured_active_quantity_index');
            }
            
            // 3. Composite index for category filtering with stock
            if (!Schema::hasIndex('products', 'products_category_active_quantity_index')) {
                $table->index(['category_id', 'is_active', 'quantity'], 'products_category_active_quantity_index');
            }
            
            // 4. Composite index for seller products
            if (!Schema::hasIndex('products', 'products_seller_active_index')) {
                $table->index(['seller_id', 'is_active'], 'products_seller_active_index');
            }
            
            // 5. Index for sorting by sales/popularity
            if (!Schema::hasIndex('products', 'products_sales_count_index')) {
                $table->index('sales_count', 'products_sales_count_index');
            }
            
            // 6. Index for sorting by views
            if (!Schema::hasIndex('products', 'products_views_count_index')) {
                $table->index('views_count', 'products_views_count_index');
            }
            
            // 7. Composite index for discounted products
            if (!Schema::hasIndex('products', 'products_discount_active_index')) {
                $table->index(['discount_percentage', 'is_active'], 'products_discount_active_index');
            }
        });
        
        // Add FULLTEXT index for search (MySQL specific)
        try {
            $existingFulltext = DB::select("SHOW INDEX FROM products WHERE Key_name = 'products_search_fulltext'");
            if (empty($existingFulltext)) {
                DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_search_fulltext (name, description)');
            }
        } catch (\Exception $e) {
            // Skip if FULLTEXT not supported (e.g., some MySQL versions)
        }

        // ==================== CATEGORIES TABLE ====================
        Schema::table('categories', function (Blueprint $table) {
            // For subcategories queries (parent_id + active)
            if (!Schema::hasIndex('categories', 'categories_parent_active_index')) {
                $table->index(['parent_id', 'is_active'], 'categories_parent_active_index');
            }
            
            // For counting products per category
            if (!Schema::hasIndex('categories', 'categories_slug_index')) {
                $table->index('slug', 'categories_slug_index');
            }
        });

        // ==================== USERS TABLE ====================
        Schema::table('users', function (Blueprint $table) {
            // For role-based queries (if using spatie/laravel-permission)
            if (!Schema::hasIndex('users', 'users_role_index') && Schema::hasColumn('users', 'role')) {
                $table->index('role', 'users_role_index');
            }
            
            // Composite index for active users
            if (!Schema::hasIndex('users', 'users_active_verified_index')) {
                $table->index(['is_active', 'email_verified_at'], 'users_active_verified_index');
            }
        });

        // ==================== NOTIFICATIONS TABLE ====================
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                // For user notifications queries (your 4-second slow query)
                if (!Schema::hasIndex('notifications', 'notifications_notifiable_index')) {
                    $table->index(['notifiable_id', 'notifiable_type'], 'notifications_notifiable_index');
                }
                
                // For unread notifications filtering
                if (!Schema::hasIndex('notifications', 'notifications_read_at_index')) {
                    $table->index('read_at', 'notifications_read_at_index');
                }
                
                // For sorting by date
                if (!Schema::hasIndex('notifications', 'notifications_created_at_index')) {
                    $table->index('created_at', 'notifications_created_at_index');
                }
                
                // Composite index for common query pattern
                if (!Schema::hasIndex('notifications', 'notifications_notifiable_read_index')) {
                    $table->index(['notifiable_id', 'notifiable_type', 'read_at'], 'notifications_notifiable_read_index');
                }
            });
        }

        // ==================== ORDERS TABLE ====================
        Schema::table('orders', function (Blueprint $table) {
            // Composite indexes for order filtering by user and status
            if (!Schema::hasIndex('orders', 'orders_buyer_status_created_index')) {
                $table->index(['buyer_id', 'status', 'created_at'], 'orders_buyer_status_created_index');
            }
            
            if (!Schema::hasIndex('orders', 'orders_seller_status_created_index')) {
                $table->index(['seller_id', 'status', 'created_at'], 'orders_seller_status_created_index');
            }
        });

        // ==================== CART TABLE ====================
        Schema::table('carts', function (Blueprint $table) {
            // Composite index for cart queries
            if (!Schema::hasIndex('carts', 'carts_buyer_product_index')) {
                $table->index(['buyer_id', 'product_id'], 'carts_buyer_product_index');
            }
        });

        // ==================== PRODUCT_COMMENTS TABLE ====================
        if (Schema::hasTable('product_comments')) {
            Schema::table('product_comments', function (Blueprint $table) {
                // For loading comments with products
                if (!Schema::hasIndex('product_comments', 'product_comments_product_created_index')) {
                    $table->index(['product_id', 'created_at'], 'product_comments_product_created_index');
                }
            });
        }
    }

    public function down(): void
    {
        // ==================== PRODUCTS TABLE ====================
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasIndex('products', 'products_is_active_quantity_index')) {
                $table->dropIndex('products_is_active_quantity_index');
            }
            if (Schema::hasIndex('products', 'products_featured_active_quantity_index')) {
                $table->dropIndex('products_featured_active_quantity_index');
            }
            if (Schema::hasIndex('products', 'products_category_active_quantity_index')) {
                $table->dropIndex('products_category_active_quantity_index');
            }
            if (Schema::hasIndex('products', 'products_seller_active_index')) {
                $table->dropIndex('products_seller_active_index');
            }
            if (Schema::hasIndex('products', 'products_sales_count_index')) {
                $table->dropIndex('products_sales_count_index');
            }
            if (Schema::hasIndex('products', 'products_views_count_index')) {
                $table->dropIndex('products_views_count_index');
            }
            if (Schema::hasIndex('products', 'products_discount_active_index')) {
                $table->dropIndex('products_discount_active_index');
            }
        });
        
        // Drop FULLTEXT index
        try {
            $existingFulltext = DB::select("SHOW INDEX FROM products WHERE Key_name = 'products_search_fulltext'");
            if (!empty($existingFulltext)) {
                DB::statement('ALTER TABLE products DROP INDEX products_search_fulltext');
            }
        } catch (\Exception $e) {
            // Skip if index doesn't exist
        }

        // ==================== CATEGORIES TABLE ====================
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasIndex('categories', 'categories_parent_active_index')) {
                $table->dropIndex('categories_parent_active_index');
            }
            if (Schema::hasIndex('categories', 'categories_slug_index')) {
                $table->dropIndex('categories_slug_index');
            }
        });

        // ==================== USERS TABLE ====================
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasIndex('users', 'users_role_index')) {
                $table->dropIndex('users_role_index');
            }
            if (Schema::hasIndex('users', 'users_active_verified_index')) {
                $table->dropIndex('users_active_verified_index');
            }
        });

        // ==================== NOTIFICATIONS TABLE ====================
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasIndex('notifications', 'notifications_notifiable_index')) {
                    $table->dropIndex('notifications_notifiable_index');
                }
                if (Schema::hasIndex('notifications', 'notifications_read_at_index')) {
                    $table->dropIndex('notifications_read_at_index');
                }
                if (Schema::hasIndex('notifications', 'notifications_created_at_index')) {
                    $table->dropIndex('notifications_created_at_index');
                }
                if (Schema::hasIndex('notifications', 'notifications_notifiable_read_index')) {
                    $table->dropIndex('notifications_notifiable_read_index');
                }
            });
        }

        // ==================== ORDERS TABLE ====================
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasIndex('orders', 'orders_buyer_status_created_index')) {
                $table->dropIndex('orders_buyer_status_created_index');
            }
            if (Schema::hasIndex('orders', 'orders_seller_status_created_index')) {
                $table->dropIndex('orders_seller_status_created_index');
            }
        });

        // ==================== CART TABLE ====================
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasIndex('carts', 'carts_buyer_product_index')) {
                $table->dropIndex('carts_buyer_product_index');
            }
        });

        // ==================== PRODUCT_COMMENTS TABLE ====================
        if (Schema::hasTable('product_comments')) {
            Schema::table('product_comments', function (Blueprint $table) {
                if (Schema::hasIndex('product_comments', 'product_comments_product_created_index')) {
                    $table->dropIndex('product_comments_product_created_index');
                }
            });
        }
    }
};