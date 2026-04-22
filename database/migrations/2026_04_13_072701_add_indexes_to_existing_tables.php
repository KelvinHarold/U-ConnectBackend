<?php
// database/migrations/2026_04_13_072701_add_indexes_to_existing_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasIndex('users', 'users_is_active_index')) {
                $table->index('is_active');
            }
            if (!Schema::hasIndex('users', 'users_created_at_index')) {
                $table->index('created_at');
            }
            if (!Schema::hasIndex('users', 'users_university_id_index')) {
                $table->index('university_id');
            }
        });

        // Categories table
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasIndex('categories', 'categories_parent_id_index')) {
                $table->index('parent_id');
            }
            if (!Schema::hasIndex('categories', 'categories_is_active_index')) {
                $table->index('is_active');
            }
        });

        // Products table - only add indexes that don't exist
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasIndex('products', 'products_category_id_index')) {
                $table->index('category_id');
            }
            if (!Schema::hasIndex('products', 'products_price_index')) {
                $table->index('price');
            }
            if (!Schema::hasIndex('products', 'products_created_at_index')) {
                $table->index('created_at');
            }
            // Check if composite index exists before adding
            $existingIndexes = DB::select('SHOW INDEX FROM products WHERE Key_name = ?', ['products_category_id_is_active_index']);
            if (empty($existingIndexes)) {
                $table->index(['category_id', 'is_active']);
            }
        });

        // Orders table
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasIndex('orders', 'orders_status_index')) {
                $table->index('status');
            }
            if (!Schema::hasIndex('orders', 'orders_created_at_index')) {
                $table->index('created_at');
            }
            // Check composite indexes
            $buyerStatusIndex = DB::select('SHOW INDEX FROM orders WHERE Key_name = ?', ['orders_buyer_id_status_index']);
            if (empty($buyerStatusIndex)) {
                $table->index(['buyer_id', 'status']);
            }
            
            $sellerStatusIndex = DB::select('SHOW INDEX FROM orders WHERE Key_name = ?', ['orders_seller_id_status_index']);
            if (empty($sellerStatusIndex)) {
                $table->index(['seller_id', 'status']);
            }
        });

        // Order items table
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasIndex('order_items', 'order_items_order_id_index')) {
                $table->index('order_id');
            }
            if (!Schema::hasIndex('order_items', 'order_items_product_id_index')) {
                $table->index('product_id');
            }
        });

        // Carts table
        Schema::table('carts', function (Blueprint $table) {
            if (!Schema::hasIndex('carts', 'carts_buyer_id_index')) {
                $table->index('buyer_id');
            }
            if (!Schema::hasIndex('carts', 'carts_product_id_index')) {
                $table->index('product_id');
            }
        });

        // Inventory logs table
        Schema::table('inventory_logs', function (Blueprint $table) {
            if (!Schema::hasIndex('inventory_logs', 'inventory_logs_product_id_index')) {
                $table->index('product_id');
            }
            $productCreatedIndex = DB::select('SHOW INDEX FROM inventory_logs WHERE Key_name = ?', ['inventory_logs_product_id_created_at_index']);
            if (empty($productCreatedIndex)) {
                $table->index(['product_id', 'created_at']);
            }
        });

        // Product comments table
        Schema::table('product_comments', function (Blueprint $table) {
            if (!Schema::hasIndex('product_comments', 'product_comments_product_id_index')) {
                $table->index('product_id');
            }
            $productCreatedIndex = DB::select('SHOW INDEX FROM product_comments WHERE Key_name = ?', ['product_comments_product_id_created_at_index']);
            if (empty($productCreatedIndex)) {
                $table->index(['product_id', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        // Drop indexes if they exist
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasIndex('users', 'users_is_active_index')) {
                $table->dropIndex('users_is_active_index');
            }
            if (Schema::hasIndex('users', 'users_created_at_index')) {
                $table->dropIndex('users_created_at_index');
            }
            if (Schema::hasIndex('users', 'users_university_id_index')) {
                $table->dropIndex('users_university_id_index');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasIndex('categories', 'categories_parent_id_index')) {
                $table->dropIndex('categories_parent_id_index');
            }
            if (Schema::hasIndex('categories', 'categories_is_active_index')) {
                $table->dropIndex('categories_is_active_index');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasIndex('products', 'products_category_id_index')) {
                $table->dropIndex('products_category_id_index');
            }
            if (Schema::hasIndex('products', 'products_price_index')) {
                $table->dropIndex('products_price_index');
            }
            if (Schema::hasIndex('products', 'products_created_at_index')) {
                $table->dropIndex('products_created_at_index');
            }
            if (Schema::hasIndex('products', 'products_category_id_is_active_index')) {
                $table->dropIndex('products_category_id_is_active_index');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasIndex('orders', 'orders_status_index')) {
                $table->dropIndex('orders_status_index');
            }
            if (Schema::hasIndex('orders', 'orders_created_at_index')) {
                $table->dropIndex('orders_created_at_index');
            }
            if (Schema::hasIndex('orders', 'orders_buyer_id_status_index')) {
                $table->dropIndex('orders_buyer_id_status_index');
            }
            if (Schema::hasIndex('orders', 'orders_seller_id_status_index')) {
                $table->dropIndex('orders_seller_id_status_index');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasIndex('order_items', 'order_items_order_id_index')) {
                $table->dropIndex('order_items_order_id_index');
            }
            if (Schema::hasIndex('order_items', 'order_items_product_id_index')) {
                $table->dropIndex('order_items_product_id_index');
            }
        });

        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasIndex('carts', 'carts_buyer_id_index')) {
                $table->dropIndex('carts_buyer_id_index');
            }
            if (Schema::hasIndex('carts', 'carts_product_id_index')) {
                $table->dropIndex('carts_product_id_index');
            }
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            if (Schema::hasIndex('inventory_logs', 'inventory_logs_product_id_index')) {
                $table->dropIndex('inventory_logs_product_id_index');
            }
            if (Schema::hasIndex('inventory_logs', 'inventory_logs_product_id_created_at_index')) {
                $table->dropIndex('inventory_logs_product_id_created_at_index');
            }
        });

        Schema::table('product_comments', function (Blueprint $table) {
            if (Schema::hasIndex('product_comments', 'product_comments_product_id_index')) {
                $table->dropIndex('product_comments_product_id_index');
            }
            if (Schema::hasIndex('product_comments', 'product_comments_product_id_created_at_index')) {
                $table->dropIndex('product_comments_product_id_created_at_index');
            }
        });
    }
};