// database/migrations/2024_01_01_000004_create_orders_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('seller_id'); // Direct seller for simplicity
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->enum('status', [
                'pending', 
                'confirmed', 
                'preparing', 
                'ready_for_delivery', 
                'delivered', 
                'cancelled', 
                'rejected'
            ])->default('pending');
            $table->enum('payment_method', ['cash_on_delivery'])->default('cash_on_delivery');
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->text('delivery_address');
            $table->text('notes')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            
            $table->foreign('buyer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index('order_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};