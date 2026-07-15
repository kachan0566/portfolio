<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_tan', 8, 2)->default(0);
            $table->unsignedInteger('qty_m')->default(0);
            $table->date('shipped_date');
            $table->string('ship_to_name', 200)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'shipped_date']);
            $table->index(['product_id', 'shipped_date']);
        });

        Schema::create('shipment_roll_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_roll_id')->constrained()->restrictOnDelete();
            $table->decimal('consumed_tan_qty', 8, 2);
            $table->decimal('consumed_qty_m', 12, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique('product_roll_id');
            $table->index('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_roll_allocations');
        Schema::dropIfExists('shipments');
    }
};
