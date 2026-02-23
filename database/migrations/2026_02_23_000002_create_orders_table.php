<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('PENDING');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('idempotency_key', 255);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_orders_user');
            $table->index('status', 'idx_orders_status');
            $table->unique('idempotency_key', 'idx_orders_idempotency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
