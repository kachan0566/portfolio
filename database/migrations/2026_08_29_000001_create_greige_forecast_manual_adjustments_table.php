<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('greige_forecast_manual_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('greige_id')->constrained()->cascadeOnDelete();
            $table->string('target_ym', 7);
            $table->decimal('adjustment_qty_m', 12, 2);
            $table->string('direction', 16);
            $table->text('reason');
            $table->string('created_by_name');
            $table->timestamps();

            $table->index(['greige_id', 'target_ym']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('greige_forecast_manual_adjustments');
    }
};
