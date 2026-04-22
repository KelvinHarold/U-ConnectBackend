// database/migrations/2024_01_01_000003_create_products_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(0); // Inventory stock
            $table->integer('min_stock_alert')->default(5); // Low stock threshold
            $table->string('image')->nullable();
            $table->json('images')->nullable(); // Multiple images
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('views_count')->default(0);
            $table->integer('sales_count')->default(0);
            $table->timestamps();
            
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            
            $table->index(['seller_id', 'is_active']);
            $table->index('quantity');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};