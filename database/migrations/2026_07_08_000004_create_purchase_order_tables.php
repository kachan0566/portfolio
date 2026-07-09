<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('type', 32);
            $table->string('status', 32)->default('ordered');
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('ship_to_id')->constrained();
            $table->foreignId('order_id')->nullable()->constrained();
            $table->date('order_date');
            $table->date('due_date');
            $table->text('arrival_memo')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('supplier_id');
            $table->index('order_id');
            $table->index('due_date');
        });

        Schema::create('yarn_purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('qty_kg', 12, 3)->default(0);
            $table->decimal('received_qty_kg', 12, 3)->default(0);
        });

        Schema::create('greige_purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('greige_id')->constrained();
            $table->unsignedInteger('qty_tan')->default(0);
            $table->unsignedInteger('meters_per_tan');
            $table->unsignedInteger('qty_meters')->default(0);
            $table->decimal('received_qty_tan', 8, 2)->default(0);
            $table->unsignedInteger('received_qty_m')->default(0);
            $table->string('stage', 50)->nullable();

            $table->index('greige_id');
        });

        Schema::create('product_purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('qty_tan')->default(0);
            $table->unsignedInteger('qty_meters')->default(0);
            $table->decimal('received_qty_tan', 8, 2)->default(0);
            $table->unsignedInteger('received_qty_m')->default(0);
            $table->string('stage', 50)->nullable();
            $table->date('finish_date')->nullable();
            $table->date('contact_date')->nullable();

            $table->index('product_id');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchase_orders');
        Schema::dropIfExists('greige_purchase_orders');
        Schema::dropIfExists('yarn_purchase_orders');
        Schema::dropIfExists('purchase_orders');
    }
};
