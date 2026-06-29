<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('receiving_code')->nullable();
            $table->date('received_date');
            $table->decimal('received_qty_m', 12, 2);
            $table->decimal('remaining_qty_m', 12, 2);
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->string('source_type', 32)->default('receiving');
            $table->timestamps();
        });

        Schema::create('shipment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('order_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('planned_ship_date');
            $table->decimal('confirmed_qty_m', 12, 2);
            $table->decimal('shipped_qty_m', 12, 2)->default(0);
            $table->string('status', 32)->default('confirmed');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shipment_lot_consumptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_ref');
            $table->foreignId('inbound_lot_id')->constrained()->cascadeOnDelete();
            $table->decimal('consumed_qty_m', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('forecast_manual_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('target_ym', 7);
            $table->decimal('adjustment_qty_m', 12, 2);
            $table->string('direction', 16);
            $table->text('reason');
            $table->string('created_by_name');
            $table->timestamps();
        });

        Schema::create('month_end_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('target_ym', 7);
            $table->date('base_date');
            $table->unsignedInteger('version');
            $table->string('created_by_name');
            $table->timestamp('submitted_at');
            $table->string('submission_status', 32)->default('submitted');
            $table->bigInteger('total_forecast_value')->default(0);
            $table->bigInteger('total_long_term_value')->default(0);
            $table->timestamps();
            $table->unique(['target_ym', 'version']);
        });

        Schema::create('month_end_forecast_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('month_end_forecast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_stock_qty', 12, 2);
            $table->decimal('inbound_scheduled_qty', 12, 2);
            $table->decimal('outbound_confirmed_qty', 12, 2);
            $table->decimal('manual_adjustment_qty', 12, 2)->default(0);
            $table->decimal('forecast_qty', 12, 2);
            $table->integer('unit_cost_snapshot')->nullable();
            $table->bigInteger('forecast_value')->default(0);
            $table->decimal('long_term_qty', 12, 2)->default(0);
            $table->bigInteger('long_term_value')->default(0);
            $table->date('oldest_received_date')->nullable();
            $table->unsignedInteger('oldest_age_months')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('month_end_forecast_lines');
        Schema::dropIfExists('month_end_forecasts');
        Schema::dropIfExists('forecast_manual_adjustments');
        Schema::dropIfExists('shipment_lot_consumptions');
        Schema::dropIfExists('shipment_plans');
        Schema::dropIfExists('inbound_lots');
    }
};
