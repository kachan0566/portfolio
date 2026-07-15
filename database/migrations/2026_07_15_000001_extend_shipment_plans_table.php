<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_plans', function (Blueprint $table) {
            $table->decimal('confirmed_qty_tan', 8, 2)->default(0)->after('confirmed_qty_m');
            $table->decimal('shipped_qty_tan', 8, 2)->default(0)->after('shipped_qty_m');
        });

        Schema::table('shipment_plans', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->index(['order_id', 'status']);
            $table->index(['product_id', 'planned_ship_date']);
        });
    }

    public function down(): void
    {
        Schema::table('shipment_plans', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropIndex(['order_id', 'status']);
            $table->dropIndex(['product_id', 'planned_ship_date']);
            $table->dropColumn(['confirmed_qty_tan', 'shipped_qty_tan']);
        });
    }
};
