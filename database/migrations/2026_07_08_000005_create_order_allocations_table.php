<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('allocation_type', 16);
            $table->decimal('qty_tan', 8, 2)->default(0);
            $table->unsignedInteger('qty_m')->default(0);
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('purchase_order_id');
            $table->index(['order_id', 'allocation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_allocations');
    }
};
