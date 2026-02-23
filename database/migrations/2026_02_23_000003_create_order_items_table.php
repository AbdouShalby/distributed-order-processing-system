<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->index('order_id', 'idx_order_items_order');
            $table->index('product_id', 'idx_order_items_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
