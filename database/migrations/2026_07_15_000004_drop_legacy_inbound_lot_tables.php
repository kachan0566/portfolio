<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shipment_lot_consumptions');
        Schema::dropIfExists('inbound_lots');
    }

    public function down(): void
    {
        // 段階8で product_rolls + shipment_roll_allocations へ移行済み。
        // 旧テーブルは復元しない（必要なら 2026_06_29_* を参照）。
    }
};
