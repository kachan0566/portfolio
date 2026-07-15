<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('processing_cost')->default(0);
            $table->timestamps();
        });

        Schema::create('greige_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('greige_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('loss_rate', 5, 4)->default(0);
            $table->unsignedInteger('weaving_cost')->default(0);
            $table->timestamps();
        });

        Schema::create('greige_recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('greige_recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_per_m', 10, 4);
            $table->timestamps();
            $table->unique(['greige_recipe_id', 'material_id']);
        });

        Schema::create('material_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('ym', 7);
            $table->unsignedInteger('unit_price');
            $table->timestamps();
            $table->unique(['material_id', 'ym']);
        });

        Schema::create('yarn_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 32);
            $table->decimal('qty_kg', 12, 3);
            $table->string('reference_type', 32)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('movement_date');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('material_id');
            $table->index('movement_date');
            $table->index(['reference_type', 'reference_id']);
            $table->unique(
                ['reference_type', 'reference_id', 'movement_type'],
                'yarn_movements_ref_type_unique',
            );
        });

        Schema::create('yarn_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_kg', 12, 3);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yarn_allocations');
        Schema::dropIfExists('yarn_stock_movements');
        Schema::dropIfExists('material_prices');
        Schema::dropIfExists('greige_recipe_lines');
        Schema::dropIfExists('greige_recipes');
        Schema::dropIfExists('product_recipes');
    }
};
