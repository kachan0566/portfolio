<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('greige_month_end_forecasts', function (Blueprint $table) {
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

        Schema::create('greige_month_end_forecast_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('greige_month_end_forecast_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('greige_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_stock_qty', 12, 2);
            $table->decimal('inbound_scheduled_qty', 12, 2);
            $table->decimal('outbound_scheduled_qty', 12, 2);
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

        Schema::create('combined_month_end_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('target_ym', 7);
            $table->date('base_date');
            $table->unsignedInteger('version');
            $table->string('created_by_name');
            $table->timestamp('submitted_at');
            $table->string('submission_status', 32)->default('submitted');
            $table->bigInteger('total_forecast_value')->default(0);
            $table->bigInteger('total_current_stock_value')->default(0);
            $table->bigInteger('product_forecast_value')->default(0);
            $table->bigInteger('greige_forecast_value')->default(0);
            $table->json('product_summary');
            $table->json('greige_summary');
            $table->timestamps();
            $table->unique(['target_ym', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combined_month_end_forecasts');
        Schema::dropIfExists('greige_month_end_forecast_lines');
        Schema::dropIfExists('greige_month_end_forecasts');
    }
};
