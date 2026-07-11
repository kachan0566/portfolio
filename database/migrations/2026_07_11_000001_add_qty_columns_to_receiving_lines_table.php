<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receiving_lines', function (Blueprint $table) {
            $table->decimal('qty_tan', 8, 2)->default(0)->after('line_no');
            $table->unsignedInteger('qty_m')->default(0)->after('qty_tan');
            $table->decimal('qty_kg', 12, 3)->default(0)->after('qty_m');
        });
    }

    public function down(): void
    {
        Schema::table('receiving_lines', function (Blueprint $table) {
            $table->dropColumn(['qty_tan', 'qty_m', 'qty_kg']);
        });
    }
};
