<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('issue_reports', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('reported_user_id')->nullable()->constrained('users')->onDelete('set null');
            $blueprint->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $blueprint->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            
            $blueprint->string('type'); // 'order', 'user', 'product', 'other'
            $blueprint->string('subject');
            $blueprint->text('description');
            $blueprint->string('evidence_image')->nullable();
            
            $blueprint->enum('status', ['pending', 'investigation', 'resolved', 'dismissed'])->default('pending');
            $blueprint->text('admin_response')->nullable();
            
            $blueprint->timestamp('resolved_at')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_reports');
    }
};
