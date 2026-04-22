// database/migrations/2024_01_01_000007_create_inventory_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id')->nullable(); // Who made the change
            $table->enum('type', ['add', 'remove', 'order', 'cancellation', 'adjustment']);
            $table->integer('quantity_change');
            $table->integer('old_quantity');
            $table->integer('new_quantity');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable(); // order_id or other reference
            $table->timestamps();
            
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_logs');
    }
};