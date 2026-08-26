<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_conversions', function (Blueprint $table) {
            $table->id();
            $table->timestamp('converted_at');
            $table->string('receiving_code', 30);
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->string('from_type', 16)->default('po');
            $table->string('to_type', 16)->default('stock');
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('order_id');
            $table->index('converted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_conversions');
    }
};
