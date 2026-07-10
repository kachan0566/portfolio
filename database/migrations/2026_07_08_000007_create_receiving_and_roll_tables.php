<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->date('received_date');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('received_date');
        });

        Schema::create('receiving_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained();
            $table->unsignedSmallInteger('line_no');
            $table->timestamps();

            $table->unique(['receiving_id', 'line_no']);
            $table->index('purchase_order_line_id');
        });

        Schema::create('greige_rolls', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('greige_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('receiving_line_id')->constrained();
            $table->decimal('tan_qty', 8, 2)->default(1.00);
            $table->decimal('actual_qty_m', 12, 2)->default(0);
            $table->unsignedInteger('nominal_meters')->nullable();
            $table->string('status', 32)->default('in_stock');
            $table->date('received_date');
            $table->timestamps();

            $table->index('greige_id');
            $table->index('status');
            $table->index(['greige_id', 'status', 'received_date']);
        });

        Schema::create('product_rolls', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('receiving_line_id')->constrained();
            $table->foreignId('parent_greige_roll_id')->nullable()->constrained('greige_rolls')->nullOnDelete();
            $table->decimal('tan_qty', 8, 2)->default(1.00);
            $table->decimal('actual_qty_m', 12, 2)->default(0);
            $table->unsignedInteger('nominal_meters')->nullable();
            $table->string('status', 32)->default('in_stock');
            $table->date('received_date');
            $table->timestamps();

            $table->index('product_id');
            $table->index('status');
            $table->index(['product_id', 'status', 'received_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_rolls');
        Schema::dropIfExists('greige_rolls');
        Schema::dropIfExists('receiving_lines');
        Schema::dropIfExists('receivings');
    }
};
