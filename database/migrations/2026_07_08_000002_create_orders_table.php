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
            $table->string('code', 30)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('order_qty_mode', 16)->default('tan');
            $table->unsignedInteger('qty_tan')->default(0);
            $table->unsignedInteger('qty_meters')->default(0);
            $table->boolean('meters_overridden')->default(false);
            $table->decimal('shipped_qty_tan', 8, 2)->default(0);
            $table->unsignedInteger('shipped_qty_m')->default(0);
            $table->date('order_date');
            $table->date('due_date');
            $table->date('planned_ship_date')->nullable();
            $table->text('ship_memo')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('product_id');
            $table->index('order_date');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
