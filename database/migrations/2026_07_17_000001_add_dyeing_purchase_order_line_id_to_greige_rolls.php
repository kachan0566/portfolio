<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('greige_rolls', function (Blueprint $table) {
            $table->foreignId('dyeing_purchase_order_line_id')
                ->nullable()
                ->after('receiving_line_id')
                ->constrained('purchase_order_lines')
                ->nullOnDelete();

            $table->index('dyeing_purchase_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('greige_rolls', function (Blueprint $table) {
            $table->dropForeign(['dyeing_purchase_order_line_id']);
            $table->dropColumn('dyeing_purchase_order_line_id');
        });
    }
};
