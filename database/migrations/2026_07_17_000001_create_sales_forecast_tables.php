<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('target_ym', 7);
            $table->date('base_date');
            $table->unsignedInteger('version');
            $table->string('created_by_name', 100);
            $table->timestamp('submitted_at');
            $table->string('submission_status', 32)->default('submitted');
            $table->bigInteger('total_sales')->default(0);
            $table->decimal('total_qty', 12, 2)->default(0);
            $table->bigInteger('total_profit')->default(0);
            $table->timestamps();

            $table->unique(['target_ym', 'version']);
            $table->index(['target_ym', 'submission_status']);
        });

        Schema::create('sales_forecast_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_forecast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->decimal('forecast_qty_m', 12, 2)->default(0);
            $table->unsignedInteger('forecast_sales')->default(0);
            $table->bigInteger('forecast_profit')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('sales_forecast_id');
            $table->unique(
                ['sales_forecast_id', 'product_id', 'source_type', 'source_id'],
                'sales_forecast_lines_source_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_forecast_lines');
        Schema::dropIfExists('sales_forecasts');
    }
};
